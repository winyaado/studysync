/**
 * ファイル: public_html\js\flashcard_study.js
 * 使い道: 単語帳学習画面の表示と操作を制御します。
 * 定義されている関数:
 *   - escapeHTML
 *   - formatVisibility
 *   - displayGlobalMessage
 *   - updateCardView
 *   - updateCardBorder
 *   - nextCard
 *   - prevCard
 *   - setMemoryLevel
 *   - disableButtons
 *   - handleSwipe
 *   - initializeEventListeners
 *   - fetchStudyData
 *   - renderStudyPage
 */


document.addEventListener('DOMContentLoaded', () => {
    const mainContent = document.getElementById('main-content');
    
    let studyDeck = [];
    let currentIndex = 0;
    
    let touchstartX = 0;
    let touchstartY = 0;
    let touchendX = 0;
    let touchendY = 0;
    const swipeThreshold = 50;

    let flashcard, cardFront, cardBack, cardCounter, prevBtn, nextBtn;

    /**
     * escapeHTML の文字列をエスケープします。
     * @param {any} str 入力値
     * @returns {void}
     */
    function escapeHTML(str) {
        if (typeof str !== 'string' && typeof str !== 'number') return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        };
        return String(str).replace(/[&<>"']/g, (m) => map[m] || '');
    }
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
        const info = map[visibility] || { text: visibility, bg: 'bg-warning' };
        return `<span class="badge ${info.bg} ml-2">${escapeHTML(info.text)}</span>`;
    }
    /**
     * displayGlobalMessage の処理を行います。
     * @param {any} message 入力値
     * @param {any} type 入力値
     * @returns {void}
     */
    function displayGlobalMessage(message, type = 'success') {
        const container = document.getElementById('global-message-container');
        if(!container) return;
        container.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">${escapeHTML(message)}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>`;
    }


    /**
     * updateCardView の状態や表示を更新します。
     * @returns {void}
     */
    function updateCardView() {
        if (studyDeck.length === 0) {
            cardFront.innerHTML = '<span class="text-gray-500 text-2xl">対象のカードがありません。</span>';
            cardBack.innerHTML = '<span class="text-gray-500 text-2xl">全てのカードを学習済みか、フィルター条件を変えてください。</span>';
            cardCounter.textContent = '0 / 0';
            flashcard.classList.remove('is-flipped');
            disableButtons(true);
            return;
        }

        disableButtons(false);
        const card = studyDeck[currentIndex];
        cardFront.textContent = card.word;
        cardBack.textContent = card.definition;
        cardCounter.textContent = `${currentIndex + 1} / ${studyDeck.length}`;

        flashcard.classList.remove('is-flipped');
        updateCardBorder(card.memory_level);
    }

    /**
     * updateCardBorder の状態や表示を更新します。
     * @param {any} level 入力値
     * @returns {void}
     */
    function updateCardBorder(level) {
        if (!flashcard) return;
        flashcard.classList.remove('border-memory-0', 'border-memory-1', 'border-memory-2');
        if (level !== undefined && level !== null) {
            flashcard.classList.add(`border-memory-${level}`);
        }
    }

    /**
     * nextCard の処理を行います。
     * @returns {void}
     */
    function nextCard() {
        if (currentIndex < studyDeck.length - 1) {
            currentIndex++;
            updateCardView();
        } else {
            displayGlobalMessage('セッションが終了しました！全てのカードを学習しました。', 'success');
            disableButtons(true);
        }
    }

    /**
     * prevCard の処理を行います。
     * @returns {void}
     */
    function prevCard() {
        if (currentIndex > 0) {
            currentIndex--;
            updateCardView();
        }
    }

    /**
     * setMemoryLevel の値や状態を設定します。
     * @param {any} level 入力値
     * @returns {Promise} 非同期処理の結果を表すPromise
     */
    async function setMemoryLevel(level) {
        if (studyDeck.length === 0) return;

        const currentCard = studyDeck[currentIndex];
        const flashcardWordId = currentCard.id;
        const originalMemoryLevel = currentCard.memory_level; // Store for potential rollback

        currentCard.memory_level = level;
        updateCardBorder(level);
        
        const formData = new FormData();
        formData.append('flashcard_word_id', flashcardWordId);
        formData.append('memory_level', level);
        formData.append('csrf_token', getCsrfToken());

        try {
            const response = await fetch('/api/study/update_flashcard_memory', {
                method: 'POST',
                body: formData,
            });
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.error || '習熟度の更新に失敗しました。');
            }
            const result = await response.json().catch(() => ({}));
            if (result && result.success !== true) {
                throw new Error(result.error || '習熟度の更新に失敗しました。');
            }
            setTimeout(nextCard, 300);
        } catch (error) {
            displayGlobalMessage('習熟度の更新中にエラーが発生しました: ' + error.message, 'danger');
            currentCard.memory_level = originalMemoryLevel; // Revert UI
            updateCardBorder(originalMemoryLevel);
        }
    }

    /**
     * disableButtons の処理を行います。
     * @param {any} disabled 入力値
     * @returns {void}
     */
    function disableButtons(disabled) {
        if (prevBtn) prevBtn.disabled = disabled;
        if (nextBtn) nextBtn.disabled = disabled;
        const memoryControls = document.getElementById('memory-controls');
        if (memoryControls) {
            Array.from(memoryControls.children).forEach(btn => btn.disabled = disabled);
        }
    }

    /**
     * handleSwipe のイベント処理を行います。
     * @returns {void}
     */
    function handleSwipe() {
        const dx = touchendX - touchstartX;
        const dy = touchendY - touchstartY;

        if (Math.abs(dx) > Math.abs(dy)) {
            if (Math.abs(dx) > swipeThreshold) {
                setMemoryLevel(dx > 0 ? 2 : 0);
            }
        } else {
            if (Math.abs(dy) > swipeThreshold) {
                if (dy > 0) setMemoryLevel(1);
            }
        }
    }
    
    /**
     * initializeEventListeners の初期化処理を行います。
     * @returns {void}
     */
    function initializeEventListeners() {
        document.addEventListener('click', (e) => {
            const target = e.target;
            if (target.closest('#flashcard')) {
                if (studyDeck.length > 0) target.closest('#flashcard').classList.toggle('is-flipped');
            } else if (target.closest('#next-card')) {
                nextCard();
            } else if (target.closest('#prev-card')) {
                prevCard();
            } else if (target.closest('.memory-btn')) {
                setMemoryLevel(parseInt(target.closest('.memory-btn').dataset.level));
            }
        });

        const flashcardElement = document.getElementById('flashcard');
        if (flashcardElement) {
            flashcardElement.addEventListener('touchstart', e => {
                touchstartX = e.changedTouches[0].screenX;
                touchstartY = e.changedTouches[0].screenY;
            }, { passive: true });

            flashcardElement.addEventListener('touchend', e => {
                touchendX = e.changedTouches[0].screenX;
                touchendY = e.changedTouches[0].screenY;
                handleSwipe();
            }, { passive: true });
        }
    }

    /**
     * fetchStudyData のデータを取得します。
     * @returns {Promise} 非同期処理の結果を表すPromise
     */
    async function fetchStudyData() {
        try {
            const url = `/api/study/get_flashcard_study_data?ids=${initialContentIds.join(',')}&filter=${initialFilterLevel}`;
            const response = await fetch(url);
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.error || '学習データの取得に失敗しました。');
            }
            const data = await response.json();

            if (!data.words || data.words.length === 0) {
                mainContent.innerHTML = `<div class="container py-5"><div class='alert alert-info'>学習対象の単語が見つかりませんでした。フィルター設定を変更するか、他のセットを選んでください。</div></div>`;
                return;
            }

            studyDeck = data.words;

            const titlePrefix = initialContentIds.length > 1 ? 'まとめ学習' : escapeHTML(data.set_details.title);
            document.title = `${titlePrefix} - 単語帳学習`;
            
            mainContent.innerHTML = renderStudyPage(titlePrefix, data.set_details);

            flashcard = document.getElementById('flashcard');
            cardFront = document.getElementById('card-front');
            cardBack = document.getElementById('card-back');
            cardCounter = document.getElementById('card-counter');
            prevBtn = document.getElementById('prev-card');
            nextBtn = document.getElementById('next-card');

            initializeEventListeners(); // Attach listeners that need specific elements
            updateCardView(); // Render the first card

        } catch (error) {
            mainContent.innerHTML = `<div class="container py-5"><div class='alert alert-danger'>エラー: ${escapeHTML(error.message)}</div></div>`;
        }
    }

    /**
     * renderStudyPage の画面表示を描画/更新します。
     * @param {any} title 入力値
     * @param {any} setDetails 入力値
     * @returns {void}
     */
    function renderStudyPage(title, setDetails) {
        const breadcrumbs = `
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/my_library.php">マイライブラリ</a></li>
                    ${initialContentIds.length > 1 ? `<li class="breadcrumb-item active" aria-current="page">${title}</li>` : `<li class="breadcrumb-item"><a href="/read/flashcard.php?id=${initialContentIds[0]}">${escapeHTML(setDetails.title)}</a></li><li class="breadcrumb-item active" aria-current="page">学習中</li>`}
                </ol>
            </nav>`;

        const headerDetails = initialContentIds.length === 1 && setDetails ? `
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h1 class="h4 mb-0">${escapeHTML(setDetails.title)}</h1>
            </div>
            <p class="text-muted small mb-0">説明: ${escapeHTML(setDetails.description || 'なし')}</p>`
            : `<h1 class="h4 mb-4">${title}</h1>`;

        return `
            ${breadcrumbs}
            <div id="global-message-container"></div>
            ${headerDetails}
            <hr>
            
            <!-- Wrapper for max width and centering on larger screens -->
            <div class="mx-auto" style="max-width: 800px;">
                <div class="study-page-container">
                    <!-- Card Scene (expands to fill space) -->
                    <div class="scene mb-4">
                        <div id="flashcard" class="flashcard rounded">
                            <div id="card-front" class="card-face card-front"></div>
                            <div id="card-back" class="card-face card-back"></div>
                        </div>
                    </div>

                    <!-- Controls (fixed at the bottom of the flex container) -->
                    <div class="controls-wrapper">
                        <div id="card-counter" class="text-center text-muted mb-3 fw-bold"></div>
                        
                        <!-- Memory Controls -->
                        <div class="d-flex justify-content-center mb-3">
                            <div id="memory-controls" class="btn-group mx-auto" style="max-width: 400px;" role="group">
                                <button data-level="0" class="memory-btn btn btn-danger">要復習</button>
                                <button data-level="1" class="memory-btn btn btn-warning">まあまあ</button>
                                <button data-level="2" class="memory-btn btn btn-success">完璧</button>
                            </div>
                        </div>

                        <!-- Navigation Buttons -->
                        <div class="d-flex justify-content-center mb-3">
                            <div class="btn-group" role="group">
                                <button id="prev-card" class="btn btn-outline-secondary">&larr; 前へ</button>
                                <button id="next-card" class="btn btn-outline-primary">次へ &rarr;</button>
                            </div>
                        </div>

                        <!-- Hint Text -->
                        <div class="controls-hint text-center p-2 bg-light rounded-lg">
                            <p class="mb-1"><strong class="d-none d-md-inline">キーボード:</strong> <kbd>スペース</kbd>でめくる, <kbd>A</kbd>/<kbd>W</kbd>/<kbd>D</kbd>で評価</p>
                            <p class="mb-0"><strong class="d-md-none">スワイプ:</strong> 左/下/右で評価</p>
                        </div>
                    </div>
                </div>
            </div>`;
    }

    document.addEventListener('keydown', (e) => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
        if (!studyDeck || studyDeck.length === 0) return;

        switch (e.key.toLowerCase()) {
            case ' ':
                e.preventDefault();
                document.getElementById('flashcard')?.classList.toggle('is-flipped');
                break;
            case 'a': setMemoryLevel(0); break;
            case 'w': setMemoryLevel(1); break;
            case 'd': setMemoryLevel(2); break;
            case 'arrowleft': prevCard(); break;
            case 'arrowright': nextCard(); break;
        }
    });

    fetchStudyData();
});




