<?php
/**
 * API名: user/delete_favorite_seed
 * 説明: user/delete_favorite_seed の処理を行います。
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
// JSON形式のリクエストボディから削除対象のシード値を取得する。
$input = get_json_input();
$seed_to_delete = $input['seed'] ?? null;
// セッションから現在のユーザーIDを取得する。
$userId = $_SESSION['user_id'];

// シード値が必須であり、有効な数値であるか検証する。
if (!is_numeric($seed_to_delete)) {
    send_json_response(400, ['error' => '無効なシード値です。']);
}
$seed_to_delete = (int)$seed_to_delete;

// --- 5. メイン処理 ---
try {
    // データベース接続を取得し、トランザクションを開始する。
    $pdo = get_pdo_connection();
    $pdo->beginTransaction();
    
    // 指定されたシードのお気に入りを、ユーザーIDで絞り込んで削除する。
    // これにより、他人のリソースを削除できないようにする。
    $stmt = $pdo->prepare("DELETE FROM favorite_identicon_seeds WHERE user_id = ? AND seed = ?");
    $stmt->execute([$userId, $seed_to_delete]);

    // 更新後の最新のお気に入りシードリストを取得する。
    $stmt = $pdo->prepare("SELECT seed FROM favorite_identicon_seeds WHERE user_id = ? ORDER BY created_at ASC");
    $stmt->execute([$userId]);
    $favorite_seeds_raw = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $favorites = array_map('intval', $favorite_seeds_raw);

    // トランザクションをコミットする。
    $pdo->commit();

    // 処理成功のレスポンスと、更新後のお気に入りシードリストをJSON形式で返す。
    send_json_response(200, [
        "success" => true,
        "favorite_seeds" => $favorites
    ]);

} catch (Exception $e) {
    // 例外が発生し、トランザクションがアクティブな場合はロールバックする。
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // エラーハンドラに処理を委譲する。
    handle_exception($e);
}
