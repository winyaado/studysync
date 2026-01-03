<?php
/**
 * API名: user/get_follow_lists
 * 説明: user/get_follow_lists の処理を行います。
 * 認証: 必須
 * HTTPメソッド: GET
 * 引数:
 *   - GET: followers_page, following_page, per_page, user_id
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
// GETリクエストから対象ユーザーIDとページネーション関連のパラメータを取得する。
$targetUserId = $_GET['user_id'] ?? null;
$perPage = isset($_GET['per_page']) && is_numeric($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
$followingPage = isset($_GET['following_page']) && is_numeric($_GET['following_page']) ? (int)$_GET['following_page'] : 1;
$followersPage = isset($_GET['followers_page']) && is_numeric($_GET['followers_page']) ? (int)$_GET['followers_page'] : 1;

// 1ページあたりの表示件数を1から100の間に制限する。
if ($perPage < 1) $perPage = 1;
if ($perPage > 100) $perPage = 100;
// ページ番号が1未満にならないように保証する。
$followingPage = max(1, $followingPage);
$followersPage = max(1, $followersPage);

// データベースクエリ用のOFFSET値を計算する。
$followingOffset = ($followingPage - 1) * $perPage;
$followersOffset = ($followersPage - 1) * $perPage;

// 対象ユーザーIDが必須であり、有効な数値であるか検証する。
if (!isset($targetUserId) || !is_numeric($targetUserId)) {
    send_json_response(400, ['error' => 'ユーザーIDが指定されていません。']);
}

// --- 5. メイン処理 ---
try {
    // データベース接続を取得する。
    $pdo = get_pdo_connection();
    // セッションから現在のユーザーIDを取得し、なければ認証エラーとする。
    $currentUserId = $_SESSION['user_id'] ?? null;
    if (!$currentUserId) {
        send_json_response(403, ['error' => '認証情報が無効です。']);
    }

    // --- フォローリストの取得 ---
    // 対象ユーザーがフォローしている総数を取得。
    $following_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM follows f WHERE f.follower_id = ?");
    $following_count_stmt->execute([(int)$targetUserId]);
    $following_total = (int)$following_count_stmt->fetchColumn();
    $following_pages = (int)ceil($following_total / $perPage);

    // 対象ユーザーのフォローリストをページネーション付きで取得。
    // 各ユーザーに対して、現在のログインユーザーがフォローしているかも確認する。
    $following_stmt = $pdo->prepare("
        SELECT
            u.id,
            COALESCE(up.username, u.name, '名もなき猫') AS username,
            up.active_identicon,
            (f2.id IS NOT NULL) AS is_following
        FROM follows f
        JOIN users u ON f.following_id = u.id
        LEFT JOIN user_profiles up ON u.id = up.user_id
        LEFT JOIN follows f2 ON f2.follower_id = ? AND f2.following_id = u.id
        WHERE f.follower_id = ?
        ORDER BY f.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $following_stmt->bindValue(1, (int)$currentUserId, PDO::PARAM_INT);
    $following_stmt->bindValue(2, (int)$targetUserId, PDO::PARAM_INT);
    $following_stmt->bindValue(3, $perPage, PDO::PARAM_INT);
    $following_stmt->bindValue(4, $followingOffset, PDO::PARAM_INT);
    $following_stmt->execute();
    $following = $following_stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- フォロワーリストの取得 ---
    // 対象ユーザーのフォロワー総数を取得。
    $followers_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM follows f WHERE f.following_id = ?");
    $followers_count_stmt->execute([(int)$targetUserId]);
    $followers_total = (int)$followers_count_stmt->fetchColumn();
    $followers_pages = (int)ceil($followers_total / $perPage);

    // 対象ユーザーのフォロワーリストをページネーション付きで取得。
    // 各ユーザーに対して、現在のログインユーザーがフォローしているかも確認する。
    $followers_stmt = $pdo->prepare("
        SELECT
            u.id,
            COALESCE(up.username, u.name, '名もなき猫') AS username,
            up.active_identicon,
            (f2.id IS NOT NULL) AS is_following
        FROM follows f
        JOIN users u ON f.follower_id = u.id
        LEFT JOIN user_profiles up ON u.id = up.user_id
        LEFT JOIN follows f2 ON f2.follower_id = ? AND f2.following_id = u.id
        WHERE f.following_id = ?
        ORDER BY f.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $followers_stmt->bindValue(1, (int)$currentUserId, PDO::PARAM_INT);
    $followers_stmt->bindValue(2, (int)$targetUserId, PDO::PARAM_INT);
    $followers_stmt->bindValue(3, $perPage, PDO::PARAM_INT);
    $followers_stmt->bindValue(4, $followersOffset, PDO::PARAM_INT);
    $followers_stmt->execute();
    $followers = $followers_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 取得したフォローリスト、フォロワーリスト、およびそれぞれのページネーション情報をJSON形式で返す。
    send_json_response(200, [
        'following' => $following,
        'followers' => $followers,
        'following_pagination' => [
            'total_count' => $following_total,
            'total_pages' => $following_pages,
            'current_page' => $followingPage,
            'per_page' => $perPage,
            'offset' => $followingOffset
        ],
        'followers_pagination' => [
            'total_count' => $followers_total,
            'total_pages' => $followers_pages,
            'current_page' => $followersPage,
            'per_page' => $perPage,
            'offset' => $followersOffset
        ]
    ]);
} catch (Exception $e) {
    // 例外が発生した場合は、エラーハンドラに処理を委譲する。
    handle_exception($e);
}
