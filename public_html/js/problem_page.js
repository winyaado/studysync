/**
 * ファイル: public_html\js\problem_page.js
 * 使い道: 問題集詳細ページの表示データ取得と描画を行います。
 * 定義されている関数:
 *   - escapeHTML
 *   - formatDate
 *   - renderPageContent
 *   - renderAttempts
 *   - fetchAndRender
 */


document.addEventListener('DOMContentLoaded', function() {
    if (typeof contentId === 'undefined' || typeof details === 'undefined') {
        console.error('contentId or details not defined by the PHP page.');
        if(!details) return; 
    }

    const mainContent = document.getElementById('main-content');
    const pageSpecificContent = document.getElementById('page-specific-content');

    /**
     * escapeHTML の文字列をエスケープします。
     * @param {any} str 入力値
     * @returns {void}
     */
    function escapeHTML(str) {
        if (typeof str !== 'string' && typeof str !== 'number') return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
        return String(str).replace(/[&<>"']/g, m => map[m] || '');
    }
    /**
     * formatDate の値を整形します。
     * @param {any} dateString 入力値
     * @returns {void}
     */
    function formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('ja-JP', { year: 'numeric', month: '2-digit', day: '2-digit' }) + ' ' + date.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit'});
    }

    /**
     * renderPageContent の画面表示を描画/更新します。
     * @param {any} problemSet 入力値
     * @param {any} attempts 入力値
     * @returns {void}
     */
    function renderPageContent(problemSet, attempts) {
        if (!pageSpecificContent) {
            console.error('#page-specific-content not found');
            return;
        }

        const timeLimit = problemSet.time_limit_minutes ? `${escapeHTML(problemSet.time_limit_minutes)}分` : '---';

        pageSpecificContent.innerHTML = `
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body text-center p-5">
                            <h5 class="card-title">試験を開始する</h5>
                            <p class="card-text text-muted">この問題集を解いて、理解度を確認しましょう。</p>
                            <a href="/read/solve.php?id=${problemSet.id}" class="btn btn-lg btn-primary mt-3"><i class="bi bi-play-circle me-2"></i>この問題集を解く</a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header fw-bold">概要</div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li class="mb-2 d-flex justify-content-between"><strong>問題数:</strong> <span>${escapeHTML(problemSet.question_count)}問</span></li>
                                <li class="d-flex justify-content-between"><strong>想定時間:</strong> <span>${timeLimit}</span></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header fw-bold">過去の回答履歴</div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>回答日時</th>
                                <th>バージョン</th>
                                <th>スコア</th>
                                <th>正答率</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${renderAttempts(attempts)}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    /**
     * renderAttempts の画面表示を描画/更新します。
     * @param {any} attempts 入力値
     * @returns {void}
     */
    function renderAttempts(attempts) {
        if (attempts.length === 0) {
            return '<tr><td colspan="4" class="text-center text-muted">まだ回答履歴がありません。</td></tr>';
        }
        return attempts.map(attempt => {
            const percentage = (attempt.total_questions > 0) ? (attempt.score / attempt.total_questions) * 100 : 0;
            return `
                <tr class="clickable-row" data-href="/read/result.php?attempt_id=${attempt.id}">
                    <td>${formatDate(attempt.completed_at)}</td>
                    <td>Ver. ${escapeHTML(attempt.version)}</td>
                    <td>${escapeHTML(attempt.score)} / ${escapeHTML(attempt.total_questions)}</td>
                    <td>${percentage.toFixed(1)}%</td>
                </tr>
            `;
        }).join('');
    }

    /**
     * fetchAndRender の画面表示を描画/更新します。
     * @returns {Promise} 非同期処理の結果を表すPromise
     */
    async function fetchAndRender() {
        try {
            const response = await fetch(`/api/study/problem_details?id=${contentId}`);
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.error || '問題集の読み込みに失敗しました。');
            }
            const data = await response.json();
            
            renderPageContent(data.problemSet, data.attempts);
            
            if (typeof initializeRating === 'function') {
                const fallbackUserRating = (details && typeof details.user_rating !== 'undefined')
                    ? details.user_rating
                    : null;
                const userRating = (data.problemSet.my_rating !== null && typeof data.problemSet.my_rating !== 'undefined')
                    ? data.problemSet.my_rating
                    : fallbackUserRating;

                initializeRating({
                    avg_rating: data.problemSet.avg_rating,
                    rating_count: data.problemSet.rating_count,
                    user_rating: userRating
                });
            }

        } catch (error) {
            if(pageSpecificContent) {
                pageSpecificContent.innerHTML = `<div class='alert alert-danger'>エラー: ${escapeHTML(error.message)}</div>`;
            } else {
                mainContent.innerHTML = `<div class='alert alert-danger'>エラー: ${escapeHTML(error.message)}</div>`;
            }
        }
    }

    mainContent.addEventListener('click', function(e) {
        const row = e.target.closest('.clickable-row');
        if (row && row.dataset.href) {
            window.location.href = row.dataset.href;
        }
    });

    if(details) { // Only run if the header successfully loaded the details
      fetchAndRender();
    }
});




