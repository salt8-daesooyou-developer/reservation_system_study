<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/customer_dashboard.php';
require_login();

$customerId = (int) ($_GET['id'] ?? 0);
$pdo = db();
$stmt = $pdo->prepare('SELECT name FROM customers WHERE id = ?');
$stmt->execute([$customerId]);
$customerName = $stmt->fetchColumn();
if (!$customerName) {
    header('Location: /reservation_system_study/admin/customers.php');
    exit;
}

$pageTitle = htmlspecialchars($customerName) . ' の詳細';
$activeMenu = 'customers';
require __DIR__ . '/../includes/header.php';
?>

<div class="mb-3">
  <a href="/reservation_system_study/admin/customers.php">‹ 顧客一覧に戻る</a>
</div>

<?php render_customer_dashboard($pdo, $customerId, true); ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
