<?php
require_once __DIR__ . '/../config/stripe.php';

const STRIPE_API_BASE = 'https://api.stripe.com/v1';

/**
 * Stripe REST API への認証付きリクエスト（Stripe PHP SDK は未導入のため curl 直叩き）。
 * $params は http_build_query() でネスト配列をそのまま Stripe のブラケット記法に変換できる。
 */
function stripe_request(string $method, string $path, array $params = []): array {
    $secretKey = stripe_config()['secret_key'];
    if ($secretKey === '') {
        throw new RuntimeException('stripe_not_configured');
    }

    $url = STRIPE_API_BASE . $path;
    $body = http_build_query($params);

    $ch = curl_init();
    $method = strtoupper($method);
    if ($method === 'GET') {
        $url .= $body !== '' ? '?' . $body : '';
    } else {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $secretKey . ':');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('stripe_request_failed: ' . $error);
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true) ?? [];
    if ($status >= 400) {
        throw new RuntimeException('stripe_api_error: ' . ($data['error']['message'] ?? "http_{$status}"));
    }
    return $data;
}

/** 顧客に紐づく Stripe Customer を取得、なければ新規作成して customers.stripe_customer_id に保存 */
function stripe_get_or_create_customer(PDO $pdo, array $customer): string {
    if (!empty($customer['stripe_customer_id'])) {
        return $customer['stripe_customer_id'];
    }
    $params = ['name' => $customer['name']];
    if (!empty($customer['email'])) {
        $params['email'] = $customer['email'];
    }
    $stripeCustomer = stripe_request('POST', '/customers', $params);
    $stmt = $pdo->prepare('UPDATE customers SET stripe_customer_id = ? WHERE id = ?');
    $stmt->execute([$stripeCustomer['id'], $customer['id']]);
    return $stripeCustomer['id'];
}

/** 月額サブスクリプション用の Checkout Session を作成し、遷移先 URL を返す */
function stripe_create_subscription_checkout(string $stripeCustomerId, string $priceId, string $successUrl, string $cancelUrl): array {
    return stripe_request('POST', '/checkout/sessions', [
        'mode' => 'subscription',
        'customer' => $stripeCustomerId,
        'line_items' => [
            ['price' => $priceId, 'quantity' => 1],
        ],
        'success_url' => $successUrl,
        'cancel_url' => $cancelUrl,
    ]);
}

/**
 * Webhook 署名検証（Stripe-Signature: t=...,v1=...）。
 * https://docs.stripe.com/webhooks#verify-manually
 */
function stripe_verify_webhook_signature(string $payload, string $sigHeader, string $webhookSecret, int $toleranceSeconds = 300): bool {
    $parts = [];
    foreach (explode(',', $sigHeader) as $pair) {
        [$k, $v] = array_pad(explode('=', $pair, 2), 2, null);
        $parts[$k][] = $v;
    }
    $timestamp = $parts['t'][0] ?? null;
    $signatures = $parts['v1'] ?? [];
    if (!$timestamp || !$signatures) {
        return false;
    }
    if (abs(time() - (int) $timestamp) > $toleranceSeconds) {
        return false;
    }
    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $webhookSecret);
    foreach ($signatures as $sig) {
        if (hash_equals($expected, $sig)) {
            return true;
        }
    }
    return false;
}
