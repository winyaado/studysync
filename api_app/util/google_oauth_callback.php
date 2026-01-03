<?php
/**
 * API名: system/google_oauth_callback
 * 説明: Google OAuth のコールバックを処理します。
 * 認証: 不要
 * HTTPメソッド: GET/POST
 * 引数:
 *   - GET: code, state
 * 返り値:
 *   - リダイレクト: entrance.php / login.php
 * エラー: 400/403/404/405/500
 */
session_start();
require_once __DIR__ . '/GoogleOAuth2.php';
require_once __DIR__ . '/User.php';
require_once __DIR__ . '/UserProfile.php';

$googleOAuth2 = new GoogleOAuth2();
$userModel = new User();
$userProfileModel = new UserProfile();

if (isset($_GET['code'], $_GET['state'])) {
    $code = $_GET['code'];
    $state = $_GET['state'];

    $userData = $googleOAuth2->handleCallback($code, $state);

    if ($userData) {
        session_regenerate_id(true);

        $internalUser = $userModel->createOrUpdate($userData);
        if (!$internalUser) {
            error_log("Failed to create or update user for google_id: " . $userData['sub']);
            header('Location: ../../login.php?error=user_sync_failed');
            exit();
        }

        $userProfile = $userProfileModel->findByUserId($internalUser['id']);
        if (!$userProfile) {
            $userProfile = $userProfileModel->createDefaultProfile($internalUser['id']);
        }

        $_SESSION['user_id'] = $internalUser['id'];
        $_SESSION['google_id'] = $internalUser['google_id'];
        $_SESSION['user_email'] = $internalUser['email'];
        $_SESSION['tenant_id'] = $internalUser['tenant_id'];
        $_SESSION['is_admin'] = (bool)$internalUser['is_admin'];
        $_SESSION['user_name'] = $userProfile['username'];

        $_SESSION['active_identicon'] = $userProfile['active_identicon'] ?? null;

        if ($internalUser['banned_at'] !== null) {
            session_destroy();
            header('Location: ../../login.php?error=banned_user');
            exit();
        }
        if ($internalUser['tenant_banned_at'] !== null) {
            session_destroy();
            header('Location: ../../login.php?error=banned_domain');
            exit();
        }

        header('Location: ../../entrance.php');
        exit();
    }

    error_log("Google OAuth callback failed.");
    header('Location: ../../login.php?error=oauth_failed');
    exit();
}

error_log("Google OAuth callback: Missing code or state.");
header('Location: ../../login.php?error=missing_params');
exit();
