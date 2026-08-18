<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/customer_dashboard.php';
require_customer_login();

$pdo = db();
$customerId = (int) $_SESSION['customer_id'];

$existsStmt = $pdo->prepare('SELECT id FROM customers WHERE id = ?');
$existsStmt->execute([$customerId]);
if (!$existsStmt->fetchColumn()) {
    header('Location: /reservation_system_study/customer/logout.php');
    exit;
}

$pageTitle = 'マイページ - 予約管理システム';
$activeMenu = 'home';
require __DIR__ . '/../includes/customer_header.php';
?>

<?php render_customer_dashboard($pdo, $customerId, false); ?>

<?php require __DIR__ . '/../includes/customer_footer.php'; ?>
