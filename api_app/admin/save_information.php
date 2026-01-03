<?php
/**
 * API名: admin/save_information
 * 説明: admin/save_information の処理を行います。
 * 認証: 管理者のみ
 * HTTPメソッド: POST
 * 引数:
 *   - POST: category, content, display_from, display_to, id, title
 * 返り値:
 *   - JSON: success / error
 * エラー: 400/403/404/405/500
 */
// --- 1. 必須ファイルの読み込み ---

// --- 2. 認証と権限チェック ---
// 管理者でない場合は、403エラーを返して処理を終了する
if (!$_SESSION['is_admin']) {
    send_json_response(403, ['error' => 'この操作を行う権限がありません。']);
}

// --- 3. HTTPメソッドの検証 ---
// POSTリクエストでなければ、405エラーを返して処理を終了する
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(405, ['error' => '許可されていないメソッドです。']);
}

// --- 4. パラメータの取得とバリデーション ---
// POSTリクエストからパラメータを取得する。IDは整数にキャストし、空の場合はnullとする。
$id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
$title = $_POST['title'] ?? '';
$content = $_POST['content'] ?? ''; // お知らせ内容（JSON形式）
$category = $_POST['category'] ?? '';
$displayFrom = $_POST['display_from'] ?? '';
$displayTo = $_POST['display_to'] ?? '';

// タイトルが最大長を超えていないか検証する。
if (mb_strlen($title) > MAX_TITLE_LENGTH) {
    send_json_response(400, ['error' => 'タイトルは' . MAX_TITLE_LENGTH . '文字以内で入力してください。']);
}
// お知らせ内容が最大サイズを超えていないか検証する。
if (strlen($content) > MAX_INFORMATION_CONTENT_SIZE_BYTES) {
    send_json_response(400, ['error' => 'お知らせ内容が長すぎます。' . (MAX_INFORMATION_CONTENT_SIZE_BYTES / 1024) . 'KB以内で入力してください。']);
}
// すべての必須フィールドが入力されているか検証する。
if (empty($title) || empty($content) || empty($category) || empty($displayFrom) || empty($displayTo)) {
    send_json_response(400, ['error' => 'すべてのフィールドは必須です。']);
}
// カテゴリが許可された値のいずれかであるか検証する。
if (!in_array($category, ['info', 'warning', 'danger'])) {
    send_json_response(400, ['error' => '無効なカテゴリです。']);
}

// 日付の形式と論理的な整合性を検証する。
try {
    $from = new DateTime($displayFrom);
    $to = new DateTime($displayTo);
    // 表示開始日時が表示終了日時より後ではないか検証する。
    if ($from >= $to) {
        send_json_response(400, ['error' => '表示開始日時は表示終了日時より前に設定してください。']);
    }
} catch (Exception $e) {
    // 無効な日付形式の場合は400エラーを返して処理を終了する。
    send_json_response(400, ['error' => '無効な日時形式です。']);
}

// --- 5. メイン処理 ---
try {
    // データベース接続を取得し、トランザクションを開始する。
    $pdo = get_pdo_connection();
    $pdo->beginTransaction();

    if ($id) {
        // IDが存在する場合は更新モード: 既存のインフォメーションを更新する。
        $stmt = $pdo->prepare("UPDATE informations SET title = ?, content = ?, category = ?, display_from = ?, display_to = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$title, $content, $category, $displayFrom, $displayTo, $id]);
    } else {
        // IDが存在しない場合は挿入モード: 新しいインフォメーションを挿入する。
        $stmt = $pdo->prepare("INSERT INTO informations (title, content, category, display_from, display_to) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $content, $category, $displayFrom, $displayTo]);
        $id = $pdo->lastInsertId(); // 新しく挿入されたレコードのIDを取得する。
    }

    // トランザクションをコミットする。
    $pdo->commit();

    // 処理成功のレスポンスと、更新または挿入されたインフォメーションのIDをJSON形式で返す。
    send_json_response(200, ['success' => true, 'id' => $id, 'message' => 'インフォメーションを保存しました。']);

} catch (Exception $e) {
    // 例外が発生し、トランザクションがアクティブな場合はロールバックする。
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // エラーハンドラに処理を委譲する。
    handle_exception($e);
}
