<?php
// APIテストツール（管理者専用）
require_once __DIR__ . '/../../api_app/util/api_helpers.php';
require_once __DIR__ . '/../../config/config.php';

$pageTitle = 'APIテストツール';
require_once __DIR__ . '/../parts/_header.php';

// --- 管理者チェック ---
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    echo '<main class="col-md-9 ms-sm-auto col-lg-10 p-4"><div class="alert alert-danger">このページにアクセスする権限がありません。</div></main>';
    require_once __DIR__ . '/../parts/_footer.php';
    exit();
}

$allTestCases = require __DIR__ . '/api_test_cases.php';
?>
<style>
    .tester-layout { display: grid; grid-template-columns: 320px 1fr; gap: 16px; }
    .tester-sidebar { border-right: 1px solid #dee2e6; padding-right: 12px; }
    .tester-sidebar .list-group-item { cursor: pointer; }
    .tester-results pre { white-space: pre-wrap; word-break: break-word; }
    .tester-result-card { border: 1px solid #dee2e6; border-radius: 8px; padding: 12px; margin-bottom: 12px; }
    .tester-result-card.pass { border-color: #198754; background: #f3fbf6; }
    .tester-result-card.fail { border-color: #dc3545; background: #fff5f5; }
    .tester-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace; }
    .tester-toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .tester-summary { font-size: 0.95rem; }
    .tester-meta { color: #6c757d; font-size: 0.9rem; }
    .tester-filter { width: 100%; }
    @media (max-width: 992px) {
        .tester-layout { grid-template-columns: 1fr; }
        .tester-sidebar { border-right: none; padding-right: 0; }
    }
</style>

<!-- サイドバー -->
<?php require_once __DIR__ . '/../parts/_sidebar.php'; ?>

<main class="col-md-9 ms-sm-auto col-lg-10 p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0"><?= htmlspecialchars($pageTitle) ?></h1>
        <div class="tester-toolbar">
            <button class="btn btn-primary" id="run-selected-btn"><i class="bi bi-play-circle me-1"></i>選択を実行</button>
            <button class="btn btn-outline-primary" id="run-all-btn">全て実行</button>
            <button class="btn btn-outline-secondary" id="clear-results-btn">結果をクリア</button>
        </div>
    </div>

    <div class="tester-layout">
        <aside class="tester-sidebar">
            <div class="mb-3">
                <input type="text" id="filter-input" class="form-control tester-filter" placeholder="テスト名で検索">
            </div>
            <div class="mb-2 tester-meta">テストグループ</div>
            <div class="list-group" id="group-list"></div>
            <hr>
            <div class="tester-meta mb-2">テストケース</div>
            <div class="list-group" id="case-list"></div>
        </aside>

        <section class="tester-results">
            <div class="tester-summary mb-3">
                <span class="badge bg-secondary" id="summary-total">0件</span>
                <span class="badge bg-success" id="summary-pass">成功 0件</span>
                <span class="badge bg-danger" id="summary-fail">失敗 0件</span>
                <span class="tester-meta ms-2" id="summary-time">0ms</span>
            </div>
            <div id="results-container">
                <div class="alert alert-info">左のテストを選択して「選択を実行」または「全て実行」を押してください。</div>
            </div>
        </section>
    </div>
</main>

<script>
    const TEST_CASES = <?= json_encode($allTestCases) ?>;
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const groupList = document.getElementById('group-list');
    const caseList = document.getElementById('case-list');
    const resultsContainer = document.getElementById('results-container');
    const filterInput = document.getElementById('filter-input');
    const runSelectedBtn = document.getElementById('run-selected-btn');
    const runAllBtn = document.getElementById('run-all-btn');
    const clearResultsBtn = document.getElementById('clear-results-btn');

    const summaryTotal = document.getElementById('summary-total');
    const summaryPass = document.getElementById('summary-pass');
    const summaryFail = document.getElementById('summary-fail');
    const summaryTime = document.getElementById('summary-time');

    let flattenedTests = [];
    let activeGroup = 'all';

    function flattenTests() {
        flattenedTests = [];
        Object.entries(TEST_CASES).forEach(([group, tests]) => {
            tests.forEach((test, index) => {
                if (String(test.method || '').toUpperCase() !== 'GET') {
                    return;
                }
                flattenedTests.push({ group, index, ...test });
            });
        });
    }

    function renderGroups() {
        groupList.innerHTML = '';
        const allItem = document.createElement('button');
        allItem.type = 'button';
        allItem.className = `list-group-item list-group-item-action ${activeGroup === 'all' ? 'active' : ''}`;
        allItem.textContent = `すべて (${flattenedTests.length})`;
        allItem.addEventListener('click', () => {
            activeGroup = 'all';
            renderGroups();
            renderCases();
        });
        groupList.appendChild(allItem);

        Object.entries(TEST_CASES).forEach(([group, tests]) => {
            const readableTests = tests.filter(test => String(test.method || '').toUpperCase() === 'GET');
            if (readableTests.length === 0) {
                return;
            }
            const item = document.createElement('button');
            item.type = 'button';
            item.className = `list-group-item list-group-item-action ${activeGroup === group ? 'active' : ''}`;
            item.textContent = `${group} (${readableTests.length})`;
            item.addEventListener('click', () => {
                activeGroup = group;
                renderGroups();
                renderCases();
            });
            groupList.appendChild(item);
        });
    }

    function renderCases() {
        const filterText = filterInput.value.trim().toLowerCase();
        caseList.innerHTML = '';
        const visibleTests = flattenedTests.filter(test => {
            const matchesGroup = activeGroup === 'all' || test.group === activeGroup;
            const matchesFilter = filterText === '' || (test.test_name || '').toLowerCase().includes(filterText);
            return matchesGroup && matchesFilter;
        });

        if (visibleTests.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'list-group-item text-muted';
            empty.textContent = '該当するテストがありません。';
            caseList.appendChild(empty);
            return;
        }

        visibleTests.forEach(test => {
            const item = document.createElement('label');
            item.className = 'list-group-item d-flex align-items-start gap-2';
            item.innerHTML = `
                <input class="form-check-input mt-1" type="checkbox" data-group="${escapeHTML(test.group)}" data-index="${test.index}">
                <div>
                    <div class="fw-semibold">${escapeHTML(test.test_name || '名称なし')}</div>
                    <div class="tester-meta">${escapeHTML(test.method)} ${escapeHTML(test.endpoint)}</div>
                </div>
            `;
            caseList.appendChild(item);
        });
    }

    function escapeHTML(str) {
        if (str === null || typeof str === 'undefined') return '';
        return String(str).replace(/[&<>"']/g, (m) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[m]));
    }

    function buildFormData(params) {
        const formData = new FormData();
        formData.append('csrf_token', getCsrfToken());

        const appendField = (key, value) => {
            if (value === null || typeof value === 'undefined') return;
            if (Array.isArray(value)) {
                value.forEach((item, idx) => appendField(`${key}[${idx}]`, item));
                return;
            }
            if (typeof value === 'object') {
                Object.entries(value).forEach(([childKey, childVal]) => {
                    appendField(`${key}[${childKey}]`, childVal);
                });
                return;
            }
            formData.append(key, value);
        };

        Object.entries(params || {}).forEach(([key, value]) => appendField(key, value));
        return formData;
    }

    function buildQueryParams(params) {
        const searchParams = new URLSearchParams();
        Object.entries(params || {}).forEach(([key, value]) => {
            if (value === null || typeof value === 'undefined') return;
            if (Array.isArray(value)) {
                value.forEach(item => searchParams.append(`${key}[]`, item));
                return;
            }
            if (typeof value === 'object') {
                searchParams.append(key, JSON.stringify(value));
                return;
            }
            searchParams.append(key, value);
        });
        return searchParams;
    }

    function shouldUseJson(test) {
        if (!test) return false;
        return test.body_type === 'json' || test.json === true;
    }

    async function runSingleTest(test) {
        const startedAt = performance.now();
        const result = {
            test_name: test.test_name || '名称なし',
            endpoint: test.endpoint,
            method: test.method,
            status: 'failed',
            expected: test.expected_status,
            actual: null,
            reason: '',
            duration_ms: 0,
            response_body: null,
        };

        try {
            const requestUrl = new URL(test.endpoint, window.location.origin);
            const options = { method: test.method, headers: {} };

            if (test.method === 'GET') {
                const query = buildQueryParams(test.params || {});
                requestUrl.search = query.toString();
            } else if (test.method === 'POST') {
                if (shouldUseJson(test)) {
                    const payload = { ...(test.params || {}) };
                    if (!payload.csrf_token) payload.csrf_token = getCsrfToken();
                    options.headers['Content-Type'] = 'application/json';
                    options.body = JSON.stringify(payload);
                } else {
                    options.body = buildFormData(test.params || {});
                }
            }

            const response = await fetch(requestUrl.toString(), options);
            result.actual = response.status;

            const responseText = await response.text();
            result.response_body = responseText;
            let responseJson = null;
            try {
                responseJson = JSON.parse(responseText);
            } catch {
                responseJson = null;
            }

            if (response.status !== test.expected_status) {
                result.reason = `HTTP ${response.status} (期待: ${test.expected_status})`;
            } else if (test.expected_json) {
                const expected = test.expected_json;
                const actual = responseJson || {};
                const mismatch = Object.keys(expected).find(key => actual[key] !== expected[key]);
                if (mismatch) {
                    result.reason = `JSONキー不一致: ${mismatch}`;
                } else {
                    result.status = 'passed';
                    result.reason = 'OK';
                }
            } else {
                result.status = 'passed';
                result.reason = 'OK';
            }
        } catch (error) {
            result.reason = error.message || 'テスト実行中にエラー';
        } finally {
            result.duration_ms = Math.round(performance.now() - startedAt);
        }

        return result;
    }

    function renderResult(result) {
        const card = document.createElement('div');
        card.className = `tester-result-card ${result.status === 'passed' ? 'pass' : 'fail'}`;
        card.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <div class="fw-semibold">${escapeHTML(result.test_name)}</div>
                    <div class="tester-meta">${escapeHTML(result.method)} ${escapeHTML(result.endpoint)}</div>
                </div>
                <span class="badge ${result.status === 'passed' ? 'bg-success' : 'bg-danger'}">
                    ${result.status === 'passed' ? '成功' : '失敗'}
                </span>
            </div>
            <div class="tester-meta mb-2">
                期待: ${escapeHTML(result.expected)} / 実際: ${escapeHTML(result.actual)}
                <span class="ms-2">(${result.duration_ms}ms)</span>
            </div>
            <div class="mb-2"><strong>結果:</strong> ${escapeHTML(result.reason)}</div>
            <details>
                <summary>レスポンス詳細</summary>
                <pre class="tester-mono">${escapeHTML(result.response_body || '')}</pre>
            </details>
        `;
        resultsContainer.appendChild(card);
    }

    async function runTests(testsToRun) {
        resultsContainer.innerHTML = '';
        runSelectedBtn.disabled = true;
        runAllBtn.disabled = true;

        const summary = { total: 0, pass: 0, fail: 0, time: 0 };
        const totalStart = performance.now();

        for (const test of testsToRun) {
            summary.total++;
            const result = await runSingleTest(test);
            if (result.status === 'passed') summary.pass++;
            else summary.fail++;
            renderResult(result);
        }

        summary.time = Math.round(performance.now() - totalStart);
        summaryTotal.textContent = `${summary.total}件`;
        summaryPass.textContent = `成功 ${summary.pass}件`;
        summaryFail.textContent = `失敗 ${summary.fail}件`;
        summaryTime.textContent = `${summary.time}ms`;

        runSelectedBtn.disabled = false;
        runAllBtn.disabled = false;
    }

    function getSelectedTests() {
        const checked = Array.from(caseList.querySelectorAll('input[type="checkbox"]:checked'));
        if (checked.length === 0) return [];
        return checked.map(input => {
            const group = input.dataset.group;
            const index = parseInt(input.dataset.index, 10);
            return (TEST_CASES[group] || [])[index];
        }).filter(Boolean).map((test, idx) => ({ group: checked[idx].dataset.group, index: parseInt(checked[idx].dataset.index, 10), ...test }));
    }

    filterInput.addEventListener('input', renderCases);
    runSelectedBtn.addEventListener('click', () => {
        const selected = getSelectedTests();
        if (selected.length === 0) {
            resultsContainer.innerHTML = '<div class="alert alert-warning">選択されたテストがありません。</div>';
            return;
        }
        runTests(selected);
    });
    runAllBtn.addEventListener('click', () => runTests(flattenedTests));
    clearResultsBtn.addEventListener('click', () => {
        resultsContainer.innerHTML = '<div class="alert alert-info">結果をクリアしました。</div>';
        summaryTotal.textContent = '0件';
        summaryPass.textContent = '成功 0件';
        summaryFail.textContent = '失敗 0件';
        summaryTime.textContent = '0ms';
    });

    flattenTests();
    renderGroups();
    renderCases();
});
</script>

<?php
require_once __DIR__ . '/../parts/_footer.php';
?>
