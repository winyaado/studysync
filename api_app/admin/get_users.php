<?php
/**
 * API名: admin/get_users
 * 説明: admin/get_users の処理を行います。
 * 認証: 管理者のみ
 * HTTPメソッド: GET
 * 引数:
 *   - GET: q
 * 返り値:
 *   - JSON: success / error
 * エラー: 400/403/404/405/500
 */
// --- 1. 必須ファイルの読み込み ---

// --- 2. 認証と権限チェック ---
// 管理者でない場合は、403エラーを返して処理を終了する
if (!$_SESSION['is_admin']) {
    send_json_response(403, ['error' => 'この操作を行う権限がありません。']);
}

// --- 3. HTTPメソッドの検証 ---
// GETリクエストでなければ、405エラーを返して処理を終了する
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_response(405, ['error' => '許可されていないメソッドです。']);
}

// --- 4. パラメータの取得とバリデーション ---
// GETリクエストから検索クエリを取得する。指定がなければ空文字列として扱う。
$searchQuery = $_GET['q'] ?? '';

// 検索クエリが最大長を超えている場合は、400エラーを返して処理を終了する。
if (mb_strlen($searchQuery) > MAX_SEARCH_QUERY_LENGTH) {
    send_json_response(400, ['error' => '検索クエリは' . MAX_SEARCH_QUERY_LENGTH . '文字以内で入力してください。']);
}

// データベースのLIKE句で使用するため、検索クエリの前後にワイルドカードを追加する。
$searchParam = '%' . $searchQuery . '%'; 

// --- 5. メイン処理 ---
try {
    // データベース接続を取得する。
    $pdo = get_pdo_connection();

    // ユーザー情報をusers, user_profiles, tenantsテーブルから取得するSQLを準備する。
    // ユーザー名またはメールアドレスが検索クエリに一致するユーザーを対象とする。
    $sql = "
        SELECT 
            u.id, 
            COALESCE(up.username, u.email) as username, 
            u.email, 
            u.banned_at,
            t.name AS tenant_name,
            t.domain_identifier
        FROM users u
        LEFT JOIN tenants t ON u.tenant_id = t.id
        LEFT JOIN user_profiles up ON u.id = up.user_id
        WHERE (COALESCE(up.username, u.email) LIKE :search_query OR u.email LIKE :search_query)
        ORDER BY u.id DESC
    ";
    $stmt = $pdo->prepare($sql);
    // 検索パラメータをSQLにバインドする。
    $stmt->bindValue(':search_query', $searchParam, PDO::PARAM_STR); 
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 取得したユーザーリストをJSON形式で返す。
    send_json_response(200, ['success' => true, 'users' => $users]);

} catch (Exception $e) {
    // 例外が発生した場合は、エラーハンドラに処理を委譲する。
    handle_exception($e);
}
