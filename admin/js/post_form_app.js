document.addEventListener("DOMContentLoaded", () => {
  const postForm = document.getElementById("postForm");
  const clientLogoInput = document.getElementById("clientLogo");
  const clientLogoPreview = document.getElementById("clientLogoPreview");
  const galleryImagesContainer = document.getElementById("galleryImagesContainer");
  const addGalleryImageBtn = document.getElementById("addGalleryImageBtn");
  const formTitle = document.getElementById("formTitle");
  const formMessage = document.getElementById("formMessage");
  
  // クライアントロゴ削除ボタンの追加 (新機能)
  const clientLogoRemoveBtn = document.createElement('button');
  clientLogoRemoveBtn.type = 'button';
  clientLogoRemoveBtn.classList.add('remove-button');
  clientLogoRemoveBtn.textContent = 'ロゴ削除';
  clientLogoRemoveBtn.style.display = 'none'; // 初期状態では非表示
  clientLogoInput.parentNode.insertBefore(clientLogoRemoveBtn, clientLogoPreview.nextSibling);

  // URLからIDを取得 (編集モードの場合)
  const urlParams = new URLSearchParams(window.location.search);
  const postId = urlParams.get('id');

  // 編集モードの場合、既存の投稿データを保持する隠しフィールド
  let existingPostData = null; 
  let clientLogoDeleted = false; // クライアントロゴが削除されたかどうかのフラグ

  // 画像プレビュー表示関数
  const setupImagePreview = (fileInput, previewContainer, initialImageUrl = null) => {
    previewContainer.innerHTML = ''; // まずクリア

    if (initialImageUrl) {
      const img = document.createElement("img");
      img.src = initialImageUrl;
      img.style.maxWidth = "150px";
      img.style.maxHeight = "150px";
      img.style.marginRight = "10px";
      img.style.border = "1px solid #ddd";
      img.style.borderRadius = "4px";
      previewContainer.appendChild(img);
    }
    
    fileInput.addEventListener("change", () => {
      previewContainer.innerHTML = '';
      const file = fileInput.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = (e) => {
          const img = document.createElement("img");
          img.src = e.target.result;
          img.style.maxWidth = "150px";
          img.style.maxHeight = "150px";
          img.style.marginRight = "10px";
          img.style.border = "1px solid #ddd";
          img.style.borderRadius = "4px";
          previewContainer.appendChild(img);
        };
        reader.readAsDataURL(file);
      }
      // ファイルが選択されたらロゴ削除ボタンを非表示
      clientLogoRemoveBtn.style.display = 'none';
      clientLogoDeleted = false; // 削除フラグをリセット
    });
  };

  // クライアントロゴのプレビュー設定（初期表示時）
  setupImagePreview(clientLogoInput, clientLogoPreview);

  // ギャラリー画像追加ボタンのイベントリスナー
  const addGalleryImageField = (initialImagePath = null, initialCaption = '', isExisting = false) => {
    const newItem = document.createElement("div");
    newItem.classList.add("gallery-image-item");
    
    let hiddenInputHtml = '';
    if (isExisting && initialImagePath) {
      // 既存の画像のパスを保持するhiddenフィールド
      hiddenInputHtml = `<input type="hidden" name="existing_gallery_paths[]" value="${initialImagePath}" />`;
    }

    newItem.innerHTML = `
      ${hiddenInputHtml}
      <input type="file" name="${isExisting ? 'existing_gallery_images[]' : 'gallery_images[]'}" accept="image/*" />
      <input type="text" name="${isExisting ? 'existing_gallery_captions[]' : 'gallery_captions[]'}" placeholder="キャプション" value="${initialCaption}" />
      <div class="image-preview"></div>
      <button type="button" class="remove-gallery-image-btn">削除</button>
    `;
    galleryImagesContainer.appendChild(newItem);

    // 新しく追加された画像入力フィールドのプレビュー設定
    const newFileInput = newItem.querySelector('input[type="file"]');
    const newPreviewContainer = newItem.querySelector('.image-preview');
    setupImagePreview(newFileInput, newPreviewContainer, initialImagePath);

    // 削除ボタンのイベントリスナー
    newItem.querySelector(".remove-gallery-image-btn").addEventListener("click", () => {
      newItem.remove();
    });
  };

  addGalleryImageBtn.addEventListener("click", () => {
    addGalleryImageField(null, '', false); // 新規追加なのでisExistingはfalse
  });

  // クライアントロゴ削除ボタンのイベントリスナー
  clientLogoRemoveBtn.addEventListener('click', () => {
    clientLogoPreview.innerHTML = ''; // プレビューをクリア
    clientLogoInput.value = ''; // ファイル入力をクリア
    clientLogoRemoveBtn.style.display = 'none'; // ボタンを隠す
    clientLogoDeleted = true; // 削除フラグを立てる
  });

  // フォーム送信時の処理
  postForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    formMessage.style.display = 'none';
    formMessage.className = 'form-message'; // クラスをリセット

    const formData = new FormData(postForm);
    
    // クライアントロゴの処理
    if (clientLogoDeleted) {
      formData.append('client_logo_removed', 'true');
    } else if (!clientLogoInput.files.length && existingPostData && existingPostData.client_logo) {
      formData.append('client_logo_unchanged', 'true');
    }

    let apiEndpoint = '';
    let successMessage = '';
    if (postId) {
      apiEndpoint = "/portfolio/api/update_post.php"; // 編集API
      formData.append('id', postId); // 投稿IDを追加
      successMessage = "投稿が正常に更新されました！";
    } else {
      apiEndpoint = "/portfolio/api/post_upload.php"; // 新規作成API
      successMessage = "投稿が正常に保存されました！";
    }

    try {
      const response = await fetch(apiEndpoint, {
        method: "POST",
        body: formData,
      });

      const result = await response.json();

      if (result.status === "success") {
        formMessage.textContent = successMessage;
        formMessage.classList.add('success');
        formMessage.style.display = 'block';
        
        // 新規作成の場合、フォームをリセット
        if (!postId) {
            postForm.reset();
            clientLogoPreview.innerHTML = '';
            galleryImagesContainer.innerHTML = '';
            addGalleryImageField(); // 最初のギャラリー画像フィールドを再追加
        }
        // 成功後、一覧ページへリダイレクト
        setTimeout(() => {
          window.location.href = "/portfolio/admin/index.html";
        }, 1500); // 1.5秒後にリダイレクト
      } else {
        formMessage.textContent = `エラー: ${result.message}`;
        formMessage.classList.add('error');
        formMessage.style.display = 'block';
      }
    } catch (error) {
      console.error("投稿保存/更新エラー:", error);
      formMessage.textContent = `通信エラーが発生しました: ${error.message}`;
      formMessage.classList.add('error');
      formMessage.style.display = 'block';
    }
  });

  // 編集モード時のデータ読み込みロジック
  if (postId) {
    formTitle.textContent = "投稿を編集";
    const fetchPostDataForEdit = async () => {
      try {
        const response = await fetch(`/portfolio/api/get_posts.php`);
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        const posts = await response.json();
        const postToEdit = posts.find(p => p.id === postId);

        if (postToEdit) {
          existingPostData = postToEdit; // 既存データを保持
          // フォームにデータをセット
          document.getElementById("postTitle").value = postToEdit.title;
          document.getElementById("clientName").value = postToEdit.client_name;
          document.getElementById("description").value = postToEdit.description;
          
          // クライアントロゴの既存プレビュー
          if (postToEdit.client_logo) {
            setupImagePreview(clientLogoInput, clientLogoPreview, postToEdit.client_logo);
            clientLogoRemoveBtn.style.display = 'inline-block'; // ロゴがあれば削除ボタン表示
          } else {
            clientLogoRemoveBtn.style.display = 'none';
          }

          // ギャラリー画像の既存プレビューとフィールド追加
          galleryImagesContainer.innerHTML = ''; // 初期ギャラリーフィールドをクリア
          if (postToEdit.gallery_images && postToEdit.gallery_images.length > 0) {
            postToEdit.gallery_images.forEach(img => {
              addGalleryImageField(img.path, img.caption, true); // 既存画像として追加
            });
          } else {
            // ギャラリー画像がない場合も、最低1つは入力フィールドを表示
            addGalleryImageField();
          }
          
        } else {
          formMessage.textContent = "編集対象の投稿が見つかりません。";
          formMessage.classList.add('error');
          formMessage.style.display = 'block';
        }
      } catch (error) {
        console.error("編集用データ読み込みエラー:", error);
        formMessage.textContent = `編集データ読み込み中にエラーが発生しました: ${error.message}`;
        formMessage.classList.add('error');
        formMessage.style.display = 'block';
      }
    };
    fetchPostDataForEdit();
  } else {
      // 新規作成の場合、最低1つのギャラリー画像フィールドを確保
      if (galleryImagesContainer.children.length === 0) {
        addGalleryImageField();
      }
  }

});