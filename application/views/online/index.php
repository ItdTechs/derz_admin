<div class="page-head">
    <div><p>People using DerZ in the last <?= (int) $minutes ?> minutes · refreshes every minute</p></div>
</div>
<?= stat_row(3) ?>
    <?= stat_tile('People online', number_format(count($people)), 'fa-signal', '', array_sum(array_column($people, 'devices')) . ' sessions') ?>
    <?= stat_tile('Businesses', number_format(count($by_business)), 'fa-building', 'info', 'with someone online') ?>
    <?php if ($staff): ?><?= stat_tile('Team in the portal', number_format(count($staff)), 'fa-user-shield', 'primary') ?><?php endif; ?>
</div>
<div class="grid side">
    <div class="panel">
        <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-users"></i> People</h4></div>
        <div class="panel-body"><table class="table table-bordered table-hover table-condensed mb-none table-export table-mobile-cards" data-card-icon="fas fa-signal" data-card-title-col="0" data-card-skip-cols="-1" data-card-subtitle-col="1">
            <thead><tr><th>Person</th><th>Business</th><th>Role</th><th>Last click</th><th>Address</th><?php if (can('sessions.end')): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
            
            <?php foreach ($people as $p): ?>
            <tr>
                <td data-label=""><div class="who"><span class="dot-online"></span><div><span class="cell-main"><?= e($p['name']) ?></span><div class="cell-sub"><?= e($p['username']) ?> · <?= e($p['phone']) ?></div></div></div></td>
                <td data-label="Business"><a href="<?= site_url('businesses/view/' . (int) $p['business_id']) ?>"><?= e($p['business_name']) ?></a></td>
                <td data-label="Role"><?= e($p['role_name'] ?: ((int) $p['type'] === 1 ? 'Platform admin' : '—')) ?><?= $p['devices'] > 1 ? ' <span class="label label-default">' . (int) $p['devices'] . ' devices</span>' : '' ?></td>
                <td data-label="Last click" class="nowrap"><?= time_ago($p['timestamp']) ?></td>
                <td data-label="Address"><code><?= e($p['ip']) ?></code></td>
                <?php if (can('sessions.end')): ?><td data-label="" class="actions"><button class="btn btn-danger icon btn-circle" data-post="<?= site_url('online/end/' . rawurlencode($p['id'])) ?>" data-confirm="Sign <?= e($p['name']) ?> out?"><i class="fas fa-right-from-bracket"></i> Sign out</button></td><?php endif; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
    <div class="stack">
        <div class="panel">
            <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-building"></i> By business</h4></div>
            <?php if (!$by_business): ?><div class="empty">—</div><?php else: ?>
            <ul class="list"><?php foreach ($by_business as $b): ?><li><span class="dot-online"></span><a class="grow" href="<?= site_url('businesses/view/' . (int) $b['id']) ?>"><?= e($b['name']) ?></a><strong><?= (int) $b['people'] ?></strong></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </div>
        <?php if ($staff): ?>
        <div class="panel">
            <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-user-shield"></i> The team</h4></div>
            <ul class="list"><?php foreach ($staff as $s): ?>
                <li><span class="avatar"><?= e(initials($s['name'])) ?></span><div class="grow"><div class="cell-main"><?= e($s['name']) ?></div><div class="cell-sub"><?= e($s['role_name']) ?> · <?= time_ago($s['timestamp']) ?></div></div>
                <?php if ((int) $s['id'] !== (int) $me['id']): ?><button class="btn btn-default btn-sm" data-post="<?= site_url('online/end_staff/' . (int) $s['id']) ?>" data-confirm="Sign <?= e($s['name']) ?> out of the portal?"><i class="fas fa-right-from-bracket"></i></button><?php endif; ?></li>
            <?php endforeach; ?></ul>
        </div>
        <?php endif; ?>
    </div>
</div>
<script>setTimeout(function () { if (!document.querySelector('.modal.in')) location.reload(); }, 60000);</script>
