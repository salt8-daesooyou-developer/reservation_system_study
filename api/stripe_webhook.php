<?php
// Stripe Webhook 受信エンドポイント。
// ローカル開発では `stripe listen --forward-to localhost/reservation_system_study/api/stripe_webhook.php`
// のように公開URLへのフォワーディングが無いと Stripe から届かない点に注意。
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/stripe_client.php';

header('Content-Type: application/json; charset=utf-8');

$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$webhookSecret = stripe_config()['webhook_secret'];

if ($webhookSecret === '' || !stripe_verify_webhook_signature($payload, $sigHeader, $webhookSecret)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_signature']);
    exit;
}

$event = json_decode($payload, true);
if (!$event) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_payload']);
    exit;
}

$pdo = db();

function upsert_membership_from_subscription(PDO $pdo, array $subRow, string $subscriptionStatus, ?int $periodEndTs): void {
    $statusMap = [
        'active' => 'active',
        'trialing' => 'active',
        'past_due' => 'hold',
        'canceled' => 'expired',
        'unpaid' => 'hold',
        'incomplete' => 'pending',
        'incomplete_expired' => 'expired',
    ];
    $membershipStatus = $statusMap[$subscriptionStatus] ?? 'pending';
    $endDate = $periodEndTs ? date('Y-m-d', $periodEndTs) : null;

    if ($subRow['customer_membership_id']) {
        $pdo->prepare('UPDATE customer_memberships SET status = ?, end_date = ? WHERE id = ?')
            ->execute([$membershipStatus, $endDate, $subRow['customer_membership_id']]);
        return;
    }

    $stmt = $pdo->prepare('
        INSERT INTO customer_memberships (customer_id, product_id, start_date, end_date, status)
        VALUES (?, ?, CURDATE(), ?, ?)
    ');
    $stmt->execute([$subRow['customer_id'], $subRow['product_id'], $endDate, $membershipStatus]);
    $membershipId = (int) $pdo->lastInsertId();
    $pdo->prepare('UPDATE stripe_subscriptions SET customer_membership_id = ? WHERE id = ?')
        ->execute([$membershipId, $subRow['id']]);
}

$type = $event['type'] ?? '';
$obj = $event['data']['object'] ?? [];

if ($type === 'checkout.session.completed') {
    $sessionId = $obj['id'] ?? null;
    $stripeSubscriptionId = $obj['subscription'] ?? null;
    if ($sessionId) {
        $stmt = $pdo->prepare('SELECT * FROM stripe_subscriptions WHERE stripe_checkout_session_id = ?');
        $stmt->execute([$sessionId]);
        $subRow = $stmt->fetch();
        if ($subRow) {
            $pdo->prepare('UPDATE stripe_subscriptions SET stripe_subscription_id = ?, status = "active" WHERE id = ?')
                ->execute([$stripeSubscriptionId, $subRow['id']]);
            $subRow['status'] = 'active';
            upsert_membership_from_subscription($pdo, $subRow, 'active', null);
        }
    }
} elseif (in_array($type, ['customer.subscription.updated', 'customer.subscription.deleted'], true)) {
    $stripeSubscriptionId = $obj['id'] ?? null;
    $status = $obj['status'] ?? 'incomplete';
    $periodEnd = $obj['current_period_end'] ?? null;
    if ($stripeSubscriptionId) {
        $stmt = $pdo->prepare('SELECT * FROM stripe_subscriptions WHERE stripe_subscription_id = ?');
        $stmt->execute([$stripeSubscriptionId]);
        $subRow = $stmt->fetch();
        if ($subRow) {
            $dbStatus = $type === 'customer.subscription.deleted' ? 'canceled' : $status;
            $pdo->prepare('UPDATE stripe_subscriptions SET status = ?, current_period_end = ? WHERE id = ?')
                ->execute([$dbStatus, $periodEnd ? date('Y-m-d H:i:s', $periodEnd) : null, $subRow['id']]);
            upsert_membership_from_subscription($pdo, $subRow, $type === 'customer.subscription.deleted' ? 'canceled' : $status, $periodEnd);
        }
    }
}

echo json_encode(['received' => true]);
