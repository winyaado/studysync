<?php
$pageTitle = "単語帳学習";
require_once __DIR__ . '/../parts/_header.php';

// GETパラメータからセットID（複数）またはID（単一）とフィルターを取得
$idsParam = $_GET['ids'] ?? null;     // コンテンツIDのカンマ区切りリスト
$idParam = $_GET['id'] ?? null;       // 単一のコンテンツID
$filterParam = $_GET['filter'] ?? '-1'; // -1 for all, 0 for needs review, etc.

// setsParamを検証 - カンマ区切りの整数リストであることを確認
$contentIds = [];
if ($idsParam) { // 複数選択の場合は'ids'を優先
    $ids = explode(',', $idsParam);
    foreach ($ids as $id) {
        if (filter_var(trim($id), FILTER_VALIDATE_INT)) {
            $contentIds[] = (int)$id;
        }
    }
} elseif ($idParam && filter_var($idParam, FILTER_VALIDATE_INT)) { // 単一選択の場合は'id'にフォールバック
    $contentIds[] = (int)$idParam;
}

if (empty($contentIds)) {
    http_response_code(400);
    echo "<main class='col-md-9 ms-sm-auto col-lg-10 p-4'><div class='alert alert-danger'>無効な単語帳が指定されました。</div></main>";
    require_once __DIR__ . '/../parts/_footer.php';
    exit();
}

// filterParamを検証
if (!in_array($filterParam, ['-1', '0', '1', '2'])) {
    $filterParam = '-1'; // 無効な場合はすべてにデフォルト設定
}

?>
<link rel="stylesheet" href="/css/flashcard_study.css?v=<?= APP_ASSET_VERSION ?>">

                <!-- サイドバー -->
                <?php require_once __DIR__ . '/../parts/_sidebar.php'; ?>

                <!-- メインコンテンツ: このコンテナはJSによって入力されます -->
                <main class="col-md-9 ms-sm-auto col-lg-10 p-4" id="main-content">
                     <!-- ローディングスピナーはJSによって置き換えられます -->
                    <div class="d-flex justify-content-center align-items-center" style="height: 100%;">
                        <div class="spinner-border" style="width: 3rem; height: 3rem;" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </main>

    <script>
        // JavaScriptに渡されるPHP変数
        const initialContentIds = <?= json_encode($contentIds) ?>;
        const initialFilterLevel = <?= json_encode($filterParam) ?>;
    </script>
    <script src="/js/flashcard_study.js?v=<?= APP_ASSET_VERSION ?>"></script>


<?php
require_once __DIR__ . '/../parts/_footer.php';
?>
