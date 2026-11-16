<?php
$groups = array();
foreach ($permissions as $k => $p) {
    $groups[$p[0]][$k] = $p[1];
}
$form = $edit || $new;
$current = $edit ? (array) json_decode((string) $edit['permissions'], true) : array();
?>
<div class="page-head">
    <div><p>What each kind of staff may do. Agents see only the businesses they manage unless the role has "all businesses".</p></div>
    <div class="page-actions"><a class="btn btn-default" href="<?= site_url('staff') ?>"><i class="fas fa-users"></i> Staff</a><a class="btn btn-primary" href="?new=1"><i class="fas fa-plus"></i> New role</a></div>
</div>
<div class="grid <?= $form ? 'side' : '' ?>">
    <div class="panel">
        <div class="panel-body"><table class="table table-bordered table-hover table-condensed mb-none table-export table-mobile-cards" data-card-icon="fas fa-key" data-card-title-col="0" data-card-skip-cols="-1">
            <thead><tr><th>Role</th><th>May</th><th class="num">Staff</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($roles as $r): $p = (array) json_decode((string) $r['permissions'], true); ?>
            <tr>
                <td data-label=""><span class="cell-main"><?= e($r['name']) ?></span> <?= (int) $r['is_owner'] ? badge('Owner', 'primary', 'fa-crown') : '' ?><div class="cell-sub"><?= e($r['description']) ?></div></td>
                <td data-label="May"><?= (int) $r['is_owner'] || in_array('*', $p, true) ? 'Everything' : count($p) . ' permissions' ?></td>
                <td data-label="Staff" class="num"><?= (int) $r['staff'] ?></td>
                <td data-label="" class="actions"><?php if (!(int) $r['is_owner']): ?>
                    <a class="btn btn-circle btn-default icon" href="?edit=<?= (int) $r['id'] ?>"><i class="fas fa-pen"></i></a>
                    <?php if (!(int) $r['staff']): ?><button class="btn btn-danger icon btn-circle" data-post="<?= site_url('staff/role_delete/' . (int) $r['id']) ?>" data-confirm="Delete the role <?= e($r['name']) ?>?"><i class="fas fa-trash"></i></button><?php endif; ?>
                <?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
    <?php if ($form): ?>
    <div class="panel">
        <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-key"></i> <?= $edit ? e($edit['name']) : 'New role' ?><span class="panel-aside"><a href="<?= site_url('staff/roles') ?>">Close</a></span></h4></div>
        <?= form_open('staff/role_save', array('class' => 'js-form panel-body')) ?>
            <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <div class="form-group mb-md"><label class="control-label">Name</label><input class="form-control" name="name" value="<?= e($edit['name'] ?? '') ?>"></div>
            <div class="form-group mb-md"><label class="control-label">Description</label><input class="form-control" name="description" value="<?= e($edit['description'] ?? '') ?>"></div>
            <?php foreach ($groups as $g => $items): ?>
            <label class="control-label mt-md"><?= e($g) ?></label>
            <?php foreach ($items as $k => $label): ?><label class="check mb-md"><input type="checkbox" name="permissions[]" value="<?= e($k) ?>" <?= in_array($k, $current, true) ? 'checked' : '' ?>> <?= e($label) ?></label><?php endforeach; ?>
            <?php endforeach; ?>
            <button class="btn btn-primary btn-block mt-md" type="submit"><i class="fas fa-save"></i> Save role</button>
        <?= form_close() ?>
    </div>
    <?php endif; ?>
</div>
