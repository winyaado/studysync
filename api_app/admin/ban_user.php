<?php
/**
 * API名: admin/ban_user
 * 説明: admin/ban_user の処理を行います。
 * 認証: 管理者のみ
 * HTTPメソッド: POST
 * 引数:
 *   - POST: action, user_id
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
// POSTリクエストでなければ、405エラーを返して処理を終了する
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(405, ['error' => '許可されていないメソッドです。']);
}

// --- 4. パラメータの取得とバリデーション ---
// POSTリクエストからユーザーIDとアクションを取得する。なければnull。
$userId = $_POST['user_id'] ?? null;
$action = $_POST['action'] ?? null; // 'ban' または 'unban'

// ユーザーIDが整数であること、アクションが'ban'または'unban'であることを検証する。
if (!$userId || !filter_var($userId, FILTER_VALIDATE_INT) || !in_array($action, ['ban', 'unban'])) {
    send_json_response(400, ['error' => '無効なユーザーIDまたはアクションです。']);
}

// 管理者自身をBANしようとしていないか検証する。
if ($userId == $_SESSION['user_id'] && $action === 'ban') {
    send_json_response(403, ['error' => '自分自身をBANすることはできません。']);
}

// --- 5. メイン処理 ---
try {
    // アクションに応じて、BAN状態を更新するSQLの日時データを決定する。'ban'の場合は現在時刻、'unban'の場合はNULL。
    $bannedAt = ($action === 'ban') ? date('Y-m-d H:i:s') : null;

    // データベース接続を準備し、usersテーブルのbanned_atを更新する。
    $pdo = get_pdo_connection();
    $stmt = $pdo->prepare("UPDATE users SET banned_at = ? WHERE id = ?");
    $stmt->execute([$bannedAt, $userId]);

    // 更新された行が0行の場合は、指定されたユーザーが存在しないため404エラーを返す。
    if ($stmt->rowCount() === 0) {
        send_json_response(404, ['error' => '指定されたユーザーが見つかりません。']);
    }

    // 処理成功のレスポンスをJSON形式で返す。
    send_json_response(200, [
        'success' => true,
        'message' => "ユーザーID {$userId} を" . ($action === 'ban' ? 'BANしました。' : 'BAN解除しました。')
    ]);

} catch (Exception $e) {
    // 例外が発生した場合は、エラーハンドラに処理を委譲する。
    handle_exception($e);
}
