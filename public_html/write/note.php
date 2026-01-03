<?php
require_once __DIR__ . '/../parts/_header.php';

// --- モード判定 ---
$contentId = $_GET['id'] ?? null;
$editMode = ($contentId && filter_var($contentId, FILTER_VALIDATE_INT));
$pageTitle = $editMode ? 'ノートの編集' : 'ノートの新規作成';

// 表示設定オプションのためにセッションからユーザーの管理者ステータスを取得
$is_admin = $_SESSION['is_admin'] ?? false;
$initialVisibility = 'private'; // デフォルト値。編集モードではJSによって上書きされます

// ノートの最大サイズをJavaScriptに渡す
$maxNoteSizeBytes = MAX_NOTE_SIZE_BYTES;
?>

<!-- CSSとJSのヘッダー -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<!-- Quill.js CSS -->
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
<link rel="stylesheet" href="/css/note_editor.css?v=<?= APP_ASSET_VERSION ?>">

    <script>
        const MAX_NOTE_SIZE_BYTES = <?= $maxNoteSizeBytes ?>;
    </script>

                <!-- サイドバー -->
                <?php require_once __DIR__ . '/../parts/_sidebar.php'; ?>

                <!-- メインコンテンツ -->
                <main class="col-md-9 ms-sm-auto col-lg-10 p-4">
                    <h1 class="h4"><?= htmlspecialchars($pageTitle) ?></h1>
                    <hr class="mb-4">

                    <div id="limit-message-container"></div>

                    <div id="form-container">
                        <form action="/api/content/save_note" method="POST" data-is-admin="<?= $is_admin ? 'true' : 'false' ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($contentId ?? '') ?>">
                            <div class="card">
                                <div class="card-body">
                                    <!-- ノート詳細 -->
                                    <h5 class="mb-3">ノートの詳細</h5>
                                    <div class="mb-3">
                                        <label for="title" class="form-label">タイトル</label>
                                        <input type="text" class="form-control" id="title" name="title" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="description" class="form-label">説明</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label for="visibility" class="form-label">公開範囲</label>
                                        <select class="form-select" id="visibility" name="visibility">
                                            <option value="private">非公開 (自分のみ)</option>
                                            <option value="domain">ドメイン内公開</option>
                                            <?php if ($is_admin): ?>
                                            <option value="public">全体に公開</option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <?php 
                                            $label_text = '講義ID';
                                            require __DIR__ . '/../parts/_lecture_selector.php'; 
                                            ?>
                                        </div>
                                    </div>
                                    <hr>
                                    <h5 class="mt-4">ノート内容 <span id="note-size-display" class="ms-2 badge bg-info"></span></h5>
                                    <div id="editor" class="editor-container mb-3"></div>
                                </div>
                                <div class="card-footer text-end">
                                    <button type="submit" class="btn btn-primary"><?= $editMode ? '更新' : '作成' ?></button>
                                </div>
                            </div>
                        </form>
                    </div>
                </main>

    <!-- 変換用の隠しQuillインスタンス -->
    <div id="hidden-converter" style="display: none;"></div>

    <!-- スクリプト -->
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3.1.6/dist/purify.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<?php
// 新規作成モードでのみ作成許可をチェックするスクリプトを追加
if (!$editMode) {
    echo <<<HTML
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const formContainer = document.getElementById('form-container');
        const limitMessageContainer = document.getElementById('limit-message-container');
        
        fetch('/api/content/check_creation_allowance')
            .then(res => {
                if (!res.ok) {
                    throw new Error('作成上限の確認に失敗しました。');
                }
                return res.json();
            })
            .then(data => {
                if (data.allowed === false) {
                    if(formContainer) formContainer.style.display = 'none';
                    if(limitMessageContainer) {
                        limitMessageContainer.innerHTML = `
                            <div class="alert alert-warning">
                                <h4 class="alert-heading">作成上限到達</h4>
                                <p>コンテンツの作成上限（\${data.limit}件）に達しているため、新しいノートを作成できません。</p>
                                <hr>
                                <p class="mb-0">既存のコンテンツを削除すると、新しいコンテンツを作成できます。 <a href="/my_content.php">作成・管理ページへ</a></p>
                            </div>
                        `;
                    }
                }
            })
            .catch(error => {
                console.error('Failed to check creation allowance:', error);
                if(limitMessageContainer) {
                     limitMessageContainer.innerHTML = `<div class="alert alert-danger">\${error.message}</div>`;
                }
            });
    });
    </script>
HTML;
}

// メインエディタロジックをインクルード
echo '<script src="/js/note_editor.js?v=' . APP_ASSET_VERSION . '"></script>';
require_once __DIR__ . '/../parts/_footer.php';
?>

