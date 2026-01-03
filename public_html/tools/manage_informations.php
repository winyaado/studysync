<?php
$pageTitle = 'インフォメーション管理';
require_once __DIR__ . '/../parts/_header.php';

// --- 管理者チェック ---
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    echo '<main class="col-md-9 ms-sm-auto col-lg-10 p-4"><div class="alert alert-danger">このページにアクセスする権限がありません。</div></main>';
    require_once __DIR__ . '/../parts/_footer.php';
    exit();
}
?>

<!-- Quill.js CSS -->
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
<link rel="stylesheet" href="/css/manage_informations.css?v=<?= APP_ASSET_VERSION ?>">

                <!-- サイドバー -->
                <?php require_once __DIR__ . '/../parts/_sidebar.php'; ?>

                <!-- メインコンテンツ -->
                <main class="col-md-9 ms-sm-auto col-lg-10 p-4">
                    <h1 class="h4"><?= htmlspecialchars($pageTitle) ?></h1>
                    <hr class="mb-4">

                    <div id="global-message-container"></div>

                    <!-- インフォメーション追加・編集フォーム -->
                    <div class="card mb-4">
                        <div class="card-header">
                            インフォメーションの追加・編集
                        </div>
                        <div class="card-body">
                            <form id="information-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                                <input type="hidden" name="id" id="info-id">
                                <div class="mb-3">
                                    <label for="info-title" class="form-label">タイトル</label>
                                    <input type="text" class="form-control" id="info-title" name="title" required>
                                </div>
                                <div class="mb-3">
                                    <label for="info-content" class="form-label">内容</label>
                                    <div id="info-editor" class="editor-container"></div>
                                    <input type="hidden" name="content" id="info-content"> <!-- コンテンツ用の隠し入力フィールド -->
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label for="info-category" class="form-label">カテゴリ</label>
                                        <select class="form-select" id="info-category" name="category" required>
                                            <option value="info">情報</option>
                                            <option value="warning">注意</option>
                                            <option value="danger">警告</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="info-display-from" class="form-label">表示開始日時</label>
                                        <input type="datetime-local" class="form-control" id="info-display-from" name="display_from" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="info-display-to" class="form-label">表示終了日時</label>
                                        <input type="datetime-local" class="form-control" id="info-display-to" name="display_to" required>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary" id="save-info-btn">保存</button>
                                <button type="button" class="btn btn-secondary" id="cancel-edit-btn">キャンセル</button>
                            </form>
                        </div>
                    </div>

                    <!-- インフォメーションリスト -->
                    <div class="card">
                        <div class="card-header">
                            既存インフォメーション一覧
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>タイトル</th>
                                        <th>カテゴリ</th>
                                        <th>表示開始</th>
                                        <th>表示終了</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody id="informations-list-table">
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

    <!-- 変換用の隠しQuillインスタンス -->
    <div id="hidden-converter" style="display: none;"></div>

    <!-- スクリプト -->
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3.1.6/dist/purify.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="/js/manage_informations.js?v=<?= APP_ASSET_VERSION ?>"></script>

<?php
require_once __DIR__ . '/../parts/_footer.php';
?>
