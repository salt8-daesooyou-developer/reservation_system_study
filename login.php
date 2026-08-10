<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/config/database.php';

$error = '';
$initialType = ($_GET['type'] ?? '') === 'customer' ? 'customer' : 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = ($_POST['type'] ?? '') === 'customer' ? 'customer' : 'admin';
    $initialType = $type;
    $loginId = trim($_POST['login_id'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($type === 'admin') {
        $stmt = db()->prepare('SELECT id, name, role, password_hash FROM staff WHERE username = ?');
        $stmt->execute([$loginId]);
        $staff = $stmt->fetch();
        if ($staff && password_verify($password, $staff['password_hash'])) {
            $_SESSION['staff_id'] = $staff['id'];
            $_SESSION['staff_name'] = $staff['name'];
            $_SESSION['staff_role'] = $staff['role'];
            header('Location: /reservation_system_study/admin/index.php');
            exit;
        }
    } else {
        $stmt = db()->prepare('SELECT id, name, password_hash FROM customers WHERE email = ?');
        $stmt->execute([$loginId]);
        $customer = $stmt->fetch();
        if ($customer && $customer['password_hash'] && password_verify($password, $customer['password_hash'])) {
            $_SESSION['customer_id'] = $customer['id'];
            $_SESSION['customer_name'] = $customer['name'];
            header('Location: /reservation_system_study/customer/mypage.php');
            exit;
        }
    }
    $error = 'ログインIDまたはパスワードが正しくありません。';
}

if (!empty($_SESSION['staff_id'])) {
    header('Location: /reservation_system_study/admin/index.php');
    exit;
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
<title>MYPAGE LOGIN - 予約管理システム</title>
<link href="/reservation_system_study/assets/css/app.css" rel="stylesheet">
</head>
<body class="auth-page">
  <div class="auth-card">
    <div class="auth-title">MYPAGE LOGIN</div>

    <div class="auth-toggle" id="typeToggle">
      <button type="button" data-type="admin" class="<?= $initialType === 'admin' ? 'active' : '' ?>">管理者用</button>
      <button type="button" data-type="customer" class="<?= $initialType === 'customer' ? 'active' : '' ?>">顧客用</button>
    </div>

    <?php if ($error): ?>
      <div class="auth-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" id="loginForm">
      <input type="hidden" name="type" id="typeInput" value="<?= htmlspecialchars($initialType) ?>">
      <div class="auth-field">
        <label id="loginIdLabel">ログインID</label>
        <input type="text" name="login_id" id="loginIdInput" autofocus>
      </div>
      <div class="auth-field">
        <label>パスワード</label>
        <input type="password" name="password">
      </div>
      <button type="submit" class="auth-submit">ログイン</button>
    </form>

    <div class="auth-links">
      <a href="/reservation_system_study/signup.php">新規会員登録</a>
      <a href="#" id="forgotLink">ログインIDまたはパスワードを忘れた場合</a>
    </div>
  </div>

<script>
  const toggle = document.getElementById('typeToggle');
  const typeInput = document.getElementById('typeInput');
  const loginIdLabel = document.getElementById('loginIdLabel');
  const loginIdInput = document.getElementById('loginIdInput');

  function applyType(type) {
    typeInput.value = type;
    toggle.querySelectorAll('button').forEach(b => b.classList.toggle('active', b.dataset.type === type));
    if (type === 'customer') {
      loginIdLabel.textContent = 'ログインID（メールアドレス）';
      loginIdInput.type = 'email';
      loginIdInput.placeholder = 'example@salteight.com';
    } else {
      loginIdLabel.textContent = 'ログインID';
      loginIdInput.type = 'text';
      loginIdInput.placeholder = '';
    }
  }

  toggle.addEventListener('click', e => {
    const btn = e.target.closest('button[data-type]');
    if (!btn) return;
    applyType(btn.dataset.type);
  });

  applyType(typeInput.value);

  document.getElementById('forgotLink').addEventListener('click', e => {
    e.preventDefault();
    alert('管理者にお問い合わせください。');
  });
</script>
</body>
</html>
