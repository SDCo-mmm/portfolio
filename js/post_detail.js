document.addEventListener("DOMContentLoaded", async () => {
  const postDetailContainer = document.getElementById("postDetail");
  
  // URLから投稿IDを取得
  const urlParams = new URLSearchParams(window.location.search);
  const postId = urlParams.get('id');

  if (!postId) {
    postDetailContainer.innerHTML = "<p>作品IDが指定されていません。</p>";
    return;
  }

  // ★★★ モーダル機能の初期化 ★★★
  const initializeModal = () => {
    // 既存のモーダルがあれば削除
    const existingModal = document.getElementById('imageModal');
    if (existingModal) {
      existingModal.remove();
    }

    // モーダル要素を作成
    const modal = document.createElement('div');
    modal.id = 'imageModal';
    modal.className = 'image-modal';
    modal.innerHTML = `
      <span class="close">&times;</span>
      <img id="modalImage" src="" alt="">
      <div class="modal-caption" id="modalCaption"></div>
    `;
    document.body.appendChild(modal);

    const modalImg = document.getElementById('modalImage');
    const modalCaption = document.getElementById('modalCaption');
    const closeBtn = modal.querySelector('.close');

    // モーダルを表示する関数
    window.showImageModal = (imageSrc, caption) => {
      modalImg.src = imageSrc;
      modalCaption.textContent = caption || '';
      modalCaption.style.display = caption ? 'block' : 'none';
      modal.classList.add('show');
      modal.style.display = 'flex';
      document.body.style.overflow = 'hidden'; // スクロール無効化
    };

    // モーダルを閉じる関数
    const closeModal = () => {
      modal.classList.remove('show');
      modal.style.display = 'none';
      document.body.style.overflow = 'auto'; // スクロール有効化
    };

    // イベントリスナー設定
    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        closeModal();
      }
    });

    // ESCキーでモーダルを閉じる
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && modal.style.display === 'flex') {
        closeModal();
      }
    });
  };

  // ★★★ タグ表示HTML生成関数 ★★★
  const createTagsDisplayHtml = (tags) => {
    if (!tags || !Array.isArray(tags) || tags.length === 0) {
      return '';
    }

    const tagsHtml = tags.map(tag => `<span class="post-detail-tag">${tag}</span>`).join('');
    
    return `
      <div class="post-detail-tags">
        ${tagsHtml}
      </div>
    `;
  };

  // 投稿データを取得
  const fetchPostDetail = async () => {
    postDetailContainer.innerHTML = "<p>作品詳細を読み込み中...</p>";
    try {
      const response = await fetch(`/portfolio/api/get_posts.php`);
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      const posts = await response.json();
      
      // 該当する投稿IDのデータを検索
      const post = posts.find(p => p.id === postId);

      if (post) {
        displayPostDetail(post);
        initializeModal(); // モーダル機能を初期化
      } else {
        postDetailContainer.innerHTML = "<p>指定された作品が見つかりません。</p>";
      }
    } catch (error) {
      console.error("作品詳細の読み込み中にエラーが発生しました:", error);
      postDetailContainer.innerHTML = "<p>作品詳細の読み込みに失敗しました。</p>";
    }
  };

  // ★★★ 投稿詳細を表示する関数（タグ表示対応版） ★★★
  const displayPostDetail = (post) => {
    const galleryHtml = post.gallery_images.map(image => {
      // ★★★ 縦長画像の場合はサムネイルを表示、そうでなければオリジナルを表示 ★★★
      const displaySrc = image.is_vertical && image.thumbnail ? image.thumbnail : image.path;
      const isVertical = image.is_vertical || false;
      
      return `
        <div class="gallery-item" data-vertical="${isVertical}">
          <img 
            src="${displaySrc}" 
            alt="${image.caption || post.title}" 
            loading="lazy"
            ${isVertical ? `onclick="showImageModal('${image.path}', '${image.caption || ''}')"` : ''}
            data-original="${image.path}"
            data-caption="${image.caption || ''}"
          >
          ${image.caption ? `<p>${image.caption}</p>` : ''}
        </div>
      `;
    }).join('');

    // ★★★ タグ表示を含む詳細ページHTML ★★★
    postDetailContainer.innerHTML = `
      <div class="post-detail-content">
        ${createTagsDisplayHtml(post.tags)}
        <h1>${post.title}</h1>
        <div class="client-info">
          ${post.client_logo ? `<img src="${post.client_logo}" alt="${post.client_name} ロゴ" class="client-logo">` : ''}
          <p class="client-name">${post.client_name}</p>
        </div>
        <p class="description">${post.description}</p>
        <div class="gallery-grid">
          ${galleryHtml}
        </div>
        <div class="back-link-container">
          <a href="/portfolio/index.html" class="back-link">&lt; 作品一覧へ戻る</a>
        </div>
      </div>
    `;

    // ★★★ 縦長画像以外でもクリック可能にする（オプション） ★★★
    const allImages = postDetailContainer.querySelectorAll('.gallery-item img');
    allImages.forEach(img => {
      if (!img.getAttribute('onclick')) {
        // 縦長画像でない場合でも、クリックで拡大表示可能
        img.style.cursor = 'pointer';
        img.addEventListener('click', () => {
          const originalSrc = img.getAttribute('data-original') || img.src;
          const caption = img.getAttribute('data-caption') || img.alt;
          showImageModal(originalSrc, caption);
        });
      }
    });
  };

  // 初期ロード
  fetchPostDetail();
});