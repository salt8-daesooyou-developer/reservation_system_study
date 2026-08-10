<?php
require __DIR__ . '/includes/auth.php';
require_admin();
$pageTitle = 'スタッフ管理';
$activeMenu = 'staff';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div></div>
  <button class="btn-accent" id="btnAddStaff">+ スタッフ追加</button>
</div>

<div class="panel">
  <table class="data-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>氏名</th>
        <th>権限</th>
        <th>作成日</th>
        <th></th>
      </tr>
    </thead>
    <tbody id="staffRows">
      <tr><td colspan="5" class="text-secondary text-center py-4">読み込み中...</td></tr>
    </tbody>
  </table>
</div>

<!-- スタッフ追加モーダル -->
<div class="modal fade" id="staffModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">スタッフ追加</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label">ログインID *</label>
          <input type="text" id="stUsername" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">パスワード * (4文字以上)</label>
          <div class="password-wrap">
            <input type="password" id="stPassword" class="form-control" required>
            <button type="button" class="password-toggle" data-target="stPassword">👁</button>
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label">氏名 *</label>
          <input type="text" id="stName" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">権限</label>
          <select id="stRole" class="form-select">
            <option value="staff">スタッフ（顧客管理・スケジュール管理のみ）</option>
            <option value="admin">管理者（全機能）</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
        <button type="button" class="btn-accent" id="btnSaveStaff">保存</button>
      </div>
    </div>
  </div>
</div>

<script>
const staffModal = new bootstrap.Modal(document.getElementById('staffModal'));
const roleLabel = { admin: '管理者', staff: 'スタッフ' };
const myId = <?= (int) $_SESSION['staff_id'] ?>;

function escapeHtml(s) {
  return (s || '').toString().replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

function loadStaff() {
  fetch('/reservation_system_study/api/staff.php')
    .then(r => r.json())
    .then(rows => {
      document.getElementById('staffRows').innerHTML = rows.map(s => `
        <tr>
          <td>${escapeHtml(s.username)}</td>
          <td>${escapeHtml(s.name)}</td>
          <td><span class="badge-status ${s.role === 'admin' ? 'badge-active' : 'badge-pending'}">${roleLabel[s.role]}</span></td>
          <td>${s.created_at.slice(0, 10)}</td>
          <td>${s.id !== myId ? `<button class="btn btn-sm btn-outline-danger" onclick="deleteStaff(${s.id})">削除</button>` : ''}</td>
        </tr>
      `).join('');
    });
}

document.getElementById('btnAddStaff').addEventListener('click', () => {
  document.getElementById('stUsername').value = '';
  document.getElementById('stPassword').value = '';
  document.getElementById('stName').value = '';
  document.getElementById('stRole').value = 'staff';
  staffModal.show();
});

document.getElementById('btnSaveStaff').addEventListener('click', () => {
  const payload = {
    username: document.getElementById('stUsername').value.trim(),
    password: document.getElementById('stPassword').value,
    name: document.getElementById('stName').value.trim(),
    role: document.getElementById('stRole').value,
  };
  if (!payload.username || !payload.password || !payload.name) {
    alert('すべての項目を入力してください。');
    return;
  }
  fetch('/reservation_system_study/api/staff.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
    .then(async r => {
      const data = await r.json();
      if (!r.ok) {
        alert(data.error === 'duplicate_username' ? '既に使用されているIDです。' : data.error === 'password_too_short' ? 'パスワードは4文字以上にしてください。' : '追加に失敗しました。');
        return;
      }
      staffModal.hide();
      loadStaff();
    });
});

function deleteStaff(id) {
  if (!confirm('このスタッフアカウントを削除しますか？')) return;
  fetch('/reservation_system_study/api/staff.php?id=' + id, { method: 'DELETE' })
    .then(r => r.json())
    .then(() => loadStaff());
}

loadStaff();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
