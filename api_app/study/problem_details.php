<?php
/**
 * API名: study/problem_details
 * 説明: study/problem_details の処理を行います。
 * 認証: 必須
 * HTTPメソッド: GET
 * 引数:
 *   - GET: id
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
// GETリクエストからコンテンツIDを取得する。指定がなければnullとする。
$contentId = $_GET['id'] ?? null;
// セッションから現在のユーザーIDとテナントIDを取得する。
$userId = $_SESSION['user_id'];
$currentUserTenantId = $_SESSION['tenant_id'] ?? null;

// コンテンツIDが必須であり、有効な整数であるか検証する。
if (!$contentId || !filter_var($contentId, FILTER_VALIDATE_INT)) {
    send_json_response(400, ['error' => '無効なIDです。']);
}

// --- 5. メイン処理 ---
try {
    // データベース接続を取得する。
    $pdo = get_pdo_connection();
    
    // 1. 問題集の基本情報を取得する。
    // コンテンツは公開済み、削除されていない、かつ、公開設定（public, 自身が所有, 同一テナント）に基づいてアクセス可能である必要がある。
    // 最新バージョンの情報（バージョンID、作者名、制限時間、問題数など）と、評価情報（平均、総数、自身の評価）、評価可能かも同時に取得する。
    $sql = "
        SELECT
            c.id, c.title, c.lecture_id, c.description, c.updated_at, c.visibility,
            psv.id as version_id,
            COALESCE(up.username, '名もなき猫') AS author_name,
            psv.time_limit_minutes,
            COUNT(q.id) AS question_count,
            (SELECT AVG(r.rating) FROM ratings r WHERE r.rateable_id = c.id AND r.rateable_type = 'ProblemSet') as avg_rating,
            (SELECT COUNT(*) FROM ratings r WHERE r.rateable_id = c.id AND r.rateable_type = 'ProblemSet') as rating_count,
            (SELECT r.rating FROM ratings r WHERE r.rateable_id = c.id AND r.rateable_type = 'ProblemSet' AND r.user_id = :my_user_id) as my_rating,
            (SELECT COUNT(*) > 0 FROM exam_attempts ea WHERE ea.problem_set_version_id = psv.id AND ea.user_id = :my_user_id) as can_rate
        FROM contents c
        JOIN users u ON c.user_id = u.id
        LEFT JOIN user_profiles up ON u.id = up.user_id
        JOIN problem_sets ps ON c.contentable_id = ps.id AND c.contentable_type = 'ProblemSet'
        LEFT JOIN problem_set_versions psv ON ps.id = psv.problem_set_id
        LEFT JOIN questions q ON psv.id = q.problem_set_version_id
        WHERE c.id = :content_id 
          AND c.status = 'published' 
          AND c.deleted_at IS NULL
          AND (
               c.visibility = 'public'
               OR c.user_id = :my_user_id
               OR (c.visibility = 'domain' AND u.tenant_id = :current_user_tenant_id)
              )
        GROUP BY c.id, psv.id
        ORDER BY psv.version DESC
        LIMIT 1;
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':content_id', $contentId, PDO::PARAM_INT);
    $stmt->bindValue(':my_user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':current_user_tenant_id', $currentUserTenantId, PDO::PARAM_INT);
    $stmt->execute();
    $problemSet = $stmt->fetch(PDO::FETCH_ASSOC);

    // 問題集が見つからない、またはアクセス権がない場合は404エラーをスローする。
    if (!$problemSet) {
        throw new Exception("指定された問題集が見つからないか、アクセス権がありません。", 404);
    }

    // 2. 現在のユーザーの、この問題集に対する過去の回答履歴（最大10件）を取得する。
    $attempts = [];
    if (isset($problemSet['id'])) {
        // このコンテンツに関連するすべてのバージョンIDを取得する。
        $stmt = $pdo->prepare("
            SELECT psv.id
            FROM problem_set_versions psv
            JOIN problem_sets ps ON psv.problem_set_id = ps.id
            JOIN contents c ON ps.id = c.contentable_id
            WHERE c.id = ?
        ");
        $stmt->execute([$contentId]);
        $versionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // 取得したすべてのバージョンIDを使って、exam_attemptsテーブルから回答履歴を検索する。
        if (!empty($versionIds)) {
            $placeholders = implode(',', array_fill(0, count($versionIds), '?'));
            $stmt = $pdo->prepare("
                SELECT 
                    ea.id, 
                    ea.score, 
                    ea.total_questions, 
                    ea.completed_at,
                    psv.version
                FROM exam_attempts ea
                JOIN problem_set_versions psv ON ea.problem_set_version_id = psv.id
                WHERE ea.user_id = ? AND ea.problem_set_version_id IN ($placeholders)
                ORDER BY ea.completed_at DESC
                LIMIT 10
            ");
            $params = array_merge([$userId], $versionIds);
            $stmt->execute($params);
            $attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // 取得した問題集の詳細と回答履歴をJSON形式で返す。
    send_json_response(200, [
        'problemSet' => $problemSet,
        'attempts' => $attempts
    ]);

} catch (Exception $e) {
    // 例外が発生した場合は、エラーハンドラに処理を委譲する。
    handle_exception($e);
}
