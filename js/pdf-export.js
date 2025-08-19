// PDF出力機能（pdfmake版 - 日本語対応）
document.addEventListener('DOMContentLoaded', () => {
  console.log('PDF Export module v2 loaded');

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
  let selectedPosts = new Map(); // postId => order
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
      // 選択解除
      selectedPosts.delete(postId);
      selectedOrder = selectedOrder.filter(id => id !== postId);
      postItem.classList.remove('pdf-selected');
      
      const numberBadge = postItem.querySelector('.pdf-selection-number');
      if (numberBadge) {
        numberBadge.remove();
      }
    } else {
      // 選択追加
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

  // PDF生成処理（pdfmake使用）
  async function generatePDF() {
    showLoadingOverlay('PDFを生成中...');
    
    try {
      // 投稿データを取得
      const response = await fetch('/portfolio/api/get_posts.php');
      const allPosts = await response.json();
      
      // 選択された投稿を順番通りに取得
      const selectedPostsData = [];
      for (const postId of selectedOrder) {
        const post = allPosts.find(p => p.id === postId);
        if (post) {
          selectedPostsData.push(post);
        }
      }

      // PDFドキュメントの定義
      const docDefinition = {
        pageSize: 'A4',
        pageMargins: [40, 40, 40, 60],
        defaultStyle: {
          font: 'Roboto' // pdfmakeのデフォルトフォント（日本語は自動的にNotoSansに切り替わる）
        },
        content: [],
        footer: function(currentPage, pageCount) {
          return {
            text: currentPage + ' / ' + pageCount,
            alignment: 'center',
            margin: [0, 20, 0, 0],
            fontSize: 10,
            color: '#666666'
          };
        }
      };

      // 各投稿をPDFに追加
      for (let i = 0; i < selectedPostsData.length; i++) {
        const post = selectedPostsData[i];
        updateLoadingProgress(`作品を追加中... (${i + 1}/${selectedPostsData.length})`);
        
        if (i > 0) {
          // ページブレーク
          docDefinition.content.push({ text: '', pageBreak: 'before' });
        }
        
        // タイトル
        docDefinition.content.push({
          text: post.title || 'Untitled',
          fontSize: 24,
          bold: true,
          margin: [0, 0, 0, 15]
        });
        
        // クライアント名
        docDefinition.content.push({
          text: post.client_name || '',
          fontSize: 16,
          color: '#505050',
          margin: [0, 0, 0, 15]
        });
        
        // タグ
        if (post.tags && post.tags.length > 0) {
          docDefinition.content.push({
            text: 'Tags: ' + post.tags.join(', '),
            fontSize: 12,
            color: '#646464',
            margin: [0, 0, 0, 10]
          });
        }
        
        // 説明文
        if (post.description) {
          docDefinition.content.push({
            text: post.description,
            fontSize: 12,
            margin: [0, 0, 0, 20],
            lineHeight: 1.5
          });
        }
        
        // 画像を追加
        if (post.gallery_images && post.gallery_images.length > 0) {
          for (let j = 0; j < Math.min(2, post.gallery_images.length); j++) {
            const image = post.gallery_images[j];
            try {
              // 縦長画像の場合はサムネイルを使用
              const imagePath = image.is_vertical && image.thumbnail ? image.thumbnail : image.path;
              const imageData = await loadImageAsBase64(imagePath);
              
              if (imageData) {
                docDefinition.content.push({
                  image: imageData,
                  width: 400,
                  alignment: 'center',
                  margin: [0, 10, 0, 5]
                });
                
                // キャプション
                if (image.caption) {
                  docDefinition.content.push({
                    text: image.caption,
                    fontSize: 10,
                    color: '#646464',
                    alignment: 'center',
                    margin: [0, 0, 0, 10]
                  });
                }
              }
            } catch (error) {
              console.error('画像読み込みエラー:', error);
            }
          }
        }
      }

      // PDFを生成
      const pdf = pdfMake.createPdf(docDefinition);
      
      // PDFをダウンロード
      const date = new Date().toISOString().split('T')[0];
      pdf.download(`STARTEND_Portfolio_${date}.pdf`);

      // 完了後の処理
      hideLoadingOverlay();
      clearSelection();
      togglePdfMode();
      
    } catch (error) {
      console.error('PDF生成エラー:', error);
      hideLoadingOverlay();
      alert('PDF生成中にエラーが発生しました。');
    }
  }

  // 画像をBase64として読み込む
  function loadImageAsBase64(imagePath) {
    return new Promise((resolve, reject) => {
      const img = new Image();
      img.crossOrigin = 'anonymous';
      
      img.onload = () => {
        const canvas = document.createElement('canvas');
        
        // 最大サイズを制限
        const maxWidth = 800;
        const maxHeight = 600;
        let width = img.width;
        let height = img.height;
        
        if (width > maxWidth || height > maxHeight) {
          const aspectRatio = width / height;
          if (width > height) {
            width = maxWidth;
            height = width / aspectRatio;
          } else {
            height = maxHeight;
            width = height * aspectRatio;
          }
        }
        
        canvas.width = width;
        canvas.height = height;
        
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, width, height);
        
        try {
          const dataURL = canvas.toDataURL('image/jpeg', 0.7);
          resolve(dataURL);
        } catch (error) {
          reject(error);
        }
      };
      
      img.onerror = () => {
        // CORSエラーの場合はプロキシ経由で再試行
        const proxyUrl = `/portfolio/api/pdf-image-proxy.php?path=${encodeURIComponent(imagePath)}`;
        const proxyImg = new Image();
        proxyImg.crossOrigin = 'anonymous';
        
        proxyImg.onload = () => {
          const canvas = document.createElement('canvas');
          
          const maxWidth = 800;
          const maxHeight = 600;
          let width = proxyImg.width;
          let height = proxyImg.height;
          
          if (width > maxWidth || height > maxHeight) {
            const aspectRatio = width / height;
            if (width > height) {
              width = maxWidth;
              height = width / aspectRatio;
            } else {
              height = maxHeight;
              width = height * aspectRatio;
            }
          }
          
          canvas.width = width;
          canvas.height = height;
          
          const ctx = canvas.getContext('2d');
          ctx.drawImage(proxyImg, 0, 0, width, height);
          
          try {
            const dataURL = canvas.toDataURL('image/jpeg', 0.7);
            resolve(dataURL);
          } catch (error) {
            reject(error);
          }
        };
        
        proxyImg.onerror = () => {
          reject(new Error('画像の読み込みに失敗しました'));
        };
        
        proxyImg.src = proxyUrl;
      };
      
      img.src = imagePath;
    });
  }

  // ローディングオーバーレイを表示
  function showLoadingOverlay(message) {
    const overlay = document.createElement('div');
    overlay.className = 'pdf-loading-overlay';
    overlay.innerHTML = `
      <div class="pdf-loading-content">
        <div class="pdf-loading-spinner"></div>
        <div class="pdf-loading-text">${message}</div>
        <div class="pdf-loading-progress"></div>
      </div>
    `;
    document.body.appendChild(overlay);
  }

  // ローディング進捗を更新
  function updateLoadingProgress(message) {
    const progress = document.querySelector('.pdf-loading-progress');
    if (progress) {
      progress.textContent = message;
    }
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