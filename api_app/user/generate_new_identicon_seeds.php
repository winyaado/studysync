<?php
/**
 * API名: user/generate_new_identicon_seeds
 * 説明: user/generate_new_identicon_seeds の処理を行います。
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
// このAPIは明示的なパラメータを受け取らないため、このセクションでの処理はありません。
// 取得すべきパラメータがないため、バリデーションも不要です。

// --- 5. メイン処理 ---
try {
    // Identiconを生成するための3つのランダムな整数（シード値）を生成する。
    $color_seed = random_int(0, 2147483647);
    $p1_seed = random_int(0, 2147483647);
    $p2_seed = random_int(0, 2147483647);

    // 生成したシード値を連想配列にまとめる。
    $new_seeds = [
        'color' => $color_seed,
        'p1' => $p1_seed,
        'p2' => $p2_seed
    ];

    // 生成したシードをセッションに一時保存する。
    // これは後で保存API (save_favorite_identicon.php, set_active_identicon.php) を呼び出す際に、
    // ユーザーが不正なシード値を送信していないか検証するために使用される。
    $_SESSION['last_generated_identicon_seeds'] = $new_seeds;

    // 処理成功のレスポンスと、新しく生成されたシード値をJSON形式で返す。
    send_json_response(200, ['success' => true, 'seeds' => $new_seeds]);

} catch (Exception $e) {
    // 例外が発生した場合は、エラーハンドラに処理を委譲する。
    handle_exception($e);
}
