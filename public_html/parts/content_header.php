<?php
require_once __DIR__ . '/../../api_app/util/api_helpers.php'; // get_pdo_connection() を利用可能にする

// このコンポーネントは、インクルード元のページのスコープで$contentIdが設定されていることを前提としています。
if (!isset($contentId)) {
    echo "<div class='alert alert-danger'>コンテンツヘッダーを読み込めません: コンテンツIDが指定されていません。</div>";
    return;
}


// $contentIdに基づいてヘッダーの詳細を取得する
$details = null;
try {
    $pdo = get_pdo_connection();
    $userId = $_SESSION['user_id'] ?? null;
    $currentUserTenantId = $_SESSION['tenant_id'] ?? null;

    $sql = "
        SELECT 
            c.id, 
            c.title, 
            c.description, 
            c.lecture_id, 
            l.name as lecture_name, 
            c.visibility,
            c.user_id AS owner_user_id,
            up.username AS author_name,
            c.contentable_type,
            (c.user_id = :current_session_user_id) AS can_edit,
            (uac.id IS NOT NULL) AS is_active,
            (SELECT AVG(r.rating) FROM ratings r WHERE r.rateable_id = c.id AND r.rateable_type = c.contentable_type) as avg_rating,
            (SELECT COUNT(r.id) FROM ratings r WHERE r.rateable_id = c.id AND r.rateable_type = c.contentable_type) as rating_count,
            ur.rating as user_rating
        FROM contents c
        JOIN users u ON c.user_id = u.id
        LEFT JOIN user_profiles up ON u.id = up.user_id
        LEFT JOIN lectures l ON c.lecture_id = l.lecture_code AND l.tenant_id = u.tenant_id
        LEFT JOIN user_active_contents uac ON c.id = uac.content_id AND uac.user_id = :current_session_user_id
        LEFT JOIN ratings ur ON ur.rateable_id = c.id AND ur.rateable_type = c.contentable_type AND ur.user_id = :current_session_user_id
        WHERE c.id = :content_id 
          AND c.status = 'published'
          AND c.deleted_at IS NULL
          AND (
               c.visibility = 'public'
               OR c.user_id = :current_session_user_id
               OR (c.visibility = 'domain' AND u.tenant_id = :current_user_tenant_id)
              )
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':content_id', $contentId, PDO::PARAM_INT);
    $stmt->bindValue(':current_session_user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':current_user_tenant_id', $currentUserTenantId, PDO::PARAM_INT);
    $stmt->execute();
    $details = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // DBエラーの場合、メッセージを表示しますが、ページの実行は停止しません
    echo "<div class='alert alert-danger'>ヘッダー情報の取得中にエラーが発生しました。</div>";
    return;
}

if (!$details) {
    echo "<div class='alert alert-warning'>指定されたコンテンツが見つからないか、アクセス権がありません。</div>";
    return; // このファイルの実行を停止しますが、親スクリプトの続行は許可します。
}

// 必要に応じて、ページのタイトルなどのために、親ページのスコープに詳細を公開します。
$pageTitle = $details['title'];

// 公開範囲バッジのためのヘルパー関数
function format_visibility_badge($visibility) {
    $map = [
        'private' => ['text' => '非公開', 'bg' => 'secondary'],
        'domain' => ['text' => 'ドメイン内公開', 'bg' => 'info'],
        'public' => ['text' => '全体に公開', 'bg' => 'success'],
    ];
    $info = $map[$visibility] ?? ['text' => $visibility, 'bg' => 'warning'];
    return "<span class='badge bg-{$info['bg']}'>" . htmlspecialchars($info['text']) . "</span>";
}

// contentable_typeを検索タイプ文字列にマッピングする
$searchTypeMap = [
    'Note' => 'note',
    'ProblemSet' => 'problem',
    'FlashCard' => 'flashcard',
];
$searchType = $searchTypeMap[$details['contentable_type']] ?? 'note';
$searchTypeText = ['note' => 'ノート', 'problem' => '問題集', 'flashcard' => '単語帳'][$searchType] ?? 'コンテンツ';

?>
<div id="global-message-container"></div>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/search.php?types[]=<?= $searchType ?>"><?= $searchTypeText ?>検索</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($details['title']) ?></li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-2">
    <h1 class="h4 mb-0"><?= htmlspecialchars($details['title']) ?>
    <?php 
        $isActive = $details['is_active'] ?? false;
        $btnClass = $isActive ? 'btn-success' : 'btn-outline-primary';
        $btnIcon = $isActive ? 'bi-check' : 'bi-plus';
        $btnText = $isActive ? '追加済み' : 'マイライブラリに追加';
        $activeState = $isActive ? 'true' : 'false';
    ?>
    <button class="btn btn-sm <?= $btnClass ?> ms-2 activate-btn" data-content-id="<?= $contentId ?>" data-active="<?= $activeState ?>">
        <i class="bi <?= $btnIcon ?>"></i> <?= $btnText ?>
    </button>
    <button class="btn btn-sm btn-outline-danger ms-2 report-btn" data-content-id="<?= $contentId ?>" data-bs-toggle="modal" data-bs-target="#reportModal">
        <i class="bi bi-flag"></i> 通報
    </button>
</h1>
    <div class="d-flex align-items-center">
        <span id="average-rating-display" class="me-2">
            <i class="bi bi-star-fill text-warning"></i> 
            <?= htmlspecialchars(number_format($details['avg_rating'] ?? 0, 1)) ?> 
            (<?= htmlspecialchars($details['rating_count'] ?? 0) ?>)
        </span>
        <?php if($details['lecture_id']): ?>
            <span class="badge bg-success me-2 fs-6"><?= htmlspecialchars($details['lecture_name'] ?? $details['lecture_id']) ?></span>
        <?php endif; ?>
        <?= format_visibility_badge($details['visibility']) ?>
    </div>
</div>
<p class="text-muted small mb-0">作成者: <?= htmlspecialchars($details['author_name'] ?? '不明') ?></p>
<p class="text-muted small mb-0">説明: <?= htmlspecialchars($details['description'] ?? 'なし') ?></p>
<hr>
<div id="rating-section" class="mb-3" data-content-id="<?= $contentId ?>" data-rateable-type="<?= htmlspecialchars($details['contentable_type']) ?>">
    <!-- 評価の星はJSによってここに描画されます -->
</div>

<!-- 通報モーダルのHTML -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="report-form">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
        <div class="modal-header">
          <h5 class="modal-title" id="reportModalLabel">コンテンツの通報</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="report-content-id" name="content_id">
          <p>このコンテンツを通報する理由を選択してください。</p>
          <div class="mb-3">
            <label class="form-label">理由カテゴリ</label>
            <div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="reason_category" id="reasonSpam" value="spam" required>
                <label class="form-check-label" for="reasonSpam">スパム</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="reason_category" id="reasonInappropriate" value="inappropriate" required>
                <label class="form-check-label" for="reasonInappropriate">不適切</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="reason_category" id="reasonCopyright" value="copyright" required>
                <label class="form-check-label" for="reasonCopyright">著作権侵害</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="reason_category" id="reasonOther" value="other" required>
                <label class="form-check-label" for="reasonOther">その他</label>
              </div>
            </div>
          </div>
          <div class="mb-3">
            <label for="reasonDetails" class="form-label">詳細 (任意、1000文字以内)</label>
            <textarea class="form-control" id="reasonDetails" name="reason_details" rows="3" maxlength="1000"></textarea>
          </div>
          <div id="report-modal-message" class="alert d-none mt-3" role="alert"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
          <button type="submit" class="btn btn-danger" id="submit-report-btn">通報する</button>
        </div>
      </form>
    </div>
  </div>
</div>
