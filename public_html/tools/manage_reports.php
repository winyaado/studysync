<?php
$pageTitle = '通報管理';
require_once __DIR__ . '/../parts/_header.php';

// 管理者権限チェック
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    echo "<main class='col-md-9 ms-sm-auto col-lg-10 p-4'><div class='alert alert-danger'>管理者権限がありません。</div></main>";
    require_once __DIR__ . '/../parts/_footer.php';
    exit();
}
?>

                <!-- サイドバー -->
                <?php require_once __DIR__ . '/../parts/_sidebar.php'; ?>

                <!-- メインコンテンツ -->
                <main class="col-md-9 ms-sm-auto col-lg-10 p-4">
                    <h1 class="h4">通報管理</h1>
                    <hr class="mb-4">

                    <div id="reports-container">
                        <div id="loading-state" class="text-center mt-5">
                            <div class="spinner-border" style="width: 3rem; height: 3rem;" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3 text-muted">通報を読み込んでいます...</p>
                        </div>

                        <div id="reports-table-wrapper" style="display: none;">
                            <div class="mb-3">
                                <label for="filterStatus" class="form-label">ステータスで絞り込み:</label>
                                <select class="form-select" id="filterStatus">
                                    <option value="">全て</option>
                                    <option value="open">未対応</option>
                                    <option value="in_progress">対応中</option>
                                    <option value="closed">対応済み</option>
                                </select>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover table-bordered bg-white">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>ステータス</th>
                                            <th>カテゴリ</th>
                                            <th>対象コンテンツ</th>
                                            <th>通報者</th>
                                            <th>通報日時</th>
                                            <th>アクション</th>
                                        </tr>
                                    </thead>
                                    <tbody id="reports-tbody">
                                        <!-- 通報はJSによってここに読み込まれます -->
                                    </tbody>
                                </table>
                            </div>
                            <div id="no-reports-message" class="alert alert-info" style="display: none;">通報はありません。</div>
                        </div>
                    </div>
                </main>

<script src="/js/manage_reports.js?v=<?= APP_ASSET_VERSION ?>"></script>

<?php
require_once __DIR__ . '/../parts/_footer.php';
?>
