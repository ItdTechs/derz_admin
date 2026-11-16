<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** Packages (prices, limits, modules, add-on prices, the features shown on the website) and payment methods. */
class Packages extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->need('packages.manage');
        $this->load->model('billing_model');
    }

    public function index()
    {
        $this->render('packages/index', array('title' => 'Packages', 'packages' => $this->billing_model->packages(), 'methods' => $this->billing_model->payment_methods(),
            'methods_ready' => $this->db->field_exists('details', 'subscription_payment_types')));
    }

    public function edit($id = 0)
    {
        $package = $id ? $this->billing_model->package($id) : null;
        if ($id && !$package) {
            show_404();
        }
        $modules = $this->billing_model->module_keys();
        $addon_fields = $this->db->field_exists('addon_store_price', 'subscription_packages') ? array('addon_store_price', 'addon_warehouse_price', 'addon_user_price') : array();
        $hr_ready = $this->db->field_exists('addon_hr_price', 'subscription_packages');
        if ($this->input->method() === 'post') {
            $errors = array();
            $num = function ($k, $max_int = 8, $required = false) use (&$errors) {
                $v = trim((string) $this->input->post($k));
                if ($v === '') {
                    if ($required) {
                        $errors[$k] = 'Required.';
                    }
                    return null;
                }
                if (!preg_match('/^\d{1,' . $max_int . '}(\.\d{1,2})?$/', $v)) {
                    $errors[$k] = 'Enter a number.';
                }
                return (float) $v;
            };
            $limit = function ($k) use (&$errors) {
                $v = trim((string) $this->input->post($k));
                if ($v !== '' && !preg_match('/^\d{1,5}$/', $v)) {
                    $errors[$k] = 'Enter a whole number, or leave it empty for no limit.';
                }
                return $v === '' ? null : (int) $v;
            };
            $name = trim((string) $this->input->post('name'));
            if ($name === '' || mb_strlen($name) > 100) {
                $errors['name'] = 'Enter a name of up to 100 characters.';
            }
            $four = $num('price_4_months', 8, true);
            $discount = $num('discount', 2, true);
            $data = array(
                'name' => $name, 'price_4_months' => (float) $four, 'price' => round((float) $four * 3, 2), 'discount' => (float) $discount,
                'period_type' => 'years', 'period_value' => 1,
                'store_limit' => $limit('store_limit'), 'warehouse_limit' => $limit('warehouse_limit'), 'user_limit' => $limit('user_limit'), 'storage_limit' => $limit('storage_limit'),
                'package_order' => (int) $this->input->post('package_order'),
                'status' => $this->input->post('status') ? 1 : 0, 'show_onwebsite' => $this->input->post('show_onwebsite') ? 1 : 0,
                'recommended' => $this->input->post('recommended') ? 1 : 0,
            );
            $addons = array();
            foreach ($addon_fields as $k) {
                $addons[$k] = $num($k);
            }
            if ($addons && count(array_filter($addons, 'is_null')) % 3 !== 0) {
                $errors['addon_store_price'] = 'Enter all three add-on prices, or none.';
            }
            $data += $addons;
            if ($hr_ready) {
                $data['addon_hr_price'] = $num('addon_hr_price');
            }
            if ($errors) {
                $this->json(array('status' => 'fail', 'errors' => $errors));
            }
            $chosen = (array) $this->input->post('modules');
            $module_values = array();
            foreach (array_unique(array_merge($modules, array_filter(array_map('trim', explode(',', (string) $this->input->post('new_modules')))))) as $m) {
                if (preg_match('/^[a-z0-9_]{2,40}$/', $m)) {
                    $module_values[$m] = in_array($m, $chosen, true) || in_array($m, array_map('trim', explode(',', (string) $this->input->post('new_modules'))), true);
                }
            }
            $features = array_slice(array_values(array_filter(array_map('trim', explode("\n", (string) $this->input->post('features'))))), 0, 30);
            $new_id = $this->billing_model->save_package((int) $id, $data, $module_values, $features);
            audit($id ? 'update' : 'create', 'package', $new_id, 'Package ' . $name . ': ' . money($data['price_4_months']) . ' / 4 months, ' . $data['discount'] . '% yearly discount', $data);
            $this->flash('success', 'Package ' . $name . ' saved.');
            $this->json(array('status' => 'success', 'url' => site_url('packages')));
        }
        $this->render('packages/form', array(
            'title' => $package ? 'Package: ' . $package['name'] : 'New package', 'p' => $package, 'modules' => $modules,
            'package_modules' => $package ? (array) json_decode((string) $package['permission'], true) : array(),
            'features' => $package ? $this->billing_model->package_features($package['id']) : array(),
            'addon_fields' => $addon_fields, 'hr_ready' => $hr_ready,
        ));
    }

    public function method_save()
    {
        $this->need_post();
        $id = (int) $this->input->post('id');
        $r = $this->billing_model->save_payment_method($id, $this->input->post('name'), $this->input->post('details'), (bool) $this->input->post('active'), $this->input->post('sort_order'));
        if (is_array($r)) {
            $this->json(array('status' => 'fail', 'message' => $r['error']));
        }
        audit($id ? 'update' : 'create', 'payment_method', $r, 'Payment method: ' . trim((string) $this->input->post('name')));
        $this->flash('success', 'Payment method saved.');
        $this->json(array('status' => 'success', 'url' => site_url('packages') . '#methods'));
    }

    public function method_delete($id = 0)
    {
        $this->need_post();
        $r = $this->billing_model->delete_payment_method((int) $id);
        if (is_array($r)) {
            $this->json(array('status' => 'fail', 'message' => $r['error']));
        }
        audit('delete', 'payment_method', (int) $id, 'Payment method deleted');
        $this->flash('success', 'Payment method deleted.');
        $this->json(array('status' => 'success', 'url' => site_url('packages') . '#methods'));
    }
}
