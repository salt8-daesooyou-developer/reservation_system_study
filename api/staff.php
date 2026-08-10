<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/database.php';
require_admin_api();

header('Content-Type: application/json; charset=utf-8');
$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

function input(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

if ($method === 'GET') {
    $rows = $pdo->query('SELECT id, username, name, role, created_at FROM staff ORDER BY id')->fetchAll();
    echo json_encode($rows);
    exit;
}

if ($method === 'POST') {
    $d = input();
    $username = trim($d['username'] ?? '');
    $password = $d['password'] ?? '';
    $name = trim($d['name'] ?? '');
    $role = in_array($d['role'] ?? '', ['admin', 'staff'], true) ? $d['role'] : 'staff';

    if ($username === '' || $password === '' || $name === '') {
        http_response_code(422);
        echo json_encode(['error' => 'required_fields_missing']);
        exit;
    }
    if (strlen($password) < 4) {
        http_response_code(422);
        echo json_encode(['error' => 'password_too_short']);
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO staff (username, password_hash, name, role) VALUES (?, ?, ?, ?)');
    try {
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $name, $role]);
    } catch (PDOException $e) {
        http_response_code(422);
        echo json_encode(['error' => 'duplicate_username']);
        exit;
    }
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
    if ($id === (int) $_SESSION['staff_id']) {
        http_response_code(422);
        echo json_encode(['error' => 'cannot_delete_self']);
        exit;
    }
    $pdo->prepare('DELETE FROM staff WHERE id = ?')->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method_not_allowed']);
