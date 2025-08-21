<?php
// FPDI統合用ローダー
require_once(__DIR__ . '/tcpdf.php');

// FPDIのオートローダーを読み込む
require_once(__DIR__ . '/fpdi/autoload.php');

use setasign\Fpdi\TcpdfFpdi;

// FPDI対応のTCPDFクラス
class TCPDF_FPDI extends TcpdfFpdi {
    // 必要に応じてカスタマイズ
}
?>