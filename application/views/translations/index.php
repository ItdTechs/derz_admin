<?php $rows_for = function ($t) { return max(1, min(6, (int) ceil(mb_strlen((string) $t) / 38))); }; ?>
<div class="page-head">
    <div><p>Every word of the DerZ app and the website, in every language. A box saves when you leave it.</p></div>
    <div class="page-actions"><button class="btn btn-primary" data-toggle="modal" data-target="#m-word"><i class="fas fa-plus"></i> Add a word</button></div>
</div>
<div class="pills mb-md">
    <span class="label label-default"><?= number_format($total) ?> words</span>
    <?php foreach ($columns as $c => $l): ?><span class="label label-<?= $missing_counts[$c] ? 'warning' : 'success' ?>"><?= e(ucfirst($l['name'])) ?>: <?= $missing_counts[$c] ? number_format($missing_counts[$c]) . ' missing' : 'complete' ?></span><?php endforeach; ?>
</div>
<div class="panel">
    <form class="panel-body filters" method="get">
        <div class="form-group grow"><label class="control-label">Search</label><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="The word, its English or a translation"></div>
        <label class="check" style="padding-bottom:10px"><input type="checkbox" name="missing" value="1" <?= $missing ? 'checked' : '' ?>> Only words missing a translation</label>
        <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Filter</button>
    </form>
    <div class="table-responsive"><table class="table table-bordered table-hover table-condensed mb-none">
        <thead><tr><th style="width:20%">Word</th><th>English</th><?php foreach ($columns as $c => $l): ?><th><?= e(ucfirst($l['name'])) ?><?= (int) $l['status'] !== 1 ? ' ' . badge('inactive', 'muted') : '' ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php if (!$words): ?><tr><td colspan="<?= 2 + count($columns) ?>"><div class="empty">No word matches.</div></td></tr><?php endif; ?>
        <?php foreach ($words as $w): ?>
        <tr data-word="<?= e($w['word']) ?>">
            <td><code style="word-break:break-all"><?= e($w['word']) ?></code></td>
            <td><textarea class="form-control tr-text" data-field="english" rows="<?= $rows_for($w['english']) ?>" style="min-height:0"><?= e((string) $w['english']) ?></textarea></td>
            <?php foreach ($columns as $c => $l): ?>
            <td><textarea class="form-control tr-text" data-field="<?= e($c) ?>" rows="<?= $rows_for($w[$c]) ?>" style="min-height:0;<?= (string) $w[$c] === '' ? 'background:#fff6e0' : '' ?>" placeholder="Not translated"><?= e((string) $w[$c]) ?></textarea></td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?= pager($total, $page, $per) ?>
</div>
<div class="modal fade" id="m-word" tabindex="-1" role="dialog"><div class="modal-dialog"><div class="modal-content">
    <?= form_open('translations/add', array('class' => 'js-form')) ?>
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fas fa-plus"></i> Add a word</h4></div>
    <div class="modal-body">
        <div class="form-group mb-md"><label class="control-label">English</label><input class="form-control" name="english"></div>
        <div class="form-group mb-md"><label class="control-label">Word (key)</label><input class="form-control" name="word" placeholder="made from the English if empty"></div>
        <?php foreach ($columns as $c => $l): ?><div class="form-group mb-md"><label class="control-label"><?= e(ucfirst($l['name'])) ?></label><input class="form-control" name="text[<?= e($c) ?>]"></div><?php endforeach; ?>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
    <?= form_close() ?>
</div></div></div>
<script>
document.addEventListener('focusout', function (e) {
    var t = e.target;
    if (!t.classList.contains('tr-text') || t.value === t.defaultValue) return;
    var data = new FormData();
    data.append('word', t.closest('tr').dataset.word); data.append('field', t.dataset.field); data.append('value', t.value);
    derzSend('<?= site_url('translations/save') ?>', data, function (r) {
        if (r.status === 'success') { t.defaultValue = t.value; t.style.background = t.value === '' ? '#fff6e0' : ''; derzToast('Saved.'); }
        else { derzToast(r.message || 'Not saved.', 'error'); }
    });
});
</script>
