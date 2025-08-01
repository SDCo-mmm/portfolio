document.addEventListener("DOMContentLoaded", () => {
  const postsListContainer = document.getElementById("postsList");
  const createPostBtn = document.getElementById("createPostBtn"); // 新規投稿ボタン

  // 投稿データを読み込む関数
  const fetchAndDisplayPosts = async () => {
    postsListContainer.innerHTML = "<p>投稿を読み込み中...</p>"; // ロード中に表示
    try {
      // APIパス修正済み: /portfolio/api/get_posts.php
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

  // 管理画面用に投稿データを表示する関数
  const displayAdminPosts = (posts) => {
    if (posts.length === 0) {
      postsListContainer.innerHTML = "<p>まだ投稿がありません。「新規投稿作成」ボタンで追加してください。</p>";
      return;
    }

    // 投稿を日付の新しい順にソート (get_posts.phpで既にソート済みだが、念のため)
    posts.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

    const tableHtml = `
      <table class="posts-table">
        <thead>
          <tr>
            <th>タイトル</th>
            <th>クライアント名</th>
            <th>作成日</th>
            <th>アクション</th>
          </tr>
        </thead>
        <tbody>
          ${posts.map(post => `
            <tr>
              <td data-label="タイトル">${post.title}</td>
              <td data-label="クライアント名">${post.client_name}</td>
              <td data-label="作成日">${new Date(post.created_at).toLocaleDateString()}</td>
              <td data-label="アクション" class="actions">
                <button class="edit-btn" data-id="${post.id}">編集</button>
                <button class="delete-btn" data-id="${post.id}">削除</button>
              </td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    `;
    postsListContainer.innerHTML = tableHtml;

    // 編集・削除ボタンのイベントリスナーを設定
    postsListContainer.querySelectorAll(".edit-btn").forEach(button => {
      button.addEventListener("click", () => {
        // 編集画面へ遷移（IDをパラメータとして渡す）
        window.location.href = `/portfolio/admin/post_form.html?id=${button.dataset.id}`;
      });
    });

    postsListContainer.querySelectorAll(".delete-btn").forEach(button => {
      button.addEventListener("click", async () => {
        const postIdToDelete = button.dataset.id;
        if (confirm(`投稿「${postIdToDelete}」を本当に削除しますか？`)) {
          try {
            const formData = new FormData();
            formData.append('id', postIdToDelete);

            // APIパス: /portfolio/api/delete_post.php
            const response = await fetch('/portfolio/api/delete_post.php', {
              method: 'POST',
              body: formData
            });

            const result = await response.json();

            if (result.status === 'success') {
              alert(result.message);
              fetchAndDisplayPosts(); // 削除後にリストを再読み込み
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
      window.location.href = "/portfolio/admin/post_form.html"; // 新規投稿フォームへ遷移
    });
  }

  // 初期表示
  fetchAndDisplayPosts();
});

// ログアウト機能
document.getElementById("logoutBtn").addEventListener("click", () => {
  // admin_auth_check Cookieを削除してログインページへリダイレクト
  document.cookie = "admin_auth_check=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/portfolio/admin/;";
  // admin_auth_token Cookieも削除 (HttpOnlyなのでJSからは直接削除できないが、念のためパスを設定)
  document.cookie = "admin_auth_token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/portfolio/;"; // ★重要: auth_admin.phpで設定したpathと一致させる
  window.location.href = "/portfolio/admin/login.html";
});