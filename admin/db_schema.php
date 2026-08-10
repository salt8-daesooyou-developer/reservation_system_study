<?php
require __DIR__ . '/../includes/auth.php';
require_admin();
require __DIR__ . '/../config/database.php';

$pdo = db();

$columns = $pdo->query("
    SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, COLUMN_DEFAULT, EXTRA
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    ORDER BY TABLE_NAME, ORDINAL_POSITION
")->fetchAll();

$foreignKeys = $pdo->query("
    SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL
    ORDER BY TABLE_NAME, COLUMN_NAME
")->fetchAll();

$rowCounts = [];
foreach ($pdo->query("
    SELECT TABLE_NAME, TABLE_ROWS
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
") as $t) {
    $rowCounts[$t['TABLE_NAME']] = (int) $t['TABLE_ROWS'];
}

$tables = [];
foreach ($columns as $c) {
    $tables[$c['TABLE_NAME']][] = $c;
}

$fksByTable = [];
foreach ($foreignKeys as $fk) {
    $fksByTable[$fk['TABLE_NAME']][] = $fk;
}

$pageTitle = 'DB スキーマ';
$activeMenu = 'db_schema';
require __DIR__ . '/../includes/header.php';
?>

<div class="text-secondary mb-3">現在の <?= htmlspecialchars($pdo->query('SELECT DATABASE()')->fetchColumn()) ?> データベースの構造です（テーブル定義を直接参照して自動生成）。</div>

<?php foreach ($tables as $tableName => $cols): ?>
  <div class="panel mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h5 class="mb-0"><?= htmlspecialchars($tableName) ?></h5>
      <span class="text-secondary" style="font-size:12px;">約 <?= number_format($rowCounts[$tableName] ?? 0) ?> 件</span>
    </div>
    <table class="data-table mb-2">
      <thead>
        <tr><th>カラム</th><th>型</th><th>NULL</th><th>キー</th><th>デフォルト</th><th>その他</th></tr>
      </thead>
      <tbody>
        <?php foreach ($cols as $col): ?>
        <tr>
          <td style="font-weight:600;"><?= htmlspecialchars($col['COLUMN_NAME']) ?></td>
          <td class="text-secondary"><?= htmlspecialchars($col['COLUMN_TYPE']) ?></td>
          <td><?= $col['IS_NULLABLE'] === 'YES' ? '○' : '-' ?></td>
          <td>
            <?php if ($col['COLUMN_KEY'] === 'PRI'): ?><span class="badge-status badge-active">PK</span>
            <?php elseif ($col['COLUMN_KEY'] === 'MUL'): ?><span class="badge-status badge-pending">FK</span>
            <?php elseif ($col['COLUMN_KEY'] === 'UNI'): ?><span class="badge-status badge-hold">UNIQUE</span>
            <?php endif; ?>
          </td>
          <td class="text-secondary"><?= htmlspecialchars($col['COLUMN_DEFAULT'] ?? '-') ?></td>
          <td class="text-secondary"><?= htmlspecialchars($col['EXTRA']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (!empty($fksByTable[$tableName])): ?>
      <div style="font-size:12px;" class="text-secondary">
        関連:
        <?php foreach ($fksByTable[$tableName] as $fk): ?>
          <span class="badge-status badge-unregistered me-1"><?= htmlspecialchars($fk['COLUMN_NAME']) ?> → <?= htmlspecialchars($fk['REFERENCED_TABLE_NAME']) ?>.<?= htmlspecialchars($fk['REFERENCED_COLUMN_NAME']) ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
