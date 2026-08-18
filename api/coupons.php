<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/activity_log.php';
require_login_api();

header('Content-Type: application/json; charset=utf-8');
$pdo = db();
$staffId = (int) $_SESSION['staff_id'];
$method = $_SERVER['REQUEST_METHOD'];

function input(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'mileage') {
    $d = input();
    $customerId = (int) ($d['customer_id'] ?? 0);
    $points = (int) ($d['points'] ?? 0);
    $reason = trim($d['reason'] ?? '');

    if (!$customerId || $points === 0) {
        http_response_code(422);
        echo json_encode(['error' => 'customer_id_and_points_required']);
        exit;
    }

    $pdo->beginTransaction();
    $pdo->prepare('UPDATE customers SET mileage_points = mileage_points + ? WHERE id = ?')->execute([$points, $customerId]);
    $pdo->prepare('INSERT INTO mileage_logs (customer_id, points, reason, created_by) VALUES (?, ?, ?, ?)')
        ->execute([$customerId, $points, $reason ?: null, $staffId]);
    $pdo->commit();

    $label = $points > 0 ? '積立' : '使用';
    log_activity($pdo, $customerId, 'CRM', 'mileage_' . ($points > 0 ? 'add' : 'use'), "マイレージ{$label} " . abs($points) . 'pt', $staffId);

    echo json_encode(['ok' => true]);
    exit;
}

if ($method === 'POST') {
    $d = input();
    $customerId = (int) ($d['customer_id'] ?? 0);
    $name = trim($d['name'] ?? '');
    $discount = (float) ($d['discount_amount'] ?? 0);

    if (!$customerId || $name === '') {
        http_response_code(422);
        echo json_encode(['error' => 'customer_id_and_name_required']);
        exit;
    }

    $stmt = $pdo->prepare('
        INSERT INTO coupons (customer_id, name, discount_amount, valid_until, created_by)
        VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([$customerId, $name, $discount, $d['valid_until'] ?: null, $staffId]);

    log_activity($pdo, $customerId, 'CRM', 'coupon_issue', "クーポン「{$name}」を発行しました", $staffId);

    echo json_encode(['id' => (int) $pdo->lastInsertId()]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method_not_allowed']);
