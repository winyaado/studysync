<?php
/**
 * API名: content/search_contents
 * 説明: content/search_contents の処理を行います。
 * 認証: 必須
 * HTTPメソッド: GET
 * 引数:
 *   - GET: follow_only, lecture_id, lecture_name, order, page, q, sort, types, user_id
 * 返り値:
 *   - JSON: success / error
 * エラー: 400/403/404/405/500
 */
// --- 1. 必須ファイルの読み込み ---
// （このファイルでは、共通ヘルパーで読み込まれるため、ここでの追加の読み込みは不要）

// --- 2. 認証と権限チェック ---

// --- 3. HTTPメソッドの検証 ---
// GETリクエストでなければ、405エラーを返して処理を終了する
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_response(405, ['error' => '許可されていないメソッドです。']);
}

// --- 4. パラメータの取得とバリデーション ---
// GETリクエストから様々な検索・フィルタリングパラメータを取得する。
$searchQuery = $_GET['q'] ?? '';
$selectedTypes = $_GET['types'] ?? [];
// selectedTypesが文字列の場合、カンマで分割して配列に変換する。
if (is_string($selectedTypes)) {
    $selectedTypes = !empty($selectedTypes) ? explode(',', $selectedTypes) : [];
}
$selectedLectureId = $_GET['lecture_id'] ?? '';
$followOnly = ($_GET['follow_only'] ?? '') === '1';
// $selectedLectureName はフロントエンドへ情報を返すために利用するのみで、クエリには直接使用しない。

// プロフィールページ用に、対象ユーザーIDを取得する。
$targetUserId = $_GET['user_id'] ?? null;

// ソートパラメータの取得。指定がなければ'updated_at'、順序は'desc'とする。
$sort_by = $_GET['sort'] ?? 'updated_at';
$order = $_GET['order'] ?? 'desc';

// ページネーションパラメータの取得。1ページあたりの表示件数は20、ページ番号は1以上を保証する。
$per_page = 20;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, $current_page);
$offset = ($current_page - 1) * $per_page; // データベースからの取得開始位置

// セッションから現在のユーザーIDとテナントIDを取得する（公開範囲のチェックに使用）。
$currentUserId = $_SESSION['user_id'];
$currentUserTenantId = $_SESSION['tenant_id'] ?? null; // テナントIDは設定されていない場合もある。

// 検索クエリの長さが最大長を超えていないか検証する。
if (mb_strlen($searchQuery) > MAX_SEARCH_QUERY_LENGTH) {
    send_json_response(400, ['error' => '検索クエリは' . MAX_SEARCH_QUERY_LENGTH . '文字以内で入力してください。']);
}

// ソート対象カラムがホワイトリストに含まれているか検証し、無効な場合はデフォルト値に設定する。
$sortable_columns = [
    'rating' => '(SELECT AVG(r.rating) FROM ratings r WHERE r.rateable_id = c.id AND r.rateable_type = c.contentable_type)',
    'updated_at' => 'c.updated_at'
];
if (!array_key_exists($sort_by, $sortable_columns)) {
    $sort_by = 'updated_at';
}
// ソート順がホワイトリストに含まれているか検証し、無効な場合はデフォルト値に設定する。
if (!in_array(strtolower($order), ['asc', 'desc'])) {
    $order = 'desc';
}

// --- 5. メイン処理 ---
try {
    // データベース接続を取得する。
    $pdo = get_pdo_connection();

    // --- クエリ構築の準備 ---
    // プリペアドステートメント用のパラメータを格納する配列を初期化。
    $params = [];
    // WHERE句の初期条件：公開済みで削除されていないコンテンツに限定。
    $where_conditions = ["c.status = 'published'", "c.deleted_at IS NULL"];

    // --- 公開範囲（visibility）に関する条件を構築 ---
    if ($targetUserId) {
        // プロフィールページ用の検索ロジックの場合、指定されたユーザーのコンテンツに絞り込む。
        $where_conditions[] = "c.user_id = ?";
        $params[] = $targetUserId;

        // 閲覧者から見た公開範囲の条件を定義。
        $visibility_conditions = [];
        $visibility_conditions[] = "c.visibility = 'public'"; // 'public' なコンテンツは誰でも閲覧可能。

        // 閲覧者とプロフィール対象者が同じテナントに属している場合、'domain'公開のコンテンツも閲覧可能。
        if ($currentUserTenantId !== null) {
            $visibility_conditions[] = "(c.visibility = 'domain' AND u.tenant_id = ?)";
            $params[] = $currentUserTenantId;
        }
        // 閲覧者がコンテンツの作者本人である場合、'private'公開のコンテンツも閲覧可能。
        if ($currentUserId === (int)$targetUserId) {
            $visibility_conditions[] = "c.visibility = 'private'";
        }
        // 上記の公開範囲条件をORで結合し、メインのWHERE句に追加。
        $where_conditions[] = "(" . implode(' OR ', $visibility_conditions) . ")";

    } else {
        // 従来の汎用検索ロジックの場合 (ログインユーザーがアクセス可能なコンテンツを検索)。
        $visibility_rules = [];
        $visibility_rule_params = [];
        $visibility_rules[] = "c.visibility = 'public'"; // 1. コンテンツが 'public'（全体公開）。
        $visibility_rules[] = "c.user_id = ?"; // 2. コンテンツの所有者が現在のユーザー。
        $visibility_rule_params[] = $currentUserId;

        // 3. コンテンツが 'domain'（ドメイン内公開）で、かつコンテンツ所有者のテナントIDと現在のユーザーのテナントIDが一致。
        if ($currentUserTenantId !== null) {
            $visibility_rules[] = "(c.visibility = 'domain' AND u.tenant_id = ?)";
            $visibility_rule_params[] = $currentUserTenantId;
        }
        // 上記の公開範囲ルールをORで結合し、WHERE句に追加。
        $where_conditions[] = "(" . implode(' OR ', $visibility_rules) . ")";
        // 公開範囲のパラメータをメインのパラメータ配列の先頭に追加する。
        $params = array_merge($visibility_rule_params, $params);
    }

    // --- フィルタリング条件の追加 ---
    // 「フォロー中のユーザーのみ」フィルタが有効で、かつプロフィールページ検索でない場合、フォロー中のユーザーのコンテンツに絞り込む。
    if ($followOnly && !$targetUserId) {
        $where_conditions[] = "c.user_id IN (SELECT f.following_id FROM follows f WHERE f.follower_id = ?)";
        $params[] = $currentUserId;
    }

    // 検索クエリが存在する場合、講義名、作者名、コンテンツタイトル、説明に対してLIKE検索条件を追加する。
    if (!empty($searchQuery)) {
        $search_param = '%' . $searchQuery . '%';
        $where_conditions[] = "(l.name LIKE ? OR COALESCE(up.username, '名もなき猫') LIKE ? OR c.title LIKE ? OR c.description LIKE ?)";
        array_push($params, $search_param, $search_param, $search_param, $search_param);
    }

    // コンテンツタイプフィルタが指定されている場合、対象タイプに絞り込む。
    if (!empty($selectedTypes) && is_array($selectedTypes)) {
        // フロントエンドから送られるタイプ名をデータベースのカラム値にマッピングする。
        $mappedTypes = array_filter(array_map(fn($t) => [
            'note' => 'Note',
            'problem' => 'ProblemSet',
            'flashcard' => 'FlashCard'
        ][$t] ?? null, $selectedTypes));
        if (!empty($mappedTypes)) {
            // マッピングされたタイプが空でなければ、IN句を作成しWHERE句に追加する。
            $placeholders = implode(',', array_fill(0, count($mappedTypes), '?'));
            $where_conditions[] = "c.contentable_type IN ($placeholders)";
            $params = array_merge($params, $mappedTypes);
        }
    }

    // 講義IDフィルタが指定されている場合、特定の講義に絞り込む。
    if (!empty($selectedLectureId)) {
        $where_conditions[] = "c.lecture_id = ?";
        $params[] = $selectedLectureId;
    }

    // --- クエリの実行とレスポンス ---
    // 全てのWHERE句条件をANDで結合して最終的なWHERE句文字列を生成する。
    $where_sql = implode(' AND ', $where_conditions);

    // SQLのFROM句とWHERE句の本体を構築する。これは総件数クエリとデータ取得クエリで共有される。
    $sql_body = "
        FROM contents c
        JOIN users u ON c.user_id = u.id
        LEFT JOIN user_profiles up ON u.id = up.user_id
        LEFT JOIN lectures l ON c.lecture_id = l.lecture_code AND l.tenant_id = u.tenant_id
        WHERE $where_sql
    ";

    // 総件数クエリを実行して、ページネーション情報を計算する。
    $count_stmt = $pdo->prepare("SELECT COUNT(c.id) " . $sql_body);
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetchColumn();
    $total_pages = ceil($total_count / $per_page);

    // ソート順を決定する。評価順の場合はNULL値を考慮したソート順を適用する。
    $order_by_column_sql = $sortable_columns[$sort_by];
    if ($sort_by === 'rating') {
        $order_by_clause = (strtolower($order) === 'asc')
            ? "CASE WHEN {$order_by_column_sql} IS NULL THEN 1 ELSE 0 END, {$order_by_column_sql} ASC"
            : "{$order_by_column_sql} DESC";
    } else {
        $order_by_clause = $order_by_column_sql . " " . $order;
    }

    // 実際のコンテンツデータを取得するSQLを準備する。
    $data_sql = "
        SELECT
            c.id, c.title, c.lecture_id, l.name AS lecture_name, c.contentable_type, c.updated_at,
            c.user_id AS author_user_id,
            COALESCE(up.username, '名もなき猫') AS author_name,
            (SELECT AVG(r.rating) FROM ratings r WHERE r.rateable_id = c.id AND r.rateable_type = c.contentable_type) as avg_rating,
            (uac.id IS NOT NULL) AS is_active
        FROM contents c
        JOIN users u ON c.user_id = u.id
        LEFT JOIN user_profiles up ON u.id = up.user_id
        LEFT JOIN lectures l ON c.lecture_id = l.lecture_code AND l.tenant_id = u.tenant_id
        LEFT JOIN user_active_contents uac ON c.id = uac.content_id AND uac.user_id = ?
        WHERE " . $where_sql . "
        ORDER BY " . $order_by_clause . "
        LIMIT ? OFFSET ?
    ";

    $data_stmt = $pdo->prepare($data_sql);

    // メインクエリのパラメータをバインドする。
    $param_idx = 1;
    $data_stmt->bindValue($param_idx++, $currentUserId, PDO::PARAM_INT); // uac.user_id 用
    // WHERE句のために構築されたパラメータを順次バインドする。
    foreach ($params as $param) {
        $data_stmt->bindValue($param_idx++, $param);
    }
    // LIMITとOFFSETパラメータを整数型として明示的にバインドする。
    $data_stmt->bindValue($param_idx++, $per_page, PDO::PARAM_INT);
    $data_stmt->bindValue($param_idx++, $offset, PDO::PARAM_INT);

    $data_stmt->execute();
    $results = $data_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 検索結果とページネーション情報をJSON形式で返す。
    send_json_response(200, [
        'results' => $results,
        'pagination' => [
            'total_count' => (int)$total_count,
            'total_pages' => (int)$total_pages,
            'current_page' => (int)$current_page,
            'per_page' => (int)$per_page,
            'offset' => (int)$offset
        ],
        'request_params' => [ // フロントエンドの状態復元のために、受け取ったパラメータを返す
            'q' => $searchQuery,
            'types' => $selectedTypes,
            'lecture_id' => $selectedLectureId,
            'lecture_name' => $_GET['lecture_name'] ?? '', // 元のGETパラメータを返す
            'follow_only' => $followOnly ? '1' : '',
            'sort' => $sort_by,
            'order' => $order,
            'page' => $current_page
        ]
    ]);

} catch (Exception $e) {
    // 例外が発生した場合は、エラーハンドラに処理を委譲する。
    handle_exception($e);
}
