<?php
/**
 * API名: user/save_favorite_identicon
 * 説明: user/save_favorite_identicon の処理を行います。
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
// JSON形式のリクエストボディから保存対象のIdenticonデータを取得する。
$input = get_json_input();
$seeds_to_save = $input['identicon_data'] ?? null;
// セッションから現在のユーザーIDを取得する。
$userId = $_SESSION['user_id'];

// Identiconデータが連想配列で、必要なキー（color, p1, p2）がすべて存在するか検証する。
if (!is_array($seeds_to_save) || !isset($seeds_to_save['color']) || !isset($seeds_to_save['p1']) || !isset($seeds_to_save['p2'])) {
    send_json_response(400, ['error' => '無効なIdenticonデータ形式です。']);
}

// データベースに保存するために、シード値の配列をカンマ区切りの文字列に変換する。
$identicon_data_string = implode(',', [
    (int)$seeds_to_save['color'],
    (int)$seeds_to_save['p1'],
    (int)$seeds_to_save['p2']
]);

// --- 5. メイン処理 ---
try {
    // データベース接続を取得する。
    $pdo = get_pdo_connection();
    
    // --- 正当性の検証 ---
    // 保存しようとしているIdenticonが、サーバー側で直前に生成されたものか、
    // または現在ユーザーがアクティブに設定しているものであるかを検証する。
    $is_newly_generated = false;
    if (isset($_SESSION['last_generated_identicon_seeds']) && is_array($_SESSION['last_generated_identicon_seeds'])) {
        $last_gen = $_SESSION['last_generated_identicon_seeds'];
        if ($last_gen['color'] == $seeds_to_save['color'] &&
            $last_gen['p1'] == $seeds_to_save['p1'] &&
            $last_gen['p2'] == $seeds_to_save['p2']) {
            $is_newly_generated = true;
        }
    }

    $stmt = $pdo->prepare("SELECT active_identicon FROM user_profiles WHERE user_id = ?");
    $stmt->execute([$userId]);
    $active_identicon_string = $stmt->fetchColumn();
    $is_active_identicon = ($active_identicon_string == $identicon_data_string);

    // 直前生成でもなく、アクティブでもないIdenticonの保存リクエストは不正とみなし、403エラーを返す。
    if (!$is_newly_generated && !$is_active_identicon) {
        send_json_response(403, ['error' => '不正なIdenticonデータの保存リクエストです。']);
    }

    // トランザクションを開始する。
    $pdo->beginTransaction();

    // --- 上限チェック ---
    // ユーザーのお気に入りスロットの上限数を取得する。
    $stmt = $pdo->prepare("SELECT identicon_slot_limit FROM user_profiles WHERE user_id = ?");
    $stmt->execute([$userId]);
    $slot_limit = $stmt->fetchColumn() ?: 5; // 取得できない場合はデフォルトで5。

    // 現在のお気に入り登録数を取得する。
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorite_identicons WHERE user_id = ?");
    $stmt->execute([$userId]);
    $current_count = $stmt->fetchColumn();

    // 上限に達している場合は、409エラーを返してロールバックする。
    if ($current_count >= $slot_limit) {
        $pdo->rollBack();
        send_json_response(409, [
            "success" => false, 
            "error" => "favorites_full", 
            "message" => "お気に入りスロットが満杯です。"
        ]);
        exit;
    }
    
    // --- 挿入処理 ---
    // 既に同じIdenticonがお気に入りに存在しないか確認する。
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorite_identicons WHERE user_id = ? AND identicon_data = ?");
    $stmt->execute([$userId, $identicon_data_string]);
    if ($stmt->fetchColumn() == 0) {
        // 存在しない場合のみ、新しいお気に入りとして挿入する。
        $stmt = $pdo->prepare("INSERT INTO favorite_identicons (user_id, identicon_data) VALUES (?, ?)");
        $stmt->execute([$userId, $identicon_data_string]);
    }
    
    // --- 更新後のリストを返却 ---
    // 最新のお気に入りリストを取得してクライアントに返す。
    $stmt = $pdo->prepare("SELECT id, name, identicon_data FROM favorite_identicons WHERE user_id = ? ORDER BY created_at ASC");
    $stmt->execute([$userId]);
    $favorite_identicons = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // トランザクションをコミットする。
    $pdo->commit();
    
    // 直前に生成されたシードを保存した場合、検証用のセッション変数をクリアする。
    if ($is_newly_generated) {
        unset($_SESSION['last_generated_identicon_seeds']);
    }

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
