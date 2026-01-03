<?php
$pageTitle = '統計';
require_once __DIR__ . '/parts/_header.php';
?>

                <!-- サイドバー -->
                <?php require_once 'parts/_sidebar.php'; ?>

                <!-- メインコンテンツ -->
                <main class="col-md-9 ms-sm-auto col-lg-10 p-4">
                    <h1 class="h4">統計情報</h1>
                    <hr class="mb-4">
                    
                    <div class="row">
                        <?php
                        // 自己完結型の統計カードコンポーネントをインクルード
                        include __DIR__ . '/parts/stats/_card_content_summary.php';
                        include __DIR__ . '/parts/stats/_card_reception_summary.php';
                        include __DIR__ . '/parts/stats/_card_study_summary.php';
                        include __DIR__ . '/parts/stats/_card_flashcard_memory.php';
                        ?>
                    </div>

                </main>

<?php
require_once __DIR__ . '/parts/_footer.php';
?>
