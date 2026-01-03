/**
 * ファイル: public_html\js\manage_informations_page.js
 * 使い道: お知らせ管理（旧版）の一覧表示と更新を行います。
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

    let editingInfoId = null; // Track if we are editing an existing info

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
                console.log("Quill toolbar config:", quill.options.modules.toolbar);
    const hiddenQuill = new Quill('#hidden-converter');

    /**
     * displayGlobalMessage の処理を行います。
     * @param {any} message 入力値
     * @param {any} type 入力値
     * @returns {void}
     */
    function displayGlobalMessage(message, type = 'danger') {
        globalMessageContainer.innerHTML = `<div class="alert alert-${type}" role="alert">${escapeHTML(message)}</div>`;
        if (type === 'success') {
            setTimeout(() => globalMessageContainer.innerHTML = '', 5000);
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
        editingInfoId = null;
        infoIdInput.value = '';
        infoTitleInput.value = '';
        quill.setContents([{ insert: '\n' }]); // Clear Quill editor
        infoCategorySelect.value = 'info';
        infoDisplayFromInput.value = '';
        infoDisplayToInput.value = '';
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
                row.innerHTML = `
                    <td>${info.id}</td>
                    <td>${escapeHTML(info.title)}</td>
                    <td>${formatCategory(info.category)}</td>
                    <td>${formatDateTime(info.display_from)}</td>
                    <td>${formatDateTime(info.display_to)}</td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary edit-btn" data-id="${info.id}" data-title="${escapeHTML(info.title)}" data-content='${info.content}' data-category="${info.category}" data-from="${info.display_from}" data-to="${info.display_to}">
                                                        <i class="bi bi-pencil"></i> 編集
                                                    </button>                        <button class="btn btn-sm btn-outline-danger delete-btn" data-id="${info.id}">
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

        const formData = new FormData();
        formData.append('id', infoIdInput.value);
        formData.append('title', infoTitleInput.value);
        formData.append('content', JSON.stringify(contentDelta)); // Delta as JSON string
        formData.append('category', infoCategorySelect.value);
        formData.append('display_from', infoDisplayFromInput.value);
        formData.append('display_to', infoDisplayToInput.value);
        formData.append('csrf_token', getCsrfToken());

        saveInfoBtn.disabled = true;
        saveInfoBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> 保存中...';

        try {
            const response = await fetch('/api/admin/save_information', {
                method: 'POST',
                body: formData
            });
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.error || '保存に失敗しました。');
            }
            const result = await response.json();
            if (result.success) {
                displayGlobalMessage('インフォメーションを保存しました。', 'success');
                resetForm();
                fetchAndRenderInformations(); // Reload list
            } else {
                throw new Error(result.message || '保存に失敗しました。');
            }
        } catch (error) {
            console.error('Save error:', error);
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
        const editButton = event.target.closest('.edit-btn');
        const deleteButton = event.target.closest('.delete-btn');

                        if (editButton) {
                            editingInfoId = editButton.dataset.id;
                            infoIdInput.value = editingInfoId;
                            infoTitleInput.value = editButton.dataset.title;
                            const contentData = editButton.dataset.content;
                            if (contentData) {
                                quill.setContents(JSON.parse(contentData)); // Load Delta to editor
                            } else {
                                quill.setContents([{ insert: '\n' }]); // Clear editor if content is empty
                            }
                            infoCategorySelect.value = editButton.dataset.category;
                            infoDisplayFromInput.value = formatForDateTimeLocal(editButton.dataset.from);
                            infoDisplayToInput.value = formatForDateTimeLocal(editButton.dataset.to);
                            saveInfoBtn.textContent = '更新';
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                        } else if (deleteButton) {            const infoId = deleteButton.dataset.id;
            const infoTitle = deleteButton.closest('tr').querySelector('td:nth-child(2)').textContent;
            if (confirm(`「${infoTitle}」を本当に削除しますか？`)) {
                try {
                    const formData = new FormData();
                    formData.append('id', infoId);
                    formData.append('csrf_token', getCsrfToken());
                    const response = await fetch('/api/admin/delete_information', {
                        method: 'POST',
                        body: formData
                    });
                    if (!response.ok) {
                        const errorData = await response.json().catch(() => ({}));
                        throw new Error(errorData.error || '削除に失敗しました。');
                    }
                    const result = await response.json();
                    if (result.success) {
                        displayGlobalMessage('インフォメーションを削除しました。', 'success');
                        fetchAndRenderInformations(); // Reload list
                    } else {
                        throw new Error(result.message || '削除に失敗しました。');
                    }
                } catch (error) {
                    console.error('Delete error:', error);
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
        const date = new Date(isoString);
        const year = date.getFullYear();
        const month = (date.getMonth() + 1).toString().padStart(2, '0');
        const day = date.getDate().toString().padStart(2, '0');
        const hours = date.getHours().toString().padStart(2, '0');
        const minutes = date.getMinutes().toString().padStart(2, '0');
        return `${year}-${month}-${day}T${hours}:${minutes}`;
    }

    fetchAndRenderInformations();
});




