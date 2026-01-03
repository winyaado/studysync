<?php
/**
 * API名: system/search_lectures
 * 説明: system/search_lectures の処理を行います。
 * 認証: 必須
 * HTTPメソッド: GET
 * 引数:
 *   - GET: q, quarters
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
// GETリクエストから検索クエリと選択されたクォーターを取得する。
$searchQuery = $_GET['q'] ?? '';
$selectedQuarters = $_GET['quarters'] ?? [];
// セッションからテナントIDを取得する。このAPIでは必須項目。
$tenantId = $_SESSION['tenant_id'];

// 検索クエリが最大長を超えていないか検証する。
if (mb_strlen($searchQuery) > MAX_SEARCH_QUERY_LENGTH) {
    send_json_response(400, ['error' => '検索クエリは' . MAX_SEARCH_QUERY_LENGTH . '文字以内で入力してください。']);
}
// クォーター選択が文字列で渡された場合、カンマで分割して配列に変換する。
if (is_string($selectedQuarters)) {
    $selectedQuarters = !empty($selectedQuarters) ? explode(',', $selectedQuarters) : [];
}

// --- 5. メイン処理 ---
try {
    // データベース接続を取得する。
    $pdo = get_pdo_connection();

    // --- クエリ構築 ---
    // プリペアドステートメント用のパラメータを格納する配列。テナントIDは常に必須条件。
    $params = [$tenantId];
    // WHERE句の初期条件。
    $where_conditions = ['tenant_id = ?'];

    // 検索クエリが存在する場合、講義コード(lecture_code)と講義名(name)に対するLIKE検索条件を追加する。
    if (!empty($searchQuery)) {
        $where_conditions[] = '(lecture_code LIKE ? OR name LIKE ?)';
        $searchParam = '%' . $searchQuery . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    // クォーターフィルタが指定されている場合、条件を追加する。
    if (!empty($selectedQuarters) && is_array($selectedQuarters)) {
        $quarterConditions = [];
        foreach ($selectedQuarters as $q) {
            $qInt = (int)$q; // 整数にキャスト
            if ($qInt > 0) {
                // `通期` (バイナリで10000、10進数で16) の場合は完全一致で検索。
                if ($qInt === 16) {
                    $quarterConditions[] = 'quarter = 16';
                } else {
                    // それ以外のクォーター（1Q, 2Q, 3Q, 4Q）はビット演算で検索し、該当ビットが立っているものを対象とする。
                    $quarterConditions[] = '(quarter & ' . $qInt . ') > 0';
                }
            }
        }
        if (!empty($quarterConditions)) {
            // 複数のクォーター条件をORで結合し、メインのWHERE句に追加する。
            $where_conditions[] = '(' . implode(' OR ', $quarterConditions) . ')';
        }
    }

    // 全てのWHERE句条件をANDで結合して最終的なWHERE句文字列を生成する。
    $where_sql = implode(' AND ', $where_conditions);
    // 講義情報を取得するSQLクエリを組み立てる。講義コードで昇順にソートし、最大200件まで取得する。
    $sql = "SELECT id, lecture_code, name, quarter FROM lectures WHERE $where_sql ORDER BY lecture_code ASC LIMIT 200";

    // --- クエリ実行 ---
    // 組み立てたSQLとパラメータを使ってクエリを実行し、講義情報を取得する。
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $lectures = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 取得した講義リストをJSON形式で返す。
    send_json_response(200, ['success' => true, 'lectures' => $lectures]);

} catch (Exception $e) {
    // 例外が発生した場合は、エラーハンドラに処理を委譲する。
    handle_exception($e);
}
