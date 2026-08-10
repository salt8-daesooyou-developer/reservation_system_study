<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/mailer.php';

if (empty($_SESSION['customer_id'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
$pdo = db();
$customerId = (int) $_SESSION['customer_id'];

function input(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT * FROM inquiries WHERE customer_id = ? ORDER BY created_at DESC');
    $stmt->execute([$customerId]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = input();
    $subject = trim($d['subject'] ?? '');
    $message = trim($d['message'] ?? '');

    if ($subject === '' || $message === '') {
        http_response_code(422);
        echo json_encode(['error' => 'subject_and_message_required']);
        exit;
    }

    $cStmt = $pdo->prepare('SELECT name, email FROM customers WHERE id = ?');
    $cStmt->execute([$customerId]);
    $customer = $cStmt->fetch();

    $insert = $pdo->prepare('INSERT INTO inquiries (customer_id, subject, message) VALUES (?, ?, ?)');
    $insert->execute([$customerId, $subject, $message]);
    $inquiryId = (int) $pdo->lastInsertId();

    // メール送信はベストエフォート。失敗してもDB保存は既に成功しているので問い合わせ自体は成立させる。
    $emailSent = false;
    if (smtp_is_configured()) {
        $body = "顧客名: {$customer['name']}\nメール: {$customer['email']}\n\n{$message}";
        $emailSent = smtp_send_mail("[お問い合わせ] {$subject}", $body, $customer['email'] ?? null);
        if ($emailSent) {
            $pdo->prepare('UPDATE inquiries SET email_sent = 1 WHERE id = ?')->execute([$inquiryId]);
        }
    }

    echo json_encode(['ok' => true, 'id' => $inquiryId, 'email_sent' => $emailSent]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method_not_allowed']);
