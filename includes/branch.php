<?php
require_once __DIR__ . '/../config/database.php';

function current_branch_id(): int {
    if (!empty($_SESSION['branch_id'])) {
        return (int) $_SESSION['branch_id'];
    }
    $first = db()->query('SELECT id FROM branches ORDER BY id LIMIT 1')->fetch();
    $id = $first ? (int) $first['id'] : 0;
    $_SESSION['branch_id'] = $id;
    return $id;
}

function current_branch_name(): string {
    $stmt = db()->prepare('SELECT name FROM branches WHERE id = ?');
    $stmt->execute([current_branch_id()]);
    $row = $stmt->fetch();
    return $row ? $row['name'] : '店舗未設定';
}
