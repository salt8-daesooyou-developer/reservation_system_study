<?php
require_once __DIR__ . '/../config/smtp.php';

/**
 * 最小限の SMTP クライアント（Composer未導入のためPHPMailer等は使わず fsockopen で直接実装）。
 * STARTTLS(587) / SSL(465) 両対応、AUTH LOGIN のみサポート。
 */
function smtp_send_mail(string $subject, string $body, ?string $replyTo = null): bool {
    $c = smtp_config();
    if (!smtp_is_configured()) {
        return false;
    }

    $host = $c['host'];
    $port = $c['port'];
    $transport = $c['secure'] === 'ssl' ? 'ssl://' : '';

    $fp = @fsockopen($transport . $host, $port, $errno, $errstr, 10);
    if (!$fp) {
        return false;
    }
    stream_set_timeout($fp, 10);

    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break; // "250 " のようにハイフンでなくスペースなら最終行
            }
        }
        return $data;
    };
    $write = function (string $cmd) use ($fp) {
        fwrite($fp, $cmd . "\r\n");
    };
    $expect = function (string $prefix) use ($read): bool {
        return str_starts_with($read(), $prefix);
    };

    if (!$expect('220')) { fclose($fp); return false; }

    $write('EHLO ' . $host);
    if (!$expect('250')) { fclose($fp); return false; }

    if ($c['secure'] === 'tls') {
        $write('STARTTLS');
        if (!$expect('220')) { fclose($fp); return false; }
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp);
            return false;
        }
        $write('EHLO ' . $host);
        if (!$expect('250')) { fclose($fp); return false; }
    }

    if ($c['username'] !== '') {
        $write('AUTH LOGIN');
        if (!$expect('334')) { fclose($fp); return false; }
        $write(base64_encode($c['username']));
        if (!$expect('334')) { fclose($fp); return false; }
        $write(base64_encode($c['password']));
        if (!$expect('235')) { fclose($fp); return false; }
    }

    $write('MAIL FROM:<' . $c['from_email'] . '>');
    if (!$expect('250')) { fclose($fp); return false; }
    $write('RCPT TO:<' . $c['to_email'] . '>');
    if (!$expect('250')) { fclose($fp); return false; }
    $write('DATA');
    if (!$expect('354')) { fclose($fp); return false; }

    $headers = [
        'From: ' . $c['from_name'] . ' <' . $c['from_email'] . '>',
        'To: <' . $c['to_email'] . '>',
        'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ];
    if ($replyTo) {
        $headers[] = 'Reply-To: <' . $replyTo . '>';
    }
    $message = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body));
    $message = str_replace("\n.", "\n..", $message); // ドット行のエスケープ

    $write($message . "\r\n.");
    if (!$expect('250')) { fclose($fp); return false; }

    $write('QUIT');
    fclose($fp);
    return true;
}
