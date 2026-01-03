/**
 * ファイル: public_html\js\search.js
 * 使い道: 検索画面の条件反映と結果テーブル描画を行います。
 * 定義されている関数:
 *   - performSearch
 *   - renderResults
 *   - renderPagination
 *   - updateResultCount
 *   - updateSortHeaders
 *   - escapeHtml
 */


$(document).ready(async function() {
    
    const typeDisplayMap = initialSearchData.typeDisplayMap;
    const typeLinkMap = initialSearchData.typeLinkMap;
    let currentState = initialSearchData.currentState;

    const $lectureSelector = $('#lecture_id');
    await initializeLectureSelector('#lecture_id', '#quarter-filter');


    /**
     * performSearch の処理を行います。
     * @param {any} page 入力値
     * @returns {void}
     */
    function performSearch(page = 1) {
        currentState.page = page;
        
        const selectedLectureData = $lectureSelector.select2('data');
        if (selectedLectureData && selectedLectureData.length > 0 && selectedLectureData[0].id) {
            currentState.lecture_name = selectedLectureData[0].text;
        } else {
            delete currentState.lecture_name; // Remove if nothing is selected
        }

        const paramsForUrl = { ...currentState };
        if (paramsForUrl.lecture_id === null || typeof paramsForUrl.lecture_id === 'undefined' || paramsForUrl.lecture_id === '') {
            delete paramsForUrl.lecture_id;
        }
        if (!paramsForUrl.follow_only) {
            delete paramsForUrl.follow_only;
        }
        if (!paramsForUrl.lecture_id) {
            delete paramsForUrl.lecture_name;
        }

        const apiUrl = '/api/content/search_contents?' + new URLSearchParams(paramsForUrl);
        
        $('#results-container').addClass('loading');
        
        const browserUrl = '/search.php?' + new URLSearchParams(paramsForUrl);
        window.history.pushState(paramsForUrl, '', browserUrl);

        fetch(apiUrl)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                renderResults(data.results);
                renderPagination(data.pagination);
                updateResultCount(data.pagination, data.results.length);
                updateSortHeaders(data.request_params.sort, data.request_params.order);
            })
            .catch(error => {
                console.error('Search failed:', error);
                $('#results-tbody').html('<tr><td colspan="7" class="text-center text-danger">検索中にエラーが発生しました。</td></tr>');
            })
            .finally(() => {
                $('#results-container').removeClass('loading');
            });
    }

    /**
     * renderResults の画面表示を描画/更新します。
     * @param {any} results 入力値
     * @returns {void}
     */
    function renderResults(results) {
        const tbody = $('#results-tbody');
        tbody.empty();
        if (!results || results.length === 0) {
            tbody.html('<tr><td colspan="7" class="text-center text-muted">検索結果が見つかりませんでした。</td></tr>');
            return;
        }

        results.forEach(row => {
            const typeInfo = typeDisplayMap[row.contentable_type] || {bg: 'bg-secondary', text: row.contentable_type};
            const detailUrl = (typeLinkMap[row.contentable_type] || '#') + row.id;
            const rating = row.avg_rating ? parseFloat(row.avg_rating).toFixed(1) : 'N/A';
            const updatedDate = new Date(row.updated_at).toLocaleDateString('ja-JP');

            const isActive = row.is_active;
            let activateButtonHtml;
            if (isActive) {
                activateButtonHtml = `
                    <button class="btn btn-sm btn-success activate-btn" data-content-id="${row.id}" data-active="true" title="マイライブラリから削除">
                        <i class="bi bi-check"></i>
                    </button>
                `;
            } else {
                activateButtonHtml = `
                    <button class="btn btn-sm btn-outline-primary activate-btn" data-content-id="${row.id}" data-active="false" title="マイライブラリに追加">
                        <i class="bi bi-plus"></i>
                    </button>
                `;
            }

            const authorLink = row.author_user_id ? `/profile.php?user_id=${row.author_user_id}` : '#';
            const authorHtml = row.author_user_id
                ? `<a href="${authorLink}">${escapeHtml(row.author_name)}</a>`
                : `${escapeHtml(row.author_name)}`;

            const tr = `
                <tr>
                    <td>${activateButtonHtml}</td>
                    <td><span class="badge ${typeInfo.bg}">${escapeHtml(typeInfo.text)}</span></td>
                    <td><a href="${detailUrl}">${escapeHtml(row.title)}</a></td>
                    <td>${escapeHtml(row.lecture_name || row.lecture_id || '---')}</td>
                    <td>${authorHtml}</td>
                    <td><i class="bi bi-star-fill text-warning"></i> ${rating}</td>
                    <td>${updatedDate}</td>
                </tr>
            `;
            tbody.append(tr);
        });
    }

    /**
     * renderPagination の画面表示を描画/更新します。
     * @param {any} pagination 入力値
     * @returns {void}
     */
    function renderPagination(pagination) {
        const { total_pages, current_page } = pagination;
        const ul = $('#pagination-ul');
        ul.empty();

        if (total_pages <= 1) return;

        ul.append(`
            <li class="page-item ${current_page === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${current_page - 1}">前へ</a>
            </li>
        `);

        for (let i = 1; i <= total_pages; i++) {
            ul.append(`
                <li class="page-item ${i === current_page ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `);
        }

        ul.append(`
            <li class="page-item ${current_page === total_pages ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${current_page + 1}">次へ</a>
            </li>
        `);
    }

    /**
     * updateResultCount の状態や表示を更新します。
     * @param {any} pagination 入力値
     * @param {any} displayCount 入力値
     * @returns {void}
     */
    function updateResultCount(pagination, displayCount) {
        const { total_count, offset } = pagination;
        if (total_count > 0) {
            $('#results-count-text').text(`全 ${total_count} 件中 ${offset + 1} - ${offset + displayCount} 件を表示`);
        } else {
            $('#results-count-text').text('全 0 件');
        }
    }
    
    /**
     * updateSortHeaders の状態や表示を更新します。
     * @param {any} currentSort 入力値
     * @param {any} currentOrder 入力値
     * @returns {void}
     */
    function updateSortHeaders(currentSort, currentOrder) {
        $('.sort-link').each(function() {
            const link = $(this);
            const sortName = link.data('sort');
            
            let iconClass = 'bi-arrow-down-up small text-muted';
            let nextOrder = 'desc';

            if (sortName === currentSort) {
                if (currentOrder === 'desc') {
                    iconClass = 'bi-sort-down';
                    nextOrder = 'asc';
                } else {
                    iconClass = 'bi-sort-up';
                    nextOrder = 'desc';
                }
            }
            link.data('order', nextOrder);
            link.find('i').attr('class', 'bi ' + iconClass);
        });
    }

    /**
     * escapeHtml の文字列をエスケープします。
     * @param {any} str 入力値
     * @returns {void}
     */
    function escapeHtml(str) {
        if (str === null || typeof str === 'undefined') return '';
        return str.toString()
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
    
    $('#search-form').on('submit', function(e) {
        e.preventDefault();
        currentState.q = $('#searchQuery').val();
        currentState.lecture_id = $lectureSelector.val();
        currentState.types = $('input[name="types[]"]:checked').map(function() { return this.value; }).get();
        currentState.follow_only = $('#followOnly').is(':checked') ? '1' : '';
        performSearch(1);
    });

    $('#pagination-ul').on('click', '.page-link', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        if (page && !$(this).parent().hasClass('disabled')) {
            performSearch(page);
        }
    });

    $('.table').on('click', '.sort-link', function(e) {
        e.preventDefault();
        const newSort = $(this).data('sort');
        const newOrder = $(this).data('order');
        currentState.sort = newSort;
        currentState.order = newOrder;
        performSearch(1);
    });
    
    performSearch(currentState.page);
});




