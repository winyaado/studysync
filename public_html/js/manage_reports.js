/**
 * ファイル: public_html\js\manage_reports.js
 * 使い道: 通報管理画面の一覧表示とステータス更新を行います。
 * 定義されている関数:
 *   - escapeHTML
 *   - formatDate
 *   - formatStatus
 *   - renderReports
 *   - fetchReports
 *   - filterAndRenderReports
 */



document.addEventListener('DOMContentLoaded', function() {
    const reportsTbody = document.getElementById('reports-tbody');
    const loadingState = document.getElementById('loading-state');
    const reportsTableWrapper = document.getElementById('reports-table-wrapper');
    const noReportsMessage = document.getElementById('no-reports-message');
    const filterStatusSelect = document.getElementById('filterStatus');

    let allReports = []; // Store all reports for filtering

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
     * formatStatus の値を整形します。
     * @param {any} status 入力値
     * @returns {void}
     */
    function formatStatus(status) {
        switch (status) {
            case 'open': return '<span class="badge bg-danger">未対応</span>';
            case 'in_progress': return '<span class="badge bg-warning text-dark">対応中</span>';
            case 'closed': return '<span class="badge bg-success">対応済み</span>';
            default: return `<span class="badge bg-secondary">${escapeHTML(status)}</span>`;
        }
    }

    /**
     * renderReports の画面表示を描画/更新します。
     * @param {any} reportsToRender 入力値
     * @returns {void}
     */
    function renderReports(reportsToRender) {
        reportsTbody.innerHTML = ''; // Clear existing rows

        if (reportsToRender.length === 0) {
            noReportsMessage.style.display = 'block';
            reportsTableWrapper.style.display = 'none';
            return;
        }

        noReportsMessage.style.display = 'none';
        reportsTableWrapper.style.display = 'block';

        reportsToRender.forEach(report => {
            const row = document.createElement('tr');
            row.dataset.reportId = report.id; // Store report ID for action
            
            const contentLink = `/read/${report.content_type.toLowerCase()}.php?id=${report.content_id}`;
            const reportedContent = `<a href="${contentLink}" target="_blank">${escapeHTML(report.content_title || '不明なコンテンツ')}</a>`;
            const reportedByUser = escapeHTML(report.reporting_username || `ユーザーID: ${report.reporting_user_id}`);

            row.innerHTML = `
                <td>${escapeHTML(report.id)}</td>
                <td>${formatStatus(report.status)}</td>
                <td>${escapeHTML(report.reason_category)}</td>
                <td>${reportedContent}</td>
                <td>${reportedByUser}</td>
                <td>${formatDate(report.created_at)}</td>
                <td>
                    <select class="form-select form-select-sm status-select" data-report-id="${report.id}">
                        <option value="open" ${report.status === 'open' ? 'selected' : ''}>未対応</option>
                        <option value="in_progress" ${report.status === 'in_progress' ? 'selected' : ''}>対応中</option>
                        <option value="closed" ${report.status === 'closed' ? 'selected' : ''}>対応済み</option>
                    </select>
                </td>
            `;
            reportsTbody.appendChild(row);
        });
    }

    /**
     * fetchReports のデータを取得します。
     * @returns {Promise} 非同期処理の結果を表すPromise
     */
    async function fetchReports() {
        loadingState.style.display = 'block';
        reportsTableWrapper.style.display = 'none';
        noReportsMessage.style.display = 'none';

        try {
            const response = await fetch('/api/admin/get_reports');
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.error || '通報リストの取得に失敗しました。');
            }
            const data = await response.json();
            allReports = data.reports || []; // Store all fetched reports
            filterAndRenderReports(); // Initial render
        } catch (error) {
            reportsTbody.innerHTML = `<tr><td colspan="7" class="text-danger">エラー: ${escapeHTML(error.message)}</td></tr>`;
            console.error('Failed to fetch reports:', error);
        } finally {
            loadingState.style.display = 'none';
        }
    }

    /**
     * filterAndRenderReports の画面表示を描画/更新します。
     * @returns {void}
     */
    function filterAndRenderReports() {
        const selectedStatus = filterStatusSelect.value;
        const filteredReports = allReports.filter(report => 
            selectedStatus === '' || report.status === selectedStatus
        );
        renderReports(filteredReports);
    }

    reportsTbody.addEventListener('change', async function(event) {
        const selectElement = event.target.closest('.status-select');
        if (!selectElement) return;

        const reportId = selectElement.dataset.reportId;
        const newStatus = selectElement.value;

        if (!confirm(`通報ID: ${reportId} のステータスを "${newStatus}" に変更しますか？`)) {
            const originalReport = allReports.find(r => r.id == reportId);
            if (originalReport) {
                selectElement.value = originalReport.status;
            }
            return;
        }

        const formData = new FormData();
        formData.append('report_id', reportId);
        formData.append('new_status', newStatus);
        formData.append('csrf_token', getCsrfToken());

        try {
            const response = await fetch('/api/admin/update_report_status', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.error || 'ステータス更新に失敗しました。');
            }

            const updatedReportIndex = allReports.findIndex(r => r.id == reportId);
            if (updatedReportIndex !== -1) {
                allReports[updatedReportIndex].status = newStatus;
            }
            filterAndRenderReports(); // Re-render to reflect changes
            alert('ステータスを更新しました。');

        } catch (error) {
            alert(`ステータスの更新中にエラーが発生しました: ${error.message}`);
            console.error('Failed to update report status:', error);
            const originalReport = allReports.find(r => r.id == reportId);
            if (originalReport) {
                selectElement.value = originalReport.status;
            }
        }
    });

    filterStatusSelect.addEventListener('change', filterAndRenderReports);

    fetchReports();
});




