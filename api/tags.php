<?php
// tags.php - タグ管理専用API

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json; charset=UTF-8');

// データファイルのパス
$tags_file = __DIR__ . '/../data/tags.json';
$posts_file = __DIR__ . '/../data/posts.json';

// タグファイルの初期化
if (!file_exists($tags_file)) {
    file_put_contents($tags_file, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// タグデータを読み込む関数
function loadTags($tags_file) {
    if (!file_exists($tags_file)) {
        return [];
    }
    $json_data = file_get_contents($tags_file);
    $tags = json_decode($json_data, true);
    return (json_last_error() === JSON_ERROR_NONE && is_array($tags)) ? $tags : [];
}

// タグデータを保存する関数
function saveTags($tags_file, $tags) {
    return file_put_contents($tags_file, json_encode($tags, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// 投稿データを読み込む関数
function loadPosts($posts_file) {
    if (!file_exists($posts_file)) {
        return [];
    }
    $json_data = file_get_contents($posts_file);
    $posts = json_decode($json_data, true);
    return (json_last_error() === JSON_ERROR_NONE && is_array($posts)) ? $posts : [];
}

// タグの使用回数を計算する関数
function calculateTagUsage($tag_name, $posts_file) {
    $posts = loadPosts($posts_file);
    $usage_count = 0;
    
    foreach ($posts as $post) {
        if (isset($post['tags']) && is_array($post['tags']) && in_array($tag_name, $post['tags'])) {
            $usage_count++;
        }
    }
    
    return $usage_count;
}

// 投稿データを更新する関数
function updatePostsTagReferences($posts_file, $old_tag, $new_tag = null) {
    $posts = loadPosts($posts_file);
    $updated = false;
    
    foreach ($posts as &$post) {
        if (isset($post['tags']) && is_array($post['tags'])) {
            $tag_index = array_search($old_tag, $post['tags']);
            if ($tag_index !== false) {
                if ($new_tag === null) {
                    // タグ削除
                    array_splice($post['tags'], $tag_index, 1);
                } else {
                    // タグ更新
                    $post['tags'][$tag_index] = $new_tag;
                }
                $updated = true;
            }
        }
    }
    
    if ($updated) {
        file_put_contents($posts_file, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    return $updated;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // タグ一覧取得
        $tags = loadTags($tags_file);
        
        // 各タグの使用回数を計算
        foreach ($tags as &$tag) {
            $tag['usage'] = calculateTagUsage($tag['name'], $posts_file);
        }
        
        echo json_encode($tags);
        break;
        
    case 'POST':
        // 認証チェック
        if (!isset($_COOKIE['admin_auth_token']) || $_COOKIE['admin_auth_token'] !== 'authenticated_admin') {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Authentication required."]);
            exit();
        }
        
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add') {
            // タグ追加
            $tag_name = trim($_POST['name'] ?? '');
            
            if (empty($tag_name)) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "タグ名が空です。"]);
                exit();
            }
            
            $tags = loadTags($tags_file);
            
            // 重複チェック
            foreach ($tags as $existing_tag) {
                if ($existing_tag['name'] === $tag_name) {
                    http_response_code(400);
                    echo json_encode(["status" => "error", "message" => "このタグは既に存在します。"]);
                    exit();
                }
            }
            
            // 新しいタグを追加
            $new_tag = [
                "id" => uniqid('tag_'),
                "name" => $tag_name,
                "created_at" => date('Y-m-d H:i:s'),
                "usage" => 0
            ];
            
            $tags[] = $new_tag;
            
            if (saveTags($tags_file, $tags)) {
                echo json_encode(["status" => "success", "message" => "タグを追加しました。", "tag" => $new_tag]);
            } else {
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "タグの保存に失敗しました。"]);
            }
            
        } elseif ($action === 'add_bulk') {
            // 一括追加
            $tag_names = json_decode($_POST['names'] ?? '[]', true);
            
            if (!is_array($tag_names) || empty($tag_names)) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "タグ名リストが無効です。"]);
                exit();
            }
            
            $tags = loadTags($tags_file);
            $existing_tag_names = array_column($tags, 'name');
            $added_count = 0;
            
            foreach ($tag_names as $tag_name) {
                $tag_name = trim($tag_name);
                if (!empty($tag_name) && !in_array($tag_name, $existing_tag_names)) {
                    $new_tag = [
                        "id" => uniqid('tag_'),
                        "name" => $tag_name,
                        "created_at" => date('Y-m-d H:i:s'),
                        "usage" => 0
                    ];
                    $tags[] = $new_tag;
                    $existing_tag_names[] = $tag_name;
                    $added_count++;
                }
            }
            
            if (saveTags($tags_file, $tags)) {
                echo json_encode(["status" => "success", "message" => "{$added_count}個のタグを追加しました。", "added_count" => $added_count]);
            } else {
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "タグの保存に失敗しました。"]);
            }
            
        } elseif ($action === 'update') {
            // タグ更新
            $tag_id = $_POST['id'] ?? '';
            $new_name = trim($_POST['name'] ?? '');
            
            if (empty($tag_id) || empty($new_name)) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "タグIDまたは名前が空です。"]);
                exit();
            }
            
            $tags = loadTags($tags_file);
            $found = false;
            $old_name = '';
            
            foreach ($tags as &$tag) {
                if ($tag['id'] === $tag_id) {
                    $old_name = $tag['name'];
                    $tag['name'] = $new_name;
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "タグが見つかりません。"]);
                exit();
            }
            
            // 投稿データも更新
            updatePostsTagReferences($posts_file, $old_name, $new_name);
            
            if (saveTags($tags_file, $tags)) {
                echo json_encode(["status" => "success", "message" => "タグを更新しました。"]);
            } else {
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "タグの保存に失敗しました。"]);
            }
            
        } elseif ($action === 'delete') {
            // タグ削除
            $tag_id = $_POST['id'] ?? '';
            
            if (empty($tag_id)) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "タグIDが空です。"]);
                exit();
            }
            
            $tags = loadTags($tags_file);
            $found = false;
            $tag_name = '';
            
            foreach ($tags as $index => $tag) {
                if ($tag['id'] === $tag_id) {
                    $tag_name = $tag['name'];
                    array_splice($tags, $index, 1);
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "タグが見つかりません。"]);
                exit();
            }
            
            // 投稿データからも削除
            updatePostsTagReferences($posts_file, $tag_name, null);
            
            if (saveTags($tags_file, $tags)) {
                echo json_encode(["status" => "success", "message" => "タグを削除しました。"]);
            } else {
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "タグの保存に失敗しました。"]);
            }
            
        } else {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "無効なアクションです。"]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Method not allowed."]);
        break;
}
?>