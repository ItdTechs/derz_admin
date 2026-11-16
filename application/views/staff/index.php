<div class="page-head">
    <div><p>The DerZ team's accounts: each with a role that says what they may do</p></div>
    <div class="page-actions">
        <?php if (can('roles.manage')): ?><a class="btn btn-default" href="<?= site_url('staff/roles') ?>"><i class="fas fa-key"></i> Roles</a><?php endif; ?>
        <button class="btn btn-primary" data-toggle="modal" data-target="#m-staff" onclick="fillStaff(null)"><i class="fas fa-user-plus"></i> Add staff</button>
    </div>
</div>
<div class="panel">
    <div class="panel-body"><table class="table table-bordered table-hover table-condensed mb-none table-export table-mobile-cards" data-card-icon="fas fa-user" data-card-title-col="0" data-card-skip-cols="-1">
        <thead><tr><th>Name</th><th>Role</th><th class="num">Businesses</th><th>Last sign-in</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($staff as $s): ?>
        <tr>
            <td data-label=""><div class="who"><span class="avatar" style="<?= (int) $s['active'] ? '' : 'background:#999' ?>"><?= e(initials($s['name'])) ?></span>
                <div><span class="cell-main"><?= e($s['name']) ?></span><?= (int) $s['id'] === (int) $me['id'] ? ' ' . badge('you', 'info') : '' ?><div class="cell-sub"><?= e($s['email']) ?><?= $s['phone'] ? ' · ' . e($s['phone']) : '' ?></div></div></div></td>
            <td data-label="Role"><?= (int) $s['is_owner'] ? badge($s['role_name'], 'primary', 'fa-crown') : e($s['role_name']) ?></td>
            <td data-label="Businesses" class="num"><?= (int) $s['businesses'] ? '<a href="' . site_url('businesses?agent=' . (int) $s['id']) . '">' . (int) $s['businesses'] . '</a>' : '0' ?></td>
            <td data-label="Last sign-in" class="nowrap"><?= time_ago($s['last_login_at']) ?><?= $s['last_ip'] ? '<div class="cell-sub">' . e($s['last_ip']) . '</div>' : '' ?></td>
            <td data-label="Status"><?= (int) $s['active'] ? badge('Active', 'success') : badge('Off', 'muted') ?><?= (int) $s['must_change_password'] ? ' ' . badge('new password', 'warning') : '' ?></td>
            <td data-label="" class="actions">
                <button class="btn btn-circle btn-default icon" data-toggle="modal" data-target="#m-staff" onclick='fillStaff(<?= json_encode(array("id" => (int) $s["id"], "name" => $s["name"], "email" => $s["email"], "phone" => $s["phone"], "role_id" => (int) $s["role_id"], "active" => (int) $s["active"]), JSON_HEX_APOS | JSON_HEX_TAG) ?>)'><i class="fas fa-pen"></i></button>
                <?php if ((int) $s['id'] !== (int) $me['id']): ?>
                <button class="btn btn-circle btn-default icon" data-post="<?= site_url('staff/reset_password/' . (int) $s['id']) ?>" data-confirm="Make a new temporary password for <?= e($s['name']) ?>? They are signed out."><i class="fas fa-key"></i></button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
<div class="panel">
    <div class="panel-heading"><h4 class="panel-title"><i class="fas fa-shield-halved"></i> Recent sign-in attempts</h4></div>
    <div class="panel-body"><table class="table table-bordered table-hover table-condensed mb-none table-export table-mobile-cards" data-card-icon="fas fa-shield-halved" data-card-title-col="1" data-card-skip-cols="-1" data-card-subtitle-col="0">
        <thead><tr><th>When</th><th>Email</th><th>Address</th><th>Result</th></tr></thead>
        <tbody><?php foreach ($attempts as $a): ?><tr><td data-label="When"><?= fmt_datetime($a['created_at']) ?></td><td data-label="Email"><?= e($a['email']) ?></td><td data-label="Address"><code><?= e($a['ip']) ?></code></td>
            <td data-label="Result"><?= (int) $a['success'] ? badge('Signed in', 'success') : badge('Failed', 'danger') ?></td></tr><?php endforeach; ?></tbody>
    </table></div>
</div>
<div class="modal fade" id="m-staff" tabindex="-1" role="dialog"><div class="modal-dialog"><div class="modal-content">
    <?= form_open('staff/save', array('class' => 'js-form')) ?>
    <input type="hidden" name="id" id="st-id">
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title" id="st-title"><i class="fas fa-user"></i> Staff</h4></div>
    <div class="modal-body">
        <div class="form-grid">
            <div class="form-group full"><label class="control-label">Name</label><input class="form-control" name="name" id="st-name"></div>
            <div class="form-group"><label class="control-label">Email (signs in with it)</label><input class="form-control" type="email" name="email" id="st-email"></div>
            <div class="form-group"><label class="control-label">Phone</label><input class="form-control" name="phone" id="st-phone"></div>
            <div class="form-group full"><label class="control-label">Role</label><select class="form-control" name="role_id" id="st-role"><?php foreach ($roles as $r): ?><option value="<?= (int) $r['id'] ?>"><?= e($r['name']) ?> — <?= e($r['description']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group full"><label class="check"><input type="checkbox" name="active" value="1" id="st-active" checked> Active (may sign in)</label></div>
        </div>
        <p class="text-muted mt-md mb-none" id="st-note">A temporary password is made and shown once; they choose their own at the first sign-in.</p>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
    <?= form_close() ?>
</div></div></div>
<script>
function fillStaff(s) {
    var n = !s; s = s || { id: 0, name: '', email: '', phone: '', role_id: <?= (int) ($roles[count($roles) - 1]['id'] ?? 0) ?>, active: 1 };
    document.getElementById('st-id').value = s.id; document.getElementById('st-name').value = s.name; document.getElementById('st-email').value = s.email;
    document.getElementById('st-phone').value = s.phone; document.getElementById('st-role').value = s.role_id; document.getElementById('st-active').checked = !!s.active;
    document.getElementById('st-note').style.display = n ? '' : 'none';
    document.getElementById('st-title').innerHTML = '<i class="fas fa-user"></i> ' + (n ? 'Add staff' : 'Change ' + s.name.replace(/</g, '&lt;'));
}
</script>
