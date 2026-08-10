<?php
require __DIR__ . '/../includes/auth.php';
$_SESSION = [];
session_destroy();
header('Location: /reservation_system_study/index.php?type=admin');
exit;
