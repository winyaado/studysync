<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api_app/util/api_helpers.php'; // get_pdo_connection() のため

// --- ユーザーIDの取得と存在チェック ---
$targetUserId = $_GET['user_id'] ?? null;
if (!$targetUserId) {
    // 自分のプロフィールページにリダイレクト、またはログインページへ
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['user_id'])) {
        header('Location: profile.php?user_id=' . $_SESSION['user_id']);
    } else {
        header('Location: login.php');
    }
    exit;
}

$targetUserId = (int)$targetUserId;
$pdo = get_pdo_connection();
$stmt = $pdo->prepare("SELECT u.id, COALESCE(up.username, u.name, '名もなき猫') as display_name FROM users u LEFT JOIN user_profiles up ON u.id = up.user_id WHERE u.id = ?");
$stmt->execute([$targetUserId]);
$user = $stmt->fetch();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!$user) {
    http_response_code(404);
    $pageTitle = 'エラー';
    require_once __DIR__ . '/parts/_header.php';
    require_once __DIR__ . '/parts/_sidebar.php';
    echo '<main class="col-md-9 ms-sm-auto col-lg-10 p-4"><div class="alert alert-danger">指定されたユーザーは見つかりませんでした。</div></main>';
    require_once __DIR__ . '/parts/_footer.php';
    exit;
}

$pageTitle = htmlspecialchars($user['display_name']) . 'のプロフィール';
require_once __DIR__ . '/parts/_header.php';

$currentUserId = $_SESSION['user_id'] ?? null;
?>
<link rel="stylesheet" href="/css/profile_dashboard.css?v=<?= APP_ASSET_VERSION ?>">
<link rel="stylesheet" href="/css/search.css?v=<?= APP_ASSET_VERSION ?>">
<script>
    // JavaScriptでPHPの変数を渡すためのデータ属性
    document.documentElement.setAttribute('data-target-user-id', '<?php echo htmlspecialchars($targetUserId); ?>');
    document.documentElement.setAttribute('data-current-user-id', '<?php echo htmlspecialchars($currentUserId); ?>');
</script>

<?php require_once 'parts/_sidebar.php'; ?>

<main class="col-md-9 ms-sm-auto col-lg-10 p-4">

    <!-- ===== Header ===== -->
    <header class="profile-header">
      <div class="profile-left">
        <canvas id="profile-avatar" width="96" height="96"></canvas>
        <div class="profile-info">
          <h1 id="profile-display-name">ユーザー名</h1>
          <p class="bio" id="profile-bio">ここに自己紹介文が入ります。統計データや活動の可視化が好きです。</p>
        </div>
      </div>

      <div class="profile-summary">
        <div class="stat">
          <div class="val" id="profile-posts-count">0</div>
          <span class="label">投稿</span>
        </div>
        <div class="stat">
          <div class="val" id="profile-followers-count">0</div>
          <span class="label">フォロワー</span>
        </div>
        <div class="stat">
          <div class="val" id="profile-avg-rating">0.0</div>
          <span class="label">平均評価</span>
        </div>
      </div>

      <div class="profile-actions">
        <button class="btn-follow" id="follow-button" data-following="false">フォローする</button>
      </div>
    </header>

    <!-- ===== Main ===== -->
    <div class="profile-tabs">
      <button type="button" class="tab-btn active" data-target="tab-contents">投稿コンテンツ一覧</button>
      <button type="button" class="tab-btn" data-target="tab-following">フォロー中</button>
      <button type="button" class="tab-btn" data-target="tab-followers">フォロワー</button>
    </div>

    <div class="tab-panel active" id="tab-contents">
      <div class="grid-card">
        <h2>投稿コンテンツ一覧</h2>

        <div class="toolbar">
          <div>
            <select id="content-type-filter">
              <option value="all">すべて</option>
              <option value="problem">問題集</option>
              <option value="flashcard">単語帳</option>
              <option value="note">ノート</option>
            </select>

            <select id="content-sort-order">
              <option value="updated_at_desc">新しい順</option>
              <option value="rating_desc">評価が高い順</option>
            </select>
          </div>
        </div>

        <div id="results-container">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <p id="results-count-text" class="mb-0 text-muted">読み込み中...</p>
          </div>
          <div class="table-responsive">
            <table class="table table-striped table-hover table-bordered bg-white">
              <thead class="table-light">
                <tr>
                  <th>ライブラリ</th>
                  <th>タイプ</th>
                  <th>タイトル</th>
                  <th>講義名</th>
                  <th>評価</th>
                  <th>更新日</th>
                </tr>
              </thead>
              <tbody id="results-tbody">
                <tr><td colspan="6" class="text-center text-muted"><div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>
              </tbody>
            </table>
          </div>
          <nav aria-label="Page navigation">
            <ul id="pagination-ul" class="pagination justify-content-center"></ul>
          </nav>
        </div>
      </div>
    </div>

    <div class="tab-panel" id="tab-following">
      <div class="grid-card">
        <h2>フォロー中</h2>
        <ul id="following-list" class="follow-list">
          <li class="text-muted">読み込み中...</li>
        </ul>
        <nav aria-label="Following navigation">
          <ul id="following-pagination" class="pagination justify-content-center"></ul>
        </nav>
      </div>
    </div>

    <div class="tab-panel" id="tab-followers">
      <div class="grid-card">
        <h2>フォロワー</h2>
        <ul id="followers-list" class="follow-list">
          <li class="text-muted">読み込み中...</li>
        </ul>
        <nav aria-label="Followers navigation">
          <ul id="followers-pagination" class="pagination justify-content-center"></ul>
        </nav>
      </div>
    </div>

</main>

<?php require_once __DIR__ . '/parts/_footer.php'; ?>
<script src="/js/profile_dashboard.js?v=<?= APP_ASSET_VERSION ?>"></script>
