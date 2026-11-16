<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** Escape for HTML. */
function e($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** "ETB 1,234.50" */
function money($v, $decimals = 2)
{
    return config_item('currency') . ' ' . number_format((float) $v, $decimals);
}

/** Short money for cards: 12.5K, 1.2M */
function money_short($v)
{
    $v = (float) $v;
    if (abs($v) >= 1000000) {
        return config_item('currency') . ' ' . rtrim(rtrim(number_format($v / 1000000, 2), '0'), '.') . 'M';
    }
    if (abs($v) >= 10000) {
        return config_item('currency') . ' ' . rtrim(rtrim(number_format($v / 1000, 1), '0'), '.') . 'K';
    }
    return money($v, 0);
}

function fmt_date($d)
{
    return $d ? date('d M Y', strtotime($d)) : '—';
}

function fmt_datetime($d)
{
    return $d ? date('d M Y, H:i', strtotime($d)) : '—';
}

/** "5 min ago", "3 days ago", "in 4 days" */
function time_ago($d)
{
    if (!$d) {
        return '—';
    }
    $diff = time() - (is_numeric($d) ? (int) $d : strtotime($d));
    $future = $diff < 0;
    $diff = abs($diff);
    if ($diff < 60) {
        return $future ? 'in a moment' : 'just now';
    }
    $units = array(31536000 => 'year', 2592000 => 'month', 86400 => 'day', 3600 => 'hour', 60 => 'min');
    foreach ($units as $secs => $name) {
        if ($diff >= $secs) {
            $n = (int) floor($diff / $secs);
            $label = $n . ' ' . $name . ($n > 1 && $name !== 'min' ? 's' : '');
            return $future ? 'in ' . $label : $label . ' ago';
        }
    }
    return '';
}

/** The current staff member may do this (Auth library). */
function can($permission)
{
    return get_instance()->auth->can($permission);
}

/** An address in the DerZ app. */
function app_url($path = '')
{
    return rtrim(config_item('app_url'), '/') . '/' . ltrim($path, '/');
}

/** A coloured label (Bootstrap / the app's labels) */
function badge($text, $tone = 'muted', $icon = '')
{
    // the app's status labels (label-*-custom), grey for off / none
    $map = array('success' => 'success-custom', 'info' => 'info-custom', 'warning' => 'warning-custom', 'danger' => 'danger-custom', 'primary' => 'primary-custom', 'dark' => 'default label-dark', 'muted' => 'default');
    return '<span class="label label-' . (isset($map[$tone]) ? $map[$tone] : 'default') . '">' . ($icon ? '<i class="fas ' . e($icon) . '"></i> ' : '') . e($text) . '</span>';
}

/** Subscription status label: active | trial | expired | none | suspended */
function status_badge($status)
{
    $map = array(
        'active'    => array('Active', 'success', 'fa-circle-check'),
        'trial'     => array('Trial', 'info', 'fa-hourglass-half'),
        'expired'   => array('Expired', 'danger', 'fa-circle-xmark'),
        'none'      => array('No plan', 'muted', 'fa-circle-minus'),
        'suspended' => array('Suspended', 'dark', 'fa-ban'),
        'pending'   => array('Pending', 'warning', 'fa-clock'),
        'approved'  => array('Approved', 'success', 'fa-check'),
        'rejected'  => array('Rejected', 'danger', 'fa-xmark'),
    );
    $m = isset($map[$status]) ? $map[$status] : array(ucfirst((string) $status), 'muted', '');
    return badge($m[0], $m[1], $m[2]);
}

/** CRM stages of a business */
function crm_stages()
{
    return array('onboarding' => array('Onboarding', 'info'), 'active' => array('Healthy', 'success'), 'at_risk' => array('At risk', 'warning'),
        'churned' => array('Churned', 'danger'), 'vip' => array('VIP', 'primary'));
}

function stage_badge($stage)
{
    $stages = crm_stages();
    $s = isset($stages[$stage]) ? $stages[$stage] : array(ucfirst((string) $stage), 'muted');
    return badge($s[0], $s[1]);
}

/**
 * Every permission of the portal: key => [group, label]. Roles hold a list of these keys
 * ("*" = all). businesses.view alone = only the businesses the staff member manages (agent).
 */
function admin_permissions()
{
    return array(
        'dashboard.view'        => array('Overview', 'Dashboard'),
        'analytics.view'        => array('Overview', 'Analytics and revenue'),
        'businesses.view'       => array('Businesses', 'See businesses (only their own, unless "all businesses")'),
        'businesses.view_all'   => array('Businesses', 'See all businesses'),
        'businesses.edit'       => array('Businesses', 'Edit business details'),
        'businesses.suspend'    => array('Businesses', 'Suspend / reactivate a business'),
        'businesses.assign'     => array('Businesses', 'Assign account managers, stage and tags'),
        'businesses.notes'      => array('Businesses', 'Write notes and follow-ups'),
        'businesses.export'     => array('Businesses', 'Export lists (CSV)'),
        'users.manage'          => array('Businesses', 'Manage app users (reset password, deactivate, sign out)'),
        'billing.view'          => array('Billing', 'See payments and requests'),
        'billing.approve'       => array('Billing', 'Approve / reject payment requests'),
        'billing.record'        => array('Billing', 'Record payments, change plan, extend dates'),
        'packages.manage'       => array('Billing', 'Packages, prices, add-ons and payment methods'),
        'online.view'           => array('Activity', 'See who is online'),
        'sessions.end'          => array('Activity', 'Sign people out (end sessions)'),
        'audit.view'            => array('Activity', 'Audit logs'),
        'messages.manage'       => array('Communication', 'Announcements and Telegram broadcasts'),
        'translations.manage'   => array('Platform', 'Translations'),
        'settings.manage'       => array('Platform', 'Platform settings, maintenance, Telegram'),
        'staff.manage'          => array('Team', 'Staff accounts'),
        'roles.manage'          => array('Team', 'Roles and permissions'),
    );
}

/** A platform setting (business_settings, business 0) */
function platform_setting($key, $default = '')
{
    static $cache = null;
    if ($cache === null || $key === null) {
        $cache = array();
        $CI = &get_instance();
        if ($CI->db->table_exists('business_settings')) {
            foreach ($CI->db->select('setting_key, setting_value')->where('business_id', 0)->get('business_settings')->result() as $r) {
                $cache[$r->setting_key] = $r->setting_value;
            }
        }
        if ($key === null) {
            return null;
        }
    }
    return array_key_exists($key, $cache) ? $cache[$key] : $default;
}

function save_platform_settings(array $values)
{
    $db = get_instance()->db;
    foreach ($values as $k => $v) {
        $db->query('INSERT INTO business_settings (business_id, setting_key, setting_value) VALUES (0, ?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', array($k, (string) $v));
    }
    platform_setting(null); // reload
}

/** Write the portal's audit log (who in the team did what) */
function audit($action, $entity, $entity_id, $summary, array $details = array(), $business_id = 0)
{
    $CI = &get_instance();
    $CI->db->insert('admin_audit', array(
        'staff_id'    => (int) $CI->auth->id(),
        'action'      => mb_substr($action, 0, 30),
        'entity'      => mb_substr($entity, 0, 40),
        'entity_id'   => (int) $entity_id,
        'business_id' => (int) $business_id,
        'summary'     => mb_substr($summary, 0, 255),
        'details'     => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : '',
        'ip'          => (string) $CI->input->ip_address(),
        'created_at'  => date('Y-m-d H:i:s'),
    ));
}

/** Pagination links (Bootstrap): ?page=n keeping the other query values */
function pager($total, $page, $per_page)
{
    $pages = (int) ceil($total / max(1, $per_page));
    if ($pages <= 1) {
        return '';
    }
    $query = $_GET;
    $link = function ($n, $label, $class = '') use ($query) {
        $query['page'] = $n;
        return '<li class="' . $class . '"><a href="?' . e(http_build_query($query)) . '">' . $label . '</a></li>';
    };
    $out = '<div class="text-center"><ul class="pagination">' . $link(max(1, $page - 1), '&laquo;', $page <= 1 ? 'disabled' : '');
    for ($i = max(1, $page - 3); $i <= min($pages, $page + 3); $i++) {
        $out .= $link($i, $i, $i === $page ? 'active' : '');
    }
    return $out . $link(min($pages, $page + 1), '&raquo;', $page >= $pages ? 'disabled' : '') . '</ul><p class="text-muted">' . number_format($total) . ' in all · page ' . $page . ' of ' . $pages . '</p></div>';
}

/** A select's options: array value => label */
function options(array $items, $selected = '')
{
    $out = '';
    foreach ($items as $v => $label) {
        $out .= '<option value="' . e($v) . '"' . ((string) $v === (string) $selected ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    return $out;
}

/** A figure tile: the app's report stat (big coloured figure, label underneath), optionally a link */
function stat_tile($label, $value, $icon, $tone = '', $sub = '', $link = '')
{
    $tones = array('' => 'success', 'info' => 'info', 'warning' => 'warning', 'danger' => 'danger', 'primary' => 'primary');
    $tone = isset($tones[$tone]) ? $tones[$tone] : 'success';
    $tag = $link ? 'a href="' . e($link) . '"' : 'div';
    // the app's report_stats() tile: big coloured figure, label underneath ($icon kept for the callers, not shown, as in the app)
    return '<div class="' . stat_cols() . '"><' . $tag . ' class="panel panel-custom text-center report-stat report-stat-' . $tone . '"><div class="panel-body">'
        . '<h3 class="mt-sm mb-none text-' . $tone . '">' . $value . '</h3><p class="text-muted">' . e($label) . '</p>'
        . ($sub !== '' ? '<small>' . $sub . '</small>' : '') . '</div></' . ($link ? 'a' : 'div') . '></div>';
}

/** Opens a row of figure tiles: the app's report_stats() columns for $n tiles. */
function stat_row($n)
{
    stat_cols($n >= 7 ? 'col-lg-3 col-md-3 col-sm-6 col-xs-6' : ($n >= 6 ? 'col-lg-2 col-md-4 col-sm-4 col-xs-6' : ($n === 5 ? 'col-md-5ths col-sm-4 col-xs-6' : ($n === 4 ? 'col-md-3 col-sm-6 col-xs-6' : ($n === 3 ? 'col-sm-4' : ($n === 2 ? 'col-sm-6' : 'col-sm-12'))))));
    return '<div class="row mb-lg report-stats">';
}

function stat_cols($set = null)
{
    static $cols = 'col-md-3 col-sm-6 col-xs-6';
    if ($set !== null) {
        $cols = $set;
    }
    return $cols;
}

/** "+12%" against a previous figure (as HTML) */
function trend($now, $before)
{
    if ((float) $before == 0) {
        return $now > 0 ? '<span class="trend up"><i class="fas fa-arrow-up"></i> new</span>' : '';
    }
    $p = round(((float) $now - (float) $before) / abs((float) $before) * 100);
    return '<span class="trend ' . ($p >= 0 ? 'up' : 'down') . '"><i class="fas fa-arrow-' . ($p >= 0 ? 'up' : 'down') . '"></i> ' . abs($p) . '%</span>';
}

/** A chart's data attribute: <canvas data-chart="..."> */
function chart_attr($type, array $labels, array $datasets, array $options = array())
{
    return e(json_encode(array_merge(array('type' => $type, 'labels' => array_values($labels), 'datasets' => $datasets), $options), JSON_UNESCAPED_UNICODE));
}

/** Initials for an avatar */
function initials($name)
{
    $parts = preg_split('/\s+/', trim((string) $name));
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $out !== '' ? $out : '?';
}
