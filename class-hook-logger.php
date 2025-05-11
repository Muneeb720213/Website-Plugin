<?php
if (!defined('ABSPATH')) exit;

class SystemeIO_Hook_Logger {
    private $logged_hooks = array();
    private $active_hooks = array();

    public function __construct() {
        $this->active_hooks = get_option('systemeio_active_hooks', array(
            'user_register',
            'wpforms_process_complete',
            'gform_after_submission',
            'cf7_submit' // Added Contact Form 7 support
        ));
        $this->setup_hooks();
    }

    private function setup_hooks() {
        add_action('user_register', array($this, 'log_user_register'), 10, 1);
        add_action('wpforms_process_complete', array($this, 'log_wpforms_submission'), 10, 4);
        add_action('gform_after_submission', array($this, 'log_gravityforms_submission'), 10, 2);
        add_action('wpcf7_mail_sent', array($this, 'log_cf7_submission'), 10, 1);
        
        do_action('systemeio_register_hooks', $this);
    }

    public function log_user_register($user_id) {
        $user = get_userdata($user_id);
        if (!$user || !$user->user_email) return;

        $hook_data = array(
            'hook' => 'user_register',
            'email' => $user->user_email,
            'user_id' => $user_id,
            'timestamp' => current_time('mysql')
        );

        $this->log_hook($hook_data);
    }

    public function log_wpforms_submission($fields, $entry, $form_data, $entry_id) {
        $email = '';
        foreach ($fields as $field) {
            if ($field['type'] === 'email' && is_email($field['value'])) {
                $email = sanitize_email($field['value']);
                break;
            }
        }

        if (empty($email)) return;

        $hook_data = array(
            'hook' => 'wpforms_process_complete',
            'email' => $email,
            'form_id' => $form_data['id'],
            'entry_id' => $entry_id,
            'timestamp' => current_time('mysql')
        );

        $this->log_hook($hook_data);
    }

    public function log_gravityforms_submission($entry, $form) {
        $email_fields = GFAPI::get_fields_by_type($form, array('email'));
        if (empty($email_fields)) return;

        $email = '';
        foreach ($email_fields as $field) {
            if (!empty($entry[$field->id])) {
                $email = sanitize_email($entry[$field->id]);
                break;
            }
        }

        if (empty($email)) return;

        $hook_data = array(
            'hook' => 'gform_after_submission',
            'email' => $email,
            'form_id' => $form['id'],
            'entry_id' => $entry['id'],
            'timestamp' => current_time('mysql')
        );

        $this->log_hook($hook_data);
    }

    public function log_cf7_submission($contact_form) {
        $submission = WPCF7_Submission::get_instance();
        if (!$submission) return;

        $posted_data = $submission->get_posted_data();
        $email = '';

        // Find email field in CF7 submission
        foreach ($posted_data as $key => $value) {
            if (is_email($value) && strpos($key, 'email') !== false) {
                $email = sanitize_email($value);
                break;
            }
        }

        if (empty($email)) return;

        $hook_data = array(
            'hook' => 'cf7_submit',
            'email' => $email,
            'form_id' => $contact_form->id(),
            'timestamp' => current_time('mysql')
        );

        $this->log_hook($hook_data);
    }

    private function log_hook($data) {
        $logs = get_option('systemeio_logged_hooks', array());
        array_unshift($logs, $data);
        
        if (count($logs) > SYSTEMEIO_LOG_LIMIT) {
            array_pop($logs);
        }
        
        update_option('systemeio_logged_hooks', $logs);
        do_action('systemeio_hook_triggered', $data);
    }

    public function get_logged_hooks() {
        return get_option('systemeio_logged_hooks', array());
    }

    public function clear_logs() {
        delete_option('systemeio_logged_hooks');
    }

    public function register_custom_hook($hook_name, $callback) {
        if (!in_array($hook_name, $this->active_hooks)) {
            $this->active_hooks[] = $hook_name;
            update_option('systemeio_active_hooks', $this->active_hooks);
        }
        
        add_action($hook_name, $callback);
    }
}