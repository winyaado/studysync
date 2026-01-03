<?php
/**
 * API名: content/get_problem_set_for_edit
 * 説明: content/get_problem_set_for_edit の処理を行います。
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
// セッションから現在のユーザーIDを取得する。
$userId = $_SESSION['user_id'];

// コンテンツIDが必須であり、有効な整数であるか検証する。
if (!$contentId || !filter_var($contentId, FILTER_VALIDATE_INT)) {
    send_json_response(400, ['error' => '無効なコンテンツIDです。']);
}

// --- 5. メイン処理 ---
try {
    // データベース接続を取得する。
    $pdo = get_pdo_connection();

    // 問題集の基本情報とコンテンツを取得する。
    // 指定されたコンテンツIDが現在のユーザーによって所有されているかを確認する。
    // 最新バージョンの問題集情報（タイトル、説明、講義ID、制限時間、可視性など）を取得する。
    $stmt = $pdo->prepare("
        SELECT c.title, c.description, c.lecture_id, l.name as lecture_name, psv.time_limit_minutes, psv.id as version_id, c.visibility
        FROM contents c
        JOIN users u ON c.user_id = u.id
        JOIN problem_sets ps ON c.contentable_id = ps.id AND c.contentable_type = 'ProblemSet'
        JOIN problem_set_versions psv ON ps.id = psv.problem_set_id
        LEFT JOIN lectures l ON c.lecture_id = l.lecture_code AND l.tenant_id = u.tenant_id
        WHERE c.id = ? AND c.user_id = ?
        ORDER BY psv.version DESC LIMIT 1
    ");
    $stmt->execute([$contentId, $userId]);
    $problemSetDetails = $stmt->fetch(PDO::FETCH_ASSOC);

    // 問題集が見つからない、または編集権限がない場合は404エラーをスローする。
    if (!$problemSetDetails) {
        throw new Exception("指定された問題集が見つからないか、編集権限がありません。", 404);
    }

    // 取得した問題集の最新バージョンIDを使って、関連する問題と選択肢を取得する。
    $stmt = $pdo->prepare("
        SELECT q.id as question_id, q.question_text, q.explanation, c.choice_text, c.is_correct
        FROM questions q
        JOIN choices c ON q.id = c.question_id
        WHERE q.problem_set_version_id = ?
        ORDER BY q.display_order, c.id
    ");
    $stmt->execute([$problemSetDetails['version_id']]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 取得した問題と選択肢のデータを、JavaScriptで扱いやすい形式（配列）に整形する。
    $problems = [];
    $questionsMap = [];
    foreach ($results as $row) {
        $questionId = $row['question_id'];
        if (!isset($questionsMap[$questionId])) {
            $questionsMap[$questionId] = [
                'question_text' => $row['question_text'],
                'explanation' => $row['explanation'],
                'choices' => [],
                'correct_choice_index' => -1
            ];
        }
        $questionsMap[$questionId]['choices'][] = $row['choice_text'];
        if ($row['is_correct']) {
            // 正解の選択肢の1から始まるインデックスを設定する。
            $questionsMap[$questionId]['correct_choice_index'] = count($questionsMap[$questionId]['choices']);
        }
    }
    
    // 整形された問題の配列を最終的な問題リストとして取得する（順序を維持）。
    $problems = array_values($questionsMap);

    // 取得した問題集の詳細と問題リストをJSON形式で返す。
    send_json_response(200, [
        'details' => $problemSetDetails,
        'problems' => $problems
    ]);

} catch (Exception $e) {
    // 例外が発生した場合は、エラーハンドラに処理を委譲する。
    handle_exception($e);
}
