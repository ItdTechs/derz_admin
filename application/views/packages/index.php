<?php $lim = function ($v) { return $v === null || $v === '' ? '∞' : (int) $v; }; ?>
<div class="page-head">
    <div><p>What businesses can buy: prices, limits, modules, add-ons</p></div>
    <div class="page-actions"><a class="btn btn-primary" href="<?= site_url('packages/edit') ?>"><i class="fas fa-plus"></i> New package</a></div>
</div>
<div class="tabs-custom">
<ul class="nav nav-tabs">
    <li class="active"><a href="#plans" data-toggle="tab"><i class="fas fa-layer-group"></i> Packages</a></li>
    <li><a href="#methods" data-toggle="tab"><i class="fas fa-building-columns"></i> Payment methods</a></li>
</ul>
</div>
<div class="tab-content tab-content-plain">
<div class="tab-pane active" id="plans">
    <div class="grid three">
        <?php foreach ($packages as $p): $year = Billing_model::period_price($p, '1_year'); ?>
        <div class="panel">
            <div class="panel-heading">
                <h4 class="panel-title"><?= e($p['name']) ?> <?= (int) $p['recommended'] ? badge('Recommended', 'primary') : '' ?>
                    <span class="panel-aside"><?= (int) $p['status'] ? badge('On sale', 'success') : badge('Off', 'muted') ?></span></h4>
            </div>
            <div class="panel-body">
                <div style="font-size:1.6rem;font-weight:600"><?= money($p['price_4_months'] ?: $year['total'] / 3, 0) ?> <small class="text-muted" style="font-size:.85rem">/ 4 months</small></div>
                <div class="text-muted mb-md"><?= money($year['total'], 0) ?> a year<?= (float) $p['discount'] ? ' (' . (float) $p['discount'] . '% off)' : '' ?></div>
                <dl class="kv">
                    <dt>Stores / warehouses</dt><dd><?= $lim($p['store_limit']) ?> / <?= $lim($p['warehouse_limit']) ?></dd>
                    <dt>Users</dt><dd><?= $lim($p['user_limit']) ?></dd>
                    <dt>Add-ons</dt><dd><?= isset($p['addon_store_price']) && $p['addon_store_price'] !== null ? 'Yes' : 'No' ?><?= isset($p['addon_hr_price']) && $p['addon_hr_price'] !== null ? ' · HR ' . money($p['addon_hr_price'], 0) : '' ?></dd>
                    <dt>On the website</dt><dd><?= (int) $p['show_onwebsite'] ? 'Yes' : 'No' ?></dd>
                    <dt>Businesses on it</dt><dd><strong><?= (int) $p['businesses'] ?></strong></dd>
                </dl>
                <a class="btn btn-default btn-block mt-md" href="<?= site_url('packages/edit/' . (int) $p['id']) ?>"><i class="fas fa-pen"></i> Edit</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<div class="tab-pane" id="methods">
    <div class="grid side">
        <div class="panel">
            <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-building-columns"></i> Ways to pay<span class="panel-aside"><span class="text-muted">shown to businesses when they upgrade</span></span></h4></div>
            <div class="panel-body"><table class="table table-bordered table-hover table-condensed mb-none table-export table-mobile-cards" data-card-icon="fas fa-building-columns" data-card-title-col="0" data-card-skip-cols="-1">
                <thead><tr><th>Name</th><th>Details</th><th>Status</th><th class="num">Used</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($methods as $m): ?>
                <tr>
                    <td data-label="Name" class="cell-main"><?= e($m['name']) ?></td>
                    <td data-label="Details" style="white-space:pre-wrap;font-size:.85rem"><?= e($m['details'] ?? '') ?></td>
                    <td data-label="Status"><?= !isset($m['active']) || (int) $m['active'] ? badge('On', 'success') : badge('Off', 'muted') ?></td>
                    <td data-label="Used" class="num"><?= (int) $m['used'] ?></td>
                    <td data-label="" class="actions">
                        <button class="btn btn-circle btn-default icon" data-toggle="modal" data-target="#m-method" onclick='fillMethod(<?= json_encode(array("id" => (int) $m["id"], "name" => $m["name"], "details" => $m["details"] ?? "", "active" => (int) ($m["active"] ?? 1), "sort_order" => (int) ($m["sort_order"] ?? 0)), JSON_HEX_APOS | JSON_HEX_TAG) ?>)'><i class="fas fa-pen"></i></button>
                        <?php if (!(int) $m['used']): ?><button class="btn btn-danger icon btn-circle" data-post="<?= site_url('packages/method_delete/' . (int) $m['id']) ?>" data-confirm="Delete <?= e($m['name']) ?>?"><i class="fas fa-trash"></i></button><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
        <div class="panel"><div class="panel-body">
            <button class="btn btn-primary btn-block" data-toggle="modal" data-target="#m-method" onclick="fillMethod({id:0,name:'',details:'',active:1,sort_order:0})"><i class="fas fa-plus"></i> Add a payment method</button>
            <p class="text-muted mt-md mb-none">E.g. "CBE transfer" with the account number, or "telebirr" with the phone number. A method requests used can be switched off, not deleted.</p>
        </div></div>
    </div>
</div>
</div><!-- tab-content -->

<div class="modal fade" id="m-method" tabindex="-1" role="dialog"><div class="modal-dialog"><div class="modal-content">
    <?= form_open('packages/method_save', array('class' => 'js-form')) ?>
    <input type="hidden" name="id" id="mm-id">
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fas fa-building-columns"></i> Payment method</h4></div>
    <div class="modal-body">
        <div class="form-group mb-md"><label class="control-label">Name</label><input class="form-control" name="name" id="mm-name" maxlength="60"></div>
        <?php if ($methods_ready): ?>
        <div class="form-group mb-md"><label class="control-label">Details (account, phone, how to pay)</label><textarea class="form-control" name="details" id="mm-details" maxlength="1000"></textarea></div>
        <div class="form-grid">
            <div class="form-group"><label class="control-label">Order</label><input class="form-control" type="number" name="sort_order" id="mm-sort"></div>
            <div class="form-group"><label class="control-label">&nbsp;</label><label class="check"><input type="checkbox" name="active" value="1" id="mm-active"> Offered to businesses</label></div>
        </div>
        <?php endif; ?>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
    <?= form_close() ?>
</div></div></div>
<script>
function fillMethod(m) {
    document.getElementById('mm-id').value = m.id; document.getElementById('mm-name').value = m.name;
    var d = document.getElementById('mm-details'); if (d) { d.value = m.details; document.getElementById('mm-sort').value = m.sort_order; document.getElementById('mm-active').checked = !!m.active; }
}
</script>
