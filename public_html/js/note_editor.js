/**
 * ファイル: public_html\js\note_editor.js
 * 使い道: ノートの作成・編集UIと送信処理を行います。
 * 定義されている関数:
 *   - displayGlobalMessage
 *   - clearAllMessages
 *   - getByteSize
 *   - updateNoteSizeDisplay
 *   - populateForm
 *   - initializePage
 */


document.addEventListener('DOMContentLoaded', async function() {
    const formContainer = document.getElementById('form-container');
    const limitMessageContainer = document.getElementById('limit-message-container');
    const noteForm = document.querySelector('form');
    const contentId = noteForm.querySelector('input[name="id"]').value;
    const isEditMode = contentId !== '';
    const noteSizeDisplay = document.getElementById('note-size-display');
    const submitBtn = noteForm.querySelector('button[type="submit"]');
    
    const maxSizeBytes = typeof MAX_NOTE_SIZE_BYTES !== 'undefined' ? MAX_NOTE_SIZE_BYTES : 2 * 1024 * 1024; // Default to 2MB

    let isSizeExceeded = false; // Flag to track if size is exceeded

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
            noteForm.before(alertContainer);
        }
        alertContainer.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">${escapeHTML(message)}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>`;
        if (type === 'success' || type === 'warning') { // Also clear warning after timeout
            setTimeout(() => {
                const alert = globalMessageContainer.querySelector('.alert');
                if (alert) bootstrap.Alert.getOrCreateInstance(alert)?.close();
            }, 5000);
        }
    }

    /**
     * clearAllMessages の処理を行います。
     * @returns {void}
     */
    function clearAllMessages() {
        const globalAlert = document.getElementById('form-global-alert');
        if (globalAlert) globalAlert.innerHTML = '';
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

    const quill = new Quill('#editor', {
        theme: 'snow',
        placeholder: 'ノートの内容をここに入力...',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                ['blockquote', 'code-block'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                [{ 'color': [] }, { 'background': [] }],
                ['link', 'image', 'video'],
                ['clean']
            ]
        },
    });

    /**
     * getByteSize のデータを取得します。
     * @param {any} str 入力値
     * @returns {void}
     */
    function getByteSize(str) {
        return new TextEncoder().encode(str).length;
    }

    /**
     * updateNoteSizeDisplay の状態や表示を更新します。
     * @returns {void}
     */
    function updateNoteSizeDisplay() {
        if (!noteSizeDisplay) return;

        const currentContentJson = JSON.stringify(quill.getContents());
        const currentSizeBytes = getByteSize(currentContentJson);

        const currentSizeKB = (currentSizeBytes / 1024).toFixed(1);
        const maxSizeKB = (maxSizeBytes / 1024).toFixed(1); // Calculate max size in KB

        noteSizeDisplay.textContent = `容量 ${currentSizeKB}KB / ${maxSizeKB}KB`; // Display in KB

        noteSizeDisplay.classList.remove('bg-info', 'bg-warning', 'bg-danger');
        let shouldBeDisabled = false;

        if (currentSizeBytes > maxSizeBytes) {
            if (!isSizeExceeded) displayGlobalMessage(`ノートのサイズが上限（${maxSizeKB}KB）を超過しています。保存できません。`, 'danger'); // Use KB in message
            noteSizeDisplay.classList.add('bg-danger');
            isSizeExceeded = true;
            shouldBeDisabled = true;
        } else if (currentSizeBytes > maxSizeBytes * 0.9) { // Warning at 90%
            noteSizeDisplay.classList.add('bg-warning');
            if (isSizeExceeded) clearAllMessages(); // Clear previous error if size is now just a warning
            isSizeExceeded = false;
        } else {
            noteSizeDisplay.classList.add('bg-info');
            if (isSizeExceeded) clearAllMessages(); // Clear warning if size is okay
            isSizeExceeded = false;
        }
        
        submitBtn.disabled = shouldBeDisabled;
    }

    /**
     * populateForm の処理を行います。
     * @param {any} details 入力値
     * @param {any} contentDelta 入力値
     * @returns {Promise} 非同期処理の結果を表すPromise
     */
    async function populateForm(details, contentDelta) {
        noteForm.querySelector('input[name="title"]').value = details.title || '';
        noteForm.querySelector('textarea[name="description"]').value = details.description || '';
        const visibilitySelect = noteForm.querySelector('#visibility');
        if (visibilitySelect && details.visibility) visibilitySelect.value = details.visibility;

        if (details.lecture_id) {
            const lectureSelect = $('#lecture_id');
            if (lectureSelect.data('select2') && !lectureSelect.find(`option[value="${details.lecture_id}"]`).length) {
                const option = new Option(details.lecture_name || details.lecture_id, details.lecture_id, true, true);
                lectureSelect.append(option);
            }
            lectureSelect.val(details.lecture_id).trigger('change');
        }
        quill.setContents(contentDelta);
    }

    /**
     * initializePage の初期化処理を行います。
     * @returns {Promise} 非同期処理の結果を表すPromise
     */
    async function initializePage() {
        if (isEditMode) {
            try {
                const response = await fetch(`/api/content/get_note_for_edit?id=${contentId}`);
                if (!response.ok) throw new Error((await response.json().catch(() => ({}))).error || 'ノートの読み込みに失敗しました。');
                
                const data = await response.json();
                if (!data.details.can_edit) {
                    noteForm.querySelectorAll('input, textarea, select, button').forEach(el => el.disabled = true);
                    quill.disable();
                    throw new Error("このノートを編集する権限がありません。");
                }
                populateForm(data.details, JSON.parse(data.content));
            } catch (error) {
                displayGlobalMessage(error.message);
                if(formContainer) formContainer.style.display = 'none';
            }
        } else { // Create mode
            try {
                const response = await fetch('/api/content/check_creation_allowance');
                if (!response.ok) throw new Error('作成上限の確認に失敗しました。');
                const data = await response.json();
                if (data.allowed === false) {
                    if (formContainer) formContainer.style.display = 'none';
                    if (limitMessageContainer) {
                        limitMessageContainer.innerHTML = `<div class="alert alert-warning"><h4 class="alert-heading">作成上限到達</h4><p>コンテンツの作成上限（${data.limit}件）に達しているため、新しいノートを作成できません。</p><hr><p class="mb-0">既存のコンテンツを削除すると、新しいコンテンツを作成できます。 <a href="/my_content.php">作成・管理ページへ</a></p></div>`;
                    }
                }
            } catch (error) {
                if(limitMessageContainer) limitMessageContainer.innerHTML = `<div class="alert alert-danger">${escapeHTML(error.message)}</div>`;
            }
        }
    }

    noteForm.addEventListener('submit', async function(event) {
        event.preventDefault();
        clearAllMessages();
        updateNoteSizeDisplay();
        if (isSizeExceeded) return;

        const formData = new FormData(this);
        formData.set('content', JSON.stringify(quill.getContents()));

        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> 保存中...';

        try {
            const response = await fetch(this.action, { method: 'POST', body: formData });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.error || 'ノートの保存に失敗しました。');
            }
            window.location.href = `/read/note.php?id=${result.content_id}`;
        } catch (error) {
            displayGlobalMessage(error.message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    });

    initializeLectureSelector('#lecture_id', '#quarter-filter');
    await initializePage(); // Wait for page to initialize and populate form
    updateNoteSizeDisplay(); // Initial size display after potential population
    setInterval(updateNoteSizeDisplay, 10000);
    quill.on('text-change', () => {
        let timeout;
        clearTimeout(timeout);
        timeout = setTimeout(updateNoteSizeDisplay, 500);
    });
});



