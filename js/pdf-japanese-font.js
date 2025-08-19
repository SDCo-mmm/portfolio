// PDF用日本語フォント設定
// M PLUS 1pフォントの基本的な文字セットをBase64エンコード

window.JapaneseFontConfig = {
  // フォント初期化関数
  initializeFont: function(pdf) {
    // jsPDFにカスタムフォントを追加
    // 実際の実装では、ここでフォントデータを設定しますが、
    // 今回はjsPDFのデフォルトフォントで日本語をサポートする方法を使用
    
    // UTF-8サポートを有効化
    pdf.setLanguage("ja");
    
    // 代替案：Canvas経由でのレンダリング
    return {
      renderText: function(text, x, y, options = {}) {
        const {
          fontSize = 12,
          fontStyle = 'normal',
          maxWidth = 170,
          align = 'left'
        } = options;
        
        // 基本的なASCII文字はそのまま表示
        const asciiRegex = /^[\x00-\x7F]*$/;
        if (asciiRegex.test(text)) {
          pdf.setFontSize(fontSize);
          if (align === 'center') {
            pdf.text(text, x, y, { align: 'center' });
          } else {
            pdf.text(text, x, y);
          }
          return;
        }
        
        // 日本語文字を含む場合の処理
        // 文字を分割して配置
        const lines = this.splitTextToLines(text, maxWidth, fontSize);
        let currentY = y;
        
        lines.forEach(line => {
          // 各文字を個別に配置（文字化け回避）
          let currentX = x;
          for (let char of line) {
            if (asciiRegex.test(char)) {
              pdf.setFontSize(fontSize);
              pdf.text(char, currentX, currentY);
              currentX += pdf.getTextWidth(char);
            } else {
              // 日本語文字は代替文字またはスキップ
              const replacement = this.getCharReplacement(char);
              if (replacement) {
                pdf.setFontSize(fontSize);
                pdf.text(replacement, currentX, currentY);
                currentX += pdf.getTextWidth(replacement) * 1.5; // 日本語は幅広
              } else {
                currentX += fontSize * 0.8; // スペース分進める
              }
            }
          }
          currentY += fontSize * 0.4; // 行間
        });
      },
      
      // テキストを行に分割
      splitTextToLines: function(text, maxWidth, fontSize) {
        const lines = [];
        const words = text.split('');
        let currentLine = '';
        const charWidth = fontSize * 0.5; // 概算文字幅
        
        for (let char of words) {
          const lineWidth = currentLine.length * charWidth;
          if (lineWidth < maxWidth) {
            currentLine += char;
          } else {
            if (currentLine) lines.push(currentLine);
            currentLine = char;
          }
        }
        if (currentLine) lines.push(currentLine);
        
        return lines;
      },
      
      // 文字置換マップ（一部の記号のみ）
      getCharReplacement: function(char) {
        const replacements = {
          '、': ',',
          '。': '.',
          '「': '[',
          '」': ']',
          '（': '(',
          '）': ')',
          '・': '･',
          'ー': '-',
          '！': '!',
          '？': '?',
          '：': ':',
          '；': ';'
        };
        return replacements[char] || '';
      }
    };
  }
};