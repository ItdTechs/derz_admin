<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Command line only:
 *   php index.php cli cleanup                             old sign-in attempts and portal sessions
 */
class Cli extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!is_cli()) {
            show_404();
        }
    }

    public function cleanup()
    {
        $this->db->where('created_at <', date('Y-m-d H:i:s', strtotime('-90 days')))->delete('admin_login_attempts');
        $this->db->where('timestamp <', time() - 86400 * 7)->delete('admin_sessions');
        echo "Cleaned.\n";
    }
}
