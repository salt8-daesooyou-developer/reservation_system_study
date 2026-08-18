<?php
require __DIR__ . '/../includes/auth.php';
require_login();
$pageTitle = '顧客管理';
$activeMenu = 'customers';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div class="status-tabs" id="statusTabs">
    <button class="active" data-status="">全体</button>
    <button data-status="active">アクティブ</button>
    <button data-status="expired">期限切れ</button>
    <button data-status="pending">予定</button>
    <button data-status="hold">休会中</button>
    <button data-status="unregistered">未登録</button>
  </div>
  <button class="btn-accent" id="btnAddCustomer">+ 顧客追加</button>
</div>

<div class="panel">
  <div class="mb-3">
    <input type="text" id="searchInput" class="form-control" style="max-width:320px;" placeholder="顧客名・連絡先で検索">
  </div>
  <table class="data-table">
    <thead>
      <tr>
        <th>No.</th>
        <th>状態</th>
        <th>顧客名</th>
        <th>性別</th>
        <th>生年月日</th>
        <th>連絡先</th>
        <th>店舗</th>
        <th></th>
      </tr>
    </thead>
    <tbody id="customerRows">
      <tr><td colspan="8" class="text-secondary text-center py-4">読み込み中...</td></tr>
    </tbody>
  </table>
</div>

<!-- 顧客追加・編集モーダル -->
<div class="modal fade" id="customerModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="customerModalTitle">顧客追加</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="cId">
        <div class="mb-2">
          <label class="form-label">顧客名 *</label>
          <input type="text" id="cName" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">フリガナ</label>
          <input type="text" id="cNameKana" class="form-control">
        </div>
        <div class="row">
          <div class="col-6 mb-2">
            <label class="form-label">性別</label>
            <select id="cGender" class="form-select">
              <option value="unknown">未登録</option>
              <option value="male">男性</option>
              <option value="female">女性</option>
            </select>
          </div>
          <div class="col-6 mb-2">
            <label class="form-label">生年月日</label>
            <input type="date" id="cBirthDate" class="form-control">
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label">連絡先</label>
          <input type="text" id="cPhone" class="form-control" placeholder="090-1234-5678">
        </div>
        <div class="mb-2">
          <label class="form-label">メールアドレス</label>
          <input type="email" id="cEmail" class="form-control">
        </div>
        <div class="mb-2">
          <label class="form-label">ご利用店舗</label>
          <select id="cBranchId" class="form-select">
            <option value="">未登録</option>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">状態</label>
          <select id="cStatus" class="form-select">
            <option value="unregistered">未登録</option>
            <option value="active">アクティブ</option>
            <option value="expired">期限切れ</option>
            <option value="pending">予定</option>
            <option value="hold">休会中</option>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">メモ</label>
          <textarea id="cMemo" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-danger me-auto d-none" id="btnDeleteCustomer">削除</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
        <button type="button" class="btn-accent" id="btnSaveCustomer">保存</button>
      </div>
    </div>
  </div>
</div>

<script>
const customerModal = new bootstrap.Modal(document.getElementById('customerModal'));

let currentStatus = '';
let currentQuery = '';

const statusLabel = { active: 'アクティブ', expired: '期限切れ', pending: '予定', hold: '休会中', unregistered: '未登録' };
const genderLabel = { male: '男性', female: '女性', unknown: '-' };

fetch('/reservation_system_study/api/branches.php')
  .then(r => r.json())
  .then(data => {
    const sel = document.getElementById('cBranchId');
    data.branches.forEach(b => {
      const opt = document.createElement('option');
      opt.value = b.id;
      opt.textContent = b.name;
      sel.appendChild(opt);
    });
  });

function loadCustomers() {
  const params = new URLSearchParams();
  if (currentStatus) params.set('status', currentStatus);
  if (currentQuery) params.set('q', currentQuery);

  fetch('/reservation_system_study/api/customers.php?' + params.toString())
    .then(r => r.json())
    .then(rows => {
      const tbody = document.getElementById('customerRows');
      if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-secondary text-center py-4">顧客が登録されていません。</td></tr>';
        return;
      }
      tbody.innerHTML = rows.map(c => `
        <tr>
          <td>${c.id}</td>
          <td><span class="badge-status badge-${c.status}">${statusLabel[c.status] || c.status}</span></td>
          <td><a href="/reservation_system_study/admin/customer_detail.php?id=${c.id}">${escapeHtml(c.name)}</a></td>
          <td>${genderLabel[c.gender] || '-'}</td>
          <td>${c.birth_date || '-'}</td>
          <td>${c.phone || '-'}</td>
          <td>${escapeHtml(c.branch_name || '-')}</td>
          <td><button class="btn btn-sm btn-outline-light" onclick="openEdit(${c.id})">編集</button></td>
        </tr>
      `).join('');
    });
}

function escapeHtml(s) {
  return (s || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

document.getElementById('statusTabs').addEventListener('click', e => {
  if (e.target.tagName !== 'BUTTON') return;
  document.querySelectorAll('#statusTabs button').forEach(b => b.classList.remove('active'));
  e.target.classList.add('active');
  currentStatus = e.target.dataset.status;
  loadCustomers();
});

let searchTimer;
document.getElementById('searchInput').addEventListener('input', e => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    currentQuery = e.target.value.trim();
    loadCustomers();
  }, 300);
});

document.getElementById('btnAddCustomer').addEventListener('click', () => {
  document.getElementById('customerModalTitle').textContent = '顧客追加';
  ['cId','cName','cNameKana','cBirthDate','cPhone','cEmail','cMemo'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('cGender').value = 'unknown';
  document.getElementById('cBranchId').value = '';
  document.getElementById('cStatus').value = 'unregistered';
  document.getElementById('btnDeleteCustomer').classList.add('d-none');
  customerModal.show();
});

function openEdit(id) {
  fetch('/reservation_system_study/api/customers.php?id=' + id)
    .then(r => r.json())
    .then(c => {
      document.getElementById('customerModalTitle').textContent = '顧客編集';
      document.getElementById('cId').value = c.id;
      document.getElementById('cName').value = c.name;
      document.getElementById('cNameKana').value = c.name_kana || '';
      document.getElementById('cGender').value = c.gender;
      document.getElementById('cBirthDate').value = c.birth_date || '';
      document.getElementById('cPhone').value = c.phone || '';
      document.getElementById('cEmail').value = c.email || '';
      document.getElementById('cBranchId').value = c.branch_id || '';
      document.getElementById('cStatus').value = c.status;
      document.getElementById('cMemo').value = c.memo || '';
      document.getElementById('btnDeleteCustomer').classList.remove('d-none');
      customerModal.show();
    });
}

document.getElementById('btnSaveCustomer').addEventListener('click', () => {
  const id = document.getElementById('cId').value;
  const payload = {
    name: document.getElementById('cName').value.trim(),
    name_kana: document.getElementById('cNameKana').value.trim(),
    gender: document.getElementById('cGender').value,
    birth_date: document.getElementById('cBirthDate').value,
    phone: document.getElementById('cPhone').value.trim(),
    email: document.getElementById('cEmail').value.trim(),
    branch_id: document.getElementById('cBranchId').value,
    status: document.getElementById('cStatus').value,
    memo: document.getElementById('cMemo').value.trim(),
  };
  if (!payload.name) { alert('顧客名を入力してください。'); return; }

  const url = id
    ? '/reservation_system_study/api/customers.php?id=' + id
    : '/reservation_system_study/api/customers.php';

  fetch(url, {
    method: id ? 'PUT' : 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
    .then(r => r.json())
    .then(() => { customerModal.hide(); loadCustomers(); });
});

document.getElementById('btnDeleteCustomer').addEventListener('click', () => {
  const id = document.getElementById('cId').value;
  if (!id || !confirm('この顧客を削除しますか？')) return;
  fetch('/reservation_system_study/api/customers.php?id=' + id, { method: 'DELETE' })
    .then(r => r.json())
    .then(() => { customerModal.hide(); loadCustomers(); });
});

loadCustomers();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
