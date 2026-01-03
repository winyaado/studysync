<?php
// アクティブ状態を設定するために現在のページを決定する
$currentPage = basename($_SERVER['SCRIPT_NAME']);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<nav class="col-md-3 col-lg-2 bg-white p-3 d-flex flex-column">
    <ul class="nav nav-pills flex-column mb-auto flex-grow-1">
        <li class="nav-item">
            <a href="/entrance.php" class="nav-link <?php echo ($currentPage === 'entrance.php') ? 'active' : 'text-dark'; ?>">
                <i class="bi bi-house-door me-2"></i>ホーム
            </a>
        </li>
        <li class="nav-item">
            <a href="/search.php" class="nav-link <?php echo ($currentPage === 'search.php') ? 'active' : 'text-dark'; ?>">
                <i class="bi bi-search me-2"></i>コンテンツ検索
            </a>
        </li>
        <li class="nav-item">
            <a href="/my_library.php" class="nav-link <?php echo ($currentPage === 'my_library.php') ? 'active' : 'text-dark'; ?>">
                <i class="bi bi-collection me-2"></i>マイライブラリ
            </a>
        </li>
        <li class="nav-item">
            <a href="/profile.php" class="nav-link <?php echo ($currentPage === 'profile.php') ? 'active' : 'text-dark'; ?>">
                <i class="bi bi-person me-2"></i>マイプロフィール
            </a>
        </li>
        
        <hr>

        <li class="nav-item">
            <a href="/my_content.php" class="nav-link <?php echo ($currentPage === 'my_content.php') ? 'active' : 'text-dark'; ?>">
                <i class="bi bi-pencil-square me-2"></i>作成・管理
            </a>
        </li>
        <li class="nav-item">
            <a href="/stats.php" class="nav-link <?php echo ($currentPage === 'stats.php') ? 'active' : 'text-dark'; ?>">
                <i class="bi bi-graph-up me-2"></i>統計
            </a>
        </li>
        <li class="nav-item">
            <a href="/settings.php" class="nav-link <?php echo ($currentPage === 'settings.php') ? 'active' : 'text-dark'; ?>">
                <i class="bi bi-gear me-2"></i>設定
            </a>
        </li>
    </ul>
    
    <hr class="mt-auto mb-2">
    <div class="text-center text-muted small">
        <a href="/legal/terms_of_service.php" class="text-muted me-2">利用規約</a>
        <a href="/legal/privacy_policy.php" class="text-muted me-2">プライバシーポリシー</a><br>
        <a href="/legal/creator_policy.php" class="text-muted">クリエイターポリシー</a>
    </div>
</nav>
