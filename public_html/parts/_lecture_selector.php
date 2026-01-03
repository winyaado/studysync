<?php
// このパーシャルは、インクルード元のスコープで以下の変数が設定されていることを期待します:
// $label_text (オプション, デフォルトは '講義')
// $selectedLectureId (オプション)
// $selectedLectureName (オプション)

$label_text = $label_text ?? '講義';
$selectedLectureId = $selectedLectureId ?? '';
$selectedLectureName = $selectedLectureName ?? '';
?>
<label for="lecture_id" class="form-label"><?= htmlspecialchars($label_text) ?></label>

<div class="mb-2">
    <small class="text-muted">開講クォーターで絞り込み:</small>
    <div class="btn-group btn-group-sm" role="group" id="quarter-filter">
        <input type="checkbox" class="btn-check" id="q1" value="1" autocomplete="off">
        <label class="btn btn-outline-primary" for="q1">1Q</label>

        <input type="checkbox" class="btn-check" id="q2" value="2" autocomplete="off">
        <label class="btn btn-outline-primary" for="q2">2Q</label>

        <input type="checkbox" class="btn-check" id="q3" value="4" autocomplete="off">
        <label class="btn btn-outline-primary" for="q3">3Q</label>

        <input type="checkbox" class="btn-check" id="q4" value="8" autocomplete="off">
        <label class="btn btn-outline-primary" for="q4">4Q</label>
        
        <input type="checkbox" class="btn-check" id="q_通期" value="16" autocomplete="off">
        <label class="btn btn-outline-primary" for="q_通期">通期</label>
    </div>
</div>

<select class="form-control" id="lecture_id" name="lecture_id" style="width: 100%;">
    <?php if ($selectedLectureId): ?>
        <option value="<?= htmlspecialchars($selectedLectureId) ?>" selected><?= htmlspecialchars($selectedLectureName ?: $selectedLectureId) ?></option>
    <?php else: ?>
        <option value="" selected>講義を検索または選択</option>
    <?php endif; ?>
</select>
