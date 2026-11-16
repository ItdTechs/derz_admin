<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Who is online: the app's sessions (rm_sessions, CodeIgniter database sessions) active within
 * online_minutes, read for loggedin_id / loggedin_branch (the user and the business). Ending a
 * session deletes it: that person is signed out at their next click. The team's own sessions
 * are in admin_sessions (staff_id).
 */
class Online_model extends CI_Model
{
    /** A value of a stored session ("key|serialized;" format) */
    public static function session_value($data, $key)
    {
        if (preg_match('/(?:^|;|\})' . preg_quote($key, '/') . '\|(i:(-?\d+)|s:\d+:"([^"]*)"|b:([01]))/', (string) $data, $m)) {
            if (isset($m[4]) && $m[4] !== '') {
                return (int) $m[4];
            }
            return isset($m[3]) && $m[3] !== '' ? $m[3] : (isset($m[2]) ? $m[2] : null);
        }
        return null;
    }

    protected function app_sessions($minutes = null)
    {
        if (!$this->db->table_exists('rm_sessions')) {
            return array();
        }
        $minutes = $minutes === null ? (int) config_item('online_minutes') : (int) $minutes;
        $rows = $this->db->query("SELECT id, ip_address, timestamp, data FROM rm_sessions WHERE timestamp >= ? AND data LIKE '%loggedin_id%' ORDER BY timestamp DESC",
            array(time() - $minutes * 60))->result_array();
        $out = array();
        foreach ($rows as $r) {
            $uid = (int) self::session_value($r['data'], 'loggedin_id');
            if ($uid > 0) {
                $out[] = array('id' => $r['id'], 'ip' => $r['ip_address'], 'timestamp' => (int) $r['timestamp'], 'user_id' => $uid,
                    'business_id' => (int) self::session_value($r['data'], 'loggedin_branch'), 'type' => (int) self::session_value($r['data'], 'loggedin_type'));
            }
        }
        return $out;
    }

    /** The app's people online, newest first, with their user and business. $scope: business ids or null (all) */
    public function app_online($scope = null)
    {
        $sessions = $this->app_sessions();
        if (!$sessions) {
            return array();
        }
        $users = array();
        foreach ($this->db->select("u.id, u.username, CONCAT_WS(' ', u.firstname, u.lastname) AS name, u.phone, u.business_id, b.name AS business_name, r.name AS role_name", false)
            ->from('users u')->join('businesses b', 'b.id = u.business_id', 'left')->join('user_permission up', 'up.user_id = u.id', 'left')->join('roles r', 'r.id = up.role_id', 'left')
            ->where_in('u.id', array_unique(array_column($sessions, 'user_id')))->get()->result_array() as $u) {
            $users[(int) $u['id']] = $u;
        }
        $out = array();
        $seen = array();
        foreach ($sessions as $s) {
            if (!isset($users[$s['user_id']]) || ($scope !== null && !in_array((int) $users[$s['user_id']]['business_id'], $scope, true))) {
                continue;
            }
            $s['devices'] = 1;
            if (isset($seen[$s['user_id']])) {
                $out[$seen[$s['user_id']]]['devices']++;
                continue;
            }
            $seen[$s['user_id']] = count($out);
            $out[] = $s + $users[$s['user_id']];
        }
        return $out;
    }

    /** user id => last activity (timestamp) of the app's people online */
    public function user_ids()
    {
        $out = array();
        foreach ($this->app_sessions() as $s) {
            $out[$s['user_id']] = max($out[$s['user_id']] ?? 0, $s['timestamp']);
        }
        return $out;
    }

    /** Businesses with someone online: business id => people */
    public function businesses_online()
    {
        $out = array();
        foreach ($this->app_online() as $p) {
            $out[(int) $p['business_id']] = ($out[(int) $p['business_id']] ?? 0) + 1;
        }
        return $out;
    }

    /** People online per hour of the last 24 hours (sessions seen, from the app's audit log as a proxy is not needed) */
    public function staff_online()
    {
        if (!$this->db->table_exists('admin_sessions')) {
            return array();
        }
        $rows = $this->db->query("SELECT id, ip_address, timestamp, data FROM admin_sessions WHERE timestamp >= ? AND data LIKE '%staff_id%' ORDER BY timestamp DESC",
            array(time() - (int) config_item('online_minutes') * 60))->result_array();
        $out = array();
        foreach ($rows as $r) {
            $sid = (int) self::session_value($r['data'], 'staff_id');
            if ($sid && !isset($out[$sid])) {
                $staff = $this->db->select('s.id, s.name, s.email, r.name AS role_name')->from('admin_staff s')->join('admin_roles r', 'r.id = s.role_id', 'left')
                    ->where('s.id', $sid)->get()->row_array();
                if ($staff) {
                    $out[$sid] = $staff + array('ip' => $r['ip_address'], 'timestamp' => (int) $r['timestamp'], 'session_id' => $r['id']);
                }
            }
        }
        return array_values($out);
    }

    /** End one app session (the person is signed out). true when it was there. */
    public function end_session($session_id)
    {
        $this->db->where('id', (string) $session_id)->delete('rm_sessions');
        return $this->db->affected_rows() > 0;
    }

    /** Every session of an app user: how many were ended */
    public function end_user_sessions($user_id)
    {
        return $this->end_where(function ($data) use ($user_id) { return (int) self::session_value($data, 'loggedin_id') === (int) $user_id; });
    }

    /** Every session of a business's people */
    public function end_business_sessions($business_id)
    {
        return $this->end_where(function ($data) use ($business_id) { return (int) self::session_value($data, 'loggedin_branch') === (int) $business_id; });
    }

    protected function end_where($match)
    {
        if (!$this->db->table_exists('rm_sessions')) {
            return 0;
        }
        $ids = array();
        foreach ($this->db->query("SELECT id, data FROM rm_sessions WHERE data LIKE '%loggedin_id%'")->result_array() as $r) {
            if ($match($r['data'])) {
                $ids[] = $r['id'];
            }
        }
        if ($ids) {
            $this->db->where_in('id', $ids)->delete('rm_sessions');
        }
        return count($ids);
    }

    /** End a staff member's portal sessions (switched off, password reset) */
    public function end_staff_sessions($staff_id, $except = '')
    {
        if (!$this->db->table_exists('admin_sessions')) {
            return 0;
        }
        $ids = array();
        foreach ($this->db->query("SELECT id, data FROM admin_sessions WHERE data LIKE '%staff_id%'")->result_array() as $r) {
            if ((int) self::session_value($r['data'], 'staff_id') === (int) $staff_id && $r['id'] !== $except) {
                $ids[] = $r['id'];
            }
        }
        if ($ids) {
            $this->db->where_in('id', $ids)->delete('admin_sessions');
        }
        return count($ids);
    }
}
