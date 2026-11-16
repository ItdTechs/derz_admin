<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
 * DerZ Admin: the portal of the DerZ team (businesses, subscriptions, staff). It runs on its own
 * address and works on the same database as the app (application/config/database.php).
 */

$host = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : 'localhost';
$local = $host === 'localhost' || strpos($host, '127.0.0.1') === 0 || substr($host, -6) === '.local';

// the DerZ app: its address (links "open in the app", the Telegram webhook) and its folder on this server
// (payment receipts and logos are read from its uploads/)
$config['app_url'] = $local ? 'http://localhost/derz_inventory/' : 'https://app.derzsystem.com/';
// production: set the app's folder, e.g. '/home/<account>/domains/app.derzsystem.com/public_html/'
$config['app_path'] = $local ? realpath(FCPATH . '../derz_inventory') . DIRECTORY_SEPARATOR : '';

// signing in: failed attempts before a pause, the pause, staff signed out after this many idle minutes
$config['login_max_attempts'] = 5;
$config['login_lock_minutes'] = 15;
$config['idle_minutes'] = 60;

// only these addresses may open the portal (empty = any); e.g. array('196.188.1.10')
$config['allowed_ips'] = array();

// "online": an app session active within this many minutes
$config['online_minutes'] = 15;

$config['currency'] = 'ETB';
