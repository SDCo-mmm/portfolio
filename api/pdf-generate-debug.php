<?php
// pdf-generate-debug.php - デバッグ版（エラー詳細を表示）

// エラーをすべて表示
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 出力バッファリングを開始
ob_start();

// デバッグ情報を記録
$debug = array();
$debug['start_time'] = microtime(true);
$debug['memory_start'] = memory_get_usage();

try {
    // TCPDF ライブラリを読み込む
    $tcpdf_path = __DIR__ . '/tcpdf/tcpdf.php';
    if (!file_exists($tcpdf_path)) {
        throw new Exception('TCPDFライブラリが見つかりません: ' . $tcpdf_path);
    }
    require_once($tcpdf_path);
    $debug['tcpdf_loaded'] = true;

    // 認証チェック（デバッグ時はスキップ可能）
    if (!isset($_COOKIE['front_auth_token']) || $_COOKIE['front_auth_token'] !== 'authenticated') {
        // デバッグ時は警告のみ
        $debug['auth_warning'] = '認証トークンが見つかりません';
    }

    // POSTデータから選択された投稿IDを取得
    $selectedPostIds = json_decode($_POST['postIds'] ?? '[]', true);
    $debug['selected_ids'] = $selectedPostIds;

    if (empty($selectedPostIds)) {
        throw new Exception('投稿が選択されていません');
    }

    // 投稿データを読み込む
    $posts_file = __DIR__ . '/../data/posts.json';
    if (!file_exists($posts_file)) {
        throw new Exception('posts.jsonが見つかりません: ' . $posts_file);
    }

    $posts_json = file_get_contents($posts_file);
    $posts = json_decode($posts_json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSONデコードエラー: ' . json_last_error_msg());
    }
    
    $debug['total_posts'] = count($posts);

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
    
    $debug['filtered_posts'] = count($selectedPosts);

    if (empty($selectedPosts)) {
        throw new Exception('選択された投稿が見つかりません');
    }

    // PDFクラス定義
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

    // PDFを生成
    $pdf = new CustomPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $debug['pdf_created'] = true;

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

    // 簡単な表紙を追加
    $pdf->AddPage();
    $pdf->SetFont('kozgopromedium', 'B', 36);
    $pdf->SetY(80);
    $pdf->Cell(0, 20, 'PORTFOLIO', 0, 1, 'C');
    $pdf->SetFont('kozgopromedium', '', 20);
    $pdf->Cell(0, 15, 'STARTEND Design Co.', 0, 1, 'C');
    
    $debug['cover_added'] = true;

    // 各投稿をPDFに追加
    $totalPosts = count($selectedPosts);
    foreach ($selectedPosts as $index => $post) {
        $pdf->AddPage();
        $pdf->setPageInfo($index + 1, $totalPosts);
        
        // タイトル
        $pdf->SetFont('kozgopromedium', 'B', 24);
        $pdf->SetTextColor(0, 0, 0);
        $title = $post['title'] ?? 'Untitled';
        $pdf->MultiCell(0, 10, $title, 0, 'L', false, 1);
        
        // クライアント名
        if (!empty($post['client_name'])) {
            $pdf->Ln(5);
            $pdf->SetFont('kozgopromedium', '', 16);
            $pdf->SetTextColor(80, 80, 80);
            $pdf->MultiCell(0, 8, $post['client_name'], 0, 'L', false, 1);
        }
        
        $debug['pages_added'] = $index + 1;
    }

    // デバッグ情報を最後のページに追加
    $pdf->AddPage();
    $pdf->SetFont('kozgopromedium', '', 10);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 10, 'Debug Information', 0, 1, 'C');
    $pdf->Ln(5);
    
    $debug['memory_end'] = memory_get_usage();
    $debug['memory_used'] = round(($debug['memory_end'] - $debug['memory_start']) / 1024 / 1024, 2) . ' MB';
    $debug['execution_time'] = round(microtime(true) - $debug['start_time'], 3) . ' seconds';
    
    foreach ($debug as $key => $value) {
        $pdf->Cell(0, 5, $key . ': ' . print_r($value, true), 0, 1);
    }

    // PDFを出力
    ob_end_clean();
    
    $date = date('Y-m-d');
    $filename = "STARTEND_Portfolio_Debug_{$date}.pdf";
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    $pdf->Output($filename, 'D');
    exit();
    
} catch (Exception $e) {
    ob_end_clean();
    
    // エラー詳細を返す
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    
    $error_response = array(
        'status' => 'error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'debug' => $debug
    );
    
    echo json_encode($error_response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}
?>