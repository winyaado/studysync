/**
 * ファイル: public_html\js\manage_informations.js
 * 使い道: お知らせ管理（新版）の一覧表示と更新を行います。
 * 定義されている関数:
 *   - displayGlobalMessage
 *   - clearGlobalMessage
 *   - resetForm
 *   - fetchAndRenderInformations
 *   - escapeHTML
 *   - formatCategory
 *   - formatDateTime
 *   - formatForDateTimeLocal
 */


document.addEventListener('DOMContentLoaded', async function() {
    const informationForm = document.getElementById('information-form');
    const infoIdInput = document.getElementById('info-id');
    const infoTitleInput = document.getElementById('info-title');
    const infoCategorySelect = document.getElementById('info-category');
    const infoDisplayFromInput = document.getElementById('info-display-from');
    const infoDisplayToInput = document.getElementById('info-display-to');
    const saveInfoBtn = document.getElementById('save-info-btn');
    const cancelEditBtn = document.getElementById('cancel-edit-btn');
    const informationsListTable = document.getElementById('informations-list-table');
    const globalMessageContainer = document.getElementById('global-message-container');

    const quill = new Quill('#info-editor', {
        theme: 'snow',
        placeholder: 'インフォメーションの内容をここに入力...',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['link'],
                [{ 'color': [] }, { 'background': [] }],
                ['clean']
            ]
        },
    });

    /**
     * displayGlobalMessage の処理を行います。
     * @param {any} message 入力値
     * @param {any} type 入力値
     * @returns {void}
     */
    function displayGlobalMessage(message, type = 'danger') {
        globalMessageContainer.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${escapeHTML(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>`;
        if (type === 'success') {
            setTimeout(() => {
                const alert = globalMessageContainer.querySelector('.alert');
                if (alert) bootstrap.Alert.getOrCreateInstance(alert).close();
            }, 5000);
        }
    }

    /**
     * clearGlobalMessage の処理を行います。
     * @returns {void}
     */
    function clearGlobalMessage() {
        globalMessageContainer.innerHTML = '';
    }

    /**
     * resetForm の値や状態を設定します。
     * @returns {void}
     */
    function resetForm() {
        informationForm.reset();
        infoIdInput.value = '';
        quill.setContents([{ insert: '\n' }]); // Clear Quill editor
        saveInfoBtn.textContent = '保存';
    }

    /**
     * fetchAndRenderInformations の画面表示を描画/更新します。
     * @returns {Promise} 非同期処理の結果を表すPromise
     */
    async function fetchAndRenderInformations() {
        clearGlobalMessage();
        informationsListTable.innerHTML = `<tr><td colspan="6" class="text-center text-muted">
            <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>
            読み込み中...
        </td></tr>`;

        try {
            const response = await fetch('/api/admin/get_all_informations');
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.error || 'インフォメーションの取得に失敗しました。');
            }
            const data = await response.json();

            informationsListTable.innerHTML = ''; // Clear loading
            if (data.informations.length === 0) {
                informationsListTable.innerHTML = '<tr><td colspan="6" class="text-center text-muted">インフォメーションはまだありません。</td></tr>';
                return;
            }

            data.informations.forEach(info => {
                const row = document.createElement('tr');
                row.dataset.info = JSON.stringify(info);

                row.innerHTML = `
                    <td>${info.id}</td>
                    <td>${escapeHTML(info.title)}</td>
                    <td>${formatCategory(info.category)}</td>
                    <td>${formatDateTime(info.display_from)}</td>
                    <td>${formatDateTime(info.display_to)}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary edit-btn">
                            <i class="bi bi-pencil"></i> 編集
                        </button>
                        <button class="btn btn-sm btn-outline-danger delete-btn">
                            <i class="bi bi-trash"></i> 削除
                        </button>
                    </td>
                `;
                informationsListTable.appendChild(row);
            });
        } catch (error) {
            console.error('Error fetching informations:', error);
            informationsListTable.innerHTML = `<tr><td colspan="6" class="text-center text-danger">${escapeHTML(error.message)}</td></tr>`;
        }
    }

    informationForm.addEventListener('submit', async function(event) {
        event.preventDefault();
        clearGlobalMessage();

        const contentDelta = quill.getContents();
        if (quill.getText().trim().length === 0) {
            displayGlobalMessage('インフォメーションの内容を入力してください。', 'danger');
            return;
        }

        const formData = new FormData(informationForm);
        formData.set('content', JSON.stringify(contentDelta));

        saveInfoBtn.disabled = true;
        saveInfoBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> 保存中...';

        try {
            const response = await fetch('/api/admin/save_information', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            if (!response.ok) {
                throw new Error(result.error || '保存に失敗しました。');
            }
            displayGlobalMessage('インフォメーションを保存しました。', 'success');
            resetForm();
            fetchAndRenderInformations();
        } catch (error) {
            displayGlobalMessage(`保存エラー: ${error.message}`, 'danger');
        } finally {
            saveInfoBtn.disabled = false;
            saveInfoBtn.textContent = '保存';
        }
    });

    cancelEditBtn.addEventListener('click', () => {
        resetForm();
    });

    informationsListTable.addEventListener('click', async function(event) {
        const target = event.target;
        const editButton = target.closest('.edit-btn');
        const deleteButton = target.closest('.delete-btn');
        const row = target.closest('tr');

        if (!row) return;
        const infoData = JSON.parse(row.dataset.info);

        if (editButton) {
            infoIdInput.value = infoData.id;
            infoTitleInput.value = infoData.title;
            infoCategorySelect.value = infoData.category;
            infoDisplayFromInput.value = formatForDateTimeLocal(infoData.display_from);
            infoDisplayToInput.value = formatForDateTimeLocal(infoData.display_to);
            if(infoData.content) {
                quill.setContents(JSON.parse(infoData.content));
            } else {
                quill.setContents([{ insert: '\n' }]);
            }
            saveInfoBtn.textContent = '更新';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else if (deleteButton) {
            if (confirm(`「${infoData.title}」を本当に削除しますか？`)) {
                try {
                    const formData = new FormData();
                    formData.append('id', infoData.id);
                    formData.append('csrf_token', getCsrfToken());
                    const response = await fetch('/api/admin/delete_information', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    if (!response.ok) {
                        throw new Error(result.error || '削除に失敗しました。');
                    }
                    displayGlobalMessage('インフォメーションを削除しました。', 'success');
                    fetchAndRenderInformations();
                } catch (error) {
                    displayGlobalMessage(`削除エラー: ${error.message}`, 'danger');
                }
            }
        }
    });

    /**
     * escapeHTML の文字列をエスケープします。
     * @param {any} str 入力値
     * @returns {void}
     */
    function escapeHTML(str) {
        if (str === null || typeof str === 'undefined') return '';
        return String(str).replace(/[&<>'"']/g, match => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[match]);
    }
    
    /**
     * formatCategory の値を整形します。
     * @param {any} category 入力値
     * @returns {void}
     */
    function formatCategory(category) {
        const map = {
            'info': { text: '情報', bg: 'bg-primary' },
            'warning': { text: '注意', bg: 'bg-warning' },
            'danger': { text: '警告', bg: 'bg-danger' },
        };
        const info = map[category] || { text: category, bg: 'bg-secondary' };
        return `<span class="badge ${info.bg}">${escapeHTML(info.text)}</span>`;
    }

    /**
     * formatDateTime の値を整形します。
     * @param {any} isoString 入力値
     * @returns {void}
     */
    function formatDateTime(isoString) {
        if (!isoString) return '';
        const date = new Date(isoString);
        return date.toLocaleDateString('ja-JP', { year: 'numeric', month: '2-digit', day: '2-digit' }) + ' ' + date.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit'});
    }

    /**
     * formatForDateTimeLocal の値を整形します。
     * @param {any} isoString 入力値
     * @returns {void}
     */
    function formatForDateTimeLocal(isoString) {
        if (!isoString) return '';
        const date = new Date(isoString + 'Z'); // Assume UTC, convert to local time for input
        const year = date.getFullYear();
        const month = (date.getMonth() + 1).toString().padStart(2, '0');
        const day = date.getDate().toString().padStart(2, '0');
        const hours = date.getHours().toString().padStart(2, '0');
        const minutes = date.getMinutes().toString().padStart(2, '0');
        return `${year}-${month}-${day}T${hours}:${minutes}`;
    }

    fetchAndRenderInformations();
});




