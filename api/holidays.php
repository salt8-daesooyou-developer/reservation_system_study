<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/branch.php';

header('Content-Type: application/json; charset=utf-8');
$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

function input(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// GET(休日一覧)は顧客カレンダーでも使うため、branch_id 指定があれば未ログインでも許可。
if ($method === 'GET') {
    $branchId = (int) ($_GET['branch_id'] ?? 0);
    $start = $_GET['start'] ?? '';
    $end = $_GET['end'] ?? '';
    if (!$branchId || !$start || !$end) {
        http_response_code(422);
        echo json_encode(['error' => 'branch_id_start_end_required']);
        exit;
    }
    $stmt = $pdo->prepare('
        SELECT holiday_date, memo FROM branch_holidays
        WHERE branch_id = ? AND holiday_date >= ? AND holiday_date < ?
        ORDER BY holiday_date
    ');
    $stmt->execute([$branchId, $start, $end]);
    echo json_encode($stmt->fetchAll());
    exit;
}

require_login_api();

if ($method === 'POST') {
    $d = input();
    $date = $d['holiday_date'] ?? '';
    if (!$date) {
        http_response_code(422);
        echo json_encode(['error' => 'holiday_date_required']);
        exit;
    }
    $stmt = $pdo->prepare('
        INSERT INTO branch_holidays (branch_id, holiday_date, memo) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE memo = VALUES(memo)
    ');
    $stmt->execute([current_branch_id(), $date, trim($d['memo'] ?? '') ?: null]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($method === 'DELETE') {
    $date = $_GET['date'] ?? '';
    if (!$date) {
        http_response_code(422);
        echo json_encode(['error' => 'date_required']);
        exit;
    }
    $pdo->prepare('DELETE FROM branch_holidays WHERE branch_id = ? AND holiday_date = ?')
        ->execute([current_branch_id(), $date]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method_not_allowed']);
