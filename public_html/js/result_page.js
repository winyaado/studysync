/**
 * ファイル: public_html\js\result_page.js
 * 使い道: 試験結果ページの表示データ取得と描画を行います。
 * 定義されている関数:
 *   - renderResultPage
 *   - renderDetailedResults
 *   - addFilterEventListeners
 *   - escapeHTML
 *   - nl2br
 *   - formatDate
 */


document.addEventListener('DOMContentLoaded', function() {
    if (typeof attemptId === 'undefined') {
        console.error('attemptId is not defined.');
        document.getElementById('main-content').innerHTML = `<div class='alert alert-danger'>エラー: 結果IDが指定されていません。</div>`;
        return;
    }

    const mainContent = document.getElementById('main-content');

    fetch(`/api/study/result_details?attempt_id=${attemptId}`)
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => { throw new Error(err.error || '結果の取得に失敗しました。') });
            }
            return response.json();
        })
        .then(data => {
            document.title = "試験結果 - " + escapeHTML(data.problemSet.title);
            mainContent.innerHTML = renderResultPage(data);
            addFilterEventListeners();
        })
        .catch(error => {
            mainContent.innerHTML = `<div class='alert alert-danger'>エラー: ${escapeHTML(error.message)}</div>`;
        });

    /**
     * renderResultPage の画面表示を描画/更新します。
     * @param {any} data 入力値
     * @returns {void}
     */
    function renderResultPage(data) {
        const { problemSet, attempt, detailedResults } = data;
        const score = attempt.score;
        const totalQuestions = attempt.total_questions;
        const percentage = (totalQuestions > 0) ? (score / totalQuestions) * 100 : 0;

        return `
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/search.php?types[]=problem">問題集検索</a></li>
                    <li class="breadcrumb-item"><a href="/read/problem.php?id=${problemSet.id}">${escapeHTML(problemSet.title)}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">結果 (${formatDate(attempt.completed_at)})</li>
                </ol>
            </nav>

            <h1 class="h4 mb-4">試験結果</h1>
            
            <div class="card text-center mb-4">
                <div class="card-body">
                    <h5 class="card-title">あなたのスコア</h5>
                    <p class="display-4 fw-bold">${score} / ${totalQuestions}</p>
                    <p class="card-text text-muted">正答率: ${percentage.toFixed(1)}%</p>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">詳細結果</h5>
                <div class="btn-group btn-group-sm" role="group" id="result-filter-buttons">
                    <button type="button" class="btn btn-outline-secondary active" data-filter="all">すべて</button>
                    <button type="button" class="btn btn-outline-secondary" data-filter="incorrect">不正解のみ</button>
                    <button type="button" class="btn btn-outline-secondary" data-filter="correct">正解のみ</button>
                </div>
            </div>

            <div class="list-group" id="detailed-results-list">
                ${renderDetailedResults(detailedResults)}
            </div>
        `;
    }

    /**
     * renderDetailedResults の画面表示を描画/更新します。
     * @param {any} results 入力値
     * @returns {void}
     */
    function renderDetailedResults(results) {
        if (results.length === 0) {
            return '<div class="list-group-item text-center text-muted">結果の詳細はありません。</div>';
        }
        return results.map((result, index) => {
            const correctBadge = `<span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> 正解</span>`;
            const incorrectBadge = `<span class="badge bg-danger"><i class="bi bi-x-circle-fill"></i> 不正解</span>`;
            const explanationHtml = result.explanation ? `
                <div class="mt-2">
                    <a class="btn btn-sm btn-outline-info" data-bs-toggle="collapse" href="#explanation-${index}" role="button">
                        <i class="bi bi-info-circle"></i> 解説を表示
                    </a>
                    <div class="collapse mt-2" id="explanation-${index}">
                        <div class="card card-body bg-light">${nl2br(escapeHTML(result.explanation))}</div>
                    </div>
                </div>
            ` : '';

            return `
                <div class="list-group-item" data-result-type="${result.is_correct ? 'correct' : 'incorrect'}">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1 fw-bold">問題 ${index + 1}</h6>
                        ${result.is_correct ? correctBadge : incorrectBadge}
                    </div>
                    <p class="mb-2">${nl2br(escapeHTML(result.question_text))}</p>
                    <small>あなたの回答: ${escapeHTML(result.user_answer_text)}</small><br>
                    ${!result.is_correct ? `<small class="text-success">正解: ${escapeHTML(result.correct_answer_text)}</small>${explanationHtml}` : explanationHtml}
                </div>
            `;
        }).join('');
    }

    /**
     * addFilterEventListeners の追加処理を行います。
     * @returns {void}
     */
    function addFilterEventListeners() {
        const filterButtons = document.getElementById('result-filter-buttons');
        const resultsList = document.getElementById('detailed-results-list');
        if (!filterButtons || !resultsList) return;
        
        const resultItems = resultsList.querySelectorAll('.list-group-item');

        filterButtons.addEventListener('click', function(e) {
            if (e.target.tagName !== 'BUTTON') return;
            
            const currentActive = filterButtons.querySelector('.active');
            if (currentActive) currentActive.classList.remove('active');
            
            e.target.classList.add('active');
            const filter = e.target.dataset.filter;

            resultItems.forEach(item => {
                const hasResultType = item.hasAttribute('data-result-type');
                if (!hasResultType) return; // Skip items without a result type
                
                item.style.display = (filter === 'all' || item.dataset.resultType === filter) ? 'block' : 'none';
            });
        });
    }
    
    /**
     * escapeHTML の文字列をエスケープします。
     * @param {any} str 入力値
     * @returns {void}
     */
    function escapeHTML(str) {
        if (typeof str !== 'string' && typeof str !== 'number') return '';
        return String(str).replace(/[&<"']/g, match => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'})[match]);
    }

    /**
     * nl2br の処理を行います。
     * @param {any} str 入力値
     * @returns {void}
     */
    function nl2br(str) {
        if (typeof str !== 'string') return '';
        return str.replace(/\r\n|\r|\n/g, '<br>');
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
});




