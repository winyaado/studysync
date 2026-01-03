/**
 * ファイル: public_html\js\settings_identicon.js
 * 使い道: 設定画面のIdenticon生成・保存・削除を管理します。
 * 定義されている関数:
 *   - parseSeedsString
 *   - initialize
 *   - render
 *   - handleSave
 *   - handleSetActive
 *   - handleDelete
 *   - promptOverwrite
 */


(function () {
    'use strict';

    const elements = {
        currentCanvas: document.getElementById('identicon-current'),
        favoritesList: document.getElementById('identicon-favorites-list'),
        favoriteCount: document.getElementById('favorite-count'),
        favoriteLimit: document.getElementById('favorite-limit'),
        generateBtn: document.getElementById('btn-generate-new'),
        saveCurrentBtn: document.getElementById('btn-save-current'),
        overwriteModal: new bootstrap.Modal(document.getElementById('overwrite-confirm-modal')),
        overwriteOptions: document.getElementById('overwrite-options'),
    };

    let state = {
        userId: document.documentElement.dataset.currentUserId,
        activeIdenticonSeeds: { color: null, p1: null, p2: null }, // Stores {color, p1, p2}
        favoriteIdenticons: [], // Stores [{id, name, identicon_data: {color, p1, p2}}, ...]
        slotLimit: 5,
    };


    
    /**
     * initialize の初期化処理を行います。
     * @returns {Promise} 非同期処理の結果を表すPromise
     */
    async function initialize() {
        try {
            const response = await fetch('/api/user/get_identicon_settings');
            if (!response.ok) throw new Error('Failed to fetch settings');
            
            const settings = await response.json();
            state.activeIdenticonSeeds = parseSeedsString(settings.active_identicon) || { color: null, p1: null, p2: null };
            state.favoriteIdenticons = settings.favorite_identicons.map(item => ({
                id: item.id,
                name: item.name,
                identicon_data: parseSeedsString(item.identicon_data) // Parse each item's seeds string
            })) || [];
            state.slotLimit = settings.slot_limit || 5;

            render();
        } catch (error) {
            console.error('Initialization failed:', error);
        }
    }
    
    /**
     * render の画面表示を描画/更新します。
     * @returns {void}
     */
    function render() {
        window.IdenticonRenderer.createIdenticon(state.activeIdenticonSeeds, elements.currentCanvas);
        
        elements.favoritesList.innerHTML = '';
        if (state.favoriteIdenticons.length === 0) {
            elements.favoritesList.innerHTML = '<p class="text-muted w-100 text-center">お気に入りはまだありません。</p>';
        } else {
            state.favoriteIdenticons.forEach(favIcon => {
                const favDiv = document.createElement('div');
                favDiv.className = 'favorite-item text-center';
                favDiv.dataset.id = favIcon.id; // Store ID for easy access
                
                const canvas = document.createElement('canvas');
                canvas.width = 64;
                canvas.height = 64;
                canvas.className = 'identicon-canvas';
                canvas.style.cursor = 'pointer';
                window.IdenticonRenderer.createIdenticon(favIcon.identicon_data, canvas);
                
                const btnGroup = document.createElement('div');
                btnGroup.className = 'btn-group btn-group-sm mt-1';
                
                const setBtn = document.createElement('button');
                setBtn.className = 'btn btn-outline-primary';
                setBtn.title = 'これを設定';
                setBtn.innerHTML = '<i class="bi bi-check-circle"></i>';
                setBtn.onclick = () => handleSetActive(favIcon.identicon_data); // Use identicon_data object

                const deleteBtn = document.createElement('button');
                deleteBtn.className = 'btn btn-outline-danger';
                deleteBtn.title = '削除';
                deleteBtn.innerHTML = '<i class="bi bi-trash"></i>';
                deleteBtn.onclick = () => handleDelete(favIcon.id); // Use item ID

                btnGroup.appendChild(setBtn);
                btnGroup.appendChild(deleteBtn);
                favDiv.appendChild(canvas);
                favDiv.appendChild(btnGroup);
                elements.favoritesList.appendChild(favDiv);
            });
        }
        
        elements.favoriteCount.textContent = state.favoriteIdenticons.length;
        elements.favoriteLimit.textContent = state.slotLimit;
    }


    elements.generateBtn.addEventListener('click', async () => {
        try {
            const genResponse = await fetch('/api/user/generate_new_identicon_seeds', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
                body: JSON.stringify({ csrf_token: getCsrfToken() })
            }); // New API
            const genData = await genResponse.json();
            if (genData.error) throw new Error(genData.error || 'Failed to generate new seeds');
            const newSeeds = genData.seeds; // Expects {color, p1, p2}

            const setResponse = await fetch('/api/user/set_active_identicon', { // New API
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
                body: JSON.stringify({ identicon_data: newSeeds, csrf_token: getCsrfToken() }), // Send object
            });
            const setData = await setResponse.json();
            if (!setData.success) throw new Error(setData.message || 'Failed to set active identicon');

            state.activeIdenticonSeeds = parseSeedsString(setData.active_identicon); // Active returns string
            render();
            
        } catch (error) {
            console.error('Failed to generate and set new identicon:', error);
            alert('新しいアイコンの生成と設定に失敗しました。');
        }
    });

    /**
     * handleSave のイベント処理を行います。
     * @param {any} seeds_to_save 入力値
     * @returns {Promise} 非同期処理の結果を表すPromise
     */
    async function handleSave(seeds_to_save) { // Argument is seeds object
        if (!seeds_to_save || seeds_to_save.color === null) return;
        try {
            const response = await fetch('/api/user/save_favorite_identicon', { // New API
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
                body: JSON.stringify({ identicon_data: seeds_to_save, csrf_token: getCsrfToken() }), // Send object
            });
            const data = await response.json();

            if (response.status === 409 && data.error === 'favorites_full') {
                promptOverwrite(seeds_to_save);
            } else if (!data.success) {
                throw new Error(data.message || 'Failed to save favorite');
            } else {
                state.favoriteIdenticons = data.favorite_identicons.map(item => ({
                    id: item.id, name: item.name, identicon_data: parseSeedsString(item.identicon_data)
                }));
                render();
            }
        } catch (error) {
            console.error('Failed to save identicon:', error);
            alert('お気に入りの保存に失敗しました。');
        }
    }

    elements.saveCurrentBtn.addEventListener('click', () => handleSave(state.activeIdenticonSeeds));
    
    /**
     * handleSetActive のイベント処理を行います。
     * @param {any} seeds_to_set 入力値
     * @returns {Promise} 非同期処理の結果を表すPromise
     */
    async function handleSetActive(seeds_to_set) { // Argument is seeds object
        if (!seeds_to_set || seeds_to_set.color === null) return; // Basic validation
        if (state.activeIdenticonSeeds.color === seeds_to_set.color &&
            state.activeIdenticonSeeds.p1 === seeds_to_set.p1 &&
            state.activeIdenticonSeeds.p2 === seeds_to_set.p2) {
            return;
        }

        if (!confirm('現在のアイコンをこのお気に入りアイコンに設定しますか？')) {
            return;
        }

        try {
            const response = await fetch('/api/user/set_active_identicon', { // New API
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
                body: JSON.stringify({ identicon_data: seeds_to_set, csrf_token: getCsrfToken() }), // Send object
            });
            const data = await response.json();

            if (!data.success) throw new Error(data.message || 'Failed to set active identicon');

            state.activeIdenticonSeeds = parseSeedsString(data.active_identicon); // Active returns string
            render();
        } catch (error) {
            console.error('Failed to set active identicon:', error);
            alert('アイコンの設定に失敗しました。');
        }
    }
    
    /**
     * handleDelete のイベント処理を行います。
     * @param {any} identicon_id 入力値
     * @returns {Promise} 非同期処理の結果を表すPromise
     */
    async function handleDelete(identicon_id) { // Argument is item ID
        if (!identicon_id || !confirm('このお気に入りアイコンを削除しますか？')) return;
        try {
            const response = await fetch('/api/user/delete_favorite_identicon', { // New API
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
                body: JSON.stringify({ id: identicon_id, csrf_token: getCsrfToken() }), // Send item ID
            });
            const data = await response.json();

            if (!data.success) throw new Error(data.message || 'Failed to delete favorite');
            
            state.favoriteIdenticons = data.favorite_identicons.map(item => ({
                id: item.id, name: item.name, identicon_data: parseSeedsString(item.identicon_data)
            }));
            render();
        } catch (error) {
            console.error('Failed to delete identicon:', error);
            alert('お気に入りの削除に失敗しました。');
        }
    }
    
    /**
     * promptOverwrite の処理を行います。
     * @param {any} seeds_to_save 入力値
     * @returns {void}
     */
    function promptOverwrite(seeds_to_save) { // Argument is seeds object
        elements.overwriteOptions.innerHTML = '';
        state.favoriteIdenticons.forEach(favIcon => { // favIcon is full object
            const canvas = document.createElement('canvas');
            canvas.width = 64;
            canvas.height = 64;
            canvas.className = 'identicon-canvas';
            canvas.style.cursor = 'pointer';
            canvas.dataset.id = favIcon.id; // Store item ID
            window.IdenticonRenderer.createIdenticon(favIcon.identicon_data, canvas);
            elements.overwriteOptions.appendChild(canvas);
        });
        elements.overwriteModal.show();

        const overwriteSelectionHandler = async e => {
            const selectedCanvas = e.target.closest('.identicon-canvas');
            if (!selectedCanvas) return;
            
            const idToDelete = selectedCanvas.dataset.id; // Get item ID
            elements.overwriteModal.hide();

            try {
                const deleteResponse = await fetch('/api/user/delete_favorite_identicon', { // New API
                     method: 'POST',
                     headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
                     body: JSON.stringify({ id: idToDelete, csrf_token: getCsrfToken() }), // Send item ID
                });
                const deleteData = await deleteResponse.json();
                if (!deleteData.success) throw new Error('Failed to delete for overwrite');

                const saveResponse = await fetch('/api/user/save_favorite_identicon', { // New API
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
                    body: JSON.stringify({ identicon_data: seeds_to_save, csrf_token: getCsrfToken() }), // Send seeds object
                });
                const saveData = await saveResponse.json();
                if (!saveData.success) throw new Error('Failed to save after overwrite');

                state.favoriteIdenticons = saveData.favorite_identicons.map(item => ({
                    id: item.id, name: item.name, identicon_data: parseSeedsString(item.identicon_data)
                }));
                render();

            } catch (error) {
                console.error('Overwrite process failed:', error);
                alert('上書き保存に失敗しました。');
            } finally {
                 elements.overwriteOptions.removeEventListener('click', overwriteSelectionHandler);
            }
        };
        elements.overwriteOptions.addEventListener('click', overwriteSelectionHandler, { once: true });
    }

    document.addEventListener('DOMContentLoaded', initialize);

})();




