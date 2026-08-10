<?php
// Google Sign-In 設定を返す。環境変数があれば優先、なければ config/google_keys.local.php を読む。
function google_config(): array {
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $local = [];
    $localFile = __DIR__ . '/google_keys.local.php';
    if (is_file($localFile)) {
        $local = require $localFile;
    }

    $config = [
        'client_id' => getenv('GOOGLE_CLIENT_ID') ?: ($local['client_id'] ?? ''),
    ];
    return $config;
}

function google_is_configured(): bool {
    return google_config()['client_id'] !== '';
}
