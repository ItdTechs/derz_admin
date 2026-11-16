<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** Who is using DerZ right now (the app's sessions) and the team in the portal; sessions can be ended. */
class Online extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->need('online.view');
        $this->load->model(array('online_model', 'business_model'));
    }

    public function index()
    {
        $scope = can('businesses.view_all') ? null : $this->business_model->agent_business_ids($this->auth->id());
        $people = $this->online_model->app_online($scope);
        $by_business = array();
        foreach ($people as $p) {
            $k = (int) $p['business_id'];
            if (!isset($by_business[$k])) {
                $by_business[$k] = array('id' => $k, 'name' => $p['business_name'], 'people' => 0);
            }
            $by_business[$k]['people']++;
        }
        usort($by_business, function ($a, $b) { return $b['people'] - $a['people']; });
        $data = array('title' => 'Online now', 'people' => $people, 'by_business' => $by_business,
            'staff' => can('staff.manage') ? $this->online_model->staff_online() : array(), 'minutes' => (int) config_item('online_minutes'));
        if ($this->input->get('format') === 'json') {
            $this->json(array('people' => count($people), 'businesses' => count($by_business)));
        }
        $this->render('online/index', $data);
    }

    public function end($session_id = '')
    {
        $this->need_post();
        $this->need('sessions.end');
        $session_id = preg_replace('/[^a-zA-Z0-9,-]/', '', (string) $session_id);
        $row = $this->db->select('data')->where('id', $session_id)->get('rm_sessions')->row_array();
        if (!$row) {
            $this->json(array('status' => 'fail', 'message' => 'This session has already ended.'));
        }
        $bid = (int) Online_model::session_value($row['data'], 'loggedin_branch');
        if (!$this->auth->can_see_business($bid)) {
            $this->json(array('status' => 'fail', 'message' => 'You do not have permission to do this.'), 403);
        }
        $uid = (int) Online_model::session_value($row['data'], 'loggedin_id');
        $this->online_model->end_session($session_id);
        audit('signout', 'app_user', $uid, 'Ended an app session of user #' . $uid, array(), $bid);
        $this->flash('success', 'The session was ended: that person is signed out.');
        $this->json(array('status' => 'success', 'url' => site_url('online')));
    }

    public function end_staff($staff_id = 0)
    {
        $this->need_post();
        $this->need('staff.manage');
        if ((int) $staff_id === $this->auth->id()) {
            $this->json(array('status' => 'fail', 'message' => 'Use Sign out for yourself.'));
        }
        $n = $this->online_model->end_staff_sessions((int) $staff_id);
        audit('signout', 'staff', (int) $staff_id, 'Ended the portal sessions of staff #' . (int) $staff_id . ' (' . $n . ')');
        $this->flash('success', 'Signed out.');
        $this->json(array('status' => 'success', 'url' => site_url('online')));
    }
}
