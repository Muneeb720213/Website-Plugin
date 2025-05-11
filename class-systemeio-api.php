<?php
if (!defined('ABSPATH')) exit;

class SystemeIO_API {
    private $api_key;
    private $api_base_url = 'https://api.systeme.io/api/';
    private $last_error = '';

    public function __construct() {
        $this->api_key = get_option('systemeio_api_key', '');
    }

    public function get_last_error() {
        return $this->last_error;
    }

    private function make_request($endpoint, $method = 'GET', $data = array()) {
        $this->last_error = '';
        
        if (empty($this->api_key)) {
            $this->last_error = __('API key is not set', 'systemeio-hook-sync');
            systemeio_log_activity('API request failed - no API key set');
            return false;
        }

        $url = $this->api_base_url . ltrim($endpoint, '/');
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ),
            'timeout' => 15
        );

        if (!empty($data)) {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $this->last_error = $response->get_error_message();
            systemeio_log_activity('API request failed', [
                'endpoint' => $endpoint,
                'error' => $this->last_error
            ]);
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code < 200 || $response_code >= 300) {
            $this->last_error = sprintf(
                __('HTTP %d - %s', 'systemeio-hook-sync'),
                $response_code,
                $response_body['message'] ?? __('Unknown error', 'systemeio-hook-sync')
            );
            
            systemeio_log_activity('API request failed', [
                'endpoint' => $endpoint,
                'status' => $response_code,
                'response' => $response_body
            ]);
            
            return false;
        }

        systemeio_log_activity('API request successful', [
            'endpoint' => $endpoint,
            'method' => $method,
            'data' => $data
        ]);
        
        return $response_body;
    }

    public function test_api_connection() {
        $response = $this->make_request('tags', 'GET');
        return $response !== false;
    }

    public function create_or_update_contact($email, $additional_data = array()) {
        $data = array_merge(array('email' => $email), $additional_data);
        return $this->make_request('contacts', 'POST', $data);
    }

    public function add_tag_to_contact($contact_id, $tag_id) {
        return $this->make_request("contacts/{$contact_id}/tags", 'POST', array('tag_id' => $tag_id));
    }

    public function remove_tag_from_contact($contact_id, $tag_id) {
        return $this->make_request("contacts/{$contact_id}/tags/{$tag_id}", 'DELETE');
    }

    public function get_contact_by_email($email) {
        $response = $this->make_request("contacts?email={$email}");
        return !empty($response['data'][0]) ? $response['data'][0] : false;
    }

    public function get_all_tags() {
        $response = $this->make_request('tags');
        return !empty($response['data']) ? $response['data'] : array();
    }

    public function get_contact_tags($contact_id) {
        return $this->make_request("contacts/{$contact_id}/tags");
    }
}