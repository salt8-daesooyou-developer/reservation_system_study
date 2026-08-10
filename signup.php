<?php
require __DIR__ . '/includes/auth.php';
if (!empty($_SESSION['staff_id'])) {
    header('Location: /reservation_system_study/index.php');
    exit;
}
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>会員登録 - 予約管理システム</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="/reservation_system_study/assets/css/app.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body class="d-flex align-items-center justify-content-center py-5" style="min-height:100vh;">
  <div class="panel" style="width:420px;">
    <div class="text-center mb-4">
      <div style="color:var(--accent); font-weight:800; font-size:24px;">RSVP</div>
      <div class="text-secondary" style="font-size:13px;">新規会員登録</div>
    </div>

    <div class="status-tabs mb-3" id="typeTabs">
      <button type="button" class="active" data-type="customer" style="flex:1;">顧客として登録</button>
      <button type="button" data-type="staff" style="flex:1;">スタッフとして登録</button>
    </div>

    <div id="formAlert" class="alert alert-danger py-2 d-none"></div>
    <div id="formSuccess" class="alert alert-success py-2 d-none"></div>

    <form id="signupForm" novalidate>
      <!-- 共通: 氏名 -->
      <div class="mb-2">
        <label class="form-label">氏名 *</label>
        <input type="text" id="fName" class="form-control" required>
      </div>

      <!-- 顧客専用項目 -->
      <div id="customerFields">
        <div class="mb-2">
          <label class="form-label">フリガナ</label>
          <input type="text" id="fNameKana" class="form-control">
        </div>
        <div class="row">
          <div class="col-6 mb-2">
            <label class="form-label">性別</label>
            <select id="fGender" class="form-select">
              <option value="unknown">未登録</option>
              <option value="male">男性</option>
              <option value="female">女性</option>
            </select>
          </div>
          <div class="col-6 mb-2">
            <label class="form-label">生年月日</label>
            <input type="date" id="fBirthDate" class="form-control">
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label">連絡先 *</label>
          <input type="text" id="fPhone" class="form-control" placeholder="090-1234-5678">
        </div>
        <div class="mb-2">
          <label class="form-label">メールアドレス</label>
          <input type="email" id="fEmail" class="form-control">
        </div>
      </div>

      <!-- スタッフ専用項目 -->
      <div id="staffFields" class="d-none">
        <div class="mb-2">
          <label class="form-label">ログインID *</label>
          <input type="text" id="fUsername" class="form-control">
        </div>
      </div>

      <!-- 共通: パスワード -->
      <div class="mb-2">
        <label class="form-label">パスワード *</label>
        <div class="password-wrap">
          <input type="password" id="fPassword" class="form-control" required>
          <button type="button" class="password-toggle" data-target="fPassword">👁</button>
        </div>
        <div class="form-text text-secondary" style="font-size:12px;">8文字以上、かつ特殊文字（!@#$%など）を1つ以上含めてください。</div>
      </div>
      <div class="mb-3">
        <label class="form-label">パスワード確認 *</label>
        <div class="password-wrap">
          <input type="password" id="fPasswordConfirm" class="form-control" required>
          <button type="button" class="password-toggle" data-target="fPasswordConfirm">👁</button>
        </div>
      </div>

      <button type="submit" class="btn-accent w-100">登録する</button>
    </form>

    <div class="text-center mt-3">
      <a href="/reservation_system_study/login.php" style="font-size:13px;">既にアカウントをお持ちの方はこちら</a>
    </div>
  </div>

<script>
let currentType = 'customer';

const errorMessages = {
  invalid_type: '登録種別が不正です。',
  name_required: '氏名を入力してください。',
  password_mismatch: 'パスワードが一致しません。',
  password_too_short: 'パスワードは8文字以上で入力してください。',
  password_needs_special_char: 'パスワードには特殊文字を1つ以上含めてください。',
  phone_required: '連絡先を入力してください。',
  invalid_email: 'メールアドレスの形式が正しくありません。',
  duplicate_phone: 'この連絡先は既に登録されています。',
  username_required: 'ログインIDを入力してください。',
  duplicate_username: 'このログインIDは既に使用されています。',
};

document.getElementById('typeTabs').addEventListener('click', e => {
  const btn = e.target.closest('button[data-type]');
  if (!btn) return;
  currentType = btn.dataset.type;
  document.querySelectorAll('#typeTabs button').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('customerFields').classList.toggle('d-none', currentType !== 'customer');
  document.getElementById('staffFields').classList.toggle('d-none', currentType !== 'staff');
  hideMessages();
});

function hideMessages() {
  document.getElementById('formAlert').classList.add('d-none');
  document.getElementById('formSuccess').classList.add('d-none');
}
function showError(msg) {
  const el = document.getElementById('formAlert');
  el.textContent = msg;
  el.classList.remove('d-none');
}

function isValidPassword(pw) {
  if (pw.length < 8) return false;
  return /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?~`]/.test(pw);
}

document.getElementById('signupForm').addEventListener('submit', e => {
  e.preventDefault();
  hideMessages();

  const name = document.getElementById('fName').value.trim();
  const password = document.getElementById('fPassword').value;
  const passwordConfirm = document.getElementById('fPasswordConfirm').value;

  if (!name) { showError('氏名を入力してください。'); return; }

  const payload = { type: currentType, name, password, password_confirm: passwordConfirm };

  if (currentType === 'customer') {
    const phone = document.getElementById('fPhone').value.trim();
    if (!phone) { showError('連絡先を入力してください。'); return; }
    payload.name_kana = document.getElementById('fNameKana').value.trim();
    payload.gender = document.getElementById('fGender').value;
    payload.birth_date = document.getElementById('fBirthDate').value;
    payload.phone = phone;
    payload.email = document.getElementById('fEmail').value.trim();
  } else {
    const username = document.getElementById('fUsername').value.trim();
    if (!username) { showError('ログインIDを入力してください。'); return; }
    payload.username = username;
  }

  if (password !== passwordConfirm) { showError('パスワードが一致しません。'); return; }
  if (!isValidPassword(password)) {
    showError('パスワードは8文字以上、かつ特殊文字を1つ以上含めてください。');
    return;
  }

  fetch('/reservation_system_study/api/signup.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
    .then(async r => {
      const data = await r.json();
      if (!r.ok) {
        showError(errorMessages[data.error] || '登録に失敗しました。');
        return;
      }
      document.getElementById('signupForm').reset();
      const el = document.getElementById('formSuccess');
      el.textContent = '登録が完了しました。ログインページからログインしてください。';
      el.classList.remove('d-none');
    });
});
</script>
<script src="/reservation_system_study/assets/js/password-toggle.js"></script>
</body>
</html>
