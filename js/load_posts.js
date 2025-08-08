// load_posts.js（タグフィルタリング機能付き版 - レイアウト改善）

document.addEventListener("DOMContentLoaded", () => {
  const postGrid = document.getElementById("postGrid");
  const sortSelect = document.getElementById("sortSelect");
  
  // ★★★ タグフィルタリング用の新しい要素 ★★★
  const tagFilterContainer = document.getElementById("tagFilterContainer");
  const clearFiltersBtn = document.getElementById("clearFiltersBtn");

  // 無限スクロール用の状態管理
  let currentPage = 1;
  let totalPages = 1;
  let isLoading = false;
  let currentSort = 'newest';
  let activeFilters = new Set(); // アクティブなタグフィルター
  let allPosts = []; // フィルタリング用の全投稿データ
  let filteredPosts = []; // フィルタリング後の投稿データ
  const postsPerPage = 12;

  // ★★★ すべての投稿データを一度に取得する関数 ★★★
  const fetchAllPosts = async () => {
    try {
      const response = await fetch(`/portfolio/api/get_posts.php?page=0&limit=0&sort_by=${currentSort}`);
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const posts = await response.json();
      allPosts = Array.isArray(posts) ? posts : [];
      
      // タグフィルターUIを初期化
      initializeTagFilters();
      
      // 初期フィルタリング
      applyFilters();
      
    } catch (error) {
      console.error("投稿の読み込み中にエラーが発生しました:", error);
      postGrid.innerHTML = "<p>投稿の読み込みに失敗しました。</p>";
    }
  };

  // ★★★ タグフィルターUIの初期化（1行レイアウト対応） ★★★
  const initializeTagFilters = () => {
    // 全投稿からタグを抽出
    const tagCounts = new Map();
    
    allPosts.forEach(post => {
      if (post.tags && Array.isArray(post.tags)) {
        post.tags.forEach(tag => {
          tagCounts.set(tag, (tagCounts.get(tag) || 0) + 1);
        });
      }
    });
    
    // タグを使用頻度順でソート
    const sortedTags = Array.from(tagCounts.entries())
      .sort((a, b) => b[1] - a[1]) // 使用数で降順ソート
      .map(entry => entry[0]);
    
    // タグフィルターボタンを生成
    if (tagFilterContainer && sortedTags.length > 0) {
      const tagButtonsHtml = sortedTags.map(tag => 
        `<button class="tag-filter-btn" data-tag="${tag}">
          <span class="tag-name">${tag}</span>
          <span class="tag-count">(${tagCounts.get(tag)})</span>
        </button>`
      ).join('');
      
      // ★★★ 1行レイアウト用のHTML構造（クリアボタンを右側に配置） ★★★
      tagFilterContainer.innerHTML = `
        <div class="filter-header">
          <div class="tag-filter-title">Filter by tags:</div>
          <div class="tag-filter-buttons">
            ${tagButtonsHtml}
          </div>
          <button id="clearFiltersBtn" class="clear-filters-btn" style="display: none; 
            appearance: none !important; 
            -webkit-appearance: none !important; 
            -moz-appearance: none !important;
            background-color: #f8f9fa !important;
            color: #6c757d !important;
            border: 1px solid #6c757d !important;
            padding: 4px 12px !important;
            border-radius: 6px !important;
            font-size: 0.85rem !important;
            font-family: 'M PLUS 1p', 'Montserrat', sans-serif !important;
            cursor: pointer !important;
            font-weight: 500 !important;
            white-space: nowrap !important;
            height: 30px !important;
            box-sizing: border-box !important;
            display: none !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 4px !important;
            text-decoration: none !important;
            outline: none !important;
            text-align: center !important;
            line-height: normal !important;
            text-transform: none !important;
            text-indent: 0 !important;
            text-shadow: none !important;
            letter-spacing: normal !important;
            word-spacing: normal !important;
            border-width: 1px !important;
            border-style: solid !important;
            border-image: none !important;
            margin: 0 !important;">
            Clear filters
          </button>
        </div>
      `;
      
      // タグフィルターボタンのイベントリスナー設定
      tagFilterContainer.querySelectorAll('.tag-filter-btn').forEach(btn => {
        btn.addEventListener('click', () => toggleTagFilter(btn.dataset.tag, btn));
      });
      
      // クリアボタンのイベントリスナー設定
      const newClearBtn = document.getElementById('clearFiltersBtn');
      if (newClearBtn) {
        newClearBtn.addEventListener('click', clearAllFilters);
      }
      
      // ★★★ 初期状態でクリアボタンを確実に非表示に ★★★
      updateClearFiltersButton();
    }
  };

  // ★★★ タグフィルターの切り替え ★★★
  const toggleTagFilter = (tag, buttonElement) => {
    if (activeFilters.has(tag)) {
      // フィルターを削除
      activeFilters.delete(tag);
      buttonElement.classList.remove('active');
    } else {
      // フィルターを追加
      activeFilters.add(tag);
      buttonElement.classList.add('active');
    }
    
    // クリアボタンの表示/非表示
    updateClearFiltersButton();
    
    // フィルタリングを適用
    applyFilters();
  };

  // ★★★ フィルタリングを適用する関数 ★★★
  const applyFilters = () => {
    if (activeFilters.size === 0) {
      // フィルターが無い場合は全投稿を表示
      filteredPosts = [...allPosts];
    } else {
      // アクティブなタグフィルターに基づいてフィルタリング
      filteredPosts = allPosts.filter(post => {
        if (!post.tags || !Array.isArray(post.tags)) return false;
        
        // 選択されたタグのいずれかを含む投稿を表示（OR検索）
        return Array.from(activeFilters).some(filterTag => 
          post.tags.includes(filterTag)
        );
      });
    }
    
    // ソート適用
    applySorting();
    
    // ページネーションをリセット
    currentPage = 1;
    totalPages = Math.ceil(filteredPosts.length / postsPerPage);
    
    console.log(`Total posts: ${filteredPosts.length}, Total pages: ${totalPages}, Posts per page: ${postsPerPage}`);
    
    // 表示を更新
    displayFilteredPosts(true);
    
    // ★★★ 自動読み込み防止：初期表示後は手動でスクロールチェックを無効化 ★★★
    // setTimeout(checkScrollTrigger, 100); // この行をコメントアウト
  };

  // ★★★ ソートを適用する関数 ★★★
  const applySorting = () => {
    if (currentSort === 'newest') {
      filteredPosts.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
    } else if (currentSort === 'client') {
      filteredPosts.sort((a, b) => a.client_name.localeCompare(b.client_name));
    }
  };

  // ★★★ フィルタリング後の投稿を表示（無限スクロール修正版） ★★★
  const displayFilteredPosts = (reset = false) => {
    if (reset) {
      postGrid.innerHTML = "";
    }

    const startIndex = (currentPage - 1) * postsPerPage;
    const endIndex = startIndex + postsPerPage;
    const postsToDisplay = filteredPosts.slice(startIndex, endIndex);

    if (postsToDisplay.length === 0 && reset) {
      if (activeFilters.size > 0) {
        postGrid.innerHTML = `
          <div class="no-results-message">
            <p>選択されたタグに該当する作品が見つかりません。</p>
            <p>フィルターを変更するか、クリアしてください。</p>
          </div>
        `;
      } else {
        postGrid.innerHTML = "<p>まだ作品がありません。</p>";
      }
      return;
    }

    postsToDisplay.forEach(post => {
      if (!post.client_logo) return;

      const postCard = document.createElement("a");
      postCard.href = `/portfolio/post.html?id=${post.id}`; 
      postCard.classList.add("post-item"); 
      postCard.setAttribute('data-client', post.client_name);

      // ★★★ タグ情報をdata属性に追加 ★★★
      if (post.tags && post.tags.length > 0) {
        postCard.setAttribute('data-tags', post.tags.join(','));
      }

      postCard.innerHTML = `
          <div class="logo-container">
            <img src="${post.client_logo}" alt="${post.client_name} Logo" class="client-logo-thumbnail" />
          </div>
          <div class="post-item-content"> 
            <h3>${post.title}</h3>
            <p>${post.client_name}</p>
            ${post.tags && post.tags.length > 0 ? 
              `<div class="post-tags">
                ${post.tags.map(tag => `<span class="post-tag">${tag}</span>`).join('')}
               </div>` : ''
            }
          </div>
      `;
      
      // フェードイン効果
      postCard.style.opacity = '0';
      postCard.style.transform = 'translateY(20px)';
      postCard.style.transition = 'all 0.4s ease';
      
      postGrid.appendChild(postCard);
      
      setTimeout(() => {
        postCard.style.opacity = '1';
        postCard.style.transform = 'translateY(0)';
      }, 50);
    });

    // ★★★ 無限スクロール用のボタン更新を追加 ★★★
    updateLoadMoreButton();
  };

  // ★★★ クリアボタンの表示状態を更新 ★★★
  const updateClearFiltersButton = () => {
    const clearBtn = document.getElementById('clearFiltersBtn');
    if (clearBtn) {
      if (activeFilters.size > 0) {
        clearBtn.style.display = 'inline-flex';
        clearBtn.style.alignItems = 'center';
        clearBtn.style.justifyContent = 'center';
        clearBtn.textContent = `Clear filters (${activeFilters.size} selected)`;
        
        // ホバー効果をJavaScriptで追加
        clearBtn.onmouseenter = () => {
          clearBtn.style.backgroundColor = '#e9ecef';
          clearBtn.style.borderColor = '#5a6268';
          clearBtn.style.color = '#495057';
          clearBtn.style.transform = 'translateY(-1px)';
        };
        clearBtn.onmouseleave = () => {
          clearBtn.style.backgroundColor = '#f8f9fa';
          clearBtn.style.borderColor = '#6c757d';
          clearBtn.style.color = '#6c757d';
          clearBtn.style.transform = 'translateY(0)';
        };
      } else {
        clearBtn.style.display = 'none';
      }
    }
  };

  // ★★★ すべてのフィルターをクリア ★★★
  const clearAllFilters = () => {
    activeFilters.clear();
    
    // すべてのフィルターボタンを非アクティブに
    document.querySelectorAll('.tag-filter-btn').forEach(btn => {
      btn.classList.remove('active');
    });
    
    updateClearFiltersButton();
    applyFilters();
  };

  // ★★★ スクロール位置をチェックして次のページを読み込む ★★★
  const checkScrollTrigger = () => {
    const scrollPosition = window.scrollY + window.innerHeight;
    const documentHeight = document.documentElement.scrollHeight;
    const triggerPoint = documentHeight - 200; // さらに短く調整
    
    // ★★★ スクロールが実際に発生している場合のみトリガー ★★★
    const hasActuallyScrolled = window.scrollY > 100; // 100px以上スクロールした場合のみ
    
    console.log(`Scroll check - Position: ${scrollPosition}, Height: ${documentHeight}, Trigger: ${triggerPoint}, ScrollY: ${window.scrollY}, Has scrolled: ${hasActuallyScrolled}, Can load: ${currentPage < totalPages && !isLoading}`);

    if (hasActuallyScrolled && scrollPosition >= triggerPoint && currentPage < totalPages && !isLoading) {
      console.log('Triggering next page load');
      loadNextPage();
    }
  };

  // ★★★ 次のページを読み込む ★★★
  const loadNextPage = () => {
    if (currentPage < totalPages) {
      console.log(`Loading page ${currentPage + 1} of ${totalPages}`);
      currentPage++;
      isLoading = true;
      
      // ローディング表示
      const loadingIndicator = document.getElementById('loadingIndicator');
      if (loadingIndicator) {
        loadingIndicator.style.display = 'block';
      }
      
      // 少し遅延を入れてから表示（UX向上）
      setTimeout(() => {
        displayFilteredPosts(false);
        isLoading = false;
        
        if (loadingIndicator) {
          loadingIndicator.style.display = 'none';
        }
        
        updateLoadMoreButton();
        
        // ★★★ 読み込み後の自動チェックは削除 ★★★
        // setTimeout(checkScrollTrigger, 200); // この行をコメントアウト
      }, 200);
    }
  };

  // ローディングインジケーターを作成
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

  const loadingIndicator = createLoadingIndicator();
  postGrid.parentNode.insertBefore(loadingIndicator, postGrid.nextSibling);

  // "もっと見る"ボタンを作成
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

  const loadMoreButton = createLoadMoreButton();
  postGrid.parentNode.insertBefore(loadMoreButton, loadingIndicator);

  // ボタンの表示/非表示を制御
  const updateLoadMoreButton = () => {
    console.log(`Current page: ${currentPage}, Total pages: ${totalPages}`); // デバッグログ
    
    if (currentPage < totalPages) {
      loadMoreButton.style.display = 'block';
      loadMoreButton.textContent = `もっと見る (${currentPage}/${totalPages}ページ目)`; // ページ情報を表示
    } else {
      loadMoreButton.style.display = 'none';
    }
    
    // 大画面でスクロールできない場合の対策
    setTimeout(() => {
      const hasVerticalScroll = document.documentElement.scrollHeight > window.innerHeight;
      console.log(`Has vertical scroll: ${hasVerticalScroll}`); // デバッグログ
      if (!hasVerticalScroll && currentPage < totalPages) {
        loadMoreButton.classList.add('no-scroll-highlight');
      } else {
        loadMoreButton.classList.remove('no-scroll-highlight');
      }
    }, 100);
  };

  // ★★★ ソート変更時の処理 ★★★
  const handleSortChange = () => {
    const newSort = sortSelect.value;
    if (newSort !== currentSort) {
      currentSort = newSort;
      applyFilters(); // ソート変更時もフィルタリングを再適用
    }
  };

  // ★★★ スクロールイベントリスナー ★★★
  let scrollTimeout;
  window.addEventListener('scroll', () => {
    clearTimeout(scrollTimeout);
    scrollTimeout = setTimeout(() => {
      console.log('Scroll event triggered'); // デバッグログ
      checkScrollTrigger();
    }, 50); // 100msから50msに短縮してレスポンシブ性向上
  });

  // ★★★ イベントリスナーの設定 ★★★
  if (sortSelect) {
    sortSelect.addEventListener("change", handleSortChange);
  }

  // ★★★ 初期ロード ★★★
  fetchAllPosts();
});