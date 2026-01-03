<?php
// php/parts/stats/_card_reception_summary.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = $_SESSION['user_id'] ?? null;
$avg_rating = 0;
$total_ratings = 0;

if ($userId) {
    try {
        $pdo = get_pdo_connection();
        $stmt = $pdo->prepare("
            SELECT AVG(r.rating) as avg_rating, COUNT(r.id) as total_ratings 
            FROM ratings r 
            JOIN contents c ON r.rateable_id = c.id 
            WHERE c.user_id = ?;
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $avg_rating = $result['avg_rating'] ?? 0;
        $total_ratings = $result['total_ratings'] ?? 0;

    } catch (Exception $e) {
        error_log("Stats Card (Reception Summary) Error: " . $e->getMessage());
    }
}
?>

<div class="col-md-6 mb-4">
    <div class="card h-100">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="bi bi-star-half me-2"></i>受け取った評価</h5>
        </div>
        <div class="card-body">
            <p class="card-text">作成した全てのコンテンツが受け取った評価の概要です。</p>
            <div class="row text-center">
                <div class="col-6">
                    <div class="display-4"><?= htmlspecialchars(number_format($avg_rating, 1)) ?></div>
                    <div class="text-muted">平均評価</div>
                </div>
                <div class="col-6">
                    <div class="display-4"><?= htmlspecialchars($total_ratings) ?></div>
                    <div class="text-muted">総評価数</div>
                </div>
            </div>
        </div>
    </div>
</div>
