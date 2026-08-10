<?php
// このファイルを smtp_keys.local.php にコピーして実際の値を入力してください。
// smtp_keys.local.php は .gitignore 対象なので、git にはコミットされません。
return [
    'host' => '',           // 例: smtp.gmail.com
    'port' => 587,          // 587(STARTTLS) または 465(SSL)
    'secure' => 'tls',      // 'tls' または 'ssl'
    'username' => '',
    'password' => '',
    'from_email' => '',
    'from_name' => 'RSVP',
    'to_email' => '',       // お問い合わせの転送先（管理者アドレス）
];
