/**
 * ファイル: public_html\js\exam.js
 * 使い道: 試験中のタイマー・遷移・提出処理を制御します。
 * 定義されている関数:
 *   - initExam
 *   - startTimer
 *   - updateView
 *   - submitExam
 */


function initExam() {
    const problems = document.querySelectorAll('.problem-card');
    const nextBtn = document.getElementById('nextProblemBtn');
    const prevBtn = document.getElementById('prevProblemBtn');
    const counterDisplay = document.getElementById('problemCounter');
    const timeDisplay = document.getElementById('time-display');
    const progressBar = document.getElementById('progress-bar');
    const examDataElem = document.getElementById('exam-data');
    const totalTimeInSeconds = (examDataElem?.dataset.timeLimit || 0) * 60;
    
    if (problems.length === 0) {
        return; // Do nothing if there are no problems
    }

    let currentProblemIndex = 0;
    let timerInterval;

    /**
     * startTimer の処理を行います。
     * @param {any} duration 入力値
     * @param {any} display 入力値
     * @returns {void}
     */
    function startTimer(duration, display) {
        let timer = duration, minutes, seconds;
        timerInterval = setInterval(function () {
            minutes = parseInt(timer / 60, 10);
            seconds = parseInt(timer % 60, 10);

            minutes = minutes < 10 ? "0" + minutes : minutes;
            seconds = seconds < 10 ? "0" + seconds : seconds;

            display.textContent = minutes + ":" + seconds;

            if (--timer < 0) {
                clearInterval(timerInterval);
                alert("時間切れです！フォームを自動的に提出します。");
                document.getElementById('examForm').submit();
            }
        }, 1000);
    }

    /**
     * updateView の状態や表示を更新します。
     * @returns {void}
     */
    function updateView() {
        problems.forEach((card, index) => {
            card.classList.toggle('d-none', index !== currentProblemIndex);
        });
        
        const progressPercent = ((currentProblemIndex + 1) / problems.length) * 100;
        counterDisplay.textContent = `${currentProblemIndex + 1} / ${problems.length}`;
        if (progressBar) {
            progressBar.style.width = `${progressPercent}%`;
            progressBar.setAttribute('aria-valuenow', progressPercent);
        }

        prevBtn.disabled = currentProblemIndex === 0;
        nextBtn.disabled = currentProblemIndex === problems.length - 1;
    }

    /**
     * submitExam の処理を行います。
     * @returns {void}
     */
    function submitExam() {
        const totalProblems = problems.length;
        const answeredCount = document.querySelectorAll('input[type="radio"]:checked').length;

        if (answeredCount < totalProblems) {
            if (!confirm(`未回答の問題が ${totalProblems - answeredCount} 問あります。本当に提出しますか？`)) {
                return; // Abort submission
            }
        }
        
        clearInterval(timerInterval);
        
        const form = document.getElementById('examForm');
        const formData = new FormData(form);
        formData.append('csrf_token', getCsrfToken());

        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> 送信中...';

        fetch('/api/study/submit_exam', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => { throw new Error(err.error || '提出に失敗しました。') });
            }
            return response.json();
        })
        .then(data => {
            if (data.attempt_id) {
                window.location.href = `/read/result.php?attempt_id=${data.attempt_id}`;
            } else {
                throw new Error('有効な attempt_id が返されませんでした。');
            }
        })
        .catch(error => {
            alert('エラー: ' + error.message);
            submitBtn.disabled = false;
            submitBtn.textContent = '試験を提出';
        });
    }

    nextBtn?.addEventListener('click', () => {
        if (currentProblemIndex < problems.length - 1) {
            currentProblemIndex++;
            updateView();
        }
    });

    prevBtn?.addEventListener('click', () => {
        if (currentProblemIndex > 0) {
            currentProblemIndex--;
            updateView();
        }
    });

    document.getElementById('submitBtn')?.addEventListener('click', submitExam);
    
    updateView();
    if(totalTimeInSeconds > 0) {
        startTimer(totalTimeInSeconds, timeDisplay);
    } else {
        timeDisplay.textContent = '--:--';
    }
}




