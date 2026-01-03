<?php
/**
 * API名: content/my_contents
 * 説明: content/my_contents の処理を行います。
 * 認証: 必須
 * HTTPメソッド: GET
 * 引数:
 *   - なし
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
// このAPIは明示的なパラメータを受け取らないため、このセクションでの処理はありません。
// セッションからユーザーIDを取得していますが、これはAPIのパラメータとは異なります。
$userId = $_SESSION['user_id'];
// 取得すべきパラメータがないため、バリデーションも不要です。

// --- 5. メイン処理 ---
try {
    // データベース接続を取得する。
    $pdo = get_pdo_connection();

    // 現在のユーザーのコンテンツ作成上限数をusersテーブルから取得する。
    $limit_stmt = $pdo->prepare("SELECT content_limit FROM users WHERE id = ?");
    $limit_stmt->execute([$userId]);
    $limit = $limit_stmt->fetchColumn();

    // 現在のユーザーが作成した、削除されていないコンテンツの一覧をcontentsテーブルから取得する。
    // 関連する講義名も結合して取得し、更新日時で降順にソートする。
    $stmt = $pdo->prepare("
        SELECT c.id, c.title, c.lecture_id, l.name AS lecture_name, c.contentable_type, c.status, c.visibility, c.updated_at
        FROM contents c
        JOIN users u ON c.user_id = u.id
        LEFT JOIN lectures l ON c.lecture_id = l.lecture_code AND l.tenant_id = u.tenant_id
        WHERE c.user_id = ? AND c.deleted_at IS NULL
        ORDER BY updated_at DESC
    ");
    $stmt->execute([$userId]);
    $myContents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 現在のコンテンツ数を計算する。
    $current_count = count($myContents);

    // 取得したコンテンツ一覧と使用状況（作成数、上限数）をJSON形式で返す。
    send_json_response(200, [
        'contents' => $myContents,
        'usage' => [
            'count' => $current_count,
            'limit' => (int)$limit
        ]
    ]);

} catch (Exception $e) {
    // 例外が発生した場合は、エラーハンドラに処理を委譲する。
    handle_exception($e);
}
