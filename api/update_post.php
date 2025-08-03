<?php
// update_post.php - 既存投稿の更新と画像アップロード/削除（修正版リサイズ機能付き）

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

// ★★★ 修正版：縦横両方制限対応リサイズ関数 ★★★
function resizeImageWithDualConstraints($source_path, $destination_path, $max_width = 1000, $max_height = 3000, $quality = 85) {
    // 画像情報を取得
    $image_info = getimagesize($source_path);
    if (!$image_info) {
        return false;
    }
    
    $original_width = $image_info[0];
    $original_height = $image_info[1];
    $mime_type = $image_info['mime'];
    
    // ★★★ 重要：両方の制限を同時にチェックして、より厳しい制限を適用 ★★★
    $needs_resize = false;
    $new_width = $original_width;
    $new_height = $original_height;
    
    // 横幅制限による縮小比率を計算
    $width_ratio = ($original_width > $max_width) ? ($max_width / $original_width) : 1.0;
    
    // 縦幅制限による縮小比率を計算
    $height_ratio = ($original_height > $max_height) ? ($max_height / $original_height) : 1.0;
    
    // ★★★ より厳しい制限（小さい比率）を採用 ★★★
    $resize_ratio = min($width_ratio, $height_ratio);
    
    // リサイズが必要かチェック
    if ($resize_ratio < 1.0) {
        $new_width = intval($original_width * $resize_ratio);
        $new_height = intval($original_height * $resize_ratio);
        $needs_resize = true;
        
        // デバッグ情報をログに出力
        error_log("Image resize: {$original_width}x{$original_height} -> {$new_width}x{$new_height} (ratio: {$resize_ratio})");
    }
    
    // リサイズが不要な場合はそのまま移動
    if (!$needs_resize) {
        error_log("No resize needed: {$original_width}x{$original_height}");
        return move_uploaded_file($source_path, $destination_path);
    }
    
    // 元画像をロード
    $source_image = null;
    switch ($mime_type) {
        case 'image/jpeg':
            $source_image = imagecreatefromjpeg($source_path);
            break;
        case 'image/png':
            $source_image = imagecreatefrompng($source_path);
            break;
        case 'image/gif':
            $source_image = imagecreatefromgif($source_path);
            break;
        case 'image/webp':
            if (function_exists('imagecreatefromwebp')) {
                $source_image = imagecreatefromwebp($source_path);
            }
            break;
    }
    
    if (!$source_image) {
        error_log("Failed to create source image from: " . $mime_type);
        return false;
    }
    
    $new_image = imagecreatetruecolor($new_width, $new_height);
    
    // 透明度保持
    if ($mime_type === 'image/png' || $mime_type === 'image/gif' || $mime_type === 'image/webp') {
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
        $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
        imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);
    }
    
    // リサイズ実行
    $resize_success = imagecopyresampled($new_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);
    
    if (!$resize_success) {
        error_log("imagecopyresampled failed");
        imagedestroy($source_image);
        imagedestroy($new_image);
        return false;
    }
    
    // 保存
    $result = false;
    switch ($mime_type) {
        case 'image/jpeg':
            $result = imagejpeg($new_image, $destination_path, $quality);
            break;
        case 'image/png':
            $result = imagepng($new_image, $destination_path, 9);
            break;
        case 'image/gif':
            $result = imagegif($new_image, $destination_path);
            break;
        case 'image/webp':
            if (function_exists('imagewebp')) {
                $result = imagewebp($new_image, $destination_path, $quality);
            }
            break;
    }
    
    // メモリ解放
    imagedestroy($source_image);
    imagedestroy($new_image);
    
    if ($result) {
        error_log("Image successfully resized and saved: " . $destination_path);
    } else {
        error_log("Failed to save resized image: " . $destination_path);
    }
    
    return $result;
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
$client_logo_removed = ($_POST['client_logo_removed'] ?? 'false') === 'true';
$client_logo_unchanged = ($_POST['client_logo_unchanged'] ?? 'false') === 'true';
$updated_at = date('Y-m-d H:i:s');

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

// クライアントロゴの処理（修正版リサイズ対応）
if ($client_logo_removed) {
    // ロゴ削除の場合
    if ($existing_client_logo_path) {
        $old_logo_filepath = $upload_base_dir . str_replace('/portfolio/upload/', '', $existing_client_logo_path);
        if (file_exists($old_logo_filepath)) {
            unlink($old_logo_filepath);
        }
    }
    $client_logo_path = null;
} elseif (isset($_FILES['client_logo']) && $_FILES['client_logo']['error'] === UPLOAD_ERR_OK) {
    // 新しいロゴアップロードの場合
    if ($existing_client_logo_path) {
        $old_logo_filepath = $upload_base_dir . str_replace('/portfolio/upload/', '', $existing_client_logo_path);
        if (file_exists($old_logo_filepath)) {
            unlink($old_logo_filepath);
        }
    }

    $file_info = $_FILES['client_logo'];
    $file_ext = strtolower(pathinfo($file_info['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['png', 'jpg', 'jpeg'];
    $allowed_mime = ['image/png', 'image/jpeg'];

    if (in_array($file_ext, $allowed_ext) && in_array($file_info['type'], $allowed_mime)) {
        $logo_filename = $post_id . '_client_' . time() . '.' . $file_ext;
        $destination = $client_logo_dir . $logo_filename;
        
        // ★★★ 修正版リサイズ機能を使用 ★★★
        if (resizeImageWithDualConstraints($file_info['tmp_name'], $destination, 1000, 3000, 85)) {
            $client_logo_path = '/portfolio/upload/client/' . $logo_filename;
        } else {
            error_log("Failed to process client logo: " . $file_info['name']);
        }
    } else {
        error_log("Invalid client logo file type: " . $file_info['name']);
    }
} elseif ($client_logo_unchanged) {
    // ロゴを変更しない場合は既存のパスを保持
    $client_logo_path = $existing_client_logo_path;
}

// ギャラリー画像の処理
$new_gallery_images_data = [];
$existing_gallery_images = $posts[$current_post_index]['gallery_images'] ?? [];

// 既存ギャラリー画像の処理
$existing_paths = $_POST['existing_gallery_paths'] ?? [];
$existing_captions = $_POST['existing_gallery_captions'] ?? [];

// 既存画像で削除されなかったものを保持
foreach ($existing_gallery_images as $existing_image) {
    $image_path = $existing_image['path'];
    $path_index = array_search($image_path, $existing_paths);
    
    if ($path_index !== false) {
        // この画像は保持される
        $caption = $existing_captions[$path_index] ?? $existing_image['caption'];
        $new_gallery_images_data[] = [
            "path" => $image_path,
            "caption" => $caption
        ];
    } else {
        // この画像は削除される
        $old_image_filepath = $upload_base_dir . str_replace('/portfolio/upload/', '', $image_path);
        if (file_exists($old_image_filepath)) {
            unlink($old_image_filepath);
        }
    }
}

// 既存画像の変更（ファイル再アップロード）の処理（修正版リサイズ対応）
if (isset($_FILES['existing_gallery_images'])) {
    foreach ($_FILES['existing_gallery_images']['error'] as $key => $error) {
        if ($error === UPLOAD_ERR_OK) {
            // 既存画像の対応するパスを取得
            $old_path = $existing_paths[$key] ?? '';
            
            // 古い画像ファイルを削除
            if ($old_path) {
                $old_filepath = $upload_base_dir . str_replace('/portfolio/upload/', '', $old_path);
                if (file_exists($old_filepath)) {
                    unlink($old_filepath);
                }
            }
            
            $file_info = [
                'name' => $_FILES['existing_gallery_images']['name'][$key],
                'type' => $_FILES['existing_gallery_images']['type'][$key],
                'tmp_name' => $_FILES['existing_gallery_images']['tmp_name'][$key],
                'error' => $_FILES['existing_gallery_images']['error'][$key],
                'size' => $_FILES['existing_gallery_images']['size'][$key]
            ];

            $file_ext = strtolower(pathinfo($file_info['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
            $allowed_mime = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

            if (in_array($file_ext, $allowed_ext) && in_array($file_info['type'], $allowed_mime)) {
                $image_filename = $post_id . '_work_' . time() . '_' . uniqid() . '.' . $file_ext;
                $destination = $works_images_dir . $image_filename;
                
                // ★★★ 修正版リサイズ機能を使用 ★★★
                if (resizeImageWithDualConstraints($file_info['tmp_name'], $destination, 1000, 3000, 85)) {
                    $caption = $existing_captions[$key] ?? '';
                    
                    // 配列内の対応する要素を更新
                    foreach ($new_gallery_images_data as &$gallery_item) {
                        if ($gallery_item['path'] === $old_path) {
                            $gallery_item['path'] = '/portfolio/upload/works/' . $image_filename;
                            $gallery_item['caption'] = $caption;
                            break;
                        }
                    }
                }
            }
        }
    }
}

// 新規ギャラリー画像の追加（修正版リサイズ対応）
if (isset($_FILES['gallery_images'])) {
    $gallery_captions = $_POST['gallery_captions'] ?? [];
    
    foreach ($_FILES['gallery_images']['error'] as $key => $error) {
        if ($error === UPLOAD_ERR_OK) {
            $file_info = [
                'name' => $_FILES['gallery_images']['name'][$key],
                'type' => $_FILES['gallery_images']['type'][$key],
                'tmp_name' => $_FILES['gallery_images']['tmp_name'][$key],
                'error' => $_FILES['gallery_images']['error'][$key],
                'size' => $_FILES['gallery_images']['size'][$key]
            ];

            $file_ext = strtolower(pathinfo($file_info['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
            $allowed_mime = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

            if (in_array($file_ext, $allowed_ext) && in_array($file_info['type'], $allowed_mime)) {
                $image_filename = $post_id . '_work_' . time() . '_' . uniqid() . '.' . $file_ext;
                $destination = $works_images_dir . $image_filename;
                
                // ★★★ 修正版リサイズ機能を使用 ★★★
                if (resizeImageWithDualConstraints($file_info['tmp_name'], $destination, 1000, 3000, 85)) {
                    $caption = $gallery_captions[$key] ?? '';
                    $new_gallery_images_data[] = [
                        "path" => '/portfolio/upload/works/' . $image_filename,
                        "caption" => $caption
                    ];
                }
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
$posts[$current_post_index]['updated_at'] = $updated_at;

// JSONデータをファイルに書き込む
if (file_put_contents($posts_file, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    echo json_encode(["status" => "success", "message" => "投稿が正常に更新されました。", "postId" => $post_id]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "投稿の更新に失敗しました。"]);
}
?>