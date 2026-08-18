<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/database.php';
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

if ($method === 'POST') {
    $d = input();
    $customerId = (int) ($d['customer_id'] ?? 0);
    $body = trim($d['body'] ?? '');

    if (!$customerId || $body === '') {
        http_response_code(422);
        echo json_encode(['error' => 'customer_id_and_body_required']);
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO customer_notes (customer_id, body, created_by) VALUES (?, ?, ?)');
    $stmt->execute([$customerId, $body, $staffId]);
    echo json_encode(['id' => (int) $pdo->lastInsertId()]);
    exit;
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) {
        http_response_code(422);
        echo json_encode(['error' => 'id_required']);
        exit;
    }
    $pdo->prepare('DELETE FROM customer_notes WHERE id = ?')->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method_not_allowed']);
