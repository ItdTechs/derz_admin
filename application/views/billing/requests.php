<div class="page-head">
    <div><p>Plans and add-ons the businesses asked for: check the payment, then approve</p></div>
    <form method="get" style="max-width:320px;width:100%"><input type="hidden" name="status" value="<?= e($status) ?>">
        <div class="input-group"><input type="search" class="form-control" name="q" value="<?= e($q) ?>" placeholder="Business or reference"><span class="input-group-btn"><button class="btn btn-default" type="submit"><i class="fa fa-search"></i></button></span></div></form>
</div>
<div class="panel"><div class="tabs-custom">
<ul class="nav nav-tabs">
    <?php foreach (array('pending' => array('Waiting', 'fa-clock'), 'approved' => array('Approved', 'fa-check'), 'rejected' => array('Rejected', 'fa-xmark')) as $k => $t): ?>
    <li class="<?= $status === $k ? 'active' : '' ?>"><a href="?status=<?= $k ?>"><i class="fas <?= $t[1] ?>"></i> <?= $t[0] ?> <span class="label label-<?= $k === 'pending' && $counts[$k] ? 'warning' : 'default' ?>"><?= (int) $counts[$k] ?></span></a></li>
    <?php endforeach; ?>
</ul></div>
    <div class="panel-body"><table class="table table-bordered table-hover table-condensed mb-none table-export table-mobile-cards" data-card-icon="fas fa-file-invoice-dollar" data-card-title-col="0" data-card-skip-cols="-1" data-card-subtitle-col="1" data-server-paged="1">
        <thead><tr><th>Business</th><th>Asked for</th><th>Paid with</th><th>Reference</th><th class="num">Amount</th><th>Sent</th><th></th></tr></thead>
        <tbody>
        
        <?php foreach ($rows as $t): ?>
        <tr>
            <td data-label=""><a class="cell-main" href="<?= site_url('businesses/view/' . (int) $t['business_id']) ?>#billing"><?= e($t['business_name']) ?></a><div class="cell-sub">#<?= (int) $t['id'] ?><?= (int) $t['renew'] ? ' · renewal' : '' ?></div></td>
            <td data-label="Asked for"><?= ($t['kind'] ?? 'plan') === 'addon' ? '<span class="label label-primary">Add-ons</span> ' . e(Billing_model::addon_summary($t))
                : e($t['package_name']) . ' <span class="cell-sub">' . e(str_replace('_', ' ', $t['billing_period'])) . '</span>' . (!empty($t['addon_amount']) && (float) $t['addon_amount'] > 0 ? '<div class="cell-sub">+ ' . e(Billing_model::addon_summary($t)) . '</div>' : '') ?></td>
            <td data-label="Paid with"><?= e($t['payment_method_name']) ?></td>
            <td data-label="Reference"><?= e($t['reference']) ?><?php if (!empty($t['receipt'])): ?><div><a href="<?= site_url('billing/receipt/' . (int) $t['id']) ?>" target="_blank"><i class="fas fa-paperclip"></i> Receipt</a></div><?php endif; ?>
                <?php if ($t['note'] !== ''): ?><div class="cell-sub"><?= e($t['note']) ?></div><?php endif; ?></td>
            <td data-label="Amount" class="num"><strong><?= money(Billing_model::total($t)) ?></strong></td>
            <td data-label="Sent" class="nowrap"><?= fmt_datetime($t['created_at']) ?><div class="cell-sub"><?= time_ago($t['created_at']) ?></div></td>
            <td data-label="" class="actions">
                <?php if ($t['status'] === 'pending' && can('billing.approve')): ?>
                <button class="btn btn-sm btn-primary" data-post="<?= site_url('billing/approve/' . (int) $t['id']) ?>" data-confirm="Approve <?= e($t['business_name']) ?> — <?= e(money(Billing_model::total($t))) ?>? The plan runs at once."><i class="fas fa-check"></i> Approve</button>
                <button class="btn btn-danger icon btn-circle" data-post="<?= site_url('billing/reject/' . (int) $t['id']) ?>" data-prompt="Why is it rejected? (the business sees it)"><i class="fas fa-xmark"></i></button>
                <?php else: ?>
                <?= status_badge($t['status']) ?><?php if ($t['approved_at']): ?><div class="cell-sub"><?= fmt_date($t['approved_at']) ?></div><?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?= pager($total, $page, $per) ?>
</div>
