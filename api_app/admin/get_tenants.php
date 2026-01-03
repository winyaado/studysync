<?php
/**
 * API名: admin/get_tenants
 * 説明: admin/get_tenants の処理を行います。
 * 認証: 管理者のみ
 * HTTPメソッド: GET
 * 引数:
 *   - なし
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

// --- 5. メイン処理 ---
try {
    // データベース接続を取得する。
    $pdo = get_pdo_connection();

    // tenantsテーブルからすべてのテナント情報を取得する。新しいものが先に表示されるようIDの降順でソートする。
    $sql = "
        SELECT 
            id, 
            name, 
            domain_identifier,
            banned_at
        FROM tenants
        ORDER BY id DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 取得したテナントリストをJSON形式で返す。
    send_json_response(200, ['success' => true, 'tenants' => $tenants]);

} catch (Exception $e) {
    // 例外が発生した場合は、エラーハンドラに処理を委譲する。
    handle_exception($e);
}
