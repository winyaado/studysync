/**
 * ファイル: public_html\js\parts\activate_button.js
 * 使い道: ライブラリ追加/削除ボタンの動作を制御します。
 * 定義されている関数:
 *   - initializeActivateButtons
 *   - updateButtonAppearance
 */



function initializeActivateButtons() {
    document.addEventListener('click', async function(event) {
        const activateButton = event.target.closest('.activate-btn');
        if (!activateButton || activateButton.dataset.loading === 'true') {
            return;
        }

        const contentId = activateButton.dataset.contentId;
        const isActive = activateButton.dataset.active === 'true';
        const action = isActive ? 'remove' : 'add';

        if (!contentId) {
            console.error('Activate button is missing data-content-id.');
            return;
        }

        activateButton.dataset.loading = 'true';
        activateButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
        
        const formData = new FormData();
        formData.append('content_id', contentId);
        formData.append('action', action);
        formData.append('csrf_token', getCsrfToken());

        try {
            const response = await fetch('/api/user/toggle_library_content', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.error || 'ライブラリの更新に失敗しました。');
            }

            const result = await response.json();
            if (result.success) {
                const newActiveState = !isActive;
                activateButton.dataset.active = newActiveState;
                updateButtonAppearance(activateButton, newActiveState);
            } else {
                throw new Error(result.message || 'ライブラリの更新に失敗しました。');
            }
        } catch (error) {
            console.error('Error toggling library content:', error);
            updateButtonAppearance(activateButton, isActive); // Revert to original state on error
        } finally {
            activateButton.dataset.loading = 'false';
        }
    });
}

/**
 * updateButtonAppearance の状態や表示を更新します。
 * @param {any} button 入力値
 * @param {any} isActive 入力値
 * @returns {void}
 */
function updateButtonAppearance(button, isActive) {
    const isSearchPage = document.getElementById('results-tbody') !== null;
    
    let btnClass, btnIcon, btnText, btnTitle;

    if (isActive) {
        btnClass = 'btn-success';
        btnIcon = 'bi-check';
        btnTitle = 'マイライブラリから削除';
        if (isSearchPage) {
            btnText = '';
        } else {
            btnText = ' 追加済み';
        }
    } else {
        btnClass = 'btn-outline-primary';
        btnIcon = 'bi-plus';
        btnTitle = 'マイライブラリに追加';
        if (isSearchPage) {
            btnText = '';
        } else {
            btnText = ' マイライブラリに追加';
        }
    }

    button.innerHTML = `<i class="bi ${btnIcon}"></i>${btnText}`;
    button.title = btnTitle;
    button.className = `btn btn-sm activate-btn ${btnClass}`;
}


initializeActivateButtons();




