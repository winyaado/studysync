<?php
$pageTitle = 'コンテンツ検索';
require_once __DIR__ . '/parts/_header.php';

// --- コントロールのサーバーサイドレンダリングのためにURLから初期検索パラメータを取得 ---
$searchQuery = $_GET['q'] ?? '';
$selectedTypes = $_GET['types'] ?? [];
if (is_string($selectedTypes)) {
    $selectedTypes = !empty($selectedTypes) ? explode(',', $selectedTypes) : [];
}
$selectedLectureId = $_GET['lecture_id'] ?? '';
$followOnly = ($_GET['follow_only'] ?? '') === '1';
$selectedLectureName = $_GET['lecture_name'] ?? ''; // 表示のために講義名を渡す
$sort_by = $_GET['sort'] ?? 'updated_at';
$order = $_GET['order'] ?? 'desc';
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;

// ソートヘッダーを表示するためのヘルパー関数（これは初期静的ページ構造の一部であるためPHPに残すことができる）
function print_sort_header($label, $column_name, $current_sort, $current_order) {
    $icon = '<i class="bi bi-arrow-down-up small text-muted"></i>';
    $next_order = 'desc';
    if ($column_name === $current_sort) {
        if (strtolower($current_order) === 'desc') {
            $icon = '<i class="bi bi-sort-down"></i>';
            $next_order = 'asc';
        } else {
            $icon = '<i class="bi bi-sort-up"></i>';
            $next_order = 'desc';
        }
    }
    
    // これらのリンクはJavaScriptによって処理されますが、スクリプトで使用するためのデータ属性を設定できます
    echo '<th><a href="#" data-sort="' . $column_name . '" data-order="' . $next_order . '" class="text-dark text-decoration-none sort-link">' . $label . ' ' . $icon . '</a></th>';
}

$typeDisplayMap = ['ProblemSet' => ['text' => '問題集', 'bg' => 'bg-success'],'Note' => ['text' => 'ノート', 'bg' => 'bg-primary'],'FlashCard' => ['text' => '単語帳', 'bg' => 'bg-info']];
$typeLinkMap = ['ProblemSet' => '/read/problem.php?id=','Note' => '/read/note.php?id=','FlashCard' => '/read/flashcard.php?id=',];

?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<link rel="stylesheet" href="/css/search.css?v=<?= APP_ASSET_VERSION ?>">

                <!-- サイドバー -->
                <?php require_once 'parts/_sidebar.php'; ?>

                <!-- メインコンテンツ -->
                <main class="col-md-9 ms-sm-auto col-lg-10 p-4">
                    <h1 class="h4">コンテンツ検索</h1>
                    <hr class="mb-4">
                    <!-- 検索コントロール -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form id="search-form" action="search.php" method="get">
                                <div class="row g-3 align-items-end">
                                    <div class="col-xl-4">
                                        <label for="searchQuery" class="form-label">キーワード</label>
                                        <input type="text" class="form-control" id="searchQuery" name="q" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="タイトル, 説明, 作成者...">
                                    </div>
                                    <div class="col-xl-6">
                                        <?php require __DIR__ . '/parts/_lecture_selector.php'; ?>
                                    </div>
                                    <div class="col-xl-2">
                                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> 検索</button>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <label class="form-label">コンテンツタイプ:</label>
                                    <div class="d-flex">
                                        <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" id="type_note" name="types[]" value="note" <?= in_array('note', $selectedTypes) ? 'checked' : '' ?>><label class="form-check-label" for="type_note">ノート</label></div>
                                        <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" id="type_problem" name="types[]" value="problem" <?= in_array('problem', $selectedTypes) ? 'checked' : '' ?>><label class="form-check-label" for="type_problem">問題集</label></div>
                                        <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" id="type_flashcard" name="types[]" value="flashcard" <?= in_array('flashcard', $selectedTypes) ? 'checked' : '' ?>><label class="form-check-label" for="type_flashcard">単語帳</label></div>
                                    </div>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="followOnly" name="follow_only" value="1" <?= $followOnly ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="followOnly">フォロー中のみ</label>
                                    </div>
                                </div>
                                <!-- ソート状態のための隠しフィールド -->
                                <input type="hidden" name="sort" id="sort_input" value="<?= htmlspecialchars($sort_by) ?>">
                                <input type="hidden" name="order" id="order_input" value="<?= htmlspecialchars($order) ?>">
                            </form>
                        </div>
                    </div>

                    <div id="results-container">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <p id="results-count-text" class="mb-0 text-muted">検索中...</p>
                        </div>

                        <!-- 検索結果テーブル -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-bordered bg-white">
                                <thead class="table-light">
                                    <tr>
                                        <th>ライブラリ</th>
                                        <th>タイプ</th>
                                        <th>タイトル</th>
                                        <th>講義名</th>
                                        <th>作成者</th>
                                        <?php print_sort_header('評価', 'rating', $sort_by, $order); ?>
                                        <?php print_sort_header('更新日', 'updated_at', $sort_by, $order); ?>
                                    </tr>
                                </thead>
                                <tbody id="results-tbody">
                                    <tr><td colspan="7" class="text-center text-muted"><div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- ページネーション -->
                        <nav aria-label="Page navigation">
                            <ul id="pagination-ul" class="pagination justify-content-center">
                            </ul>
                        </nav>
                    </div>

                </main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="/js/lecture_selector.js?v=<?= APP_ASSET_VERSION ?>"></script>
<script>
    // 外部JSファイルにPHP変数を渡す
    const initialSearchData = {
        typeDisplayMap: <?= json_encode($typeDisplayMap) ?>,
        typeLinkMap: <?= json_encode($typeLinkMap) ?>,
        currentState: {
            q: '<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>',
            types: <?= json_encode($selectedTypes) ?>,
            lecture_id: '<?= htmlspecialchars($selectedLectureId, ENT_QUOTES, 'UTF-8') ?>',
            follow_only: '<?= $followOnly ? '1' : '' ?>',
            // lecture_nameは現在search.jsによって処理されます
            sort: '<?= htmlspecialchars($sort_by, ENT_QUOTES, 'UTF-8') ?>',
            order: '<?= htmlspecialchars($order, ENT_QUOTES, 'UTF-8') ?>',
            page: <?= $current_page ?>
        }
    };
</script>
<script src="/js/search.js?v=<?= APP_ASSET_VERSION ?>"></script>

<?php require_once __DIR__ . '/parts/_footer.php'; ?>
