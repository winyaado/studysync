<?php
$pageTitle = "単語帳閲覧";
require_once __DIR__ . '/../parts/_header.php';

// 実際のアプリケーションでは、$_GET['id']からのIDに基づいてDBから単語帳の詳細を取得します
$vocabTitle = "アカデミック英語 (サンプル)";
$vocabLectureId = "E45678";
$vocabList = [
    "Academic" => "学術的な、大学の",
    "Analyze" => "分析する",
    "Concept" => "概念",
    "Data" => "データ",
    "Hypothesis" => "仮説"
];
?>

                <!-- サイドバー -->
                <?php require_once __DIR__ . '/../parts/_sidebar.php'; ?>

                <!-- メインコンテンツ -->
                <main class="col-md-9 ms-sm-auto col-lg-10 p-4">
                     <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/search.php?type=vocabulary">単語帳検索</a></li>
                            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($vocabTitle); ?></li>
                        </ol>
                    </nav>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h1 class="h4"><?php echo htmlspecialchars($vocabTitle); ?></h1>
                        <span class="badge bg-info fs-6"><?php echo htmlspecialchars($vocabLectureId); ?></span>
                    </div>
                    
                    <hr>

                    <!-- 単語リスト -->
                    <div class="list-group">
                        <?php foreach ($vocabList as $term => $definition): ?>
                        <div class="list-group-item">
                            <div class="row">
                                <div class="col-md-4 fw-bold"><?php echo htmlspecialchars($term); ?></div>
                                <div class="col-md-8"><?php echo htmlspecialchars($definition); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </main>
<?php
require_once __DIR__ . '/../parts/_footer.php';
?>
