<?php
$pageTitle = 'マイライブラリ';
require_once __DIR__ . '/parts/_header.php';

// $contentId変数は、main contentに含まれている場合、content_header.phpから利用可能です。
// このページでは、単一のコンテンツは取得しません。
?>

                <!-- サイドバー -->
                <?php require_once 'parts/_sidebar.php'; ?>

                <!-- メインコンテンツ -->
                <main class="col-md-9 ms-sm-auto col-lg-10 p-4" id="main-content">
                    <h1 class="h4">マイライブラリ</h1>
                    <hr class="mb-4">

                    <div id="loading-state" class="text-center mt-5">
                        <div class="spinner-border" style="width: 3rem; height: 3rem;" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted">コンテンツを読み込んでいます...</p>
                    </div>

                    <div id="library-content">
                        <!-- コンテンツはJSによってここに描画されます -->
                    </div>

                    <!-- 学習モード設定モーダル -->
                    <div class="modal fade" id="studyConfigModal" tabindex="-1" aria-labelledby="studyConfigModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="studyConfigModalLabel">学習設定</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="mb-3">選択されたフラッシュカードセットの学習方法を設定してください。</p>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="studyFilter" id="studyFilterAll" value="-1" checked>
                                        <label class="form-check-label" for="studyFilterAll">
                                            すべての単語を学習
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="studyFilter" id="studyFilterNeedsReview" value="0">
                                        <label class="form-check-label" for="studyFilterNeedsReview">
                                            「要復習」の単語のみ学習
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="studyFilter" id="studyFilterSoSo" value="1">
                                        <label class="form-check-label" for="studyFilterSoSo">
                                            「要復習」と「まあまあ」の単語を学習
                                        </label>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                                    <button type="button" class="btn btn-primary" id="startStudyButton">学習開始</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>

<script src="/js/my_library_page.js?v=<?= APP_ASSET_VERSION ?>"></script>

<?php
require_once __DIR__ . '/parts/_footer.php';
?>
