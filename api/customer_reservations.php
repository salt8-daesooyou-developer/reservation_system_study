<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/database.php';

if (empty($_SESSION['customer_id'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];
$customerId = (int) $_SESSION['customer_id'];

function input(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** ログイン中の顧客が登録した店舗ID（未登録なら最初の店舗にフォールバック） */
function customer_branch_id(PDO $pdo, int $customerId): int {
    $stmt = $pdo->prepare('SELECT branch_id FROM customers WHERE id = ?');
    $stmt->execute([$customerId]);
    $branchId = (int) ($stmt->fetchColumn() ?: 0);
    if ($branchId) {
        return $branchId;
    }
    $first = $pdo->query('SELECT id FROM branches ORDER BY id LIMIT 1')->fetch();
    return $first ? (int) $first['id'] : 0;
}

$branchId = customer_branch_id($pdo, $customerId);

if ($method === 'GET' && isset($_GET['year']) && isset($_GET['summary'])) {
    $year = (int) $_GET['year'];
    $start = sprintf('%04d-01-01', $year);
    $end = sprintf('%04d-01-01', $year + 1);

    $stmt = $pdo->prepare('
        SELECT s.schedule_date, COUNT(*) AS count,
               MAX(CASE WHEN my.id IS NOT NULL THEN 1 ELSE 0 END) AS my_reserved
        FROM schedules s
        LEFT JOIN reservations my ON my.schedule_id = s.id AND my.customer_id = ? AND my.status IN ("reserved","show")
        WHERE s.branch_id = ? AND s.schedule_date >= ? AND s.schedule_date < ?
        GROUP BY s.schedule_date
    ');
    $stmt->execute([$customerId, $branchId, $start, $end]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($method === 'GET' && isset($_GET['start']) && isset($_GET['end'])) {
    $start = $_GET['start'];
    $end = $_GET['end'];

    $stmt = $pdo->prepare('
        SELECT s.id, s.schedule_date, s.start_time, s.end_time, s.capacity, s.status,
               c.name AS class_name, c.category, s.instructor_name,
               (SELECT COUNT(*) FROM reservations r WHERE r.schedule_id = s.id AND r.status IN ("reserved","show")) AS booked,
               my.id AS my_reservation_id, my.status AS my_status
        FROM schedules s
        JOIN classes c ON c.id = s.class_id
        LEFT JOIN reservations my ON my.schedule_id = s.id AND my.customer_id = ? AND my.status IN ("reserved","show")
        WHERE s.branch_id = ? AND s.schedule_date >= ? AND s.schedule_date < ?
        ORDER BY s.schedule_date, s.start_time
    ');
    $stmt->execute([$customerId, $branchId, $start, $end]);
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
               c.name AS class_name, c.category, s.instructor_name,
               (SELECT COUNT(*) FROM reservations r WHERE r.schedule_id = s.id AND r.status IN ("reserved","show")) AS booked,
               my.id AS my_reservation_id, my.status AS my_status
        FROM schedules s
        JOIN classes c ON c.id = s.class_id
        LEFT JOIN reservations my ON my.schedule_id = s.id AND my.customer_id = ? AND my.status IN ("reserved","show")
        WHERE s.branch_id = ? AND s.schedule_date >= ? AND s.schedule_date < ?
        ORDER BY s.schedule_date, s.start_time
    ');
    $stmt->execute([$customerId, $branchId, $start, $end]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($method === 'POST') {
    $d = input();
    $scheduleId = (int) ($d['schedule_id'] ?? 0);
    if (!$scheduleId) {
        http_response_code(422);
        echo json_encode(['error' => 'schedule_id_required']);
        exit;
    }

    $sStmt = $pdo->prepare('
        SELECT s.capacity, s.schedule_date, s.branch_id,
               (SELECT COUNT(*) FROM reservations r WHERE r.schedule_id = s.id AND r.status IN ("reserved","show")) AS booked
        FROM schedules s WHERE s.id = ?
    ');
    $sStmt->execute([$scheduleId]);
    $schedule = $sStmt->fetch();
    if (!$schedule) {
        http_response_code(404);
        echo json_encode(['error' => 'schedule_not_found']);
        exit;
    }
    if ($schedule['schedule_date'] < date('Y-m-d')) {
        http_response_code(422);
        echo json_encode(['error' => 'past_schedule']);
        exit;
    }
    if ($schedule['booked'] >= $schedule['capacity']) {
        http_response_code(422);
        echo json_encode(['error' => 'capacity_full']);
        exit;
    }

    $holidayStmt = $pdo->prepare('SELECT id FROM branch_holidays WHERE branch_id = ? AND holiday_date = ?');
    $holidayStmt->execute([$schedule['branch_id'], $schedule['schedule_date']]);
    if ($holidayStmt->fetch()) {
        http_response_code(422);
        echo json_encode(['error' => 'branch_holiday']);
        exit;
    }

    $dupStmt = $pdo->prepare('SELECT id FROM reservations WHERE schedule_id = ? AND customer_id = ?');
    $dupStmt->execute([$scheduleId, $customerId]);
    if ($dupStmt->fetch()) {
        http_response_code(422);
        echo json_encode(['error' => 'already_reserved']);
        exit;
    }

    // 1日1回まで（午前・午後を問わず、同じ日に既に予約があれば不可）
    $dayStmt = $pdo->prepare('
        SELECT r.id FROM reservations r
        JOIN schedules s2 ON s2.id = r.schedule_id
        WHERE r.customer_id = ? AND s2.schedule_date = ? AND r.status IN ("reserved","show")
    ');
    $dayStmt->execute([$customerId, $schedule['schedule_date']]);
    if ($dayStmt->fetch()) {
        http_response_code(422);
        echo json_encode(['error' => 'daily_limit_reached']);
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO reservations (schedule_id, customer_id, status) VALUES (?, ?, "reserved")');
    $stmt->execute([$scheduleId, $customerId]);
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

    $stmt = $pdo->prepare('
        SELECT r.customer_id, s.schedule_date
        FROM reservations r JOIN schedules s ON s.id = r.schedule_id
        WHERE r.id = ?
    ');
    $stmt->execute([$id]);
    $reservation = $stmt->fetch();
    if (!$reservation || (int) $reservation['customer_id'] !== $customerId) {
        http_response_code(404);
        echo json_encode(['error' => 'not_found']);
        exit;
    }
    if ($reservation['schedule_date'] < date('Y-m-d')) {
        http_response_code(422);
        echo json_encode(['error' => 'past_schedule']);
        exit;
    }

    $pdo->prepare('DELETE FROM reservations WHERE id = ?')->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method_not_allowed']);
