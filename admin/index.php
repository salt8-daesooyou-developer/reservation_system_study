<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/branch.php';
require_login();

$pdo = db();

$total = (int) $pdo->query("SELECT COUNT(*) c FROM customers")->fetch()['c'];

$statusCounts = [];
foreach ($pdo->query("SELECT status, COUNT(*) c FROM customers GROUP BY status") as $row) {
    $statusCounts[$row['status']] = (int) $row['c'];
}
$active = $statusCounts['active'] ?? 0;
$expired = $statusCounts['expired'] ?? 0;
$pending = $statusCounts['pending'] ?? 0;
$hold = $statusCounts['hold'] ?? 0;
$unregistered = $statusCounts['unregistered'] ?? 0;

$soon = (int) $pdo->query("
    SELECT COUNT(DISTINCT customer_id) c FROM customer_memberships
    WHERE status = 'active' AND end_date IS NOT NULL
      AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
")->fetch()['c'];

function pct(int $n, int $total): int {
    return $total > 0 ? (int) round($n * 100 / $total) : 0;
}

$todaySchedules = $pdo->prepare("
    SELECT s.id, s.start_time, s.end_time, s.capacity, c.name AS class_name, s.instructor_name,
           (SELECT COUNT(*) FROM reservations r WHERE r.schedule_id = s.id AND r.status IN ('reserved','show')) AS booked
    FROM schedules s
    JOIN classes c ON c.id = s.class_id
    WHERE s.branch_id = :branch_id AND s.schedule_date = CURDATE()
    ORDER BY s.start_time
");
$todaySchedules->execute(['branch_id' => current_branch_id()]);
$todaySchedules = $todaySchedules->fetchAll();

$pageTitle = 'ダッシュボード';
$activeMenu = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="stat-grid">
  <div class="stat-card">
    <div class="label">全顧客</div>
    <div class="value"><?= $total ?></div>
  </div>
  <div class="stat-card">
    <div class="label">アクティブ <span class="sub">全顧客の <?= pct($active, $total) ?>%</span></div>
    <div class="value"><?= $active ?></div>
    <div class="bar"><span style="width:<?= pct($active, $total) ?>%"></span></div>
  </div>
  <div class="stat-card">
    <div class="label">まもなく期限切れ(7日以内) <span class="sub">全顧客の <?= pct($soon, $total) ?>%</span></div>
    <div class="value"><?= $soon ?></div>
    <div class="bar"><span style="width:<?= pct($soon, $total) ?>%"></span></div>
  </div>
  <div class="stat-card">
    <div class="label">期限切れ <span class="sub">全顧客の <?= pct($expired, $total) ?>%</span></div>
    <div class="value"><?= $expired ?></div>
    <div class="bar"><span style="width:<?= pct($expired, $total) ?>%"></span></div>
  </div>
  <div class="stat-card">
    <div class="label">休会中 <span class="sub">全顧客の <?= pct($hold, $total) ?>%</span></div>
    <div class="value"><?= $hold ?></div>
    <div class="bar"><span style="width:<?= pct($hold, $total) ?>%"></span></div>
  </div>
  <div class="stat-card">
    <div class="label">未登録 <span class="sub">全顧客の <?= pct($unregistered, $total) ?>%</span></div>
    <div class="value"><?= $unregistered ?></div>
    <div class="bar"><span style="width:<?= pct($unregistered, $total) ?>%"></span></div>
  </div>
</div>

<div class="panel">
  <h5 class="mb-3">📅 本日のスケジュール (<?= date('Y-m-d') ?>)</h5>
  <?php if (!$todaySchedules): ?>
    <div class="text-secondary">本日登録されているスケジュールはありません。</div>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr><th>時間</th><th>クラス</th><th>インストラクター</th><th>予約状況</th></tr>
      </thead>
      <tbody>
        <?php foreach ($todaySchedules as $s): ?>
        <tr>
          <td><?= substr($s['start_time'],0,5) ?> - <?= substr($s['end_time'],0,5) ?></td>
          <td><?= htmlspecialchars($s['class_name']) ?></td>
          <td><?= htmlspecialchars($s['instructor_name'] ?? '-') ?></td>
          <td><?= (int)$s['booked'] ?> / <?= (int)$s['capacity'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
