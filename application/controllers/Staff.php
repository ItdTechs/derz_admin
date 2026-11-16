<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** The team: staff accounts (agents, support, finance ...) and roles with their permissions. */
class Staff extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model(array('staff_model', 'online_model'));
    }

    public function index()
    {
        $this->need('staff.manage');
        $this->render('staff/index', array('title' => 'Staff', 'staff' => $this->staff_model->all(), 'roles' => $this->staff_model->roles(),
            'attempts' => $this->staff_model->login_attempts(15)));
    }

    /** Create / change an account. A new account gets a temporary password (shown once). */
    public function save()
    {
        $this->need_post();
        $this->need('staff.manage');
        $id = (int) $this->input->post('id');
        $role = $this->staff_model->role((int) $this->input->post('role_id'));
        // only an Owner gives the Owner role
        if ($role && (int) $role['is_owner'] === 1 && !$this->auth->is_owner()) {
            $this->json(array('status' => 'fail', 'errors' => array('role_id' => 'Only an Owner can make someone Owner.')));
        }
        if ($id && ($current = $this->staff_model->get($id)) && (int) $current['is_owner'] === 1 && !$this->auth->is_owner()) {
            $this->json(array('status' => 'fail', 'message' => 'Only an Owner can change an Owner account.'));
        }
        if ($id === $this->auth->id() && !$this->input->post('active')) {
            $this->json(array('status' => 'fail', 'errors' => array('active' => 'You cannot switch off your own account.')));
        }
        $temp = $id ? '' : self::temp_password();
        $r = $this->staff_model->save($id, $this->input->post(), $temp);
        if (is_array($r)) {
            $this->json(array('status' => 'fail', 'errors' => array($r['field'] => $r['error'])));
        }
        if ($id && !$this->input->post('active')) {
            $this->online_model->end_staff_sessions($id);
        }
        audit($id ? 'update' : 'create', 'staff', $r, ($id ? 'Changed staff ' : 'Added staff ') . trim((string) $this->input->post('name')) . ' (' . ($role['name'] ?? '') . ')');
        if ($temp !== '') {
            $this->json(array('status' => 'success', 'password' => $temp, 'message' => 'Account made. Give this temporary password to ' . trim((string) $this->input->post('name'))
                . ': they choose their own at the first sign-in.'));
        }
        $this->flash('success', 'Saved.');
        $this->json(array('status' => 'success', 'url' => site_url('staff')));
    }

    public function reset_password($id = 0)
    {
        $this->need_post();
        $this->need('staff.manage');
        $s = $this->staff_model->get((int) $id);
        if (!$s || ((int) $s['is_owner'] === 1 && !$this->auth->is_owner())) {
            $this->json(array('status' => 'fail', 'message' => 'Not allowed.'));
        }
        $temp = self::temp_password();
        $this->db->where('id', (int) $id)->update('admin_staff', array('password' => password_hash($temp, PASSWORD_DEFAULT), 'must_change_password' => 1));
        $this->online_model->end_staff_sessions((int) $id);
        audit('password', 'staff', (int) $id, 'Reset the password of ' . $s['name']);
        $this->json(array('status' => 'success', 'password' => $temp, 'message' => 'Temporary password for ' . $s['name'] . ' (they were signed out):'));
    }

    protected static function temp_password()
    {
        return substr(str_replace(array('+', '/', '='), '', base64_encode(random_bytes(12))), 0, 11) . random_int(2, 9);
    }

    /* ---------- roles ---------- */

    public function roles()
    {
        $this->need('roles.manage');
        $edit = (int) $this->input->get('edit');
        $this->render('staff/roles', array('title' => 'Roles', 'roles' => $this->staff_model->roles(), 'edit' => $edit ? $this->staff_model->role($edit) : null,
            'new' => $this->input->get('new') === '1', 'permissions' => admin_permissions()));
    }

    public function role_save()
    {
        $this->need_post();
        $this->need('roles.manage');
        $id = (int) $this->input->post('id');
        $r = $this->staff_model->save_role($id, $this->input->post('name'), $this->input->post('description'), (array) $this->input->post('permissions'));
        if (is_array($r)) {
            $this->json(array('status' => 'fail', 'message' => $r['error']));
        }
        audit($id ? 'update' : 'create', 'role', $r, 'Role ' . trim((string) $this->input->post('name')) . ': ' . implode(', ', (array) $this->input->post('permissions')));
        $this->flash('success', 'Role saved.');
        $this->json(array('status' => 'success', 'url' => site_url('staff/roles')));
    }

    public function role_delete($id = 0)
    {
        $this->need_post();
        $this->need('roles.manage');
        $r = $this->staff_model->delete_role((int) $id);
        if ($r !== true) {
            $this->json(array('status' => 'fail', 'message' => $r));
        }
        audit('delete', 'role', (int) $id, 'Role deleted');
        $this->flash('success', 'Role deleted.');
        $this->json(array('status' => 'success', 'url' => site_url('staff/roles')));
    }
}
