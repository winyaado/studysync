/**
 * ファイル: public_html\js\my_library_page.js
 * 使い道: マイライブラリ画面の一覧表示を行います。
 * 定義されている関数:
 *   - escapeHTML
 *   - formatContentType
 *   - fetchAndRenderLibrary
 */



document.addEventListener('DOMContentLoaded', async function() {
    const libraryContentDiv = document.getElementById('library-content');
    const loadingStateDiv = document.getElementById('loading-state');
    const studyConfigModal = new bootstrap.Modal(document.getElementById('studyConfigModal'));
    const startStudyButton = document.getElementById('startStudyButton');

    let selectedFlashcardIds = []; // Stores content_ids of flashcards selected for combined study

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
     * formatContentType の値を整形します。
     * @param {any} type 入力値
     * @returns {void}
     */
    function formatContentType(type) {
        const map = {
            'Note': { text: 'ノート', bg: 'bg-primary' },
            'ProblemSet': { text: '問題集', bg: 'bg-success' },
            'FlashCard': { text: '単語帳', bg: 'bg-info' },
        };
        const info = map[type] || { text: type, bg: 'bg-secondary' }; // Fallback
        return `<span class="badge ${info.bg}">${escapeHTML(info.text)}</span>`;
    }

    /**
     * fetchAndRenderLibrary の画面表示を描画/更新します。
     * @returns {Promise} 非同期処理の結果を表すPromise
     */
    async function fetchAndRenderLibrary() {
        if (loadingStateDiv) loadingStateDiv.style.display = 'block';
        libraryContentDiv.innerHTML = ''; // Clear previous content

        try {
            const response = await fetch('/api/user/get_library_contents');
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.error || 'マイライブラリの取得に失敗しました。');
            }
            const data = await response.json();

            if (!data.library || Object.keys(data.library).length === 0) {
                libraryContentDiv.innerHTML = '<div class="alert alert-info">マイライブラリにはまだコンテンツがありません。<a href="/search.php">コンテンツ検索</a>から追加してください。</div>';
                return;
            }

            let html = '';
            let allFlashcardIds = [];

            html += `
                <div class="mb-4">
                    <button class="btn btn-primary btn-lg w-100 study-combined-btn" data-scope="global">
                        <i class="bi bi-play-circle me-2"></i>すべての単語帳から横断学習する
                    </button>
                </div>
            `;

            for (const lectureId in data.library) {
                const lectureGroup = data.library[lectureId];
                const flashcardsInLecture = lectureGroup.contents.filter(c => c.contentable_type === 'FlashCard');
                const flashcardIdsInLecture = flashcardsInLecture.map(c => c.id);
                allFlashcardIds = allFlashcardIds.concat(flashcardIdsInLecture);

                html += `
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">${escapeHTML(lectureGroup.lecture_name)}</h5>
                            ${flashcardsInLecture.length > 0 ? `
                                <button class="btn btn-sm btn-outline-primary study-combined-btn" data-scope="lecture" data-lecture-id="${lectureId}" data-content-ids="${flashcardIdsInLecture.join(',')}">
                                    <i class="bi bi-play-circle me-2"></i>この講義の単語帳で横断学習する
                                </button>
                            ` : ''}
                        </div>
                        <ul class="list-group list-group-flush">
                `;
                lectureGroup.contents.forEach(content => {
                    const detailUrl = (content.contentable_type === 'Note' ? '/read/note.php?id=' :
                                      (content.contentable_type === 'ProblemSet' ? '/read/problem.php?id=' :
                                      (content.contentable_type === 'FlashCard' ? '/read/flashcard.php?id=' : '#'))) + content.id;
                    html += `
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                ${formatContentType(content.contentable_type)}
                                <a href="${detailUrl}" class="ms-2 text-decoration-none">${escapeHTML(content.title)}</a>
                            </div>
                            <small class="text-muted">${content.updated_at.split(' ')[0]}</small>
                        </li>
                    `;
                });
                html += `
                        </ul>
                    </div>
                `;
            }
            libraryContentDiv.innerHTML = html;

            const globalStudyBtn = libraryContentDiv.querySelector('.study-combined-btn[data-scope="global"]');
            if (globalStudyBtn) {
                globalStudyBtn.dataset.contentIds = allFlashcardIds.join(',');
                globalStudyBtn.disabled = allFlashcardIds.length === 0;
            }


        } catch (error) {
            libraryContentDiv.innerHTML = `<div class="alert alert-danger">エラー: ${escapeHTML(error.message)}</div>`;
        } finally {
            if (loadingStateDiv) loadingStateDiv.style.display = 'none';
        }
    }

    libraryContentDiv.addEventListener('click', function(event) {
        const studyBtn = event.target.closest('.study-combined-btn');
        if (studyBtn) {
            const contentIds = studyBtn.dataset.contentIds;
            if (contentIds) {
                selectedFlashcardIds = contentIds.split(',').map(Number); // Store for modal
                studyConfigModal.show();
            } else {
                if (studyBtn.dataset.scope === 'global') {
                    const globalIds = []; // This would require another API call or storing the IDs globaly
                    if (allFlashcardIds.length > 0) {
                        selectedFlashcardIds = allFlashcardIds;
                        studyConfigModal.show();
                    } else {
                        alert('学習対象の単語帳がありません。');
                    }
                }
            }
        }
    });

    startStudyButton.addEventListener('click', function() {
        const selectedFilter = document.querySelector('input[name="studyFilter"]:checked').value;
        if (selectedFlashcardIds.length > 0) {
            window.location.href = `/read/flashcard_study.php?ids=${selectedFlashcardIds.join(',')}&filter=${selectedFilter}`;
        } else {
            alert('学習する単語帳が選択されていません。');
        }
        studyConfigModal.hide();
    });


    fetchAndRenderLibrary();
});




