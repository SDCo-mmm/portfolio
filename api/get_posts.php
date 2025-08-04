<?php
// get_posts.php - 無限スクロール対応版

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json; charset=UTF-8');

// GETリクエスト以外は拒否
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

// ★★★ 無限スクロール用のパラメータ取得 ★★★
$page = intval($_GET['page'] ?? 0); // ページ番号（0の場合は全件取得）
$limit = intval($_GET['limit'] ?? 0); // 1ページあたりの件数（0の場合は全件取得）
$sort_by = $_GET['sort_by'] ?? 'newest'; // ソート順

// ページ番号とlimitの検証
if ($page < 0) $page = 0;
if ($limit < 0 || $limit > 50) $limit = ($limit === 0) ? 0 : 12; // 0は全件取得、それ以外は最大50件まで制限

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

// ★★★ 後方互換性の保持：pageやlimitが指定されていない場合は従来の形式で返す ★★★
if ($page === 0 && $limit === 0) {
    // 従来の形式：投稿配列をそのまま返す（詳細ページや管理画面用）
    echo json_encode($posts);
    exit();
}

// ★★★ 無限スクロール用のページネーション処理 ★★★
if ($page < 1) $page = 1; // ページネーション使用時は1から開始
if ($limit < 1) $limit = 12; // デフォルト12件

$total_posts = count($posts);
$total_pages = ceil($total_posts / $limit);
$offset = ($page - 1) * $limit;

// 指定ページのデータを取得
$page_posts = array_slice($posts, $offset, $limit);

// ★★★ レスポンスデータの構築（無限スクロール用） ★★★
$response = [
    "posts" => $page_posts,
    "pagination" => [
        "current_page" => $page,
        "total_pages" => $total_pages,
        "total_posts" => $total_posts,
        "per_page" => $limit,
        "has_next" => $page < $total_pages,
        "has_prev" => $page > 1
    ]
];

echo json_encode($response);
exit();