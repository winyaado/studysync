<?php
/**
 * API名: content/get_flashcard_for_edit
 * 説明: content/get_flashcard_for_edit の処理を行います。
 * 認証: 必須
 * HTTPメソッド: GET
 * 引数:
 *   - GET: id
 * 返り値:
 *   - JSON: success / error
 * エラー: 400/403/404/405/500
 */
// --- 1. 必須ファイルの読み込み ---

// --- 2. 認証と権限チェック ---

// --- 3. HTTPメソッドの検証 ---
// GETリクエストでなければ、405エラーを返して処理を終了する
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_response(405, ['error' => '許可されていないメソッドです。']);
}

// --- 4. パラメータの取得とバリデーション ---
// GETリクエストからコンテンツIDを取得する。指定がなければnullとする。
$contentId = $_GET['id'] ?? null;
// セッションから現在のユーザーIDを取得する。
$userId = $_SESSION['user_id'];

// コンテンツIDが必須であり、有効な整数であるか検証する。
if (!$contentId || !filter_var($contentId, FILTER_VALIDATE_INT)) {
    send_json_response(400, ['error' => '無効なコンテンツIDです。']);
}

// --- 5. メイン処理 ---
try {
    // データベース接続を取得する。
    $pdo = get_pdo_connection();

    // 1. フラッシュカードセットの詳細情報を取得する。
    // 指定されたコンテンツIDが現在のユーザーによって所有されているかを確認する。
    // 講義名などの関連情報も結合して取得する。
    $sql = "
        SELECT 
            c.title, 
            c.description, 
            c.lecture_id, 
            l.name as lecture_name,
            c.visibility,
            f.id as flashcard_table_id
        FROM contents c
        JOIN users u ON c.user_id = u.id
        JOIN flashcards f ON c.contentable_id = f.id AND c.contentable_type = 'FlashCard'
        LEFT JOIN lectures l ON c.lecture_id = l.lecture_code AND l.tenant_id = u.tenant_id
        WHERE c.id = ? AND c.user_id = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$contentId, $userId]);
    $details = $stmt->fetch(PDO::FETCH_ASSOC);

    // フラッシュカードセットが見つからない、または編集権限がない場合は404エラーをスローする。
    if (!$details) {
        throw new Exception("指定されたフラッシュカードセットが見つからないか、編集権限がありません。", 404);
    }

    // 2. 取得したflashcard_table_idを使って、関連する単語リストを取得する。表示順でソートする。
    $stmt = $pdo->prepare("
        SELECT id, word, definition, display_order 
        FROM flashcard_words 
        WHERE flashcard_id = ? 
        ORDER BY display_order ASC
    ");
    $stmt->execute([$details['flashcard_table_id']]);
    $words = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 取得した詳細情報と単語リストをJSON形式で返す。
    send_json_response(200, [
        'details' => $details,
        'words' => $words
    ]);

} catch (Exception $e) {
    // 例外が発生した場合は、エラーハンドラに処理を委譲する。
    handle_exception($e);
}
