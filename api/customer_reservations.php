<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/branch.php';

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

if ($method === 'GET') {
    $start = $_GET['start'] ?? '';
    $end = $_GET['end'] ?? '';
    if (!$start || !$end) {
        http_response_code(422);
        echo json_encode(['error' => 'start_and_end_required']);
        exit;
    }

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
    $stmt->execute([$customerId, current_branch_id(), $start, $end]);
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
        SELECT s.capacity, s.schedule_date,
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

    $dupStmt = $pdo->prepare('SELECT id FROM reservations WHERE schedule_id = ? AND customer_id = ?');
    $dupStmt->execute([$scheduleId, $customerId]);
    if ($dupStmt->fetch()) {
        http_response_code(422);
        echo json_encode(['error' => 'already_reserved']);
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
