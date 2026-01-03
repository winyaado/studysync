/**
 * ファイル: public_html\js\solve_page.js
 * 使い道: 試験開始ページで問題データを取得し、試験画面を生成します。
 * 定義されている関数:
 *   - renderExam
 *   - escapeHTML
 */


document.addEventListener('DOMContentLoaded', function() {
    if (typeof contentId === 'undefined') {
        console.error('contentId is not defined.');
        return;
    }
    
    const mainContent = document.getElementById('main-content');

    fetch(`/api/study/solve_data?id=${contentId}`)
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => { throw new Error(err.error || '試験の準備に失敗しました。') });
            }
            return response.json();
        })
        .then(data => {
            const { problemSet, problems } = data;
            document.title = "試験中 - " + escapeHTML(problemSet.title);
            
            const generatedHtml = renderExam(problemSet, problems);
            mainContent.innerHTML = generatedHtml;
            
            const script = document.createElement('script');
            const assetVersion = window.ASSET_VERSION ? `?v=${encodeURIComponent(window.ASSET_VERSION)}` : '';
            script.src = '/js/exam.js' + assetVersion;
            script.onload = function() {
                if (typeof initExam === 'function') {
                    initExam();
                } else {
                    console.error('initExam function not found in exam.js');
                }
            };
            document.body.appendChild(script);
        })
        .catch(error => {
            mainContent.innerHTML = `<div class='alert alert-danger'>エラー: ${escapeHTML(error.message)}</div>`;
        });
    
    /**
     * renderExam の画面表示を描画/更新します。
     * @param {any} problemSet 入力値
     * @param {any} problems 入力値
     * @returns {void}
     */
    function renderExam(problemSet, problems) {
        const problemCardsHtml = problems.map((problem, index) => {
            const choicesHtml = problem.choices.map(choice => `
                <label class="list-group-item list-group-item-action">
                    <input class="form-check-input me-2" type="radio" name="answers[${problem.id}]" value="${choice.id}" required>
                    ${escapeHTML(choice.text)}
                </label>
            `).join('');

            return `
                <div class="card problem-card ${index > 0 ? 'd-none' : ''}" id="problem-${index + 1}">
                    <div class="card-header fw-bold">問題 ${index + 1}</div>
                    <div class="card-body">
                        <p class="card-text fs-5 mb-4">${escapeHTML(problem.question)}</p>
                        <div class="list-group">
                            ${choicesHtml}
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        return `
            <div id="exam-data" data-time-limit="${escapeHTML(problemSet.time_limit_minutes || '0')}"></div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/search.php?types[]=problem">問題集検索</a></li>
                    <li class="breadcrumb-item"><a href="/read/problem.php?id=${problemSet.id}">${escapeHTML(problemSet.title)}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">試験中</li>
                </ol>
            </nav>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h4 mb-0">${escapeHTML(problemSet.title)}</h1>
                <div class="d-flex align-items-center">
                    <i class="bi bi-clock me-2"></i>
                    <span id="time-display" class="fw-bold me-3">--:--</span>
                    <span id="problemCounter" class="fw-bold"></span>
                </div>
            </div>
            <div class="progress mb-4" role="progressbar" id="progress-bar-container">
                <div class="progress-bar" id="progress-bar" style="width: 0%"></div>
            </div>

            <form id="examForm" action="/read/result.php" method="post">
                <input type="hidden" name="csrf_token" value="${escapeHTML(getCsrfToken())}">
                <input type="hidden" name="problem_set_id" value="${problemSet.id}">
                <div id="problemsContainer">
                    ${problemCardsHtml}
                </div>
            </form>

            <div class="d-flex justify-content-between mt-4">
                <button class="btn btn-secondary" id="prevProblemBtn" disabled><i class="bi bi-arrow-left"></i> 前の問題</button>
                <button class="btn btn-danger" id="submitBtn">試験を提出</button>
                <button class="btn btn-primary" id="nextProblemBtn"><i class="bi bi-arrow-right"></i> 次の問題</button>
            </div>
        `;
    }

    /**
     * escapeHTML の文字列をエスケープします。
     * @param {any} str 入力値
     * @returns {void}
     */
    function escapeHTML(str) {
        if (typeof str !== 'string' && typeof str !== 'number') return '';
        return String(str).replace(/[&<>"']/g, match => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[match]);
    }
});




