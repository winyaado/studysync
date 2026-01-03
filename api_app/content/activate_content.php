<?php
/**
 * API名: content/activate_content
 * 説明: content/activate_content の処理を行います。
 * 認証: 必須
 * HTTPメソッド: POST
 * 引数:
 *   - POST: content_id
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
// POSTリクエストからコンテンツIDを取得する。指定がなければnullとする。
$contentId = $_POST['content_id'] ?? null;

// コンテンツIDが必須であり、有効な整数であるか検証する。
if (!$contentId || !filter_var($contentId, FILTER_VALIDATE_INT)) {
    send_json_response(400, ['error' => '無効なコンテンツIDです。']);
}

// --- 5. メイン処理 ---
try {
    // データベース接続を取得する。
    $pdo = get_pdo_connection();

    // 1. 指定されたコンテンツが存在し、現在のユーザーがアクセス可能であるかを確認する。
    // コンテンツは公開済み、削除されていない、かつ、公開設定（public, 自身が所有, 同一テナント）に基づいてアクセス可能である必要がある。
    $sql_access_check = "
        SELECT c.id FROM contents c
        JOIN users u ON c.user_id = u.id
        WHERE c.id = ?
          AND c.status = 'published'
          AND c.deleted_at IS NULL
          AND (
            c.visibility = 'public'
            OR c.user_id = ?
            OR (c.visibility = 'domain' AND u.tenant_id = ?)
          )
    ";
    $stmt_access = $pdo->prepare($sql_access_check);
    $stmt_access->execute([$contentId, $userId, $currentUserTenantId]);

    // アクセス可能なコンテンツが見つからなければ、404エラーをスローする。
    if ($stmt_access->fetchColumn() === false) {
        throw new Exception("対象のコンテンツが見つからないか、アクセス権がありません。", 404);
    }

    // 2. user_active_contents テーブルにユーザーとコンテンツの関連を挿入する。
    // INSERT IGNORE を使用することで、既に存在する場合はエラーとならず、重複挿入を無視する。
    $stmt_insert = $pdo->prepare("INSERT IGNORE INTO user_active_contents (user_id, content_id, added_at) VALUES (?, ?, NOW())");
    $stmt_insert->execute([$userId, $contentId]);

    // 挿入された行があるか（新しい関連が追加されたか）に基づいてステータスメッセージを決定する。
    $status = ($stmt_insert->rowCount() > 0) ? 'added' : 'exists';

    // 処理成功のレスポンスとステータスをJSON形式で返す。
    send_json_response(200, ['success' => true, 'status' => $status]);

} catch (Exception $e) {
    // 例外が発生した場合は、エラーハンドラに処理を委譲する。
    handle_exception($e);
}
