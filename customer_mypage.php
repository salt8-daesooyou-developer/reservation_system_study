<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/config/database.php';
require_customer_login();

$pdo = db();
$customerId = (int) $_SESSION['customer_id'];

$stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
$stmt->execute([$customerId]);
$customer = $stmt->fetch();
if (!$customer) {
    header('Location: /reservation_system_study/customer_logout.php');
    exit;
}

$mStmt = $pdo->prepare('
    SELECT cm.*, mp.name AS product_name, mp.type AS product_type
    FROM customer_memberships cm
    JOIN membership_products mp ON mp.id = cm.product_id
    WHERE cm.customer_id = ?
    ORDER BY cm.start_date DESC
');
$mStmt->execute([$customerId]);
$memberships = $mStmt->fetchAll();

$rStmt = $pdo->prepare('
    SELECT r.id, r.status, s.schedule_date, s.start_time, s.end_time, c.name AS class_name, s.instructor_name, b.name AS branch_name
    FROM reservations r
    JOIN schedules s ON s.id = r.schedule_id
    JOIN classes c ON c.id = s.class_id
    JOIN branches b ON b.id = s.branch_id
    WHERE r.customer_id = ? AND s.schedule_date >= CURDATE() AND r.status IN ("reserved", "show")
    ORDER BY s.schedule_date, s.start_time
');
$rStmt->execute([$customerId]);
$reservations = $rStmt->fetchAll();

$statusLabel = ['active' => 'アクティブ', 'expired' => '期限切れ', 'pending' => '予定', 'hold' => '休会中', 'unregistered' => '未登録'];
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>マイページ - 予約管理システム</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="/reservation_system_study/assets/css/app.css" rel="stylesheet">
</head>
<body>
<div style="max-width:640px; margin:0 auto; padding:32px 16px;">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <div style="color:var(--accent); font-weight:800; font-size:20px;">RSVP</div>
      <div class="text-secondary" style="font-size:13px;">マイページ</div>
    </div>
    <a href="/reservation_system_study/customer_logout.php" class="btn btn-outline-light btn-sm">ログアウト</a>
  </div>

  <div class="panel mb-3">
    <div style="font-size:18px; font-weight:700;"><?= htmlspecialchars($customer['name']) ?> 様</div>
    <div class="text-secondary" style="font-size:13px;">
      連絡先: <?= htmlspecialchars($customer['phone'] ?? '-') ?>
      ・ 状態: <span class="badge-status badge-<?= htmlspecialchars($customer['status']) ?>"><?= $statusLabel[$customer['status']] ?? $customer['status'] ?></span>
    </div>
  </div>

  <div class="panel mb-3">
    <h6 class="mb-3">保有中の会員権</h6>
    <?php if (!$memberships): ?>
      <div class="text-secondary">保有中の会員権はありません。</div>
    <?php else: ?>
      <table class="data-table">
        <thead><tr><th>プラン名</th><th>期間／回数</th><th>状態</th></tr></thead>
        <tbody>
          <?php foreach ($memberships as $m): ?>
          <tr>
            <td><?= htmlspecialchars($m['product_name']) ?></td>
            <td><?= $m['product_type'] === 'count' ? htmlspecialchars($m['remaining_count']) . '回 残り' : htmlspecialchars($m['start_date']) . ' 〜 ' . htmlspecialchars($m['end_date'] ?? '-') ?></td>
            <td><span class="badge-status badge-<?= htmlspecialchars($m['status']) ?>"><?= $statusLabel[$m['status']] ?? $m['status'] ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h6 class="mb-3">今後の予約</h6>
    <?php if (!$reservations): ?>
      <div class="text-secondary">今後の予約はありません。</div>
    <?php else: ?>
      <table class="data-table">
        <thead><tr><th>日付</th><th>時間</th><th>クラス</th><th>店舗</th></tr></thead>
        <tbody>
          <?php foreach ($reservations as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['schedule_date']) ?></td>
            <td><?= substr($r['start_time'], 0, 5) ?> - <?= substr($r['end_time'], 0, 5) ?></td>
            <td><?= htmlspecialchars($r['class_name']) ?></td>
            <td><?= htmlspecialchars($r['branch_name']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
