<?php
$pageTitle = 'BAN管理';
require_once __DIR__ . '/../parts/_header.php';

// --- 管理者チェック ---
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    echo '<main class="col-md-9 ms-sm-auto col-lg-10 p-4"><div class="alert alert-danger">このページにアクセスする権限がありません。</div></main>';
    require_once __DIR__ . '/../parts/_footer.php';
    exit();
}
?>

<link rel="stylesheet" href="../css/manage_bans.css?v=<?= APP_ASSET_VERSION ?>">

                <!-- サイドバー -->
                <?php require_once __DIR__ . '/../parts/_sidebar.php'; ?>

                <!-- メインコンテンツ -->
                <main class="col-md-9 ms-sm-auto col-lg-10 p-4">
                    <h1 class="h4"><?= htmlspecialchars($pageTitle) ?></h1>
                    <hr class="mb-4">

                    <div id="global-message-container"></div>

                    <!-- ユーザーBAN管理 -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            ユーザーBAN管理
                            <div class="input-group w-50">
                                <input type="text" id="user-search-input" class="form-control" placeholder="ユーザー名またはメールアドレスで検索...">
                                <button class="btn btn-outline-secondary" type="button" id="user-search-button"><i class="bi bi-search"></i> 検索</button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>名前</th>
                                        <th>メール</th>
                                        <th>テナント</th>
                                        <th>BAN状況</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody id="user-list-table">
                                    <!-- ユーザーはJavaScriptによってここに読み込まれます -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ドメインBAN管理 -->
                    <div class="card">
                        <div class="card-header">
                            ドメインBAN管理
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>ドメイン名</th>
                                        <th>ドメイン識別子</th>
                                        <th>BAN状況</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody id="tenant-list-table">
                                    <!-- テナントはJavaScriptによってここに読み込まれます -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                </main>

    <!-- このページ用の外部JavaScript -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script> <!-- 一部のBootstrapコンポーネントまたは将来の必要性のために必要 -->
    <script src="/js/manage_bans.js?v=<?= APP_ASSET_VERSION ?>"></script>

<?php
require_once __DIR__ . '/../parts/_footer.php';
?>
