<?php
// post_upload.php - 新規投稿の保存と画像アップロード（タグ自動登録対応版）

// エラー出力を抑制してJSONレスポンスを保護
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

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

// ★★★ タグ管理機能を追加 ★★★
function loadTags($tags_file) {
    if (!file_exists($tags_file)) {
        return [];
    }
    $json_data = file_get_contents($tags_file);
    $tags = json_decode($json_data, true);
    return (json_last_error() === JSON_ERROR_NONE && is_array($tags)) ? $tags : [];
}

function saveTags($tags_file, $tags) {
    return file_put_contents($tags_file, json_encode($tags, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function addNewTagsToMaster($new_tags, $tags_file) {
    if (empty($new_tags)) return;
    
    $existing_tags = loadTags($tags_file);
    $existing_tag_names = array_column($existing_tags, 'name');
    
    $added_count = 0;
    foreach ($new_tags as $tag_name) {
        $tag_name = trim($tag_name);
        if (!empty($tag_name) && !in_array($tag_name, $existing_tag_names)) {
            $new_tag = [
                "id" => uniqid('tag_'),
                "name" => $tag_name,
                "created_at" => date('Y-m-d H:i:s'),
                "usage" => 0
            ];
            $existing_tags[] = $new_tag;
            $existing_tag_names[] = $tag_name;
            $added_count++;
        }
    }
    
    if ($added_count > 0) {
        saveTags($tags_file, $existing_tags);
        error_log("Added {$added_count} new tags to master list from post creation");
    }
}

// ★★★ サムネイル生成関数（既存のコードを維持） ★★★
function generateThumbnail($source_path, $thumbnail_path, $thumb_width = 600, $thumb_height = 450, $quality = 85) {
    $image_info = getimagesize($source_path);
    if (!$image_info) {
        return false;
    }
    
    $original_width = $image_info[0];
    $original_height = $image_info[1];
    $mime_type = $image_info['mime'];
    
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
        error_log("Failed to create source image for thumbnail: " . $mime_type);
        return false;
    }
    
    $thumbnail_image = imagecreatetruecolor($thumb_width, $thumb_height);
    
    if ($mime_type === 'image/png' || $mime_type === 'image/gif' || $mime_type === 'image/webp') {
        imagealphablending($thumbnail_image, false);
        imagesavealpha($thumbnail_image, true);
        $transparent = imagecolorallocatealpha($thumbnail_image, 255, 255, 255, 127);
        imagefilledrectangle($thumbnail_image, 0, 0, $thumb_width, $thumb_height, $transparent);
    }
    
    $thumb_ratio = $thumb_width / $thumb_height;
    $original_ratio = $original_width / $original_height;
    
    if ($original_ratio > $thumb_ratio) {
        $crop_height = $original_height;
        $crop_width = $original_height * $thumb_ratio;
        $crop_x = ($original_width - $crop_width) / 2;
        $crop_y = 0;
    } else {
        $crop_width = $original_width;
        $crop_height = $original_width / $thumb_ratio;
        $crop_x = 0;
        $crop_y = 0;
    }
    
    $resize_success = imagecopyresampled(
        $thumbnail_image, $source_image,
        0, 0,
        $crop_x, $crop_y,
        $thumb_width, $thumb_height,
        $crop_width, $crop_height
    );
    
    if (!$resize_success) {
        error_log("imagecopyresampled failed for thumbnail");
        imagedestroy($source_image);
        imagedestroy($thumbnail_image);
        return false;
    }
    
    // グラデーション効果
    $gradient_height = intval($thumb_height * 0.5);
    $gradient_start_y = $thumb_height - $gradient_height;
    
    imagealphablending($thumbnail_image, true);
    
    for ($y = $gradient_start_y; $y < $thumb_height; $y++) {
        $progress = ($y - $gradient_start_y) / $gradient_height;
        $alpha = intval($progress * 127);
        $gradient_color = imagecolorallocatealpha($thumbnail_image, 255, 255, 255, 127 - $alpha);
        imageline($thumbnail_image, 0, $y, $thumb_width - 1, $y, $gradient_color);
    }
    
    if ($mime_type === 'image/png' || $mime_type === 'image/gif' || $mime_type === 'image/webp') {
        imagealphablending($thumbnail_image, false);
        imagesavealpha($thumbnail_image, true);
    }
    
    $result = false;
    switch ($mime_type) {
        case 'image/jpeg':
            $result = imagejpeg($thumbnail_image, $thumbnail_path, $quality);
            break;
        case 'image/png':
            $result = imagepng($thumbnail_image, $thumbnail_path, 9);
            break;
        case 'image/gif':
            $result = imagegif($thumbnail_image, $thumbnail_path);
            break;
        case 'image/webp':
            if (function_exists('imagewebp')) {
                $result = imagewebp($thumbnail_image, $thumbnail_path, $quality);
            }
            break;
    }
    
    imagedestroy($source_image);
    imagedestroy($thumbnail_image);
    
    if ($result) {
        error_log("Thumbnail successfully generated: " . $thumbnail_path);
    } else {
        error_log("Failed to save thumbnail: " . $thumbnail_path);
    }
    
    return $result;
}

// 縦長画像検出関数
function isVerticalImage($image_path, $vertical_threshold = 1.5) {
    $image_info = getimagesize($image_path);
    if (!$image_info) {
        return false;
    }
    
    $width = $image_info[0];
    $height = $image_info[1];
    $aspect_ratio = $height / $width;
    
    return $aspect_ratio >= $vertical_threshold;
}

// リサイズ関数（既存のコードを維持）
function resizeImageWithDualConstraints($source_path, $destination_path, $max_width = 1000, $max_height = 3000, $quality = 85) {
    $image_info = getimagesize($source_path);
    if (!$image_info) {
        return false;
    }
    
    $original_width = $image_info[0];
    $original_height = $image_info[1];
    $mime_type = $image_info['mime'];
    
    $needs_resize = false;
    $new_width = $original_width;
    $new_height = $original_height;
    
    $width_ratio = ($original_width > $max_width) ? ($max_width / $original_width) : 1.0;
    $height_ratio = ($original_height > $max_height) ? ($max_height / $original_height) : 1.0;
    $resize_ratio = min($width_ratio, $height_ratio);
    
    if ($resize_ratio < 1.0) {
        $new_width = intval($original_width * $resize_ratio);
        $new_height = intval($original_height * $resize_ratio);
        $needs_resize = true;
        error_log("Image resize: {$original_width}x{$original_height} -> {$new_width}x{$new_height}");
    }
    
    if (!$needs_resize) {
        return move_uploaded_file($source_path, $destination_path);
    }
    
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
    
    if ($mime_type === 'image/png' || $mime_type === 'image/gif' || $mime_type === 'image/webp') {
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
        $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
        imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);
    }
    
    $resize_success = imagecopyresampled($new_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);
    
    if (!$resize_success) {
        error_log("imagecopyresampled failed");
        imagedestroy($source_image);
        imagedestroy($new_image);
        return false;
    }
    
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
    
    imagedestroy($source_image);
    imagedestroy($new_image);
    
    return $result;
}

// データファイルのパス
$posts_file = __DIR__ . '/../data/posts.json';
$tags_file = __DIR__ . '/../data/tags.json'; // ★追加：タグファイルのパス
$upload_base_dir = __DIR__ . '/../upload/';
$client_logo_dir = $upload_base_dir . 'client/';
$works_images_dir = $upload_base_dir . 'works/';
$thumbnails_dir = $upload_base_dir . 'thumbnails/';

// アップロードディレクトリが存在しない場合は作成
if (!is_dir($client_logo_dir)) mkdir($client_logo_dir, 0755, true);
if (!is_dir($works_images_dir)) mkdir($works_images_dir, 0755, true);
if (!is_dir($thumbnails_dir)) mkdir($thumbnails_dir, 0755, true);

// posts.json を読み込み
$posts = [];
if (file_exists($posts_file)) {
    $json_data = file_get_contents($posts_file);
    $decoded_posts = json_decode($json_data, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_posts)) {
        $posts = $decoded_posts;
    }
}

// 新しい投稿データの準備
$new_post_id = uniqid('post_');
$title = $_POST['title'] ?? '';
$client_name = $_POST['client_name'] ?? '';
$description = $_POST['description'] ?? '';

// ★★★ タグデータの処理 ★★★
$tags = [];
if (!empty($_POST['tags'])) {
    $tags_json = $_POST['tags'];
    $decoded_tags = json_decode($tags_json, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_tags)) {
        // タグの正規化（トリム、重複除去、空要素除去）
        $tags = array_values(array_unique(array_filter(array_map('trim', $decoded_tags))));
    }
}

$created_at = date('Y-m-d H:i:s');

// クライアントロゴのアップロード処理（既存のコードを維持）
$client_logo_path = null;
if (isset($_FILES['client_logo']) && $_FILES['client_logo']['error'] === UPLOAD_ERR_OK) {
    $file_info = $_FILES['client_logo'];
    $file_ext = strtolower(pathinfo($file_info['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['png', 'jpg', 'jpeg'];
    $allowed_mime = ['image/png', 'image/jpeg'];

    if (in_array($file_ext, $allowed_ext) && in_array($file_info['type'], $allowed_mime)) {
        $logo_filename = $new_post_id . '_client.' . $file_ext;
        $destination = $client_logo_dir . $logo_filename;
        
        if (resizeImageWithDualConstraints($file_info['tmp_name'], $destination, 1000, 3000, 85)) {
            $client_logo_path = '/portfolio/upload/client/' . $logo_filename;
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to process client logo."]);
            exit();
        }
    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid client logo file type."]);
        exit();
    }
}

// ギャラリー画像のアップロード処理（既存のコードを維持）
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
            $allowed_ext = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
            $allowed_mime = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

            if (in_array($file_ext, $allowed_ext) && in_array($file_info['type'], $allowed_mime)) {
                $image_filename = $new_post_id . '_work_' . uniqid() . '.' . $file_ext;
                $destination = $works_images_dir . $image_filename;
                
                if (resizeImageWithDualConstraints($file_info['tmp_name'], $destination, 1000, 3000, 85)) {
                    $caption = $_POST['gallery_captions'][$key] ?? '';
                    
                    $thumbnail_path = null;
                    $is_vertical = isVerticalImage($destination);
                    
                    if ($is_vertical) {
                        $thumbnail_filename = 'thumb_' . $image_filename;
                        $thumbnail_destination = $thumbnails_dir . $thumbnail_filename;
                        
                        if (generateThumbnail($destination, $thumbnail_destination)) {
                            $thumbnail_path = '/portfolio/upload/thumbnails/' . $thumbnail_filename;
                            error_log("Generated thumbnail for vertical image: " . $thumbnail_filename);
                        }
                    }
                    
                    $gallery_images[] = [
                        "path" => '/portfolio/upload/works/' . $image_filename,
                        "thumbnail" => $thumbnail_path,
                        "is_vertical" => $is_vertical,
                        "caption" => $caption
                    ];
                } else {
                    error_log("Failed to process gallery image: " . $file_info['name']);
                }
            } else {
                error_log("Invalid gallery image file type: " . $file_info['name']);
            }
        }
    }
}

// ★★★ 新しいタグをマスターリストに自動追加 ★★★
if (!empty($tags)) {
    addNewTagsToMaster($tags, $tags_file);
}

// ★★★ 新しい投稿データを構築（タグ対応） ★★★
$new_post = [
    "id" => $new_post_id,
    "title" => $title,
    "client_name" => $client_name,
    "description" => $description,
    "client_logo" => $client_logo_path,
    "gallery_images" => $gallery_images,
    "tags" => $tags, // ★新規：タグデータを追加
    "created_at" => $created_at
];

// 既存の投稿リストの先頭に追加
array_unshift($posts, $new_post);

// JSONデータをファイルに書き込む
if (file_put_contents($posts_file, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    // 出力バッファをクリアしてクリーンなJSONを送信
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode([
        "status" => "success", 
        "message" => "投稿が正常に保存されました。", 
        "postId" => $new_post_id,
        "tags" => $tags // デバッグ用にタグ情報も返す
    ]);
} else {
    http_response_code(500);
    // 出力バッファをクリアしてクリーンなJSONを送信
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode(["status" => "error", "message" => "Failed to save post data to JSON file. Check file permissions."]);
}

// 余分な出力を防ぐために明示的にexit
exit();
?>