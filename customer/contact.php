<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/database.php';
require_customer_login();

$pageTitle = 'お問い合わせ - 予約管理システム';
$activeMenu = 'contact';
require __DIR__ . '/../includes/customer_header.php';
?>
<div style="max-width:640px; margin:0 auto;">

  <div class="panel mb-3">
    <h6 class="mb-3">お問い合わせ</h6>
    <div id="formAlert" class="auth-error d-none"></div>
    <div id="formSuccess" class="mb-3 d-none" style="color: var(--green); font-size:13px;">送信しました。担当者よりご連絡いたします。</div>
    <div class="mb-2">
      <label class="form-label">件名 *</label>
      <input type="text" id="fSubject" class="form-control">
    </div>
    <div class="mb-3">
      <label class="form-label">お問い合わせ内容 *</label>
      <textarea id="fMessage" class="form-control" rows="5"></textarea>
    </div>
    <button class="btn-accent" id="btnSend">送信する</button>
  </div>

  <div class="panel">
    <h6 class="mb-3">これまでのお問い合わせ</h6>
    <div id="inquiryList"><div class="text-secondary">読み込み中...</div></div>
  </div>
</div>

<script>
const statusLabel = { new: '受付済み', replied: '返信済み', closed: '対応完了' };

function escapeHtml(s) {
  return (s || '').toString().replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

function loadInquiries() {
  fetch('/reservation_system_study/api/contact.php')
    .then(r => r.json())
    .then(rows => {
      const list = document.getElementById('inquiryList');
      if (!rows.length) {
        list.innerHTML = '<div class="text-secondary">お問い合わせ履歴はありません。</div>';
        return;
      }
      list.innerHTML = rows.map(i => `
        <div class="mb-2 p-2" style="border:1px solid var(--border); border-radius:10px;">
          <div class="d-flex justify-content-between align-items-start">
            <div style="font-weight:700;">${escapeHtml(i.subject)}</div>
            <span class="badge-status badge-${i.status === 'closed' ? 'active' : i.status === 'replied' ? 'pending' : 'unregistered'}">${statusLabel[i.status] || i.status}</span>
          </div>
          <div class="text-secondary" style="font-size:12px; margin:4px 0;">${i.created_at}</div>
          <div style="white-space:pre-wrap;">${escapeHtml(i.message)}</div>
        </div>
      `).join('');
    });
}

document.getElementById('btnSend').addEventListener('click', () => {
  const alertEl = document.getElementById('formAlert');
  const successEl = document.getElementById('formSuccess');
  alertEl.classList.add('d-none');
  successEl.classList.add('d-none');

  const subject = document.getElementById('fSubject').value.trim();
  const message = document.getElementById('fMessage').value.trim();
  if (!subject || !message) {
    alertEl.textContent = '件名と内容を入力してください。';
    alertEl.classList.remove('d-none');
    return;
  }

  fetch('/reservation_system_study/api/contact.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ subject, message }),
  })
    .then(async r => {
      const data = await r.json();
      if (!r.ok) {
        alertEl.textContent = '送信に失敗しました。';
        alertEl.classList.remove('d-none');
        return;
      }
      successEl.classList.remove('d-none');
      document.getElementById('fSubject').value = '';
      document.getElementById('fMessage').value = '';
      loadInquiries();
    });
});

loadInquiries();
</script>

<?php require __DIR__ . '/../includes/customer_footer.php'; ?>
