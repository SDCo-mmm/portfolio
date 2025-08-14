// load_posts.js（完全修正版 - タグオーバーレイ修正・フォントサイズ対応）

console.log('load_posts.js loaded successfully');

document.addEventListener("DOMContentLoaded", () => {
  console.log('DOMContentLoaded event fired');
  
  const postGrid = document.getElementById("postGrid");
  const sortSelect = document.getElementById("sortSelect");
  const tagFilterContainer = document.getElementById("tagFilterContainer");

  let currentPage = 1;
  let totalPages = 1;
  let isLoading = false;
  let currentSort = 'newest';
  let activeFilters = new Set();
  let allPosts = [];
  let filteredPosts = [];
  const postsPerPage = 12;

  // ★★★ タグ制限と+N more表示機能（修正版） ★★★
  const createLimitedTagsHtml = (tags, maxVisibleTags = 4) => {
    if (!tags || tags.length === 0) return '';
    
    // ★★★ スマホでは全タグ表示（横スクロール対応） ★★★
    const isMobile = window.innerWidth <= 480;
    console.log(`Screen width: ${window.innerWidth}, isMobile: ${isMobile}, tags count: ${tags.length}`);
    
    if (isMobile) {
      console.log('Mobile detected: showing all tags for horizontal scroll');
      return tags.map(tag => `<span class="post-tag">${tag}</span>`).join('');
    }
    
    // タブレット・デスクトップでは制限表示
    console.log('Desktop/Tablet: showing limited tags with +N more');
    const visibleTags = tags.slice(0, maxVisibleTags);
    const hiddenCount = tags.length - maxVisibleTags;
    
    let tagsHtml = visibleTags.map(tag => `<span class="post-tag">${tag}</span>`).join('');
    
    if (hiddenCount > 0) {
      tagsHtml += `<span class="post-tag more-tag">+${hiddenCount} more</span>`;
    }
    
    return tagsHtml;
  };

  // ★★★ オーバーレイHTML生成（修正版：タグが制限を超える場合のみ） ★★★
  const createTagOverlayHtml = (tags, title) => {
    const isMobile = window.innerWidth <= 480;
    if (isMobile) return ''; // スマホでは常に無効
    
    // デバイス別のタグ制限数を設定
    const maxTags = window.innerWidth <= 768 ? 3 : 4;
    
    // ★★★ 重要な修正：タグ数が制限を超える場合のみオーバーレイを表示 ★★★
    if (!tags || tags.length <= maxTags) return ''; // 制限以下なら不要
    
    const allTagsHtml = tags.map(tag => `<span class="post-tag">${tag}</span>`).join('');
    
    return `
      <div class="tag-overlay">
        <div class="tag-overlay-content">
          <div class="tag-overlay-title">All Tags</div>
          <div class="tag-overlay-tags">
            ${allTagsHtml}
          </div>
        </div>
      </div>
    `;
  };

  // ★★★ 投稿表示（+N more対応版） ★★★
  const displayFilteredPosts = (reset = false) => {
    console.log('displayFilteredPosts called, reset:', reset);
    
    if (reset) {
      postGrid.innerHTML = "";
    }

    const startIndex = (currentPage - 1) * postsPerPage;
    const endIndex = startIndex + postsPerPage;
    const postsToDisplay = filteredPosts.slice(startIndex, endIndex);

    console.log('Posts to display:', postsToDisplay.length);

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

    postsToDisplay.forEach((post, index) => {
      console.log(`Processing post ${index + 1}:`, post.title);
      
      if (!post.client_logo) {
        console.log('Skipping post due to no client_logo');
        return;
      }

      const postCard = document.createElement("a");
      postCard.href = `/portfolio/post.html?id=${post.id}`; 
      postCard.classList.add("post-item"); 
      postCard.setAttribute('data-client', post.client_name);

      console.log(`Creating post card for: ${post.title}`);

      if (post.tags && post.tags.length > 0) {
        postCard.setAttribute('data-tags', post.tags.join(','));
        console.log(`Post tags: ${post.tags.join(', ')}`);
      }

      // ★★★ デバイス別タグ制限数を設定 ★★★
      const maxTags = window.innerWidth <= 480 ? 3 : window.innerWidth <= 768 ? 3 : 4;
      
      postCard.innerHTML = `
          <div class="logo-container">
            <img src="${post.client_logo}" alt="${post.client_name} Logo" class="client-logo-thumbnail" />
          </div>
          <div class="post-item-content"> 
            <p>${post.client_name}</p>
            <h3>${post.title}</h3>
            ${post.tags && post.tags.length > 0 ? 
              `<div class="post-tags">
                ${createLimitedTagsHtml(post.tags, maxTags)}
               </div>` : ''
            }
          </div>
          ${createTagOverlayHtml(post.tags, post.title)}
      `;
      
      console.log('Post card HTML generated');
      
      postCard.style.opacity = '0';
      postCard.style.transform = 'translateY(20px)';
      postCard.style.transition = 'all 0.4s ease';
      
      postGrid.appendChild(postCard);
      console.log('Post card added to grid');
      
      setTimeout(() => {
        postCard.style.opacity = '1';
        postCard.style.transform = 'translateY(0)';
        
        // ★★★ スマホでタグ横スクロールを強制適用 ★★★
        if (window.innerWidth <= 480) {
          const tagContainer = postCard.querySelector('.post-tags');
          if (tagContainer) {
            console.log('Applying mobile horizontal scroll styles...');
            tagContainer.style.display = 'flex';
            tagContainer.style.flexWrap = 'nowrap';
            tagContainer.style.overflowX = 'scroll';
            tagContainer.style.overflowY = 'hidden';
            tagContainer.style.webkitOverflowScrolling = 'touch';
            tagContainer.style.scrollbarWidth = 'none';
            tagContainer.style.msOverflowStyle = 'none';
            tagContainer.style.justifyContent = 'flex-start';
            tagContainer.style.width = '100%';
            tagContainer.style.maxWidth = '100%';
            
            // 各タグにもスタイル適用
            const tags = tagContainer.querySelectorAll('.post-tag');
            tags.forEach(tag => {
              tag.style.flexShrink = '0';
              tag.style.whiteSpace = 'nowrap';
              tag.style.maxWidth = 'none';
              tag.style.minWidth = 'auto';
            });
            
            console.log(`Applied mobile styles to ${tags.length} tags`);
          }
          
          // ★★★ スマホでタイトル・クライアント名の読みやすい制限 ★★★
          const titleElement = postCard.querySelector('h3');
          const clientElement = postCard.querySelector('p');
          
          if (titleElement) {
            titleElement.style.height = '2.2rem';
            titleElement.style.maxHeight = '2.2rem';
            titleElement.style.lineHeight = '1.1';
            titleElement.style.fontSize = '0.85rem';
            titleElement.style.overflow = 'hidden';
            titleElement.style.margin = '0 0 4px 0';
            titleElement.style.padding = '0';
            console.log('Applied readable mobile title styles');
          }
          
          if (clientElement) {
            clientElement.style.height = '2.2rem';
            clientElement.style.maxHeight = '2.2rem';
            clientElement.style.lineHeight = '1.1';
            clientElement.style.fontSize = '0.8rem';
            clientElement.style.overflow = 'hidden';
            clientElement.style.margin = '0 0 4px 0';
            clientElement.style.padding = '0';
            console.log('Applied readable mobile client name styles');
          }
        }
      }, 50);
    });

    updateLoadMoreButton();
  };

  // ★★★ すべての投稿データを取得 ★★★
  const fetchAllPosts = async () => {
    try {
      const response = await fetch(`/portfolio/api/get_posts.php?page=0&limit=0&sort_by=${currentSort}`);
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const posts = await response.json();
      allPosts = Array.isArray(posts) ? posts : [];
      
      initializeTagFilters();
      applyFilters();
      
    } catch (error) {
      console.error("投稿の読み込み中にエラーが発生しました:", error);
      postGrid.innerHTML = "<p>投稿の読み込みに失敗しました。</p>";
    }
  };

  // ★★★ タグフィルターUI初期化 ★★★
  const initializeTagFilters = () => {
    const tagCounts = new Map();
    
    allPosts.forEach(post => {
      if (post.tags && Array.isArray(post.tags)) {
        post.tags.forEach(tag => {
          tagCounts.set(tag, (tagCounts.get(tag) || 0) + 1);
        });
      }
    });
    
    const sortedTags = Array.from(tagCounts.entries())
      .sort((a, b) => b[1] - a[1])
      .map(entry => entry[0]);
    
    if (tagFilterContainer && sortedTags.length > 0) {
      const tagButtonsHtml = sortedTags.map(tag => 
        `<button class="tag-filter-btn" data-tag="${tag}">
          <span class="tag-name">${tag}</span>
          <span class="tag-count">(${tagCounts.get(tag)})</span>
        </button>`
      ).join('');
      
      tagFilterContainer.innerHTML = `
        <div class="filter-header">
          <div class="filter-title-row">
            <div class="tag-filter-title">Filter by tags:</div>
            <button id="clearFiltersBtn" class="clear-filters-btn">
              Clear filters
            </button>
          </div>
          <div class="tag-filter-buttons">
            ${tagButtonsHtml}
          </div>
        </div>
      `;
      
      tagFilterContainer.querySelectorAll('.tag-filter-btn').forEach(btn => {
        btn.addEventListener('click', () => toggleTagFilter(btn.dataset.tag, btn));
      });
      
      const newClearBtn = document.getElementById('clearFiltersBtn');
      if (newClearBtn) {
        newClearBtn.addEventListener('click', clearAllFilters);
      }
      
      updateClearFiltersButton();
    }
  };

  // ★★★ タグフィルター切り替え ★★★
  const toggleTagFilter = (tag, buttonElement) => {
    if (activeFilters.has(tag)) {
      activeFilters.delete(tag);
      buttonElement.classList.remove('active');
    } else {
      activeFilters.add(tag);
      buttonElement.classList.add('active');
    }
    
    updateClearFiltersButton();
    applyFilters();
  };

  // ★★★ フィルタリング適用 ★★★
  const applyFilters = () => {
    if (activeFilters.size === 0) {
      filteredPosts = [...allPosts];
    } else {
      filteredPosts = allPosts.filter(post => {
        if (!post.tags || !Array.isArray(post.tags)) return false;
        return Array.from(activeFilters).some(filterTag => 
          post.tags.includes(filterTag)
        );
      });
    }
    
    applySorting();
    
    currentPage = 1;
    totalPages = Math.ceil(filteredPosts.length / postsPerPage);
    
    console.log(`Total posts: ${filteredPosts.length}, Total pages: ${totalPages}, Posts per page: ${postsPerPage}`);
    
    displayFilteredPosts(true);
  };

  // ★★★ ソート適用 ★★★
  const applySorting = () => {
    if (currentSort === 'newest') {
      filteredPosts.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
    } else if (currentSort === 'client') {
      filteredPosts.sort((a, b) => a.client_name.localeCompare(b.client_name));
    }
  };

  // ★★★ その他の関数 ★★★
  const updateClearFiltersButton = () => {
    const clearBtn = document.getElementById('clearFiltersBtn');
    if (clearBtn) {
      if (activeFilters.size > 0) {
        clearBtn.style.display = 'inline-flex';
        clearBtn.classList.add('show');
        clearBtn.textContent = `Clear filters (${activeFilters.size})`;
      } else {
        clearBtn.style.display = 'none';
        clearBtn.classList.remove('show');
      }
    }
  };

  const clearAllFilters = () => {
    activeFilters.clear();
    document.querySelectorAll('.tag-filter-btn').forEach(btn => {
      btn.classList.remove('active');
    });
    updateClearFiltersButton();
    applyFilters();
  };

  const loadNextPage = () => {
    if (currentPage < totalPages) {
      console.log(`Loading page ${currentPage + 1} of ${totalPages}`);
      currentPage++;
      isLoading = true;
      
      setTimeout(() => {
        displayFilteredPosts(false);
        isLoading = false;
        updateLoadMoreButton();
      }, 200);
    }
  };

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
  postGrid.parentNode.insertBefore(loadMoreButton, postGrid.nextSibling);

  const updateLoadMoreButton = () => {
    console.log(`Current page: ${currentPage}, Total pages: ${totalPages}`);
    
    if (currentPage < totalPages) {
      loadMoreButton.style.display = 'block';
      loadMoreButton.textContent = `もっと見る (${currentPage}/${totalPages}ページ目)`;
    } else {
      loadMoreButton.style.display = 'none';
    }
    
    setTimeout(() => {
      const hasVerticalScroll = document.documentElement.scrollHeight > window.innerHeight;
      console.log(`Has vertical scroll: ${hasVerticalScroll}`);
      if (!hasVerticalScroll && currentPage < totalPages) {
        loadMoreButton.classList.add('no-scroll-highlight');
      } else {
        loadMoreButton.classList.remove('no-scroll-highlight');
      }
    }, 100);
  };

  // ★★★ スクロール位置をチェックして次のページを読み込む ★★★
  const checkScrollTrigger = () => {
    const scrollPosition = window.scrollY + window.innerHeight;
    const documentHeight = document.documentElement.scrollHeight;
    const triggerPoint = documentHeight - 200; // 200px手前でトリガー
    
    // スクロールが実際に発生している場合のみトリガー
    const hasActuallyScrolled = window.scrollY > 100; // 100px以上スクロールした場合のみ
    
    console.log(`Scroll check - Position: ${scrollPosition}, Height: ${documentHeight}, Trigger: ${triggerPoint}, ScrollY: ${window.scrollY}, Has scrolled: ${hasActuallyScrolled}, Can load: ${currentPage < totalPages && !isLoading}`);

    if (hasActuallyScrolled && scrollPosition >= triggerPoint && currentPage < totalPages && !isLoading) {
      console.log('Triggering next page load via scroll');
      loadNextPage();
    }
  };

  // ★★★ スクロールイベントリスナー ★★★
  let scrollTimeout;
  window.addEventListener('scroll', () => {
    clearTimeout(scrollTimeout);
    scrollTimeout = setTimeout(() => {
      console.log('Scroll event triggered');
      checkScrollTrigger();
    }, 50); // 50msの遅延でパフォーマンス向上
  });

  const handleSortChange = () => {
    const newSort = sortSelect.value;
    if (newSort !== currentSort) {
      currentSort = newSort;
      applyFilters();
    }
  };

  if (sortSelect) {
    sortSelect.addEventListener("change", handleSortChange);
  }

  // ★★★ 初期ロード ★★★
  fetchAllPosts();
});