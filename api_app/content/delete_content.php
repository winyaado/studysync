<?php
/**
 * API名: content/delete_content
 * 説明: content/delete_content の処理を行います。
 * 認証: 必須
 * HTTPメソッド: POST
 * 引数:
 *   - POST: id
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
// POSTリクエストからコンテンツIDを取得する。指定がなければnullとする。
$contentId = $_POST['id'] ?? null;
// セッションから現在のユーザーIDを取得する。
$userId = $_SESSION['user_id'];

// コンテンツIDが必須であり、有効な整数であるか検証する。
if (!$contentId || !filter_var($contentId, FILTER_VALIDATE_INT)) {
    send_json_response(400, ['error' => '無効なIDです。']);
}

// --- 5. メイン処理 ---
try {
    // データベース接続を取得する。
    $pdo = get_pdo_connection();

    // まず、指定されたコンテンツの所有者を確認する。
    $stmt = $pdo->prepare("SELECT user_id FROM contents WHERE id = ?");
    $stmt->execute([$contentId]);
    $content = $stmt->fetch();

    // コンテンツが見つからない場合は404エラーをスローする。
    if (!$content) {
        throw new Exception("コンテンツが見つかりません。", 404);
    }
    // 現在のユーザーがコンテンツの所有者でなければ403エラーをスローする。
    if ($content['user_id'] != $userId) {
        throw new Exception("このコンテンツを削除する権限がありません。", 403);
    }

    // コンテンツを論理削除する（deleted_atタイムスタンプに現在時刻を設定する）。
    $stmt = $pdo->prepare("UPDATE contents SET deleted_at = NOW() WHERE id = ?");
    $success = $stmt->execute([$contentId]);
    
    // 論理削除が成功したかを確認し、適切なレスポンスを返す。
    if ($success) {
        send_json_response(200, ['success' => true, 'message' => 'コンテンツを削除しました。']);
    } else {
        // 更新が成功しなかった場合はエラーとして扱う。
        throw new Exception("コンテンツの削除に失敗しました。");
    }

} catch (Exception $e) {
    // 例外が発生した場合は、エラーハンドラに処理を委譲する。
    handle_exception($e);
}
