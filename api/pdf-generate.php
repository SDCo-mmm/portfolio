<?php
// pdf-generate.php - サーバーサイドでPDFを生成（完全版）

// 出力バッファリングを開始
ob_start();

// エラー表示（本番環境ではコメントアウト）
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

// TCPDF ライブラリを読み込む
require_once(__DIR__ . '/tcpdf/tcpdf.php');

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
if (!$posts) {
    ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['status' => 'error', 'message' => 'Failed to load posts data']);
    exit;
}

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

// カスタムPDFクラス（ページ番号追加用）
class CustomPDF extends TCPDF {
    private $currentPageNum = 0;
    private $totalPages = 0;
    
    public function setPageInfo($current, $total) {
        $this->currentPageNum = $current;
        $this->totalPages = $total;
    }
    
    public function Footer() {
        if ($this->currentPageNum > 0) {
            $this->SetY(-15);
            $this->SetFont('helvetica', '', 10);
            $this->SetTextColor(150, 150, 150);
            $this->Cell(0, 10, $this->currentPageNum . ' / ' . $this->totalPages, 0, false, 'C');
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
    $pdf->SetSubject('Portfolio Collection');

    // 日本語フォントを設定
    $pdf->SetFont('kozgopromedium', '', 12);

    // ヘッダーを無効化
    $pdf->setPrintHeader(false);

    // マージン設定
    $pdf->SetMargins(20, 20, 20);
    $pdf->SetAutoPageBreak(TRUE, 25);

    // 表紙を追加（PDF形式を優先）
    $coverAdded = false;
    
    // 方法1: 表紙PDFを使用（最優先）
    $coverPdfPath = __DIR__ . '/../assets/cover.pdf';
    if (file_exists($coverPdfPath)) {
        // 方法1-A: FPDI/TCPDIを使用した直接結合（利用可能な場合）
        if (class_exists('FPDI') || method_exists($pdf, 'setSourceFile')) {
            try {
                // FPDIまたはTCPDIのメソッドが使える場合
                $pageCount = $pdf->setSourceFile($coverPdfPath);
                if ($pageCount > 0) {
                    $tplIdx = $pdf->importPage(1);
                    $pdf->AddPage();
                    $pdf->useTemplate($tplIdx, 0, 0, 210, 297);
                    $coverAdded = true;
                    error_log("PDF表紙を直接結合しました");
                }
            } catch (Exception $e) {
                error_log('PDF結合エラー: ' . $e->getMessage());
            }
        }
        
        // 方法1-B: Ghostscriptを使用してPDFを画像に変換
        if (!$coverAdded) {
            $tempImagePath = sys_get_temp_dir() . '/cover_temp_' . uniqid() . '.jpg';
            
            // Ghostscriptコマンドのバリエーションを試す
            $commands = [
                // 標準的なGhostscriptコマンド
                "gs -dNOPAUSE -dBATCH -sDEVICE=jpeg -r300 -dJPEGQ=95 -sOutputFile={$tempImagePath} -dFirstPage=1 -dLastPage=1 {$coverPdfPath} 2>&1",
                // 古いバージョンのGhostscript
                "gs -dNOPAUSE -dBATCH -sDEVICE=jpeg -r300 -sOutputFile={$tempImagePath} {$coverPdfPath} 2>&1",
                // ImageMagickのconvertコマンド（PDFサポートがある場合）
                "convert -density 300 {$coverPdfPath}[0] -quality 95 {$tempImagePath} 2>&1",
                // pdftoppmコマンド（Popplerツール）
                "pdftoppm -jpeg -r 300 -singlefile {$coverPdfPath} " . str_replace('.jpg', '', $tempImagePath) . " 2>&1"
            ];
            
            foreach ($commands as $command) {
                exec($command, $output, $return_var);
                if ($return_var === 0 && file_exists($tempImagePath)) {
                    try {
                        $pdf->AddPage();
                        // A4全面に画像を配置
                        $pdf->Image($tempImagePath, 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0, false, false, true);
                        $coverAdded = true;
                        unlink($tempImagePath); // 一時ファイルを削除
                        error_log("PDFを画像変換して表紙追加（コマンド: " . explode(' ', $command)[0] . "）");
                        break;
                    } catch (Exception $e) {
                        error_log('PDF表紙画像追加エラー: ' . $e->getMessage());
                        if (file_exists($tempImagePath)) {
                            unlink($tempImagePath);
                        }
                    }
                } else {
                    error_log("PDFから画像への変換失敗（コマンド: " . explode(' ', $command)[0] . "）: " . implode("\n", $output));
                }
            }
        }
        
        // 方法1-C: PDFをベースに簡易的な表紙を作成
        if (!$coverAdded) {
            $pdf->AddPage();
            
            // 背景
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Rect(0, 0, 210, 297, 'F');
            
            // PDFアイコンを表示
            $pdf->SetDrawColor(200, 200, 200);
            $pdf->SetLineWidth(1);
            $pdf->Rect(80, 80, 50, 70, 'D');
            
            // アイコン内のテキスト
            $pdf->SetFont('helvetica', 'B', 24);
            $pdf->SetTextColor(150, 150, 150);
            $pdf->SetXY(80, 110);
            $pdf->Cell(50, 10, 'PDF', 0, 0, 'C');
            
            // メッセージ
            $pdf->SetFont('kozgopromedium', '', 12);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->SetY(170);
            $pdf->Cell(0, 10, 'Original Cover PDF', 0, 1, 'C');
            $pdf->Cell(0, 10, $coverPdfPath, 0, 1, 'C');
            
            $coverAdded = true;
            error_log("PDF表紙の代替表示を作成");
        }
    }
    
    // 方法2: 表紙画像を使用（PDFがない場合の代替）
    if (!$coverAdded) {
        $coverImageFormats = ['jpg', 'jpeg', 'png'];
        foreach ($coverImageFormats as $format) {
            $coverImagePath = __DIR__ . '/../assets/cover.' . $format;
            if (file_exists($coverImagePath)) {
                try {
                    $pdf->AddPage();
                    // A4全面に画像を配置（210mm x 297mm）
                    $pdf->Image($coverImagePath, 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0, false, false, true);
                    $coverAdded = true;
                    error_log("表紙画像を追加: " . $coverImagePath);
                    break;
                } catch (Exception $e) {
                    error_log('表紙画像追加エラー: ' . $e->getMessage());
                }
            }
        }
    }
    
    // 方法3: テキストベースの表紙を生成（最終手段）
    if (!$coverAdded) {
        $pdf->AddPage();
        
        // 背景色
        $pdf->SetFillColor(245, 245, 245);
        $pdf->Rect(0, 0, 210, 297, 'F');
        
        // 枠線
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->SetLineWidth(0.5);
        $pdf->Rect(10, 10, 190, 277, 'D');
        
        // タイトル
        $pdf->SetFont('kozgopromedium', 'B', 48);
        $pdf->SetTextColor(50, 50, 50);
        $pdf->SetY(80);
        $pdf->Cell(0, 20, 'PORTFOLIO', 0, 1, 'C');
        
        // サブタイトル
        $pdf->SetFont('kozgopromedium', '', 28);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetY(120);
        $pdf->Cell(0, 15, 'STARTEND Design Co.', 0, 1, 'C');
        
        // 線
        $pdf->SetDrawColor(150, 150, 150);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(60, 145, 150, 145);
        
        // 年
        $pdf->SetFont('kozgopromedium', '', 20);
        $pdf->SetY(160);
        $pdf->Cell(0, 10, date('Y'), 0, 1, 'C');
        
        // 選択された作品数
        $pdf->SetFont('kozgopromedium', '', 14);
        $pdf->SetY(200);
        $totalWorks = count($selectedPosts);
        $pdf->Cell(0, 10, $totalWorks . ' Works', 0, 1, 'C');
        
        $coverAdded = true;
        error_log("テキストベース表紙を生成");
    }

    // 各投稿をPDFに追加
    $totalPosts = count($selectedPosts);
    foreach ($selectedPosts as $index => $post) {
        $pdf->AddPage();
        $pdf->setPageInfo($index + 1, $totalPosts);
        
        $currentY = 20; // Y座標を管理
        
        // タイトル
        $pdf->SetY($currentY);
        $pdf->SetFont('kozgopromedium', 'B', 24);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->MultiCell(0, 10, $post['title'] ?? 'Untitled', 0, 'L', false, 1);
        $currentY = $pdf->GetY() + 5;
        
        // クライアント名とロゴ
        if (!empty($post['client_name']) || !empty($post['client_logo'])) {
            // クライアントロゴ
            if (!empty($post['client_logo'])) {
                $logoPath = $_SERVER['DOCUMENT_ROOT'] . $post['client_logo'];
                if (file_exists($logoPath)) {
                    try {
                        // ロゴのサイズを取得
                        $logoInfo = getimagesize($logoPath);
                        if ($logoInfo) {
                            $logoWidth = $logoInfo[0];
                            $logoHeight = $logoInfo[1];
                            
                            // 最大サイズを設定
                            $maxLogoWidth = 40;
                            $maxLogoHeight = 20;
                            
                            // アスペクト比を保持してサイズを計算
                            $ratio = min($maxLogoWidth / $logoWidth, $maxLogoHeight / $logoHeight);
                            $newLogoWidth = $logoWidth * $ratio;
                            $newLogoHeight = $logoHeight * $ratio;
                            
                            // ロゴを右側に配置
                            $logoX = 170 - $newLogoWidth;
                            
                            $pdf->Image($logoPath, $logoX, $currentY, $newLogoWidth, $newLogoHeight);
                            error_log("ロゴ追加成功: " . $post['client_logo']);
                        }
                    } catch (Exception $e) {
                        error_log('ロゴ画像エラー: ' . $e->getMessage() . ' - ' . $logoPath);
                    }
                }
            }
            
            // クライアント名
            if (!empty($post['client_name'])) {
                $pdf->SetY($currentY);
                $pdf->SetFont('kozgopromedium', '', 16);
                $pdf->SetTextColor(80, 80, 80);
                $pdf->MultiCell(120, 8, $post['client_name'], 0, 'L', false, 1);
            }
            
            $currentY = $pdf->GetY() + 10;
        }
        
        // タグ
        if (!empty($post['tags']) && is_array($post['tags'])) {
            $pdf->SetY($currentY);
            $pdf->SetFont('kozgopromedium', '', 12);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->MultiCell(0, 6, 'Tags: ' . implode(', ', $post['tags']), 0, 'L', false, 1);
            $currentY = $pdf->GetY() + 5;
        }
        
        // 説明文
        if (!empty($post['description'])) {
            $pdf->SetY($currentY);
            $pdf->SetFont('kozgopromedium', '', 12);
            $pdf->SetTextColor(0, 0, 0);
            $description = mb_convert_encoding($post['description'], 'UTF-8', 'auto');
            $pdf->MultiCell(0, 6, $description, 0, 'J', false, 1);
            $currentY = $pdf->GetY() + 10;
        }
        
        // 画像
        if (!empty($post['gallery_images']) && is_array($post['gallery_images'])) {
            $pdf->SetY($currentY);
            $maxImages = 2;
            $imageCount = 0;
            
            foreach ($post['gallery_images'] as $image) {
                if ($imageCount >= $maxImages) break;
                if ($pdf->GetY() > 220) break; // ページ下部に近い場合はスキップ
                
                $imagePath = $_SERVER['DOCUMENT_ROOT'] . $image['path'];
                
                // 縦長画像の場合はサムネイルを使用
                if (!empty($image['is_vertical']) && !empty($image['thumbnail'])) {
                    $thumbnailPath = $_SERVER['DOCUMENT_ROOT'] . $image['thumbnail'];
                    if (file_exists($thumbnailPath)) {
                        $imagePath = $thumbnailPath;
                        error_log("サムネイル使用: " . $image['thumbnail']);
                    }
                }
                
                if (file_exists($imagePath)) {
                    try {
                        // 画像のサイズを取得
                        $imageInfo = getimagesize($imagePath);
                        if ($imageInfo) {
                            $imgWidth = $imageInfo[0];
                            $imgHeight = $imageInfo[1];
                            
                            // 最大幅を設定
                            $maxWidth = 170;
                            $maxHeight = 80;
                            
                            // アスペクト比を保持してサイズを計算
                            $ratio = min($maxWidth / $imgWidth, $maxHeight / $imgHeight);
                            $newWidth = $imgWidth * $ratio;
                            $newHeight = $imgHeight * $ratio;
                            
                            // センタリングのためのX座標を計算
                            $x = ($pdf->getPageWidth() - $newWidth) / 2;
                            $y = $pdf->GetY();
                            
                            // 画像を追加
                            $pdf->Image($imagePath, $x, $y, $newWidth, $newHeight, '', '', '', true, 300, '', false, false, 0, false, false, false);
                            
                            // Y座標を更新
                            $pdf->SetY($y + $newHeight + 2);
                            
                            // キャプション
                            if (!empty($image['caption'])) {
                                $pdf->SetFont('kozgopromedium', '', 10);
                                $pdf->SetTextColor(100, 100, 100);
                                $pdf->MultiCell(0, 5, $image['caption'], 0, 'C', false, 1);
                                $pdf->Ln(3);
                            } else {
                                $pdf->Ln(5);
                            }
                            
                            $imageCount++;
                            error_log("画像追加成功: " . $image['path']);
                        }
                    } catch (Exception $e) {
                        error_log('画像処理エラー: ' . $e->getMessage() . ' - ' . $imagePath);
                    }
                } else {
                    error_log('画像ファイルが存在しません: ' . $imagePath);
                }
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
    echo json_encode(['status' => 'error', 'message' => 'PDF generation failed: ' . $e->getMessage()]);
    exit();
}
?>
    header('Pragma: public');
    
    // ブラウザに直接出力
    $pdf->Output($filename, 'D'); // 'D' = ダウンロード
    exit();
    
} catch (Exception $e) {
    ob_end_clean(); // バッファをクリア
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['status' => 'error', 'message' => 'PDF generation failed: ' . $e->getMessage()]);
    exit();
}
?>