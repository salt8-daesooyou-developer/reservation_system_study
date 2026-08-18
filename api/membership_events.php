<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/activity_log.php';
require_login_api();

header('Content-Type: application/json; charset=utf-8');
$pdo = db();
$staffId = (int) $_SESSION['staff_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

function input(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

$d = input();
$membershipId = (int) ($d['customer_membership_id'] ?? 0);
$action = $d['action'] ?? '';

$mStmt = $pdo->prepare('
    SELECT cm.*, mp.name AS product_name
    FROM customer_memberships cm
    JOIN membership_products mp ON mp.id = cm.product_id
    WHERE cm.id = ?
');
$mStmt->execute([$membershipId]);
$membership = $mStmt->fetch();
if (!$membership) {
    http_response_code(404);
    echo json_encode(['error' => 'membership_not_found']);
    exit;
}

if ($action === 'hold') {
    $pdo->prepare('UPDATE customer_memberships SET status = "hold" WHERE id = ?')->execute([$membershipId]);
    $detail = 'ホールド開始' . (!empty($d['memo']) ? '（' . $d['memo'] . '）' : '');
    $pdo->prepare('INSERT INTO membership_events (customer_membership_id, type, detail, created_by) VALUES (?, "hold", ?, ?)')
        ->execute([$membershipId, $detail, $staffId]);
    log_activity($pdo, $membership['customer_id'], 'CRM', 'membership_hold', $membership['product_name'] . ' をホールドしました', $staffId);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'resume') {
    $pdo->prepare('UPDATE customer_memberships SET status = "active" WHERE id = ?')->execute([$membershipId]);
    $pdo->prepare('INSERT INTO membership_events (customer_membership_id, type, detail, created_by) VALUES (?, "hold", "ホールド解除（再開）", ?)')
        ->execute([$membershipId, $staffId]);
    log_activity($pdo, $membership['customer_id'], 'CRM', 'membership_resume', $membership['product_name'] . ' のホールドを解除しました', $staffId);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'extend') {
    $days = (int) ($d['days'] ?? 0);
    if ($days <= 0 || !$membership['end_date']) {
        http_response_code(422);
        echo json_encode(['error' => 'invalid_extend']);
        exit;
    }
    $newEnd = date('Y-m-d', strtotime($membership['end_date'] . " +{$days} days"));
    $pdo->prepare('UPDATE customer_memberships SET end_date = ? WHERE id = ?')->execute([$newEnd, $membershipId]);
    $detail = "{$days}日延長（{$membership['end_date']} → {$newEnd}）";
    $pdo->prepare('INSERT INTO membership_events (customer_membership_id, type, detail, created_by) VALUES (?, "extend", ?, ?)')
        ->execute([$membershipId, $detail, $staffId]);
    log_activity($pdo, $membership['customer_id'], 'CRM', 'membership_extend', $membership['product_name'] . " を{$days}日延長しました", $staffId);
    echo json_encode(['ok' => true, 'end_date' => $newEnd]);
    exit;
}

if ($action === 'transfer') {
    $toCustomerId = (int) ($d['to_customer_id'] ?? 0);
    if (!$toCustomerId) {
        http_response_code(422);
        echo json_encode(['error' => 'to_customer_id_required']);
        exit;
    }
    $toStmt = $pdo->prepare('SELECT name FROM customers WHERE id = ?');
    $toStmt->execute([$toCustomerId]);
    $toName = $toStmt->fetchColumn();
    if (!$toName) {
        http_response_code(404);
        echo json_encode(['error' => 'target_customer_not_found']);
        exit;
    }

    $fromCustomerId = (int) $membership['customer_id'];
    $pdo->prepare('UPDATE customer_memberships SET customer_id = ? WHERE id = ?')->execute([$toCustomerId, $membershipId]);
    $detail = "{$membership['product_name']} を譲渡（→ {$toName}）";
    $pdo->prepare('
        INSERT INTO membership_events (customer_membership_id, type, detail, from_customer_id, to_customer_id, created_by)
        VALUES (?, "transfer", ?, ?, ?, ?)
    ')->execute([$membershipId, $detail, $fromCustomerId, $toCustomerId, $staffId]);
    log_activity($pdo, $fromCustomerId, 'CRM', 'membership_transfer', "{$membership['product_name']} を {$toName} に譲渡しました", $staffId);
    log_activity($pdo, $toCustomerId, 'CRM', 'membership_transfer', "{$membership['product_name']} を譲り受けました", $staffId);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(422);
echo json_encode(['error' => 'invalid_action']);
