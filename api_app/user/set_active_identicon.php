<?php
/**
 * API名: user/set_active_identicon
 * 説明: user/set_active_identicon の処理を行います。
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
// JSON形式のリクエストボディから設定対象のIdenticonデータを取得する。
$input = get_json_input();
$seeds_to_set = $input['identicon_data'] ?? null;
// セッションから現在のユーザーIDを取得する。
$userId = $_SESSION['user_id'];

// Identiconデータが連想配列で、必要なキー（color, p1, p2）がすべて存在するか検証する。
if (!is_array($seeds_to_set) || !isset($seeds_to_set['color']) || !isset($seeds_to_set['p1']) || !isset($seeds_to_set['p2'])) {
    send_json_response(400, ['error' => '無効なIdenticonデータ形式です。']);
}

// データベースに保存するために、シード値の配列をカンマ区切りの文字列に変換する。
$identicon_data_string = implode(',', [
    (int)$seeds_to_set['color'],
    (int)$seeds_to_set['p1'],
    (int)$seeds_to_set['p2']
]);

// --- 5. メイン処理 ---
try {
    // データベース接続を取得する。
    $pdo = get_pdo_connection();

    // --- 正当性の検証 ---
    // 設定しようとしているIdenticonが、サーバー側で直前に生成されたものか、
    // 既にお気に入り登録されているものか、または現在アクティブなものであるかを検証する。
    
    // 1. 直前に生成されたものか確認
    $is_newly_generated = false;
    if (isset($_SESSION['last_generated_identicon_seeds']) && is_array($_SESSION['last_generated_identicon_seeds'])) {
        $last_gen = $_SESSION['last_generated_identicon_seeds'];
        if ((int)$last_gen['color'] == (int)$seeds_to_set['color'] &&
            (int)$last_gen['p1'] == (int)$seeds_to_set['p1'] &&
            (int)$last_gen['p2'] == (int)$seeds_to_set['p2']) {
            $is_newly_generated = true;
        }
    }

    // 2. お気に入りに存在するか確認
    $stmt_fav = $pdo->prepare("SELECT COUNT(*) FROM favorite_identicons WHERE user_id = ? AND identicon_data = ?");
    $stmt_fav->execute([$userId, $identicon_data_string]);
    $is_in_favorites = $stmt_fav->fetchColumn() > 0;
    
    // 3. 現在アクティブなものと同一か確認
    $stmt_active = $pdo->prepare("SELECT active_identicon FROM user_profiles WHERE user_id = ?");
    $stmt_active->execute([$userId]);
    $is_current_active = ($stmt_active->fetchColumn() == $identicon_data_string);

    // 上記のいずれにも当てはまらない場合は不正なリクエストとみなし、403エラーを返す。
    if (!$is_in_favorites && !$is_current_active && !$is_newly_generated) {
        send_json_response(403, ['error' => '不正なIdenticonデータの設定リクエストです。']);
    }
    
    // --- active_identiconを更新 ---
    // user_profilesテーブルのactive_identiconカラムを更新する。
    $stmt_update = $pdo->prepare("UPDATE user_profiles SET active_identicon = ? WHERE user_id = ?");
    $stmt_update->execute([$identicon_data_string, $userId]);

    // サーバーサイドのセッション情報も更新する。
    $_SESSION['active_identicon'] = $identicon_data_string;

    // 直前に生成されたシードをアクティブにした場合、検証用のセッション変数をクリアする。
    if ($is_newly_generated) {
        unset($_SESSION['last_generated_identicon_seeds']);
    }

    // 処理成功のレスポンスと、新しくアクティブになったIdenticonデータをJSON形式で返す。
    send_json_response(200, [
        "success" => true,
        "active_identicon" => $identicon_data_string
    ]);

} catch (Exception $e) {
    // 例外が発生した場合は、エラーハンドラに処理を委譲する。
    handle_exception($e);
}
