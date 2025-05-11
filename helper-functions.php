<?php
if (!defined('ABSPATH')) exit;

/**
 * Recursively find email in array or object
 */
function systemeio_find_email($data) {
    if (is_email($data)) {
        return sanitize_email($data);
    }

    if (is_array($data) || is_object($data)) {
        foreach ($data as $value) {
            $found = systemeio_find_email($value);
            if ($found) {
                return $found;
            }
        }
    }

    return false;
}

/**
 * Log systeme.io activities
 */
function systemeio_log_activity($message, $data = array()) {
    $log = get_option('systemeio_activity_log', array());
    
    $entry = array(
        'timestamp' => current_time('mysql'),
        'message' => $message,
        'data' => $data
    );
    
    array_unshift($log, $entry);
    
    if (count($log) > SYSTEMEIO_LOG_LIMIT) {
        array_pop($log);
    }
    
    update_option('systemeio_activity_log', $log);
}

/**
 * Get the current webhook URL for this site
 */
function systemeio_get_webhook_url() {
    return add_query_arg(array(
        'action' => 'systemeio_webhook',
        'token' => wp_hash('systemeio_webhook')
    ), admin_url('admin-ajax.php'));
}

/**
 * Verify webhook request
 */
function systemeio_verify_webhook() {
    if (!isset($_GET['token']) || $_GET['token'] !== wp_hash('systemeio_webhook')) {
        wp_die(__('Invalid webhook token', 'systemeio-hook-sync'), 403);
    }
}