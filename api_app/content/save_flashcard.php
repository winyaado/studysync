<?php
/**
 * API名: content/save_flashcard
 * 説明: content/save_flashcard の処理を行います。
 * 認証: 必須
 * HTTPメソッド: POST
 * 引数:
 *   - POST: description, id, lecture_id, title, visibility, words, words_to_delete
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
$wordsJson = $_POST['words'] ?? '[]'; // 単語と定義のJSON配列。デフォルトは空配列のJSON文字列。
$wordsToDeleteJson = $_POST['words_to_delete'] ?? '[]'; // 削除対象の単語IDのJSON配列。デフォルトは空配列のJSON文字列。

// タイトルが最大長を超えていないか検証する。
if (mb_strlen($title) > MAX_TITLE_LENGTH) {
    send_json_response(400, ['error' => 'タイトルは' . MAX_TITLE_LENGTH . '文字以内で入力してください。']);
}
// 説明文が最大長を超えていないか検証する。
if (strlen($description) > MAX_DESCRIPTION_SIZE_BYTES) {
    send_json_response(400, ['error' => '説明文が長すぎます。' . (MAX_DESCRIPTION_SIZE_BYTES / 1024) . 'KB以内で入力してください。']);
}

// 'public'公開を選択した場合、現在のユーザーが管理者権限を持っているか検証する。
if ($visibility === 'public' && (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin'])) {
    send_json_response(403, ['error' => '全体に公開する権限がありません。']);
}

// 単語データ (wordsJson) をJSONデコードし、デコードエラーがないか検証する。
$words = json_decode($wordsJson, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    send_json_response(400, ['error' => '単語データが不正なJSON形式です。']);
}
// 削除対象単語データ (wordsToDeleteJson) をJSONデコードし、デコードエラーがないか検証する。
$wordsToDelete = json_decode($wordsToDeleteJson, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    send_json_response(400, ['error' => '削除単語データが不正なJSON形式です。']);
}

// 単語数が上限を超えていないか検証する。
if (count($words) > MAX_FLASHCARD_WORDS_PER_SET) {
    send_json_response(400, ['error' => '一度に登録できる単語数は' . MAX_FLASHCARD_WORDS_PER_SET . '個までです。']);
}
// 単語が少なくとも1つは存在するか検証する。
if (empty($words)) {
    send_json_response(400, ['error' => '少なくとも1つの単語が必要です。']);
}

// 各単語のデータ（wordとdefinition）について、存在と最大長、空でないことを検証する。
foreach ($words as $index => $wordData) {
    $wordNumber = $index + 1; // ユーザー向けに1から始まる番号
    if (!isset($wordData['word']) || mb_strlen($wordData['word']) > MAX_TEXT_SIZE_BYTES || empty($wordData['word'])) {
        send_json_response(400, ['error' => "単語{$wordNumber}（表面）が長すぎるか、空です。"]);
    }
    if (!isset($wordData['definition']) || mb_strlen($wordData['definition']) > MAX_TEXT_SIZE_BYTES || empty($wordData['definition'])) {
        send_json_response(400, ['error' => "単語{$wordNumber}（裏面）が長すぎるか、空です。"]);
    }
}

// --- 5. メイン処理 ---
try {
    // データベース接続を取得し、トランザクションを開始する。
    $pdo = get_pdo_connection();
    $pdo->beginTransaction();

    $new_content_id = $contentId;
    $flashcardTableId = null; // flashcardsテーブルのID

    if ($contentId) {
        // --- 更新モード ---
        // 既存のフラッシュカードセットの所有権を確認し、flashcardsテーブルのIDを取得する。
        $stmt = $pdo->prepare("SELECT user_id, contentable_id FROM contents WHERE id = ? AND contentable_type = 'FlashCard'");
        $stmt->execute([$contentId]);
        $content = $stmt->fetch(PDO::FETCH_ASSOC);

        // コンテンツが見つからない、または現在のユーザーが所有者でない場合は403エラーをスローする。
        if (!$content || $content['user_id'] != $userId) {
            throw new Exception("このコンテンツを更新する権限がありません。", 403);
        }
        $flashcardTableId = $content['contentable_id'];

        // contentsテーブルのメタデータ（タイトル、説明、講義ID、公開範囲、更新日時）を更新する。
        $stmt = $pdo->prepare("UPDATE contents SET title = ?, description = ?, lecture_id = ?, visibility = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$title, $description, $lectureId, $visibility, $contentId]);

        // flashcardsテーブルはコンテナとしての役割なので、直接的な更新は不要。

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

        // flashcardsテーブルに新しいセットを挿入し、そのIDを取得する。
        $stmt = $pdo->prepare("INSERT INTO flashcards (user_id) VALUES (?)");
        $stmt->execute([$userId]);
        $flashcardTableId = $pdo->lastInsertId();

        // contentsテーブルに多態的なリレーション情報を含む新しいコンテンツを挿入する。
        $stmt = $pdo->prepare(
            "INSERT INTO contents (user_id, lecture_id, contentable_id, contentable_type, title, description, status, visibility) 
             VALUES (?, ?, ?, 'FlashCard', ?, ?, 'published', ?)"
        );
        $stmt->execute([$userId, $lectureId, $flashcardTableId, $title, $description, $visibility]);
        $new_content_id = $pdo->lastInsertId(); // 新しく挿入されたコンテンツのIDを取得する。
    }

    // --- 単語の管理（削除、更新、挿入） ---
    // 1. 削除対象の単語が存在する場合、flashcard_wordsテーブルから削除する。
    if (!empty($wordsToDelete)) {
        // SQLインジェクションを防ぐため、IDのプレースホルダーを動的に生成する。
        $placeholders = implode(',', array_fill(0, count($wordsToDelete), '?'));
        $stmt = $pdo->prepare("DELETE FROM flashcard_words WHERE id IN ($placeholders) AND flashcard_id = ?");
        $stmt->execute(array_merge($wordsToDelete, [$flashcardTableId]));
    }

    // 2. 各単語データについて、既存なら更新、新規なら挿入する。
    foreach ($words as $wordData) {
        $wordId = $wordData['id'] ?? null;
        $wordText = $wordData['word'];
        $definitionText = $wordData['definition'];
        $displayOrder = $wordData['display_order'];

        if ($wordId) {
            // 既存の単語の場合は、word, definition, display_order, updated_atを更新する。
            $stmt = $pdo->prepare("UPDATE flashcard_words SET word = ?, definition = ?, display_order = ?, updated_at = NOW() WHERE id = ? AND flashcard_id = ?");
            $stmt->execute([$wordText, $definitionText, $displayOrder, $wordId, $flashcardTableId]);
        } else {
            // 新しい単語の場合は、flashcard_id, word, definition, display_orderを挿入する。
            $stmt = $pdo->prepare("INSERT INTO flashcard_words (flashcard_id, word, definition, display_order) VALUES (?, ?, ?, ?)");
            $stmt->execute([$flashcardTableId, $wordText, $definitionText, $displayOrder]);
        }
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
