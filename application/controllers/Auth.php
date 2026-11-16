<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** Sign in / out. */
class Auth extends MY_Controller
{
    public function login()
    {
        if (!$this->auth->ready()) {
            show_error('Run database/sql/2026-11-10_45_admin_portal.sql on the DerZ database first.', 503);
        }
        if ($this->auth->staff()) {
            redirect('');
        }
        $error = '';
        $email = '';
        if ($this->input->method() === 'post') {
            $email = trim((string) $this->input->post('email'));
            $result = $this->auth->login($email, (string) $this->input->post('password'));
            if ($result === true) {
                audit('login', 'staff', $this->auth->id(), 'Signed in');
                $next = (string) $this->session->userdata('after_login');
                $this->session->unset_userdata('after_login');
                redirect(preg_match('#^[a-z0-9_/?=&%.-]*$#i', $next) && strpos($next, '//') === false ? $next : '');
            }
            $error = $result;
        }
        $this->load->view('auth/login', array('error' => $error, 'email' => $email, 'flash' => $this->session->flashdata('flash')));
    }

    public function logout()
    {
        if ($this->auth->staff()) {
            audit('logout', 'staff', $this->auth->id(), 'Signed out');
        }
        $this->auth->logout();
        redirect('login');
    }
}
