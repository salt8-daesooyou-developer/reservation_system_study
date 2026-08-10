<?php
// このファイルを stripe_keys.local.php にコピーして実際の鍵を入力してください。
// stripe_keys.local.php は .gitignore 対象なので、git にはコミットされません。
return [
    'secret_key' => '',      // sk_test_... または sk_live_...
    'publishable_key' => '', // pk_test_... または pk_live_...
    'webhook_secret' => '',  // whsec_...（Stripe CLI か ダッシュボードの Webhook 設定から取得）
];
