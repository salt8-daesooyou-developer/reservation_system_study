<?php
require __DIR__ . '/../includes/auth.php';
unset($_SESSION['customer_id'], $_SESSION['customer_name']);
header('Location: /reservation_system_study/login.php?type=customer');
exit;
