/**
 * ファイル: public_html\js\flashcard_editor.js
 * 使い道: 単語帳の作成・編集UIと送信処理を行います。
 * 定義されている関数:
 *   - clearAllMessages
 *   - displayGlobalMessage
 *   - displayCardMessage
 *   - addWord
 *   - updateWordNumbers
 *   - populateForm
 *   - initializePage
 *   - parseCsvLine
 *   - handleCsvImport
 */


document.addEventListener('DOMContentLoaded', async function() {
    const wordsContainer = document.getElementById('words-container');
    const addWordBtn = document.getElementById('add-word-btn');
    const wordTemplate = document.getElementById('word-template');
    const flashcardForm = document.querySelector('form');
    const submitBtn = flashcardForm.querySelector('button[type="submit"]');
    const csvFileInput = document.getElementById('csv-file-input');

    let contentId = flashcardForm.querySelector('input[name="id"]').value;
    let editMode = contentId !== '';
    let wordsToDelete = []; // To store IDs of words marked for deletion

    /**
     * clearAllMessages の処理を行います。
     * @returns {void}
     */
    function clearAllMessages() {
        const globalAlert = document.getElementById('form-global-alert');
        if (globalAlert) globalAlert.remove(); // Remove previous alert
        document.querySelectorAll('.card-alert-message').forEach(alert => alert.remove());
    }

    /**
     * displayGlobalMessage の処理を行います。
     * @param {any} message 入力値
     * @param {any} type 入力値
     * @returns {void}
     */
    function displayGlobalMessage(message, type = 'danger') {
        let alertContainer = document.getElementById('form-global-alert');
        if (!alertContainer) {
            alertContainer = document.createElement('div');
            alertContainer.id = 'form-global-alert';
            flashcardForm.prepend(alertContainer);
        }
        alertContainer.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">${escapeHTML(message)}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>`;
        if (type === 'success' || type === 'warning') {
            setTimeout(() => {
                const alert = alertContainer.querySelector('.alert');
                if (alert) bootstrap.Alert.getOrCreateInstance(alert)?.close();
            }, 5000);
        }
    }
    
    /**
     * displayCardMessage の処理を行います。
     * @param {any} cardElement 入力値
     * @param {any} message 入力値
     * @returns {void}
     */
    function displayCardMessage(cardElement, message) {
        const cardBody = cardElement.querySelector('.card-body');
        if (!cardBody) return;
        const existingError = cardBody.querySelector('.card-alert-message');
        if (existingError) existingError.remove();

        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-danger card-alert-message mt-2';
        alertDiv.setAttribute('role', 'alert');
        alertDiv.textContent = message;
        cardBody.prepend(alertDiv);
    }

    /**
     * escapeHTML の文字列をエスケープします。
     * @param {any} str 入力値
     * @returns {void}
     */
    function escapeHTML(str) {
        if (str === null || typeof str === 'undefined') return '';
        return String(str).replace(/[&<>'"]/g, match => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[match]);
    }

    /**
     * addWord の追加処理を行います。
     * @param {any} data 入力値
     * @param {any} word: '' 入力値
     * @param {any} definition: '' } 入力値
     * @returns {void}
     */
    function addWord(data = { id: '', word: '', definition: '' }) {
        const newWordFragment = wordTemplate.content.cloneNode(true);
        const wordCard = newWordFragment.querySelector('.word-card');
        
        wordCard.querySelector('.word-id').value = data.id;
        wordCard.querySelector('.word-text').value = data.word;
        wordCard.querySelector('.definition-text').value = data.definition;
        
        wordsContainer.appendChild(newWordFragment);
        updateWordNumbers();
    }

    /**
     * parseCsvLine の入力を解析します。
     * @param {any} line 入力値
     * @returns {void}
     */
    function parseCsvLine(line) {
        const matches = line.match(/(".*?"|[^",]+)(?=\s*,|\s*$)/g);
        if (!matches) return [];
        return matches.map(field => field.trim().replace(/^"|"$/g, '').replace(/""/g, '"'));
    }

    /**
     * handleCsvImport のイベント処理を行います。
     * @param {any} event 入力値
     * @returns {void}
     */
    function handleCsvImport(event) {
        const file = event.target.files[0];
        const successAlert = document.getElementById('csv-import-success');
        if (successAlert) successAlert.style.display = 'none';
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function(e) {
            let importedCount = 0;
            try {
                const lines = e.target.result.split(/\r?\n/).filter(line => line.trim() !== '');
                if (lines.length < 2) throw new Error('CSVファイルには、ヘッダー行と少なくとも1つの単語データ行が必要です。');

                wordsContainer.innerHTML = '';
                for (let i = 1; i < lines.length; i++) {
                    const columns = parseCsvLine(lines[i]);
                    if (columns.length < 2) continue;
                    const word = columns[0];
                    const definition = columns[1];
                    if (!word || !definition) continue;
                    addWord({ id: '', word, definition });
                    importedCount++;
                }

                if (importedCount > 0) {
                    if (successAlert) {
                        successAlert.textContent = `${importedCount} 件の単語を取り込みました。`;
                        successAlert.style.display = 'block';
                        setTimeout(() => { successAlert.style.display = 'none'; }, 5000);
                    }
                } else {
                    throw new Error('有効な単語データが見つかりませんでした。');
                }
            } catch (error) {
                displayGlobalMessage(`CSVファイルの処理中にエラーが発生しました: ${error.message}`);
            } finally {
                event.target.value = '';
            }
        };
        reader.onerror = () => displayGlobalMessage('ファイルの読み込みに失敗しました。');
        reader.readAsText(file, 'UTF-8');
    }

    /**
     * updateWordNumbers の状態や表示を更新します。
     * @returns {void}
     */
    function updateWordNumbers() {
        const wordCards = wordsContainer.querySelectorAll('.word-card:not(.deleted)'); // Ignore soft-deleted cards
        wordCards.forEach((card, index) => {
            card.querySelector('.word-title').textContent = `単語 ${index + 1}`;
        });
    }

    /**
     * populateForm の処理を行います。
     * @param {any} details 入力値
     * @param {any} words 入力値
     * @returns {void}
     */
    function populateForm(details, words) {
        flashcardForm.querySelector('input[name="title"]').value = details.title || '';
        flashcardForm.querySelector('textarea[name="description"]').value = details.description || '';
        
        const visibilitySelect = flashcardForm.querySelector('#visibility');
        if (visibilitySelect && details.visibility) {
            visibilitySelect.value = details.visibility;
        }

        if (details.lecture_id) {
            const lectureSelect = $('#lecture_id');
            if (lectureSelect.data('select2')) {
                if (!lectureSelect.find(`option[value="${details.lecture_id}"]`).length) {
                    const option = new Option(details.lecture_name || details.lecture_id, details.lecture_id, true, true);
                    lectureSelect.append(option);
                }
                lectureSelect.val(details.lecture_id).trigger('change');
            } else {
                const option = new Option(details.lecture_name || details.lecture_id, details.lecture_id, true, true);
                lectureSelect.append(option);
                lectureSelect.val(details.lecture_id);
            }
        }
        
        wordsContainer.innerHTML = ''; // Clear any initial empty word
        if (words && Array.isArray(words) && words.length > 0) {
            words.forEach(addWord);
        } else {
            addWord(); // Add one empty word for new set
        }
    }

    /**
     * initializePage の初期化処理を行います。
     * @returns {Promise} 非同期処理の結果を表すPromise
     */
    async function initializePage() {
        if (editMode) { // Edit mode
            submitBtn.innerHTML = '更新';
            try {
                const response = await fetch(`/api/content/get_flashcard_for_edit?id=${contentId}`);
                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.error || 'データの読み込みに失敗しました。');
                }
                const data = await response.json();
                populateForm(data.details, data.words);
            } catch (error) {
                displayGlobalMessage(error.message);
            }
        } else { // Create mode
            submitBtn.innerHTML = '作成';
            try {
                const response = await fetch('/api/content/check_creation_allowance');
                if (!response.ok) throw new Error('作成上限の確認に失敗しました。');
                const data = await response.json();
                if (data.allowed === false) {
                    const formContainer = document.getElementById('form-container');
                    const limitMessageContainer = document.getElementById('limit-message-container');
                    if (formContainer) formContainer.style.display = 'none';
                    if (limitMessageContainer) {
                        limitMessageContainer.innerHTML = `
                            <div class="alert alert-warning">
                                <h4 class="alert-heading">作成上限到達</h4>
                                <p>コンテンツの作成上限（${data.limit}件）に達しているため、新しいフラッシュカードを作成できません。</p>
                                <hr>
                                <p class="mb-0">既存のコンテンツを削除すると、新しいコンテンツを作成できます。 <a href="/my_content.php">作成・管理ページへ</a></p>
                            </div>
                        `;
                    }
                } else {
                    addWord(); // Add one empty word for new set if allowed
                }
            } catch (error) {
                displayGlobalMessage(error.message);
            }
        }
    }

    addWordBtn.addEventListener('click', () => addWord());
    csvFileInput?.addEventListener('change', handleCsvImport);

    wordsContainer.addEventListener('click', function(e) {
        const removeBtn = e.target.closest('.remove-word-btn');
        if (removeBtn) {
            const wordCard = removeBtn.closest('.word-card');
            const wordId = wordCard.querySelector('.word-id').value;

            if (wordId) { // Existing word, mark for deletion
                wordsToDelete.push(wordId);
                wordCard.classList.add('deleted'); // Add a class to hide/mark
                wordCard.style.display = 'none'; // Temporarily hide
            } else { // New word, just remove from DOM
                wordCard.remove();
            }
            updateWordNumbers();
        }
    });

    flashcardForm.addEventListener('submit', async function(event) {
        event.preventDefault();
        clearAllMessages();

        const formData = new FormData();
        formData.append('id', contentId);
        formData.append('title', flashcardForm.querySelector('input[name="title"]').value);
        formData.append('description', flashcardForm.querySelector('textarea[name="description"]').value);
        formData.append('visibility', flashcardForm.querySelector('#visibility').value);
        formData.append('lecture_id', flashcardForm.querySelector('select[name="lecture_id"]').value);
        formData.append('csrf_token', getCsrfToken());
        
        const wordCards = wordsContainer.querySelectorAll('.word-card:not(.deleted)');
        const wordsToSave = [];
        let isValid = true;
        
        wordCards.forEach((card, index) => {
            const wordId = card.querySelector('.word-id').value;
            const wordText = card.querySelector('.word-text').value.trim();
            const definitionText = card.querySelector('.definition-text').value.trim();

            if (!wordText || !definitionText) {
                displayCardMessage(card, `単語と意味の両方を入力してください。`);
                isValid = false;
                return;
            }

            wordsToSave.push({
                id: wordId,
                word: wordText,
                definition: definitionText,
                display_order: index + 1 // Assign display order based on current position
            });
        });

        if (!isValid) {
            displayGlobalMessage('未入力の単語カードがあります。', 'danger');
            return;
        }
        if (wordsToSave.length === 0) {
            displayGlobalMessage('少なくとも1つの単語カードが必要です。', 'danger');
            return;
        }

        formData.append('words', JSON.stringify(wordsToSave));
        formData.append('words_to_delete', JSON.stringify(wordsToDelete));

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> 保存中...';

        try {
            const response = await fetch(this.action, { method: 'POST', body: formData });
            const result = await response.json();

            if (response.ok && result.success) {
                displayGlobalMessage('単語帳が正常に保存されました。リダイレクト中...', 'success');
                window.location.href = `/read/flashcard.php?id=${result.content_id}`;
            } else {
                throw new Error(result.error || '単語帳の保存に失敗しました。');
            }
        } catch (error) {
            displayGlobalMessage('エラー: ' + error.message, 'danger');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = editMode ? '更新' : '作成';
        }
    });

    initializeLectureSelector('#lecture_id', '#quarter-filter');
    initializePage();
});




