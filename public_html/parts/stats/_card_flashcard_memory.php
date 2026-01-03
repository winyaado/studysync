<?php
// php/parts/stats/_card_flashcard_memory.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = $_SESSION['user_id'] ?? null;
$memory_stats = [
    '2' => 0, // 完璧
    '1' => 0, // まあまあ
    '0' => 0, // 要復習
];
$total_learned_words = 0;

if ($userId) {
    try {
        $pdo = get_pdo_connection();
        $stmt = $pdo->prepare("
            SELECT memory_level, COUNT(*) as count 
            FROM user_flashcard_word_memory 
            WHERE user_id = ? 
            GROUP BY memory_level;
        ");
        $stmt->execute([$userId]);
        $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $memory_stats['2'] = $results[2] ?? 0;
        $memory_stats['1'] = $results[1] ?? 0;
        $memory_stats['0'] = $results[0] ?? 0;
        $total_learned_words = array_sum($memory_stats);

    } catch (Exception $e) {
        error_log("Stats Card (Flashcard Memory) Error: " . $e->getMessage());
    }
}
?>

<div class="col-md-6 mb-4">
    <div class="card h-100">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="bi bi-person-check-fill me-2"></i>単語帳の習熟度</h5>
        </div>
        <div class="card-body">
            <p class="card-text">これまでに学習した全単語の習熟度別の単語数です。</p>
            <div class="display-4 text-center"><?= htmlspecialchars($total_learned_words) ?></div>
            <ul class="list-group list-group-flush mt-3">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    完璧
                    <span class="badge bg-success rounded-pill"><?= htmlspecialchars($memory_stats[2]) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    まあまあ
                    <span class="badge bg-warning text-dark rounded-pill"><?= htmlspecialchars($memory_stats[1]) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    要復習
                    <span class="badge bg-danger rounded-pill"><?= htmlspecialchars($memory_stats[0]) ?></span>
                </li>
            </ul>
        </div>
    </div>
</div>
