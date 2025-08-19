<?php
// pdf-image-proxy.php - PDFに画像を埋め込むためのプロキシ

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

// 画像パスを取得
$imagePath = $_GET['path'] ?? '';

if (empty($imagePath)) {
    http_response_code(400);
    exit('Image path is required');
}

// セキュリティ：相対パスのみ許可
if (strpos($imagePath, '..') !== false || strpos($imagePath, 'http') === 0) {
    http_response_code(403);
    exit('Invalid image path');
}

// 画像ファイルのフルパス
$fullPath = $_SERVER['DOCUMENT_ROOT'] . $imagePath;

// ファイルが存在するか確認
if (!file_exists($fullPath) || !is_file($fullPath)) {
    http_response_code(404);
    exit('Image not found');
}

// 画像のMIMEタイプを取得
$imageInfo = getimagesize($fullPath);
if (!$imageInfo) {
    http_response_code(400);
    exit('Invalid image file');
}

$mimeType = $imageInfo['mime'];

// 画像を出力
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: public, max-age=3600');

readfile($fullPath);
exit();
?>