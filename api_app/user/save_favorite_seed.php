<?php
/**
 * API名: user/save_favorite_seed
 * 説明: user/save_favorite_seed の処理を行います。
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
// JSON形式のリクエストボディから保存対象のシード値を取得する。
$input = get_json_input();
$seed_to_save = $input['seed'] ?? null;
// セッションから現在のユーザーIDを取得する。
$userId = $_SESSION['user_id'];

// シード値が必須であり、有効な数値であるか検証する。
if (!is_numeric($seed_to_save)) {
    send_json_response(400, ['error' => '無効なシード値です。']);
}
$seed_to_save = (int)$seed_to_save;

// --- 5. メイン処理 ---
try {
    // データベース接続を取得する。
    $pdo = get_pdo_connection();
    
    // --- 正当性の検証 ---
    // 保存しようとしているシードが、サーバー側で直前に生成されたものか、
    // または現在ユーザーがアクティブに設定しているものであるかを検証する。
    $stmt = $pdo->prepare("SELECT active_identicon_seed FROM user_profiles WHERE user_id = ?");
    $stmt->execute([$userId]);
    $active_seed = $stmt->fetchColumn();

    $is_new_seed = isset($_SESSION['last_generated_seed']) && $_SESSION['last_generated_seed'] == $seed_to_save;
    $is_active_seed = $active_seed == $seed_to_save;

    // 直前生成でもなく、アクティブでもないシードの保存リクエストは不正とみなし、403エラーを返す。
    if (!$is_new_seed && !$is_active_seed) {
        send_json_response(403, ['error' => '不正なリクエストです。']);
    }

    // トランザクションを開始する。
    $pdo->beginTransaction();

    // --- 上限チェック ---
    // ユーザーのお気に入りシードのスロット上限数を取得する。
    $stmt = $pdo->prepare("SELECT identicon_slot_limit FROM user_profiles WHERE user_id = ?");
    $stmt->execute([$userId]);
    $slot_limit = $stmt->fetchColumn() ?: 5; // 取得できない場合はデフォルトで5。

    // 現在のお気に入り登録数を取得する。
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorite_identicon_seeds WHERE user_id = ?");
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
    // 既に同じシードがお気に入りに存在しないか確認する。
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorite_identicon_seeds WHERE user_id = ? AND seed = ?");
    $stmt->execute([$userId, $seed_to_save]);
    if ($stmt->fetchColumn() == 0) {
        // 存在しない場合のみ、新しいお気に入りとして挿入する。
        $stmt = $pdo->prepare("INSERT INTO favorite_identicon_seeds (user_id, seed) VALUES (?, ?)");
        $stmt->execute([$userId, $seed_to_save]);
    }
    
    // --- 更新後のリストを返却 ---
    // 最新のお気に入りシードリストを取得してクライアントに返す。
    $stmt = $pdo->prepare("SELECT seed FROM favorite_identicon_seeds WHERE user_id = ? ORDER BY created_at ASC");
    $stmt->execute([$userId]);
    $favorite_seeds_raw = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $favorites = array_map('intval', $favorite_seeds_raw);

    // トランザクションをコミットする。
    $pdo->commit();
    
    // 直前に生成されたシードを保存した場合、検証用のセッション変数をクリアする。
    if ($is_new_seed) {
        unset($_SESSION['last_generated_seed']);
    }

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
