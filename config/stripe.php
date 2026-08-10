<?php
// Stripe 設定を返す。環境変数があれば優先、なければ config/stripe_keys.local.php を読む。
function stripe_config(): array {
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $local = [];
    $localFile = __DIR__ . '/stripe_keys.local.php';
    if (is_file($localFile)) {
        $local = require $localFile;
    }

    $config = [
        'secret_key' => getenv('STRIPE_SECRET_KEY') ?: ($local['secret_key'] ?? ''),
        'publishable_key' => getenv('STRIPE_PUBLISHABLE_KEY') ?: ($local['publishable_key'] ?? ''),
        'webhook_secret' => getenv('STRIPE_WEBHOOK_SECRET') ?: ($local['webhook_secret'] ?? ''),
    ];
    return $config;
}

function stripe_is_configured(): bool {
    return stripe_config()['secret_key'] !== '';
}
