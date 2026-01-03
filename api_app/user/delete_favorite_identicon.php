<?php
/**
 * API名: user/delete_favorite_identicon
 * 説明: user/delete_favorite_identicon の処理を行います。
 * 認証: 必須
 * HTTPメソッド: POST
 * 引数:
 *   - なし
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
// JSON形式のリクエストボディから削除対象のIdenticon IDを取得する。
$input = get_json_input();
$identicon_id_to_delete = $input['id'] ?? null;

// Identicon IDが必須であり、有効な数値であるか検証する。
if (!is_numeric($identicon_id_to_delete)) {
    send_json_response(400, ['error' => '無効なIdenticon IDです。']);
}
$identicon_id_to_delete = (int)$identicon_id_to_delete;

// --- 5. メイン処理 ---
try {
    // データベース接続を取得し、トランザクションを開始する。
    $pdo = get_pdo_connection();
    $userId = $_SESSION['user_id'];
    $pdo->beginTransaction();
    
    // 指定されたIDのお気に入りを、ユーザーIDで絞り込んで削除する。
    // これにより、他人のリソースを削除できないようにする。
    $stmt = $pdo->prepare("DELETE FROM favorite_identicons WHERE id = ? AND user_id = ?");
    $stmt->execute([$identicon_id_to_delete, $userId]);

    // 更新後の最新のお気に入りリストを取得する。
    $stmt = $pdo->prepare("SELECT id, name, identicon_data FROM favorite_identicons WHERE user_id = ? ORDER BY created_at ASC");
    $stmt->execute([$userId]);
    $favorite_identicons = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // トランザクションをコミットする。
    $pdo->commit();

    // 処理成功のレスポンスと、更新後のお気に入りリストをJSON形式で返す。
    send_json_response(200, [
        "success" => true,
        "favorite_identicons" => $favorite_identicons
    ]);

} catch (Exception $e) {
    // 例外が発生し、トランザクションがアクティブな場合はロールバックする。
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // エラーハンドラに処理を委譲する。
    handle_exception($e);
}
