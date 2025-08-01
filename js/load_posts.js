document.addEventListener("DOMContentLoaded", () => {
  const postGrid = document.getElementById("postGrid");
  const sortSelect = document.getElementById("sortSelect");

  let postsData = []; // 全ての投稿データを保持する配列

  const fetchPosts = async () => {
    try {
      // APIパスを修正
      const response = await fetch("/portfolio/api/get_posts.php"); 
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      postsData = await response.json(); // データを取得して保存
      displayPosts(postsData); // 初期表示
    } catch (error) {
      console.error("投稿の読み込み中にエラーが発生しました:", error);
      postGrid.innerHTML = "<p>投稿の読み込みに失敗しました。</p>";
    }
  };

  const displayPosts = (posts) => {
    postGrid.innerHTML = ""; // 既存の内容をクリア

    // ソート処理
    const sortBy = sortSelect.value;
    let sortedPosts = [...posts]; // 元のデータを変更しないようにコピー

    if (sortBy === "newest") {
      sortedPosts.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
    } else if (sortBy === "client") {
      sortedPosts.sort((a, b) => a.client_name.localeCompare(b.client_name, 'ja', { sensitivity: 'base' }));
    }

    if (sortedPosts.length === 0) {
      postGrid.innerHTML = "<p>まだ作品がありません。</p>";
      return;
    }

    sortedPosts.forEach(post => {
      // クライアントロゴが存在しない場合は表示しない
      if (!post.client_logo) {
        return; // この投稿はスキップ
      }

      const postCard = document.createElement("a");
      // post.htmlへのリンクパスを修正
      postCard.href = `/portfolio/post.html?id=${post.id}`; 
      postCard.classList.add("post-item"); 
      postCard.setAttribute('data-client', post.client_name); // ソート用属性

      // ★★★ 新しいHTML構造：正方形のロゴコンテナ + コンテンツエリア ★★★
      postCard.innerHTML = `
          <div class="logo-container">
            <img src="${post.client_logo}" alt="${post.client_name} Logo" class="client-logo-thumbnail" />
          </div>
          <div class="post-item-content"> 
            <h3>${post.title}</h3>
            <p>${post.client_name}</p>
          </div>
      `;
      postGrid.appendChild(postCard);
    });
  };

  // ソート選択肢が変更されたら再表示
  sortSelect.addEventListener("change", () => {
    displayPosts(postsData); // 保存しておいたデータをソートして表示
  });

  // 最初の投稿読み込み
  fetchPosts();
});