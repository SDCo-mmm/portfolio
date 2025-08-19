// PDF出力機能（サーバーサイド版）
document.addEventListener('DOMContentLoaded', () => {
  console.log('PDF Export module (Server-side) loaded');

  // DOM要素
  const pdfModeBtn = document.getElementById('pdfModeBtn');
  const pdfControlBar = document.getElementById('pdfControlBar');
  const selectedCountSpan = document.getElementById('selectedCount');
  const clearSelectionBtn = document.getElementById('clearSelectionBtn');
  const selectAllBtn = document.getElementById('selectAllBtn');
  const generatePdfBtn = document.getElementById('generatePdfBtn');
  const exitPdfModeBtn = document.getElementById('exitPdfModeBtn');
  const postGrid = document.getElementById('postGrid');

  // 状態管理
  let pdfMode = false;
  let selectedPosts = new Map();
  let selectedOrder = [];

  // PDF出力モードの切替
  pdfModeBtn.addEventListener('click', () => {
    pdfMode = !pdfMode;
    togglePdfMode();
  });

  // PDFモード解除ボタン
  exitPdfModeBtn.addEventListener('click', () => {
    pdfMode = false;
    togglePdfMode();
  });

  // PDFモードの有効/無効切替
  function togglePdfMode() {
    if (pdfMode) {
      document.body.classList.add('pdf-selection-mode');
      pdfModeBtn.classList.add('active');
      pdfControlBar.style.display = 'block';
      enablePostSelection();
    } else {
      document.body.classList.remove('pdf-selection-mode');
      pdfModeBtn.classList.remove('active');
      pdfControlBar.style.display = 'none';
      disablePostSelection();
      clearSelection();
    }
  }

  // 投稿選択機能を有効化
  function enablePostSelection() {
    const postItems = postGrid.querySelectorAll('.post-item');
    postItems.forEach(item => {
      item.addEventListener('click', handlePostSelection);
      item.style.pointerEvents = 'auto';
    });
  }

  // 投稿選択機能を無効化
  function disablePostSelection() {
    const postItems = postGrid.querySelectorAll('.post-item');
    postItems.forEach(item => {
      item.removeEventListener('click', handlePostSelection);
      item.classList.remove('pdf-selected');
      const numberBadge = item.querySelector('.pdf-selection-number');
      if (numberBadge) {
        numberBadge.remove();
      }
    });
  }

  // 投稿選択ハンドラー
  function handlePostSelection(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const postItem = e.currentTarget;
    const postId = new URL(postItem.href).searchParams.get('id');
    
    if (!postId) return;

    if (selectedPosts.has(postId)) {
      selectedPosts.delete(postId);
      selectedOrder = selectedOrder.filter(id => id !== postId);
      postItem.classList.remove('pdf-selected');
      
      const numberBadge = postItem.querySelector('.pdf-selection-number');
      if (numberBadge) {
        numberBadge.remove();
      }
    } else {
      selectedOrder.push(postId);
      selectedPosts.set(postId, selectedOrder.length);
      postItem.classList.add('pdf-selected');
      
      const numberBadge = document.createElement('div');
      numberBadge.className = 'pdf-selection-number';
      numberBadge.textContent = selectedOrder.length;
      postItem.appendChild(numberBadge);
    }

    updateSelectionCount();
    updateSelectionNumbers();
  }

  // 選択番号を更新
  function updateSelectionNumbers() {
    const postItems = postGrid.querySelectorAll('.post-item');
    postItems.forEach(item => {
      const postId = new URL(item.href).searchParams.get('id');
      const numberBadge = item.querySelector('.pdf-selection-number');
      
      if (postId && selectedPosts.has(postId) && numberBadge) {
        const order = selectedOrder.indexOf(postId) + 1;
        numberBadge.textContent = order;
      }
    });
  }

  // 選択数を更新
  function updateSelectionCount() {
    const count = selectedPosts.size;
    selectedCountSpan.textContent = count;
    generatePdfBtn.disabled = count === 0;
  }

  // 選択をクリア
  function clearSelection() {
    selectedPosts.clear();
    selectedOrder = [];
    
    const postItems = postGrid.querySelectorAll('.post-item.pdf-selected');
    postItems.forEach(item => {
      item.classList.remove('pdf-selected');
      const numberBadge = item.querySelector('.pdf-selection-number');
      if (numberBadge) {
        numberBadge.remove();
      }
    });
    
    updateSelectionCount();
  }

  // クリアボタンのイベント
  clearSelectionBtn.addEventListener('click', clearSelection);

  // すべてを選択ボタンのイベント
  selectAllBtn.addEventListener('click', selectAllPosts);

  // すべての投稿を選択
  function selectAllPosts() {
    const postItems = postGrid.querySelectorAll('.post-item');
    
    clearSelection();
    
    postItems.forEach((item, index) => {
      const postId = new URL(item.href).searchParams.get('id');
      if (postId) {
        selectedOrder.push(postId);
        selectedPosts.set(postId, index + 1);
        item.classList.add('pdf-selected');
        
        const numberBadge = document.createElement('div');
        numberBadge.className = 'pdf-selection-number';
        numberBadge.textContent = index + 1;
        item.appendChild(numberBadge);
      }
    });
    
    updateSelectionCount();
  }

  // PDF生成ボタンのイベント
  generatePdfBtn.addEventListener('click', async () => {
    if (selectedPosts.size === 0) return;
    
    console.log('Generating PDF for posts:', selectedOrder);
    await generatePDF();
  });

  // PDF生成処理（サーバーサイド）
  async function generatePDF() {
    showLoadingOverlay('PDFを生成中...');
    
    try {
      // FormDataを作成
      const formData = new FormData();
      formData.append('postIds', JSON.stringify(selectedOrder));
      
      // サーバーにリクエスト
      const response = await fetch('/portfolio/api/pdf-generate.php', {
        method: 'POST',
        body: formData
      });
      
      // エラーチェック
      if (!response.ok) {
        // エラーレスポンスの内容を取得
        const contentType = response.headers.get('content-type');
        let errorMessage = 'PDF生成に失敗しました';
        
        if (contentType && contentType.includes('application/json')) {
          // JSONレスポンスの場合
          try {
            const errorData = await response.json();
            errorMessage = errorData.message || errorMessage;
          } catch (e) {
            // JSONパースエラー
            errorMessage = 'サーバーエラーが発生しました（JSONパースエラー）';
          }
        } else {
          // HTMLやテキストレスポンスの場合（PHPエラーの可能性）
          try {
            const errorText = await response.text();
            console.error('サーバーエラー詳細:', errorText);
            errorMessage = 'サーバーエラーが発生しました。コンソールを確認してください。';
          } catch (e) {
            errorMessage = 'サーバーエラーが発生しました';
          }
        }
        
        throw new Error(errorMessage);
      }
      
      // PDFをダウンロード
      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.style.display = 'none';
      a.href = url;
      const date = new Date().toISOString().split('T')[0];
      a.download = `STARTEND_Portfolio_${date}.pdf`;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      document.body.removeChild(a);
      
      // 完了後の処理
      hideLoadingOverlay();
      clearSelection();
      togglePdfMode();
      
      // 成功メッセージ
      showSuccessMessage('PDFが正常に生成されました');
      
    } catch (error) {
      console.error('PDF生成エラー:', error);
      hideLoadingOverlay();
      alert('PDF生成中にエラーが発生しました。\n' + error.message);
    }
  }

  // 成功メッセージを表示
  function showSuccessMessage(message) {
    const successDiv = document.createElement('div');
    successDiv.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      background-color: #28a745;
      color: white;
      padding: 15px 25px;
      border-radius: 5px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.2);
      z-index: 10000;
      font-size: 14px;
      animation: slideIn 0.3s ease;
    `;
    successDiv.textContent = message;
    document.body.appendChild(successDiv);
    
    setTimeout(() => {
      successDiv.style.animation = 'slideOut 0.3s ease';
      setTimeout(() => document.body.removeChild(successDiv), 300);
    }, 3000);
  }

  // ローディングオーバーレイを表示
  function showLoadingOverlay(message) {
    const overlay = document.createElement('div');
    overlay.className = 'pdf-loading-overlay';
    overlay.innerHTML = `
      <div class="pdf-loading-content">
        <div class="pdf-loading-spinner"></div>
        <div class="pdf-loading-text">${message}</div>
        <div class="pdf-loading-progress">サーバーで処理中...</div>
      </div>
    `;
    document.body.appendChild(overlay);
  }

  // ローディングオーバーレイを非表示
  function hideLoadingOverlay() {
    const overlay = document.querySelector('.pdf-loading-overlay');
    if (overlay) {
      overlay.remove();
    }
  }

  // MutationObserverで動的に追加される投稿を監視
  const observer = new MutationObserver((mutations) => {
    if (pdfMode) {
      mutations.forEach(mutation => {
        mutation.addedNodes.forEach(node => {
          if (node.classList && node.classList.contains('post-item')) {
            node.addEventListener('click', handlePostSelection);
            
            const postId = new URL(node.href).searchParams.get('id');
            if (postId && selectedPosts.has(postId)) {
              node.classList.add('pdf-selected');
              const order = selectedOrder.indexOf(postId) + 1;
              const numberBadge = document.createElement('div');
              numberBadge.className = 'pdf-selection-number';
              numberBadge.textContent = order;
              node.appendChild(numberBadge);
            }
          }
        });
      });
    }
  });

  // 投稿グリッドの変更を監視
  if (postGrid) {
    observer.observe(postGrid, {
      childList: true,
      subtree: true
    });
  }
});

// CSSアニメーション追加
const style = document.createElement('style');
style.textContent = `
  @keyframes slideIn {
    from {
      transform: translateX(100%);
      opacity: 0;
    }
    to {
      transform: translateX(0);
      opacity: 1;
    }
  }
  
  @keyframes slideOut {
    from {
      transform: translateX(0);
      opacity: 1;
    }
    to {
      transform: translateX(100%);
      opacity: 0;
    }
  }
`;
document.head.appendChild(style);