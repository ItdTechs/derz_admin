<div class="page-head"><div><p><?= $forced ? 'Choose your own password before you go on.' : 'Your other devices will be signed out.' ?></p></div></div>
<div class="panel" style="max-width:520px">
    <?= form_open('account/password', array('class' => 'js-form panel-body')) ?>
        <div class="form-group mb-md"><label class="control-label">Current password<?= $forced ? ' (the temporary one)' : '' ?></label><input class="form-control" type="password" name="current" autocomplete="current-password"></div>
        <div class="form-group mb-md"><label class="control-label">New password</label><input class="form-control" type="password" name="password" autocomplete="new-password"><div class="hint">At least 10 characters, with letters and numbers.</div></div>
        <div class="form-group mb-md"><label class="control-label">New password again</label><input class="form-control" type="password" name="password_confirm" autocomplete="new-password"></div>
        <button class="btn btn-primary" type="submit"><i class="fas fa-key"></i> Change password</button>
    <?= form_close() ?>
</div>
