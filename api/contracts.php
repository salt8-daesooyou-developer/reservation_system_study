<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/activity_log.php';
require_login_api();

header('Content-Type: application/json; charset=utf-8');
$pdo = db();
$staffId = (int) $_SESSION['staff_id'];
$method = $_SERVER['REQUEST_METHOD'];

function input(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

if ($method === 'POST') {
    $d = input();
    $customerId = (int) ($d['customer_id'] ?? 0);
    $title = trim($d['title'] ?? '');
    $contractDate = $d['contract_date'] ?? date('Y-m-d');

    if (!$customerId || $title === '') {
        http_response_code(422);
        echo json_encode(['error' => 'customer_id_and_title_required']);
        exit;
    }

    $stmt = $pdo->prepare('
        INSERT INTO contracts (customer_id, customer_membership_id, title, contract_date, file_path, memo, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $customerId,
        $d['customer_membership_id'] ?? null,
        $title,
        $contractDate,
        ($d['file_path'] ?? '') !== '' ? $d['file_path'] : null,
        ($d['memo'] ?? '') !== '' ? $d['memo'] : null,
        $staffId,
    ]);

    log_activity($pdo, $customerId, 'CRM', 'contract_add', "契約書「{$title}」を登録しました", $staffId);

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
    $pdo->prepare('DELETE FROM contracts WHERE id = ?')->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method_not_allowed']);
