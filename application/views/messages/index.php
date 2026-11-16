<?php
$a = $edit ?: array('id' => 0, 'title' => '', 'body' => '', 'level' => 'info', 'audience' => 'all', 'starts_at' => '', 'ends_at' => '', 'active' => 1);
$levels = array('info' => 'Information (blue)', 'success' => 'Good news (green)', 'warning' => 'Warning (yellow)', 'danger' => 'Important (red)');
$now = date('Y-m-d H:i:s');
?>
<div class="page-head"><div><p>A message at the top of the app for all or some businesses, and Telegram broadcasts</p></div></div>
<?php if (!$ready): ?><div class="alert alert-warning"><i class="fas fa-database"></i> Run SQL step 45 to use announcements.</div><?php endif; ?>
<div class="grid side">
    <div class="panel">
        <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-bullhorn"></i> Announcements</h4></div>
        <div class="panel-body"><table class="table table-bordered table-hover table-condensed mb-none table-export table-mobile-cards" data-card-icon="fas fa-bullhorn" data-card-title-col="0" data-card-skip-cols="-1">
            <thead><tr><th>Message</th><th>Who sees it</th><th>When</th><th>Status</th><th></th></tr></thead>
            <tbody>
            
            <?php foreach ($items as $it):
                $live = (int) $it['active'] && $it['starts_at'] <= $now && (!$it['ends_at'] || $it['ends_at'] >= $now); ?>
            <tr>
                <td data-label=""><span class="label label-<?= e($it['level']) ?>"><?= e($it['level']) ?></span> <span class="cell-main"><?= e($it['title']) ?></span><div class="cell-sub"><?= e(mb_strimwidth($it['body'], 0, 110, '…')) ?></div></td>
                <td data-label="Who sees it"><?= e($audiences[$it['audience']] ?? $it['audience']) ?></td>
                <td data-label="When" class="nowrap"><?= fmt_date($it['starts_at']) ?> – <?= $it['ends_at'] ? fmt_date($it['ends_at']) : 'until switched off' ?></td>
                <td data-label="Status"><?= $live ? badge('Showing', 'success') : ((int) $it['active'] ? badge('Scheduled / ended', 'muted') : badge('Off', 'muted')) ?></td>
                <td data-label="" class="actions"><a class="btn btn-circle btn-default icon" href="?edit=<?= (int) $it['id'] ?>"><i class="fas fa-pen"></i></a>
                    <button class="btn btn-danger icon btn-circle" data-post="<?= site_url('messages/delete/' . (int) $it['id']) ?>" data-confirm="Delete this announcement?"><i class="fas fa-trash"></i></button></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
    <div class="stack">
        <div class="panel">
            <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-<?= $edit ? 'pen' : 'plus' ?>"></i> <?= $edit ? 'Change announcement' : 'New announcement' ?><span class="panel-aside"><?php if ($edit): ?><a href="<?= site_url('messages') ?>">New</a><?php endif; ?></span></h4></div>
            <?= form_open('messages/save', array('class' => 'js-form panel-body')) ?>
                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                <div class="form-group mb-md"><label class="control-label">Title</label><input class="form-control" name="title" maxlength="150" value="<?= e($a['title']) ?>"></div>
                <div class="form-group mb-md"><label class="control-label">Message</label><textarea class="form-control" name="body" maxlength="2000"><?= e($a['body']) ?></textarea></div>
                <div class="form-grid">
                    <div class="form-group"><label class="control-label">Look</label><select class="form-control" name="level"><?= options($levels, $a['level']) ?></select></div>
                    <div class="form-group"><label class="control-label">Who sees it</label><select class="form-control" name="audience"><?php foreach ($audiences as $k => $label): ?><option value="<?= e($k) ?>" <?= $k === $a['audience'] ? 'selected' : '' ?>><?= e($label) ?> (<?= (int) $counts[$k] ?>)</option><?php endforeach; ?></select></div>
                    <div class="form-group"><label class="control-label">From</label><input class="form-control" type="date" name="starts_at" value="<?= e($a['starts_at'] ? date('Y-m-d', strtotime($a['starts_at'])) : date('Y-m-d')) ?>"></div>
                    <div class="form-group"><label class="control-label">Until (optional)</label><input class="form-control" type="date" name="ends_at" value="<?= e($a['ends_at'] ? date('Y-m-d', strtotime($a['ends_at'])) : '') ?>"></div>
                </div>
                <label class="check mt-md mb-md"><input type="checkbox" name="active" value="1" <?= (int) $a['active'] ? 'checked' : '' ?>> Switched on</label>
                <button class="btn btn-primary btn-block" type="submit"><i class="fas fa-save"></i> Save</button>
            <?= form_close() ?>
        </div>
        <div class="panel">
            <div class="panel-heading"><h4 class="panel-title"><i class="fab fa-telegram"></i> Telegram broadcast</h4></div>
            <?php if (!$telegram): ?><div class="panel-body text-muted">Set up the DerZ Telegram bot in Settings to send messages to the chats the businesses linked.</div><?php else: ?>
            <?= form_open('messages/broadcast', array('class' => 'js-form panel-body', 'data-confirm' => 'Send this Telegram message now?')) ?>
                <div class="form-group mb-md"><label class="control-label">Send to</label><select class="form-control" name="audience"><?= options($audiences) ?></select></div>
                <div class="form-group mb-md"><label class="control-label">Message</label><textarea class="form-control" name="text" maxlength="3500" placeholder="e.g. New in DerZ: …"></textarea></div>
                <button class="btn btn-primary btn-block" type="submit" data-busy="Sending"><i class="fab fa-telegram"></i> Send</button>
            <?= form_close() ?>
            <?php endif; ?>
        </div>
    </div>
</div>
