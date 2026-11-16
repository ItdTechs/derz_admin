<?php
$k = $kpi;
$f = $funnel;
$pct = function ($a, $b) { return $b ? round(100 * $a / $b) : 0; };
?>
<div class="page-head">
    <div><p>Revenue, growth, retention and how the businesses use DerZ</p></div>
    <div class="page-actions">
        <div class="pills">
            <?php foreach (array(6, 12, 24) as $n): ?><a class="<?= $months === $n ? 'active' : '' ?>" href="?months=<?= $n ?>"><?= $n ?> months</a><?php endforeach; ?>
        </div>
        <a class="btn btn-default" href="<?= site_url('analytics/export') ?>"><i class="fas fa-file-csv"></i> Export</a>
    </div>
</div>

<?= stat_row(6) ?>
    <?= stat_tile('MRR', money_short($k['mrr']), 'fa-arrows-rotate', '', 'ARR ' . money_short($k['arr'])) ?>
    <?= stat_tile('Revenue this year', money_short($k['revenue_year']), 'fa-sack-dollar', 'primary', money_short($k['revenue_all']) . ' all time') ?>
    <?= stat_tile('Average per paying business', money($k['arpu'], 0), 'fa-user-tag', 'info', 'per month') ?>
    <?= stat_tile('Trial to paid', $k['conversion'] . '%', 'fa-filter-circle-dollar', $k['conversion'] >= 20 ? '' : 'warning', 'of businesses past their trial') ?>
    <?= stat_tile('Churn (30 days)', $k['churn_rate'] . '%', 'fa-person-walking-arrow-right', $k['churn_rate'] > 5 ? 'danger' : '', number_format($k['churned_30d']) . ' paid plans not renewed') ?>
    <?= stat_tile('People active (7 days)', number_format($k['users_active_7d']), 'fa-users', 'info', 'of ' . number_format($k['users']) . ' app users') ?>
</div>

<div class="grid two">
    <div class="panel">
        <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-sack-dollar"></i> Revenue per month<span class="panel-aside"><span class="text-muted">approved payments</span></span></h4></div>
        <div class="panel-body"><div class="chart-box"><canvas data-chart="<?= chart_attr('bar', array_column($revenue, 'label'), array(
            array('label' => 'Plans', 'data' => array_column($revenue, 'plan')), array('label' => 'Add-ons', 'data' => array_column($revenue, 'addon'))), array('stacked' => true, 'money' => true)) ?>"></canvas></div></div>
    </div>
    <div class="panel">
        <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-user-plus"></i> Growth<span class="panel-aside"><span class="text-muted">new businesses per month</span></span></h4></div>
        <div class="panel-body"><div class="chart-box"><canvas data-chart="<?= chart_attr('line', array_column($signups, 'label'), array(
            array('label' => 'Signed up', 'data' => array_column($signups, 'signups')), array('label' => 'Paying today', 'data' => array_column($signups, 'paying'), 'color' => '#6a4bd8'))) ?>"></canvas></div></div>
    </div>
    <div class="panel">
        <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-cash-register"></i> Sales through DerZ<span class="panel-aside"><span class="text-muted">what the businesses record</span></span></h4></div>
        <div class="panel-body"><div class="chart-box"><canvas data-chart="<?= chart_attr('bar', array_column($gmv, 'label'), array(
            array('label' => 'Sales amount', 'data' => array_column($gmv, 'amount'))), array('money' => true)) ?>"></canvas></div></div>
    </div>
    <div class="panel">
        <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-store"></i> Businesses selling per day<span class="panel-aside"><span class="text-muted">last 30 days</span></span></h4></div>
        <div class="panel-body"><div class="chart-box"><canvas data-chart="<?= chart_attr('line', array_map(function ($d) { return date('d M', strtotime($d)); }, array_keys($daily)), array(
            array('label' => 'Businesses with a sale', 'data' => array_values($daily)))) ?>"></canvas></div></div>
    </div>
</div>
<div class="mb-md"></div>

<div class="grid three">
    <div class="panel">
        <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-layer-group"></i> Plans running</h4></div>
        <div class="panel-body">
            <?php if ($plan_mix): ?>
            <div class="chart-box sm"><canvas data-chart="<?= chart_attr('doughnut', array_column($plan_mix, 'name'), array(array('label' => 'Businesses', 'data' => array_map('intval', array_column($plan_mix, 'n'))))) ?>"></canvas></div>
            <?php else: ?><div class="empty">No running plans.</div><?php endif; ?>
        </div>
    </div>
    <div class="panel">
        <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-filter"></i> Funnel<span class="panel-aside"><span class="text-muted">all businesses</span></span></h4></div>
        <div class="panel-body">
            <?php foreach (array('signed_up' => 'Signed up', 'products' => 'Added products', 'sold' => 'Made a sale', 'paid' => 'Paid') as $key => $label): ?>
            <div class="funnel-row"><span><?= $label ?></span><div class="bar"><span style="width:<?= $pct($f[$key], $f['signed_up']) ?>%"></span></div><strong class="text-right"><?= number_format($f[$key]) ?></strong></div>
            <?php endforeach; ?>
            <p class="text-muted mb-none" style="font-size:.82rem"><?= $pct($f['sold'], $f['signed_up']) ?>% make a sale · <?= $pct($f['paid'], $f['sold']) ?>% of those pay</p>
        </div>
    </div>
    <div class="panel">
        <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-location-dot"></i> Cities</h4></div>
        <div class="panel-body">
            <?php $max = $cities ? max(array_column($cities, 'n')) : 1; foreach ($cities as $c): ?>
            <div class="funnel-row"><span><?= e($c['city']) ?></span><div class="bar"><span style="width:<?= $pct($c['n'], $max) ?>%"></span></div><strong class="text-right"><?= (int) $c['n'] ?></strong></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<div class="mb-md"></div>

<div class="grid two">
    <div class="panel">
        <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-people-group"></i> Signup cohorts<span class="panel-aside"><span class="text-muted">still running / paying today</span></span></h4></div>
        <div class="panel-body"><table class="table table-bordered table-hover table-condensed mb-none table-export table-mobile-cards" data-card-icon="fas fa-calendar" data-card-title-col="0" data-card-skip-cols="-1">
            <thead><tr><th>Month</th><th class="num">Signed up</th><th class="num">Still running</th><th class="num">Paying</th><th>Kept</th></tr></thead>
            <tbody><?php foreach (array_reverse($cohorts) as $c): ?>
                <tr><td><?= e($c['label']) ?></td><td class="num"><?= $c['signups'] ?></td><td class="num"><?= $c['running'] ?></td><td class="num"><?= $c['paying'] ?></td>
                    <td style="min-width:120px"><div class="bar"><span style="width:<?= $pct($c['running'], $c['signups']) ?>%"></span></div><small class="text-muted"><?= $pct($c['running'], $c['signups']) ?>%</small></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    </div>
    <div class="panel">
        <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-building-columns"></i> How businesses pay<span class="panel-aside"><span class="text-muted">last 12 months</span></span></h4></div>
        <div class="panel-body"><table class="table table-bordered table-hover table-condensed mb-none table-export table-mobile-cards" data-card-icon="fas fa-building-columns" data-card-title-col="0" data-card-skip-cols="-1">
            <thead><tr><th>Method</th><th class="num">Payments</th><th class="num">Amount</th></tr></thead>
            <tbody>
            <?php foreach ($methods as $m): ?><tr><td><?= e($m['name']) ?></td><td class="num"><?= (int) $m['n'] ?></td><td class="num"><?= money($m['amount']) ?></td></tr><?php endforeach; ?></tbody>
        </table></div>
    </div>
    <div class="panel">
        <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-trophy"></i> Selling most<span class="panel-aside"><span class="text-muted">last 30 days</span></span></h4></div>
        <div class="panel-body"><table class="table table-bordered table-hover table-condensed mb-none table-export table-mobile-cards" data-card-icon="fas fa-trophy" data-card-title-col="0" data-card-skip-cols="-1" data-card-subtitle-col="1">
            <thead><tr><th>Business</th><th>Package</th><th class="num">Sales</th><th class="num">Amount</th></tr></thead>
            <tbody>
            <?php foreach ($top as $t): ?><tr>
                <td data-label="Business"><a href="<?= site_url('businesses/view/' . (int) $t['id']) ?>"><?= e($t['name']) ?></a></td>
                <td data-label="Package"><?= e($t['package_name']) ?></td>
                <td data-label="Sales" class="num"><?= number_format($t['sales']) ?></td><td data-label="Amount" class="num"><?= money_short($t['amount']) ?></td></tr><?php endforeach; ?></tbody>
        </table></div>
    </div>
    <div class="panel">
        <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-triangle-exclamation"></i> At risk<span class="panel-aside"><span class="text-muted">running plan, no sign-in or sale for 14 days</span></span></h4></div>
        <div class="panel-body"><table class="table table-bordered table-hover table-condensed mb-none table-export table-mobile-cards" data-card-icon="fas fa-triangle-exclamation" data-card-title-col="0" data-card-skip-cols="-1" data-card-subtitle-col="1">
            <thead><tr><th>Business</th><th>Plan</th><th>Last sign-in</th><th>Last sale</th></tr></thead>
            <tbody>
            <?php foreach ($at_risk as $b): ?><tr>
                <td data-label="Business"><a href="<?= site_url('businesses/view/' . (int) $b['id']) ?>"><?= e($b['name']) ?></a></td>
                <td data-label="Plan"><?= e($b['package_name']) ?><?= (int) $b['free_trial'] ? ' <span class="label label-info">trial</span>' : '' ?></td>
                <td data-label="Last sign-in"><?= time_ago($b['last_login']) ?></td><td data-label="Last sale"><?= time_ago($b['last_sale']) ?></td></tr><?php endforeach; ?></tbody>
        </table></div>
    </div>
</div>
