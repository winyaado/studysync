<?php
/**
 * API名: user/toggle_follow
 * 説明: user/toggle_follow の処理を行います。
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
// JSON形式のリクエストボディからフォロー対象のユーザーIDを取得する。
$input = get_json_input();
$targetUserId = $input['target_user_id'] ?? null;
// セッションから現在のユーザーIDを取得する。
$currentUserId = $_SESSION['user_id'];

// 対象ユーザーIDが必須であり、有効な数値であるか検証する。
if (!isset($targetUserId) || !is_numeric($targetUserId)) {
    send_json_response(400, ['error' => '対象ユーザーIDが指定されていません。']);
}

// 自分自身をフォロー/アンフォローしようとしていないか検証する。
if ($currentUserId === (int)$targetUserId) {
    send_json_response(400, ['error' => '自分自身をフォロー/アンフォローすることはできません。']);
}

// --- 5. メイン処理 ---
try {
    // データベース接続を取得し、トランザクションを開始する。
    $pdo = get_pdo_connection();
    $pdo->beginTransaction();

    // 現在のフォロー状態（フォローしているかどうか）を確認する。
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ? AND following_id = ?");
    $stmt->execute([$currentUserId, $targetUserId]);
    $isFollowing = (bool)$stmt->fetchColumn();

    if ($isFollowing) {
        // フォロー中の場合は、followsテーブルからレコードを削除してアンフォローする。
        $stmt = $pdo->prepare("DELETE FROM follows WHERE follower_id = ? AND following_id = ?");
        $stmt->execute([$currentUserId, $targetUserId]);
        $isFollowing = false;
        $message = 'フォローを解除しました。';
    } else {
        // 未フォローの場合は、followsテーブルにレコードを挿入してフォローする。
        $stmt = $pdo->prepare("INSERT INTO follows (follower_id, following_id) VALUES (?, ?)");
        $stmt->execute([$currentUserId, $targetUserId]);
        $isFollowing = true;
        $message = 'フォローしました。';
    }

    // トランザクションをコミットする。
    $pdo->commit();

    // 処理成功のレスポンスと、更新後のフォロー状態をJSON形式で返す。
    send_json_response(200, [
        'success' => true,
        'message' => $message,
        'is_following' => $isFollowing
    ]);

} catch (PDOException $e) {
    // DB関連の例外が発生した場合はロールバックする。
    $pdo->rollBack();
    handle_exception($e);
} catch (Exception $e) {
    // その他の例外が発生した場合は、エラーハンドラに処理を委譲する。
    handle_exception($e);
}
