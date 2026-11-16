<?php $export = '?' . e(http_build_query(array_merge($_GET, array('export' => 'csv', 'from' => $from, 'to' => $to)))); ?>
<div class="page-head">
    <div><p>Every approved payment: plans and add-ons</p></div>
    <div class="page-actions"><a class="btn btn-default" href="<?= $export ?>"><i class="fas fa-file-csv"></i> Export</a></div>
</div>
<?= stat_row(2) ?>
    <?= stat_tile('Received', money($sum), 'fa-sack-dollar', '', fmt_date($from) . ' – ' . fmt_date($to)) ?>
    <?= stat_tile('Payments', number_format($total), 'fa-receipt', 'info', $total ? 'average ' . money($sum / $total, 0) : '') ?>
</div>
<div class="panel">
    <form class="panel-body filters" method="get">
        <div class="form-group"><label class="control-label">From</label><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
        <div class="form-group"><label class="control-label">To</label><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
        <div class="form-group"><label class="control-label">Kind</label><select class="form-control" name="kind"><?= options(array('' => 'Plans and add-ons', 'plan' => 'Plans', 'addon' => 'Add-ons'), $kind) ?></select></div>
        <div class="form-group grow"><label class="control-label">Search</label><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Business or reference"></div>
        <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Filter</button>
    </form>
    <div class="panel-body"><table class="table table-bordered table-hover table-condensed mb-none table-export table-mobile-cards" data-card-icon="fas fa-receipt" data-card-title-col="1" data-card-skip-cols="-1" data-card-subtitle-col="0" data-server-paged="1">
        <thead><tr><th>Date</th><th>Business</th><th>What</th><th>Method</th><th>Reference</th><th>Runs</th><th class="num">Amount</th></tr></thead>
        <tbody>
        
        <?php foreach ($rows as $t): ?>
        <tr>
            <td data-label="Date" class="nowrap"><?= fmt_date($t['approved_at'] ?: $t['created_at']) ?></td>
            <td data-label="Business"><a href="<?= site_url('businesses/view/' . (int) $t['business_id']) ?>"><?= e($t['business_name']) ?></a></td>
            <td data-label="What"><?= ($t['kind'] ?? 'plan') === 'addon' ? 'Add-ons: ' . e(Billing_model::addon_summary($t)) : e($t['package_name']) . ' <span class="cell-sub">' . e(str_replace('_', ' ', $t['billing_period'])) . '</span>' ?></td>
            <td data-label="Method"><?= e($t['payment_method_name']) ?></td>
            <td data-label="Reference"><?= e($t['reference']) ?><?php if (!empty($t['receipt'])): ?> <a href="<?= site_url('billing/receipt/' . (int) $t['id']) ?>" target="_blank"><i class="fas fa-paperclip"></i></a><?php endif; ?></td>
            <td data-label="Runs" class="nowrap"><?= $t['purchase_date'] ? fmt_date($t['purchase_date']) . ' – ' . fmt_date($t['expire_date']) : '—' ?></td>
            <td data-label="Amount" class="num"><strong><?= money(Billing_model::total($t)) ?></strong></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?= pager($total, $page, $per) ?>
</div>
