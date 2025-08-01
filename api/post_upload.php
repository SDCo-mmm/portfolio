<?php
// post_upload.php - 新規投稿の保存と画像アップロード

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json; charset=UTF-8');

// POSTリクエスト以外は拒否
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);
    exit();
}

// 認証チェック (Cookie 'admin_auth_token'を確認)
// HttpOnly属性のCookieはPHPからのみアクセス可能です
if (!isset($_COOKIE['admin_auth_token']) || $_COOKIE['admin_auth_token'] !== 'authenticated_admin') {
    http_response_code(401); // Unauthorized
    echo json_encode(["status" => "error", "message" => "Authentication required."]);
    exit();
}

// データファイルのパス
$posts_file = __DIR__ . '/../data/posts.json';
$upload_base_dir = __DIR__ . '/../upload/';
$client_logo_dir = $upload_base_dir . 'client/';
$works_images_dir = $upload_base_dir . 'works/';

// アップロードディレクトリが存在しない場合は作成
if (!is_dir($client_logo_dir)) mkdir($client_logo_dir, 0755, true);
if (!is_dir($works_images_dir)) mkdir($works_images_dir, 0755, true);

// posts.json を読み込み（ファイルがない場合は空の配列を初期化）
$posts = [];
if (file_exists($posts_file)) {
    $json_data = file_get_contents($posts_file);
    $decoded_posts = json_decode($json_data, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_posts)) {
        $posts = $decoded_posts;
    }
}

// 新しい投稿データの準備
$new_post_id = uniqid('post_'); // ユニークなIDを生成
$title = $_POST['title'] ?? '';
$client_name = $_POST['client_name'] ?? '';
$description = $_POST['description'] ?? '';
$created_at = date('Y-m-d H:i:s');

// クライアントロゴのアップロード処理
$client_logo_path = null;
if (isset($_FILES['client_logo']) && $_FILES['client_logo']['error'] === UPLOAD_ERR_OK) {
    $file_info = $_FILES['client_logo'];
    $file_ext = strtolower(pathinfo($file_info['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['png', 'jpg', 'jpeg'];
    $allowed_mime = ['image/png', 'image/jpeg'];

    if (in_array($file_ext, $allowed_ext) && in_array($file_info['type'], $allowed_mime)) {
        $logo_filename = $new_post_id . '_client.' . $file_ext;
        $destination = $client_logo_dir . $logo_filename;
        if (move_uploaded_file($file_info['tmp_name'], $destination)) {
            $client_logo_path = '/portfolio/upload/client/' . $logo_filename;
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to move client logo."]);
            exit();
        }
    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid client logo file type."]);
        exit();
    }
}

// ギャラリー画像のアップロード処理
$gallery_images = [];
if (isset($_FILES['gallery_images'])) {
    foreach ($_FILES['gallery_images']['error'] as $key => $error) {
        if ($error === UPLOAD_ERR_OK) {
            $file_info = [
                'name' => $_FILES['gallery_images']['name'][$key],
                'type' => $_FILES['gallery_images']['type'][$key],
                'tmp_name' => $_FILES['gallery_images']['tmp_name'][$key],
                'error' => $_FILES['gallery_images']['error'][$key],
                'size' => $_FILES['gallery_images']['size'][$key],
            ];

            $file_ext = strtolower(pathinfo($file_info['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['png', 'jpg', 'jpeg', 'gif', 'webp']; // ギャラリーはより多くの形式を許容
            $allowed_mime = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

            if (in_array($file_ext, $allowed_ext) && in_array($file_info['type'], $allowed_mime)) {
                $image_filename = $new_post_id . '_work_' . uniqid() . '.' . $file_ext;
                $destination = $works_images_dir . $image_filename;
                
                if (move_uploaded_file($file_info['tmp_name'], $destination)) {
                    $caption = $_POST['gallery_captions'][$key] ?? '';
                    $gallery_images[] = [
                        "path" => '/portfolio/upload/works/' . $image_filename,
                        "caption" => $caption
                    ];
                } else {
                    // 画像の移動に失敗しても他の画像や投稿は保存を続行
                    error_log("Failed to move gallery image: " . $file_info['name']);
                }
            } else {
                // 不正なファイルタイプはスキップ
                error_log("Invalid gallery image file type: " . $file_info['name']);
            }
        }
    }
}

// 新しい投稿データを構築
$new_post = [
    "id" => $new_post_id,
    "title" => $title,
    "client_name" => $client_name,
    "description" => $description,
    "client_logo" => $client_logo_path, // nullの可能性あり
    "gallery_images" => $gallery_images, // 空配列の可能性あり
    "created_at" => $created_at
];

// 既存の投稿リストの先頭に追加 (新しい投稿が常に一番上に来るように)
array_unshift($posts, $new_post);

// JSONデータをファイルに書き込む
if (file_put_contents($posts_file, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    echo json_encode(["status" => "success", "message" => "投稿が正常に保存されました。", "postId" => $new_post_id]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to save post data to JSON file. Check file permissions."]);
}

exit();