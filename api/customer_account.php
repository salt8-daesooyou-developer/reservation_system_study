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

const SPECIAL_CHARS_PATTERN = '/[!@#$%^&*()_+\-=\[\]{};\':"\\\\|,.<>\/?~`]/';

if ($method === 'PUT' && isset($_GET['password'])) {
    $d = input();
    $current = $d['current_password'] ?? '';
    $new = $d['new_password'] ?? '';
    $confirm = $d['new_password_confirm'] ?? '';

    $stmt = $pdo->prepare('SELECT password_hash FROM customers WHERE id = ?');
    $stmt->execute([$customerId]);
    $hash = $stmt->fetchColumn();

    if (!$hash || !password_verify($current, $hash)) {
        http_response_code(422);
        echo json_encode(['error' => 'current_password_incorrect']);
        exit;
    }
    if ($new !== $confirm) {
        http_response_code(422);
        echo json_encode(['error' => 'password_mismatch']);
        exit;
    }
    if (mb_strlen($new) < 8 || !preg_match(SPECIAL_CHARS_PATTERN, $new)) {
        http_response_code(422);
        echo json_encode(['error' => 'password_invalid']);
        exit;
    }

    $pdo->prepare('UPDATE customers SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($new, PASSWORD_DEFAULT), $customerId]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($method === 'PUT') {
    $d = input();
    $name = trim($d['name'] ?? '');
    $email = trim($d['email'] ?? '');
    $phone = trim($d['phone'] ?? '');
    $branchId = (int) ($d['branch_id'] ?? 0);

    if ($name === '') {
        http_response_code(422);
        echo json_encode(['error' => 'name_required']);
        exit;
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        echo json_encode(['error' => 'invalid_email']);
        exit;
    }
    if ($phone === '') {
        http_response_code(422);
        echo json_encode(['error' => 'phone_required']);
        exit;
    }
    if (!$branchId) {
        http_response_code(422);
        echo json_encode(['error' => 'branch_required']);
        exit;
    }
    $branchStmt = $pdo->prepare('SELECT id FROM branches WHERE id = ?');
    $branchStmt->execute([$branchId]);
    if (!$branchStmt->fetch()) {
        http_response_code(422);
        echo json_encode(['error' => 'branch_not_found']);
        exit;
    }

    $dupStmt = $pdo->prepare('SELECT id, phone, email FROM customers WHERE (phone = ? OR email = ?) AND id != ?');
    $dupStmt->execute([$phone, $email, $customerId]);
    if ($dup = $dupStmt->fetch()) {
        http_response_code(422);
        echo json_encode(['error' => $dup['phone'] === $phone ? 'duplicate_phone' : 'duplicate_email']);
        exit;
    }

    $stmt = $pdo->prepare('
        UPDATE customers SET name = ?, name_kana = ?, phone = ?, email = ?, branch_id = ?
        WHERE id = ?
    ');
    $stmt->execute([
        $name,
        trim($d['name_kana'] ?? '') ?: null,
        $phone,
        $email,
        $branchId,
        $customerId,
    ]);
    $_SESSION['customer_name'] = $name;
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method_not_allowed']);
