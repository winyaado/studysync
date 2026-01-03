<?php
/**
 * API名: content/save_problem_set
 * 説明: content/save_problem_set の処理を行います。
 * 認証: 必須
 * HTTPメソッド: POST
 * 引数:
 *   - POST: description, id, lecture_id, questions, time_limit, title, visibility
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
// POSTリクエストから各種パラメータを取得する。IDは整数にキャストし、空の場合はnullとする。
$contentId = !empty($_POST['id']) ? (int)$_POST['id'] : null;
// セッションから現在のユーザーIDを取得する。
$userId = $_SESSION['user_id'];
$title = $_POST['title'] ?? '';
$description = $_POST['description'] ?? null;
$lectureId = isset($_POST['lecture_id']) && $_POST['lecture_id'] !== '' ? $_POST['lecture_id'] : null;
$timeLimit = !empty($_POST['time_limit']) ? (int)$_POST['time_limit'] : null;
$questions = $_POST['questions'] ?? []; // 問題の配列。デフォルトは空配列。
$visibility = $_POST['visibility'] ?? 'private'; // 公開範囲。デフォルトは'private'。

// タイトルが最大長を超えていないか検証する。
if (mb_strlen($title) > MAX_TITLE_LENGTH) {
    send_json_response(400, ['error' => 'タイトルは' . MAX_TITLE_LENGTH . '文字以内で入力してください。']);
}
// 説明文が最大長を超えていないか検証する。
if (strlen($description) > MAX_DESCRIPTION_SIZE_BYTES) {
    send_json_response(400, ['error' => '説明文が長すぎます。' . (MAX_DESCRIPTION_SIZE_BYTES / 1024) . 'KB以内で入力してください。']);
}
// 問題数が上限を超えていないか検証する。
if (count($questions) > MAX_QUESTIONS_PER_SET) {
    send_json_response(400, ['error' => '一度に作成できる問題数は' . MAX_QUESTIONS_PER_SET . '問までです。']);
}

// 各問題と選択肢のデータを検証する。
foreach ($questions as $index => $q) {
    $questionNumber = $index + 1; // ユーザー向けに1から始まる番号
    
    // 問題本文の存在と最大長を検証する。
    if (!isset($q['text']) || strlen($q['text']) > MAX_TEXT_SIZE_BYTES) {
        send_json_response(400, ['error' => "問題{$questionNumber}の本文が長すぎるか、存在しません。"]);
    }
    // 解説文の最大長を検証する（任意項目）。
    if (isset($q['explanation']) && strlen($q['explanation']) > MAX_TEXT_SIZE_BYTES) {
        send_json_response(400, ['error' => "問題{$questionNumber}の解説が長すぎます。"]);
    }
    
    // 選択肢の存在と配列形式を検証する。
    if (!isset($q['choices']) || !is_array($q['choices'])) {
        send_json_response(400, ['error' => "問題{$questionNumber}に選択肢がありません。"]);
    }
    // 選択肢の数が上限を超えていないか検証する。
    if (count($q['choices']) > MAX_CHOICES_PER_QUESTION) {
        send_json_response(400, ['error' => "問題{$questionNumber}の選択肢は" . MAX_CHOICES_PER_QUESTION . "個までです。"]);
    }
    // 各選択肢のテキスト長を検証する。
    foreach ($q['choices'] as $cIndex => $c) {
        $choiceNumber = $cIndex + 1;
        if (mb_strlen($c) > MAX_CHOICE_TEXT_LENGTH) {
            send_json_response(400, ['error' => "問題{$questionNumber}の選択肢{$choiceNumber}が長すぎます。"]);
        }
    }
}

// 'public'公開を選択した場合、現在のユーザーが管理者権限を持っているか検証する。
if ($visibility === 'public' && (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin'])) {
    send_json_response(403, ['error' => '全体に公開する権限がありません。']);
}

// --- 5. メイン処理 ---
try {
    // データベース接続を取得し、トランザクションを開始する。
    $pdo = get_pdo_connection();
    $pdo->beginTransaction();

    $new_content_id = $contentId;
    $problemSetId = null; // problem_setsテーブルのID
    $problemSetVersionId = null; // problem_set_versionsテーブルのID

    if ($contentId) {
        // --- 更新モード（新しいバージョンを作成） ---
        // 既存の問題集の所有権を確認し、problem_setsテーブルのIDを取得する。
        $stmt = $pdo->prepare("SELECT user_id, contentable_id FROM contents WHERE id = ? AND contentable_type = 'ProblemSet'");
        $stmt->execute([$contentId]);
        $content = $stmt->fetch(PDO::FETCH_ASSOC);

        // コンテンツが見つからない、または現在のユーザーが所有者でない場合は403エラーをスローする。
        if (!$content || $content['user_id'] != $userId) {
            throw new Exception("このコンテンツを更新する権限がありません。", 403);
        }
        $problemSetId = $content['contentable_id'];

        // contentsテーブルのメタデータ（タイトル、説明、講義ID、公開範囲、更新日時）を更新する。
        $stmt = $pdo->prepare("UPDATE contents SET title = ?, description = ?, lecture_id = ?, visibility = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$title, $description, $lectureId, $visibility, $contentId]);

        // 既存問題集の最新バージョン番号を取得し、新しいバージョン番号を生成する。
        $stmt = $pdo->prepare("SELECT MAX(version) AS max_version FROM problem_set_versions WHERE problem_set_id = ?");
        $stmt->execute([$problemSetId]);
        $latestVersion = $stmt->fetchColumn();
        $newVersionNumber = $latestVersion + 1;

        // problem_set_versionsテーブルに新しいバージョンを挿入し、そのIDを取得する。
        $stmt = $pdo->prepare("INSERT INTO problem_set_versions (problem_set_id, version, time_limit_minutes) VALUES (?, ?, ?)");
        $stmt->execute([$problemSetId, $newVersionNumber, $timeLimit]);
        $problemSetVersionId = $pdo->lastInsertId();

    } else {
        // --- 新規作成モード ---
        // コンテンツ作成上限数を超えていないか確認する。
        $stmt = $pdo->prepare("SELECT content_limit FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $limit = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(id) FROM contents WHERE user_id = ? AND deleted_at IS NULL");
        $stmt->execute([$userId]);
        $count = $stmt->fetchColumn();

        // 上限を超えている場合は403エラーをスローする。
        if ($count >= $limit) {
            throw new Exception("コンテンツの作成上限（{$limit}件）に達しました。", 403);
        }

        // problem_setsテーブルに新しい問題集を挿入し、そのIDを取得する。
        $stmt = $pdo->prepare("INSERT INTO problem_sets (user_id) VALUES (?)");
        $stmt->execute([$userId]);
        $problemSetId = $pdo->lastInsertId();

        // problem_set_versionsテーブルに最初のバージョンを挿入し、そのIDを取得する。
        $stmt = $pdo->prepare("INSERT INTO problem_set_versions (problem_set_id, version, time_limit_minutes) VALUES (?, 1, ?)");
        $stmt->execute([$problemSetId, $timeLimit]);
        $problemSetVersionId = $pdo->lastInsertId();

        // contentsテーブルに多態的なリレーション情報を含む新しいコンテンツを挿入する。
        $stmt = $pdo->prepare(
            "INSERT INTO contents (user_id, lecture_id, contentable_id, contentable_type, title, description, status, visibility) 
             VALUES (?, ?, ?, 'ProblemSet', ?, ?, 'published', ?)"
        );
        $stmt->execute([$userId, $lectureId, $problemSetId, $title, $description, $visibility]);
        $new_content_id = $pdo->lastInsertId(); // 新しく挿入されたコンテンツのIDを取得する。
    }

    // --- 新しい問題と選択肢の挿入（更新モード・新規作成モード共通） ---
    // questions配列が空でなく、かつ配列形式であることを確認する。
    if (!empty($questions) && is_array($questions)) {
        $displayOrder = 0;
        foreach ($questions as $questionData) {
            $displayOrder++;
            $questionText = $questionData['text'];
            $explanation = $questionData['explanation'] ?? null;
            $correctChoiceIndex = $questionData['correct_choice']; // 正解の選択肢のインデックス

            // questionsテーブルに問題を挿入し、そのIDを取得する。
            $stmt = $pdo->prepare("INSERT INTO questions (problem_set_version_id, question_text, explanation, display_order) VALUES (?, ?, ?, ?)");
            $stmt->execute([$problemSetVersionId, $questionText, $explanation, $displayOrder]);
            $questionId = $pdo->lastInsertId();

            // 選択肢の配列が空でなく、かつ配列形式であることを確認する。
            if (isset($questionData['choices']) && is_array($questionData['choices'])) {
                foreach ($questionData['choices'] as $index => $choiceText) {
                    // 現在の選択肢が正解であるか判断する。
                    $isCorrect = ($index == $correctChoiceIndex);
                    // choicesテーブルに選択肢を挿入する。
                    $stmt = $pdo->prepare("INSERT INTO choices (question_id, choice_text, is_correct) VALUES (?, ?, ?)");
                    $stmt->execute([$questionId, $choiceText, $isCorrect]);
                }
            }
        }
    }

    // トランザクションをコミットする。
    $pdo->commit();

    // 処理成功のレスポンスと、更新または新規作成されたコンテンツのIDをJSON形式で返す。
    send_json_response(200, [
        'success' => true,
        'status' => $contentId ? 'updated' : 'created',
        'content_id' => $new_content_id
    ]);

} catch (Exception $e) {
    // 例外が発生し、トランザクションがアクティブな場合はロールバックする。
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // エラーハンドラに処理を委譲する。
    handle_exception($e);
}
