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

if ($method === 'GET' && isset($_GET['products'])) {
    echo json_encode($pdo->query('SELECT * FROM membership_products ORDER BY id')->fetchAll());
    exit;
}

if ($method === 'POST') {
    $d = input();
    $customerId = (int) ($d['customer_id'] ?? 0);
    $productId = (int) ($d['product_id'] ?? 0);
    $startDate = $d['start_date'] ?? date('Y-m-d');

    if (!$customerId || !$productId) {
        http_response_code(422);
        echo json_encode(['error' => 'customer_id_and_product_id_required']);
        exit;
    }

    $pStmt = $pdo->prepare('SELECT * FROM membership_products WHERE id = ?');
    $pStmt->execute([$productId]);
    $product = $pStmt->fetch();
    if (!$product) {
        http_response_code(404);
        echo json_encode(['error' => 'product_not_found']);
        exit;
    }

    $endDate = null;
    if ($product['type'] === 'period' && $product['valid_days']) {
        $endDate = date('Y-m-d', strtotime($startDate . " +{$product['valid_days']} days"));
    }
    $remaining = $product['type'] === 'count' ? (int) $product['session_count'] : null;

    $pdo->beginTransaction();
    $stmt = $pdo->prepare('
        INSERT INTO customer_memberships (customer_id, product_id, start_date, end_date, remaining_count, status)
        VALUES (?, ?, ?, ?, ?, "active")
    ');
    $stmt->execute([$customerId, $productId, $startDate, $endDate, $remaining]);
    $newId = (int) $pdo->lastInsertId();

    $pdo->prepare("UPDATE customers SET status = 'active' WHERE id = ?")->execute([$customerId]);
    $pdo->commit();

    echo json_encode(['id' => $newId]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method_not_allowed']);
