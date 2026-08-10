<?php
require __DIR__ . '/includes/auth.php';

$isStaff = !empty($_SESSION['staff_id']);
$isAdmin = ($_SESSION['staff_role'] ?? '') === 'admin';
$isCustomer = !empty($_SESSION['customer_id']);
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>予約管理システム</title>
<link href="/reservation_system_study/assets/css/app.css" rel="stylesheet">
</head>
<body class="auth-page">
  <div class="auth-card">
    <div class="auth-title">RSVP</div>
    <div class="auth-subtitle">予約管理システム</div>

    <?php if ($isStaff): ?>
    <div class="auth-section">
      <div class="auth-section-title">管理者メニュー</div>
      <a class="auth-btn outline" href="/reservation_system_study/admin/index.php">📊 ダッシュボード</a>
      <a class="auth-btn outline" href="/reservation_system_study/admin/customers.php">👤 顧客管理</a>
      <a class="auth-btn outline" href="/reservation_system_study/admin/calendar.php">📅 スケジュール管理</a>
      <?php if ($isAdmin): ?>
      <a class="auth-btn outline" href="/reservation_system_study/admin/staff.php">🔑 スタッフ管理</a>
      <a class="auth-btn outline" href="/reservation_system_study/admin/db_schema.php">🗄️ DBスキーマ</a>
      <?php endif; ?>
      <a class="auth-btn danger" href="/reservation_system_study/admin/logout.php">ログアウト</a>
    </div>
    <?php elseif ($isCustomer): ?>
    <div class="auth-section">
      <div class="auth-section-title">マイメニュー</div>
      <a class="auth-btn outline" href="/reservation_system_study/customer/mypage.php">🏠 ホーム</a>
      <a class="auth-btn outline" href="/reservation_system_study/customer/booking.php">📅 予約する</a>
      <a class="auth-btn danger" href="/reservation_system_study/customer/logout.php">ログアウト</a>
    </div>
    <?php else: ?>
    <div class="auth-section">
      <div class="auth-section-title">お客様</div>
      <a class="auth-btn primary" href="/reservation_system_study/login.php?type=customer">顧客ログイン</a>
      <a class="auth-btn outline" href="/reservation_system_study/signup.php">新規会員登録</a>
    </div>
    <div class="auth-section">
      <div class="auth-section-title">スタッフ</div>
      <a class="auth-btn outline" href="/reservation_system_study/login.php?type=admin">スタッフログイン</a>
    </div>
    <?php endif; ?>
  </div>
</body>
</html>
