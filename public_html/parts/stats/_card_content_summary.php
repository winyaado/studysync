<?php
// php/parts/stats/_card_content_summary.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../api_app/util/api_helpers.php'; // get_pdo_connection()のため

$userId = $_SESSION['user_id'] ?? null;
$stats = [
    'Note' => 0,
    'ProblemSet' => 0,
    'FlashCard' => 0,
];
$total_contents = 0;

if ($userId) {
    try {
        $pdo = get_pdo_connection();
        $stmt = $pdo->prepare("
            SELECT contentable_type, COUNT(*) as count 
            FROM contents 
            WHERE user_id = ? AND deleted_at IS NULL
            GROUP BY contentable_type;
        ");
        $stmt->execute([$userId]);
        $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $stats['Note'] = $results['Note'] ?? 0;
        $stats['ProblemSet'] = $results['ProblemSet'] ?? 0;
        $stats['FlashCard'] = $results['FlashCard'] ?? 0;
        $total_contents = array_sum($stats);

    } catch (Exception $e) {
        // エラーの場合、カードには0が表示されます。
        error_log("Stats Card (Content Summary) Error: " . $e->getMessage());
    }
}
?>

<div class="col-md-6 mb-4">
    <div class="card h-100">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="bi bi-journal-richtext me-2"></i>作成コンテンツ概要</h5>
        </div>
        <div class="card-body">
            <p class="card-text">これまでに作成したコンテンツの数です。</p>
            <div class="display-4 text-center"><?= htmlspecialchars($total_contents) ?></div>
            <ul class="list-group list-group-flush mt-3">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    ノート
                    <span class="badge bg-primary rounded-pill"><?= htmlspecialchars($stats['Note']) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    問題集
                    <span class="badge bg-success rounded-pill"><?= htmlspecialchars($stats['ProblemSet']) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    単語帳
                    <span class="badge bg-info rounded-pill"><?= htmlspecialchars($stats['FlashCard']) ?></span>
                </li>
            </ul>
        </div>
    </div>
</div>
