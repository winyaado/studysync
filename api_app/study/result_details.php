<?php
/**
 * API名: study/result_details
 * 説明: study/result_details の処理を行います。
 * 認証: 必須
 * HTTPメソッド: GET
 * 引数:
 *   - GET: attempt_id
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
// GETリクエストから試行ID (attempt_id) を取得する。指定がなければnullとする。
$attemptId = $_GET['attempt_id'] ?? null;
// セッションから現在のユーザーIDを取得する。
$userId = $_SESSION['user_id'];

// 試行IDが必須であり、有効な整数であるか検証する。
if (!$attemptId || !filter_var($attemptId, FILTER_VALIDATE_INT)) {
    send_json_response(400, ['error' => '試行IDは必須です。']);
}

// --- 5. メイン処理 ---
try {
    // データベース接続を取得する。
    $pdo = get_pdo_connection();

    // 指定された試行IDの試験結果の基本情報を取得する。同時に、結果の所有者が現在のユーザーであることを確認する。
    $stmt = $pdo->prepare("SELECT * FROM exam_attempts WHERE id = ? AND user_id = ?");
    $stmt->execute([$attemptId, $userId]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    // 試験結果が見つからない、またはアクセス権がない場合は404エラーをスローする。
    if (!$attempt) {
        throw new Exception("指定された試験結果が見つからないか、アクセス権がありません。", 404);
    }

    // 試験結果に関連する問題集の情報を取得する。
    $stmt = $pdo->prepare("
        SELECT c.id, c.title 
        FROM contents c
        JOIN problem_sets ps ON c.contentable_id = ps.id
        JOIN problem_set_versions psv ON ps.id = psv.problem_set_id
        WHERE psv.id = ? AND c.contentable_type = 'ProblemSet'
    ");
    $stmt->execute([$attempt['problem_set_version_id']]);
    $problemSet = $stmt->fetch(PDO::FETCH_ASSOC);

    // 各問題の詳細な結果（問題文、解説、ユーザーの回答、正解）を取得する。
    $sql = "
        SELECT
            q.id AS question_id,
            q.question_text,
            q.explanation,
            ua.selected_choice_id AS user_choice_id,
            (SELECT GROUP_CONCAT(CONCAT(id, ':', choice_text) SEPARATOR '|') FROM choices WHERE question_id = q.id) AS all_choices,
            (SELECT id FROM choices WHERE question_id = q.id AND is_correct = 1) AS correct_choice_id
        FROM questions q
        LEFT JOIN user_answers ua ON q.id = ua.question_id AND ua.exam_attempt_id = ?
        WHERE q.problem_set_version_id = ?
        ORDER BY q.display_order
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$attemptId, $attempt['problem_set_version_id']]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 取得したデータをフロントエンドで表示しやすい形式に整形する。
    $detailedResults = [];
    foreach ($results as $row) {
        // all_choices文字列を解析して、選択肢IDとテキストのマッピングを作成する。
        $choicesMap = [];
        if(isset($row['all_choices']) && $row['all_choices']) {
            foreach(explode('|', $row['all_choices']) as $choice) {
                list($id, $text) = explode(':', $choice, 2);
                $choicesMap[$id] = $text;
            }
        }
        
        // 整形後の結果配列に各問題のデータを追加する。
        $detailedResults[] = [
            'question_text' => $row['question_text'],
            'explanation' => $row['explanation'],
            'user_answer_text' => $row['user_choice_id'] ? ($choicesMap[$row['user_choice_id']] ?? 'N/A') : '未回答',
            'correct_answer_text' => $row['correct_choice_id'] ? ($choicesMap[$row['correct_choice_id']] ?? 'N/A') : 'N/A',
            'is_correct' => ($row['user_choice_id'] == $row['correct_choice_id']) && ($row['user_choice_id'] !== null)
        ];
    }
    
    // 問題集情報、試行情報、および詳細な結果をJSON形式で返す。
    send_json_response(200, [
        'problemSet' => $problemSet,
        'attempt' => $attempt,
        'detailedResults' => $detailedResults
    ]);

} catch (Exception $e) {
    // 例外が発生した場合は、エラーハンドラに処理を委譲する。
    handle_exception($e);
}
