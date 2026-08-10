<?php
// 1回限りの実行用スクリプト: 今日から30日間、全店舗に午前(10:00-12:00)/午後(15:00-17:00)
// クラスのスケジュールを定員18名で生成する。既存の組み合わせはスキップする。
require __DIR__ . '/../config/database.php';

$pdo = db();
$days = 30;

$classes = $pdo->query("SELECT id, category FROM classes WHERE category IN ('AM','PM')")->fetchAll();
if (!$classes) {
    fwrite(STDERR, "AM/PM classes not found. Run sql/seed.sql first.\n");
    exit(1);
}
$classByCategory = [];
foreach ($classes as $c) {
    $classByCategory[$c['category']] = (int) $c['id'];
}

$branches = $pdo->query('SELECT id FROM branches ORDER BY id')->fetchAll();
if (!$branches) {
    fwrite(STDERR, "No branches found.\n");
    exit(1);
}

$slots = [
    ['category' => 'AM', 'start' => '10:00:00', 'end' => '12:00:00'],
    ['category' => 'PM', 'start' => '15:00:00', 'end' => '17:00:00'],
];

$existsStmt = $pdo->prepare('
    SELECT id FROM schedules
    WHERE branch_id = ? AND class_id = ? AND schedule_date = ? AND start_time = ?
');
$insertStmt = $pdo->prepare('
    INSERT INTO schedules (branch_id, class_id, instructor_name, schedule_date, start_time, end_time, capacity)
    VALUES (?, ?, NULL, ?, ?, ?, 18)
');

$created = 0;
$today = new DateTime('today');

foreach ($branches as $branch) {
    $branchId = (int) $branch['id'];
    for ($i = 0; $i < $days; $i++) {
        $date = (clone $today)->modify("+{$i} day")->format('Y-m-d');
        foreach ($slots as $slot) {
            $classId = $classByCategory[$slot['category']] ?? null;
            if (!$classId) continue;

            $existsStmt->execute([$branchId, $classId, $date, $slot['start']]);
            if ($existsStmt->fetch()) continue;

            $insertStmt->execute([$branchId, $classId, $date, $slot['start'], $slot['end']]);
            $created++;
        }
    }
}

echo "Created {$created} schedules.\n";
