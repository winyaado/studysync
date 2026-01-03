<?php
/**
 * API名: content/save_note
 * 説明: content/save_note の処理を行います。
 * 認証: 必須
 * HTTPメソッド: POST
 * 引数:
 *   - POST: content, description, id, lecture_id, title, visibility
 * 返り値:
 *   - JSON: success / error
 * エラー: 400/403/404/405/500
 */
// --- 1. 必須ファイルの読み込み ---

// --- 2. 認証と権限チェック ---

// --- 3. HTTPメソッドの検証 ---
// POSTリクエストでなければ、405エラーを返して処理を終了する
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(405, ['error' => '許可されていないメソッドです。']);
}

// --- 4. パラメータの取得とバリデーション ---
// POSTリクエストから各種パラメータを取得する。IDは整数にキャストし、空の場合はnullとする。
$contentId = !empty($_POST['id']) ? (int)$_POST['id'] : null;
// セッションから現在のユーザーIDを取得する。
$userId = $_SESSION['user_id'];
$title = $_POST['title'] ?? '';
$description = $_POST['description'] ?? null;
$lectureId = isset($_POST['lecture_id']) && $_POST['lecture_id'] !== '' ? $_POST['lecture_id'] : null;
$visibility = $_POST['visibility'] ?? 'private'; // 公開範囲。デフォルトは'private'。
$noteContent = $_POST['content'] ?? ''; // Quill Delta形式のJSON文字列。デフォルトは空文字列。

// タイトルが最大長を超えていないか検証する。
if (mb_strlen($title) > MAX_TITLE_LENGTH) {
    send_json_response(400, ['error' => 'タイトルは' . MAX_TITLE_LENGTH . '文字以内で入力してください。']);
}
// 説明文が最大長を超えていないか検証する。
if (strlen($description) > MAX_DESCRIPTION_SIZE_BYTES) {
    send_json_response(400, ['error' => '説明文が長すぎます。' . (MAX_DESCRIPTION_SIZE_BYTES / 1024) . 'KB以内で入力してください。']);
}
// ノート内容が容量制限を超えていないか検証する。
if (strlen($noteContent) > MAX_NOTE_SIZE_BYTES) {
    send_json_response(413, ['error' => 'ノートの容量が上限（' . (MAX_NOTE_SIZE_BYTES / 1024 / 1024) . 'MB）を超えています。']);
}

// タイトルとノート内容が必須項目であることを検証する。（ノート内容は空文字列でも可だが、未設定は不可）
if (empty($title) || !isset($_POST['content'])) {
    send_json_response(400, ['error' => 'タイトルとノート内容は必須です。']);
}

// 'public'公開を選択した場合、現在のユーザーが管理者権限を持っているか検証する。
if ($visibility === 'public' && (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin'])) {
    send_json_response(403, ['error' => '全体に公開する権限がありません。']);
}

// ノート内容が有効なJSON形式であるか検証する。
json_decode($noteContent);
if (json_last_error() !== JSON_ERROR_NONE) {
    send_json_response(400, ['error' => '無効なノートコンテンツ形式です。']);
}

// --- 5. メイン処理 ---
try {
    // データベース接続を取得し、トランザクションを開始する。
    $pdo = get_pdo_connection();
    $pdo->beginTransaction();

    $new_content_id = $contentId;
    $note_table_id = null; // 'notes'テーブルのID

    if ($contentId) {
        // --- 更新モード ---
        // 既存のノートの所有権を確認し、notesテーブルのIDを取得する。
        $stmt = $pdo->prepare("SELECT c.user_id, c.contentable_id FROM contents c WHERE c.id = ? AND c.contentable_type = 'Note'");
        $stmt->execute([$contentId]);
        $existingContent = $stmt->fetch(PDO::FETCH_ASSOC);

        // コンテンツが見つからない、または現在のユーザーが所有者でない場合は403エラーをスローする。
        if (!$existingContent || $existingContent['user_id'] != $userId) {
            throw new Exception("このノートを更新する権限がありません。", 403);
        }
        $note_table_id = $existingContent['contentable_id'];

        // contentsテーブルのメタデータ（タイトル、説明、講義ID、公開範囲、更新日時）を更新する。
        $stmt = $pdo->prepare("UPDATE contents SET title = ?, description = ?, lecture_id = ?, visibility = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$title, $description, $lectureId, $visibility, $contentId]);

        // notesテーブルのノート内容を更新する。
        $stmt = $pdo->prepare("UPDATE notes SET content = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$noteContent, $note_table_id]);

    } else {
        // --- 新規作成モード ---
        // コンテンツ作成上限数を超えていないか確認する。
        $stmt = $pdo->prepare("SELECT content_limit FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $limit = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(id) FROM contents WHERE user_id = ? AND deleted_at IS NULL");
        $stmt->execute([$userId]);
        $count = $stmt->fetchColumn();

        // 上限を超えている場合は403エラーをスローする。
        if ($count >= $limit) {
            throw new Exception("コンテンツの作成上限（{$limit}件）に達しました。", 403);
        }

        // notesテーブルにノート内容を挿入し、そのIDを取得する。
        $stmt = $pdo->prepare("INSERT INTO notes (user_id, content) VALUES (?, ?)");
        $stmt->execute([$userId, $noteContent]);
        $note_table_id = $pdo->lastInsertId();

        // ノートコンテンツの保存に失敗した場合は500エラーをスローする。
        if (!$note_table_id) {
            throw new Exception("ノートコンテンツの保存に失敗しました。", 500);
        }

        // contentsテーブルに多態的なリレーション情報を含む新しいコンテンツを挿入する。
        $stmt = $pdo->prepare(
            "INSERT INTO contents (user_id, lecture_id, contentable_id, contentable_type, title, description, status, visibility) 
             VALUES (?, ?, ?, 'Note', ?, ?, 'published', ?)"
        );
        $stmt->execute([$userId, $lectureId, $note_table_id, $title, $description, $visibility]);
        $new_content_id = $pdo->lastInsertId(); // 新しく挿入されたコンテンツのIDを取得する。
    }

    // トランザクションをコミットする。
    $pdo->commit();

    // 処理成功のレスポンスと、更新または新規作成されたコンテンツのIDをJSON形式で返す。
    send_json_response(200, [
        'success' => true,
        'status' => $contentId ? 'updated' : 'created',
        'content_id' => $new_content_id
    ]);

} catch (Exception $e) {
    // 例外が発生し、トランザクションがアクティブな場合はロールバックする。
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // エラーハンドラに処理を委譲する。
    handle_exception($e);
}
