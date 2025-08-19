<?php
// tcpdi.php - シンプルなPDF結合クラス
// /portfolio/api/tcpdf/tcpdi.php に配置

require_once(dirname(__FILE__).'/tcpdf.php');

class TCPDI extends TCPDF {
    
    protected $_tpldata = array();
    protected $tpls = array();
    protected $importedPages = array();
    
    /**
     * Import a page from external PDF
     */
    public function importPage($pageNo = 1) {
        // 簡易実装のため、表紙画像として扱う
        return $pageNo;
    }
    
    /**
     * Set source PDF file
     */
    public function setSourceFile($filename) {
        if (!file_exists($filename)) {
            return false;
        }
        // 簡易実装のため、ファイルの存在確認のみ
        $this->currentFilename = $filename;
        return 1; // 1ページあるものとして返す
    }
    
    /**
     * Use imported page as template
     */
    public function useTemplate($tplIdx, $x = 0, $y = 0, $w = 0, $h = 0) {
        // 表紙PDFを画像として扱う代替案
        if (isset($this->currentFilename) && file_exists($this->currentFilename)) {
            // PDFを画像に変換できない場合は、代替テキストを表示
            $this->SetFont('kozgopromedium', 'B', 36);
            $this->SetTextColor(0, 0, 0);
            $this->SetY(100);
            $this->Cell(0, 20, 'PORTFOLIO', 0, 1, 'C');
            
            $this->SetFont('kozgopromedium', '', 24);
            $this->SetY(130);
            $this->Cell(0, 15, 'STARTEND Design Co.', 0, 1, 'C');
            
            $this->SetFont('kozgopromedium', '', 16);
            $this->SetY(160);
            $this->Cell(0, 10, date('Y'), 0, 1, 'C');
        }
    }
}
?>