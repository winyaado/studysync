<?php
/**
 * API名: admin/delete_information
 * 説明: admin/delete_information の処理を行います。
 * 認証: 管理者のみ
 * HTTPメソッド: POST
 * 引数:
 *   - POST: id
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
// POSTリクエストから削除対象のIDを取得する。整数にキャストし、空の場合はnullとする。
$id = !empty($_POST['id']) ? (int)$_POST['id'] : null;

// IDが指定されていない場合は、400エラーを返して処理を終了する。
if (empty($id)) {
    send_json_response(400, ['error' => 'IDは必須です。']);
}

// --- 5. メイン処理 ---
try {
    // データベース接続を取得する。
    $pdo = get_pdo_connection();

    // 指定されたIDのインフォメーションをinformationsテーブルから削除する。
    $stmt = $pdo->prepare("DELETE FROM informations WHERE id = ?");
    $stmt->execute([$id]);

    // 削除された行数をチェックする。
    if ($stmt->rowCount() > 0) {
        // 1行以上削除された場合は、成功レスポンスを返す。
        send_json_response(200, ['success' => true, 'message' => 'インフォメーションを削除しました。']);
    } else {
        // 削除された行が0行の場合は、対象が見つからなかったとして404エラーをスローする。
        throw new Exception("指定されたIDのインフォメーションが見つかりませんでした。", 404);
    }

} catch (Exception $e) {
    // 例外が発生した場合は、エラーハンドラに処理を委譲する。
    handle_exception($e);
}
