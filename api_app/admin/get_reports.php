<?php
/**
 * API名: admin/get_reports
 * 説明: admin/get_reports の処理を行います。
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
    send_json_response(403, ['error' => '管理者権限がありません。']);
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

    // 通報情報を取得するSQLを準備する。reportsテーブルを主軸に、通報者情報(users, user_profiles)とコンテンツ情報(contents)を結合する。
    $sql = "
        SELECT
            r.id,
            r.reporting_user_id,
            up_reporter.username AS reporting_username,
            r.content_id,
            c.title AS content_title,
            c.contentable_type AS content_type,
            r.reason_category,
            r.reason_details,
            r.status,
            r.created_at
        FROM
            reports r
        JOIN
            users u_reporter ON r.reporting_user_id = u_reporter.id
        LEFT JOIN
            user_profiles up_reporter ON u_reporter.id = up_reporter.user_id
        JOIN
            contents c ON r.content_id = c.id
        ORDER BY
            r.created_at DESC
    ";

    // SQLを実行し、すべての通報情報を取得する。
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 取得した通報リストをJSON形式で返す。
    send_json_response(200, ['reports' => $reports]);

} catch (Exception $e) {
    // 例外が発生した場合は、エラーハンドラに処理を委譲する。
    handle_exception($e);
}
