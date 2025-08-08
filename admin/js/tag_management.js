document.addEventListener("DOMContentLoaded", function() {
  const newTagNameInput = document.getElementById("newTagName");
  const addTagBtn = document.getElementById("addTagBtn");
  const addInitialTagsBtn = document.getElementById("addInitialTagsBtn");
  const tagsList = document.getElementById("tagsList");
  const tagSearchInput = document.getElementById("tagSearchInput");
  const tagSortSelect = document.getElementById("tagSortSelect");
  const messageArea = document.getElementById("messageArea");

  // 初期タグリスト
  const initialTags = [
    "TVCM", "ラジオCM", "雑誌広告", "新聞広告", "その他広告（紙媒体）", "DM", "ポスター", "フライヤー",
    "ポストカード", "POP", "ステッカー", "名刺", "カレンダー", "封筒", "カタログ", "パンフレット",
    "PR紙・会報誌・機関誌", "書籍・装丁", "広報・PRツール", "パッケージ", "CI・VI", "企業・ブランドサイト",
    "特設サイト", "WEB広告", "バナー", "ランディングページ", "Instagram", "Twitter", "LINE", "Facebook",
    "サイン・ディスプレー", "店舗・施設", "プロダクト", "ノベルティ", "企業・ブランドVP", "デジタルサイネージ",
    "採用ツール", "フォトレタッチ", "動画編集", "ビジネスツール（一式）", "クロスメディア（複数）", "その他"
  ];

  let allTags = []; // 全タグデータ

  // メッセージ表示関数
  function showMessage(message, type) {
    type = type || 'success';
    if (messageArea) {
      messageArea.textContent = message;
      messageArea.className = 'form-message ' + type;
      messageArea.style.display = 'block';
      setTimeout(function() {
        messageArea.style.display = 'none';
      }, 3000);
    }
  }

  // タグデータを取得
  function fetchTags() {
    if (!tagsList) {
      console.error("tagsList要素が見つかりません");
      return;
    }
    
    fetch("/portfolio/api/tags.php")
      .then(function(response) {
        if (!response.ok) {
          throw new Error('HTTP error! status: ' + response.status);
        }
        return response.text();
      })
      .then(function(responseText) {
        try {
          allTags = JSON.parse(responseText);
          displayTags();
        } catch (parseError) {
          console.error("JSON解析エラー:", parseError);
          throw new Error("APIレスポンスのJSON解析に失敗しました");
        }
      })
      .catch(function(error) {
        console.error("タグデータの読み込み中にエラーが発生しました:", error);
        showMessage("タグデータの読み込みに失敗しました: " + error.message, 'error');
        
        // エラー時のフォールバック表示
        if (tagsList) {
          tagsList.innerHTML = 
            '<div style="padding: 20px; text-align: center; color: #dc3545;">' +
            '<p>タグデータの読み込みに失敗しました。</p>' +
            '<p>エラー: ' + error.message + '</p>' +
            '<button onclick="location.reload()" style="margin-top: 10px; padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">' +
            'ページを再読み込み' +
            '</button>' +
            '</div>';
        }
      });
  }

  // タグ一覧を表示
  function displayTags(tags) {
    if (!tagsList) {
      console.error("tagsList要素が見つかりません");
      return;
    }
    
    const tagsToDisplay = tags || allTags;
    
    if (!Array.isArray(tagsToDisplay)) {
      console.error("tagsToDisplayが配列ではありません:", typeof tagsToDisplay);
      tagsList.innerHTML = "<p>タグデータの形式が正しくありません。</p>";
      return;
    }
    
    if (tagsToDisplay.length === 0) {
      tagsList.innerHTML = 
        '<div style="padding: 40px; text-align: center; color: #6c757d;">' +
        '<p>登録されているタグがありません。</p>' +
        '<p>「初期タグを一括追加」ボタンでタグを追加してください。</p>' +
        '</div>';
      return;
    }

    const tagsHtml = tagsToDisplay.map(function(tag) {
      return '<div class="tag-item" data-tag-id="' + (tag.id || 'unknown') + '">' +
        '<div class="tag-info">' +
        '<h3 class="tag-name">' + (tag.name || 'Unknown') + '</h3>' +
        '<p class="tag-usage">' + (tag.usage || 0) + '回使用</p>' +
        '<p class="tag-created">作成日: ' + (tag.created_at ? new Date(tag.created_at).toLocaleDateString() : '不明') + '</p>' +
        '</div>' +
        '<div class="tag-actions">' +
        '<button class="edit-tag-btn" data-tag-id="' + (tag.id || '') + '" data-tag-name="' + (tag.name || '') + '">編集</button>' +
        '<button class="delete-tag-btn" data-tag-id="' + (tag.id || '') + '" data-tag-name="' + (tag.name || '') + '" data-tag-usage="' + (tag.usage || 0) + '">削除</button>' +
        '</div>' +
        '</div>';
    }).join('');

    tagsList.innerHTML = tagsHtml;

    // イベントリスナーを設定
    setupTagEventListeners();
  }

  // タグ操作のイベントリスナー設定
  function setupTagEventListeners() {
    // 編集ボタン
    document.querySelectorAll('.edit-tag-btn').forEach(function(btn) {
      btn.addEventListener('click', function(e) {
        const tagId = e.target.dataset.tagId;
        const currentName = e.target.dataset.tagName;
        editTag(tagId, currentName);
      });
    });

    // 削除ボタン
    document.querySelectorAll('.delete-tag-btn').forEach(function(btn) {
      btn.addEventListener('click', function(e) {
        const tagId = e.target.dataset.tagId;
        const tagName = e.target.dataset.tagName;
        const usageCount = parseInt(e.target.dataset.tagUsage) || 0;
        deleteTag(tagId, tagName, usageCount);
      });
    });
  }

  // タグの編集
  function editTag(tagId, currentName) {
    const newName = prompt('タグ名を編集してください:', currentName);
    if (newName && newName.trim() && newName.trim() !== currentName) {
      const formData = new FormData();
      formData.append('action', 'update');
      formData.append('id', tagId);
      formData.append('name', newName.trim());

      fetch('/portfolio/api/tags.php', {
        method: 'POST',
        body: formData
      })
      .then(function(response) {
        return response.json();
      })
      .then(function(result) {
        if (result.status === 'success') {
          showMessage(result.message);
          fetchTags(); // リロード
        } else {
          showMessage(result.message, 'error');
        }
      })
      .catch(function(error) {
        showMessage('タグの更新に失敗しました: ' + error.message, 'error');
      });
    }
  }

  // タグの削除
  function deleteTag(tagId, tagName, usageCount) {
    const confirmMessage = usageCount > 0 
      ? 'タグ「' + tagName + '」を削除しますか？\n' + usageCount + '個の投稿からこのタグが削除されます。'
      : 'タグ「' + tagName + '」を削除しますか？';

    if (confirm(confirmMessage)) {
      const formData = new FormData();
      formData.append('action', 'delete');
      formData.append('id', tagId);

      fetch('/portfolio/api/tags.php', {
        method: 'POST',
        body: formData
      })
      .then(function(response) {
        return response.json();
      })
      .then(function(result) {
        if (result.status === 'success') {
          showMessage(result.message);
          fetchTags(); // リロード
        } else {
          showMessage(result.message, 'error');
        }
      })
      .catch(function(error) {
        showMessage('タグの削除に失敗しました: ' + error.message, 'error');
      });
    }
  }

  // 新しいタグを追加
  function addNewTag(tagName) {
    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('name', tagName);

    fetch('/portfolio/api/tags.php', {
      method: 'POST',
      body: formData
    })
    .then(function(response) {
      return response.json();
    })
    .then(function(result) {
      if (result.status === 'success') {
        showMessage(result.message);
        if (newTagNameInput) newTagNameInput.value = '';
        fetchTags(); // リロード
      } else {
        showMessage(result.message, 'error');
      }
    })
    .catch(function(error) {
      showMessage('タグの追加に失敗しました: ' + error.message, 'error');
    });
  }

  // 初期タグを一括追加
  function addInitialTags() {
    if (confirm(initialTags.length + '個の初期タグを一括追加しますか？\n既に存在するタグはスキップされます。')) {
      const formData = new FormData();
      formData.append('action', 'add_bulk');
      formData.append('names', JSON.stringify(initialTags));

      fetch('/portfolio/api/tags.php', {
        method: 'POST',
        body: formData
      })
      .then(function(response) {
        return response.json();
      })
      .then(function(result) {
        if (result.status === 'success') {
          showMessage(result.message);
          fetchTags(); // リロード
          
          // 初期タグボタンを無効化
          if (result.added_count > 0 && addInitialTagsBtn) {
            addInitialTagsBtn.disabled = true;
            addInitialTagsBtn.textContent = '初期タグ追加済み';
          }
        } else {
          showMessage(result.message, 'error');
        }
      })
      .catch(function(error) {
        showMessage('初期タグの追加に失敗しました: ' + error.message, 'error');
      });
    }
  }

  // タグの検索とソート
  function filterAndSortTags() {
    let filteredTags = allTags.slice(); // 配列のコピー
    const searchTerm = tagSearchInput ? tagSearchInput.value.toLowerCase() : '';
    const sortBy = tagSortSelect ? tagSortSelect.value : 'name';

    // 検索フィルター
    if (searchTerm) {
      filteredTags = filteredTags.filter(function(tag) {
        return tag.name.toLowerCase().indexOf(searchTerm) !== -1;
      });
    }

    // ソート
    if (sortBy === 'name') {
      filteredTags.sort(function(a, b) {
        return a.name.localeCompare(b.name);
      });
    } else if (sortBy === 'usage') {
      filteredTags.sort(function(a, b) {
        return (b.usage || 0) - (a.usage || 0);
      });
    } else if (sortBy === 'created') {
      filteredTags.sort(function(a, b) {
        return new Date(b.created_at) - new Date(a.created_at);
      });
    }

    displayTags(filteredTags);
  }

  // イベントリスナー設定
  if (addTagBtn) {
    addTagBtn.addEventListener('click', function() {
      const tagName = newTagNameInput ? newTagNameInput.value.trim() : '';
      if (tagName) {
        addNewTag(tagName);
      } else {
        showMessage('タグ名を入力してください。', 'error');
      }
    });
  }

  if (addInitialTagsBtn) {
    addInitialTagsBtn.addEventListener('click', addInitialTags);
  }

  // Enterキーでタグ追加
  if (newTagNameInput) {
    newTagNameInput.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        addTagBtn.click();
      }
    });
  }

  // 検索とソート
  if (tagSearchInput) {
    tagSearchInput.addEventListener('input', filterAndSortTags);
  }
  if (tagSortSelect) {
    tagSortSelect.addEventListener('change', filterAndSortTags);
  }

  // 初期ロード
  fetchTags();
});