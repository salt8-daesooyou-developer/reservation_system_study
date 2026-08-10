<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/branch.php';

header('Content-Type: application/json; charset=utf-8');
$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

function input(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// GET(店舗一覧)は会員登録フォームでも使うため未ログインでも許可。書き込み系のみ要ログイン。
if ($method === 'GET') {
    $rows = $pdo->query('SELECT id, name, brand FROM branches ORDER BY name')->fetchAll();
    echo json_encode(['branches' => $rows, 'current_id' => empty($_SESSION['staff_id']) ? null : current_branch_id()]);
    exit;
}

require_login_api();

if ($method === 'POST' && isset($_GET['switch'])) {
    $d = input();
    $id = (int) ($d['branch_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT id FROM branches WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'branch_not_found']);
        exit;
    }
    $_SESSION['branch_id'] = $id;
    echo json_encode(['ok' => true]);
    exit;
}

if ($method === 'POST') {
    require_admin_api();
    $d = input();
    $name = trim($d['name'] ?? '');
    if ($name === '') {
        http_response_code(422);
        echo json_encode(['error' => 'name_required']);
        exit;
    }
    $brand = ($d['brand'] ?? '') === 'EN' ? 'EN' : 'RIZZ';
    $stmt = $pdo->prepare('INSERT INTO branches (name, brand) VALUES (?, ?)');
    try {
        $stmt->execute([$name, $brand]);
    } catch (PDOException $e) {
        http_response_code(422);
        echo json_encode(['error' => 'duplicate_name']);
        exit;
    }
    $id = (int) $pdo->lastInsertId();
    $_SESSION['branch_id'] = $id;
    echo json_encode(['id' => $id]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method_not_allowed']);
