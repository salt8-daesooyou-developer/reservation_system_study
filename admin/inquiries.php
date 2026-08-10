<?php
require __DIR__ . '/../includes/auth.php';
require_login();
$pageTitle = 'お問い合わせ管理';
$activeMenu = 'inquiries';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
  <table class="data-table">
    <thead>
      <tr><th>日時</th><th>顧客名</th><th>件名</th><th>内容</th><th>メール</th><th>状態</th></tr>
    </thead>
    <tbody id="inquiryRows">
      <tr><td colspan="6" class="text-secondary text-center py-4">読み込み中...</td></tr>
    </tbody>
  </table>
</div>

<script>
const statusLabel = { new: '受付済み', replied: '返信済み', closed: '対応完了' };

function escapeHtml(s) {
  return (s || '').toString().replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

function loadInquiries() {
  fetch('/reservation_system_study/api/inquiries.php')
    .then(r => r.json())
    .then(rows => {
      const tbody = document.getElementById('inquiryRows');
      if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-secondary text-center py-4">お問い合わせはありません。</td></tr>';
        return;
      }
      tbody.innerHTML = rows.map(i => `
        <tr>
          <td class="text-secondary">${i.created_at}</td>
          <td>${escapeHtml(i.customer_name)}</td>
          <td>${escapeHtml(i.subject)}</td>
          <td style="max-width:320px; white-space:pre-wrap;">${escapeHtml(i.message)}</td>
          <td>${i.email_sent == 1 ? '✅' : '—'}</td>
          <td>
            <select class="form-select form-select-sm" onchange="updateStatus(${i.id}, this.value)">
              <option value="new" ${i.status === 'new' ? 'selected' : ''}>受付済み</option>
              <option value="replied" ${i.status === 'replied' ? 'selected' : ''}>返信済み</option>
              <option value="closed" ${i.status === 'closed' ? 'selected' : ''}>対応完了</option>
            </select>
          </td>
        </tr>
      `).join('');
    });
}

function updateStatus(id, status) {
  fetch(`/reservation_system_study/api/inquiries.php?id=${id}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ status }),
  }).then(() => loadInquiries());
}

loadInquiries();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
