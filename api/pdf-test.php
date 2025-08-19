<?php
// pdf-test.php - PDF生成機能のテスト
// /portfolio/api/pdf-test.php

// 出力バッファリングを開始
ob_start();

// エラー表示を有効化
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "PDF生成テスト開始<br>\n";

// 1. TCPDFの存在確認
echo "1. TCPDFチェック: ";
if (file_exists(__DIR__ . '/tcpdf/tcpdf.php')) {
    echo "OK<br>\n";
    require_once(__DIR__ . '/tcpdf/tcpdf.php');
} else {
    echo "NG - TCPDFが見つかりません<br>\n";
    exit;
}

// 2. posts.jsonの存在確認
echo "2. posts.jsonチェック: ";
$posts_file = __DIR__ . '/../data/posts.json';
if (file_exists($posts_file)) {
    echo "OK<br>\n";
    $posts = json_decode(file_get_contents($posts_file), true);
    echo "   投稿数: " . count($posts) . "<br>\n";
} else {
    echo "NG - posts.jsonが見つかりません<br>\n";
    exit;
}

// 3. 簡単なPDF生成テスト
echo "3. PDF生成テスト: ";
try {
    $pdf = new TCPDF();
    $pdf->SetCreator('Test');
    $pdf->SetAuthor('Test');
    $pdf->SetTitle('Test PDF');
    
    // 日本語フォントの確認
    $pdf->SetFont('kozgopromedium', '', 12);
    
    $pdf->AddPage();
    $pdf->Cell(0, 10, 'PDF Generation Test', 0, 1);
    $pdf->MultiCell(0, 10, '日本語テスト：こんにちは、世界！', 0, 'L');
    
    // メモリ出力でテスト
    $pdfContent = $pdf->Output('test.pdf', 'S');
    
    if (strlen($pdfContent) > 0) {
        echo "OK - PDFサイズ: " . strlen($pdfContent) . " bytes<br>\n";
    } else {
        echo "NG - PDF生成失敗<br>\n";
    }
    
} catch (Exception $e) {
    echo "NG - エラー: " . $e->getMessage() . "<br>\n";
}

// 4. 表紙ファイルの確認
echo "4. 表紙ファイルチェック:<br>\n";
$coverFormats = ['pdf', 'jpg', 'jpeg', 'png'];
foreach ($coverFormats as $format) {
    $coverPath = __DIR__ . '/../assets/cover.' . $format;
    echo "   cover.{$format}: ";
    if (file_exists($coverPath)) {
        echo "存在 (サイズ: " . filesize($coverPath) . " bytes)<br>\n";
    } else {
        echo "なし<br>\n";
    }
}

// 5. メモリ使用量
echo "5. メモリ使用量: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB<br>\n";
echo "   最大メモリ: " . ini_get('memory_limit') . "<br>\n";

// 6. PHPバージョン
echo "6. PHPバージョン: " . PHP_VERSION . "<br>\n";

// 7. 必要な拡張機能
echo "7. 拡張機能チェック:<br>\n";
$extensions = ['gd', 'mbstring', 'json'];
foreach ($extensions as $ext) {
    echo "   {$ext}: " . (extension_loaded($ext) ? 'OK' : 'NG') . "<br>\n";
}

echo "<br>\nテスト完了<br>\n";
?>