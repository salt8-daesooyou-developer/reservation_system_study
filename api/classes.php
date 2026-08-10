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

if ($method === 'GET') {
    echo json_encode($pdo->query('SELECT * FROM classes ORDER BY category, name')->fetchAll());
    exit;
}

if ($method === 'POST') {
    $d = input();
    $name = trim($d['name'] ?? '');
    $category = trim($d['category'] ?? '');
    $capacity = (int) ($d['capacity'] ?? 10);

    if ($name === '') {
        http_response_code(422);
        echo json_encode(['error' => 'name_required']);
        exit;
    }
    if ($capacity < 1) {
        http_response_code(422);
        echo json_encode(['error' => 'invalid_capacity']);
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO classes (name, category, capacity) VALUES (?, ?, ?)');
    try {
        $stmt->execute([$name, $category ?: null, $capacity]);
    } catch (PDOException $e) {
        http_response_code(422);
        echo json_encode(['error' => 'duplicate_name']);
        exit;
    }
    echo json_encode(['id' => (int) $pdo->lastInsertId()]);
    exit;
}

if ($method === 'PUT') {
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) {
        http_response_code(422);
        echo json_encode(['error' => 'id_required']);
        exit;
    }
    $d = input();
    $name = trim($d['name'] ?? '');
    $category = trim($d['category'] ?? '');
    $capacity = (int) ($d['capacity'] ?? 10);

    if ($name === '') {
        http_response_code(422);
        echo json_encode(['error' => 'name_required']);
        exit;
    }
    if ($capacity < 1) {
        http_response_code(422);
        echo json_encode(['error' => 'invalid_capacity']);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE classes SET name = ?, category = ?, capacity = ? WHERE id = ?');
    try {
        $stmt->execute([$name, $category ?: null, $capacity, $id]);
    } catch (PDOException $e) {
        http_response_code(422);
        echo json_encode(['error' => 'duplicate_name']);
        exit;
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) {
        http_response_code(422);
        echo json_encode(['error' => 'id_required']);
        exit;
    }
    try {
        $pdo->prepare('DELETE FROM classes WHERE id = ?')->execute([$id]);
    } catch (PDOException $e) {
        http_response_code(422);
        echo json_encode(['error' => 'class_in_use']);
        exit;
    }
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method_not_allowed']);
