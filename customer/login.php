<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT id, name, password_hash FROM customers WHERE phone = ?');
    $stmt->execute([$phone]);
    $customer = $stmt->fetch();

    if ($customer && $customer['password_hash'] && password_verify($password, $customer['password_hash'])) {
        $_SESSION['customer_id'] = $customer['id'];
        $_SESSION['customer_name'] = $customer['name'];
        header('Location: /reservation_system_study/customer/mypage.php');
        exit;
    }
    $error = '連絡先またはパスワードが正しくありません。';
}

if (!empty($_SESSION['customer_id'])) {
    header('Location: /reservation_system_study/customer/mypage.php');
    exit;
}
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>顧客ログイン - 予約管理システム</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="/reservation_system_study/assets/css/app.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height:100vh;">
  <div class="panel" style="width:340px;">
    <div class="text-center mb-4">
      <div style="color:var(--accent); font-weight:800; font-size:24px;">RSVP</div>
      <div class="text-secondary" style="font-size:13px;">顧客専用ログイン</div>
    </div>
    <?php if ($error): ?>
      <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
      <div class="mb-3">
        <label class="form-label">連絡先</label>
        <input type="text" name="phone" class="form-control" required autofocus placeholder="090-1234-5678">
      </div>
      <div class="mb-3">
        <label class="form-label">パスワード</label>
        <div class="password-wrap">
          <input type="password" name="password" id="cPassword" class="form-control" required>
          <button type="button" class="password-toggle" data-target="cPassword">👁</button>
        </div>
      </div>
      <button type="submit" class="btn-accent w-100">ログイン</button>
    </form>
    <div class="text-center mt-3">
      <a href="/reservation_system_study/signup.php" style="font-size:13px;">新規会員登録はこちら</a>
    </div>
    <div class="text-center mt-1">
      <a href="/reservation_system_study/admin/login.php" style="font-size:12px; color:var(--text-dim);">スタッフの方はこちら</a>
    </div>
  </div>
<script src="/reservation_system_study/assets/js/password-toggle.js"></script>
</body>
</html>
