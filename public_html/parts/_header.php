<?php
// このヘッダーは、インクルード元のページで以下の変数が設定されていることを前提としています:
// $pageTitle (string): ページのタイトル。

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../api_app/util/api_helpers.php';

// ユーザーがログインしていない場合、ログインページにリダイレクトする
// (login.php自体を除くすべてのページに適用)
if (basename($_SERVER['SCRIPT_NAME']) !== 'login.php' && !isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit();
}

$userName = $_SESSION['user_name'] ?? 'ゲスト';
$csrfToken = get_csrf_token();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
    <title><?php echo htmlspecialchars($pageTitle); ?> - StudySync</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --bs-primary: #0a2540; /* Original Blue */
            --bs-primary-rgb: 10, 37, 64;
            --bs-body-bg: #f0f2f5;
        }
        html, body {
            height: 100%;
        }
        .main-wrapper {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .nav-pills .nav-link.active, .nav-pills .show>.nav-link {
            background-color: var(--bs-primary);
        }
        .table-sortable th {
            cursor: pointer;
        }
        .table-sortable th:hover {
            background-color: #e9ecef;
        }
    </style>
    <script>
        window.ASSET_VERSION = <?php echo json_encode(APP_ASSET_VERSION); ?>;
        window.getCsrfToken = function() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        };
    </script>
</head>
<body data-current-user-id="<?php echo htmlspecialchars($_SESSION['user_id'] ?? ''); ?>" data-active-identicon="<?php echo htmlspecialchars($_SESSION['active_identicon'] ?? ''); ?>">
    <div class="main-wrapper">
        <!-- ヘッダー -->
        <header class="navbar navbar-expand-sm bg-primary-subtle shadow-sm">
            <div class="container-fluid">
                <a class="navbar-brand fw-bold" href="/entrance.php">StudySync</a>
                 <div class="d-flex ms-auto align-items-center">
                    <span class="me-3">ようこそ、<?php echo htmlspecialchars($userName); ?>さん！</span>
                    <canvas id="header-identicon" width="40" height="40" class="me-2"></canvas>
                    <a href="/login.php?action=logout" class="btn btn-sm btn-outline-primary">ログアウト</a>
                </div>
            </div>
        </header>

        <div class="container-fluid flex-grow-1">
            <div class="row h-100">
                <!-- サイドバーは親ページによってインクルードされます -->
