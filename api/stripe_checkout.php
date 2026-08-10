<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/stripe_client.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}
if (!stripe_is_configured()) {
    http_response_code(503);
    echo json_encode(['error' => 'stripe_not_configured']);
    exit;
}

$raw = file_get_contents('php://input');
$d = json_decode($raw, true) ?: [];
$productId = (int) ($d['product_id'] ?? 0);
if (!$productId) {
    http_response_code(422);
    echo json_encode(['error' => 'product_id_required']);
    exit;
}

$pdo = db();
$customerId = (int) $_SESSION['customer_id'];

$cStmt = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
$cStmt->execute([$customerId]);
$customer = $cStmt->fetch();

$pStmt = $pdo->prepare('SELECT * FROM membership_products WHERE id = ?');
$pStmt->execute([$productId]);
$product = $pStmt->fetch();

if (!$customer || !$product) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}
if (empty($product['stripe_price_id'])) {
    http_response_code(422);
    echo json_encode(['error' => 'product_not_configured']);
    exit;
}

try {
    $stripeCustomerId = stripe_get_or_create_customer($pdo, $customer);

    $baseUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/reservation_system_study';
    $session = stripe_create_subscription_checkout(
        $stripeCustomerId,
        $product['stripe_price_id'],
        $baseUrl . '/customer/subscribe.php?success=1',
        $baseUrl . '/customer/subscribe.php?canceled=1'
    );

    $insert = $pdo->prepare('
        INSERT INTO stripe_subscriptions (customer_id, product_id, stripe_checkout_session_id, status)
        VALUES (?, ?, ?, "pending")
    ');
    $insert->execute([$customerId, $productId, $session['id']]);

    echo json_encode(['url' => $session['url']]);
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode(['error' => 'stripe_error', 'message' => $e->getMessage()]);
}
