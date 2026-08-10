<?php
require __DIR__ . '/../includes/auth.php';
require_login();
$pageTitle = 'クラス管理';
$activeMenu = 'classes';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div class="text-secondary" style="font-size:13px;">レッスンの種類（カテゴリ・定員）を管理します。スケジュール追加時にここで作成したクラスから選べます。</div>
  <button class="btn-accent" id="btnAddClass">+ クラス追加</button>
</div>

<div class="panel">
  <table class="data-table">
    <thead>
      <tr><th>クラス名</th><th>カテゴリ</th><th>定員</th><th></th></tr>
    </thead>
    <tbody id="classRows">
      <tr><td colspan="4" class="text-secondary text-center py-4">読み込み中...</td></tr>
    </tbody>
  </table>
</div>

<!-- クラス追加・編集モーダル -->
<div class="modal fade" id="classModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="classModalTitle">クラス追加</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="clId">
        <div class="mb-2">
          <label class="form-label">クラス名 *</label>
          <input type="text" id="clName" class="form-control" required placeholder="例: ヒップクラス">
        </div>
        <div class="mb-2">
          <label class="form-label">カテゴリ</label>
          <input type="text" id="clCategory" class="form-control" list="categoryOptions" placeholder="例: ヒップ">
          <datalist id="categoryOptions">
            <option value="下半身">
            <option value="上半身">
            <option value="ヒップ">
            <option value="レッグ">
            <option value="全身">
            <option value="AM">
            <option value="PM">
          </datalist>
        </div>
        <div class="mb-2">
          <label class="form-label">定員 *</label>
          <input type="number" id="clCapacity" class="form-control" value="10" min="1" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-danger me-auto d-none" id="btnDeleteClass">削除</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
        <button type="button" class="btn-accent" id="btnSaveClass">保存</button>
      </div>
    </div>
  </div>
</div>

<script>
const classModal = new bootstrap.Modal(document.getElementById('classModal'));

function escapeHtml(s) {
  return (s || '').toString().replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

function loadClasses() {
  fetch('/reservation_system_study/api/classes.php')
    .then(r => r.json())
    .then(rows => {
      const tbody = document.getElementById('classRows');
      if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-secondary text-center py-4">クラスが登録されていません。</td></tr>';
        return;
      }
      tbody.innerHTML = rows.map(c => `
        <tr>
          <td>${escapeHtml(c.name)}</td>
          <td>${escapeHtml(c.category || '-')}</td>
          <td>${c.capacity}名</td>
          <td><button class="btn btn-sm btn-outline-light" onclick="openEdit(${c.id})">編集</button></td>
        </tr>
      `).join('');
    });
}

document.getElementById('btnAddClass').addEventListener('click', () => {
  document.getElementById('classModalTitle').textContent = 'クラス追加';
  document.getElementById('clId').value = '';
  document.getElementById('clName').value = '';
  document.getElementById('clCategory').value = '';
  document.getElementById('clCapacity').value = 10;
  document.getElementById('btnDeleteClass').classList.add('d-none');
  classModal.show();
});

function openEdit(id) {
  fetch('/reservation_system_study/api/classes.php')
    .then(r => r.json())
    .then(rows => {
      const c = rows.find(r => r.id === id);
      if (!c) return;
      document.getElementById('classModalTitle').textContent = 'クラス編集';
      document.getElementById('clId').value = c.id;
      document.getElementById('clName').value = c.name;
      document.getElementById('clCategory').value = c.category || '';
      document.getElementById('clCapacity').value = c.capacity;
      document.getElementById('btnDeleteClass').classList.remove('d-none');
      classModal.show();
    });
}

document.getElementById('btnSaveClass').addEventListener('click', () => {
  const id = document.getElementById('clId').value;
  const payload = {
    name: document.getElementById('clName').value.trim(),
    category: document.getElementById('clCategory').value.trim(),
    capacity: document.getElementById('clCapacity').value,
  };
  if (!payload.name) { alert('クラス名を入力してください。'); return; }

  const url = id
    ? '/reservation_system_study/api/classes.php?id=' + id
    : '/reservation_system_study/api/classes.php';

  fetch(url, {
    method: id ? 'PUT' : 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
    .then(async r => {
      const data = await r.json();
      if (!r.ok) { alert(data.error === 'duplicate_name' ? '既に存在するクラス名です。' : '保存に失敗しました。'); return; }
      classModal.hide();
      loadClasses();
    });
});

document.getElementById('btnDeleteClass').addEventListener('click', () => {
  const id = document.getElementById('clId').value;
  if (!id || !confirm('このクラスを削除しますか？')) return;
  fetch('/reservation_system_study/api/classes.php?id=' + id, { method: 'DELETE' })
    .then(async r => {
      const data = await r.json();
      if (!r.ok) { alert(data.error === 'class_in_use' ? 'このクラスは既にスケジュールで使用されているため削除できません。' : '削除に失敗しました。'); return; }
      classModal.hide();
      loadClasses();
    });
});

loadClasses();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
