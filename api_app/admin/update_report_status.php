<?php
/**
 * API名: admin/update_report_status
 * 説明: admin/update_report_status の処理を行います。
 * 認証: 管理者のみ
 * HTTPメソッド: POST
 * 引数:
 *   - POST: new_status, report_id
 * 返り値:
 *   - JSON: success / error
 * エラー: 400/403/404/405/500
 */
// --- 1. 必須ファイルの読み込み ---

// --- 2. 認証と権限チェック ---
// 管理者でない場合は、403エラーを返して処理を終了する
if (!$_SESSION['is_admin']) {
    send_json_response(403, ['error' => '管理者権限がありません。']);
}

// --- 3. HTTPメソッドの検証 ---
// POSTリクエストでなければ、405エラーを返して処理を終了する
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(405, ['error' => '許可されていないメソッドです。']);
}

// --- 4. パラメータの取得とバリデーション ---
// POSTリクエストから通報IDと新しいステータスを取得する。指定がなければnullとする。
$reportId = $_POST['report_id'] ?? null;
$newStatus = $_POST['new_status'] ?? null;

// 通報IDが必須であり、有効な整数であるか検証する。
if (!$reportId || !filter_var($reportId, FILTER_VALIDATE_INT)) {
    send_json_response(400, ['error' => '無効な通報IDです。']);
}

// 新しいステータスが許可された値のいずれかであるか検証する。
$allowedStatuses = ['open', 'in_progress', 'closed'];
if (!in_array($newStatus, $allowedStatuses)) {
    send_json_response(400, ['error' => '無効なステータスです。']);
}

// --- 5. メイン処理 ---
try {
    // データベース接続を取得する。
    $pdo = get_pdo_connection();

    // reportsテーブルのstatusカラムを通報IDに基づいて更新する。updated_atも現在時刻に更新する。
    $stmt = $pdo->prepare("UPDATE reports SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$newStatus, $reportId]);

    // 更新された行が0行の場合は、指定された通報が見つからないか、ステータスが既に最新であるためエラーをスローする。
    if ($stmt->rowCount() === 0) {
        throw new Exception("通報ID: {$reportId} が見つからないか、ステータスは既に最新です。", 404);
    }

    // 処理成功のレスポンスをJSON形式で返す。
    send_json_response(200, ['success' => true, 'message' => 'ステータスが更新されました。']);

} catch (Exception $e) {
    // 例外が発生した場合は、エラーハンドラに処理を委譲する。
    handle_exception($e);
}
