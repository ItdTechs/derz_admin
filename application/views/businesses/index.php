<?php
$statuses = array('' => 'All', 'active' => 'Paying', 'trial' => 'Trial', 'expiring' => 'Ending soon', 'expired' => 'Expired', 'suspended' => 'Suspended');
$q = $_GET;
$link = function (array $set) use ($q) { $q = array_merge($q, $set); unset($q['page']); return '?' . e(http_build_query(array_filter($q, function ($v) { return $v !== '' && $v !== null; }))); };
?>
<div class="page-head">
    <div><p><?= number_format($total) ?> <?= $total === 1 ? 'business' : 'businesses' ?><?= can('businesses.view_all') ? '' : ' you manage' ?></p></div>
    <div class="page-actions">
        <?php if (can('businesses.export')): ?><a class="btn btn-default" href="<?= site_url('businesses/export') . $link(array()) ?>"><i class="fas fa-file-csv"></i> Export</a><?php endif; ?>
    </div>
</div>

<div class="pills mb-md">
    <?php foreach ($statuses as $k => $label): ?><a class="<?= (string) $f['status'] === $k ? 'active' : '' ?>" href="<?= $link(array('status' => $k)) ?>"><?= $label ?></a><?php endforeach; ?>
    <a class="<?= $f['follow_up'] ? 'active' : '' ?>" href="<?= $link(array('follow_up' => $f['follow_up'] ? '' : '1')) ?>"><i class="fas fa-phone"></i> Follow-up due</a>
</div>

<div class="panel">
    <form class="panel-body filters" method="get">
        <input type="hidden" name="status" value="<?= e($f['status']) ?>">
        <div class="form-group grow"><label class="control-label">Search</label><input class="form-control" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Name, phone, email, admin, ID"></div>
        <div class="form-group"><label class="control-label">Package</label><select class="form-control" name="package" data-autosubmit><option value="">Any</option><?= options(array_column($packages, 'name', 'id'), $f['package']) ?></select></div>
        <div class="form-group"><label class="control-label">Stage</label><select class="form-control" name="stage" data-autosubmit><option value="">Any</option><?= options(array_map(function ($s) { return $s[0]; }, crm_stages()), $f['stage']) ?></select></div>
        <?php if (can('businesses.view_all')): ?>
        <div class="form-group"><label class="control-label">Account manager</label><select class="form-control" name="agent" data-autosubmit><option value="">Anyone</option><option value="none" <?= $f['agent'] === 'none' ? 'selected' : '' ?>>Nobody</option><?= options(array_column($agents, 'name', 'id'), $f['agent']) ?></select></div>
        <?php endif; ?>
        <div class="form-group"><label class="control-label">Sort</label><select class="form-control" name="sort" data-autosubmit><?= options(array('newest' => 'Newest', 'name' => 'Name', 'expiry' => 'Ending first', 'last_active' => 'Last active', 'sales' => 'Most sales (30 days)'), $sort) ?></select></div>
        <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Filter</button>
    </form>
    <div class="panel-body">
        <table class="table table-bordered table-hover table-condensed mb-none table-export table-mobile-cards" data-card-icon="fas fa-building" data-card-title-col="0" data-card-skip-cols="-1" data-server-paged="1">
            <thead><tr><th>Business</th><th>Plan</th><th>Ends</th><th>Account</th><th class="num">Users</th><th class="num">Sales 30d</th><th>Last active</th><th></th></tr></thead>
            <tbody>
            
            <?php foreach ($rows as $r): $suspended = $r['business_status'] === 'suspended'; ?>
            <tr>
                <td data-label="">
                    <div class="who">
                        <span class="avatar" style="<?= $suspended ? 'background:#555' : '' ?>"><?= e(initials($r['name'])) ?></span>
                        <div>
                            <a class="cell-main" href="<?= site_url('businesses/view/' . (int) $r['id']) ?>"><?= e($r['name']) ?></a>
                            <?php if (!empty($online[(int) $r['id']])): ?> <span class="dot-online" title="<?= (int) $online[(int) $r['id']] ?> online"></span><?php endif; ?>
                            <div class="cell-sub">#<?= (int) $r['id'] ?> · <?= e($r['admin_name']) ?> · <?= e($r['contact_number'] ?: $r['admin_phone']) ?><?= $r['city'] ? ' · ' . e($r['city']) : '' ?></div>
                        </div>
                    </div>
                </td>
                <td data-label="Plan"><?= $suspended ? status_badge('suspended') : status_badge($r['status']) ?> <span class="cell-sub"><?= e($r['package_name']) ?></span></td>
                <td data-label="Ends" class="nowrap"><?= fmt_date($r['expire_date']) ?></td>
                <td data-label="Account"><?= stage_badge($r['stage'] ?: 'onboarding') ?><?php if ($r['agent_name']): ?><div class="cell-sub"><i class="fas fa-user-tie"></i> <?= e($r['agent_name']) ?></div><?php endif; ?></td>
                <td data-label="Users" class="num"><?= (int) $r['users'] ?></td>
                <td data-label="Sales 30d" class="num"><?= money_short($r['sales_30d']) ?></td>
                <td data-label="Last active" class="nowrap"><?= time_ago($r['last_active']) ?></td>
                <td data-label="" class="actions"><a class="btn btn-circle btn-default icon" href="<?= site_url('businesses/view/' . (int) $r['id']) ?>">Open <i class="fas fa-chevron-right"></i></a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= pager($total, $page, $per) ?>
</div>
