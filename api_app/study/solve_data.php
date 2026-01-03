<?php
/**
 * API名: study/solve_data
 * 説明: study/solve_data の処理を行います。
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
    // 最新バージョンの問題集情報（タイトル、バージョンID、制限時間）を取得する。
    $stmt = $pdo->prepare("
        SELECT c.id, c.title, psv.id as version_id, psv.time_limit_minutes
        FROM contents c
        JOIN users u ON c.user_id = u.id
        JOIN problem_sets ps ON c.contentable_id = ps.id AND c.contentable_type = 'ProblemSet'
        JOIN problem_set_versions psv ON ps.id = psv.problem_set_id
        WHERE c.id = ?
          AND c.status = 'published'
          AND c.deleted_at IS NULL
          AND (
               c.visibility = 'public'
               OR c.user_id = ?
               OR (c.visibility = 'domain' AND u.tenant_id = ?)
              )
        ORDER BY psv.version DESC LIMIT 1
    ");
    $stmt->execute([$contentId, $userId, $currentUserTenantId]);
    $problemSet = $stmt->fetch(PDO::FETCH_ASSOC);

    // 問題集が見つからない、またはアクセス権がない場合は404エラーをスローする。
    if (!$problemSet) {
        throw new Exception("指定された問題集が見つかりません。", 404);
    }

    // 2. 取得した問題集の最新バージョンIDを使って、関連する問題と選択肢を取得する。
    $stmt = $pdo->prepare("
        SELECT q.id as question_id, q.question_text, c.id as choice_id, c.choice_text, c.is_correct
        FROM questions q
        JOIN choices c ON q.id = c.question_id
        WHERE q.problem_set_version_id = ?
        ORDER BY q.display_order, c.id
    ");
    $stmt->execute([$problemSet['version_id']]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. 取得した問題と選択肢のデータを、フロントエンドで扱いやすい形式（配列）に整形する。
    // 同時に、正解の選択肢IDを別途保存する。
    $problems = [];
    $correctAnswers = [];
    foreach ($results as $row) {
        $questionId = $row['question_id'];
        // 問題がまだ配列にない場合、新しいエントリを作成する。
        if (!isset($problems[$questionId])) {
            $problems[$questionId] = [
                'id' => $questionId,
                'question' => $row['question_text'],
                'choices' => []
            ];
        }
        // 現在の選択肢を問題に追加する。
        $problems[$questionId]['choices'][] = [
            'id' => $row['choice_id'],
            'text' => $row['choice_text']
        ];
        // 正解の選択肢である場合、そのIDを記録する。
        if ($row['is_correct']) {
            $correctAnswers[$questionId] = $row['choice_id'];
        }
    }
    
    // 4. 整形した正解データをセッションに保存し、後で回答提出API (submit_exam.php) で利用できるようにする。
    $_SESSION['exam_correct_answers'] = $correctAnswers;

    // 問題集の詳細と整形された問題リストをJSON形式で返す。
    send_json_response(200, [
        'problemSet' => [
            'id' => $problemSet['id'],
            'title' => $problemSet['title'],
            'time_limit_minutes' => $problemSet['time_limit_minutes']
        ],
        'problems' => array_values($problems) // JavaScriptで扱いやすいようにインデックスを0からにリセット
    ]);

} catch (Exception $e) {
    // 例外が発生した場合は、エラーハンドラに処理を委譲する。
    handle_exception($e);
}
