<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Staff sign-in and permissions. Staff are the DerZ team (admin_staff), not app users; each has
 * one role (admin_roles) with a list of permission keys (admin_permissions()). An agent who may
 * see businesses but not "all businesses" sees the ones they manage (business_crm.agent_id).
 */
class Staff_auth
{
    protected $CI;
    protected $staff = false;
    protected $permissions = null;

    public function __construct()
    {
        $this->CI =& get_instance();
    }

    /** The portal's tables exist (SQL step 45). */
    public function ready()
    {
        static $ready = null;
        if ($ready === null) {
            $ready = $this->CI->db->table_exists('admin_staff') && $this->CI->db->table_exists('admin_sessions');
        }
        return $ready;
    }

    public function id()
    {
        return (int) $this->CI->session->userdata('staff_id');
    }

    /** The signed-in staff member with their role, or null. */
    public function staff()
    {
        if ($this->staff === false) {
            $this->staff = null;
            if ($this->id()) {
                $row = $this->CI->db->select('s.*, r.name AS role_name, r.permissions, r.is_owner')
                    ->from('admin_staff s')->join('admin_roles r', 'r.id = s.role_id', 'left')
                    ->where('s.id', $this->id())->where('s.active', 1)->get()->row_array();
                $this->staff = $row ?: null;
            }
        }
        return $this->staff;
    }

    public function permissions()
    {
        if ($this->permissions === null) {
            $s = $this->staff();
            $this->permissions = $s ? (array) json_decode((string) $s['permissions'], true) : array();
            if ($s && (int) $s['is_owner'] === 1) {
                $this->permissions = array('*');
            }
        }
        return $this->permissions;
    }

    public function is_owner()
    {
        return in_array('*', $this->permissions(), true);
    }

    public function can($permission)
    {
        $p = $this->permissions();
        return in_array('*', $p, true) || in_array($permission, $p, true);
    }

    /** May the staff member open this business (all businesses, or one they manage)? */
    public function can_see_business($business_id)
    {
        if ($this->can('businesses.view_all')) {
            return true;
        }
        if (!$this->can('businesses.view')) {
            return false;
        }
        return (int) $this->CI->db->where('business_id', (int) $business_id)->where('agent_id', $this->id())->count_all_results('business_crm') > 0;
    }

    /** Failed sign-ins from this address / for this email in the lock window. */
    public function locked($email)
    {
        $since = date('Y-m-d H:i:s', strtotime('-' . (int) config_item('login_lock_minutes') . ' minutes'));
        $max = (int) config_item('login_max_attempts');
        $db = $this->CI->db;
        $by_ip = $db->where('ip', $this->CI->input->ip_address())->where('success', 0)->where('created_at >=', $since)->count_all_results('admin_login_attempts');
        $by_email = $db->where('email', $email)->where('success', 0)->where('created_at >=', $since)->count_all_results('admin_login_attempts');
        return $by_ip >= $max * 2 || $by_email >= $max;
    }

    /** Sign in: true or the error text. */
    public function login($email, $password)
    {
        $email = strtolower(trim((string) $email));
        if ($this->locked($email)) {
            return 'Too many failed attempts. Try again in ' . (int) config_item('login_lock_minutes') . ' minutes.';
        }
        $db = $this->CI->db;
        // an email, or a phone number (0972795880, +251972795880 ...)
        if (strpos($email, '@') !== false) {
            $staff = $db->where('email', $email)->get('admin_staff')->row_array();
        } else {
            $staff = null;
            $wanted = self::phone_key($email);
            if ($wanted !== '') {
                foreach ($db->where('phone <>', '')->get('admin_staff')->result_array() as $row) {
                    if (self::phone_key($row['phone']) === $wanted) {
                        $staff = $row;
                        break;
                    }
                }
            }
        }
        $ok = $staff && password_verify((string) $password, $staff['password']);
        $db->insert('admin_login_attempts', array('email' => mb_substr($email, 0, 150), 'ip' => $this->CI->input->ip_address(), 'success' => $ok && (int) $staff['active'] === 1 ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s')));
        if (!$ok) {
            return 'The email / phone or password is not right.';
        }
        if ((int) $staff['active'] !== 1) {
            return 'This account is switched off. Ask the owner of the portal.';
        }
        if (password_needs_rehash($staff['password'], PASSWORD_DEFAULT)) {
            $db->where('id', $staff['id'])->update('admin_staff', array('password' => password_hash($password, PASSWORD_DEFAULT)));
        }
        $this->CI->session->sess_regenerate(true);
        $this->CI->session->set_userdata(array('staff_id' => (int) $staff['id'], 'last_activity' => time()));
        $db->where('id', $staff['id'])->update('admin_staff', array('last_login_at' => date('Y-m-d H:i:s'), 'last_seen_at' => date('Y-m-d H:i:s'),
            'last_ip' => $this->CI->input->ip_address()));
        $this->staff = false;
        $this->permissions = null;
        return true;
    }

    public function logout()
    {
        $this->CI->session->unset_userdata(array('staff_id', 'last_activity'));
        $this->CI->session->sess_destroy();
        $this->staff = false;
        $this->permissions = null;
    }

    /** Signed out after idle_minutes without a request; keeps last_seen_at fresh (once a minute). */
    public function touch()
    {
        $last = (int) $this->CI->session->userdata('last_activity');
        if ($last && time() - $last > (int) config_item('idle_minutes') * 60) {
            $this->logout();
            return false;
        }
        if (time() - $last >= 60) {
            $this->CI->session->set_userdata('last_activity', time());
            $this->CI->db->where('id', $this->id())->update('admin_staff', array('last_seen_at' => date('Y-m-d H:i:s')));
        }
        return true;
    }

    /** A phone number to compare: 09.. / 2519.. / +2519.. all give 9xxxxxxxx */
    public static function phone_key($phone)
    {
        $d = preg_replace('/\D+/', '', (string) $phone);
        if (strpos($d, '251') === 0 && strlen($d) === 12) {
            $d = substr($d, 3);
        }
        return ltrim($d, '0');
    }

    /** A password good enough for the portal: null or the problem. */
    public static function weak_password($password)
    {
        $password = (string) $password;
        if (strlen($password) < 10) {
            return 'Use at least 10 characters.';
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            return 'Use letters and numbers.';
        }
        return null;
    }
}
