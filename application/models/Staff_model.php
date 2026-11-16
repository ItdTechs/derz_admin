<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** The DerZ team: staff accounts, roles and the portal's audit log. */
class Staff_model extends CI_Model
{
    public function all()
    {
        return $this->db->query('SELECT s.*, r.name AS role_name, r.is_owner,
                (SELECT COUNT(*) FROM business_crm c WHERE c.agent_id = s.id) AS businesses
            FROM admin_staff s LEFT JOIN admin_roles r ON r.id = s.role_id ORDER BY s.active DESC, s.name')->result_array();
    }

    public function get($id)
    {
        return $this->db->select('s.*, r.name AS role_name, r.is_owner')->from('admin_staff s')->join('admin_roles r', 'r.id = s.role_id', 'left')
            ->where('s.id', (int) $id)->get()->row_array() ?: null;
    }

    /** Staff who can manage businesses (the account manager choices) */
    public function agents()
    {
        return $this->db->select('id, name')->where('active', 1)->order_by('name')->get('admin_staff')->result_array();
    }

    /** Owners left if this account stopped being one */
    public function other_owners($id)
    {
        return (int) $this->db->query('SELECT COUNT(*) AS n FROM admin_staff s JOIN admin_roles r ON r.id = s.role_id WHERE r.is_owner = 1 AND s.active = 1 AND s.id <> ?',
            array((int) $id))->row()->n;
    }

    /** Create ($id 0) or change a staff account. $password '' = keep. Returns the id or ['error' => ...]. */
    public function save($id, array $d, $password = '')
    {
        $email = strtolower(trim((string) $d['email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return array('error' => 'Enter a valid email.', 'field' => 'email');
        }
        if ($this->db->where('email', $email)->where('id <>', (int) $id)->count_all_results('admin_staff')) {
            return array('error' => 'Another account uses this email.', 'field' => 'email');
        }
        if (trim((string) $d['name']) === '') {
            return array('error' => 'Enter the name.', 'field' => 'name');
        }
        $role = $this->role((int) $d['role_id']);
        if (!$role) {
            return array('error' => 'Choose a role.', 'field' => 'role_id');
        }
        $row = array('name' => mb_substr(trim($d['name']), 0, 120), 'email' => $email, 'phone' => mb_substr(trim((string) ($d['phone'] ?? '')), 0, 30),
            'role_id' => (int) $role['id'], 'active' => !empty($d['active']) ? 1 : 0);
        if ($id) {
            $current = $this->get($id);
            // the last owner keeps the Owner role and stays active
            if ((int) $current['is_owner'] === 1 && ((int) $role['is_owner'] !== 1 || !$row['active']) && $this->other_owners($id) === 0) {
                return array('error' => 'This is the only Owner: make someone else Owner first.', 'field' => 'role_id');
            }
        }
        if ($password !== '') {
            $row['password'] = password_hash($password, PASSWORD_DEFAULT);
            $row['must_change_password'] = 1;
        }
        if ($id) {
            $this->db->where('id', (int) $id)->update('admin_staff', $row);
            return (int) $id;
        }
        $row['created_by'] = (int) $this->auth->id();
        $row['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('admin_staff', $row);
        return (int) $this->db->insert_id();
    }

    public function roles()
    {
        return $this->db->query('SELECT r.*, (SELECT COUNT(*) FROM admin_staff s WHERE s.role_id = r.id) AS staff FROM admin_roles r ORDER BY r.is_owner DESC, r.id')->result_array();
    }

    public function role($id)
    {
        return $this->db->where('id', (int) $id)->get('admin_roles')->row_array() ?: null;
    }

    /** Create or change a role (not the Owner role). Returns the id or ['error' => ...]. */
    public function save_role($id, $name, $description, array $permissions)
    {
        $name = trim((string) $name);
        if ($name === '' || mb_strlen($name) > 60) {
            return array('error' => 'Enter a name of up to 60 characters.');
        }
        if ($this->db->where('name', $name)->where('id <>', (int) $id)->count_all_results('admin_roles')) {
            return array('error' => 'Another role has this name.');
        }
        if ($id && ($r = $this->role($id)) && (int) $r['is_owner'] === 1) {
            return array('error' => 'The Owner role cannot be changed.');
        }
        $valid = array_keys(admin_permissions());
        $row = array('name' => $name, 'description' => mb_substr(trim((string) $description), 0, 255),
            'permissions' => json_encode(array_values(array_intersect($permissions, $valid))));
        if ($id) {
            $this->db->where('id', (int) $id)->update('admin_roles', $row);
            return (int) $id;
        }
        $this->db->insert('admin_roles', $row);
        return (int) $this->db->insert_id();
    }

    public function delete_role($id)
    {
        $r = $this->role($id);
        if (!$r || (int) $r['is_owner'] === 1) {
            return 'The Owner role cannot be deleted.';
        }
        if ($this->db->where('role_id', (int) $id)->count_all_results('admin_staff')) {
            return 'Staff have this role: give them another role first.';
        }
        $this->db->where('id', (int) $id)->delete('admin_roles');
        return true;
    }

    /** The portal's audit log. $f: staff_id, business_id, action, q, from, to */
    public function audit(array $f, $limit = 50, $offset = 0, $count = false)
    {
        $this->db->from('admin_audit a')->join('admin_staff s', 's.id = a.staff_id', 'left')->join('businesses b', 'b.id = a.business_id', 'left');
        foreach (array('staff_id', 'business_id') as $k) {
            if (!empty($f[$k])) {
                $this->db->where('a.' . $k, (int) $f[$k]);
            }
        }
        if (!empty($f['action'])) {
            $this->db->where('a.action', $f['action']);
        }
        if (!empty($f['q'])) {
            $this->db->like('a.summary', $f['q']);
        }
        if (!empty($f['from'])) {
            $this->db->where('a.created_at >=', $f['from'] . ' 00:00:00');
        }
        if (!empty($f['to'])) {
            $this->db->where('a.created_at <=', $f['to'] . ' 23:59:59');
        }
        if ($count) {
            return (int) $this->db->count_all_results();
        }
        return $this->db->select('a.*, s.name AS staff_name, b.name AS business_name')->order_by('a.id', 'DESC')->limit($limit, $offset)->get()->result_array();
    }

    public function audit_actions()
    {
        return array_column($this->db->query('SELECT DISTINCT action FROM admin_audit ORDER BY action')->result_array(), 'action');
    }

    /** Sign-in attempts of the last days (security) */
    public function login_attempts($limit = 30)
    {
        return $this->db->order_by('id', 'DESC')->limit($limit)->get('admin_login_attempts')->result_array();
    }
}
