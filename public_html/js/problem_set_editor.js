/**
 * ファイル: public_html\js\problem_set_editor.js
 * 使い道: 問題集の作成・編集UIと送信処理を行います。
 * 定義されている関数:
 *   - clearAllMessages
 *   - displayGlobalMessage
 *   - displayCardMessage
 *   - addQuestion
 *   - addChoice
 *   - updateQuestionNumbers
 *   - populateForm
 *   - initializePage
 *   - parseCsvLine
 *   - handleCsvImport
 */


document.addEventListener('DOMContentLoaded', async function() {
    const questionsContainer = document.getElementById('questions-container');
    const addQuestionBtn = document.getElementById('add-question-btn');
    const questionTemplate = document.getElementById('question-template');
    const choiceTemplate = document.getElementById('choice-template');
    const csvFileInput = document.getElementById('csv-file-input');
    const problemSetForm = document.querySelector('form');

    /**
     * clearAllMessages の処理を行います。
     * @returns {void}
     */
    function clearAllMessages() {
        const globalAlert = document.getElementById('form-global-alert');
        if (globalAlert) globalAlert.innerHTML = '';
        document.querySelectorAll('.card-alert-message').forEach(alert => alert.remove());
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
            const firstCard = document.querySelector('.card');
            if (firstCard) firstCard.before(alertContainer);
            else problemSetForm.prepend(alertContainer);
        }
        if (!message) {
            alertContainer.innerHTML = '';
            return;
        }
        alertContainer.innerHTML = `<div class="alert alert-${type}" role="alert">${escapeHTML(message)}</div>`;
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
     * addQuestion の追加処理を行います。
     * @param {any} data 入力値
     * @returns {void}
     */
    function addQuestion(data = null) {
        const newQuestionFragment = questionTemplate.content.cloneNode(true);
        const choicesContainer = newQuestionFragment.querySelector('.choices-container');
        
        if (data) {
            newQuestionFragment.querySelector('.question-text').value = data.question_text || '';
            newQuestionFragment.querySelector('.explanation-text').value = data.explanation || '';
            const numChoices = data.choices ? data.choices.length : 0;
            for (let i = 0; i < numChoices; i++) {
                const isCorrect = (data.correct_choice_index) === (i + 1);
                addChoice(choicesContainer, data.choices[i], isCorrect);
            }
        } else {
            for (let i = 0; i < 4; i++) {
                addChoice(choicesContainer, '', false);
            }
        }
        
        questionsContainer.appendChild(newQuestionFragment);
        updateQuestionNumbers();
    }

    /**
     * addChoice の追加処理を行います。
     * @param {any} container 入力値
     * @param {any} choiceText 入力値
     * @param {any} isCorrect 入力値
     * @returns {void}
     */
    function addChoice(container, choiceText = '', isCorrect = false) {
        const newChoiceFragment = choiceTemplate.content.cloneNode(true);
        if (choiceText) newChoiceFragment.querySelector('.choice-text').value = choiceText;
        if (isCorrect) newChoiceFragment.querySelector('.is-correct-radio').checked = true;
        container.appendChild(newChoiceFragment);
    }

    /**
     * updateQuestionNumbers の状態や表示を更新します。
     * @returns {void}
     */
    function updateQuestionNumbers() {
        const questionCards = questionsContainer.querySelectorAll('.question-card');
        questionCards.forEach((card, index) => {
            card.querySelector('.question-title').textContent = `問題 ${index + 1}`;
            card.querySelectorAll('.is-correct-radio').forEach(radio => {
                radio.name = `correct_choice_group_${index}`;
            });
        });
    }

    /**
     * populateForm の処理を行います。
     * @param {any} details 入力値
     * @param {any} problems 入力値
     * @returns {void}
     */
    function populateForm(details, problems) {
        console.log("Populating form with details:", details);
        console.log("Populating form with problems:", problems);

        problemSetForm.querySelector('input[name="title"]').value = details.title || '';
        problemSetForm.querySelector('textarea[name="description"]').value = details.description || '';
        problemSetForm.querySelector('input[name="time_limit"]').value = details.time_limit_minutes || '';
        
        const visibilitySelect = problemSetForm.querySelector('#visibility');
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
                console.warn("Select2 not yet initialized for #lecture_id, attempting fallback.");
                const option = new Option(details.lecture_name || details.lecture_id, details.lecture_id, true, true);
                lectureSelect.append(option);
                lectureSelect.val(details.lecture_id);
            }
        }
        
        questionsContainer.innerHTML = '';
        if (problems && Array.isArray(problems) && problems.length > 0) {
            problems.forEach(addQuestion);
        } else {
            console.log("No problems found in data to populate. Adding one empty question.");
            addQuestion();
        }
    }

    /**
     * initializePage の初期化処理を行います。
     * @returns {Promise} 非同期処理の結果を表すPromise
     */
    async function initializePage() {
        const urlParams = new URLSearchParams(window.location.search);
        const contentId = urlParams.get('id');
        const submitBtn = problemSetForm.querySelector('button[type="submit"]');

        if (contentId) { // Edit mode
            if(submitBtn) submitBtn.innerHTML = '更新';
            try {
                const response = await fetch(`/api/content/get_problem_set_for_edit?id=${contentId}`);
                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.error || 'データの読み込みに失敗しました。');
                }
                const data = await response.json();
                populateForm(data.details, data.problems);
            } catch (error) {
                displayGlobalMessage(error.message);
            }
        } else { // Create mode
            if(submitBtn) submitBtn.innerHTML = '作成';
            if (questionsContainer.children.length === 0) {
                addQuestion();
            }
        }
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
        successAlert.style.display = 'none';
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function(e) {
            let importedCount = 0;
            try {
                const lines = e.target.result.split(/\r?\n/).filter(line => line.trim() !== '');
                if (lines.length < 2) throw new Error('CSVファイルには、ヘッダー行と少なくとも1つの問題データ行が必要です。');

                questionsContainer.innerHTML = '';
                for (let i = 1; i < lines.length; i++) {
                    const columns = parseCsvLine(lines[i]);
                    if (columns.length < 7) continue;

                    const questionData = {
                        question_text: columns[0],
                        choices: [columns[1], columns[2], columns[3], columns[4]],
                        correct_choice_index: parseInt(columns[5], 10),
                        explanation: columns[6]
                    };
                    if (isNaN(questionData.correct_choice_index) || questionData.correct_choice_index < 1 || questionData.correct_choice_index > 4) continue;
                    
                    addQuestion(questionData);
                    importedCount++;
                }
                
                if (importedCount > 0) {
                    successAlert.textContent = `${importedCount} 件の問題を取り込みました。`;
                    successAlert.style.display = 'block';
                    setTimeout(() => { successAlert.style.display = 'none'; }, 5000);
                } else {
                    throw new Error('有効な問題データが見つかりませんでした。');
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

    problemSetForm.addEventListener('submit', async function(event) {
        event.preventDefault();
        clearAllMessages();

        const formData = new FormData();
        formData.append('id', this.querySelector('input[name="id"]').value);
        formData.append('title', this.querySelector('input[name="title"]').value);
        formData.append('description', this.querySelector('textarea[name="description"]').value);
        formData.append('visibility', this.querySelector('#visibility').value); // Append visibility
        formData.append('lecture_id', this.querySelector('select[name="lecture_id"]').value);
        formData.append('time_limit', this.querySelector('input[name="time_limit"]').value);
        formData.append('csrf_token', getCsrfToken());

        const questionCards = questionsContainer.querySelectorAll('.question-card');
        if (questionCards.length === 0) {
            displayGlobalMessage('問題を追加してください。');
            return;
        }

        let isValid = true;
        for (let i = 0; i < questionCards.length; i++) {
            const questionCard = questionCards[i];
            const questionTextarea = questionCard.querySelector('.question-text');
            if (!questionTextarea.value.trim()) {
                displayCardMessage(questionCard, `問題文を入力してください。`);
                questionTextarea.focus();
                isValid = false;
                break;
            }

            const choiceItems = questionCard.querySelectorAll('.choice-item');
            if (choiceItems.length < 2) {
                displayCardMessage(questionCard, `選択肢は2つ以上必要です。`);
                isValid = false;
                break;
            }
            
            let correctChoiceIndex = -1;
            for (let j = 0; j < choiceItems.length; j++) {
                const choiceItem = choiceItems[j];
                const choiceText = choiceItem.querySelector('.choice-text');
                if (!choiceText.value.trim()) {
                    displayCardMessage(questionCard, `選択肢 ${j + 1} を入力してください。`);
                    choiceText.focus();
                    isValid = false;
                    break;
                }
                if (choiceItem.querySelector('.is-correct-radio').checked) {
                    correctChoiceIndex = j;
                }
            }
            if (!isValid) break;
            
            if (correctChoiceIndex === -1) {
                displayCardMessage(questionCard, `正解の選択肢を選択してください。`);
                questionCard.querySelector('.is-correct-radio').focus();
                isValid = false;
                break;
            }

            formData.append(`questions[${i}][text]`, questionTextarea.value);
            formData.append(`questions[${i}][explanation]`, questionCard.querySelector('.explanation-text').value);
            formData.append(`questions[${i}][correct_choice]`, correctChoiceIndex);
            choiceItems.forEach((item, k) => {
                formData.append(`questions[${i}][choices][${k}]`, item.querySelector('.choice-text').value);
            });
        }

        if (!isValid) return;

        const submitBtn = this.querySelector('button[type="submit"]');
        const originalBtnText = this.querySelector('input[name="id"]').value ? '更新' : '作成';
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> 保存中...';

        try {
            const response = await fetch(this.action, { method: 'POST', body: formData });
            const result = await response.json();

            if (response.ok && result.success) {
                displayGlobalMessage('問題集が正常に保存されました。リダイレクト中...', 'success');
                window.location.href = `/read/problem.php?id=${result.content_id}`;
            } else {
                displayGlobalMessage(result.error || '問題集の保存に失敗しました。');
            }
        } catch (error) {
            displayGlobalMessage('ネットワークエラーが発生しました。');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    });

    addQuestionBtn?.addEventListener('click', () => addQuestion());
    csvFileInput?.addEventListener('change', handleCsvImport);
    questionsContainer?.addEventListener('click', function(e) {
        if (e.target.closest('.remove-question-btn')) {
            e.target.closest('.question-card').remove();
            updateQuestionNumbers();
        }
        if (e.target.closest('.remove-choice-btn')) {
            e.target.closest('.choice-item').remove();
        }
    });

    initializeLectureSelector('#lecture_id', '#quarter-filter');
    initializePage();
});




