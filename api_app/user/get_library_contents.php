<?php
/**
 * API名: user/get_library_contents
 * 説明: user/get_library_contents の処理を行います。
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

    // ユーザーがライブラリに追加した（有効化した）コンテンツを、関連する講義情報とともに取得する。
    // 削除済みのコンテンツは除外する。
    $sql = "
        SELECT 
            c.id,
            c.title,
            c.contentable_type,
            c.updated_at,
            c.lecture_id,
            l.name AS lecture_name
        FROM user_active_contents uac
        JOIN contents c ON uac.content_id = c.id
        LEFT JOIN users u ON c.user_id = u.id
        LEFT JOIN lectures l ON c.lecture_id = l.lecture_code AND l.tenant_id = u.tenant_id
        WHERE uac.user_id = ? AND c.deleted_at IS NULL
        ORDER BY l.name ASC, c.updated_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $contents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 取得したコンテンツを、フロントエンドで表示しやすいように講義ごとにグループ化する。
    $library = [];
    foreach ($contents as $content) {
        $key = $content['lecture_id'] ?? 'unclassified';
        if (!isset($library[$key])) {
            $library[$key] = [
                'lecture_name' => $content['lecture_name'] ?? '講義未分類',
                'contents' => []
            ];
        }
        $library[$key]['contents'][] = $content;
    }

    // グループ化されたライブラリデータをJSON形式で返す。
    send_json_response(200, [
        'success' => true,
        'library' => $library
    ]);

} catch (Exception $e) {
    // 例外が発生した場合は、エラーハンドラに処理を委譲する。
    handle_exception($e);
}
