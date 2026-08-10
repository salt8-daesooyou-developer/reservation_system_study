<?php
require_once __DIR__ . '/../config/google.php';

/**
 * Google の ID トークン(JWT)を検証し、ペイロード(email/name/sub等)を返す。
 * Composer未導入のため、Google公式の tokeninfo エンドポイントに投げて検証する
 * 簡易実装（本番の大規模運用ではJWK署名検証の方が推奨されるが、curlのみで完結する
 * この方式は小〜中規模のアプリでは一般的に使われる）。
 *
 * @return array{sub:string,email:string,email_verified:bool,name:string}|null
 */
function google_verify_id_token(string $idToken): ?array {
    $clientId = google_config()['client_id'];
    if ($clientId === '' || $idToken === '') {
        return null;
    }

    $ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $status !== 200) {
        return null;
    }
    $payload = json_decode($response, true);
    if (!is_array($payload)) {
        return null;
    }
    if (($payload['aud'] ?? '') !== $clientId) {
        return null; // このアプリ向けに発行されたトークンではない
    }
    if (($payload['email_verified'] ?? 'false') !== 'true') {
        return null;
    }

    return [
        'sub' => $payload['sub'] ?? '',
        'email' => $payload['email'] ?? '',
        'email_verified' => true,
        'name' => $payload['name'] ?? ($payload['email'] ?? ''),
    ];
}
