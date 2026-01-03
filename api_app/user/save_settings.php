<?php
/**
 * API名: user/save_settings
 * 説明: user/save_settings の処理を行います。
 * 認証: 必須
 * HTTPメソッド: POST
 * 引数:
 *   - POST: new_bio, new_username
 * 返り値:
 *   - JSON: success / error
 * エラー: 400/403/404/405/500
 */
// --- 1. 必須ファイルの読み込み ---
require_once __DIR__ . '/../util/UserProfile.php';

// --- 2. 認証と権限チェック ---

// --- 3. HTTPメソッドの検証 ---
// POSTリクエストでなければ、405エラーを返して処理を終了する
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(405, ['error' => '許可されていないメソッドです。']);
}

// --- 4. パラメータの取得とバリデーション ---
// このAPIは更新対象によって処理が分岐するため、パラメータの取得とバリデーションはメイン処理内で行う。
// セッションからユーザーIDのみを取得しておく。
$userId = $_SESSION['user_id'];

// --- 5. メイン処理 ---
try {
    $userProfileModel = new UserProfile();

    // new_usernameパラメータが存在する場合、ユーザー名の更新処理を行う。
    if (isset($_POST['new_username'])) {
        // パラメータをトリムして取得する。
        $newUsername = trim($_POST['new_username']);
        // ユーザー名が1文字以上50文字以下であるか検証する。
        if ($newUsername === '' || mb_strlen($newUsername) > 50) {
            send_json_response(400, ['error' => 'ユーザー名は1文字以上50文字以下で入力してください。']);
        }

        // データベースのユーザー名を更新する。
        if ($userProfileModel->updateUsername($userId, $newUsername)) {
            // 成功した場合、セッションのユーザー名も更新し、成功レスポンスを返す。
            $_SESSION['user_name'] = $newUsername;
            send_json_response(200, ['success' => true, 'message' => 'ユーザー名を更新しました。']);
        } else {
            // 更新に失敗した場合は500エラーを返す。
            send_json_response(500, ['error' => 'ユーザー名の更新に失敗しました。']);
        }
    }

    // new_bioパラメータが存在する場合、自己紹介文の更新処理を行う。
    if (isset($_POST['new_bio'])) {
        // パラメータをトリムして取得する。
        $newBio = trim($_POST['new_bio']);
        // 自己紹介文が1000文字以下であるか検証する。
        if (mb_strlen($newBio) > 1000) {
            send_json_response(400, ['error' => '自己紹介文は1000文字以下で入力してください。']);
        }

        // データベースの自己紹介文を更新する。
        if ($userProfileModel->updateBio($userId, $newBio)) {
            // 成功した場合、成功レスポンスを返す。
            send_json_response(200, ['success' => true, 'message' => '自己紹介文を更新しました。']);
        } else {
            // 更新に失敗した場合は500エラーを返す。
            send_json_response(500, ['error' => '自己紹介文の更新に失敗しました。']);
        }
    }

    // 更新対象のパラメータが何も指定されていない場合は、400エラーを返す。
    send_json_response(400, ['error' => '更新対象が指定されていません。']);

} catch(Exception $e) {
    // 例外が発生した場合は、エラーハンドラに処理を委譲する。
    handle_exception($e);
}
