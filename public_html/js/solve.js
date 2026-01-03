/**
 * ファイル: public_html\js\solve.js
 * 使い道: 試験画面の問題遷移（前へ/次へ）を制御します。
 * 定義されている関数:
 *   - updateProblemView
 */


document.addEventListener('DOMContentLoaded', function() {
    const problemCards = document.querySelectorAll('.problem-card');
    const nextButton = document.getElementById('nextProblemBtn');
    const prevButton = document.getElementById('prevProblemBtn');
    const counter = document.getElementById('problemCounter');
    let currentProblemIndex = 0;

    /**
     * updateProblemView の状態や表示を更新します。
     * @returns {void}
     */
    function updateProblemView() {
        problemCards.forEach((card, index) => {
            if (index === currentProblemIndex) {
                card.classList.remove('d-none');
            } else {
                card.classList.add('d-none');
            }
        });

        counter.textContent = `${currentProblemIndex + 1} / ${problemCards.length}`;

        prevButton.disabled = currentProblemIndex === 0;
        nextButton.disabled = currentProblemIndex === problemCards.length - 1;
    }

    if (nextButton) {
        nextButton.addEventListener('click', () => {
            if (currentProblemIndex < problemCards.length - 1) {
                currentProblemIndex++;
                updateProblemView();
            }
        });
    }

    if (prevButton) {
        prevButton.addEventListener('click', () => {
            if (currentProblemIndex > 0) {
                currentProblemIndex--;
                updateProblemView();
            }
        });
    }

    if (problemCards.length > 0) {
        updateProblemView();
    }
});



