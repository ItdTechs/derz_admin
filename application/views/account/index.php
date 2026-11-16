<div class="page-head"><div class="who"><span class="avatar lg"><?= e(initials($me['name'])) ?></span><div><p><?= e($me['role_name']) ?> · <?= e($me['email']) ?></p></div></div></div>
<div class="grid two">
    <div class="panel">
        <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-user"></i> Profile</h4></div>
        <?= form_open('account', array('class' => 'js-form panel-body')) ?>
            <div class="form-group mb-md"><label class="control-label">Name</label><input class="form-control" name="name" value="<?= e($me['name']) ?>"></div>
            <div class="form-group mb-md"><label class="control-label">Phone</label><input class="form-control" name="phone" value="<?= e($me['phone']) ?>"></div>
            <div class="form-group mb-md"><label class="control-label">Email</label><input class="form-control" value="<?= e($me['email']) ?>" disabled><div class="hint">An Owner or administrator changes it in Staff.</div></div>
            <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Save</button>
        <?= form_close() ?>
    </div>
    <div class="panel">
        <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-shield-halved"></i> Security</h4></div>
        <div class="panel-body">
            <dl class="kv">
                <dt>Last sign-in</dt><dd><?= fmt_datetime($me['last_login_at']) ?> <span class="text-muted">from <?= e($me['last_ip']) ?></span></dd>
                <dt>Role</dt><dd><?= e($me['role_name']) ?></dd>
                <dt>Signed out after</dt><dd><?= (int) config_item('idle_minutes') ?> idle minutes</dd>
            </dl>
            <a class="btn btn-default mt-md" href="<?= site_url('account/password') ?>"><i class="fas fa-key"></i> Change password</a>
        </div>
    </div>
</div>
