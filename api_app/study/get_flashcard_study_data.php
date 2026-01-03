<?php
/**
 * API名: study/get_flashcard_study_data
 * 説明: study/get_flashcard_study_data の処理を行います。
 * 認証: 必須
 * HTTPメソッド: GET
 * 引数:
 *   - GET: filter, ids
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
// GETリクエストから学習対象のコンテンツIDリストとフィルタ条件を取得する。
$idsParam = $_GET['ids'] ?? '';
$filter = $_GET['filter'] ?? '-1';
// セッションから現在のユーザーIDとテナントIDを取得する。
$userId = $_SESSION['user_id'];
$currentUserTenantId = $_SESSION['tenant_id'] ?? null;

// カンマ区切りのID文字列を数値の配列に変換する。
$contentIds = array_filter(explode(',', $idsParam), 'is_numeric');
// コンテンツIDが少なくとも1つ指定されているか検証する。
if (empty($contentIds)) {
    send_json_response(400, ['error' => '無効なセットIDが指定されました。']);
}
// フィルタ条件が許可された値のいずれかであるか検証し、無効な場合はデフォルト値'-1'を設定する。
if (!in_array($filter, ['-1', '0', '1', '2'])) {
    $filter = '-1';
}

// --- 5. メイン処理 ---
try {
    // データベース接続を取得する。
    $pdo = get_pdo_connection();

    // 1. 指定されたコンテンツIDのうち、現在のユーザーがアクセス可能なものだけに絞り込む。
    // コンテンツは公開済み、削除されていない、かつ、公開設定（public, 自身が所有, 同一テナント）に基づいてアクセス可能である必要がある。
    $placeholders = implode(',', array_fill(0, count($contentIds), '?'));
    $sql_access_check = "
        SELECT c.id FROM contents c
        JOIN users u ON c.user_id = u.id
        WHERE c.id IN ($placeholders)
          AND c.contentable_type = 'FlashCard'
          AND c.status = 'published'
          AND c.deleted_at IS NULL
          AND (
            c.visibility = 'public'
            OR c.user_id = ?
            OR (c.visibility = 'domain' AND u.tenant_id = ?)
          )
    ";
    $params_access_check = array_merge($contentIds, [$userId, $currentUserTenantId]);
    $stmt_access = $pdo->prepare($sql_access_check);
    $stmt_access->execute($params_access_check);
    $accessibleContentIds = $stmt_access->fetchAll(PDO::FETCH_COLUMN);

    // 学習可能なセットが見つからない場合は404エラーをスローする。
    if (empty($accessibleContentIds)) {
        throw new Exception("学習可能なフラッシュカードセットが見つかりません。", 404);
    }
    
    // 2. アクセス可能なフラッシュカードセットに含まれる単語データを取得する。
    $placeholders_accessible = implode(',', array_fill(0, count($accessibleContentIds), '?'));
    $sql_words = "
        SELECT 
            fw.id,
            fw.word,
            fw.definition,
            um.memory_level
        FROM flashcard_words fw
        JOIN flashcards f ON fw.flashcard_id = f.id
        JOIN contents c ON f.id = c.contentable_id AND c.contentable_type = 'FlashCard'
        LEFT JOIN user_flashcard_word_memory um ON fw.id = um.flashcard_word_id AND um.user_id = ?
        WHERE c.id IN ($placeholders_accessible)
    ";
    
    $params_words = array_merge([$userId], $accessibleContentIds);

    // フィルタ条件に基づいて取得する単語を絞り込む。
    if ($filter === '0') { // '0': 要復習（未学習も含む）
        $sql_words .= " AND (um.memory_level = 0 OR um.memory_level IS NULL)";
    } elseif ($filter !== '-1') { // '1':まあまあ, '2':完璧
        $sql_words .= " AND um.memory_level = ?";
        $params_words[] = $filter;
    }
    // '-1'（すべて）の場合は追加のWHERE句は不要。

    $stmt_words = $pdo->prepare($sql_words);
    $stmt_words->execute($params_words);
    $words = $stmt_words->fetchAll(PDO::FETCH_ASSOC);

    // 3. 学習対象が単一のセットの場合のみ、そのセットの詳細情報を取得する（オプション）。
    $setDetails = null;
    if (count($accessibleContentIds) === 1) {
        $stmt = $pdo->prepare("SELECT title, description, lecture_id, visibility FROM contents WHERE id = ?");
        $stmt->execute([$accessibleContentIds[0]]);
        $setDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 取得した単語リストとセット詳細情報をJSON形式で返す。
    send_json_response(200, [
        'words' => $words,
        'set_details' => $setDetails
    ]);

} catch (Exception $e) {
    // 例外が発生した場合は、エラーハンドラに処理を委譲する。
    handle_exception($e);
}
