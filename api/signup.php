<?php
require __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
$pdo = db();

function input(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

const SPECIAL_CHARS_PATTERN = '/[!@#$%^&*()_+\-=\[\]{};\':"\\\\|,.<>\/?~`]/';

function validate_password(string $password): ?string {
    if (mb_strlen($password) < 8) {
        return 'password_too_short';
    }
    if (!preg_match(SPECIAL_CHARS_PATTERN, $password)) {
        return 'password_needs_special_char';
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$d = input();
$type = $d['type'] ?? '';
$password = $d['password'] ?? '';
$passwordConfirm = $d['password_confirm'] ?? '';
$name = trim($d['name'] ?? '');

if (!in_array($type, ['customer', 'staff'], true)) {
    http_response_code(422);
    echo json_encode(['error' => 'invalid_type']);
    exit;
}
if ($name === '') {
    http_response_code(422);
    echo json_encode(['error' => 'name_required']);
    exit;
}
if ($password !== $passwordConfirm) {
    http_response_code(422);
    echo json_encode(['error' => 'password_mismatch']);
    exit;
}
$pwError = validate_password($password);
if ($pwError) {
    http_response_code(422);
    echo json_encode(['error' => $pwError]);
    exit;
}

if ($type === 'customer') {
    $phone = trim($d['phone'] ?? '');
    $email = trim($d['email'] ?? '');
    $gender = in_array($d['gender'] ?? '', ['male', 'female'], true) ? $d['gender'] : 'unknown';
    $birthDate = ($d['birth_date'] ?? '') ?: null;
    $nameKana = trim($d['name_kana'] ?? '');
    $branchId = (int) ($d['branch_id'] ?? 0);

    if ($phone === '') {
        http_response_code(422);
        echo json_encode(['error' => 'phone_required']);
        exit;
    }
    if ($email === '') {
        http_response_code(422);
        echo json_encode(['error' => 'email_required']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        echo json_encode(['error' => 'invalid_email']);
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

    $dupStmt = $pdo->prepare('SELECT phone, email FROM customers WHERE phone = ? OR email = ?');
    $dupStmt->execute([$phone, $email]);
    if ($dup = $dupStmt->fetch()) {
        http_response_code(422);
        echo json_encode(['error' => $dup['phone'] === $phone ? 'duplicate_phone' : 'duplicate_email']);
        exit;
    }

    $stmt = $pdo->prepare('
        INSERT INTO customers (name, name_kana, gender, birth_date, phone, email, branch_id, password_hash, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, "unregistered")
    ');
    try {
        $stmt->execute([
            $name,
            $nameKana ?: null,
            $gender,
            $birthDate,
            $phone,
            $email,
            $branchId,
            password_hash($password, PASSWORD_DEFAULT),
        ]);
    } catch (PDOException $e) {
        http_response_code(422);
        echo json_encode(['error' => 'duplicate_customer']);
        exit;
    }
    echo json_encode(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
    exit;
}

// type === 'staff'
$username = trim($d['username'] ?? '');
if ($username === '') {
    http_response_code(422);
    echo json_encode(['error' => 'username_required']);
    exit;
}

$stmt = $pdo->prepare('INSERT INTO staff (username, password_hash, name, role) VALUES (?, ?, ?, "staff")');
try {
    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $name]);
} catch (PDOException $e) {
    http_response_code(422);
    echo json_encode(['error' => 'duplicate_username']);
    exit;
}
echo json_encode(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
