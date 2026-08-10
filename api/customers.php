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

$validStatuses = ['active', 'expired', 'pending', 'hold', 'unregistered'];

if ($method === 'GET' && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $stmt = $pdo->prepare('
        SELECT c.*, b.name AS branch_name
        FROM customers c LEFT JOIN branches b ON b.id = c.branch_id
        WHERE c.id = ?
    ');
    $stmt->execute([$id]);
    $customer = $stmt->fetch();
    if (!$customer) {
        http_response_code(404);
        echo json_encode(['error' => 'not_found']);
        exit;
    }
    $mStmt = $pdo->prepare('
        SELECT cm.*, mp.name AS product_name, mp.type AS product_type
        FROM customer_memberships cm
        JOIN membership_products mp ON mp.id = cm.product_id
        WHERE cm.customer_id = ?
        ORDER BY cm.start_date DESC
    ');
    $mStmt->execute([$id]);
    $customer['memberships'] = $mStmt->fetchAll();
    echo json_encode($customer);
    exit;
}

if ($method === 'GET') {
    $status = $_GET['status'] ?? '';
    $q = trim($_GET['q'] ?? '');

    $sql = 'SELECT c.*, b.name AS branch_name FROM customers c LEFT JOIN branches b ON b.id = c.branch_id WHERE 1=1';
    $params = [];

    if ($status !== '' && in_array($status, $validStatuses, true)) {
        $sql .= ' AND c.status = ?';
        $params[] = $status;
    }
    if ($q !== '') {
        $sql .= ' AND (c.name LIKE ? OR c.phone LIKE ?)';
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    $sql .= ' ORDER BY c.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($method === 'POST') {
    $d = input();
    $name = trim($d['name'] ?? '');
    if ($name === '') {
        http_response_code(422);
        echo json_encode(['error' => 'name_required']);
        exit;
    }
    $status = in_array($d['status'] ?? '', $validStatuses, true) ? $d['status'] : 'unregistered';

    $stmt = $pdo->prepare('
        INSERT INTO customers (name, name_kana, gender, birth_date, phone, email, branch_id, status, memo)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $name,
        $d['name_kana'] ?? null,
        in_array($d['gender'] ?? '', ['male','female'], true) ? $d['gender'] : 'unknown',
        $d['birth_date'] ?: null,
        $d['phone'] ?? null,
        $d['email'] ?? null,
        $d['branch_id'] ?: null,
        $status,
        $d['memo'] ?? null,
    ]);
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
    if ($name === '') {
        http_response_code(422);
        echo json_encode(['error' => 'name_required']);
        exit;
    }
    $status = in_array($d['status'] ?? '', $validStatuses, true) ? $d['status'] : 'unregistered';

    $stmt = $pdo->prepare('
        UPDATE customers SET name=?, name_kana=?, gender=?, birth_date=?, phone=?, email=?, branch_id=?, status=?, memo=?
        WHERE id=?
    ');
    $stmt->execute([
        $name,
        $d['name_kana'] ?? null,
        in_array($d['gender'] ?? '', ['male','female'], true) ? $d['gender'] : 'unknown',
        $d['birth_date'] ?: null,
        $d['phone'] ?? null,
        $d['email'] ?? null,
        $d['branch_id'] ?: null,
        $status,
        $d['memo'] ?? null,
        $id,
    ]);
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
    $stmt = $pdo->prepare('DELETE FROM customers WHERE id = ?');
    $stmt->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method_not_allowed']);
