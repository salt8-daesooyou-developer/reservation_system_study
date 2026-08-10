<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/database.php';
require_login_api();

header('Content-Type: application/json; charset=utf-8');
$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

function input(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

$validStatuses = ['new', 'replied', 'closed'];

if ($method === 'GET') {
    $rows = $pdo->query('
        SELECT i.*, c.name AS customer_name, c.email AS customer_email
        FROM inquiries i JOIN customers c ON c.id = i.customer_id
        ORDER BY i.created_at DESC
    ')->fetchAll();
    echo json_encode($rows);
    exit;
}

if ($method === 'PUT') {
    $id = (int) ($_GET['id'] ?? 0);
    $d = input();
    $status = $d['status'] ?? '';
    if (!$id || !in_array($status, $validStatuses, true)) {
        http_response_code(422);
        echo json_encode(['error' => 'invalid_request']);
        exit;
    }
    $pdo->prepare('UPDATE inquiries SET status = ? WHERE id = ?')->execute([$status, $id]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method_not_allowed']);
