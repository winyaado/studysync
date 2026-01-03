<?php
$pageTitle = "問題集閲覧"; // これはcontent_header.phpによって更新されます
require_once __DIR__ . '/../parts/_header.php';

// GETパラメータからIDを取得
$contentId = $_GET['id'] ?? null;
if (!$contentId || !filter_var($contentId, FILTER_VALIDATE_INT)) {
    http_response_code(400);
    echo "<main class='col-md-9 ms-sm-auto col-lg-10 p-4'><div class='alert alert-danger'>無効なIDです。</div></main>";
    require_once __DIR__ . '/../parts/_footer.php';
    exit();
}
$details = null; // インクルードのために$detailsが定義されていることを確認
?>
<link rel="stylesheet" href="/css/problem_page.css?v=<?= APP_ASSET_VERSION ?>">

                <!-- サイドバー -->
                <?php require_once __DIR__ . '/../parts/_sidebar.php'; ?>

                <!-- メインコンテンツ: ヘッダーはサーバーサイドでインクルードされ、コンテンツはJSによって入力されます -->
                <main class="col-md-9 ms-sm-auto col-lg-10 p-4" id="main-content">
                    <?php require_once __DIR__ . '/../parts/content_header.php'; ?>
                    
                    <?php if ($details): // ヘッダーが成功した場合のみ残りをレンダリング ?>
                    <div id="page-specific-content">
                        <!-- 問題集のコンテンツはJSによってここに描画されます -->
                        <div class="text-center mt-5">
                            <div class="spinner-border" style="width: 3rem; height: 3rem;" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3 text-muted">問題集を読み込んでいます...</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </main>

<script>
    // PHP変数を外部JSファイルに渡す
    const contentId = <?= json_encode($contentId) ?>;
    const details = <?= json_encode($details) ?>;
</script>
<script src="/js/content_rating.js?v=<?= APP_ASSET_VERSION ?>"></script> <!-- 評価コンポーネント用 -->
<script src="/js/problem_page.js?v=<?= APP_ASSET_VERSION ?>"></script>

<?php
require_once __DIR__ . '/../parts/_footer.php';
?>
