<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api_app/util/api_helpers.php';
require_once __DIR__ . '/../api_app/util/GoogleOAuth2.php';

$googleOAuth2 = new GoogleOAuth2();
$error_message = '';

// ログアウトアクションがリクエストされたか確認
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    // 全てのセッション変数をクリア
    $_SESSION = array();

    // セッションを破棄
    session_destroy();

    // クリーンな状態を保証するためログインページにリダイレクト
    header('Location: login.php');
    exit();
}


// ユーザーが既にログインしている場合、エントランスページにリダイレクト
if (isset($_SESSION['user_id'])) {
    header('Location: entrance.php'); // Assuming entrance.php will be the main page
    exit();
}

// 「Googleでログイン」ボタンがクリックされた場合、OAuthフローを開始
if (isset($_POST['google_login'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrfToken)) {
        $error_message = '不正な操作が検出されました。もう一度お試しください。';
    } else {
        $googleOAuth2->initiateLogin();
    }
}

// エラーメッセージがあれば表示
if ($error_message === '' && isset($_GET['error'])) {
    if ($_GET['error'] == 'oauth_failed') {
        $error_message = 'Google認証に失敗しました。もう一度お試しください。';
    } elseif ($_GET['error'] == 'missing_params') {
        $error_message = '認証パラメータが不足しています。';
    }
}
$csrfToken = get_csrf_token();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン - StudySync</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/login.css?v=<?= APP_ASSET_VERSION ?>">
</head>
<body>
    <div class="login-container">
        <div class="card login-card">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <h1 class="h3 mb-3 fw-bold text-primary">StudySync</h1>
                    <p class="text-muted">
                        Googleアカウントでログイン
                    </p>
                </div>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <div class="d-grid">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <button type="submit" name="google_login" class="btn btn-primary btn-lg w-100">
                            <span class="d-flex align-items-center justify-content-center">
                                Googleでログイン
                            </span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php require_once __DIR__ . '/parts/_footer.php'; ?>
