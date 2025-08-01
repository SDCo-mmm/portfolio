document.addEventListener("DOMContentLoaded", async () => {
  const postDetailContainer = document.getElementById("postDetail");
  
  // URLから投稿IDを取得
  const urlParams = new URLSearchParams(window.location.search);
  const postId = urlParams.get('id');

  if (!postId) {
    postDetailContainer.innerHTML = "<p>作品IDが指定されていません。</p>";
    return;
  }

  // 投稿データを取得
  const fetchPostDetail = async () => {
    postDetailContainer.innerHTML = "<p>作品詳細を読み込み中...</p>";
    try {
      // APIパス修正済み: /portfolio/api/get_posts.php
      const response = await fetch(`/portfolio/api/get_posts.php`);
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      const posts = await response.json();
      
      // 該当する投稿IDのデータを検索
      const post = posts.find(p => p.id === postId);

      if (post) {
        displayPostDetail(post);
      } else {
        postDetailContainer.innerHTML = "<p>指定された作品が見つかりません。</p>";
      }
    } catch (error) {
      console.error("作品詳細の読み込み中にエラーが発生しました:", error);
      postDetailContainer.innerHTML = "<p>作品詳細の読み込みに失敗しました。</p>";
    }
  };

  // 投稿詳細を表示する関数
  const displayPostDetail = (post) => {
    const galleryHtml = post.gallery_images.map(image => `
      <div class="gallery-item">
        <img src="${image.path}" alt="${image.caption || post.title}" loading="lazy">
        ${image.caption ? `<p>${image.caption}</p>` : ''}
      </div>
    `).join('');

    postDetailContainer.innerHTML = `
      <div class="post-detail-content">
        <h1>${post.title}</h1>
        <div class="client-info">
          ${post.client_logo ? `<img src="${post.client_logo}" alt="${post.client_name} ロゴ" class="client-logo">` : ''}
          <p class="client-name">${post.client_name}</p>
        </div>
        <p class="description">${post.description}</p>
        <div class="gallery-grid">
          ${galleryHtml}
        </div>
        <a href="/portfolio/index.html" class="back-link">&lt; 作品一覧へ戻る</a>
      </div>
    `;
  };

  // 初期ロード
  fetchPostDetail();
});