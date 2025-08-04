<?php
// post_upload.php - 新規投稿の保存と画像アップロード（サムネイル生成機能付き）

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

// ★★★ 新機能：サムネイル生成関数（グラデーション付き） ★★★
function generateThumbnail($source_path, $thumbnail_path, $thumb_width = 600, $thumb_height = 450, $quality = 85) {
    $image_info = getimagesize($source_path);
    if (!$image_info) {
        return false;
    }
    
    $original_width = $image_info[0];
    $original_height = $image_info[1];
    $mime_type = $image_info['mime'];
    
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
        error_log("Failed to create source image for thumbnail: " . $mime_type);
        return false;
    }
    
    // サムネイル用の新しい画像を作成
    $thumbnail_image = imagecreatetruecolor($thumb_width, $thumb_height);
    
    // 透明度保持
    if ($mime_type === 'image/png' || $mime_type === 'image/gif' || $mime_type === 'image/webp') {
        imagealphablending($thumbnail_image, false);
        imagesavealpha($thumbnail_image, true);
        $transparent = imagecolorallocatealpha($thumbnail_image, 255, 255, 255, 127);
        imagefilledrectangle($thumbnail_image, 0, 0, $thumb_width, $thumb_height, $transparent);
    }
    
    // ★★★ 上部クロップ処理 ★★★
    $thumb_ratio = $thumb_width / $thumb_height;
    $original_ratio = $original_width / $original_height;
    
    if ($original_ratio > $thumb_ratio) {
        // 元画像が横長の場合：高さを基準にして横幅を調整
        $crop_height = $original_height;
        $crop_width = $original_height * $thumb_ratio;
        $crop_x = ($original_width - $crop_width) / 2; // 中央クロップ
        $crop_y = 0;
    } else {
        // 元画像が縦長の場合：横幅を基準にして上部をクロップ
        $crop_width = $original_width;
        $crop_height = $original_width / $thumb_ratio;
        $crop_x = 0;
        $crop_y = 0; // ★重要：上部から切り取り
    }
    
    // クロップしてリサイズ
    $resize_success = imagecopyresampled(
        $thumbnail_image, $source_image,
        0, 0, // サムネイル画像の開始位置
        $crop_x, $crop_y, // 元画像のクロップ開始位置
        $thumb_width, $thumb_height, // サムネイルのサイズ
        $crop_width, $crop_height // 元画像のクロップサイズ
    );
    
    if (!$resize_success) {
        error_log("imagecopyresampled failed for thumbnail");
        imagedestroy($source_image);
        imagedestroy($thumbnail_image);
        return false;
    }
    
    // ★★★ 新機能：下部透過グラデーション効果を追加 ★★★
    $gradient_height = intval($thumb_height * 0.25); // 画像の下25%にグラデーション
    $gradient_start_y = $thumb_height - $gradient_height;
    
    // アルファブレンディングを有効にしてグラデーション描画
    imagealphablending($thumbnail_image, true);
    
    for ($y = $gradient_start_y; $y < $thumb_height; $y++) {
        // グラデーションの透明度を計算（0-100の範囲）
        $progress = ($y - $gradient_start_y) / $gradient_height;
        $alpha = intval($progress * 100); // 0（完全不透明）から100（ほぼ透明）
        
        // 白色の半透明色を作成（実際には元の色を薄くする効果）
        $gradient_color = imagecolorallocatealpha($thumbnail_image, 255, 255, 255, $alpha);
        
        // 水平線を描画してグラデーション効果を作成
        imageline($thumbnail_image, 0, $y, $thumb_width - 1, $y, $gradient_color);
    }
    
    // アルファブレンディングを無効にして透明度を保持
    if ($mime_type === 'image/png' || $mime_type === 'image/gif' || $mime_type === 'image/webp') {
        imagealphablending($thumbnail_image, false);
        imagesavealpha($thumbnail_image, true);
    }
    
    // サムネイルを保存
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
    
    // メモリ解放
    imagedestroy($source_image);
    imagedestroy($thumbnail_image);
    
    if ($result) {
        error_log("Thumbnail with gradient successfully generated: " . $thumbnail_path);
    } else {
        error_log("Failed to save thumbnail with gradient: " . $thumbnail_path);
    }
    
    return $result;
}

// ★★★ 縦長画像検出関数 ★★★
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

// ★★★ 既存のリサイズ関数 ★★★
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
        error_log("Image resize: {$original_width}x{$original_height} -> {$new_width}x{$new_height} (ratio: {$resize_ratio})");
    }
    
    if (!$needs_resize) {
        error_log("No resize needed: {$original_width}x{$original_height}");
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
$thumbnails_dir = $upload_base_dir . 'thumbnails/'; // ★新規：サムネイル用ディレクトリ

// アップロードディレクトリが存在しない場合は作成
if (!is_dir($client_logo_dir)) mkdir($client_logo_dir, 0755, true);
if (!is_dir($works_images_dir)) mkdir($works_images_dir, 0755, true);
if (!is_dir($thumbnails_dir)) mkdir($thumbnails_dir, 0755, true); // ★新規

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

// ★★★ ギャラリー画像のアップロード処理（サムネイル生成対応） ★★★
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
                
                // 元画像をリサイズして保存
                if (resizeImageWithDualConstraints($file_info['tmp_name'], $destination, 1000, 3000, 85)) {
                    $caption = $_POST['gallery_captions'][$key] ?? '';
                    
                    // ★★★ 縦長画像の場合はサムネイルを生成 ★★★
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
                        "thumbnail" => $thumbnail_path, // ★新規：サムネイルパス
                        "is_vertical" => $is_vertical,  // ★新規：縦長フラグ
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

// 新しい投稿データを構築
$new_post = [
    "id" => $new_post_id,
    "title" => $title,
    "client_name" => $client_name,
    "description" => $description,
    "client_logo" => $client_logo_path,
    "gallery_images" => $gallery_images,
    "created_at" => $created_at
];

// 既存の投稿リストの先頭に追加
array_unshift($posts, $new_post);

// JSONデータをファイルに書き込む
if (file_put_contents($posts_file, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    echo json_encode(["status" => "success", "message" => "投稿が正常に保存されました。", "postId" => $new_post_id]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to save post data to JSON file. Check file permissions."]);
}

exit();
?>