<div class="page-head"><div><p>The platform: free trial, support, payment instructions, maintenance mode, Telegram</p></div></div>
<?= form_open('settings', array('class' => 'js-form')) ?>
<div class="grid two">
    <div class="stack">
        <div class="panel"><div class="panel-heading"><h4 class="panel-title"><i class="fas fa-gift"></i> Sign-up</h4></div><div class="panel-body"><div class="form-grid">
            <div class="form-group"><label class="control-label">Free trial (days)</label><input class="form-control" name="trial_days" inputmode="numeric" value="<?= e($s['trial_days']) ?>"></div>
        </div></div></div>
        <div class="panel"><div class="panel-heading"><h4 class="panel-title"><i class="fas fa-headset"></i> Support contacts<span class="panel-aside"><span class="text-muted">shown to businesses</span></span></h4></div><div class="panel-body"><div class="form-grid">
            <div class="form-group"><label class="control-label">Phone</label><input class="form-control" name="support_phone" value="<?= e($s['support_phone']) ?>"></div>
            <div class="form-group"><label class="control-label">Email</label><input class="form-control" name="support_email" value="<?= e($s['support_email']) ?>"></div>
            <div class="form-group full"><label class="control-label">Telegram</label><input class="form-control" name="support_telegram" value="<?= e($s['support_telegram']) ?>" placeholder="@derzsupport"></div>
        </div></div></div>
        <div class="panel"><div class="panel-heading"><h4 class="panel-title"><i class="fas fa-building-columns"></i> Payment instructions</h4></div><div class="panel-body">
            <textarea class="form-control" name="payment_instructions" maxlength="1000" rows="5"><?= e($s['payment_instructions']) ?></textarea>
            <div class="hint text-muted mt-md">Shown on the upgrade page of the app. The ways to pay are in <a href="<?= site_url('packages') ?>#methods">Packages › Payment methods</a>.</div>
        </div></div>
    </div>
    <div class="stack">
        <div class="panel"><div class="panel-heading"><h4 class="panel-title"><i class="fas fa-screwdriver-wrench"></i> Maintenance mode<span class="panel-aside"><?= $s['maintenance_mode'] ? badge('ON', 'danger') : badge('off', 'muted') ?></span></h4></div><div class="panel-body">
            <label class="check mb-md"><input type="checkbox" name="maintenance_mode" value="1" <?= $s['maintenance_mode'] ? 'checked' : '' ?>> <strong>Show the maintenance page to every business</strong></label>
            <div class="form-group mb-md"><label class="control-label">Message</label><textarea class="form-control" name="maintenance_message" maxlength="500"><?= e($s['maintenance_message']) ?></textarea></div>
            <div class="form-group"><label class="control-label">Expected back</label><input class="form-control" name="maintenance_until" maxlength="60" value="<?= e($s['maintenance_until']) ?>" placeholder="e.g. today 14:00"></div>
        </div></div>
        <div class="panel" id="telegram"><div class="panel-heading"><h4 class="panel-title"><i class="fab fa-telegram"></i> Telegram<span class="panel-aside"><?= $bot ? badge('@' . $bot, 'success') : badge('not set up', 'muted') ?></span></h4></div><div class="panel-body">
            <div class="form-group mb-md"><label class="control-label">Bot token</label><input class="form-control" name="telegram_bot_token" autocomplete="off" placeholder="<?= $bot ? 'saved — paste a new one to change it' : '123456:ABC…' ?>">
                <div class="hint">From @BotFather. Its messages go through the app (<?= $webhook ? 'webhook on' : 'webhook set when the app is on https' ?>).</div></div>
            <div class="form-grid">
                <div class="form-group"><label class="control-label">"Continue with Telegram" client ID</label><input class="form-control" name="telegram_login_client_id" value="<?= e($s['telegram_login_client_id']) ?>"></div>
                <div class="form-group"><label class="control-label">Client secret</label><input class="form-control" type="password" name="telegram_login_client_secret" autocomplete="new-password" placeholder="<?= $login_secret_saved ? 'saved — type to change' : '' ?>"></div>
            </div>
            <hr>
            <label class="control-label">Team chats <span class="text-muted">(new sign-ups and payment requests arrive here)</span></label>
            <ul class="list" style="margin:0 -18px">
                <?php if (!$team_chats): ?><li class="text-muted">No chat linked yet.</li><?php endif; ?>
                <?php foreach ($team_chats as $c): ?>
                <li><i class="fas <?= $c['chat_type'] === 'private' ? 'fa-user' : 'fa-users' ?>" style="color:#038F5C"></i><span class="grow"><?= e($c['title'] ?: $c['chat_id']) ?></span>
                    <button type="button" class="btn btn-sm btn-default text-danger" data-post="<?= site_url('settings/telegram_remove/' . (int) $c['id']) ?>" data-confirm="Remove this chat?"><i class="fas fa-xmark"></i></button></li>
                <?php endforeach; ?>
            </ul>
            <?php if ($bot): ?>
            <div class="page-actions mt-md">
                <button type="button" class="btn btn-default" id="tg-connect"><i class="fas fa-link"></i> Link a chat</button>
                <button type="button" class="btn btn-default" data-post="<?= site_url('settings/telegram_test') ?>"><i class="fas fa-paper-plane"></i> Send a test</button>
            </div>
            <div id="tg-links" class="alert alert-info mt-md hidden"></div>
            <?php endif; ?>
        </div></div>
    </div>
</div>
<div class="mt-md"><button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Save settings</button></div>
<?= form_close() ?>
<script>
(function () {
    var b = document.getElementById('tg-connect');
    if (!b) return;
    b.addEventListener('click', function () {
        derzSend('<?= site_url('settings/telegram_connect') ?>', new FormData(), function (r) {
            var box = document.getElementById('tg-links');
            if (r.status !== 'success') { derzToast(r.message, 'error'); return; }
            box.innerHTML = 'Open one of these within 30 minutes and press Start: <a href="' + r.private + '" target="_blank" rel="noopener">my chat</a> · <a href="' + r.group + '" target="_blank" rel="noopener">a group</a>. Then reload this page.';
            box.classList.remove('hidden');
        });
    });
})();
</script>
