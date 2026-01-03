<?php
$pageTitle = "ノート閲覧";
require_once __DIR__ . '/../parts/_header.php';

// GETパラメータからIDを取得
$contentId = $_GET['id'] ?? null;
if (!$contentId || !filter_var($contentId, FILTER_VALIDATE_INT)) {
    http_response_code(400);
    echo "<main class='col-md-9 ms-sm-auto col-lg-10 p-4'><div class='alert alert-danger'>無効なIDです。</div></main>";
    require_once __DIR__ . '/../parts/_footer.php';
    exit();
}
$details = null; 
?>
<!-- Quill.js CSS (レンダリングスタイル用) -->
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
<link rel="stylesheet" href="/css/problem_page.css?v=<?= APP_ASSET_VERSION ?>"> <!-- 評価の星用 -->
<style>
    /* 表示用のQuillエディタコンテンツのパディングを模倣 */
    #page-specific-content .ql-editor {
        padding: 12px 15px;
        min-height: 200px;
        box-sizing: border-box;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        background-color: #fff;
    }
    #page-specific-content .ql-snow {
        border: none;
    }
</style>

                <!-- サイドバー -->
                <?php require_once __DIR__ . '/../parts/_sidebar.php'; ?>

                <!-- メインコンテンツ: ヘッダーはサーバーサイドでインクルードされ、コンテンツはJSによって入力されます -->
                <main class="col-md-9 ms-sm-auto col-lg-10 p-4" id="main-content">
                    <?php require_once __DIR__ . '/../parts/content_header.php'; ?>
                    
                    <?php if ($details): ?>
                    <div id="page-specific-content">
                        <div class="text-center mt-5">
                            <div class="spinner-border" style="width: 3rem; height: 3rem;" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3 text-muted">ノートを読み込んでいます...</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </main>

    <!-- 変換用の隠しQuillインスタンス -->
    <div id="hidden-converter" style="display: none;"></div>

    <!-- スクリプト -->
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3.1.6/dist/purify.min.js"></script>
    <script src="/js/content_rating.js?v=<?= APP_ASSET_VERSION ?>"></script>

    <script>
        const details = <?= json_encode($details) ?>;

        document.addEventListener('DOMContentLoaded', async function() {
            if (!details) return; // ヘッダーが詳細の読み込みに失敗した場合は停止

            const contentId = details.id;
            const pageSpecificContent = document.getElementById('page-specific-content');
            const hiddenQuill = new Quill('#hidden-converter');

            try {
                const response = await fetch(`/api/content/get_note_for_edit?id=${contentId}`);
                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.error || 'ノート内容の取得に失敗しました。');
                }
                const data = await response.json();
                const { content } = data;

                const noteDelta = JSON.parse(content);
                hiddenQuill.setContents(noteDelta);
                const noteHtml = hiddenQuill.root.innerHTML;
                const sanitizedHtml = DOMPurify.sanitize(noteHtml);

                pageSpecificContent.innerHTML = `
                    <div class="ql-snow">
                        <div class="ql-editor">
                            ${sanitizedHtml}
                        </div>
                    </div>
                    <div class="mt-4 text-end">
                        ${details.can_edit ? `<a href="/write/note.php?id=${contentId}" class="btn btn-primary"><i class="bi bi-pencil me-2"></i>ノートを編集</a>` : ''}
                    </div>
                `;

                if (typeof initializeRating === 'function') {
                    initializeRating(details);
                }

            } catch (error) {
                pageSpecificContent.innerHTML = `<div class='alert alert-danger'>エラー: ${error.message}</div>`;
            }
        });
    </script>

<?php
require_once __DIR__ . '/../parts/_footer.php';
?>

