<?php
$pageTitle = "単語帳閲覧";
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
<link rel="stylesheet" href="/css/problem_page.css?v=<?= APP_ASSET_VERSION ?>"> <!-- クリッカブルな行と評価スタイルにproblem_page.cssを再利用 -->

                <!-- サイドバー -->
                <?php require_once __DIR__ . '/../parts/_sidebar.php'; ?>

                <!-- メインコンテンツ: ヘッダーはサーバーサイドでインクルードされ、コンテンツはJSによって入力されます -->
                <main class="col-md-9 ms-sm-auto col-lg-10 p-4" id="main-content">
                    <?php require_once __DIR__ . '/../parts/content_header.php'; ?>
                    
                    <?php if ($details): ?>
                    <div id="page-specific-content">
                        <!-- フラッシュカードの単語はJSによってここに描画されます -->
                        <div class="text-center mt-5">
                            <div class="spinner-border" style="width: 3rem; height: 3rem;" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3 text-muted">単語リストを読み込んでいます...</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </main>

    <script src="/js/content_rating.js?v=<?= APP_ASSET_VERSION ?>"></script>

    <script>
        const details = <?= json_encode($details) ?>;

        document.addEventListener('DOMContentLoaded', async function() {
            if (!details) return;

            const contentId = details.id;
            const pageSpecificContent = document.getElementById('page-specific-content');
            
            function escapeHTML(str) {
                if (typeof str !== 'string' && typeof str !== 'number') return '';
                const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
                return String(str).replace(/[&<>"']/g, m => map[m] || '');
            }

            try {
                // ヘッダーはすでに詳細を取得していますが、'words'配列が必要です
                const response = await fetch(`/api/content/get_flashcard_details?id=${contentId}`);
                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.error || '単語帳の単語リスト取得に失敗しました。');
                }
                const data = await response.json();
                const { words } = data;

                // --- ページ固有のコンテンツを生成 ---
                const wordsTable = words.length > 0 ? words.map((word, index) => `
                    <tr>
                        <td>${index + 1}</td>
                        <td>${escapeHTML(word.word)}</td>
                        <td>${escapeHTML(word.definition)}</td>
                    </tr>
                `).join('') : `
                    <tr>
                        <td colspan="3" class="text-center text-muted">単語が登録されていません。</td>
                    </tr>
                `;

                pageSpecificContent.innerHTML = `
                    <div class="card mt-4">
                        <div class="card-header fw-bold">単語リスト</div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>単語（表面）</th>
                                        <th>意味（裏面）</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${wordsTable}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="mt-4 text-end">
                        ${details.can_edit ? `<a href="/write/flashcard.php?id=${contentId}" class="btn btn-primary"><i class="bi bi-pencil me-2"></i>単語帳を編集</a>` : ''}
                        <a href="/read/flashcard_study.php?id=${contentId}" class="btn btn-success ms-2"><i class="bi bi-play-circle me-2"></i>学習を開始</a>
                    </div>
                `;

                // 評価コンポーネントを初期化
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
