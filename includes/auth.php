<?php
session_start();

function require_login(): void {
    if (empty($_SESSION['staff_id'])) {
        header('Location: /reservation_system_study/login.php');
        exit;
    }
}

function require_login_api(): void {
    if (empty($_SESSION['staff_id'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'unauthorized']);
        exit;
    }
}

function is_admin(): bool {
    return ($_SESSION['staff_role'] ?? '') === 'admin';
}

function require_admin(): void {
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        echo '権限がありません（管理者専用ページです）。';
        exit;
    }
}

function require_admin_api(): void {
    require_login_api();
    if (!is_admin()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'forbidden']);
        exit;
    }
}

function require_customer_login(): void {
    if (empty($_SESSION['customer_id'])) {
        header('Location: /reservation_system_study/customer_login.php');
        exit;
    }
}
