<?php
// delete_post.php - 投稿の削除（サムネイル削除対応）

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json; charset=UTF-8');

// POSTリクエスト以外は拒否
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);
    exit();
}

// 認証チェック
if (!isset($_COOKIE['admin_auth_token']) || $_COOKIE['admin_auth_token'] !== 'authenticated_admin') {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Authentication required."]);
    exit();
}

// データファイルのパス
$posts_file = __DIR__ . '/../data/posts.json';
$upload_base_dir = __DIR__ . '/../upload/';

// posts.json を読み込み
$posts = [];
if (file_exists($posts_file)) {
    $json_data = file_get_contents($posts_file);
    $decoded_posts = json_decode($json_data, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_posts)) {
        $posts = $decoded_posts;
    }
}

// POSTデータから投稿IDを取得
$post_id_to_delete = $_POST['id'] ?? '';

if (empty($post_id_to_delete)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "削除する投稿IDが指定されていません。"]);
    exit();
}

$found = false;
$updated_posts = [];
$deleted_files = [];

foreach ($posts as $post) {
    if ($post['id'] === $post_id_to_delete) {
        $found = true;
        // 関連するファイルを削除対象リストに追加
        if (!empty($post['client_logo'])) {
            $deleted_files[] = $upload_base_dir . str_replace('/portfolio/upload/', '', $post['client_logo']);
        }
        if (!empty($post['gallery_images'])) {
            foreach ($post['gallery_images'] as $image) {
                // ★オリジナル画像を削除リストに追加
                $deleted_files[] = $upload_base_dir . str_replace('/portfolio/upload/', '', $image['path']);
                
                // ★★★ サムネイルも削除リストに追加 ★★★
                if (!empty($image['thumbnail'])) {
                    $deleted_files[] = $upload_base_dir . str_replace('/portfolio/upload/', '', $image['thumbnail']);
                }
            }
        }
        // この投稿はスキップ（削除）
    } else {
        $updated_posts[] = $post;
    }
}

if (!$found) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "指定された投稿が見つかりません。"]);
    exit();
}

// ファイルシステムから画像とサムネイルを削除
foreach ($deleted_files as $file_path) {
    if (file_exists($file_path) && is_file($file_path)) {
        if (!unlink($file_path)) {
            error_log("Failed to delete file: " . $file_path);
        } else {
            error_log("Successfully deleted file: " . $file_path);
        }
    }
}

// JSONデータをファイルに書き込む
if (file_put_contents($posts_file, json_encode($updated_posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    echo json_encode(["status" => "success", "message" => "投稿と関連ファイル（サムネイル含む）が正常に削除されました。", "deletedId" => $post_id_to_delete]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "投稿データの保存に失敗しました。"]);
}
?>