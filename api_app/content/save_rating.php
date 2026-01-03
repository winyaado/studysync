<?php
/**
 * API名: content/save_rating
 * 説明: content/save_rating の処理を行います。
 * 認証: 必須
 * HTTPメソッド: POST
 * 引数:
 *   - POST: rateable_id, rateable_type, rating
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
// POSTリクエストから評価対象ID、評価対象タイプ、評価値を取得する。指定がなければnullとする。
$userId = $_SESSION['user_id'];
$rateableId = $_POST['rateable_id'] ?? null;
$rateableType = $_POST['rateable_type'] ?? null;
$rating = $_POST['rating'] ?? null;

// 評価対象IDが必須であり、有効な整数であるか検証する。
// 評価対象タイプが許可された値のいずれかであるか検証する。
// 評価値が必須であり、1から5の整数であるか検証する。
if (!$rateableId || !filter_var($rateableId, FILTER_VALIDATE_INT) || 
    !$rateableType || !in_array($rateableType, ['ProblemSet', 'Note', 'FlashCard']) || 
    !$rating || !filter_var($rating, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]])) {
    send_json_response(400, ['error' => '無効な評価データです。']);
}

// --- 5. メイン処理 ---
try {
    // データベース接続を取得する。
    $pdo = get_pdo_connection();

    // 評価対象が問題集の場合、ユーザーがその問題集を解いたことがあるか確認する。
    // 未回答の問題集は評価できないようにする。
    if ($rateableType === 'ProblemSet') {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM exam_attempts ea
            JOIN problem_set_versions psv ON ea.problem_set_version_id = psv.id
            JOIN problem_sets ps ON psv.problem_set_id = ps.id
            JOIN contents c ON ps.id = c.contentable_id
            WHERE c.id = ? AND ea.user_id = ? AND c.contentable_type = 'ProblemSet'
        ");
        $stmt->execute([$rateableId, $userId]);
        if ($stmt->fetchColumn() == 0) {
            throw new Exception("この問題集を解いていないため評価できません。", 403);
        }
    }
    // ノートやフラッシュカードの場合、このチェックは不要。

    // 評価をratingsテーブルに挿入または更新する。
    // ON DUPLICATE KEY UPDATE句により、既に評価が存在する場合は更新、存在しない場合は新規挿入となる。
    $stmt = $pdo->prepare("
        INSERT INTO ratings (user_id, rateable_id, rateable_type, rating, created_at)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE rating = VALUES(rating), created_at = NOW()
    ");
    $stmt->execute([$userId, $rateableId, $rateableType, $rating]);

    // 更新後の平均評価と評価者数をratingsテーブルから再取得する。
    $stmt = $pdo->prepare("
        SELECT AVG(rating) as avg_rating, COUNT(*) as rating_count
        FROM ratings
        WHERE rateable_id = ? AND rateable_type = ?
    ");
    $stmt->execute([$rateableId, $rateableType]);
    $newStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // 処理成功のレスポンスと、更新された評価統計をJSON形式で返す。
    send_json_response(200, [
        'success' => true,
        'avg_rating' => $newStats['avg_rating'] ? (float)$newStats['avg_rating'] : 0, // 平均評価をfloat型で返す
        'rating_count' => (int)$newStats['rating_count'], // 評価者数をint型で返す
        'my_rating' => (int)$rating // ユーザー自身の最新の評価もint型で返す
    ]);

} catch (Exception $e) {
    // 例外が発生した場合は、エラーハンドラに処理を委譲する。
    handle_exception($e);
}
