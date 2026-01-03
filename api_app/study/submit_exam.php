<?php
/**
 * API名: study/submit_exam
 * 説明: study/submit_exam の処理を行います。
 * 認証: 必須
 * HTTPメソッド: POST
 * 引数:
 *   - POST: answers, problem_set_id
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
// セッションとPOSTリクエストから各種データを取得する。
$userId = $_SESSION['user_id'];
$contentId = $_POST['problem_set_id'] ?? null;
$userAnswers = $_POST['answers'] ?? [];
$correctAnswers = $_SESSION['exam_correct_answers'] ?? null;

// コンテンツIDが必須であり、有効な整数であるか検証する。
if (!$contentId || !filter_var($contentId, FILTER_VALIDATE_INT)) {
    send_json_response(400, ['error' => '無効な問題集IDです。']);
}
// セッションに採点用の正解データが存在するか検証する。なければ試験の再開を促す。
if (!is_array($correctAnswers)) {
    send_json_response(400, ['error' => '試験結果の処理に必要な情報が不足しています。もう一度試験を開始してください。']);
}
// 提出された回答が配列形式であるか検証する。
if (!is_array($userAnswers)) {
    send_json_response(400, ['error' => '提出された回答の形式が不正です。']);
}

// --- 5. メイン処理 ---
try {
    // データベース接続を取得する。
    $pdo = get_pdo_connection();

    // 提出されたコンテンツIDに対応する、最新バージョンの問題集IDを取得する。
    $stmt = $pdo->prepare("
        SELECT psv.id 
        FROM problem_set_versions psv 
        JOIN problem_sets ps ON psv.problem_set_id = ps.id 
        JOIN contents c ON ps.id = c.contentable_id 
        WHERE c.id = ? AND c.contentable_type = 'ProblemSet' 
        ORDER BY psv.version DESC LIMIT 1
    ");
    $stmt->execute([$contentId]);
    $versionId = $stmt->fetchColumn();

    // 対応するバージョンが見つからない場合はエラーとする。
    if (!$versionId) {
        throw new Exception("対象の問題集のバージョンが見つかりません。", 404);
    }
    
    // データベースから実際の問題数を取得し、回答数やセッションデータと矛盾がないか検証する。
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE problem_set_version_id = ?");
    $stmt->execute([$versionId]);
    $actualQuestionCount = $stmt->fetchColumn();

    // 提出された回答数が実際の問題数より多い場合はエラーとする。
    if (count($userAnswers) > $actualQuestionCount) {
        send_json_response(400, ['error' => '提出された回答の数が問題数より多いです。']);
    }
    // セッションに保存された正解データ数と実際の問題数が一致しない場合は、データの不整合としてエラーを返し、セッションをクリアする。
    if (count($correctAnswers) != $actualQuestionCount) {
        unset($_SESSION['exam_correct_answers']);
        send_json_response(400, ['error' => 'サーバー上の正解データと問題数が一致しません。もう一度試験を開始してください。']);
    }

    // --- 答え合わせとスコア計算 ---
    $score = 0;
    $totalQuestions = $actualQuestionCount; // 検証済みの問題数を使用。
    foreach ($correctAnswers as $questionId => $correctChoiceId) {
        // ユーザーの回答が存在し、かつ正解と一致する場合にスコアを加算する。
        if (isset($userAnswers[$questionId]) && $userAnswers[$questionId] == $correctChoiceId) {
            $score++;
        }
    }
    
    // --- exam_attempts テーブルに結果を保存 ---
    // 試験結果のサマリーを保存し、試行ID (attemptId) を取得する。
    $stmt = $pdo->prepare("INSERT INTO exam_attempts (user_id, problem_set_version_id, score, total_questions, completed_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$userId, $versionId, $score, $totalQuestions]);
    $attemptId = $pdo->lastInsertId();

    // 試行IDが取得できなければエラーとする。
    if (!$attemptId) {
        throw new Exception("試験結果の保存に失敗しました。", 500);
    }

    // --- user_answers テーブルに個々の回答を保存 ---
    if (!empty($userAnswers)) {
        $stmt = $pdo->prepare("INSERT INTO user_answers (exam_attempt_id, question_id, selected_choice_id) VALUES (?, ?, ?)");
        foreach($userAnswers as $questionId => $choiceId) {
            // 回答が選択されている（空でない）場合のみ保存する。
            if(!empty($choiceId)){
                 $stmt->execute([$attemptId, $questionId, $choiceId]);
            }
        }
    }
    
    // --- セッションデータをクリア ---
    // 一度使用した正解データは不要なため、セッションから削除する。
    unset($_SESSION['exam_correct_answers']);
    
    // 処理成功のレスポンスと、新しい試行IDをJSON形式で返す。
    send_json_response(200, ['success' => true, 'attempt_id' => $attemptId]);

} catch (Exception $e) {
    // 例外が発生した場合は、エラーハンドラに処理を委譲する。
    handle_exception($e);
}
