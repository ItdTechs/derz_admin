<div class="page-head">
    <div><p>Everything the team did in the portal</p></div>
    <div class="page-actions"><a class="btn btn-default" href="?<?= e(http_build_query(array_merge($_GET, array('export' => 'csv')))) ?>"><i class="fas fa-file-csv"></i> Export</a></div>
</div>
<div class="panel">
    <form class="panel-body filters" method="get">
        <div class="form-group"><label class="control-label">Who</label><select class="form-control" name="staff_id"><option value="">Anyone</option><?= options(array_column($staff, 'name', 'id'), $f['staff_id']) ?></select></div>
        <div class="form-group"><label class="control-label">Action</label><select class="form-control" name="action"><option value="">Any</option><?= options(array_combine($actions, $actions) ?: array(), $f['action']) ?></select></div>
        <div class="form-group"><label class="control-label">From</label><input class="form-control" type="date" name="from" value="<?= e($f['from']) ?>"></div>
        <div class="form-group"><label class="control-label">To</label><input class="form-control" type="date" name="to" value="<?= e($f['to']) ?>"></div>
        <div class="form-group grow"><label class="control-label">Search</label><input class="form-control" type="search" name="q" value="<?= e($f['q']) ?>"></div>
        <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Filter</button>
    </form>
    <div class="panel-body"><table class="table table-bordered table-hover table-condensed mb-none table-export table-mobile-cards" data-card-icon="fas fa-clipboard-list" data-card-title-col="2" data-card-skip-cols="-1" data-card-subtitle-col="0" data-server-paged="1">
        <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Business</th><th>What</th><th>Address</th></tr></thead>
        <tbody>
        
        <?php foreach ($rows as $a): ?>
        <tr>
            <td data-label="When" class="nowrap"><?= fmt_datetime($a['created_at']) ?></td>
            <td data-label="Who"><?= e($a['staff_name'] ?: '—') ?></td>
            <td data-label="Action"><span class="label label-default"><?= e($a['action']) ?></span> <span class="cell-sub"><?= e($a['entity']) ?></span></td>
            <td data-label="Business"><?= $a['business_id'] ? '<a href="' . site_url('businesses/view/' . (int) $a['business_id']) . '">' . e($a['business_name'] ?: '#' . $a['business_id']) . '</a>' : '—' ?></td>
            <td data-label="What"><?= e($a['summary']) ?></td>
            <td data-label="Address"><code><?= e($a['ip']) ?></code></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?= pager($total, $page, $per) ?>
</div>
