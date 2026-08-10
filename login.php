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
<style>
  :root {
    --lp-bg: #eef7fc;
    --lp-card: #ffffff;
    --lp-text: #1a1a1a;
    --lp-dim: #7a8a99;
    --lp-blue: #1c7fae;
    --lp-blue-dark: #135f83;
    --lp-toggle-bg: #eef2f5;
    --lp-border: #dfe6ec;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    min-height: 100vh;
    background: linear-gradient(180deg, #f4fafd 0%, #e9f4fb 100%);
    font-family: -apple-system, "Segoe UI", "Noto Sans KR", "Noto Sans JP", sans-serif;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
  }
  .lp-card {
    background: var(--lp-card);
    width: 100%;
    max-width: 440px;
    border-radius: 20px;
    padding: 48px 40px 36px;
    box-shadow: 0 20px 50px rgba(20, 60, 90, .10);
  }
  .lp-title {
    text-align: center;
    font-weight: 800;
    font-size: 26px;
    letter-spacing: .5px;
    color: var(--lp-text);
    margin-bottom: 28px;
  }
  .lp-toggle {
    display: flex;
    background: var(--lp-toggle-bg);
    border-radius: 10px;
    padding: 4px;
    margin-bottom: 24px;
  }
  .lp-toggle button {
    flex: 1;
    border: none;
    background: none;
    padding: 10px 0;
    border-radius: 8px;
    font-weight: 700;
    font-size: 14px;
    color: var(--lp-dim);
    cursor: pointer;
  }
  .lp-toggle button.active {
    background: var(--lp-blue);
    color: #fff;
  }
  .lp-field { margin-bottom: 18px; }
  .lp-field label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--lp-text);
    margin-bottom: 6px;
  }
  .lp-field input {
    width: 100%;
    padding: 11px 14px;
    border: 1px solid var(--lp-border);
    border-radius: 8px;
    font-size: 14px;
    background: #fff;
    color: var(--lp-text);
  }
  .lp-field input:focus {
    outline: none;
    border-color: var(--lp-blue);
  }
  .lp-submit {
    width: 100%;
    margin-top: 8px;
    padding: 13px 0;
    border: none;
    border-radius: 8px;
    background: var(--lp-blue);
    color: #fff;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
  }
  .lp-submit:hover { background: var(--lp-blue-dark); }
  .lp-links { text-align: center; margin-top: 20px; }
  .lp-links a {
    display: block;
    color: var(--lp-blue);
    font-size: 13px;
    text-decoration: underline;
    margin-top: 8px;
  }
  .lp-error {
    background: #fdecec;
    color: #c0392b;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 13px;
    margin-bottom: 18px;
  }
</style>
</head>
<body>
  <div class="lp-card">
    <div class="lp-title">MYPAGE LOGIN</div>

    <div class="lp-toggle" id="typeToggle">
      <button type="button" data-type="admin" class="<?= $initialType === 'admin' ? 'active' : '' ?>">管理者用</button>
      <button type="button" data-type="customer" class="<?= $initialType === 'customer' ? 'active' : '' ?>">顧客用</button>
    </div>

    <?php if ($error): ?>
      <div class="lp-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" id="loginForm">
      <input type="hidden" name="type" id="typeInput" value="<?= htmlspecialchars($initialType) ?>">
      <div class="lp-field">
        <label id="loginIdLabel">ログインID</label>
        <input type="text" name="login_id" id="loginIdInput" autofocus>
      </div>
      <div class="lp-field">
        <label>パスワード</label>
        <input type="password" name="password">
      </div>
      <button type="submit" class="lp-submit">ログイン</button>
    </form>

    <div class="lp-links">
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
