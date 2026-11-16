<?php
$v = function ($k, $d = '') use ($p) { return $p && isset($p[$k]) && $p[$k] !== null ? $p[$k] : $d; };
$money = function ($x) { return $x === '' || $x === null ? '' : rtrim(rtrim(number_format((float) $x, 2, '.', ''), '0'), '.'); };
?>
<div class="crumbs"><a href="<?= site_url('packages') ?>">Packages</a> / <?= $p ? e($p['name']) : 'New' ?></div>
<div class="page-head"><div><h3><?= $p ? e($p['name']) : 'New package' ?></h3><p>Prices in ETB. The yearly price is 3 × the 4-month price, less the yearly discount.</p></div></div>
<?= form_open('packages/edit/' . ($p ? (int) $p['id'] : ''), array('class' => 'js-form')) ?>
<div class="grid side">
    <div class="stack">
        <div class="panel"><div class="panel-heading"><h4 class="panel-title"><i class="fas fa-tag"></i> Name and price</h4></div><div class="panel-body"><div class="form-grid">
            <div class="form-group full"><label class="control-label">Name</label><input class="form-control" name="name" value="<?= e($v('name')) ?>" maxlength="100"></div>
            <div class="form-group"><label class="control-label">Price for 4 months</label><input class="form-control" name="price_4_months" inputmode="decimal" value="<?= e($money($v('price_4_months', ''))) ?>"></div>
            <div class="form-group"><label class="control-label">Yearly discount (%)</label><input class="form-control" name="discount" inputmode="decimal" value="<?= e($money($v('discount', '0'))) ?>"></div>
            <div class="form-group"><label class="control-label">Order on the plans page</label><input class="form-control" type="number" name="package_order" value="<?= e($v('package_order', '0')) ?>"></div>
        </div></div></div>
        <div class="panel"><div class="panel-heading"><h4 class="panel-title"><i class="fas fa-ruler"></i> Limits<span class="panel-aside"><span class="text-muted">empty = no limit, 0 = none</span></span></h4></div><div class="panel-body"><div class="form-grid">
            <?php foreach (array('store_limit' => 'Stores', 'warehouse_limit' => 'Warehouses', 'user_limit' => 'Users', 'storage_limit' => 'Storage (MB)') as $k => $label): ?>
            <div class="form-group"><label class="control-label"><?= $label ?></label><input class="form-control" name="<?= $k ?>" inputmode="numeric" value="<?= e($v($k, '')) ?>" placeholder="∞"></div>
            <?php endforeach; ?>
        </div></div></div>
        <?php if ($addon_fields || $hr_ready): ?>
        <div class="panel"><div class="panel-heading"><h4 class="panel-title"><i class="fas fa-puzzle-piece"></i> Add-on prices<span class="panel-aside"><span class="text-muted">a year; empty = not offered</span></span></h4></div><div class="panel-body"><div class="form-grid">
            <?php foreach (array('addon_store_price' => 'Per extra store', 'addon_warehouse_price' => 'Per extra warehouse', 'addon_user_price' => 'Per 5 extra users') as $k => $label): if (!in_array($k, $addon_fields, true)) continue; ?>
            <div class="form-group"><label class="control-label"><?= $label ?></label><input class="form-control" name="<?= $k ?>" inputmode="decimal" value="<?= e($money($v($k, ''))) ?>"></div>
            <?php endforeach; ?>
            <?php if ($hr_ready): ?><div class="form-group"><label class="control-label">HR & payroll</label><input class="form-control" name="addon_hr_price" inputmode="decimal" value="<?= e($money($v('addon_hr_price', ''))) ?>"></div><?php endif; ?>
        </div></div></div>
        <?php endif; ?>
        <div class="panel"><div class="panel-heading"><h4 class="panel-title"><i class="fas fa-list-check"></i> Features on the website<span class="panel-aside"><span class="text-muted">one per line, at most 30</span></span></h4></div><div class="panel-body">
            <textarea class="form-control" name="features" rows="8"><?= e(implode("\n", $features)) ?></textarea>
        </div></div>
    </div>
    <div class="stack">
        <div class="panel"><div class="panel-heading"><h4 class="panel-title"><i class="fas fa-toggle-on"></i> Status</h4></div><div class="panel-body">
            <label class="check mb-md"><input type="checkbox" name="status" value="1" <?= $v('status', 1) ? 'checked' : '' ?>> On sale</label>
            <label class="check mb-md"><input type="checkbox" name="show_onwebsite" value="1" <?= $v('show_onwebsite', 1) ? 'checked' : '' ?>> Shown on the website</label>
            <label class="check"><input type="checkbox" name="recommended" value="1" <?= $v('recommended', 0) ? 'checked' : '' ?>> Recommended</label>
        </div></div>
        <div class="panel"><div class="panel-heading"><h4 class="panel-title"><i class="fas fa-cubes"></i> Modules</h4></div><div class="panel-body">
            <?php foreach ($modules as $m): ?>
            <label class="check mb-md"><input type="checkbox" name="modules[]" value="<?= e($m) ?>" <?= !empty($package_modules[$m]) ? 'checked' : '' ?>> <?= e(ucwords(str_replace('_', ' ', $m))) ?></label>
            <?php endforeach; ?>
            <div class="form-group mt-md"><label class="control-label">New module keys</label><input class="form-control" name="new_modules" placeholder="e.g. loyalty_points"><div class="hint">Comma separated; switched on for this package.</div></div>
        </div></div>
        <button class="btn btn-primary btn-block" type="submit"><i class="fas fa-save"></i> Save package</button>
    </div>
</div>
<?= form_close() ?>
