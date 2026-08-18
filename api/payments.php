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
$customerId = (int) ($d['customer_id'] ?? 0);
$type = ($d['type'] ?? '') === 'refund' ? 'refund' : 'sale';
$amount = (float) ($d['amount'] ?? 0);
$memo = trim($d['memo'] ?? '');

if (!$customerId || $amount <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'customer_id_and_amount_required']);
    exit;
}

$cStmt = $pdo->prepare('SELECT id, name FROM customers WHERE id = ?');
$cStmt->execute([$customerId]);
$customer = $cStmt->fetch();
if (!$customer) {
    http_response_code(404);
    echo json_encode(['error' => 'customer_not_found']);
    exit;
}

$stmt = $pdo->prepare('
    INSERT INTO payments (customer_id, customer_membership_id, product_id, type, amount, method, memo, created_by)
    VALUES (?, ?, ?, ?, ?, "manual", ?, ?)
');
$stmt->execute([
    $customerId,
    $d['customer_membership_id'] ?? null,
    $d['product_id'] ?? null,
    $type,
    $amount,
    $memo ?: null,
    $staffId,
]);

$label = $type === 'refund' ? '返金' : '売上';
log_activity($pdo, $customerId, '決済', 'payment_' . $type, "{$label} ¥" . number_format($amount) . ' を手動登録しました', $staffId);

echo json_encode(['id' => (int) $pdo->lastInsertId()]);
