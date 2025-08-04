document.addEventListener("DOMContentLoaded", () => {
  const postGrid = document.getElementById("postGrid");
  const sortSelect = document.getElementById("sortSelect");

  // ★★★ 無限スクロール用の状態管理 ★★★
  let currentPage = 1;
  let totalPages = 1;
  let isLoading = false;
  let currentSort = 'newest';
  const postsPerPage = 12; // 1ページあたりの表示件数

  // ★★★ ローディングインジケーターを作成 ★★★
  const createLoadingIndicator = () => {
    const loading = document.createElement('div');
    loading.id = 'loadingIndicator';
    loading.className = 'loading-indicator';
    loading.innerHTML = `
      <div class="loading-spinner"></div>
      <p>作品を読み込み中...</p>
    `;
    loading.style.display = 'none';
    return loading;
  };

  // ローディングインジケーターをページに追加
  const loadingIndicator = createLoadingIndicator();
  postGrid.parentNode.insertBefore(loadingIndicator, postGrid.nextSibling);

  // ★★★ 投稿データを取得する関数 ★★★
  const fetchPosts = async (page = 1, sortBy = 'newest', reset = false) => {
    if (isLoading) return; // 既に読み込み中の場合は処理をスキップ
    
    isLoading = true;
    loadingIndicator.style.display = 'block';

    try {
      const response = await fetch(`/portfolio/api/get_posts.php?page=${page}&limit=${postsPerPage}&sort_by=${sortBy}`);
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const data = await response.json();
      
      // ページネーション情報を更新
      currentPage = data.pagination.current_page;
      totalPages = data.pagination.total_pages;
      
      // 投稿を表示
      displayPosts(data.posts, reset);
      
      // 無限スクロールのトリガーをチェック
      checkScrollTrigger();
      
    } catch (error) {
      console.error("投稿の読み込み中にエラーが発生しました:", error);
      if (reset) {
        postGrid.innerHTML = "<p>投稿の読み込みに失敗しました。</p>";
      }
    } finally {
      isLoading = false;
      loadingIndicator.style.display = 'none';
    }
  };

  // ★★★ 投稿を表示する関数 ★★★
  const displayPosts = (posts, reset = false) => {
    if (reset) {
      postGrid.innerHTML = ""; // 既存の内容をクリア
    }

    if (posts.length === 0 && reset) {
      postGrid.innerHTML = "<p>まだ作品がありません。</p>";
      return;
    }

    posts.forEach(post => {
      // クライアントロゴが存在しない場合は表示しない
      if (!post.client_logo) {
        return; // この投稿はスキップ
      }

      const postCard = document.createElement("a");
      postCard.href = `/portfolio/post.html?id=${post.id}`; 
      postCard.classList.add("post-item"); 
      postCard.setAttribute('data-client', post.client_name);

      // ★★★ 投稿カードのHTML構造 ★★★
      postCard.innerHTML = `
          <div class="logo-container">
            <img src="${post.client_logo}" alt="${post.client_name} Logo" class="client-logo-thumbnail" />
          </div>
          <div class="post-item-content"> 
            <h3>${post.title}</h3>
            <p>${post.client_name}</p>
          </div>
      `;
      
      // ★★★ フェードイン効果を追加 ★★★
      postCard.style.opacity = '0';
      postCard.style.transform = 'translateY(20px)';
      postCard.style.transition = 'all 0.4s ease';
      
      postGrid.appendChild(postCard);
      
      // アニメーション実行
      setTimeout(() => {
        postCard.style.opacity = '1';
        postCard.style.transform = 'translateY(0)';
      }, 50);
    });
  };

  // ★★★ スクロール位置をチェックして次のページを読み込む ★★★
  const checkScrollTrigger = () => {
    const scrollPosition = window.scrollY + window.innerHeight;
    const documentHeight = document.documentElement.scrollHeight;
    const triggerPoint = documentHeight - 1000; // 1000px手前でトリガー

    if (scrollPosition >= triggerPoint && currentPage < totalPages && !isLoading) {
      loadNextPage();
    }
  };

  // ★★★ 次のページを読み込む ★★★
  const loadNextPage = () => {
    if (currentPage < totalPages) {
      fetchPostsWithButtonUpdate(currentPage + 1, currentSort, false);
    }
  };

  // ★★★ ソート変更時の処理 ★★★
  const handleSortChange = () => {
    const newSort = sortSelect.value;
    if (newSort !== currentSort) {
      currentSort = newSort;
      currentPage = 1;
      totalPages = 1;
      fetchPostsWithButtonUpdate(1, currentSort, true); // リセットして最初から読み込み
    }
  };

  // ★★★ スクロールイベントリスナー ★★★
  let scrollTimeout;
  window.addEventListener('scroll', () => {
    // スクロールイベントをデバウンス（パフォーマンス向上）
    clearTimeout(scrollTimeout);
    scrollTimeout = setTimeout(checkScrollTrigger, 100);
  });

  // ★★★ ソート選択の変更イベント ★★★
  sortSelect.addEventListener("change", handleSortChange);

  // ★★★ "もっと見る"ボタンを手動で追加（オプション） ★★★
  const createLoadMoreButton = () => {
    const button = document.createElement('button');
    button.id = 'loadMoreButton';
    button.className = 'load-more-button';
    button.textContent = 'もっと見る';
    button.style.display = 'none';
    
    button.addEventListener('click', () => {
      if (currentPage < totalPages && !isLoading) {
        loadNextPage();
      }
    });
    
    return button;
  };

  // "もっと見る"ボタンを追加
  const loadMoreButton = createLoadMoreButton();
  postGrid.parentNode.insertBefore(loadMoreButton, loadingIndicator);

  // ★★★ ボタンの表示/非表示を制御 ★★★
  const updateLoadMoreButton = () => {
    if (currentPage < totalPages) {
      loadMoreButton.style.display = 'block';
      loadMoreButton.textContent = 'もっと見る';
    } else {
      loadMoreButton.style.display = 'none';
    }
  };

  // ★★★ 初期表示時にもボタンを表示チェック ★★★
  const originalFetchPosts = fetchPosts;
  const fetchPostsWithButtonUpdate = async (page = 1, sortBy = 'newest', reset = false) => {
    await originalFetchPosts(page, sortBy, reset);
    updateLoadMoreButton();
    
    // ★★★ 大画面でスクロールできない場合の対策 ★★★
    setTimeout(() => {
      const hasVerticalScroll = document.documentElement.scrollHeight > window.innerHeight;
      if (!hasVerticalScroll && currentPage < totalPages) {
        // スクロールバーがない場合はボタンを強調表示
        loadMoreButton.classList.add('no-scroll-highlight');
      } else {
        loadMoreButton.classList.remove('no-scroll-highlight');
      }
    }, 100);
  };

  // ★★★ 初期ロード ★★★
  fetchPostsWithButtonUpdate(1, currentSort, true);
});