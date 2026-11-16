<?php
$k = $kpi;
$hour = (int) date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
?>
<div class="dashboard-console-card mb-md">
    <div class="dashboard-console-main">
        <div class="dashboard-console-heading">
            <p class="dashboard-console-greeting"><?= $greet ?>, <?= e(strtok($me['name'], ' ')) ?></p>
            <h2 class="dashboard-console-title">Today on DerZ</h2>
            <p class="dashboard-console-date"><?= date('l, d F Y') ?></p>
        </div>
        <div class="dashboard-console-metrics">
            <div class="dashboard-metric"><span class="dashboard-metric-label">Paying businesses</span><span class="dashboard-metric-value"><?= number_format($k['active']) ?></span></div>
            <div class="dashboard-metric"><span class="dashboard-metric-label">New this month</span><span class="dashboard-metric-value is-positive"><?= number_format($k['new_month']) ?></span></div>
            <?php if ($online !== null): ?><div class="dashboard-metric"><span class="dashboard-metric-label">Online now</span><span class="dashboard-metric-value"><?= number_format($online) ?></span></div><?php endif; ?>
        </div>
    </div>
    <div class="dashboard-console-actions">
        <?php if (can('businesses.view')): ?><a href="<?= site_url('businesses') ?>" class="dashboard-action-pill"><i class="fas fa-building"></i><span>Businesses</span></a><?php endif; ?>
        <?php if (can('billing.view')): ?><a href="<?= site_url('billing') ?>" class="dashboard-action-pill"><i class="fas fa-file-invoice-dollar"></i><span>Requests</span><?php if ($k['pending']): ?><span class="dashboard-action-badge"><?= (int) $k['pending'] ?></span><?php endif; ?></a><?php endif; ?>
        <?php if (can('analytics.view')): ?><a href="<?= site_url('analytics') ?>" class="dashboard-action-pill"><i class="fas fa-chart-line"></i><span>Analytics</span></a><?php endif; ?>
    </div>
</div>

<?php if ($maintenance): ?>
<div class="alert alert-warning"><i class="fas fa-screwdriver-wrench"></i><div><strong>Maintenance mode is on</strong>: businesses see the maintenance page.
    <?php if (can('settings.manage')): ?><a href="<?= site_url('settings') ?>">Switch it off</a><?php endif; ?></div></div>
<?php endif; ?>

<?= stat_row(8) ?>
    <?php if (can('analytics.view')): ?>
    <?= stat_tile('Monthly recurring revenue', money_short($k['mrr']), 'fa-arrows-rotate', '', 'ARR ' . money_short($k['arr'])) ?>
    <?= stat_tile('Revenue this month', money_short($k['revenue_month']), 'fa-sack-dollar', 'primary', trend($k['revenue_month'], $k['revenue_last_month']) . ' vs last month') ?>
    <?php endif; ?>
    <?= stat_tile('Paying businesses', number_format($k['active']), 'fa-building-circle-check', '', number_format($k['trial']) . ' on trial', can('businesses.view') ? site_url('businesses?status=active') : '') ?>
    <?= stat_tile('New this month', number_format($k['new_month']), 'fa-user-plus', 'info', trend($k['new_month'], $k['new_last_month']) . ' vs last month', can('businesses.view') ? site_url('businesses?sort=newest') : '') ?>
    <?php if (can('billing.view')): ?>
    <?= stat_tile('Requests waiting', number_format($k['pending']), 'fa-file-invoice-dollar', $k['pending'] ? 'warning' : '', money($k['pending_amount']), site_url('billing')) ?>
    <?php endif; ?>
    <?php if ($online !== null): ?>
    <?= stat_tile('Online now', number_format($online), 'fa-signal', 'info', number_format($businesses_online) . ' businesses', site_url('online')) ?>
    <?php endif; ?>
    <?= stat_tile('Ending in 14 days', number_format($k['expiring']), 'fa-hourglass-end', $k['expiring'] ? 'danger' : '', number_format($k['expired']) . ' expired', can('businesses.view') ? site_url('businesses?status=expiring&sort=expiry') : '') ?>
    <?php if (can('analytics.view')): ?>
    <?= stat_tile('Sales recorded (30 days)', number_format($k['sales_30d']), 'fa-cash-register', 'primary', money_short($k['gmv_30d']) . ' through DerZ') ?>
    <?php endif; ?>
</div>

<?php if (can('analytics.view')): ?>
<div class="grid two">
    <div class="panel">
        <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-sack-dollar"></i> Revenue<span class="panel-aside"><span class="text-muted">last 6 months</span></span></h4></div>
        <div class="panel-body"><div class="chart-box sm"><canvas data-chart="<?= chart_attr('bar', array_column($revenue, 'label'), array(
            array('label' => 'Plans', 'data' => array_column($revenue, 'plan')), array('label' => 'Add-ons', 'data' => array_column($revenue, 'addon'))), array('stacked' => true, 'money' => true)) ?>"></canvas></div></div>
    </div>
    <div class="panel">
        <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-user-plus"></i> New businesses<span class="panel-aside"><span class="text-muted">and how many of them pay today</span></span></h4></div>
        <div class="panel-body"><div class="chart-box sm"><canvas data-chart="<?= chart_attr('line', array_column($signups, 'label'), array(
            array('label' => 'Signed up', 'data' => array_column($signups, 'signups')), array('label' => 'Paying now', 'data' => array_column($signups, 'paying'), 'color' => '#6a4bd8'))) ?>"></canvas></div></div>
    </div>
</div>
<div class="mb-md"></div>
<?php endif; ?>

<div class="grid two">
    <?php if (can('billing.view')): ?>
    <div class="panel">
        <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-file-invoice-dollar"></i> Payment requests waiting<span class="panel-aside"><a href="<?= site_url('billing') ?>">All</a></span></h4></div>
        <?php if (!$pending): ?>
            <div class="empty"><i class="fas fa-circle-check"></i>Nothing waiting.</div>
        <?php else: ?>
        <ul class="list">
            <?php foreach ($pending as $t): ?>
            <li>
                <span class="avatar"><?= e(initials($t['business_name'])) ?></span>
                <div class="grow">
                    <div class="cell-main"><a href="<?= site_url('businesses/view/' . (int) $t['business_id']) ?>"><?= e($t['business_name']) ?></a></div>
                    <div class="cell-sub"><?= ($t['kind'] ?? 'plan') === 'addon' ? 'Add-ons: ' . e(Billing_model::addon_summary($t)) : e($t['package_name'] . ' · ' . str_replace('_', ' ', $t['billing_period'])) ?>
                        · <?= e($t['payment_method_name']) ?> · <?= time_ago($t['created_at']) ?></div>
                </div>
                <strong class="nowrap"><?= money(Billing_model::total($t)) ?></strong>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="panel">
        <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-hourglass-end"></i> Ending soon<span class="panel-aside"><?php if (can('businesses.view')): ?><a href="<?= site_url('businesses?status=expiring&sort=expiry') ?>">All</a><?php endif; ?></span></h4></div>
        <?php if (!$expiring): ?>
            <div class="empty"><i class="fas fa-calendar-check"></i>No plan ends in the next 14 days.</div>
        <?php else: ?>
        <ul class="list">
            <?php foreach ($expiring as $b): ?>
            <li>
                <span class="avatar" style="background:#e5a00d"><?= e(initials($b['name'])) ?></span>
                <div class="grow">
                    <div class="cell-main"><a href="<?= site_url('businesses/view/' . (int) $b['id']) ?>"><?= e($b['name']) ?></a></div>
                    <div class="cell-sub"><?= e($b['package_name']) ?><?= (int) $b['free_trial'] ? ' (trial)' : '' ?> · <?= e($b['contact_number']) ?></div>
                </div>
                <span class="label label-<?= strtotime($b['expire_date']) < strtotime('+3 days') ? 'danger' : 'warning' ?>"><?= time_ago($b['expire_date']) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <div class="panel">
        <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-seedling"></i> Newest businesses</h4></div>
        <ul class="list">
            <?php foreach ($recent as $b): ?>
            <li>
                <span class="avatar" style="background:#2b85c6"><?= e(initials($b['name'])) ?></span>
                <div class="grow">
                    <div class="cell-main"><?php if (can('businesses.view')): ?><a href="<?= site_url('businesses/view/' . (int) $b['id']) ?>"><?= e($b['name']) ?></a><?php else: ?><?= e($b['name']) ?><?php endif; ?></div>
                    <div class="cell-sub"><?= e($b['admin_name']) ?> · <?= e($b['phone']) ?><?= $b['city'] ? ' · ' . e($b['city']) : '' ?></div>
                </div>
                <span class="text-muted nowrap"><?= time_ago($b['created_at']) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php if ($follow_ups): ?>
    <div class="panel">
        <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-phone"></i> My follow-ups</h4></div>
        <ul class="list">
            <?php foreach ($follow_ups as $f): ?>
            <li>
                <i class="fas fa-calendar-day" style="color:#038F5C"></i>
                <div class="grow"><a class="cell-main" href="<?= site_url('businesses/view/' . (int) $f['business_id']) ?>#notes"><?= e($f['name']) ?></a><div class="cell-sub"><?= stage_badge($f['stage']) ?></div></div>
                <span class="label label-<?= $f['next_follow_up'] <= date('Y-m-d') ? 'danger' : 'info' ?>"><?= fmt_date($f['next_follow_up']) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>
