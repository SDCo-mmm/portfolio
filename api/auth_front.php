<?php
// auth_front.php

// CORSヘッダー (開発環境向け。本番では特定のオリジンに制限することを推奨)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: text/plain; charset=UTF-8');

// POSTリクエスト以外は拒否
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo "NG";
    exit();
}

// 仮のパスワード (★★★ 本番環境では必ず変更し、password_hash()でハッシュ化すること！ ★★★)
$correct_password = "front_password_2025"; // ここを実際のパスワードに変更

// 入力されたパスワードの取得
$input_password = $_POST['password'] ?? '';

// パスワードの検証
if ($input_password === $correct_password) {
    // 認証成功

    // セキュアなCookieを設定
    setcookie(
        'front_auth_token',
        'authenticated', // 本来はランダムなトークンを生成すべき
        [
            'expires' => time() + (86400 * 1), // 1日 (24時間 * 60分 * 60秒)
            'path' => '/portfolio/',           // ★ここを修正: /portfolio/
            'secure' => true,                  // HTTPS接続時のみ送信 (本番環境では必須)
            'httponly' => true,                // JavaScriptからのアクセスを禁止
            'samesite' => 'Lax'                // CSRF対策 (Laxは安全と利便性のバランスが良い)
        ]
    );

    // バッファをクリアし、余分な出力がないことを保証
    if (ob_get_length()) {
        ob_clean();
    }
    echo "OK";
    exit(); // ここで処理を終了し、余分な出力がないようにする
} else {
    // 認証失敗
    http_response_code(401); // Unauthorized

    // バッファをクリアし、余分な出力がないことを保証
    if (ob_get_length()) {
        ob_clean();
    }
    echo "NG";
    exit(); // ここで処理を終了し、余分な出力がないようにする
}