<?php
// SMTP設定を返す。環境変数があれば優先、なければ config/smtp_keys.local.php を読む。
function smtp_config(): array {
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $local = [];
    $localFile = __DIR__ . '/smtp_keys.local.php';
    if (is_file($localFile)) {
        $local = require $localFile;
    }

    $config = [
        'host' => getenv('SMTP_HOST') ?: ($local['host'] ?? ''),
        'port' => (int) (getenv('SMTP_PORT') ?: ($local['port'] ?? 587)),
        'secure' => getenv('SMTP_SECURE') ?: ($local['secure'] ?? 'tls'),
        'username' => getenv('SMTP_USERNAME') ?: ($local['username'] ?? ''),
        'password' => getenv('SMTP_PASSWORD') ?: ($local['password'] ?? ''),
        'from_email' => getenv('SMTP_FROM_EMAIL') ?: ($local['from_email'] ?? ''),
        'from_name' => getenv('SMTP_FROM_NAME') ?: ($local['from_name'] ?? 'RSVP'),
        'to_email' => getenv('SMTP_TO_EMAIL') ?: ($local['to_email'] ?? ''),
    ];
    return $config;
}

function smtp_is_configured(): bool {
    $c = smtp_config();
    return $c['host'] !== '' && $c['from_email'] !== '' && $c['to_email'] !== '';
}
