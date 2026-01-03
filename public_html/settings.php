<?php
$pageTitle = '設定';
require_once __DIR__ . '/parts/_header.php';
require_once __DIR__ . '/../api_app/util/UserProfile.php';

$userProfileModel = new UserProfile();

// Fetch the latest profile data
$profile = $userProfileModel->getProfile($_SESSION['user_id']);
$userName = $profile['username'];
$userBio = $profile['bio'];
?>

                <!-- サイドバー -->
                <?php require_once 'parts/_sidebar.php'; ?>

                <!-- メインコンテンツ -->
                <main class="col-md-9 ms-sm-auto col-lg-10 p-4">
                    <h1 class="h3 fw-bold mb-4">設定</h1>

                    <div id="settings-feedback" class="alert d-none" role="alert"></div>

                    <div class="row justify-content-center">
                        <div class="col-lg-8">
                            <div class="card p-4">
                                <!-- ユーザー名セクション -->
                                <section class="mb-5">
                                    <h3 class="h5 fw-semibold border-bottom pb-2 mb-3">ユーザー名</h3>
                                    <form method="POST" action="/api/user/save_settings" class="settings-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                                        <div class="mb-3">
                                            <label for="username" class="form-label">新しいユーザー名</label>
                                            <input type="text" class="form-control" id="username" name="new_username" value="<?php echo htmlspecialchars($userName); ?>" required>
                                        </div>
                                        <button type="submit" class="btn btn-primary">ユーザー名を更新</button>
                                    </form>
                                </section>

                                <!-- 自己紹介セクション -->
                                <section class="mb-5">
                                    <h3 class="h5 fw-semibold border-bottom pb-2 mb-3">自己紹介</h3>
                                    <form method="POST" action="/api/user/save_settings" class="settings-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                                        <div class="mb-3">
                                            <label for="bio" class="form-label">自己紹介文</label>
                                            <textarea class="form-control" id="bio" name="new_bio" rows="5" placeholder="あなたのプロフィールについて簡単な説明を追加しましょう"><?php echo htmlspecialchars($userBio); ?></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary">自己紹介を更新</button>
                                    </form>
                                </section>

                                <!-- プロフィールアイコンセクション -->
                                <section class="mb-5">
                                    <h3 class="h5 fw-semibold border-bottom pb-2 mb-3">プロフィールアイコン</h3>
                                    
                                    <div class="alert alert-light small">「新しいアイコンを生成」ボタンで、ランダムなアイコンがプロフィールに設定されます。</div>

                                    <div class="row text-center mb-4 justify-content-center">
                                        <div class="col-auto">
                                            <h6>現在のアイコン</h6>
                                            <canvas id="identicon-current" width="128" height="128"></canvas>
                                            <div class="mt-2">
                                                <button id="btn-save-current" class="btn btn-sm btn-outline-secondary">お気に入りに保存</button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-center mb-4">
                                        <button id="btn-generate-new" class="btn btn-primary">新しいアイコンを生成</button>
                                    </div>

                                    <h4 class="h6 fw-semibold mt-4">お気に入りスロット (<span id="favorite-count">0</span>/<span id="favorite-limit">5</span>)</h4>
                                    <div id="identicon-favorites-list" class="d-flex flex-wrap gap-3 justify-content-center">
                                        <!-- お気に入りアイコンはJSでここに描画されます -->
                                        <p class="text-muted w-100 text-center">お気に入りはまだありません。</p>
                                    </div>
                                </section>
                                
                                <!-- 他の設定はここに追加できます -->
                            </div>
                        </div>
                    </div>
                </main>

<!-- 上書き確認用モーダル -->
<div class="modal fade" id="overwrite-confirm-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">お気に入りを上書き保存</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>お気に入りスロットが満杯です。どのお気に入りを削除して、新しいアイコンを保存しますか？</p>
                <div id="overwrite-options" class="d-flex flex-wrap gap-3 justify-content-center">
                    <!-- 上書き対象のお気に入りアイコンがここに表示されます -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/parts/_footer.php';
?>
<script src="/js/settings_identicon.js?v=<?= APP_ASSET_VERSION ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const feedback = document.getElementById('settings-feedback');

    function showFeedback(message, type) {
        if (!feedback) return;
        feedback.className = `alert alert-${type}`;
        feedback.textContent = message;
        feedback.classList.remove('d-none');
        feedback.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    document.querySelectorAll('.settings-form').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const formData = new FormData(form);

            try {
                const response = await fetch(form.action, { method: 'POST', body: formData });
                const result = await response.json().catch(() => ({}));
                if (!response.ok || !result.success) {
                    throw new Error(result.error || '更新に失敗しました。');
                }
                showFeedback(result.message || '更新しました。', 'success');
            } catch (error) {
                showFeedback(error.message || '更新に失敗しました。', 'danger');
            }
        });
    });
});
</script>

