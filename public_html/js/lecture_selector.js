/**
 * ファイル: public_html/js/lecture_selector.js
 * 使い道: 講義選択UI（Select2）の初期化と候補取得を行います。
 * 定義されている関数:
 *   - initializeLectureSelector
 *   - initSelect2
 */

/**
 * 講義セレクタと学期フィルタを初期化します。
 * @param {string} selector 講義セレクタのCSSセレクタ
 * @param {string} filterSelector 学期フィルタのCSSセレクタ
 * @returns {Promise} 非同期処理の結果を表すPromise
 */
async function initializeLectureSelector(selector, filterSelector) {
    const $lectureId = $(selector);
    const $quarterFilter = $(filterSelector);

    if ($lectureId.length === 0) {
        console.error(`Lecture selector element not found with selector: ${selector}`);
        return;
    }

    const initialOption = $lectureId.find('option[value!=""]');
    const initialValue = initialOption.length ? initialOption.val() : null;
    const initialText = initialOption.length ? initialOption.text() : null;

    let allLectures = [];
    try {
        // 講義一覧を取得
        const response = await fetch('/api/system/search_lectures');
        if (!response.ok) {
            throw new Error(`Failed to load lectures. Status: ${response.status}`);
        }
        const data = await response.json();

        allLectures = data.lectures.map(function(lecture) {
            let quarterText = '';
            if (lecture.quarter === 16) quarterText = '通期';
            else if (lecture.quarter === 3) quarterText = '前期';
            else if (lecture.quarter === 12) quarterText = '後期';
            else if (lecture.quarter === 15) quarterText = '前期後期';
            else {
                let parts = [];
                if (lecture.quarter & 1) parts.push('1Q');
                if (lecture.quarter & 2) parts.push('2Q');
                if (lecture.quarter & 4) parts.push('3Q');
                if (lecture.quarter & 8) parts.push('4Q');
                quarterText = parts.join(', ');
            }
            const displayQuarter = quarterText ? ` (${quarterText})` : '';
            return {
                id: lecture.lecture_code,
                text: `${lecture.lecture_code}: ${lecture.name}${displayQuarter}`,
                quarter: lecture.quarter // フィルタ用に保持
            };
        });

    } catch (error) {
        console.error('Failed to initialize lecture selector:', error);
        $lectureId.select2({
            theme: 'bootstrap-5',
            placeholder: '講義の読み込みに失敗しました',
            disabled: true
        });
        return;
    }

    if (initialValue && !allLectures.some(l => l.id === initialValue)) {
        allLectures.unshift({
            id: initialValue,
            text: initialText,
            quarter: 0 // 四半期は不明だが選択は維持する
        });
    }

    /**
     * Select2の初期化を行います。
     * @param {Array} lectureData Select2に渡す講義データ
     * @returns {void}
     */
    const initSelect2 = (lectureData) => {
        $lectureId.off().empty().select2({
            theme: 'bootstrap-5',
            placeholder: '講義を検索または選択',
            allowClear: true,
            language: {
                noResults: function () { return '結果がありません'; },
                searching: function () { return '検索中…'; },
            },
            data: lectureData
        });
    };

    initSelect2(allLectures);
    if (initialValue) {
        $lectureId.val(initialValue).trigger('change');
    } else {
        $lectureId.val(null).trigger('change');
    }

    if ($quarterFilter.length > 0) {
        $quarterFilter.on('change', 'input', function() {
            const selectedQuarters = $quarterFilter.find('input:checked').map(function() {
                return parseInt(this.value, 10);
            }).get();

            let filteredLectures = allLectures;
            if (selectedQuarters.length > 0) {
                filteredLectures = allLectures.filter(lecture => {
                    for (const q of selectedQuarters) {
                        if (q === 16) { // 通期は完全一致のみ
                            if (lecture.quarter === 16) return true;
                        } else { // 1Q〜4Qはビット演算で判定
                            if ((lecture.quarter & q) > 0) return true;
                        }
                    }
                    return false;
                });
            }

            const currentSelection = $lectureId.val();
            initSelect2(filteredLectures);

            if (currentSelection && filteredLectures.some(l => l.id === currentSelection)) {
                $lectureId.val(currentSelection).trigger('change');
            } else {
                $lectureId.val(null).trigger('change');
            }

            $lectureId.select2('open');
        });
    }
}

