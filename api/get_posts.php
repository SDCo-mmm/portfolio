<?php
// get_posts.php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json; charset=UTF-8');

// POSTリクエスト以外は拒否
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(["error" => "Method Not Allowed"]);
    exit();
}

$posts_file = __DIR__ . '/../data/posts.json'; // posts.jsonへのパス

// ファイルの存在確認
if (!file_exists($posts_file)) {
    http_response_code(404);
    echo json_encode(["error" => "Data file not found."]);
    exit();
}

// ファイルの読み込み
$json_data = file_get_contents($posts_file);

// JSONデータのデコード
$posts = json_decode($json_data, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to decode JSON data."]);
    exit();
}

// ソート順の取得 (GETパラメータから)
$sort_by = $_GET['sort_by'] ?? 'newest'; // デフォルトは新しい順

// データのソート
if ($sort_by === 'newest') {
    // created_atで降順ソート
    usort($posts, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
} elseif ($sort_by === 'client') {
    // client_nameで昇順ソート
    usort($posts, function($a, $b) {
        return strcmp($a['client_name'], $b['client_name']);
    });
}
// 他のソート順が必要であればここに追加

echo json_encode($posts);
exit();