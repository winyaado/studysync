<?php
$pageTitle = '作成・管理';
require_once __DIR__ . '/parts/_header.php';
?>

                <!-- サイドバー -->
                <?php require_once 'parts/_sidebar.php'; ?>

                <!-- メインコンテンツ -->
                <main class="col-md-9 ms-sm-auto col-lg-10 p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <h1 class="h4">作成・管理</h1>
                    </div>
                    <hr class="mb-4">
                    
                    <!-- 新規作成セクション -->
                    <div class="card mb-4">
                        <div class="card-header">
                            新規作成
                        </div>
                        <div class="card-body text-center">
                            <p>新しいコンテンツを作成します。</p>
                            <div class="btn-group" role="group">
                                <a href="/write/note.php" class="btn btn-outline-primary"><i class="bi bi-patch-plus me-1"></i> ノート作成</a>
                                <a href="/write/problem_set.php" class="btn btn-outline-primary"><i class="bi bi-patch-plus me-1"></i> 問題集作成</a>
                                <a href="/write/flashcard.php" class="btn btn-outline-primary"><i class="bi bi-card-text me-1"></i> 単語帳作成</a>
                            </div>
                        </div>
                    </div>

                    <!-- 作成済みコンテンツリスト -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>作成済みコンテンツ</span>
                            <span id="content-usage-display" class="badge bg-secondary"></span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>タイプ</th>
                                        <th>タイトル</th>
                                        <th>講義名</th>
                                        <th>ステータス</th>
                                        <th>公開範囲</th>
                                        <th>最終更新日</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody id="my-contents-list">
                                    <!-- ここにJSでコンテンツが挿入される -->
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">
                                            <div class="spinner-border spinner-border-sm" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                            読み込み中...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </main>

<script src="/js/my_content_page.js?v=<?= APP_ASSET_VERSION ?>"></script>

<?php
require_once __DIR__ . '/parts/_footer.php';
?>
