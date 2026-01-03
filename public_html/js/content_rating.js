/**
 * ファイル: public_html\js\content_rating.js
 * 使い道: コンテンツ評価UIの描画と評価送信を行います。
 * 定義されている関数:
 *   - initializeRating
 *   - renderAverageDisplay
 *   - renderInteractiveStars
 */


function initializeRating(details) {
    const userRatingSection = document.getElementById('rating-section');
    const averageRatingSection = document.getElementById('average-rating-display');

    // 対象要素が存在しないページでは何もしない
    if (!userRatingSection || !averageRatingSection) return;

    const contentId = userRatingSection.dataset.contentId;
    const rateableType = userRatingSection.dataset.rateableType;

    // --- 描画用の関数 ---
    /**
     * renderAverageDisplay の画面表示を描画/更新します。
     * @param {any} avg 入力値
     * @param {any} count 入力値
     * @returns {void}
     */
    function renderAverageDisplay(avg, count) {
        const avgText = avg > 0 ? parseFloat(avg).toFixed(1) : 'N/A';
        const countText = `(${count}件)`;
        averageRatingSection.innerHTML = `<i class="bi bi-star-fill text-warning"></i> ${avgText} ${countText}`;
    }

    /**
     * renderInteractiveStars の画面表示を描画/更新します。
     * @param {any} myRating 入力値
     * @returns {void}
     */
    function renderInteractiveStars(myRating) {
        let starsHtml = '';
        for (let i = 1; i <= 5; i++) {
            const isFilled = i <= myRating;
            starsHtml += `<i class="bi ${isFilled ? 'bi-star-fill' : 'bi-star'}" data-rating="${i}"></i>`;
        }
        
        userRatingSection.innerHTML = `
            <div class="d-flex align-items-center">
                <div class="rating-stars me-2">${starsHtml}</div>
                <div id="rating-feedback" class="form-text"></div>
            </div>
        `;
    }

    // --- 初期描画 ---
    renderAverageDisplay(details.avg_rating, details.rating_count);
    renderInteractiveStars(details.user_rating);

    // --- クリック時の保存処理 ---
    userRatingSection.addEventListener('click', async (event) => {
        const star = event.target.closest('.bi-star, .bi-star-fill');
        if (!star) return;

        // クリックした星の値を取得
        const rating = parseInt(star.dataset.rating, 10);
        const feedbackEl = document.getElementById('rating-feedback');

        if (feedbackEl) feedbackEl.textContent = '評価を保存中...';

        const formData = new FormData();
        formData.append('rateable_id', contentId);
        formData.append('rateable_type', rateableType);
        formData.append('rating', rating);
        formData.append('csrf_token', getCsrfToken());

        try {
            // 評価を送信
            const response = await fetch('/api/content/save_rating', {
                method: 'POST',
                body: formData,
            });

            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.error || '評価の保存に失敗しました。');
            }

            renderAverageDisplay(result.avg_rating, result.rating_count);
            renderInteractiveStars(result.my_rating);
            
            const newFeedbackEl = document.getElementById('rating-feedback');
            if (newFeedbackEl) {
                newFeedbackEl.textContent = '評価を保存しました！';
                setTimeout(() => { newFeedbackEl.textContent = ''; }, 3000);
            }

        } catch (error) {
            // 失敗時はメッセージを表示
            if (feedbackEl) {
                feedbackEl.textContent = `エラー: ${error.message}`;
                feedbackEl.classList.add('text-danger');
            }
        }
    });
}



