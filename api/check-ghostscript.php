<?php
// check-ghostscript.php - サーバーのPDF処理機能を確認

echo "<h2>PDF処理機能チェック</h2>";

// 1. Ghostscriptの確認
echo "<h3>1. Ghostscript</h3>";
exec('which gs', $output, $return_var);
if ($return_var === 0) {
    echo "✓ Ghostscriptがインストールされています: " . $output[0] . "<br>";
    exec('gs --version', $version);
    echo "バージョン: " . $version[0] . "<br>";
} else {
    echo "✗ Ghostscriptが見つかりません<br>";
}

// 2. ImageMagickの確認
echo "<h3>2. ImageMagick</h3>";
exec('which convert', $output2, $return_var2);
if ($return_var2 === 0) {
    echo "✓ ImageMagickがインストールされています: " . $output2[0] . "<br>";
    exec('convert -version | head -n 1', $version2);
    echo "バージョン: " . $version2[0] . "<br>";
    
    // PDFサポートの確認
    exec('convert -list format | grep PDF', $pdf_support);
    if (!empty($pdf_support)) {
        echo "✓ PDFサポート: あり<br>";
    } else {
        echo "✗ PDFサポート: なし<br>";
    }
} else {
    echo "✗ ImageMagickが見つかりません<br>";
}

// 3. Popplerツールの確認
echo "<h3>3. Poppler (pdftoppm)</h3>";
exec('which pdftoppm', $output3, $return_var3);
if ($return_var3 === 0) {
    echo "✓ Popplerがインストールされています: " . $output3[0] . "<br>";
} else {
    echo "✗ Popplerが見つかりません<br>";
}

// 4. PHP拡張の確認
echo "<h3>4. PHP拡張</h3>";
echo "GD: " . (extension_loaded('gd') ? '✓' : '✗') . "<br>";
echo "ImageMagick (PHP): " . (extension_loaded('imagick') ? '✓' : '✗') . "<br>";

// 5. 実際のPDF変換テスト
echo "<h3>5. PDF変換テスト</h3>";
$testPdf = __DIR__ . '/../assets/cover.pdf';
if (file_exists($testPdf)) {
    echo "テストPDF: 存在<br>";
    
    // Ghostscriptでテスト
    $tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.jpg';
    $cmd = "gs -dNOPAUSE -dBATCH -sDEVICE=jpeg -r150 -sOutputFile={$tempFile} -dFirstPage=1 -dLastPage=1 {$testPdf} 2>&1";
    exec($cmd, $output4, $return_var4);
    
    if ($return_var4 === 0 && file_exists($tempFile)) {
        echo "✓ Ghostscriptによる変換: 成功<br>";
        echo "生成された画像サイズ: " . filesize($tempFile) . " bytes<br>";
        unlink($tempFile);
    } else {
        echo "✗ Ghostscriptによる変換: 失敗<br>";
        echo "エラー: " . implode("<br>", $output4) . "<br>";
    }
} else {
    echo "テストPDF: なし（/portfolio/assets/cover.pdf）<br>";
}

echo "<br><hr><br>";
echo "<p>注意: PDF表紙を使用するには、上記のいずれかのツールが必要です。</p>";
?>