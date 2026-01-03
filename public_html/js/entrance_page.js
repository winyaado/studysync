/**
 * ファイル: public_html\js\entrance_page.js
 * 使い道: トップページのお知らせ取得と表示を行います。
 * 定義されている関数:
 *   - escapeHTML
 *   - formatCategory
 *   - formatDateTime
 *   - fetchAndRenderInformations
 */


document.addEventListener('DOMContentLoaded', async function() {
    const informationsDisplayArea = document.getElementById('informations-display-area');
    
    const hiddenQuill = new Quill('#hidden-converter');

    /**
     * escapeHTML の文字列をエスケープします。
     * @param {any} str 入力値
     * @returns {void}
     */
    function escapeHTML(str) {
        if (str === null || typeof str === 'undefined') return '';
        return String(str).replace(/[&<>"']/g, match => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[match]);
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
     * fetchAndRenderInformations の画面表示を描画/更新します。
     * @returns {Promise} 非同期処理の結果を表すPromise
     */
    async function fetchAndRenderInformations() {
        try {
            const response = await fetch('/api/system/get_active_informations');
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.error || 'お知らせの取得に失敗しました。');
            }
            const data = await response.json();

            informationsDisplayArea.innerHTML = ''; // Clear loading message

            if (data.informations.length === 0) {
                informationsDisplayArea.innerHTML = '<p class="text-center text-muted">現在、お知らせはありません。</p>';
                return;
            }

            data.informations.forEach(info => {
                let noteHtml = '';
                try {
                    const noteDelta = JSON.parse(info.content);
                    hiddenQuill.setContents(noteDelta);
                    noteHtml = hiddenQuill.root.innerHTML;
                } catch (e) {
                    console.error('Error parsing Delta content for info ID:', info.id, e);
                    noteHtml = '<p class="text-danger">コンテンツの表示に失敗しました。</p>';
                }

                const sanitizedHtml = DOMPurify.sanitize(noteHtml);

                const infoCard = `
                    <div class="list-group-item list-group-item-action mb-2">
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1">${escapeHTML(info.title)}</h5>
                            <small>${formatCategory(info.category)}</small>
                        </div>
                        <p class="mb-1">
                            ${sanitizedHtml}
                        </p>
                        <small class="text-muted">表示期間: ${formatDateTime(info.display_from)} - ${formatDateTime(info.display_to)}</small>
                    </div>
                `;
                informationsDisplayArea.insertAdjacentHTML('beforeend', infoCard);
            });

        } catch (error) {
            console.error('Error fetching active informations:', error);
            informationsDisplayArea.innerHTML = `<div class='alert alert-danger'>エラー: ${escapeHTML(error.message)}</div>`;
        }
    }

    fetchAndRenderInformations();
});




