<?php
/**
 * API名: user/get_identicon_settings
 * 説明: user/get_identicon_settings の処理を行います。
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
// セッションからユーザーIDを取得しますが、これはAPIのパラメータとは異なります。
$userId = $_SESSION['user_id'];
// 取得すべきパラメータがないため、バリデーションも不要です。

// --- 5. メイン処理 ---
try {
    // データベース接続を取得する。
    $pdo = get_pdo_connection();

    // user_profilesテーブルから、現在のアクティブなIdenticonと、お気に入りのスロット上限数を取得する。
    $stmt = $pdo->prepare("
        SELECT active_identicon, identicon_slot_limit 
        FROM user_profiles 
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    // プロファイルが存在しない場合は、デフォルト値で対応する（通常は発生しない）。
    if (!$profile) {
        $profile = ['active_identicon' => null, 'identicon_slot_limit' => 5];
    }
    
    // favorite_identiconsテーブルから、ユーザーのお気に入りIdenticonリストを取得する。
    $stmt = $pdo->prepare("
        SELECT id, name, identicon_data 
        FROM favorite_identicons 
        WHERE user_id = ? 
        ORDER BY created_at ASC
    ");
    $stmt->execute([$userId]);
    $favorite_identicons = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 取得した設定情報を一つの配列にまとめる。
    $settings = [
        'active_identicon' => $profile['active_identicon'],
        'favorite_identicons' => $favorite_identicons,
        'slot_limit' => (int)$profile['identicon_slot_limit']
    ];

    // 設定情報をJSON形式で返す。
    send_json_response(200, $settings);

} catch (Exception $e) {
    // 例外が発生した場合は、エラーハンドラに処理を委譲する。
    handle_exception($e);
}
