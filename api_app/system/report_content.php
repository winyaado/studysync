<?php
/**
 * API名: system/report_content
 * 説明: system/report_content の処理を行います。
 * 認証: 必須
 * HTTPメソッド: POST
 * 引数:
 *   - POST: content_id, reason_category, reason_details
 * 返り値:
 *   - JSON: success / error
 * エラー: 400/403/404/405/500
 */
// --- 1. 必須ファイルの読み込み ---
require_once __DIR__ . '/../notifications/NotificationService.php'; // 通知サービスを読み込む

use App\Notifications\NotificationService;

// --- 2. 認証と権限チェック ---

// --- 3. HTTPメソッドの検証 ---
// POSTリクエストでなければ、405エラーを返して処理を終了する
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(405, ['error' => '許可されていないメソッドです。']);
}

// --- 4. パラメータの取得とバリデーション ---
// セッションから通報者のユーザーIDを取得する。
$reportingUserId = $_SESSION['user_id'];
// POSTリクエストから通報対象のコンテンツID、理由カテゴリ、詳細理由を取得する。
$contentId = $_POST['content_id'] ?? null;
$reasonCategory = $_POST['reason_category'] ?? null;
$reasonDetails = $_POST['reason_details'] ?? null;

// コンテンツIDが必須であり、有効な整数であるか検証する。
if (!$contentId || !filter_var($contentId, FILTER_VALIDATE_INT)) {
    send_json_response(400, ['error' => '無効なコンテンツIDです。']);
}
// 理由カテゴリが必須であることを検証する。
if (empty($reasonCategory)) {
    send_json_response(400, ['error' => '通報理由のカテゴリは必須です。']);
}

// 理由カテゴリが許可された値のいずれかであるか検証する。
$allowedCategories = ['spam', 'inappropriate', 'copyright', 'other', 'hate_speech', 'harassment'];
if (!in_array($reasonCategory, $allowedCategories)) {
    send_json_response(400, ['error' => '無効な通報カテゴリです。']);
}
// 詳細理由が最大長を超えていないか検証する。
if (mb_strlen($reasonDetails) > 1000) {
    send_json_response(400, ['error' => '通報理由の詳細は1000文字以内で入力してください。']);
}

// --- 5. メイン処理 ---
try {
    // データベース接続を取得し、トランザクションを開始する。
    $pdo = get_pdo_connection();
    $pdo->beginTransaction();

    // 1. 通報対象のコンテンツが存在するか（削除されていないか）を確認する。
    $stmt_content = $pdo->prepare("SELECT title, contentable_type FROM contents WHERE id = ? AND deleted_at IS NULL");
    $stmt_content->execute([$contentId]);
    $reportedContent = $stmt_content->fetch(PDO::FETCH_ASSOC);

    // コンテンツが見つからない場合は404エラーをスローする。
    if (!$reportedContent) {
        throw new Exception("通報対象のコンテンツが見つかりません。", 404);
    }

    // 2. 通報をreportsテーブルに保存する。
    $stmt_report = $pdo->prepare("INSERT INTO reports (reporting_user_id, content_id, reason_category, reason_details) VALUES (?, ?, ?, ?)");
    $stmt_report->execute([$reportingUserId, $contentId, $reasonCategory, $reasonDetails]);
    $reportId = $pdo->lastInsertId();

    // トランザクションをコミットする。
    $pdo->commit();

    // 3. 通知サービスを利用して管理者へ通知を送信する。
    $notificationService = new NotificationService();
    $notificationDetails = [
        'report_id' => $reportId,
        'reported_by' => $_SESSION['user_name'] ?? 'ID:'.$reportingUserId,
        'content_id' => $contentId,
        'content_title' => $reportedContent['title'],
        'content_type' => $reportedContent['contentable_type'],
        'reason_category' => $reasonCategory,
        'reason_details' => $reasonDetails,
        'content_link' => APP_URL . '/read/' . strtolower($reportedContent['contentable_type']) . '.php?id=' . $contentId, // 通報されたコンテンツへのリンク
        'report_link' => APP_URL . '/tools/manage_reports.php?id=' . $reportId, // 管理画面の通報詳細へのリンク
    ];
    $notificationService->dispatch("新しいコンテンツ通報", $notificationDetails);

    // 処理成功のレスポンスをJSON形式で返す。
    send_json_response(200, ['success' => true, 'message' => '通報が正常に送信されました。']);

} catch (Exception $e) {
    // 例外が発生し、トランザクションがアクティブな場合はロールバックする。
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // エラーハンドラに処理を委譲する。
    handle_exception($e);
}
