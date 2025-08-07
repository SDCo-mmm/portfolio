document.addEventListener("DOMContentLoaded", () => {
  const postsListContainer = document.getElementById("postsList");
  const createPostBtn = document.getElementById("createPostBtn");
  
  // ★★★ 一括削除用の要素 ★★★
  let bulkDeleteBtn = null;
  let selectAllCheckbox = null;

  // 投稿データを読み込む関数
  const fetchAndDisplayPosts = async () => {
    postsListContainer.innerHTML = "<p>投稿を読み込み中...</p>";
    try {
      const response = await fetch("/portfolio/api/get_posts.php");
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      const posts = await response.json();
      displayAdminPosts(posts);
    } catch (error) {
      console.error("管理画面：投稿の読み込み中にエラーが発生しました:", error);
      postsListContainer.innerHTML = "<p>投稿の読み込みに失敗しました。</p>";
    }
  };

  // ★★★ 一括操作バーを作成 ★★★
  const createBulkActionBar = () => {
    const bulkActionBar = document.createElement('div');
    bulkActionBar.id = 'bulkActionBar';
    bulkActionBar.className = 'bulk-action-bar';
    bulkActionBar.innerHTML = `
      <div class="bulk-actions">
        <span class="selected-count">0件選択中</span>
        <button id="bulkDeleteBtn" class="bulk-delete-btn" disabled>選択した投稿を削除</button>
        <button id="deselectAllBtn" class="deselect-all-btn">選択解除</button>
      </div>
    `;
    return bulkActionBar;
  };

  // ★★★ 管理画面用に投稿データを表示する関数（強化版） ★★★
  const displayAdminPosts = (posts) => {
    if (posts.length === 0) {
      postsListContainer.innerHTML = "<p>まだ投稿がありません。「新規投稿作成」ボタンで追加してください。</p>";
      return;
    }

    // 投稿を日付の新しい順にソート
    posts.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

    // ★★★ 一括操作バーを追加 ★★★
    const bulkActionBar = createBulkActionBar();
    
    const tableHtml = `
      <table class="posts-table">
        <thead>
          <tr>
            <th class="checkbox-column">
              <input type="checkbox" id="selectAllCheckbox" title="全て選択/解除">
            </th>
            <th>タイトル</th>
            <th>クライアント名</th>
            <th>タグ</th>
            <th>作成日</th>
            <th>アクション</th>
          </tr>
        </thead>
        <tbody>
          ${posts.map(post => {
            // タグ表示の処理
            const tagsDisplay = post.tags && post.tags.length > 0 
              ? post.tags.map(tag => `<span class="tag-badge">${tag}</span>`).join(' ')
              : '<span class="no-tags">タグなし</span>';
            
            return `
              <tr data-post-id="${post.id}">
                <td data-label="選択" class="checkbox-column">
                  <input type="checkbox" class="post-checkbox" value="${post.id}">
                </td>
                <td data-label="タイトル">${post.title}</td>
                <td data-label="クライアント名">${post.client_name}</td>
                <td data-label="タグ" class="tags-column">${tagsDisplay}</td>
                <td data-label="作成日">${new Date(post.created_at).toLocaleDateString()}</td>
                <td data-label="アクション" class="actions">
                  <button class="edit-btn" data-id="${post.id}">編集</button>
                  <button class="delete-btn" data-id="${post.id}">削除</button>
                </td>
              </tr>
            `;
          }).join('')}
        </tbody>
      </table>
    `;

    postsListContainer.innerHTML = '';
    postsListContainer.appendChild(bulkActionBar);
    postsListContainer.insertAdjacentHTML('beforeend', tableHtml);

    // ★★★ 一括操作の機能を設定 ★★★
    setupBulkActions();
    setupIndividualActions();
  };

  // ★★★ 一括操作機能の設定 ★★★
  const setupBulkActions = () => {
    selectAllCheckbox = document.getElementById('selectAllCheckbox');
    bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    const deselectAllBtn = document.getElementById('deselectAllBtn');
    const postCheckboxes = document.querySelectorAll('.post-checkbox');
    const selectedCountSpan = document.querySelector('.selected-count');

    // 選択状態の更新
    const updateSelectionState = () => {
      const checkedBoxes = document.querySelectorAll('.post-checkbox:checked');
      const totalBoxes = document.querySelectorAll('.post-checkbox');
      
      // 選択数の表示更新
      selectedCountSpan.textContent = `${checkedBoxes.length}件選択中`;
      
      // 一括削除ボタンの有効/無効
      bulkDeleteBtn.disabled = checkedBoxes.length === 0;
      
      // 全選択チェックボックスの状態更新
      if (checkedBoxes.length === 0) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = false;
      } else if (checkedBoxes.length === totalBoxes.length) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = true;
      } else {
        selectAllCheckbox.indeterminate = true;
      }
    };

    // 全選択/全解除の処理
    selectAllCheckbox.addEventListener('change', () => {
      const isChecked = selectAllCheckbox.checked;
      postCheckboxes.forEach(checkbox => {
        checkbox.checked = isChecked;
      });
      updateSelectionState();
    });

    // 個別チェックボックスの処理
    postCheckboxes.forEach(checkbox => {
      checkbox.addEventListener('change', updateSelectionState);
    });

    // 選択解除ボタン
    deselectAllBtn.addEventListener('click', () => {
      postCheckboxes.forEach(checkbox => {
        checkbox.checked = false;
      });
      updateSelectionState();
    });

    // ★★★ 一括削除ボタンの処理 ★★★
    bulkDeleteBtn.addEventListener('click', async () => {
      const checkedBoxes = document.querySelectorAll('.post-checkbox:checked');
      const selectedIds = Array.from(checkedBoxes).map(cb => cb.value);
      
      if (selectedIds.length === 0) return;

      if (confirm(`選択した${selectedIds.length}件の投稿を本当に削除しますか？この操作は取り消せません。`)) {
        try {
          // 一括削除ボタンを無効化
          bulkDeleteBtn.disabled = true;
          bulkDeleteBtn.textContent = '削除中...';

          const deletePromises = selectedIds.map(async (postId) => {
            const formData = new FormData();
            formData.append('id', postId);
            
            const response = await fetch('/portfolio/api/delete_post.php', {
              method: 'POST',
              body: formData
            });
            
            const result = await response.json();
            if (result.status !== 'success') {
              throw new Error(`投稿 ${postId} の削除に失敗: ${result.message}`);
            }
            return result;
          });

          await Promise.all(deletePromises);
          
          alert(`${selectedIds.length}件の投稿が正常に削除されました。`);
          fetchAndDisplayPosts(); // リスト再読み込み
          
        } catch (error) {
          alert(`一括削除中にエラーが発生しました: ${error.message}`);
          console.error('一括削除エラー:', error);
        } finally {
          bulkDeleteBtn.disabled = false;
          bulkDeleteBtn.textContent = '選択した投稿を削除';
        }
      }
    });

    // 初期状態を更新
    updateSelectionState();
  };

  // ★★★ 個別操作（編集・削除）の設定 ★★★
  const setupIndividualActions = () => {
    // 編集ボタン
    document.querySelectorAll(".edit-btn").forEach(button => {
      button.addEventListener("click", () => {
        window.location.href = `/portfolio/admin/post_form.html?id=${button.dataset.id}`;
      });
    });

    // 個別削除ボタン
    document.querySelectorAll(".delete-btn").forEach(button => {
      button.addEventListener("click", async () => {
        const postIdToDelete = button.dataset.id;
        if (confirm(`投稿「${postIdToDelete}」を本当に削除しますか？`)) {
          try {
            const formData = new FormData();
            formData.append('id', postIdToDelete);

            const response = await fetch('/portfolio/api/delete_post.php', {
              method: 'POST',
              body: formData
            });

            const result = await response.json();

            if (result.status === 'success') {
              alert(result.message);
              fetchAndDisplayPosts();
            } else {
              alert(`削除に失敗しました: ${result.message}`);
              console.error('削除エラー:', result.message);
            }
          } catch (error) {
            alert('削除中にエラーが発生しました。');
            console.error('削除フェッチエラー:', error);
          }
        }
      });
    });
  };

  // 新規投稿作成ボタンのイベントリスナー
  if (createPostBtn) {
    createPostBtn.addEventListener("click", () => {
      window.location.href = "/portfolio/admin/post_form.html";
    });
  }

  // 初期表示
  fetchAndDisplayPosts();
});

// ログアウト機能
document.getElementById("logoutBtn").addEventListener("click", () => {
  document.cookie = "admin_auth_check=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/portfolio/admin/;";
  document.cookie = "admin_auth_token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/portfolio/;";
  window.location.href = "/portfolio/admin/login.html";
});