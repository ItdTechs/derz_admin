<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** The signed-in staff member's own account: name, phone, password, their sessions. */
class Account extends Admin_Controller
{
    public function index()
    {
        $this->load->model('online_model');
        if ($this->input->method() === 'post') {
            $name = trim((string) $this->input->post('name'));
            if ($name === '') {
                $this->json(array('status' => 'fail', 'errors' => array('name' => 'Enter your name.')));
            }
            $this->db->where('id', $this->auth->id())->update('admin_staff', array('name' => mb_substr($name, 0, 120), 'phone' => mb_substr(trim((string) $this->input->post('phone')), 0, 30)));
            audit('update', 'staff', $this->auth->id(), 'Changed own profile');
            $this->flash('success', 'Saved.');
            $this->json(array('status' => 'success', 'url' => site_url('account')));
        }
        $this->render('account/index', array('title' => 'My account'));
    }

    public function password()
    {
        $forced = (int) $this->data['me']['must_change_password'] === 1;
        if ($this->input->method() === 'post') {
            $current = (string) $this->input->post('current');
            $new = (string) $this->input->post('password');
            $errors = array();
            if (!password_verify($current, $this->data['me']['password'])) {
                $errors['current'] = 'This is not your current password.';
            } elseif ($weak = Staff_auth::weak_password($new)) {
                $errors['password'] = $weak;
            } elseif ($new === $current) {
                $errors['password'] = 'Choose a new password.';
            } elseif ($new !== (string) $this->input->post('password_confirm')) {
                $errors['password_confirm'] = 'The two passwords are not the same.';
            }
            if ($errors) {
                $this->json(array('status' => 'fail', 'errors' => $errors));
            }
            $this->db->where('id', $this->auth->id())->update('admin_staff', array('password' => password_hash($new, PASSWORD_DEFAULT), 'must_change_password' => 0));
            $this->load->model('online_model');
            $this->online_model->end_staff_sessions($this->auth->id(), session_id()); // other devices are signed out
            audit('password', 'staff', $this->auth->id(), 'Changed own password');
            $this->flash('success', 'Your password is changed. Other devices were signed out.');
            $this->json(array('status' => 'success', 'url' => site_url('')));
        }
        $this->render('account/password', array('title' => 'Change password', 'forced' => $forced));
    }
}
