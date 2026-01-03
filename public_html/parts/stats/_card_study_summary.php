<?php
// php/parts/stats/_card_study_summary.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = $_SESSION['user_id'] ?? null;
$total_attempts = 0;
$avg_score_percentage = 0;

if ($userId) {
    try {
        $pdo = get_pdo_connection();
        $stmt = $pdo->prepare("
            SELECT COUNT(id) as total_attempts, AVG(score / total_questions) * 100 as avg_score_percentage 
            FROM exam_attempts 
            WHERE user_id = ? AND total_questions > 0;
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $total_attempts = $result['total_attempts'] ?? 0;
        $avg_score_percentage = $result['avg_score_percentage'] ?? 0;

    } catch (Exception $e) {
        error_log("Stats Card (Study Summary) Error: " . $e->getMessage());
    }
}
?>

<div class="col-md-6 mb-4">
    <div class="card h-100">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="bi bi-card-checklist me-2"></i>問題集の学習概要</h5>
        </div>
        <div class="card-body">
            <p class="card-text">これまでに解答した問題集の成績概要です。</p>
            <div class="row text-center">
                <div class="col-6">
                    <div class="display-4"><?= htmlspecialchars($total_attempts) ?></div>
                    <div class="text-muted">総解答回数</div>
                </div>
                <div class="col-6">
                    <div class="display-4"><?= htmlspecialchars(number_format($avg_score_percentage, 1)) ?><small>%</small></div>
                    <div class="text-muted">平均正答率</div>
                </div>
            </div>
        </div>
    </div>
</div>
