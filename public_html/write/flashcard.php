<?php
require_once __DIR__ . '/../parts/_header.php';

// --- モード判定 ---
$contentId = $_GET['id'] ?? null;
$editMode = ($contentId && filter_var($contentId, FILTER_VALIDATE_INT));
$pageTitle = $editMode ? '単語帳の編集' : '単語帳の新規作成';

// 表示設定オプションのためにセッションからユーザーの管理者ステータスを取得
$is_admin = $_SESSION['is_admin'] ?? false;
$initialVisibility = 'private'; // デフォルト値。編集モードではJSによって上書きされます
?>

<!-- CSSとJSのヘッダー -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

                <!-- サイドバー -->
                <?php require_once __DIR__ . '/../parts/_sidebar.php'; ?>

                <!-- メインコンテンツ -->
                <main class="col-md-9 ms-sm-auto col-lg-10 p-4">
                    <h1 class="h4"><?= htmlspecialchars($pageTitle) ?></h1>
                    <hr class="mb-4">

                    <div id="limit-message-container"></div>

                    <div id="form-container">
                        <?php if (!$editMode): ?>
                        <!-- CSV Import Section (新規作成時のみ表示) -->
                        <div class="card mb-4">
                            <div class="card-header">CSVからインポート</div>
                            <div class="card-body">
                                <p class="card-text text-muted">指定のフォーマットのCSVファイルをアップロードして、単語を一括で作成できます。</p>
                                <div class="mb-3">
                                    <label for="csv-file-input" class="form-label">CSVファイルを選択</label>
                                    <input class="form-control" type="file" id="csv-file-input" accept=".csv">
                                </div>
                                <div id="csv-import-success" class="alert alert-success" style="display: none;" role="alert"></div>
                                <a href="sample_flashcards.csv" download="sample_flashcards.csv" class="btn btn-sm btn-secondary"><i class="bi bi-download me-1"></i>サンプルCSVをダウンロード</a>
                            </div>
                        </div>
                        <?php endif; ?>

                        <form action="/api/content/save_flashcard" method="POST" data-is-admin="<?= $is_admin ? 'true' : 'false' ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($contentId ?? '') ?>">
                            <div class="card">
                                <div class="card-body">
                                    <!-- 単語帳詳細 -->
                                    <h5 class="mb-3">単語帳の詳細</h5>
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
                                    <h5 class="mt-4">カードの追加・編集</h5>
                                    <div id="words-container"></div>
                                    <button type="button" id="add-word-btn" class="btn btn-secondary mt-3"><i class="bi bi-plus-lg"></i> 単語を追加</button>
                                </div>
                                <div class="card-footer text-end">
                                    <button type="submit" class="btn btn-primary"><?= $editMode ? '更新' : '作成' ?></button>
                                </div>
                            </div>
                        </form>
                    </div>
                </main>

<!-- 単一の単語用テンプレート -->
<template id="word-template">
    <div class="card word-card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="word-title">単語</span>
            <button type="button" class="btn-close remove-word-btn" aria-label="Close"></button>
        </div>
        <div class="card-body">
            <input type="hidden" class="word-id" value=""> <!-- 編集モードでの既存の単語用 -->
            <div class="mb-3">
                <label class="form-label">単語 / 表面</label>
                <input type="text" class="form-control word-text" required>
            </div>
            <div class="mb-3">
                <label class="form-label">意味 / 裏面</label>
                <textarea class="form-control definition-text" rows="2" required></textarea>
            </div>
        </div>
    </div>
</template>

<!-- スクリプト -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="/js/lecture_selector.js?v=<?= APP_ASSET_VERSION ?>"></script>
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
                                <p>コンテンツの作成上限（\${data.limit}件）に達しているため、新しい単語帳を作成できません。</p>
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

echo '<script src="/js/flashcard_editor.js?v=' . APP_ASSET_VERSION . '"></script>';
require_once __DIR__ . '/../parts/_footer.php';
?>

