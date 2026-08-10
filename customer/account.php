<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/database.php';
require_customer_login();

$pdo = db();
$customerId = (int) $_SESSION['customer_id'];

$stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
$stmt->execute([$customerId]);
$customer = $stmt->fetch();
if (!$customer) {
    header('Location: /reservation_system_study/customer/logout.php');
    exit;
}

$pageTitle = 'アカウント設定 - 予約管理システム';
$activeMenu = 'account';
require __DIR__ . '/../includes/customer_header.php';
?>
<div style="max-width:640px; margin:0 auto;">

  <div class="panel mb-3">
    <h6 class="mb-3">プロフィール</h6>
    <div id="profileAlert" class="auth-error d-none"></div>
    <div id="profileSuccess" class="mb-3 d-none" style="color: var(--green); font-size:13px;">保存しました。</div>
    <div class="mb-2">
      <label class="form-label">氏名 *</label>
      <input type="text" id="fName" class="form-control" value="<?= htmlspecialchars($customer['name']) ?>">
    </div>
    <div class="mb-2">
      <label class="form-label">フリガナ</label>
      <input type="text" id="fNameKana" class="form-control" value="<?= htmlspecialchars($customer['name_kana'] ?? '') ?>">
    </div>
    <div class="row">
      <div class="col-6 mb-2">
        <label class="form-label">性別</label>
        <select id="fGender" class="form-select">
          <option value="unknown" <?= $customer['gender'] === 'unknown' ? 'selected' : '' ?>>未登録</option>
          <option value="male" <?= $customer['gender'] === 'male' ? 'selected' : '' ?>>男性</option>
          <option value="female" <?= $customer['gender'] === 'female' ? 'selected' : '' ?>>女性</option>
        </select>
      </div>
      <div class="col-6 mb-2">
        <label class="form-label">生年月日</label>
        <input type="date" id="fBirthDate" class="form-control" value="<?= htmlspecialchars($customer['birth_date'] ?? '') ?>">
      </div>
    </div>
    <div class="mb-2">
      <label class="form-label">連絡先 *</label>
      <input type="text" id="fPhone" class="form-control" value="<?= htmlspecialchars($customer['phone'] ?? '') ?>">
    </div>
    <div class="mb-2">
      <label class="form-label">メールアドレス（ログインID） *</label>
      <input type="email" id="fEmail" class="form-control" value="<?= htmlspecialchars($customer['email'] ?? '') ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">ご利用店舗 *</label>
      <select id="fBranchId" class="form-select"></select>
    </div>
    <button class="btn-accent" id="btnSaveProfile">プロフィールを保存</button>
  </div>

  <div class="panel">
    <h6 class="mb-3">パスワード変更</h6>
    <div id="passwordAlert" class="auth-error d-none"></div>
    <div id="passwordSuccess" class="mb-3 d-none" style="color: var(--green); font-size:13px;">パスワードを変更しました。</div>
    <div class="mb-2">
      <label class="form-label">現在のパスワード *</label>
      <input type="password" id="fCurrentPassword" class="form-control">
    </div>
    <div class="mb-2">
      <label class="form-label">新しいパスワード *</label>
      <input type="password" id="fNewPassword" class="form-control">
      <div class="text-secondary" style="font-size:12px;">8文字以上、かつ特殊文字を1つ以上含めてください。</div>
    </div>
    <div class="mb-3">
      <label class="form-label">新しいパスワード（確認） *</label>
      <input type="password" id="fNewPasswordConfirm" class="form-control">
    </div>
    <button class="btn-accent" id="btnSavePassword">パスワードを変更</button>
  </div>
</div>

<script>
const profileErrors = {
  name_required: '氏名を入力してください。',
  invalid_email: 'メールアドレスの形式が正しくありません。',
  phone_required: '連絡先を入力してください。',
  branch_required: 'ご利用店舗を選択してください。',
  branch_not_found: '選択した店舗が見つかりません。',
  duplicate_phone: 'この連絡先は既に使用されています。',
  duplicate_email: 'このメールアドレスは既に使用されています。',
};
const passwordErrors = {
  current_password_incorrect: '現在のパスワードが正しくありません。',
  password_mismatch: '新しいパスワードが一致しません。',
  password_invalid: 'パスワードは8文字以上、かつ特殊文字を1つ以上含めてください。',
};

fetch('/reservation_system_study/api/branches.php')
  .then(r => r.json())
  .then(data => {
    const sel = document.getElementById('fBranchId');
    sel.innerHTML = data.branches.map(b =>
      `<option value="${b.id}" ${b.id === <?= (int) ($customer['branch_id'] ?? 0) ?> ? 'selected' : ''}>${b.name}</option>`
    ).join('');
  });

document.getElementById('btnSaveProfile').addEventListener('click', () => {
  const alertEl = document.getElementById('profileAlert');
  const successEl = document.getElementById('profileSuccess');
  alertEl.classList.add('d-none');
  successEl.classList.add('d-none');

  const payload = {
    name: document.getElementById('fName').value.trim(),
    name_kana: document.getElementById('fNameKana').value.trim(),
    gender: document.getElementById('fGender').value,
    birth_date: document.getElementById('fBirthDate').value,
    phone: document.getElementById('fPhone').value.trim(),
    email: document.getElementById('fEmail').value.trim(),
    branch_id: document.getElementById('fBranchId').value,
  };

  fetch('/reservation_system_study/api/customer_account.php', {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
    .then(async r => {
      const data = await r.json();
      if (!r.ok) {
        alertEl.textContent = profileErrors[data.error] || '保存に失敗しました。';
        alertEl.classList.remove('d-none');
        return;
      }
      successEl.classList.remove('d-none');
    });
});

document.getElementById('btnSavePassword').addEventListener('click', () => {
  const alertEl = document.getElementById('passwordAlert');
  const successEl = document.getElementById('passwordSuccess');
  alertEl.classList.add('d-none');
  successEl.classList.add('d-none');

  const payload = {
    current_password: document.getElementById('fCurrentPassword').value,
    new_password: document.getElementById('fNewPassword').value,
    new_password_confirm: document.getElementById('fNewPasswordConfirm').value,
  };

  fetch('/reservation_system_study/api/customer_account.php?password=1', {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
    .then(async r => {
      const data = await r.json();
      if (!r.ok) {
        alertEl.textContent = passwordErrors[data.error] || 'パスワード変更に失敗しました。';
        alertEl.classList.remove('d-none');
        return;
      }
      successEl.classList.remove('d-none');
      document.getElementById('fCurrentPassword').value = '';
      document.getElementById('fNewPassword').value = '';
      document.getElementById('fNewPasswordConfirm').value = '';
    });
});
</script>

<?php require __DIR__ . '/../includes/customer_footer.php'; ?>
