/**
 * ファイル: public_html\js\my_content_page.js
 * 使い道: 作成コンテンツ一覧の表示と削除を行います。
 * 定義されている関数:
 *   - fetchAndRenderContents
 *   - handleDeleteClick
 *   - formatVisibility
 *   - escapeHTML
 *   - formatDate
 */


document.addEventListener('DOMContentLoaded', function() {
    const listBody = document.getElementById('my-contents-list');

    const typeDisplayMap = {
        'ProblemSet': { text: '問題集', bg: 'bg-success' },
        'Note': { text: 'ノート', bg: 'bg-primary' },
        'FlashCard': { text: '単語帳', bg: 'bg-info' }, // Added FlashCard
    };
    const typeEditLinkMap = {
        'ProblemSet': '/write/problem_set.php?id=',
        'Note': '/write/note.php?id=',
        'FlashCard': '/write/flashcard.php?id=', // Added FlashCard
    };

    /**
     * fetchAndRenderContents の画面表示を描画/更新します。
     * @returns {void}
     */
    function fetchAndRenderContents() {
        fetch('/api/content/my_contents')
            .then(response => {
                if (!response.ok) {
                    throw new Error('コンテンツの取得に失敗しました。');
                }
                return response.json();
            })
            .then(data => {
                const { contents, usage } = data;

                const usageDisplay = document.getElementById('content-usage-display');
                if (usageDisplay && usage) {
                    usageDisplay.textContent = `作成済み: ${usage.count} / ${usage.limit}`;
                }

                listBody.innerHTML = ''; // Clear loading/previous content
                if (contents.length === 0) {
                    listBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">まだ作成したコンテンツがありません。</td></tr>';
                    return;
                }

                contents.forEach(content => {
                    const typeInfo = typeDisplayMap[content.contentable_type] || { text: content.contentable_type, bg: 'bg-secondary' };
                    const editLink = typeEditLinkMap[content.contentable_type] || '#';
                    const isEditDisabled = editLink === '#';
                    const lectureDisplay = escapeHTML(content.lecture_name || content.lecture_id || '---');

                    const row = document.createElement('tr');
                    row.dataset.id = content.id;
                    row.innerHTML = `
                        <td><span class="badge ${typeInfo.bg}">${escapeHTML(typeInfo.text)}</span></td>
                        <td>${escapeHTML(content.title)}</td>
                        <td>${lectureDisplay}</td>
                        <td><span class="badge bg-light text-dark border">${escapeHTML(content.status)}</span></td>
                        <td>${formatVisibility(content.visibility)}</td> <!-- New line for visibility -->
                        <td>${formatDate(content.updated_at)}</td>
                        <td>
                            <a href="${editLink}${content.id}" class="btn btn-sm btn-outline-primary ${isEditDisabled ? 'disabled' : ''}" title="編集">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <button class="btn btn-sm btn-outline-danger delete-btn" data-id="${content.id}" title="削除">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    `;
                    listBody.appendChild(row);
                });
            })
            .catch(error => {
                listBody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">${escapeHTML(error.message)}</td></tr>`;
            });
    }

    /**
     * handleDeleteClick のイベント処理を行います。
     * @param {any} event 入力値
     * @returns {void}
     */
    function handleDeleteClick(event) {
        const deleteButton = event.target.closest('.delete-btn');
        if (!deleteButton) {
            return;
        }

        const contentId = deleteButton.dataset.id;
        const rowToDelete = deleteButton.closest('tr');
        const title = rowToDelete.querySelector('td:nth-child(2)').textContent;

        if (confirm(`「${title}」を本当に削除しますか？この操作は元に戻せません。`)) {
            const formData = new FormData();
            formData.append('id', contentId);
            formData.append('csrf_token', getCsrfToken());

            fetch('/api/content/delete_content', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => { throw new Error(err.error || '削除に失敗しました。') });
                }
                return response.json();
            })
            .then(result => {
                if (result.success) {
                    rowToDelete.style.transition = 'opacity 0.5s ease';
                    rowToDelete.style.opacity = '0';
                    setTimeout(() => {
                        rowToDelete.remove();
                        if (listBody.children.length === 0) {
                            listBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">まだ作成したコンテンツがありません。</td></tr>';
                        }
                    }, 500);
                } else {
                    alert('エラー: ' + result.message);
                }
            })
            .catch(error => {
                alert('エラー: ' + error.message);
            });
        }
    }

    listBody.addEventListener('click', handleDeleteClick);
    
    /**
     * formatVisibility の値を整形します。
     * @param {any} visibility 入力値
     * @returns {void}
     */
    function formatVisibility(visibility) {
        const map = {
            'private': { text: '非公開', bg: 'bg-secondary' },
            'domain': { text: 'ドメイン内公開', bg: 'bg-info' },
            'public': { text: '全体に公開', bg: 'bg-success' },
        };
        const info = map[visibility] || { text: visibility, bg: 'bg-warning' }; // Fallback
        return `<span class="badge ${info.bg}">${escapeHTML(info.text)}</span>`;
    }

    /**
     * escapeHTML の文字列をエスケープします。
     * @param {any} str 入力値
     * @returns {void}
     */
    function escapeHTML(str) {
        if (str === null || typeof str === 'undefined') {
            return '';
        }
        return String(str).replace(/[&<>"']/g, function(match) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[match];
        });
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

    fetchAndRenderContents();
});




