<?php
/**
 * @var string $pageTitle
 * @var string $activeMenu one of: home, booking, account, subscribe, contact
 */
require_once __DIR__ . '/auth.php';
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle ?? '予約管理システム') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Noto+Sans+JP:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="/reservation_system_study/assets/css/app.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<div class="customer-topbar">
  <div class="brand">RSVP</div>
  <nav class="customer-nav">
    <a class="<?= ($activeMenu ?? '') === 'home' ? 'active' : '' ?>" href="/reservation_system_study/customer/mypage.php">ホーム</a>
    <a class="<?= ($activeMenu ?? '') === 'booking' ? 'active' : '' ?>" href="/reservation_system_study/customer/booking.php">予約する</a>
    <a class="<?= ($activeMenu ?? '') === 'account' ? 'active' : '' ?>" href="/reservation_system_study/customer/account.php">プロフィール設定</a>
    <a class="<?= ($activeMenu ?? '') === 'subscribe' ? 'active' : '' ?>" href="/reservation_system_study/customer/subscribe.php">月額プラン</a>
    <a class="<?= ($activeMenu ?? '') === 'contact' ? 'active' : '' ?>" href="/reservation_system_study/customer/contact.php">お問い合わせ</a>
  </nav>
  <div class="customer-user">
    <?= htmlspecialchars($_SESSION['customer_name'] ?? '') ?> 様
    <a href="/reservation_system_study/customer/logout.php">ログアウト</a>
  </div>
</div>
<div class="customer-content">
