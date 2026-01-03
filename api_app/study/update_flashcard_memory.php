<?php
/**
 * API名: study/update_flashcard_memory
 * 説明: study/update_flashcard_memory の処理を行います。
 * 認証: 必須
 * HTTPメソッド: POST
 * 引数:
 *   - POST: flashcard_word_id, memory_level
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
// セッションから現在のユーザーIDとテナントIDを取得する。
$userId = $_SESSION['user_id'];
$currentUserTenantId = $_SESSION['tenant_id'] ?? null;
// POSTリクエストから単語IDと習熟度レベルを取得する。
$flashcardWordId = $_POST['flashcard_word_id'] ?? null;
$memoryLevel = $_POST['memory_level'] ?? null;

// 単語IDが必須であり、有効な整数であるか検証する。
// 習熟度レベルが必須であり、許可された値（0, 1, 2）のいずれかであるか検証する。
if (!$flashcardWordId || !filter_var($flashcardWordId, FILTER_VALIDATE_INT) ||
    !isset($memoryLevel) || !in_array($memoryLevel, [0, 1, 2])) {
    send_json_response(400, ['error' => '無効なパラメータです。']);
}

// --- 5. メイン処理 ---
try {
    // データベース接続を取得する。
    $pdo = get_pdo_connection();

    // ユーザーがこの単語を「閲覧」する権限があるかを確認する。
    // これにより、ライブラリに追加していないセットの単語でも「お試し」で学習進捗を記録できる。
    // コンテンツは公開済み、削除されていない、かつ、公開設定（public, 自身が所有, 同一テナント）に基づいてアクセス可能である必要がある。
    $sql_access_check = "
        SELECT COUNT(fw.id) 
        FROM flashcard_words fw
        JOIN flashcards f ON fw.flashcard_id = f.id
        JOIN contents c ON f.id = c.contentable_id AND c.contentable_type = 'FlashCard'
        JOIN users u ON c.user_id = u.id
        WHERE fw.id = ?
          AND c.status = 'published'
          AND c.deleted_at IS NULL
          AND (
            c.visibility = 'public'
            OR c.user_id = ?
            OR (c.visibility = 'domain' AND u.tenant_id = ?)
          )
    ";
    $stmt_access = $pdo->prepare($sql_access_check);
    $stmt_access->execute([$flashcardWordId, $userId, $currentUserTenantId]);
    
    // アクセス権がない場合は403エラーをスローする。
    if ($stmt_access->fetchColumn() == 0) {
        throw new Exception("この単語を更新する権限がありません。", 403);
    }
    
    // 習熟度をuser_flashcard_word_memoryテーブルに挿入または更新する。
    // ON DUPLICATE KEY UPDATE句により、既に習熟度データが存在する場合は更新、存在しない場合は新規挿入となる。
    $sql_update = "
        INSERT INTO user_flashcard_word_memory (user_id, flashcard_word_id, memory_level)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE memory_level = VALUES(memory_level)
    ";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([$userId, $flashcardWordId, $memoryLevel]);

    // 処理成功のレスポンスをJSON形式で返す。
    send_json_response(200, ['success' => true]);

} catch (Exception $e) {
    // 例外が発生した場合は、エラーハンドラに処理を委譲する。
    handle_exception($e);
}
