<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../config/stripe.php';
require_customer_login();

$pdo = db();
$customerId = (int) $_SESSION['customer_id'];

$products = $pdo->query("
    SELECT * FROM membership_products
    WHERE type = 'period' AND stripe_price_id IS NOT NULL
    ORDER BY price
")->fetchAll();

$subStmt = $pdo->prepare("
    SELECT s.*, mp.name AS product_name
    FROM stripe_subscriptions s
    JOIN membership_products mp ON mp.id = s.product_id
    WHERE s.customer_id = ?
    ORDER BY s.created_at DESC
");
$subStmt->execute([$customerId]);
$subscriptions = $subStmt->fetchAll();

$statusLabel = [
    'pending' => '手続き中', 'active' => '有効', 'past_due' => '支払い遅延',
    'canceled' => '解約済み', 'incomplete' => '未完了',
];

$pageTitle = '月額プラン - 予約管理システム';
$activeMenu = 'home';
require __DIR__ . '/../includes/customer_header.php';
?>
<div style="max-width:640px; margin:0 auto;">

  <?php if (isset($_GET['success'])): ?>
    <div class="panel mb-3" style="border-color: var(--green);">
      決済手続きが完了しました。反映まで少し時間がかかる場合があります。
    </div>
  <?php elseif (isset($_GET['canceled'])): ?>
    <div class="panel mb-3" style="border-color: var(--red);">
      決済手続きがキャンセルされました。
    </div>
  <?php endif; ?>

  <?php if (!stripe_is_configured()): ?>
    <div class="panel mb-3 text-secondary">
      現在、月額決済（Stripe）は準備中です。管理者側の設定が完了するまでお待ちください。
    </div>
  <?php endif; ?>

  <div class="panel mb-3">
    <h6 class="mb-3">月額プラン</h6>
    <?php if (!$products): ?>
      <div class="text-secondary">現在お申込み可能な月額プランはありません。</div>
    <?php else: ?>
      <?php foreach ($products as $p): ?>
        <div class="d-flex justify-content-between align-items-center mb-2" style="padding:12px; border:1px solid var(--border); border-radius:10px;">
          <div>
            <div style="font-weight:700;"><?= htmlspecialchars($p['name']) ?></div>
            <div class="text-secondary" style="font-size:13px;">¥<?= number_format($p['price']) ?> / 月</div>
          </div>
          <button class="btn-accent btn-sm" onclick="startCheckout(<?= (int) $p['id'] ?>)" <?= stripe_is_configured() ? '' : 'disabled' ?>>申し込む</button>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h6 class="mb-3">お申込み状況</h6>
    <?php if (!$subscriptions): ?>
      <div class="text-secondary">お申込み中のプランはありません。</div>
    <?php else: ?>
      <table class="data-table">
        <thead><tr><th>プラン</th><th>状態</th><th>次回更新</th></tr></thead>
        <tbody>
          <?php foreach ($subscriptions as $s): ?>
          <tr>
            <td><?= htmlspecialchars($s['product_name']) ?></td>
            <td><span class="badge-status badge-<?= $s['status'] === 'active' ? 'active' : ($s['status'] === 'canceled' ? 'expired' : 'pending') ?>"><?= $statusLabel[$s['status']] ?? $s['status'] ?></span></td>
            <td><?= $s['current_period_end'] ? htmlspecialchars(substr($s['current_period_end'], 0, 10)) : '-' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<script>
function startCheckout(productId) {
  fetch('/reservation_system_study/api/stripe_checkout.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ product_id: productId }),
  })
    .then(async r => {
      const data = await r.json();
      if (!r.ok) {
        alert(data.error === 'stripe_not_configured' ? '現在、決済機能は準備中です。' : '決済ページの作成に失敗しました。');
        return;
      }
      location.href = data.url;
    });
}
</script>

<?php require __DIR__ . '/../includes/customer_footer.php'; ?>
