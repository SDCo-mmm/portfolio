<?php
// pdf-generate.php - FPDI対応完全修正版

// エラー表示（デバッグ用）
ini_set('display_errors', 1);
error_reporting(E_ALL);

ob_start();

// 基本的なTCPDFを読み込む
require_once(__DIR__ . '/tcpdf/tcpdf.php');

// FPDIが利用可能か確認
$fpdi_available = false;
$fpdi_autoload = __DIR__ . '/tcpdf/fpdi/autoload.php';

if (file_exists($fpdi_autoload)) {
    require_once($fpdi_autoload);
    $fpdi_available = true;
    error_log("FPDI is available");
} else {
    error_log("FPDI not found at: " . $fpdi_autoload);
}

// 認証チェック
if (!isset($_COOKIE['front_auth_token']) || $_COOKIE['front_auth_token'] !== 'authenticated') {
    ob_end_clean();
    http_response_code(401);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// POSTデータから選択された投稿IDを取得
$selectedPostIds = json_decode($_POST['postIds'] ?? '[]', true);

if (empty($selectedPostIds)) {
    ob_end_clean();
    http_response_code(400);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['status' => 'error', 'message' => 'No posts selected']);
    exit;
}

// 投稿データを読み込む
$posts_file = __DIR__ . '/../data/posts.json';
if (!file_exists($posts_file)) {
    ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['status' => 'error', 'message' => 'Posts data not found']);
    exit;
}

$posts = json_decode(file_get_contents($posts_file), true);

// 選択された投稿をフィルタリング
$selectedPosts = [];
foreach ($selectedPostIds as $postId) {
    foreach ($posts as $post) {
        if ($post['id'] === $postId) {
            $selectedPosts[] = $post;
            break;
        }
    }
}

// カスタムPDFクラスの定義（文字化け修正版）
if ($fpdi_available) {
    class CustomPDF extends \setasign\Fpdi\TcpdfFpdi {
        private $clientName = '';
        private $postTitle = '';
        private $currentPostPage = 0;
        private $totalPostPages = 0;
        
        public function setPageInfo($client, $title, $current, $total) {
            $this->clientName = $client;
            $this->postTitle = $title;
            $this->currentPostPage = $current;
            $this->totalPostPages = $total;
        }
        
        public function Footer() {
            if ($this->currentPostPage > 0) {
                $this->SetY(-15);
                // 日本語フォントに変更
                $this->SetFont('kozgopromedium', '', 9);
                $this->SetTextColor(150, 150, 150);
                
                // フッターテキスト（日本語対応）
                $footerText = $this->clientName . ' - ' . $this->postTitle . ' - ' . $this->currentPostPage . '/' . $this->totalPostPages;
                $this->Cell(0, 10, $footerText, 0, false, 'C');
            }
        }
    }
} else {
    class CustomPDF extends TCPDF {
        private $clientName = '';
        private $postTitle = '';
        private $currentPostPage = 0;
        private $totalPostPages = 0;
        
        public function setPageInfo($client, $title, $current, $total) {
            $this->clientName = $client;
            $this->postTitle = $title;
            $this->currentPostPage = $current;
            $this->totalPostPages = $total;
        }
        
        public function Footer() {
            if ($this->currentPostPage > 0) {
                $this->SetY(-15);
                // 日本語フォントに変更
                $this->SetFont('kozgopromedium', '', 9);
                $this->SetTextColor(150, 150, 150);
                
                // フッターテキスト（日本語対応）
                $footerText = $this->clientName . ' - ' . $this->postTitle . ' - ' . $this->currentPostPage . '/' . $this->totalPostPages;
                $this->Cell(0, 10, $footerText, 0, false, 'C');
            }
        }
    }
}

try {
    // PDFを生成
    $pdf = new CustomPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // 文書情報
    $pdf->SetCreator('STARTEND Design Co.');
    $pdf->SetAuthor('STARTEND Design Co.');
    $pdf->SetTitle('Portfolio');
    
    // 日本語フォントを設定
    $pdf->SetFont('kozgopromedium', '', 12);
    
    // ヘッダーを無効化
    $pdf->setPrintHeader(false);
    
    // マージン設定
    $pdf->SetMargins(20, 20, 20);
    $pdf->SetAutoPageBreak(TRUE, 25);
    
    // ===== 表紙追加処理 =====
    $coverAdded = false;
    $coverPdfPath = __DIR__ . '/../assets/cover.pdf';
    
    if (file_exists($coverPdfPath)) {
        error_log("Cover PDF found: " . $coverPdfPath);
        
        // FPDIが利用可能な場合、PDFを直接インポート
        if ($fpdi_available) {
            try {
                error_log("Attempting to import PDF with FPDI...");
                
                // PDFファイルをソースとして設定
                $pageCount = $pdf->setSourceFile($coverPdfPath);
                error_log("FPDI: PDF has " . $pageCount . " pages");
                
                if ($pageCount > 0) {
                    // 最初のページをインポート
                    $templateId = $pdf->importPage(1);
                    
                    // 新しいページを追加
                    $pdf->AddPage('P', array(210, 297));
                    
                    // インポートしたページを配置
                    $pdf->useTemplate($templateId, 0, 0, 210, 297);
                    
                    $coverAdded = true;
                    error_log("✓ PDF cover imported directly!");
                }
            } catch (Exception $e) {
                error_log("FPDI import error: " . $e->getMessage());
            }
        }
        
        // FPDIが使えない、または失敗した場合はGhostscriptで変換
        if (!$coverAdded) {
            error_log("Using Ghostscript for PDF to image conversion...");
            
            $tempImagePath = sys_get_temp_dir() . '/cover_' . uniqid() . '.jpg';
            $cmd = "gs -dNOPAUSE -dBATCH -sDEVICE=jpeg -r150 -sOutputFile={$tempImagePath} -dFirstPage=1 -dLastPage=1 {$coverPdfPath} 2>&1";
            
            exec($cmd, $output, $return_var);
            
            if ($return_var === 0 && file_exists($tempImagePath)) {
                $pdf->AddPage();
                $pdf->Image($tempImagePath, 0, 0, 210, 297, 'JPG', '', '', false, 300, '', false, false, 0, false, false, true);
                $coverAdded = true;
                error_log("✓ Cover added as image");
                unlink($tempImagePath);
            }
        }
    }
    
    // 表紙が追加できなかった場合
    if (!$coverAdded) {
        $pdf->AddPage();
        
        // テキストベースの表紙
        $pdf->SetFillColor(245, 245, 245);
        $pdf->Rect(0, 0, 210, 297, 'F');
        
        $pdf->SetFont('kozgopromedium', 'B', 48);
        $pdf->SetTextColor(50, 50, 50);
        $pdf->SetY(80);
        $pdf->Cell(0, 20, 'PORTFOLIO', 0, 1, 'C');
        
        $pdf->SetFont('kozgopromedium', '', 28);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetY(120);
        $pdf->Cell(0, 15, 'STARTEND Design Co.', 0, 1, 'C');
        
        error_log("Text-based cover generated");
    }
    
// 各投稿をPDFに追加（修正版）
    $totalPosts = count($selectedPosts);
    foreach ($selectedPosts as $index => $post) {
        $pdf->AddPage();
        
        // この投稿の画像数から必要なページ数を計算
        $totalImages = count($post['gallery_images'] ?? []);
        $imagesPerFirstPage = 4;  // 1ページ目は2×2
        $imagesPerOtherPage = 6;  // 2ページ目以降は2×3
        $totalPagesForPost = 1;
        if ($totalImages > $imagesPerFirstPage) {
            $remainingImages = $totalImages - $imagesPerFirstPage;
            $totalPagesForPost += ceil($remainingImages / $imagesPerOtherPage);
        }
        
        // 現在の投稿内のページ番号
        $currentPostPage = 1;
        
        // フッター用の情報を設定
        $pdf->setPageInfo($post['client_name'] ?? '', $post['title'] ?? 'Untitled', $currentPostPage, $totalPagesForPost);
        
        // 余白を狭める（上部10mm）
        $currentY = 10;
        
        // 1. クライアントロゴ（上部中央）
        if (!empty($post['client_logo'])) {
            $logoPath = $_SERVER['DOCUMENT_ROOT'] . $post['client_logo'];
            
            if (file_exists($logoPath)) {
                try {
                    $logoInfo = getimagesize($logoPath);
                    if ($logoInfo) {
                        $logoWidth = $logoInfo[0];
                        $logoHeight = $logoInfo[1];
                        
                        $maxLogoWidth = 50;
                        $maxLogoHeight = 25;
                        
                        $ratio = min($maxLogoWidth / $logoWidth, $maxLogoHeight / $logoHeight);
                        $newLogoWidth = $logoWidth * $ratio;
                        $newLogoHeight = $logoHeight * $ratio;
                        
                        $logoX = (210 - $newLogoWidth) / 2;
                        
                        $pdf->Image($logoPath, $logoX, $currentY, $newLogoWidth, $newLogoHeight, '', '', '', false, 150, '', false, false, 0, false, false, false);
                        $currentY += $newLogoHeight + 5;
                    }
                } catch (Exception $e) {
                    error_log('Logo error: ' . $e->getMessage());
                }
            }
        }
        
        // 2. クライアント名（12pt、センタリング）
        if (!empty($post['client_name'])) {
            $pdf->SetY($currentY);
            $pdf->SetFont('kozgopromedium', '', 12);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(0, 5, $post['client_name'], 0, 1, 'C');
            $currentY = $pdf->GetY() + 2;
        }
        
        // 3. タイトル（16pt、センタリング、ボールド）
        $pdf->SetY($currentY);
        $pdf->SetFont('kozgopromedium', 'B', 16);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 7, $post['title'] ?? 'Untitled', 0, 1, 'C');
        $currentY = $pdf->GetY() + 5;
        
        // 4. 説明文（10pt、両端揃え）
        if (!empty($post['description'])) {
            $pdf->SetY($currentY);
            $pdf->SetFont('kozgopromedium', '', 10);
            $pdf->SetTextColor(60, 60, 60);
            
            $pdf->SetLeftMargin(30);
            $pdf->SetRightMargin(30);
            
            $description = mb_convert_encoding($post['description'], 'UTF-8', 'auto');
            $pdf->MultiCell(0, 4.5, $description, 0, 'J', false, 1);
            
            $pdf->SetLeftMargin(20);
            $pdf->SetRightMargin(20);
            
            $currentY = $pdf->GetY() + 8;
        }
        
        // 5. ギャラリー画像（グリッドレイアウト）
        if (!empty($post['gallery_images']) && is_array($post['gallery_images'])) {
            $imageIndex = 0;
            $totalImagesCount = count($post['gallery_images']);
            
            // 統一された画像サイズ設定（ここで定義）
            $standardImageWidth = 85;   // 画像の幅
            $standardImageHeight = 65;  // 画像の高さ
            $standardHorizontalGap = 10; // 横の間隔
            $standardVerticalGap = 10;   // 縦の間隔
            
            // 1ページ目の画像（2×2グリッド）
            $firstPageImages = array_slice($post['gallery_images'], 0, 4);
            
            if (count($firstPageImages) > 0) {
                $cols = 2;
                $rows = ceil(count($firstPageImages) / $cols);
                
                $totalWidth = ($standardImageWidth * $cols) + ($standardHorizontalGap * ($cols - 1));
                $startX = (210 - $totalWidth) / 2;
                
                $pdf->SetY($currentY);
                
                for ($row = 0; $row < $rows; $row++) {
                    $rowY = $currentY + ($row * ($standardImageHeight + $standardVerticalGap + 5));
                    
                    if ($rowY + $standardImageHeight > 265) break;
                    
                    for ($col = 0; $col < $cols; $col++) {
                        $idx = ($row * $cols) + $col;
                        if ($idx >= count($firstPageImages)) break;
                        
                        $image = $firstPageImages[$idx];
                        $imagePath = $_SERVER['DOCUMENT_ROOT'] . $image['path'];
                        
                        if (!empty($image['is_vertical']) && !empty($image['thumbnail'])) {
                            $thumbnailPath = $_SERVER['DOCUMENT_ROOT'] . $image['thumbnail'];
                            if (file_exists($thumbnailPath)) {
                                $imagePath = $thumbnailPath;
                            }
                        }
                        
                        if (file_exists($imagePath)) {
                            try {
                                $imageInfo = getimagesize($imagePath);
                                if ($imageInfo) {
                                    $imgWidth = $imageInfo[0];
                                    $imgHeight = $imageInfo[1];
                                    
                                    $ratio = min($standardImageWidth / $imgWidth, $standardImageHeight / $imgHeight);
                                    $newWidth = $imgWidth * $ratio;
                                    $newHeight = $imgHeight * $ratio;
                                    
                                    $x = $startX + ($col * ($standardImageWidth + $standardHorizontalGap)) + (($standardImageWidth - $newWidth) / 2);
                                    $y = $rowY + (($standardImageHeight - $newHeight) / 2);
                                    
                                    // 画像を配置
                                    $pdf->Image($imagePath, $x, $y, $newWidth, $newHeight, '', '', '', false, 150, '', false, false, 0, false, false, false);
                                    
                                    // 画像に枠線を追加
                                    $pdf->SetDrawColor(180, 180, 180); // グレーの色（明るめのグレー）
                                    $pdf->SetLineWidth(0.3); // 線の太さ（約1px相当）
                                    $pdf->Rect($x, $y, $newWidth, $newHeight, 'D'); // 枠線を描画
                                    
                                    if (!empty($image['caption'])) {
                                        $pdf->SetFont('kozgopromedium', '', 8);
                                        $pdf->SetTextColor(120, 120, 120);
                                        $captionY = $rowY + $standardImageHeight + 2;
                                        $captionX = $startX + ($col * ($standardImageWidth + $standardHorizontalGap));
                                        $pdf->SetXY($captionX, $captionY);
                                        $pdf->Cell($standardImageWidth, 3, $image['caption'], 0, 0, 'C');
                                    }
                                }
                            } catch (Exception $e) {
                                error_log('Gallery image error: ' . $e->getMessage());
                            }
                        }
                    }
                }
                
                $imageIndex = count($firstPageImages);
            }
            
            // 残りの画像がある場合は新しいページに追加
            while ($imageIndex < $totalImagesCount) {
                $pdf->AddPage();
                $currentPostPage++;
                $pdf->setPageInfo($post['client_name'] ?? '', $post['title'] ?? 'Untitled', $currentPostPage, $totalPagesForPost);
                
                $pageStartY = 15;
                
                // このページの画像を取得
                $pageImages = array_slice($post['gallery_images'], $imageIndex, 6);
                
                $cols = 2;
                $maxRows = 3;
                
                $totalWidth = ($standardImageWidth * $cols) + ($standardHorizontalGap * ($cols - 1));
                $startX = (210 - $totalWidth) / 2;
                
                $imageCount = 0;
                for ($row = 0; $row < $maxRows; $row++) {
                    $rowY = $pageStartY + ($row * ($standardImageHeight + $standardVerticalGap + 5));
                    
                    if ($rowY + $standardImageHeight > 270) break;
                    
                    for ($col = 0; $col < $cols; $col++) {
                        $idx = ($row * $cols) + $col;
                        if ($idx >= count($pageImages)) break;
                        
                        $image = $pageImages[$idx];
                        $imagePath = $_SERVER['DOCUMENT_ROOT'] . $image['path'];
                        
                        if (!empty($image['is_vertical']) && !empty($image['thumbnail'])) {
                            $thumbnailPath = $_SERVER['DOCUMENT_ROOT'] . $image['thumbnail'];
                            if (file_exists($thumbnailPath)) {
                                $imagePath = $thumbnailPath;
                            }
                        }
                        
                        if (file_exists($imagePath)) {
                            try {
                                $imageInfo = getimagesize($imagePath);
                                if ($imageInfo) {
                                    $imgWidth = $imageInfo[0];
                                    $imgHeight = $imageInfo[1];
                                    
                                    $ratio = min($standardImageWidth / $imgWidth, $standardImageHeight / $imgHeight);
                                    $newWidth = $imgWidth * $ratio;
                                    $newHeight = $imgHeight * $ratio;
                                    
                                    $x = $startX + ($col * ($standardImageWidth + $standardHorizontalGap)) + (($standardImageWidth - $newWidth) / 2);
                                    $y = $rowY + (($standardImageHeight - $newHeight) / 2);
                                    
                                    // 画像を配置
                                    $pdf->Image($imagePath, $x, $y, $newWidth, $newHeight, '', '', '', false, 150, '', false, false, 0, false, false, false);
                                    
                                    // 画像に枠線を追加
                                    $pdf->SetDrawColor(180, 180, 180); // グレーの色
                                    $pdf->SetLineWidth(0.3); // 線の太さ
                                    $pdf->Rect($x, $y, $newWidth, $newHeight, 'D'); // 枠線を描画
                                    
                                    if (!empty($image['caption'])) {
                                        $pdf->SetFont('kozgopromedium', '', 8);
                                        $pdf->SetTextColor(120, 120, 120);
                                        $captionY = $rowY + $standardImageHeight + 2;
                                        $captionX = $startX + ($col * ($standardImageWidth + $standardHorizontalGap));
                                        $pdf->SetXY($captionX, $captionY);
                                        $pdf->Cell($standardImageWidth, 3, $image['caption'], 0, 0, 'C');
                                    }
                                    
                                    $imageCount++;
                                }
                            } catch (Exception $e) {
                                error_log('Gallery image error: ' . $e->getMessage());
                            }
                        }
                    }
                }
                
                $imageIndex += $imageCount;
                if ($imageCount == 0) break; // 画像がない場合は終了
            }
        }
    }
    
    // PDFを出力
    ob_end_clean();
    
    $date = date('Y-m-d');
    $filename = "STARTEND_Portfolio_{$date}.pdf";
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    $pdf->Output($filename, 'D');
    exit();
    
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'status' => 'error', 
        'message' => 'PDF generation failed: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    exit();
}
?>