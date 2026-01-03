<?php
/**
 * API名: user/get_user_profile
 * 説明: user/get_user_profile の処理を行います。
 * 認証: 必須
 * HTTPメソッド: GET
 * 引数:
 *   - GET: user_id
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
// GETリクエストから対象のユーザーIDを取得する。
$targetUserId = $_GET['user_id'] ?? null;
// セッションから現在のユーザーIDを取得する。
$currentUserId = $_SESSION['user_id'] ?? null;

// 対象ユーザーIDが必須であり、有効な数値であるか検証する。
if (!isset($targetUserId) || !is_numeric($targetUserId)) {
    send_json_response(400, ['error' => 'ユーザーIDが指定されていません。']);
}

// --- 5. メイン処理 ---
try {
    // データベース接続を取得する。
    $pdo = get_pdo_connection();

    // ユーザー情報を取得するSQLクエリを準備する。
    // サブクエリを使用して、投稿数、フォロワー数、コンテンツの平均評価、および現在のユーザーがフォローしているかどうかの情報を取得する。
    // ユーザー名や自己紹介はuser_profilesテーブルのものを優先し、なければusersテーブルのものをフォールバックとして使用する。
    $stmt = $pdo->prepare("
        SELECT
            u.id,
            COALESCE(up.username, u.name, '名もなき猫') AS username,
            COALESCE(up.bio, '') AS bio,
            up.active_identicon,
            (
                SELECT COUNT(c.id)
                FROM contents c
                WHERE c.user_id = u.id
                  AND c.status = 'published'
                  AND c.deleted_at IS NULL
                  AND (c.visibility = 'public' OR (c.visibility = 'domain' AND u.tenant_id IS NOT NULL AND EXISTS(SELECT 1 FROM users ut WHERE ut.id = c.user_id AND ut.tenant_id = u.tenant_id)))
            ) AS posts_count,
            (
                SELECT COUNT(id)
                FROM follows
                WHERE following_id = u.id
            ) AS followers_count,
            (
                SELECT AVG(r.rating)
                FROM ratings r
                JOIN contents c ON r.rateable_id = c.id AND r.rateable_type = c.contentable_type
                WHERE c.user_id = u.id
                  AND c.status = 'published'
                  AND c.deleted_at IS NULL
                  AND (c.visibility = 'public' OR (c.visibility = 'domain' AND u.tenant_id IS NOT NULL AND EXISTS(SELECT 1 FROM users ut WHERE ut.id = c.user_id AND ut.tenant_id = u.tenant_id)))
            ) AS avg_rating,
            (
                SELECT COUNT(id)
                FROM follows
                WHERE follower_id = ? AND following_id = u.id
            ) > 0 AS is_following
        FROM users u
        LEFT JOIN user_profiles up ON u.id = up.user_id
        WHERE u.id = ?
    ");

    // SQLクエリにパラメータをバインドして実行する。
    $stmt->execute([$currentUserId, $targetUserId]);
    $profile_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // ユーザーが見つからない場合は404エラーを返す。
    if (!$profile_data) {
        send_json_response(404, ['error' => 'ユーザーが見つかりません。']);
    }

    // 取得したデータを適切な型にキャスト・整形する。
    $profile_data['id'] = (int)$profile_data['id'];
    $profile_data['posts_count'] = (int)$profile_data['posts_count'];
    $profile_data['followers_count'] = (int)$profile_data['followers_count'];
    $profile_data['avg_rating'] = $profile_data['avg_rating'] !== null ? round((float)$profile_data['avg_rating'], 1) : 0.0;
    $profile_data['is_following'] = (bool)$profile_data['is_following'];
    if ($profile_data['active_identicon'] !== null) {
        $profile_data['active_identicon'] = (string)$profile_data['active_identicon'];
    }

    // 整形したプロフィールデータをJSON形式で返す。
    send_json_response(200, $profile_data);

} catch (Exception $e) {
    // 例外が発生した場合は、エラーハンドラに処理を委譲する。
    handle_exception($e);
}
