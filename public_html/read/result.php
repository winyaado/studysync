<?php
$pageTitle = "試験結果"; // 仮タイトル
require_once __DIR__ . '/../parts/_header.php';

// GETパラメータから attempt_id を取得
$attemptId = $_GET['attempt_id'] ?? null;
if (!$attemptId || !filter_var($attemptId, FILTER_VALIDATE_INT)) {
    http_response_code(400);
    echo "<main class='col-md-9 ms-sm-auto col-lg-10 p-4'><div class='alert alert-danger'>無効な結果IDです。</div></main>";
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
                        <p class="mt-3 text-muted">結果を読み込んでいます...</p>
                    </div>
                </main>

<script>
    const attemptId = <?= json_encode($attemptId) ?>;
</script>
<script src="/js/result_page.js?v=<?= APP_ASSET_VERSION ?>"></script>

<?php
require_once __DIR__ . '/../parts/_footer.php';
?>
