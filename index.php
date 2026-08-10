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
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="/reservation_system_study/assets/css/app.css" rel="stylesheet">
</head>
<body>
<div style="max-width:560px; margin:0 auto; padding:48px 16px;">
  <div class="text-center mb-4">
    <div style="color:var(--accent); font-weight:800; font-size:28px;">RSVP</div>
    <div class="text-secondary" style="font-size:13px;">予約管理システム</div>
  </div>

  <?php if ($isStaff): ?>
  <div class="panel mb-3">
    <h6 class="mb-3">管理者メニュー</h6>
    <div class="d-grid gap-2">
      <a class="btn btn-outline-light text-start" href="/reservation_system_study/admin/index.php">📊 ダッシュボード</a>
      <a class="btn btn-outline-light text-start" href="/reservation_system_study/admin/customers.php">👤 顧客管理</a>
      <a class="btn btn-outline-light text-start" href="/reservation_system_study/admin/calendar.php">📅 スケジュール管理</a>
      <?php if ($isAdmin): ?>
      <a class="btn btn-outline-light text-start" href="/reservation_system_study/admin/staff.php">🔑 スタッフ管理</a>
      <a class="btn btn-outline-light text-start" href="/reservation_system_study/admin/db_schema.php">🗄️ DBスキーマ</a>
      <?php endif; ?>
      <a class="btn btn-outline-danger text-start" href="/reservation_system_study/admin/logout.php">ログアウト</a>
    </div>
  </div>
  <?php elseif ($isCustomer): ?>
  <div class="panel mb-3">
    <h6 class="mb-3">マイメニュー</h6>
    <div class="d-grid gap-2">
      <a class="btn btn-outline-light text-start" href="/reservation_system_study/customer/mypage.php">🏠 ホーム</a>
      <a class="btn btn-outline-light text-start" href="/reservation_system_study/customer/booking.php">📅 予約する</a>
      <a class="btn btn-outline-danger text-start" href="/reservation_system_study/customer/logout.php">ログアウト</a>
    </div>
  </div>
  <?php else: ?>
  <div class="panel mb-3">
    <h6 class="mb-3">お客様</h6>
    <div class="d-grid gap-2">
      <a class="btn-accent text-start" href="/reservation_system_study/customer/login.php">顧客ログイン</a>
      <a class="btn btn-outline-light text-start" href="/reservation_system_study/signup.php">新規会員登録</a>
    </div>
  </div>
  <div class="panel">
    <h6 class="mb-3">スタッフ</h6>
    <div class="d-grid gap-2">
      <a class="btn btn-outline-light text-start" href="/reservation_system_study/admin/login.php">スタッフログイン</a>
    </div>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
