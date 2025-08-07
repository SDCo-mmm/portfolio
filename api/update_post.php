<?php
// update_post.php - 既存投稿の更新と画像アップロード/削除（タグ機能対応版）

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

// ★★★ サムネイル生成関数（post_upload.phpと同じ） ★★★
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

// リサイズ関数
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
$upload_base_dir = __DIR__ . '/../upload/';
$client_logo_dir = $upload_base_dir . 'client/';
$works_images_dir = $upload_base_dir . 'works/';
$thumbnails_dir = $upload_base_dir . 'thumbnails/';

// サムネイルディレクトリが存在しない場合は作成
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

// フォームデータの取得
$post_id = $_POST['id'] ?? '';
$title = $_POST['title'] ?? '';
$client_name = $_POST['client_name'] ?? '';
$description = $_POST['description'] ?? '';
$client_logo_removed = ($_POST['client_logo_removed'] ?? 'false') === 'true';
$client_logo_unchanged = ($_POST['client_logo_unchanged'] ?? 'false') === 'true';

// ★★★ タグデータの処理 ★★★
$tags = [];
if (!empty($_POST['tags'])) {
    $tags_json = $_POST['tags'];
    $decoded_tags = json_decode($tags_json, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_tags)) {
        $tags = array_values(array_unique(array_filter(array_map('trim', $decoded_tags))));
    }
}

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

// クライアントロゴの処理
if ($client_logo_removed) {
    if ($existing_client_logo_path) {
        $old_logo_filepath = $upload_base_dir . str_replace('/portfolio/upload/', '', $existing_client_logo_path);
        if (file_exists($old_logo_filepath)) {
            unlink($old_logo_filepath);
        }
    }
    $client_logo_path = null;
} elseif (isset($_FILES['client_logo']) && $_FILES['client_logo']['error'] === UPLOAD_ERR_OK) {
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
        
        if (resizeImageWithDualConstraints($file_info['tmp_name'], $destination, 1000, 3000, 85)) {
            $client_logo_path = '/portfolio/upload/client/' . $logo_filename;
        } else {
            error_log("Failed to process client logo: " . $file_info['name']);
        }
    } else {
        error_log("Invalid client logo file type: " . $file_info['name']);
    }
} elseif ($client_logo_unchanged) {
    $client_logo_path = $existing_client_logo_path;
}

// ギャラリー画像の処理（既存のロジックを維持）
$new_gallery_images_data = [];
$existing_gallery_images = $posts[$current_post_index]['gallery_images'] ?? [];

$existing_paths = $_POST['existing_gallery_paths'] ?? [];
$existing_captions = $_POST['existing_gallery_captions'] ?? [];

// 既存画像で削除されなかったものを保持
foreach ($existing_gallery_images as $existing_image) {
    $image_path = $existing_image['path'];
    $path_index = array_search($image_path, $existing_paths);
    
    if ($path_index !== false) {
        $caption = $existing_captions[$path_index] ?? $existing_image['caption'];
        $new_gallery_images_data[] = [
            "path" => $image_path,
            "thumbnail" => $existing_image['thumbnail'] ?? null,
            "is_vertical" => $existing_image['is_vertical'] ?? false,
            "caption" => $caption
        ];
    } else {
        $old_image_filepath = $upload_base_dir . str_replace('/portfolio/upload/', '', $image_path);
        if (file_exists($old_image_filepath)) {
            unlink($old_image_filepath);
        }
        
        if (!empty($existing_image['thumbnail'])) {
            $old_thumbnail_filepath = $upload_base_dir . str_replace('/portfolio/upload/', '', $existing_image['thumbnail']);
            if (file_exists($old_thumbnail_filepath)) {
                unlink($old_thumbnail_filepath);
            }
        }
    }
}

// 既存画像の変更処理
if (isset($_FILES['existing_gallery_images'])) {
    foreach ($_FILES['existing_gallery_images']['error'] as $key => $error) {
        if ($error === UPLOAD_ERR_OK) {
            $old_path = $existing_paths[$key] ?? '';
            
            if ($old_path) {
                $old_filepath = $upload_base_dir . str_replace('/portfolio/upload/', '', $old_path);
                if (file_exists($old_filepath)) {
                    unlink($old_filepath);
                }
                
                foreach ($existing_gallery_images as $existing_img) {
                    if ($existing_img['path'] === $old_path && !empty($existing_img['thumbnail'])) {
                        $old_thumb_filepath = $upload_base_dir . str_replace('/portfolio/upload/', '', $existing_img['thumbnail']);
                        if (file_exists($old_thumb_filepath)) {
                            unlink($old_thumb_filepath);
                        }
                        break;
                    }
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
                
                if (resizeImageWithDualConstraints($file_info['tmp_name'], $destination, 1000, 3000, 85)) {
                    $caption = $existing_captions[$key] ?? '';
                    
                    $thumbnail_path = null;
                    $is_vertical = isVerticalImage($destination);
                    
                    if ($is_vertical) {
                        $thumbnail_filename = 'thumb_' . $image_filename;
                        $thumbnail_destination = $thumbnails_dir . $thumbnail_filename;
                        
                        if (generateThumbnail($destination, $thumbnail_destination)) {
                            $thumbnail_path = '/portfolio/upload/thumbnails/' . $thumbnail_filename;
                        }
                    }
                    
                    foreach ($new_gallery_images_data as &$gallery_item) {
                        if ($gallery_item['path'] === $old_path) {
                            $gallery_item['path'] = '/portfolio/upload/works/' . $image_filename;
                            $gallery_item['thumbnail'] = $thumbnail_path;
                            $gallery_item['is_vertical'] = $is_vertical;
                            $gallery_item['caption'] = $caption;
                            break;
                        }
                    }
                }
            }
        }
    }
}

// 新規ギャラリー画像の追加
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
                
                if (resizeImageWithDualConstraints($file_info['tmp_name'], $destination, 1000, 3000, 85)) {
                    $caption = $gallery_captions[$key] ?? '';
                    
                    $thumbnail_path = null;
                    $is_vertical = isVerticalImage($destination);
                    
                    if ($is_vertical) {
                        $thumbnail_filename = 'thumb_' . $image_filename;
                        $thumbnail_destination = $thumbnails_dir . $thumbnail_filename;
                        
                        if (generateThumbnail($destination, $thumbnail_destination)) {
                            $thumbnail_path = '/portfolio/upload/thumbnails/' . $thumbnail_filename;
                        }
                    }
                    
                    $new_gallery_images_data[] = [
                        "path" => '/portfolio/upload/works/' . $image_filename,
                        "thumbnail" => $thumbnail_path,
                        "is_vertical" => $is_vertical,
                        "caption" => $caption
                    ];
                }
            }
        }
    }
}

// ★★★ 投稿データを更新（タグ対応） ★★★
$posts[$current_post_index]['title'] = $title;
$posts[$current_post_index]['client_name'] = $client_name;
$posts[$current_post_index]['description'] = $description;
$posts[$current_post_index]['client_logo'] = $client_logo_path;
$posts[$current_post_index]['gallery_images'] = $new_gallery_images_data;
$posts[$current_post_index]['tags'] = $tags; // ★新規：タグデータを更新
$posts[$current_post_index]['updated_at'] = $updated_at;

// JSONデータをファイルに書き込む
if (file_put_contents($posts_file, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    echo json_encode([
        "status" => "success", 
        "message" => "投稿が正常に更新されました。", 
        "postId" => $post_id,
        "tags" => $tags // デバッグ用にタグ情報も返す
    ]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "投稿の更新に失敗しました。"]);
}
?>