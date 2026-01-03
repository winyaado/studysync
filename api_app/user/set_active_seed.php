<?php
/**
 * API名: user/set_active_seed
 * 説明: user/set_active_seed の処理を行います。
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
// JSON形式のリクエストボディから設定対象のシード値を取得する。
$input = get_json_input();
$seed_to_set = $input['seed'] ?? null;
// セッションから現在のユーザーIDを取得する。
$userId = $_SESSION['user_id'];

// シード値が必須であり、有効な数値であるか検証する。
if (!is_numeric($seed_to_set)) {
    send_json_response(400, ['error' => '無効なシード値です。']);
}
$seed_to_set = (int)$seed_to_set;

// --- 5. メイン処理 ---
try {
    // データベース接続を取得する。
    $pdo = get_pdo_connection();

    // --- 正当性の検証 ---
    // 設定しようとしているシードが、お気に入りリスト、現在アクティブなシード、
    // またはサーバーで直前に生成されたシードのいずれかであることを検証する。
    
    // 1. お気に入りリストに存在するか確認。
    $stmt_fav = $pdo->prepare("SELECT COUNT(*) FROM favorite_identicon_seeds WHERE user_id = ? AND seed = ?");
    $stmt_fav->execute([$userId, $seed_to_set]);
    $is_in_favorites = $stmt_fav->fetchColumn() > 0;

    // 2. 現在のアクティブシードと同一か確認。
    $stmt_active = $pdo->prepare("SELECT active_identicon_seed FROM user_profiles WHERE user_id = ?");
    $stmt_active->execute([$userId]);
    $is_current_active = $stmt_active->fetchColumn() == $seed_to_set;

    // 3. 直前にサーバーが生成したシードか確認。
    $is_newly_generated = isset($_SESSION['last_generated_seed']) && $_SESSION['last_generated_seed'] == $seed_to_set;
    
    // 上記のいずれにも当てはまらない場合は不正なリクエストとみなし、403エラーを返す。
    if (!$is_in_favorites && !$is_current_active && !$is_newly_generated) {
        send_json_response(403, ['error' => '不正なリクエストです。']);
    }
    
    // --- active_seedを更新 ---
    // user_profilesテーブルのactive_identicon_seedカラムを更新する。
    $stmt = $pdo->prepare("UPDATE user_profiles SET active_identicon_seed = ? WHERE user_id = ?");
    $stmt->execute([$seed_to_set, $userId]);

    // サーバーサイドのセッション情報も更新する。
    $_SESSION['active_identicon_seed'] = $seed_to_set;

    // 処理成功のレスポンスと、新しくアクティブになったシード値をJSON形式で返す。
    send_json_response(200, [
        "success" => true,
        "active_seed" => $seed_to_set
    ]);

} catch (Exception $e) {
    // 例外が発生した場合は、エラーハンドラに処理を委譲する。
    handle_exception($e);
}
