/**
 * ファイル: public_html\js\manage_bans.js
 * 使い道: BAN管理画面のユーザー/テナント操作を行います。
 * 定義されている関数:
 *   - displayGlobalMessage
 *   - escapeHTML
 *   - formatBanStatus
 *   - formatDate
 *   - fetchAndRenderUsers
 *   - handleUserBan
 *   - fetchAndRenderTenants
 *   - handleTenantBan
 */


document.addEventListener('DOMContentLoaded', async function() {
    const userListTable = document.getElementById('user-list-table');
    const tenantListTable = document.getElementById('tenant-list-table');
    const userSearchInput = document.getElementById('user-search-input');
    const userSearchButton = document.getElementById('user-search-button');
    const globalMessageContainer = document.getElementById('global-message-container');

    /**
     * displayGlobalMessage の処理を行います。
     * @param {any} message 入力値
     * @param {any} type 入力値
     * @returns {void}
     */
    function displayGlobalMessage(message, type = 'danger') {
        globalMessageContainer.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">${escapeHTML(message)}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>`;
        setTimeout(() => {
            const alert = globalMessageContainer.querySelector('.alert');
            if (alert) bootstrap.Alert.getOrCreateInstance(alert)?.close();
        }, 5000);
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

    /**
     * formatBanStatus の値を整形します。
     * @param {any} bannedAt 入力値
     * @returns {void}
     */
    function formatBanStatus(bannedAt) {
        if (bannedAt) {
            return `<span class="badge bg-danger">BAN中 (${formatDate(bannedAt)})</span>`;
        }
        return `<span class="badge bg-success">アクティブ</span>`;
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
     * fetchAndRenderUsers の画面表示を描画/更新します。
     * @param {any} query 入力値
     * @returns {Promise} 非同期処理の結果を表すPromise
     */
    async function fetchAndRenderUsers(query = '') {
        userListTable.innerHTML = `<tr><td colspan="6" class="text-center text-muted">
            <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>
            ユーザーを読み込み中...
        </td></tr>`;
        try {
            const response = await fetch(`/api/admin/get_users?q=${encodeURIComponent(query)}`);
            if (!response.ok) throw new Error((await response.json()).error || 'ユーザーの取得に失敗しました。');
            const data = await response.json();

            userListTable.innerHTML = '';
            if (data.users.length === 0) {
                userListTable.innerHTML = '<tr><td colspan="6" class="text-center text-muted">ユーザーが見つかりません。</td></tr>';
                return;
            }

            data.users.forEach(user => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${escapeHTML(user.id)}</td>
                    <td>${escapeHTML(user.username || 'N/A')}</td>
                    <td>${escapeHTML(user.email)}</td>
                    <td>${escapeHTML(user.tenant_name || 'N/A')} (${escapeHTML(user.domain_identifier || 'N/A')})</td>
                    <td>${formatBanStatus(user.banned_at)}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-${user.banned_at ? 'success' : 'danger'} ban-user-btn" 
                                data-user-id="${user.id}" data-action="${user.banned_at ? 'unban' : 'ban'}">
                            <i class="bi bi-${user.banned_at ? 'check-circle' : 'x-circle'}"></i> ${user.banned_at ? 'BAN解除' : 'BANする'}
                        </button>
                    </td>
                `;
                userListTable.appendChild(row);
            });
        } catch (error) {
            userListTable.innerHTML = `<tr><td colspan="6" class="text-center text-danger">エラー: ${escapeHTML(error.message)}</td></tr>`;
            displayGlobalMessage(`ユーザーリストの取得中にエラーが発生しました: ${error.message}`, 'danger');
        }
    }

    /**
     * handleUserBan のイベント処理を行います。
     * @param {any} userId 入力値
     * @param {any} action 入力値
     * @returns {Promise} 非同期処理の結果を表すPromise
     */
    async function handleUserBan(userId, action) {
        if (!confirm(`ユーザーID ${userId} を${action === 'ban' ? 'BANしますか' : 'BAN解除しますか'}？`)) return;
        
        const formData = new FormData();
        formData.append('user_id', userId);
        formData.append('action', action);
        formData.append('csrf_token', getCsrfToken());

        try {
            const response = await fetch('/api/admin/ban_user', { method: 'POST', body: formData });
            if (!response.ok) throw new Error((await response.json()).error || 'ユーザーBAN処理に失敗しました。');
            const result = await response.json();
            displayGlobalMessage(result.message, 'success');
            fetchAndRenderUsers(userSearchInput.value); // Re-render after action
        } catch (error) {
            displayGlobalMessage(`ユーザーBAN処理中にエラーが発生しました: ${error.message}`, 'danger');
        }
    }

    /**
     * fetchAndRenderTenants の画面表示を描画/更新します。
     * @returns {Promise} 非同期処理の結果を表すPromise
     */
    async function fetchAndRenderTenants() {
        tenantListTable.innerHTML = `<tr><td colspan="5" class="text-center text-muted">
            <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>
            ドメインを読み込み中...
        </td></tr>`;
        try {
            const response = await fetch('/api/admin/get_tenants');
            if (!response.ok) throw new Error((await response.json()).error || 'ドメインの取得に失敗しました。');
            const data = await response.json();

            tenantListTable.innerHTML = '';
            if (data.tenants.length === 0) {
                tenantListTable.innerHTML = '<tr><td colspan="5" class="text-center text-muted">ドメインが見つかりません。</td></tr>';
                return;
            }

            data.tenants.forEach(tenant => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${escapeHTML(tenant.id)}</td>
                    <td>${escapeHTML(tenant.name)}</td>
                    <td>${escapeHTML(tenant.domain_identifier)}</td>
                    <td>${formatBanStatus(tenant.banned_at)}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-${tenant.banned_at ? 'success' : 'danger'} ban-tenant-btn" 
                                data-tenant-id="${tenant.id}" data-action="${tenant.banned_at ? 'unban' : 'ban'}">
                            <i class="bi bi-${tenant.banned_at ? 'check-circle' : 'x-circle'}"></i> ${tenant.banned_at ? 'BAN解除' : 'BANする'}
                        </button>
                    </td>
                `;
                tenantListTable.appendChild(row);
            });
        } catch (error) {
            tenantListTable.innerHTML = `<tr><td colspan="5" class="text-center text-danger">エラー: ${escapeHTML(error.message)}</td></tr>`;
            displayGlobalMessage(`ドメインリストの取得中にエラーが発生しました: ${error.message}`, 'danger');
        }
    }

    /**
     * handleTenantBan のイベント処理を行います。
     * @param {any} tenantId 入力値
     * @param {any} action 入力値
     * @returns {Promise} 非同期処理の結果を表すPromise
     */
    async function handleTenantBan(tenantId, action) {
        if (!confirm(`テナントID ${tenantId} を${action === 'ban' ? 'BANしますか' : 'BAN解除しますか'}？`)) return;

        const formData = new FormData();
        formData.append('tenant_id', tenantId);
        formData.append('action', action);
        formData.append('csrf_token', getCsrfToken());

        try {
            const response = await fetch('/api/admin/ban_tenant', { method: 'POST', body: formData });
            if (!response.ok) throw new Error((await response.json()).error || 'ドメインBAN処理に失敗しました。');
            const result = await response.json();
            displayGlobalMessage(result.message, 'success');
            fetchAndRenderTenants(); // Re-render after action
        } catch (error) {
            displayGlobalMessage(`ドメインBAN処理中にエラーが発生しました: ${error.message}`, 'danger');
        }
    }

    userSearchButton.addEventListener('click', () => fetchAndRenderUsers(userSearchInput.value));
    userSearchInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            fetchAndRenderUsers(userSearchInput.value);
        }
    });

    userListTable.addEventListener('click', function(event) {
        const button = event.target.closest('.ban-user-btn');
        if (button) {
            const userId = button.dataset.userId;
            const action = button.dataset.action;
            handleUserBan(userId, action);
        }
    });

    tenantListTable.addEventListener('click', function(event) {
        const button = event.target.closest('.ban-tenant-btn');
        if (button) {
            const tenantId = button.dataset.tenantId;
            const action = button.dataset.action;
            handleTenantBan(tenantId, action);
        }
    });

    fetchAndRenderUsers();
    fetchAndRenderTenants();
});




