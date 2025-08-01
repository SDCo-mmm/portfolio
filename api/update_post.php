<?php
// update_post.php - 既存投稿の更新と画像アップロード/削除

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

// 認証チェック
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

// posts.json を読み込み
$posts = [];
if (file_exists($posts_file)) {
    $json_data = file_get_contents($posts_file);
    $decoded_posts = json_decode($json_data, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_posts)) {
        $posts = $decoded_posts;
    }
}

// フォームデータの取得
$post_id = $_POST['id'] ?? '';
$title = $_POST['title'] ?? '';
$client_name = $_POST['client_name'] ?? '';
$description = $_POST['description'] ?? '';
$client_logo_removed = ($_POST['client_logo_removed'] ?? 'false') === 'true'; // クライアントロゴ削除フラグ
$updated_at = date('Y-m-d H:i:s'); // 更新日時

// 編集対象の投稿を検索
$current_post_index = -1;
foreach ($posts as $index => $post) {
    if ($post['id'] === $post_id) {
        $current_post_index = $index;
        break;
    }
}

if ($current_post_index === -1) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "指定された投稿が見つかりません。"]);
    exit();
}

// 既存のクライアントロゴパス
$existing_client_logo_path = $posts[$current_post_index]['client_logo'] ?? null;
$client_logo_path = $existing_client_logo_path;

// クライアントロゴの削除処理
if ($client_logo_removed && $existing_client_logo_path) {
    $old_logo_filepath = str_replace('/portfolio/', $upload_base_dir . '../', $existing_client_logo_path);
    if (file_exists($old_logo_filepath)) {
        unlink($old_logo_filepath);
    }
    $client_logo_path = null;
}

// 新しいクライアントロゴのアップロード処理
if (isset($_FILES['client_logo']) && $_FILES['client_logo']['error'] === UPLOAD_ERR_OK) {
    // 既存のロゴがあれば削除
    if ($existing_client_logo_path && !$client_logo_removed) { // 削除フラグが立っていない場合のみ削除
        $old_logo_filepath = str_replace('/portfolio/', $upload_base_dir . '../', $existing_client_logo_path);
        if (file_exists($old_logo_filepath)) {
            unlink($old_logo_filepath);
        }
    }

    $file_info = $_FILES['client_logo'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (in_array($file_info['type'], $allowed_types)) {
        $ext = pathinfo($file_info['name'], PATHINFO_EXTENSION);
        $logo_filename = md5(time() . $file_info['name']) . '.' . $ext;
        $destination = $client_logo_dir . $logo_filename;

        if (move_uploaded_file($file_info['tmp_name'], $destination)) {
            $client_logo_path = '/portfolio/upload/client/' . $logo_filename;
        } else {
            error_log("Failed to move client logo: " . $file_info['name']);
        }
    } else {
        error_log("Invalid client logo file type: " . $file_info['name']);
    }
}


// ギャラリー画像の処理
$new_gallery_images_data = [];
$existing_gallery_paths = $posts[$current_post_index]['gallery_images'] ?? [];

// 既存のギャラリー画像を再構築 (削除されたもの以外)
$kept_existing_image_paths = json_decode($_POST['existing_gallery_images_json'] ?? '[]', true);

// まず、保持する既存画像をリストに追加
foreach ($existing_gallery_paths as $existing_image) {
    // パスのみで比較するため、キャプションは含まない
    if (in_array($existing_image['path'], $kept_existing_image_paths)) {
        // 保持する画像であれば、更新されたキャプションを適用
        $found_index = array_search($existing_image['path'], $kept_existing_image_paths);
        $caption_from_form = $_POST['existing_gallery_captions'][$found_index] ?? '';
        $new_gallery_images_data[] = [
            "path" => $existing_image['path'],
            "caption" => $caption_from_form
        ];
    } else {
        // フォームから送られてこなかった（削除された）画像は物理削除
        $old_image_filepath = str_replace('/portfolio/', $upload_base_dir . '../', $existing_image['path']);
        if (file_exists($old_image_filepath)) {
            unlink($old_image_filepath);
        }
    }
}


// 新規アップロードされたギャラリー画像の処理
if (isset($_FILES['new_gallery_images'])) {
    foreach ($_FILES['new_gallery_images']['error'] as $key => $error) {
        if ($error === UPLOAD_ERR_OK) {
            $file_info = [
                'name' => $_FILES['new_gallery_images']['name'][$key],
                'type' => $_FILES['new_gallery_images']['type'][$key],
                'tmp_name' => $_FILES['new_gallery_images']['tmp_name'][$key],
                'error' => $_FILES['new_gallery_images']['error'][$key],
                'size' => $_FILES['new_gallery_images']['size'][$key]
            ];

            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (in_array($file_info['type'], $allowed_types)) {
                $ext = pathinfo($file_info['name'], PATHINFO_EXTENSION);
                $image_filename = md5(time() . $file_info['name'] . uniqid()) . '.' . $ext; // さらにユニーク性を高める
                $destination = $works_images_dir . $image_filename;

                if (move_uploaded_file($file_info['tmp_name'], $destination)) {
                    // 新規アップロードされた画像のキャプションは、フォームからのgallery_captions[]の対応するインデックスから取得
                    // このインデックスは、JavaScriptで動的に追加される新しい入力フィールドに対応
                    $caption = $_POST['new_gallery_captions'][$key] ?? ''; // 新規画像用のキャプション
                    $new_gallery_images_data[] = [
                        "path" => '/portfolio/upload/works/' . $image_filename,
                        "caption" => $caption
                    ];
                } else {
                    error_log("Failed to move new gallery image: " . $file_info['name']);
                }
            } else {
                error_log("Invalid new gallery image file type: " . $file_info['name']);
            }
        }
    }
}


// 投稿データを更新
$posts[$current_post_index]['title'] = $title;
$posts[$current_post_index]['client_name'] = $client_name;
$posts[$current_post_index]['description'] = $description;
$posts[$current_post_index]['client_logo'] = $client_logo_path;
$posts[$current_post_index]['gallery_images'] = $new_gallery_images_data;
$posts[$current_post_index]['updated_at'] = $updated_at; // 更新日時を追加

// JSONデータをファイルに書き込む
if (file_put_contents($posts_file, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    echo json_encode(["status" => "success", "message" => "投稿が正常に更新されました。", "postId" => $post_id]);
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode(["status" => "error", "message" => "投稿の更新に失敗しました。"]);
}
?>