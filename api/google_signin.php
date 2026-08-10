<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/google_client.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}
if (!google_is_configured()) {
    http_response_code(503);
    echo json_encode(['error' => 'google_not_configured']);
    exit;
}

$raw = file_get_contents('php://input');
$d = json_decode($raw, true) ?: [];
$credential = $d['credential'] ?? '';

$payload = google_verify_id_token($credential);
if (!$payload) {
    http_response_code(401);
    echo json_encode(['error' => 'invalid_token']);
    exit;
}

$pdo = db();

// 1. 既に google_sub で連携済みの顧客
$stmt = $pdo->prepare('SELECT id, name FROM customers WHERE google_sub = ?');
$stmt->execute([$payload['sub']]);
$customer = $stmt->fetch();

// 2. 同じメールで既存登録済みの顧客がいれば、初回ログイン時に自動連携
if (!$customer) {
    $stmt = $pdo->prepare('SELECT id, name FROM customers WHERE email = ?');
    $stmt->execute([$payload['email']]);
    $customer = $stmt->fetch();
    if ($customer) {
        $pdo->prepare('UPDATE customers SET google_sub = ? WHERE id = ?')
            ->execute([$payload['sub'], $customer['id']]);
    }
}

// 3. どちらにも該当しなければ新規登録（電話番号・店舗は未設定のまま。設定画面で後から入力）
$isNew = false;
if (!$customer) {
    $stmt = $pdo->prepare('
        INSERT INTO customers (name, email, google_sub, status)
        VALUES (?, ?, ?, "unregistered")
    ');
    $stmt->execute([$payload['name'], $payload['email'], $payload['sub']]);
    $customer = ['id' => (int) $pdo->lastInsertId(), 'name' => $payload['name']];
    $isNew = true;
}

$_SESSION['customer_id'] = $customer['id'];
$_SESSION['customer_name'] = $customer['name'];
echo json_encode(['ok' => true, 'is_new' => $isNew]);
