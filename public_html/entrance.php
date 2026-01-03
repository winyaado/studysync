<?php
$pageTitle = 'ホーム';
require_once __DIR__ . '/parts/_header.php';
?>

                <!-- サイドバー -->
                <?php require_once 'parts/_sidebar.php'; ?>

                <!-- メインコンテンツ -->
                <main class="col-md-9 ms-sm-auto col-lg-10 p-4">
                    <div class="text-center mb-5">
                        <h1 class="display-5 fw-bold text-body-emphasis mb-3">メインメニュー</h1>
                        <p class="lead text-muted">閲覧するコンテンツの種類を選択してください</p>
                    </div>

                    <div class="row g-4 justify-content-center">
                        <!-- カード 1: ノート -->
                        <div class="col-md-6 col-lg-4">
                            <a href="/search.php?types[]=note" class="text-decoration-none">
                                <div class="card h-100 shadow-sm border-0">
                                    <div class="card-body text-center p-5">
                                        <div class="card-title mb-4">
                                            <i class="bi bi-journal-text"></i>
                                        </div>
                                        <h3 class="h4 fw-bold mb-3">ノート</h3>
                                        <p class="card-text text-muted mb-0">講義ノートを閲覧します。</p>
                                    </div>
                                </div>
                            </a>
                        </div>

                        <!-- カード 2: 問題集 -->
                        <div class="col-md-6 col-lg-4">
                            <a href="/search.php?types[]=problem" class="text-decoration-none">
                                <div class="card h-100 shadow-sm border-0">
                                    <div class="card-body text-center p-5">
                                        <div class="card-title mb-4">
                                            <i class="bi bi-patch-question"></i>
                                        </div>
                                        <h3 class="h4 fw-bold mb-3">問題集</h3>
                                        <p class="card-text text-muted mb-0">問題を解いて、知識をテストしましょう。</p>
                                    </div>
                                </div>
                            </a>
                        </div>

                        <!-- カード 3: 単語帳 -->
                        <div class="col-md-6 col-lg-4">
                             <a href="/search.php?types[]=flashcard" class="text-decoration-none">
                                <div class="card h-100 shadow-sm border-0">
                                    <div class="card-body text-center p-5">
                                        <div class="card-title mb-4">
                                            <i class="bi bi-book"></i>
                                        </div>
                                        <h3 class="h4 fw-bold mb-3">単語帳</h3>
                                        <p class="card-text text-muted mb-0">単語帳を利用して、語彙を増やしましょう。</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- お知らせ表示エリア -->
                    <div class="row justify-content-center mt-5">
                        <div class="col-lg-8">
                            <h2 class="h4 mb-3 text-body-emphasis">お知らせ</h2>
                            <div id="informations-display-area" class="list-group">
                                <p class="text-center text-muted">読み込み中...</p>
                            </div>
                        </div>
                    </div>
                </main>

    <!-- 変換用の隠しQuillインスタンス -->
    <div id="hidden-converter" style="display: none;"></div>

    <!-- Quill.js JS (Delta変換用) -->
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
    <!-- XSS対策のためのDOMPurify -->
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3.1.6/dist/purify.min.js"></script>

    <script src="/js/entrance_page.js?v=<?= APP_ASSET_VERSION ?>"></script>

<?php
require_once __DIR__ . '/parts/_footer.php';
?>
