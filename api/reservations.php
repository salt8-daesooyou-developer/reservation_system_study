<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/activity_log.php';
require_login_api();

header('Content-Type: application/json; charset=utf-8');
$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];
$staffId = (int) $_SESSION['staff_id'];

function input(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

$validStatuses = ['reserved', 'show', 'noshow', 'cancelled'];

if ($method === 'GET' && isset($_GET['customer_search'])) {
    $q = trim($_GET['customer_search']);
    $stmt = $pdo->prepare('SELECT id, name, phone FROM customers WHERE name LIKE ? ORDER BY name LIMIT 20');
    $stmt->execute(['%' . $q . '%']);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($method === 'POST') {
    $d = input();
    $scheduleId = (int) ($d['schedule_id'] ?? 0);
    $customerId = (int) ($d['customer_id'] ?? 0);

    if (!$scheduleId || !$customerId) {
        http_response_code(422);
        echo json_encode(['error' => 'schedule_id_and_customer_id_required']);
        exit;
    }

    $sStmt = $pdo->prepare('
        SELECT s.capacity,
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

    $clsStmt = $pdo->prepare('SELECT c.name FROM schedules s JOIN classes c ON c.id = s.class_id WHERE s.id = ?');
    $clsStmt->execute([$scheduleId]);
    $className = $clsStmt->fetchColumn();
    log_activity($pdo, $customerId, 'カレンダー', 'reservation_add', "「{$className}」を予約登録しました", $staffId);

    echo json_encode(['id' => (int) $pdo->lastInsertId()]);
    exit;
}

if ($method === 'PUT') {
    $id = (int) ($_GET['id'] ?? 0);
    $d = input();
    $status = $d['status'] ?? '';
    if (!$id || !in_array($status, $validStatuses, true)) {
        http_response_code(422);
        echo json_encode(['error' => 'invalid_request']);
        exit;
    }
    $pdo->prepare('UPDATE reservations SET status = ? WHERE id = ?')->execute([$status, $id]);

    $infoStmt = $pdo->prepare('
        SELECT r.customer_id, c.name AS class_name
        FROM reservations r JOIN schedules s ON s.id = r.schedule_id JOIN classes c ON c.id = s.class_id
        WHERE r.id = ?
    ');
    $infoStmt->execute([$id]);
    $info = $infoStmt->fetch();
    if ($info) {
        $statusLabel = ['reserved' => '予約中', 'show' => '出席', 'noshow' => '欠席', 'cancelled' => 'キャンセル'][$status] ?? $status;
        log_activity($pdo, (int) $info['customer_id'], 'カレンダー', 'reservation_status', "「{$info['class_name']}」を{$statusLabel}に変更しました", $staffId);
    }

    echo json_encode(['ok' => true]);
    exit;
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) {
        http_response_code(422);
        echo json_encode(['error' => 'id_required']);
        exit;
    }

    $infoStmt = $pdo->prepare('
        SELECT r.customer_id, c.name AS class_name
        FROM reservations r JOIN schedules s ON s.id = r.schedule_id JOIN classes c ON c.id = s.class_id
        WHERE r.id = ?
    ');
    $infoStmt->execute([$id]);
    $info = $infoStmt->fetch();

    $pdo->prepare('DELETE FROM reservations WHERE id = ?')->execute([$id]);

    if ($info) {
        log_activity($pdo, (int) $info['customer_id'], 'カレンダー', 'reservation_delete', "「{$info['class_name']}」の予約を削除しました", $staffId);
    }

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method_not_allowed']);
