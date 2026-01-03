<?php
$pageTitle = "試験中"; // 仮タイトル
require_once __DIR__ . '/../parts/_header.php';

// GETパラメータからIDを取得
$contentId = $_GET['id'] ?? null;
if (!$contentId || !filter_var($contentId, FILTER_VALIDATE_INT)) {
    http_response_code(400);
    echo "<main class='col-md-9 ms-sm-auto col-lg-10 p-4'><div class='alert alert-danger'>無効なIDです。</div></main>";
    require_once __DIR__ . '/../parts/_footer.php';
    exit();
}
?>

                <!-- サイドバー -->
                <?php require_once __DIR__ . '/../parts/_sidebar.php'; ?>

                <!-- メインコンテンツ: 初期状態では読み込み中を表示 -->
                <main class="col-md-9 ms-sm-auto col-lg-10 p-4" id="main-content">
                    <div class="text-center mt-5">
                        <div class="spinner-border" style="width: 3rem; height: 3rem;" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted">試験を準備しています...</p>
                    </div>
                </main>

<script>
    const contentId = <?= json_encode($contentId) ?>;
</script>
<script src="/js/solve_page.js?v=<?= APP_ASSET_VERSION ?>"></script>

<?php
// exam.js はJSで動的に読み込むので、ここでは不要
require_once __DIR__ . '/../parts/_footer.php';
?>
