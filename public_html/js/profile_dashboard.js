/**
 * ファイル: public_html\js\profile_dashboard.js
 * 使い道: プロフィールページの統計表示とコンテンツ一覧の描画を行います。
 * 定義されている関数:
 *   - updateResultCount
 *   - escapeHTML
 *   - fetchUserProfile
 *   - fetchUserContents
 *   - updateFollowButton
 *   - updatePagination
 */


document.addEventListener('DOMContentLoaded', () => {
    const targetUserId = document.documentElement.dataset.targetUserId;
    const currentUserId = document.documentElement.dataset.currentUserId;

    const profileAvatar = document.getElementById('profile-avatar');
    const profileDisplayName = document.getElementById('profile-display-name');
    const profileBio = document.getElementById('profile-bio');
    const profilePostsCount = document.getElementById('profile-posts-count');
    const profileFollowersCount = document.getElementById('profile-followers-count');
    const profileAvgRating = document.getElementById('profile-avg-rating');
    const followButton = document.getElementById('follow-button');
    const followingList = document.getElementById('following-list');
    const followersList = document.getElementById('followers-list');
    const followingPagination = document.getElementById('following-pagination');
    const followersPagination = document.getElementById('followers-pagination');

    const contentTypeFilter = document.getElementById('content-type-filter');
    const contentSortOrder = document.getElementById('content-sort-order');
    const resultsTbody = document.getElementById('results-tbody');
    const paginationUl = document.getElementById('pagination-ul');
    const resultsCountText = document.getElementById('results-count-text');
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabPanels = document.querySelectorAll('.tab-panel');

    let currentPage = 1;
    let totalPages = 1;
    let followingPage = 1;
    let followersPage = 1;
    let followingTotalPages = 1;
    let followersTotalPages = 1;

    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            const targetId = button.dataset.target;
            tabButtons.forEach(btn => btn.classList.toggle('active', btn === button));
            tabPanels.forEach(panel => panel.classList.toggle('active', panel.id === targetId));
        });
    });

    // プロフィール情報の取得と表示
    /**
     * fetchUserProfile のデータを取得します。
     * @returns {Promise} 非同期処理の結果を表すPromise
     */
    const fetchUserProfile = async () => {
        try {
            const response = await fetch(`/api/user/get_user_profile?user_id=${targetUserId}`);
            const data = await response.json();

            if (data.error) {
                console.error('Error fetching profile:', data.error);
                return;
            }

            let seeds = { color: null, p1: null, p2: null };
            const activeIdenticonData = data.active_identicon; // This will be the comma-separated string
            if (activeIdenticonData) {
                const parts = activeIdenticonData.split(',').map(Number);
                if (parts.length === 3) {
                    seeds = { color: parts[0], p1: parts[1], p2: parts[2] };
                } else {
                    console.warn("Invalid active identicon format from API:", activeIdenticonData);
                    const defaultSeed = parseInt(targetUserId || 0);
                    seeds = { color: defaultSeed, p1: defaultSeed, p2: defaultSeed };
                }
            } else {
                const defaultSeed = parseInt(targetUserId || 0);
                seeds = { color: defaultSeed, p1: defaultSeed, p2: defaultSeed };
            }
            window.IdenticonRenderer.createIdenticon(seeds, profileAvatar);
            
            profileDisplayName.textContent = data.username;
            profileBio.textContent = data.bio;
            profilePostsCount.textContent = data.posts_count;
            profileFollowersCount.textContent = data.followers_count;
            profileAvgRating.textContent = data.avg_rating.toFixed(1);

            // フォローボタンの表示/非表示と状態設定
            if (targetUserId === currentUserId) {
                followButton.style.display = 'none';
            } else {
                followButton.style.display = 'block';
                updateFollowButton(data.is_following);
            }

        } catch (error) {
            console.error('Failed to fetch user profile:', error);
        }
    };

    // コンテンツ一覧の取得と表示
    /**
     * fetchUserContents のデータを取得します。
     * @returns {Promise} 非同期処理の結果を表すPromise
     */
    const fetchUserContents = async () => {
        const type = contentTypeFilter.value;
        const [sort, order] = contentSortOrder.value.split('_'); // 'updated_at_desc' -> ['updated_at', 'desc']

        try {
            const url = new URL('/api/content/search_contents', window.location.origin);
            url.searchParams.append('user_id', targetUserId);
            if (type !== 'all') url.searchParams.append('types', type);
            url.searchParams.append('sort', sort);
            url.searchParams.append('order', order);
            url.searchParams.append('page', currentPage);

            const response = await fetch(url);
            const data = await response.json();

            resultsTbody.innerHTML = '';

            if (data.error) {
                console.error('Error fetching contents:', data.error);
                resultsTbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">コンテンツの取得に失敗しました。</td></tr>';
                updatePagination(data.pagination?.total_count || 0, data.pagination?.total_pages || 1);
                updateResultCount(data.pagination || { total_count: 0, offset: 0 }, 0);
                return;
            }

            if (data.results.length === 0) {
                resultsTbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">コンテンツがありません。</td></tr>';
            } else {
                data.results.forEach(content => {
                    const row = document.createElement('tr');
                    const typeInfo = {
                        'ProblemSet': { text: '問題集', bg: 'bg-success' },
                        'Note': { text: 'ノート', bg: 'bg-primary' },
                        'FlashCard': { text: '単語帳', bg: 'bg-info' },
                    }[content.contentable_type] || { text: content.contentable_type, bg: 'bg-secondary' };
                    const detailUrl = {
                        'ProblemSet': '/read/problem.php?id=',
                        'Note': '/read/note.php?id=',
                        'FlashCard': '/read/flashcard.php?id=',
                    }[content.contentable_type] + content.id;
                    const rating = content.avg_rating ? parseFloat(content.avg_rating).toFixed(1) : 'N/A';
                    const updatedDate = new Date(content.updated_at).toLocaleDateString('ja-JP');

                    const isActive = content.is_active;
                    const activateButtonHtml = isActive
                        ? `<button class="btn btn-sm btn-success activate-btn" data-content-id="${content.id}" data-active="true" title="マイライブラリから削除"><i class="bi bi-check"></i></button>`
                        : `<button class="btn btn-sm btn-outline-primary activate-btn" data-content-id="${content.id}" data-active="false" title="マイライブラリに追加"><i class="bi bi-plus"></i></button>`;

                    row.innerHTML = `
                        <td>${activateButtonHtml}</td>
                        <td><span class="badge ${typeInfo.bg}">${escapeHTML(typeInfo.text)}</span></td>
                        <td><a href="${detailUrl}">${escapeHTML(content.title)}</a></td>
                        <td>${escapeHTML(content.lecture_name || content.lecture_id || '---')}</td>
                        <td><i class="bi bi-star-fill text-warning"></i> ${rating}</td>
                        <td>${updatedDate}</td>
                    `;
                    resultsTbody.appendChild(row);
                });
            }
            updatePagination(data.pagination.total_count, data.pagination.total_pages);
            updateResultCount(data.pagination, data.results.length);
            
        } catch (error) {
            console.error('Failed to fetch user contents:', error);
            resultsTbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">コンテンツの取得中にエラーが発生しました。</td></tr>';
            updatePagination(0, 1);
            updateResultCount({ total_count: 0, offset: 0 }, 0);
        }
    };

    /**
     * renderFollowList の画面表示を描画/更新します。
     * @param {HTMLElement} listEl 描画先の要素
     * @param {Array} users 入力値
     * @param {string} emptyText 表示文
     * @returns {void}
     */
    const renderFollowList = (listEl, users, emptyText) => {
        if (!listEl) return;
        if (!users || users.length === 0) {
            listEl.innerHTML = `<li class="text-muted">${escapeHTML(emptyText)}</li>`;
            return;
        }
        listEl.innerHTML = '';
        users.forEach(user => {
            const li = document.createElement('li');
            const userId = user.id;
            const userName = user.username || '名もなき猫';
            const identiconData = user.active_identicon || '';
            const isFollowing = Number(user.is_following) === 1;
            const canFollow = currentUserId && String(userId) !== String(currentUserId);
            const followLabel = isFollowing ? 'フォロー中' : 'フォローする';
            const followClass = isFollowing ? 'btn-unfollow' : 'btn-follow';
            const followButtonHtml = canFollow
                ? `<button class="btn btn-sm ${followClass} follow-toggle-btn" data-user-id="${userId}" data-following="${isFollowing ? 'true' : 'false'}">${followLabel}</button>`
                : '';
            li.innerHTML = `
                <canvas class="follow-avatar" width="32" height="32" data-identicon="${escapeHTML(identiconData)}" data-user-id="${userId}"></canvas>
                <a href="/profile.php?user_id=${userId}">${escapeHTML(userName)}</a>
                <span class="follow-actions">${followButtonHtml}</span>
            `;
            listEl.appendChild(li);
        });
        renderFollowIdenticons(listEl);
    };

    /**
     * renderFollowIdenticons のidenticonを描画します。
     * @param {HTMLElement} listEl 表示先の要素
     * @returns {void}
     */
    const renderFollowIdenticons = (listEl) => {
        if (!window.IdenticonRenderer) return;
        const canvases = listEl.querySelectorAll('.follow-avatar');
        canvases.forEach(canvas => {
            const identiconData = canvas.dataset.identicon || '';
            const userId = parseInt(canvas.dataset.userId || '0', 10);
            const seeds = parseIdenticonSeeds(identiconData, userId);
            window.IdenticonRenderer.createIdenticon(seeds, canvas);
        });
    };

    /**
     * parseIdenticonSeeds の文字列をシードに変換します。
     * @param {string} identiconData 入力値
     * @param {number} fallbackSeed 入力値
     * @returns {any}
     */
    const parseIdenticonSeeds = (identiconData, fallbackSeed) => {
        if (identiconData) {
            const parts = identiconData.split(',').map(Number);
            if (parts.length === 3 && parts.every(Number.isFinite)) {
                return { color: parts[0], p1: parts[1], p2: parts[2] };
            }
        }
        return { color: fallbackSeed, p1: fallbackSeed, p2: fallbackSeed };
    };

    /**
     * fetchFollowLists のデータを取得します。
     * @returns {Promise} 非同期処理の結果を表すPromise
     */
    const fetchFollowLists = async () => {
        if (!followingList || !followersList) return;
        try {
            const url = new URL('/api/user/get_follow_lists', window.location.origin);
            url.searchParams.append('user_id', targetUserId);
            url.searchParams.append('following_page', followingPage);
            url.searchParams.append('followers_page', followersPage);
            const response = await fetch(url);
            const data = await response.json();
            if (data.error) {
                console.error('Error fetching follow lists:', data.error);
                renderFollowList(followingList, [], 'フォロー中のユーザーがいません。');
                renderFollowList(followersList, [], 'フォロワーがいません。');
                return;
            }
            renderFollowList(followingList, data.following || [], 'フォロー中のユーザーがいません。');
            renderFollowList(followersList, data.followers || [], 'フォロワーがいません。');
            renderFollowPagination(followingPagination, data.following_pagination, 'following');
            renderFollowPagination(followersPagination, data.followers_pagination, 'followers');
        } catch (error) {
            console.error('Failed to fetch follow lists:', error);
            renderFollowList(followingList, [], 'フォロー中のユーザーがいません。');
            renderFollowList(followersList, [], 'フォロワーがいません。');
        }
    };

    /**
     * renderFollowPagination の状態や表示を更新します。
     * @param {HTMLElement} paginationEl 表示先の要素
     * @param {any} pagination 入力値
     * @param {string} listType following | followers
     * @returns {void}
     */
    const renderFollowPagination = (paginationEl, pagination, listType) => {
        if (!paginationEl) return;
        const totalPagesForList = pagination?.total_pages || 1;
        const currentPageForList = pagination?.current_page || 1;
        if (listType === 'following') {
            followingTotalPages = totalPagesForList;
            followingPage = currentPageForList;
        } else {
            followersTotalPages = totalPagesForList;
            followersPage = currentPageForList;
        }
        paginationEl.innerHTML = '';
        if (totalPagesForList <= 1) return;

        paginationEl.insertAdjacentHTML('beforeend', `
            <li class="page-item ${currentPageForList === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-list="${listType}" data-page="${currentPageForList - 1}">前へ</a>
            </li>
        `);
        for (let i = 1; i <= totalPagesForList; i++) {
            paginationEl.insertAdjacentHTML('beforeend', `
                <li class="page-item ${i === currentPageForList ? 'active' : ''}">
                    <a class="page-link" href="#" data-list="${listType}" data-page="${i}">${i}</a>
                </li>
            `);
        }
        paginationEl.insertAdjacentHTML('beforeend', `
            <li class="page-item ${currentPageForList === totalPagesForList ? 'disabled' : ''}">
                <a class="page-link" href="#" data-list="${listType}" data-page="${currentPageForList + 1}">次へ</a>
            </li>
        `);
    };

    // フォローボタンの状態更新
    /**
     * updateFollowButton の状態や表示を更新します。
     * @param {any} isFollowing 入力値
     * @returns {void}
     */
    const updateFollowButton = (isFollowing) => {
        if (isFollowing) {
            followButton.textContent = 'フォロー中';
            followButton.classList.remove('btn-follow');
            followButton.classList.add('btn-unfollow');
            followButton.dataset.following = 'true';
        } else {
            followButton.textContent = 'フォローする';
            followButton.classList.remove('btn-unfollow');
            followButton.classList.add('btn-follow');
            followButton.dataset.following = 'false';
        }
    };

    // フォロー/アンフォロー処理
    followButton.addEventListener('click', async () => {
        try {
            const isFollowing = followButton.dataset.following === 'true';
            const response = await fetch('/api/user/toggle_follow', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCsrfToken()
                },
                body: JSON.stringify({ target_user_id: targetUserId, csrf_token: getCsrfToken() })
            });
            const data = await response.json();

            if (data.error) {
                console.error('Error toggling follow:', data.error);
                alert('フォロー状態の変更に失敗しました: ' + data.error);
                return;
            }

            // フォロー数を更新（ここでは簡略化のため再フェッチ）
            fetchUserProfile();
            fetchFollowLists();
            updateFollowButton(data.is_following);
            
        } catch (error) {
            console.error('Failed to toggle follow:', error);
            alert('フォロー状態の変更中にエラーが発生しました。');
        }
    });

    // ページネーションの更新
    /**
     * updatePagination の状態や表示を更新します。
     * @param {any} totalCount 入力値
     * @param {any} totalPagesData 入力値
     * @returns {void}
     */
    const updatePagination = (totalCount, totalPagesData) => {
        totalPages = totalPagesData;
        paginationUl.innerHTML = '';
        if (totalPages <= 1) return;

        paginationUl.insertAdjacentHTML('beforeend', `
            <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${currentPage - 1}">前へ</a>
            </li>
        `);
        for (let i = 1; i <= totalPages; i++) {
            paginationUl.insertAdjacentHTML('beforeend', `
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `);
        }
        paginationUl.insertAdjacentHTML('beforeend', `
            <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${currentPage + 1}">次へ</a>
            </li>
        `);
    };

    /**
     * updateResultCount の状態や表示を更新します。
     * @param {any} pagination 入力値
     * @param {any} displayCount 入力値
     * @returns {void}
     */
    function updateResultCount(pagination, displayCount) {
        if (!resultsCountText) return;
        const total = pagination?.total_count || 0;
        const offset = pagination?.offset || 0;
        if (total > 0) {
            resultsCountText.textContent = `全 ${total} 件中 ${offset + 1} - ${offset + displayCount} 件を表示`;
        } else {
            resultsCountText.textContent = '全 0 件';
        }
    }

    // フィルター、ソート、検索のイベントリスナー
    contentTypeFilter.addEventListener('change', () => { currentPage = 1; fetchUserContents(); });
    contentSortOrder.addEventListener('change', () => { currentPage = 1; fetchUserContents(); });
        paginationUl.addEventListener('click', (event) => {
            const link = event.target.closest('.page-link');
            if (!link) return;
            event.preventDefault();
            const page = Number(link.dataset.page);
            if (!page || page < 1 || page > totalPages) return;
            currentPage = page;
            fetchUserContents();
        });
    if (followingPagination) {
        followingPagination.addEventListener('click', (event) => {
            const link = event.target.closest('.page-link');
            if (!link) return;
            event.preventDefault();
            const page = Number(link.dataset.page);
            if (!page || page < 1 || page > followingTotalPages) return;
            followingPage = page;
            fetchFollowLists();
        });
    }
    if (followersPagination) {
        followersPagination.addEventListener('click', (event) => {
            const link = event.target.closest('.page-link');
            if (!link) return;
            event.preventDefault();
            const page = Number(link.dataset.page);
            if (!page || page < 1 || page > followersTotalPages) return;
            followersPage = page;
            fetchFollowLists();
        });
    }

    const handleFollowToggleClick = async (event) => {
        const button = event.target.closest('.follow-toggle-btn');
        if (!button) return;
        event.preventDefault();
        const targetId = button.dataset.userId;
        try {
            const response = await fetch('/api/user/toggle_follow', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCsrfToken()
                },
                body: JSON.stringify({ target_user_id: targetId, csrf_token: getCsrfToken() })
            });
            const data = await response.json();
            if (data.error) {
                alert('フォロー状態の変更に失敗しました: ' + data.error);
                return;
            }
            fetchFollowLists();
        } catch (error) {
            console.error('Failed to toggle follow:', error);
            alert('フォロー状態の変更中にエラーが発生しました。');
        }
    };

    if (followingList) {
        followingList.addEventListener('click', handleFollowToggleClick);
    }
    if (followersList) {
        followersList.addEventListener('click', handleFollowToggleClick);
    }

    /**
     * escapeHTML の文字列をエスケープします。
     * @param {any} str 入力値
     * @returns {void}
     */
    function escapeHTML(str) {
        if (str === null || typeof str === 'undefined') return '';
        return String(str).replace(/[&<>"']/g, match => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[match]));
    }

    // 初期データの読み込み
    fetchUserProfile();
    fetchUserContents();
    fetchFollowLists();
});




