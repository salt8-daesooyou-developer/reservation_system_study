<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/branch.php';
require_login_api();

header('Content-Type: application/json; charset=utf-8');
$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

function input(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

if ($method === 'GET' && isset($_GET['classes'])) {
    echo json_encode($pdo->query('SELECT * FROM classes ORDER BY id')->fetchAll());
    exit;
}

if ($method === 'GET' && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $stmt = $pdo->prepare('
        SELECT s.*, c.name AS class_name, c.category
        FROM schedules s JOIN classes c ON c.id = s.class_id
        WHERE s.id = ?
    ');
    $stmt->execute([$id]);
    $schedule = $stmt->fetch();
    if (!$schedule) {
        http_response_code(404);
        echo json_encode(['error' => 'not_found']);
        exit;
    }
    $rStmt = $pdo->prepare('
        SELECT r.*, cu.name AS customer_name
        FROM reservations r JOIN customers cu ON cu.id = r.customer_id
        WHERE r.schedule_id = ?
        ORDER BY r.reserved_at
    ');
    $rStmt->execute([$id]);
    $schedule['reservations'] = $rStmt->fetchAll();
    echo json_encode($schedule);
    exit;
}

if ($method === 'GET' && isset($_GET['year']) && isset($_GET['summary'])) {
    $year = (int) $_GET['year'];
    $start = sprintf('%04d-01-01', $year);
    $end = sprintf('%04d-01-01', $year + 1);

    $stmt = $pdo->prepare('
        SELECT schedule_date, COUNT(*) AS count
        FROM schedules
        WHERE branch_id = ? AND schedule_date >= ? AND schedule_date < ?
        GROUP BY schedule_date
    ');
    $stmt->execute([current_branch_id(), $start, $end]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($method === 'GET' && isset($_GET['start']) && isset($_GET['end'])) {
    $start = $_GET['start'];
    $end = $_GET['end'];

    $stmt = $pdo->prepare('
        SELECT s.id, s.schedule_date, s.start_time, s.end_time, s.capacity, s.status,
               c.name AS class_name, s.instructor_name,
               (SELECT COUNT(*) FROM reservations r WHERE r.schedule_id = s.id AND r.status IN ("reserved","show")) AS booked
        FROM schedules s
        JOIN classes c ON c.id = s.class_id
        WHERE s.branch_id = ? AND s.schedule_date >= ? AND s.schedule_date < ?
        ORDER BY s.schedule_date, s.start_time
    ');
    $stmt->execute([current_branch_id(), $start, $end]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($method === 'GET') {
    $year = (int) ($_GET['year'] ?? date('Y'));
    $month = (int) ($_GET['month'] ?? date('n'));
    $start = sprintf('%04d-%02d-01', $year, $month);
    $end = date('Y-m-d', strtotime($start . ' +1 month'));

    $stmt = $pdo->prepare('
        SELECT s.id, s.schedule_date, s.start_time, s.end_time, s.capacity, s.status,
               c.name AS class_name, s.instructor_name,
               (SELECT COUNT(*) FROM reservations r WHERE r.schedule_id = s.id AND r.status IN ("reserved","show")) AS booked
        FROM schedules s
        JOIN classes c ON c.id = s.class_id
        WHERE s.branch_id = ? AND s.schedule_date >= ? AND s.schedule_date < ?
        ORDER BY s.schedule_date, s.start_time
    ');
    $stmt->execute([current_branch_id(), $start, $end]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($method === 'POST') {
    $d = input();
    $classId = (int) ($d['class_id'] ?? 0);
    $date = $d['schedule_date'] ?? '';
    $startTime = $d['start_time'] ?? '';
    $endTime = $d['end_time'] ?? '';

    if (!$classId || !$date || !$startTime || !$endTime) {
        http_response_code(422);
        echo json_encode(['error' => 'required_fields_missing']);
        exit;
    }

    $stmt = $pdo->prepare('
        INSERT INTO schedules (branch_id, class_id, instructor_name, schedule_date, start_time, end_time, capacity)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        current_branch_id(),
        $classId,
        $d['instructor_name'] ?? null,
        $date,
        $startTime,
        $endTime,
        (int) ($d['capacity'] ?? 10),
    ]);
    echo json_encode(['id' => (int) $pdo->lastInsertId()]);
    exit;
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) {
        http_response_code(422);
        echo json_encode(['error' => 'id_required']);
        exit;
    }
    $pdo->prepare('DELETE FROM schedules WHERE id = ?')->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method_not_allowed']);
