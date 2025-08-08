document.addEventListener("DOMContentLoaded", () => {
  const postForm = document.getElementById("postForm");
  const clientLogoInput = document.getElementById("clientLogo");
  const clientLogoPreview = document.getElementById("clientLogoPreview");
  const galleryImagesContainer = document.getElementById("galleryImagesContainer");
  const addGalleryImageBtn = document.getElementById("addGalleryImageBtn");
  const formTitle = document.getElementById("formTitle");
  const formMessage = document.getElementById("formMessage");
  
  // ★★★ タグ入力関連の要素 ★★★
  const tagInputContainer = document.getElementById("tagInputContainer");
  const tagInputField = document.getElementById("tagInputField");
  const tagSuggestions = document.getElementById("tagSuggestions");
  
  let currentTags = []; // 現在選択されているタグ
  let availableTags = []; // 利用可能なタグ一覧
  let suggestionIndex = -1; // キーボード操作用のインデックス
  
  // クライアントロゴ削除ボタンの追加
  const clientLogoRemoveBtn = document.createElement('button');
  clientLogoRemoveBtn.type = 'button';
  clientLogoRemoveBtn.classList.add('remove-button');
  clientLogoRemoveBtn.textContent = 'ロゴ削除';
  clientLogoRemoveBtn.style.display = 'none';
  clientLogoInput.parentNode.insertBefore(clientLogoRemoveBtn, clientLogoPreview.nextSibling);

  // URLからIDを取得
  const urlParams = new URLSearchParams(window.location.search);
  const postId = urlParams.get('id');

  let existingPostData = null; 
  let clientLogoDeleted = false;

  // ★★★ 利用可能なタグを新しいAPIから取得する関数 ★★★
  const fetchAvailableTags = async () => {
    try {
      const response = await fetch("/portfolio/api/tags.php");
      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
      
      const tags = await response.json();
      availableTags = tags.map(tag => tag.name).sort();
    } catch (error) {
      console.error("タグデータの取得に失敗:", error);
      availableTags = [];
    }
  };

  // ★★★ タグ入力UIの更新 ★★★
  const updateTagInputUI = () => {
    // 既存のタグ表示をクリア
    const existingTags = tagInputContainer.querySelectorAll('.tag-item');
    existingTags.forEach(tag => tag.remove());
    
    // 現在のタグを表示
    currentTags.forEach((tag, index) => {
      const tagElement = document.createElement('div');
      tagElement.className = 'tag-item';
      tagElement.innerHTML = `
        <span>${tag}</span>
        <button type="button" class="remove-tag" data-index="${index}">&times;</button>
      `;
      
      // 削除ボタンのイベントリスナー
      tagElement.querySelector('.remove-tag').addEventListener('click', () => {
        removeTag(index);
      });
      
      tagInputContainer.insertBefore(tagElement, tagInputField);
    });
    
    // フォームデータ用のhidden inputを更新
    updateTagsHiddenInput();
  };

  // ★★★ タグ削除 ★★★
  const removeTag = (index) => {
    currentTags.splice(index, 1);
    updateTagInputUI();
  };

  // ★★★ タグ追加 ★★★
  const addTag = (tag) => {
    const trimmedTag = tag.trim();
    if (trimmedTag && !currentTags.includes(trimmedTag)) {
      currentTags.push(trimmedTag);
      updateTagInputUI();
      tagInputField.value = '';
      hideSuggestions();
    }
  };

  // ★★★ サジェスト機能 ★★★
  const showSuggestions = (inputValue) => {
    const filtered = availableTags.filter(tag => 
      tag.toLowerCase().includes(inputValue.toLowerCase()) && 
      !currentTags.includes(tag)
    );
    
    if (filtered.length === 0) {
      hideSuggestions();
      return;
    }
    
    tagSuggestions.innerHTML = filtered.map((tag, index) => 
      `<div class="tag-suggestion-item" data-tag="${tag}" data-index="${index}">${tag}</div>`
    ).join('');
    
    tagSuggestions.style.display = 'block';
    suggestionIndex = -1;
    
    // サジェスト項目のクリックイベント
    tagSuggestions.querySelectorAll('.tag-suggestion-item').forEach(item => {
      item.addEventListener('click', () => {
        addTag(item.dataset.tag);
      });
    });
  };

  const hideSuggestions = () => {
    tagSuggestions.style.display = 'none';
    suggestionIndex = -1;
  };

  // ★★★ hidden inputの更新 ★★★
  const updateTagsHiddenInput = () => {
    let hiddenInput = document.getElementById('tagsHiddenInput');
    if (!hiddenInput) {
      hiddenInput = document.createElement('input');
      hiddenInput.type = 'hidden';
      hiddenInput.name = 'tags';
      hiddenInput.id = 'tagsHiddenInput';
      postForm.appendChild(hiddenInput);
    }
    hiddenInput.value = JSON.stringify(currentTags);
  };

  // ★★★ タグ入力フィールドのイベントリスナー ★★★
  tagInputField.addEventListener('input', (e) => {
    const inputValue = e.target.value;
    if (inputValue.trim()) {
      showSuggestions(inputValue);
    } else {
      hideSuggestions();
    }
  });

  tagInputField.addEventListener('keydown', (e) => {
    const suggestions = tagSuggestions.querySelectorAll('.tag-suggestion-item');
    
    switch(e.key) {
      case 'Enter':
        e.preventDefault();
        if (suggestionIndex >= 0 && suggestions[suggestionIndex]) {
          addTag(suggestions[suggestionIndex].dataset.tag);
        } else if (tagInputField.value.trim()) {
          addTag(tagInputField.value);
        }
        break;
        
      case 'ArrowDown':
        e.preventDefault();
        if (suggestions.length > 0) {
          suggestionIndex = Math.min(suggestionIndex + 1, suggestions.length - 1);
          updateSuggestionHighlight(suggestions);
        }
        break;
        
      case 'ArrowUp':
        e.preventDefault();
        if (suggestions.length > 0) {
          suggestionIndex = Math.max(suggestionIndex - 1, -1);
          updateSuggestionHighlight(suggestions);
        }
        break;
        
      case 'Escape':
        hideSuggestions();
        break;
        
      case 'Backspace':
        if (tagInputField.value === '' && currentTags.length > 0) {
          removeTag(currentTags.length - 1);
        }
        break;
    }
  });

  // サジェストハイライトの更新
  const updateSuggestionHighlight = (suggestions) => {
    suggestions.forEach((item, index) => {
      item.classList.toggle('active', index === suggestionIndex);
    });
  };

  // クリック時にサジェストを隠す
  document.addEventListener('click', (e) => {
    if (!tagInputContainer.contains(e.target) && !tagSuggestions.contains(e.target)) {
      hideSuggestions();
    }
  });

  // 画像プレビュー表示関数
  const setupImagePreview = (fileInput, previewContainer, initialImageUrl = null) => {
    previewContainer.innerHTML = '';

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
      clientLogoRemoveBtn.style.display = 'none';
      clientLogoDeleted = false;
    });
  };

  // クライアントロゴのプレビュー設定
  setupImagePreview(clientLogoInput, clientLogoPreview);

  // ギャラリー画像追加ボタンのイベントリスナー
  const addGalleryImageField = (initialImagePath = null, initialCaption = '', isExisting = false) => {
    const newItem = document.createElement("div");
    newItem.classList.add("gallery-image-item");
    
    let hiddenInputHtml = '';
    if (isExisting && initialImagePath) {
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

    const newFileInput = newItem.querySelector('input[type="file"]');
    const newPreviewContainer = newItem.querySelector('.image-preview');
    setupImagePreview(newFileInput, newPreviewContainer, initialImagePath);

    newItem.querySelector(".remove-gallery-image-btn").addEventListener("click", () => {
      newItem.remove();
    });
  };

  addGalleryImageBtn.addEventListener("click", () => {
    addGalleryImageField(null, '', false);
  });

  // クライアントロゴ削除ボタンのイベントリスナー
  clientLogoRemoveBtn.addEventListener('click', () => {
    clientLogoPreview.innerHTML = '';
    clientLogoInput.value = '';
    clientLogoRemoveBtn.style.display = 'none';
    clientLogoDeleted = true;
  });

  // フォーム送信時の処理
  postForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    formMessage.style.display = 'none';
    formMessage.className = 'form-message';

    // フォーム送信前にタグデータを更新
    updateTagsHiddenInput();

    const formData = new FormData(postForm);
    
    if (clientLogoDeleted) {
      formData.append('client_logo_removed', 'true');
    } else if (!clientLogoInput.files.length && existingPostData && existingPostData.client_logo) {
      formData.append('client_logo_unchanged', 'true');
    }

    let apiEndpoint = '';
    let successMessage = '';
    if (postId) {
      apiEndpoint = "/portfolio/api/update_post.php";
      formData.append('id', postId);
      successMessage = "投稿が正常に更新されました！";
    } else {
      apiEndpoint = "/portfolio/api/post_upload.php";
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
        
        if (!postId) {
            postForm.reset();
            clientLogoPreview.innerHTML = '';
            galleryImagesContainer.innerHTML = '';
            currentTags = [];
            updateTagInputUI();
            addGalleryImageField();
        }
        
        setTimeout(() => {
          window.location.href = "/portfolio/admin/index.html";
        }, 1500);
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

  // ★★★ 編集モード時のデータ読み込み（タグ対応） ★★★
  if (postId) {
    formTitle.textContent = "投稿を編集";
    const fetchPostDataForEdit = async () => {
      try {
        const response = await fetch(`/portfolio/api/get_posts.php`);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        
        const posts = await response.json();
        const postToEdit = posts.find(p => p.id === postId);

        if (postToEdit) {
          existingPostData = postToEdit;
          
          // フォームにデータをセット
          document.getElementById("postTitle").value = postToEdit.title;
          document.getElementById("clientName").value = postToEdit.client_name;
          document.getElementById("description").value = postToEdit.description;
          
          // ★★★ タグデータの設定 ★★★
          if (postToEdit.tags && Array.isArray(postToEdit.tags)) {
            currentTags = [...postToEdit.tags];
            updateTagInputUI();
          }
          
          // クライアントロゴの既存プレビュー
          if (postToEdit.client_logo) {
            setupImagePreview(clientLogoInput, clientLogoPreview, postToEdit.client_logo);
            clientLogoRemoveBtn.style.display = 'inline-block';
          } else {
            clientLogoRemoveBtn.style.display = 'none';
          }

          // ギャラリー画像の既存プレビューとフィールド追加
          galleryImagesContainer.innerHTML = '';
          if (postToEdit.gallery_images && postToEdit.gallery_images.length > 0) {
            postToEdit.gallery_images.forEach(img => {
              addGalleryImageField(img.path, img.caption, true);
            });
          } else {
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
    if (galleryImagesContainer.children.length === 0) {
      addGalleryImageField();
    }
  }

  // ★★★ 初期化処理 ★★★
  const initialize = async () => {
    await fetchAvailableTags();
    updateTagInputUI();
  };

  initialize();
});