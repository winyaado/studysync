<?php
/**
 * API名: content/check_creation_allowance
 * 説明: content/check_creation_allowance の処理を行います。
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
// このAPIは明示的なパラメータを受け取らないため、このセクションでの主要な処理はありません。
// セッションからユーザーIDを取得していますが、これはAPIのパラメータとは異なります。
$userId = $_SESSION['user_id'];
// 取得すべきパラメータがないため、バリデーションも不要です。

// --- 5. メイン処理 ---
try {
    // データベース接続を取得する。
    $pdo = get_pdo_connection();

    // 現在のユーザーのコンテンツ作成上限数をusersテーブルから取得する。
    $stmt = $pdo->prepare("SELECT content_limit FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $limit = $stmt->fetchColumn();

    // 現在のユーザーが作成している、削除されていないコンテンツの数をcontentsテーブルから取得する。
    $stmt = $pdo->prepare("SELECT COUNT(id) FROM contents WHERE user_id = ? AND deleted_at IS NULL");
    $stmt->execute([$userId]);
    $count = $stmt->fetchColumn();
    
    // 現在のコンテンツ数が上限未満であれば、コンテンツ作成が許可されていると判断する。
    $isAllowed = $count < $limit;

    // 許可状況、現在のコンテンツ数、および上限数をJSON形式で返す。
    send_json_response(200, [
        'allowed' => $isAllowed,
        'count' => (int)$count,
        'limit' => (int)$limit
    ]);

} catch (Exception $e) {
    // 例外が発生した場合は、エラーハンドラに処理を委譲する。
    handle_exception($e);
}
