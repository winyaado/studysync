<?php
/**
 * API名: content/get_note_for_edit
 * 説明: content/get_note_for_edit の処理を行います。
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
// セッションから現在のユーザーIDとテナントIDを取得する。
$userId = $_SESSION['user_id'];
$currentUserTenantId = $_SESSION['tenant_id'] ?? null;

// コンテンツIDが必須であり、有効な整数であるか検証する。
if (!$contentId || !filter_var($contentId, FILTER_VALIDATE_INT)) {
    send_json_response(400, ['error' => '無効なコンテンツIDです。']);
}

// --- 5. メイン処理 ---
try {
    // データベース接続を取得する。
    $pdo = get_pdo_connection();

    // ノートの基本情報、コンテンツ、および評価情報を取得する。
    // コンテンツは公開済み、削除されていない、かつ、公開設定（public, 自身が所有, 同一テナント）に基づいてアクセス可能である必要がある。
    // 講義情報、作者名、編集権限、平均評価、ユーザー自身の評価も取得する。
    $sql = "
        SELECT 
            c.id, 
            c.title, 
            c.description, 
            c.lecture_id, 
            l.name as lecture_name, 
            c.visibility,
            c.user_id AS owner_user_id,
            up.username AS author_name,
            (c.user_id = :current_session_user_id) AS can_edit,
            n.content AS note_content,
            (SELECT AVG(r.rating) FROM ratings r WHERE r.rateable_id = c.id AND r.rateable_type = 'Note') as avg_rating,
            (SELECT COUNT(r.id) FROM ratings r WHERE r.rateable_id = c.id AND r.rateable_type = 'Note') as rating_count,
            ur.rating as user_rating
        FROM contents c
        JOIN users u ON c.user_id = u.id
        LEFT JOIN user_profiles up ON u.id = up.user_id
        LEFT JOIN lectures l ON c.lecture_id = l.lecture_code AND l.tenant_id = u.tenant_id
        JOIN notes n ON c.contentable_id = n.id AND c.contentable_type = 'Note'
        LEFT JOIN ratings ur ON ur.rateable_id = c.id AND ur.rateable_type = 'Note' AND ur.user_id = :current_session_user_id
        WHERE c.id = :content_id 
          AND c.status = 'published'
          AND (
               c.visibility = 'public'
               OR c.user_id = :current_session_user_id
               OR (c.visibility = 'domain' AND u.tenant_id = :current_user_tenant_id)
              )
          AND c.deleted_at IS NULL
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':content_id', $contentId, PDO::PARAM_INT);
    $stmt->bindValue(':current_session_user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':current_user_tenant_id', $currentUserTenantId, PDO::PARAM_INT);
    $stmt->execute();
    $noteData = $stmt->fetch(PDO::FETCH_ASSOC);

    // ノートが見つからない、またはアクセス権がない場合は404エラーをスローする。
    if (!$noteData) {
        throw new Exception("指定されたノートが見つからないか、アクセス権がありません。", 404);
    }

    // 取得したノートの詳細とコンテンツをJSON形式で返す。
    send_json_response(200, [
        'details' => [
            'id' => $noteData['id'],
            'title' => $noteData['title'],
            'description' => $noteData['description'],
            'lecture_id' => $noteData['lecture_id'],
            'lecture_name' => $noteData['lecture_name'],
            'visibility' => $noteData['visibility'],
            'owner_user_id' => $noteData['owner_user_id'],
            'author_name' => $noteData['author_name'],
            'can_edit' => (bool)$noteData['can_edit'],
            'avg_rating' => $noteData['avg_rating'] ? (float)$noteData['avg_rating'] : 0,
            'rating_count' => (int)$noteData['rating_count'],
            'user_rating' => $noteData['user_rating'] ? (int)$noteData['user_rating'] : 0
        ],
        'content' => $noteData['note_content'] // Quill Delta形式のJSON文字列
    ]);

} catch (Exception $e) {
    // 例外が発生した場合は、エラーハンドラに処理を委譲する。
    handle_exception($e);
}
