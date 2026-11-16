<?php
$sub = $b['subscription'];
$suspended = ($b['business_status'] ?? 'active') === 'suspended';
$id = (int) $b['id'];
$u = $usage;
$addons = array_filter($b['addons']);
?>
<div class="crumbs"><a href="<?= site_url('businesses') ?>">Businesses</a> / #<?= $id ?></div>
<div class="page-head">
    <div class="who">
        <span class="avatar lg" style="<?= $suspended ? 'background:#555' : '' ?>"><?= e(initials($b['name'])) ?></span>
        <div>
            <h3><?= e($b['name']) ?></h3>
            <p><?= $suspended ? status_badge('suspended') : status_badge($b['status']) ?> <?= e($b['package_name'] ?: 'No package') ?>
                <?php if ($b['expire_date']): ?> · ends <?= fmt_date($b['expire_date']) ?> (<?= time_ago($b['expire_date']) ?>)<?php endif; ?>
                · <?= stage_badge($crm['stage']) ?></p>
        </div>
    </div>
    <div class="page-actions">
        <?php if (can('billing.record')): ?>
        <button class="btn btn-primary" data-toggle="modal" data-target="#m-payment"><i class="fas fa-money-bill-wave"></i> Record payment</button>
        <button class="btn btn-default" data-toggle="modal" data-target="#m-expiry"><i class="fas fa-calendar-plus"></i> End date</button>
        <?php endif; ?>
        <?php if (can('businesses.edit')): ?><button class="btn btn-default" data-toggle="modal" data-target="#m-edit"><i class="fas fa-pen"></i> Edit</button><?php endif; ?>
        <?php if (can('businesses.suspend') && $suspension_ready): ?>
            <?php if ($suspended): ?>
            <button class="btn btn-default" data-post="<?= site_url('businesses/reactivate/' . $id) ?>" data-confirm="Let <?= e($b['name']) ?> use DerZ again?"><i class="fas fa-unlock"></i> Reactivate</button>
            <?php else: ?>
            <button class="btn btn-default text-danger" data-toggle="modal" data-target="#m-suspend"><i class="fas fa-ban"></i> Suspend</button>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($suspended): ?>
<div class="alert alert-danger"><i class="fas fa-ban"></i><div><strong>Suspended</strong> <?= time_ago($b['suspended_at']) ?>: <?= e($b['suspended_reason']) ?>. Its people cannot use DerZ until it is reactivated.</div></div>
<?php endif; ?>

<div class="tabs-custom">
<ul class="nav nav-tabs">
    <li class="active"><a href="#overview" data-toggle="tab"><i class="fas fa-chart-pie"></i> Overview</a></li>
    <li><a href="#users" data-toggle="tab"><i class="fas fa-users"></i> Users <span class="label label-default"><?= count($users) ?></span></a></li>
    <?php if (can('billing.view')): ?><li><a href="#billing" data-toggle="tab"><i class="fas fa-receipt"></i> Payments <span class="label label-default"><?= count($payments) ?></span></a></li><?php endif; ?>
    <li><a href="#notes" data-toggle="tab"><i class="fas fa-note-sticky"></i> Account & notes <span class="label label-default"><?= count($notes) ?></span></a></li>
    <li><a href="#history" data-toggle="tab"><i class="fas fa-clock-rotate-left"></i> History</a></li>
</ul>
</div>
<div class="tab-content tab-content-plain">

<!-- overview -->
<div class="tab-pane active" id="overview">
    <?= stat_row(6) ?>
        <?= stat_tile('Sales (30 days)', number_format($u['sales_30d']), 'fa-cash-register', '', money_short($u['sales_30d_total'])) ?>
        <?= stat_tile('All sales', number_format($u['sales']), 'fa-chart-column', 'primary', money_short($u['sales_total']) . ' · last ' . time_ago($u['last_sale'])) ?>
        <?= stat_tile('Products', number_format($u['products']), 'fa-box', 'info', number_format($u['customers']) . ' customers · ' . number_format($u['suppliers']) . ' suppliers') ?>
        <?= stat_tile('Locations', number_format($u['stores'] + $u['warehouses']), 'fa-store', '', $u['stores'] . ' stores · ' . $u['warehouses'] . ' warehouses') ?>
        <?= stat_tile('Users', number_format($u['users']), 'fa-users', 'info', 'last active ' . time_ago($b['last_active'])) ?>
        <?= stat_tile('Purchases', number_format($u['purchases']), 'fa-truck-ramp-box', 'warning', money_short($u['purchases_total'])) ?>
    </div>
    <div class="grid side">
        <div class="panel">
            <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-chart-line"></i> Sales per day<span class="panel-aside"><span class="text-muted">last 30 days</span></span></h4></div>
            <div class="panel-body"><div class="chart-box"><canvas data-chart="<?= chart_attr('bar', array_map(function ($d) { return date('d M', strtotime($d)); }, array_keys($daily)), array(
                array('label' => 'Amount', 'data' => array_map(function ($d) { return $d[1]; }, array_values($daily)))), array('money' => true)) ?>"></canvas></div></div>
        </div>
        <div class="stack">
            <div class="panel">
                <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-id-card"></i> Subscription
                    <?php if (can('billing.record') && $sub): ?><span class="panel-aside"><button class="btn btn-default btn-xs" data-toggle="modal" data-target="#m-package">Change package</button></span><?php endif; ?></h4></div>
                <div class="panel-body">
                    <dl class="kv">
                        <dt>Package</dt><dd><?= e($b['package_name'] ?: '—') ?> <?= $sub && (int) $sub['free_trial'] ? badge('trial', 'info') : '' ?></dd>
                        <dt>Ends</dt><dd><?= fmt_datetime($b['expire_date']) ?></dd>
                        <dt>Last upgrade</dt><dd><?= fmt_date($sub['upgrade_lasttime'] ?? null) ?></dd>
                        <dt>Add-ons</dt><dd><?php if (!$addons): ?>—<?php else: foreach ($addons as $k => $n): ?><span class="tag"><?= $k === 'hr' ? 'HR & payroll' : '+' . $n . ' ' . $k . ($n > 1 ? 's' : '') ?></span><?php endforeach; endif; ?></dd>
                    </dl>
                </div>
            </div>
            <div class="panel">
                <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-building"></i> Details</h4></div>
                <div class="panel-body">
                    <dl class="kv">
                        <dt>Admin</dt><dd><?= e($b['admin_name']) ?> <span class="text-muted">(<?= e($b['admin_username']) ?>)</span></dd>
                        <dt>Phone</dt><dd><?= e($b['contact_number'] ?: $b['admin_phone']) ?></dd>
                        <dt>Email</dt><dd><?= e($b['business_email'] ?: '—') ?></dd>
                        <dt>Address</dt><dd><?= e(trim(($b['address'] ?? '') . ', ' . ($b['city'] ?? ''), ', ') ?: '—') ?></dd>
                        <dt>TIN / VAT</dt><dd><?= e(($b['tin'] ?? '') ?: '—') ?> / <?= e(($b['vat_number'] ?? '') ?: '—') ?></dd>
                        <dt>Joined</dt><dd><?= fmt_date($b['created_at']) ?> (<?= time_ago($b['created_at']) ?>)</dd>
                        <dt>Manager</dt><dd><?= e($b['agent_name'] ?: '—') ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- users -->
<div class="tab-pane" id="users">
    <div class="panel">
        <div class="panel-body"><table class="table table-bordered table-hover table-condensed mb-none table-export table-mobile-cards" data-card-icon="fas fa-user" data-card-title-col="0" data-card-skip-cols="-1" data-card-subtitle-col="1">
            <thead><tr><th>User</th><th>Role</th><th>Phone</th><th>Last sign-in</th><th>Status</th><?php if (can('users.manage')): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($users as $usr): ?>
            <tr>
                <td data-label=""><div class="who"><span class="avatar" style="background:#2b85c6"><?= e(initials($usr['firstname'] . ' ' . $usr['lastname'])) ?></span>
                    <div><span class="cell-main"><?= e(trim($usr['firstname'] . ' ' . $usr['lastname'])) ?></span> <?= $usr['online'] ? '<span class="dot-online" title="online"></span>' : '' ?>
                        <div class="cell-sub"><?= e($usr['username']) ?><?= $usr['email'] ? ' · ' . e($usr['email']) : '' ?></div></div></div></td>
                <td data-label="Role"><?= e($usr['role_name'] ?: '—') ?></td>
                <td data-label="Phone"><?= e($usr['phone']) ?></td>
                <td data-label="Last sign-in" class="nowrap"><?= $usr['online'] ? '<span class="label label-success">online now</span>' : time_ago($usr['last_login']) ?></td>
                <td data-label="Status"><?= (int) $usr['active'] ? badge('Active', 'success') : badge('Off', 'muted') ?></td>
                <?php if (can('users.manage')): ?>
                <td data-label="" class="actions">
                    <button class="btn btn-circle btn-default icon" data-post="<?= site_url("businesses/user_password/$id/" . (int) $usr['id']) ?>" data-confirm="Make a new password for <?= e($usr['username']) ?>? They are signed out."><i class="fas fa-key"></i> Password</button>
                    <?php if ($usr['online']): ?><button class="btn btn-circle btn-default icon" data-post="<?= site_url("businesses/user_signout/$id/" . (int) $usr['id']) ?>" data-confirm="Sign <?= e($usr['username']) ?> out of DerZ?"><i class="fas fa-right-from-bracket"></i></button><?php endif; ?>
                    <?php if ((int) $usr['id'] !== (int) $b['admin_id']): ?>
                    <button class="btn btn-circle btn-default icon" data-post="<?= site_url("businesses/user_active/$id/" . (int) $usr['id']) ?>" data-value-active="<?= (int) $usr['active'] ? '0' : '1' ?>"
                        data-confirm="<?= (int) $usr['active'] ? 'Switch off' : 'Switch on' ?> <?= e($usr['username']) ?>?"><?= (int) $usr['active'] ? '<i class="fas fa-user-slash"></i> Off' : '<i class="fas fa-user-check"></i> On' ?></button>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
</div>

<!-- payments -->
<?php if (can('billing.view')): ?>
<div class="tab-pane" id="billing">
    <div class="panel">
        <div class="panel-body"><table class="table table-bordered table-hover table-condensed mb-none table-export table-mobile-cards" data-card-icon="fas fa-receipt" data-card-title-col="1" data-card-skip-cols="-1" data-card-subtitle-col="0">
            <thead><tr><th>Date</th><th>What</th><th>Method</th><th>Reference</th><th class="num">Amount</th><th>Status</th><th>Period</th></tr></thead>
            <tbody>
            
            <?php foreach ($payments as $t): ?>
            <tr>
                <td data-label="Date" class="nowrap"><?= fmt_date($t['created_at']) ?></td>
                <td data-label="What"><?= ($t['kind'] ?? 'plan') === 'addon' ? 'Add-ons: ' . e(Billing_model::addon_summary($t)) : e($t['package_name']) . ' <span class="cell-sub">' . e(str_replace('_', ' ', $t['billing_period'])) . '</span>' ?></td>
                <td data-label="Method"><?= e($t['payment_method_name']) ?></td>
                <td data-label="Reference"><?= e($t['reference']) ?><?php if (!empty($t['receipt'])): ?> <a href="<?= site_url('billing/receipt/' . (int) $t['id']) ?>" target="_blank"><i class="fas fa-paperclip"></i></a><?php endif; ?></td>
                <td data-label="Amount" class="num"><?= money(Billing_model::total($t)) ?></td>
                <td data-label="Status"><?= status_badge($t['status']) ?></td>
                <td data-label="Period" class="nowrap"><?= $t['purchase_date'] ? fmt_date($t['purchase_date']) . ' – ' . fmt_date($t['expire_date']) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
</div>
<?php endif; ?>

<!-- account and notes -->
<div class="tab-pane" id="notes">
    <div class="grid side">
        <div class="panel">
            <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-note-sticky"></i> Notes</h4></div>
            <?php if (can('businesses.notes')): ?>
            <?= form_open('businesses/note/' . $id, array('class' => 'js-form panel-body')) ?>
                <div class="form-grid">
                    <div class="form-group full"><textarea class="form-control" name="note" placeholder="What happened: a call, a visit, a problem, a promise…"></textarea></div>
                    <div class="form-group"><label class="control-label">Kind</label><select class="form-control" name="kind"><?= options(array('note' => 'Note', 'call' => 'Phone call', 'visit' => 'Visit', 'email' => 'Email / message')) ?></select></div>
                    <?php if (can('businesses.assign')): ?><div class="form-group"><label class="control-label">Next follow-up</label><input class="form-control" type="date" name="next_follow_up" value="<?= e($crm['next_follow_up']) ?>"></div><?php endif; ?>
                </div>
                <button class="btn btn-primary mt-md" type="submit"><i class="fas fa-plus"></i> Add note</button>
            <?= form_close() ?>
            <?php endif; ?>
            <?php if (!$notes): ?><div class="empty"><i class="fas fa-note-sticky"></i>No notes yet.</div><?php else: ?>
            <ul class="timeline-list mb-md">
                <?php $icons = array('note' => 'fa-note-sticky', 'call' => 'fa-phone', 'visit' => 'fa-person-walking', 'email' => 'fa-envelope'); foreach ($notes as $n): ?>
                <li><div class="meta"><i class="fas <?= $icons[$n['kind']] ?? 'fa-note-sticky' ?>"></i> <?= e($n['staff_name']) ?> · <?= fmt_datetime($n['created_at']) ?></div><div style="white-space:pre-wrap"><?= e($n['note']) ?></div></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <div class="panel">
            <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-user-tie"></i> Account</h4></div>
            <?php if (can('businesses.assign')): ?>
            <?= form_open('businesses/crm/' . $id, array('class' => 'js-form panel-body')) ?>
                <div class="form-group mb-md"><label class="control-label">Account manager</label><select class="form-control" name="agent_id"><option value="">Nobody</option><?= options(array_column($agents, 'name', 'id'), $crm['agent_id']) ?></select></div>
                <div class="form-group mb-md"><label class="control-label">Stage</label><select class="form-control" name="stage"><?= options(array_map(function ($s) { return $s[0]; }, crm_stages()), $crm['stage']) ?></select></div>
                <div class="form-group mb-md"><label class="control-label">Tags</label><input class="form-control" name="tags" value="<?= e($crm['tags']) ?>" placeholder="pharmacy, Bole, referral"></div>
                <div class="form-group mb-md"><label class="control-label">Next follow-up</label><input class="form-control" type="date" name="next_follow_up" value="<?= e($crm['next_follow_up']) ?>"></div>
                <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Save</button>
            <?= form_close() ?>
            <?php else: ?>
            <div class="panel-body"><dl class="kv"><dt>Manager</dt><dd><?= e($b['agent_name'] ?: '—') ?></dd><dt>Stage</dt><dd><?= stage_badge($crm['stage']) ?></dd>
                <dt>Tags</dt><dd><?= e($crm['tags'] ?: '—') ?></dd><dt>Follow-up</dt><dd><?= fmt_date($crm['next_follow_up']) ?></dd></dl></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- history -->
<div class="tab-pane" id="history">
    <div class="grid two">
        <div class="panel">
            <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-user-shield"></i> What the team did</h4></div>
            <?php if (!$team_audit): ?><div class="empty">Nothing yet.</div><?php else: ?>
            <ul class="timeline-list mb-md"><?php foreach ($team_audit as $a): ?><li><div class="meta"><?= e($a['staff_name'] ?: 'System') ?> · <?= fmt_datetime($a['created_at']) ?></div><?= e($a['summary']) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </div>
        <div class="panel">
            <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-clock-rotate-left"></i> In the app<span class="panel-aside"><span class="text-muted">the business's audit log</span></span></h4></div>
            <?php if (!$app_audit): ?><div class="empty">Nothing yet.</div><?php else: ?>
            <ul class="timeline-list mb-md"><?php foreach ($app_audit as $a): ?><li><div class="meta"><?= e($a['user_name'] ?: '—') ?> · <?= fmt_datetime($a['created_at']) ?> · <?= e($a['action']) ?></div><?= e($a['summary']) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </div>
    </div>
</div>

</div><!-- tab-content -->

<!-- dialogs -->
<?php if (can('billing.record')): ?>
<div class="modal fade" id="m-payment" tabindex="-1" role="dialog"><div class="modal-dialog"><div class="modal-content">
    <?= form_open('businesses/record_payment/' . $id, array('class' => 'js-form')) ?>
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fas fa-money-bill-wave"></i> Record a payment</h4></div>
    <div class="modal-body">
        <p class="text-muted">Money received outside the app (bank, cash, mobile money): the plan runs at once, from today, or from the current end date when renewing the same package.</p>
        <div class="form-grid">
            <div class="form-group"><label class="control-label">Package</label><select class="form-control" name="package_id" id="pay-package"><?php foreach ($packages as $p): if ((int) $p['id'] === 1) continue; ?>
                <option value="<?= (int) $p['id'] ?>" data-four="<?= e(Billing_model::period_price($p, '4_months')['total']) ?>" data-year="<?= e(Billing_model::period_price($p, '1_year')['total']) ?>" <?= (int) $p['id'] === (int) $b['package_id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label class="control-label">Period</label><select class="form-control" name="billing_period" id="pay-period"><option value="1_year">A year</option><option value="4_months">4 months</option></select></div>
            <div class="form-group"><label class="control-label">Paid with</label><select class="form-control" name="payment_method"><?= options(array_column($methods, 'name', 'id')) ?></select></div>
            <div class="form-group"><label class="control-label">Reference</label><input class="form-control" name="reference" placeholder="Bank reference, receipt no."></div>
            <div class="form-group full"><label class="control-label">Note</label><input class="form-control" name="note" maxlength="255"></div>
        </div>
        <div class="alert alert-info mt-md mb-none"><i class="fas fa-calculator"></i><div>Amount: <strong id="pay-amount">—</strong></div></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit"><i class="fas fa-check"></i> Record and apply</button></div>
    <?= form_close() ?>
</div></div></div>
<div class="modal fade" id="m-expiry" tabindex="-1" role="dialog"><div class="modal-dialog"><div class="modal-content">
    <?= form_open('businesses/set_expiry/' . $id, array('class' => 'js-form')) ?>
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fas fa-calendar-plus"></i> End date</h4></div>
    <div class="modal-body">
        <p class="text-muted">Extend a trial, or correct a date. The package stays the same.</p>
        <div class="form-group mb-md"><label class="control-label">Ends on</label><input class="form-control" type="date" name="expire_date" value="<?= e($b['expire_date'] ? date('Y-m-d', strtotime($b['expire_date'])) : date('Y-m-d', strtotime('+7 days'))) ?>"></div>
        <div class="form-group mb-md"><label class="control-label">Kind</label><select class="form-control" name="as_trial"><option value="">Keep as it is</option><option value="1">Trial</option><option value="0">Paid</option></select></div>
        <div class="form-group"><label class="control-label">Why</label><input class="form-control" name="note" placeholder="e.g. trial extended after the demo"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
    <?= form_close() ?>
</div></div></div>
<div class="modal fade" id="m-package" tabindex="-1" role="dialog"><div class="modal-dialog"><div class="modal-content">
    <?= form_open('businesses/change_package/' . $id, array('class' => 'js-form')) ?>
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fas fa-layer-group"></i> Change package</h4></div>
    <div class="modal-body">
        <p class="text-muted">A correction: the business moves to another package until the same end date. Nothing is billed (use Record payment for a paid upgrade).</p>
        <div class="form-group"><label class="control-label">Package</label><select class="form-control" name="package_id"><?= options(array_column($packages, 'name', 'id'), $b['package_id']) ?></select></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Change</button></div>
    <?= form_close() ?>
</div></div></div>
<?php endif; ?>
<?php if (can('businesses.edit')): ?>
<div class="modal fade" id="m-edit" tabindex="-1" role="dialog"><div class="modal-dialog modal-lg"><div class="modal-content">
    <?= form_open('businesses/update/' . $id, array('class' => 'js-form')) ?>
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fas fa-pen"></i> Edit business</h4></div>
    <div class="modal-body"><div class="form-grid">
        <?php foreach (array('name' => 'Name', 'contact_number' => 'Phone', 'business_email' => 'Email', 'website' => 'Website', 'city' => 'City', 'tin' => 'TIN', 'vat_number' => 'VAT number') as $k => $label): ?>
        <div class="form-group"><label class="control-label"><?= $label ?></label><input class="form-control" name="<?= $k ?>" value="<?= e($b[$k] ?? '') ?>"></div>
        <?php endforeach; ?>
        <div class="form-group full"><label class="control-label">Address</label><textarea class="form-control" name="address"><?= e($b['address'] ?? '') ?></textarea></div>
    </div></div>
    <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
    <?= form_close() ?>
</div></div></div>
<?php endif; ?>
<?php if (can('businesses.suspend')): ?>
<div class="modal fade" id="m-suspend" tabindex="-1" role="dialog"><div class="modal-dialog"><div class="modal-content">
    <?= form_open('businesses/suspend/' . $id, array('class' => 'js-form', 'data-confirm' => 'Suspend ' . $b['name'] . '? Its people are signed out at once.')) ?>
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fas fa-ban"></i> Suspend <?= e($b['name']) ?></h4></div>
    <div class="modal-body">
        <p class="text-muted">The business cannot use DerZ until it is reactivated; its data stays. Its people see the reason.</p>
        <div class="form-group"><label class="control-label">Reason</label><textarea class="form-control" name="reason" placeholder="e.g. Payment not received. Call 09…"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button class="btn btn-danger" type="submit"><i class="fas fa-ban"></i> Suspend</button></div>
    <?= form_close() ?>
</div></div></div>
<?php endif; ?>
<script>
(function () {
    var pkg = document.getElementById('pay-package'), per = document.getElementById('pay-period'), out = document.getElementById('pay-amount');
    if (!pkg) return;
    function show() { var o = pkg.options[pkg.selectedIndex]; var v = per.value === '4_months' ? o.dataset.four : o.dataset.year; out.textContent = 'ETB ' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2 }); }
    pkg.addEventListener('change', show); per.addEventListener('change', show); show();
})();
</script>
