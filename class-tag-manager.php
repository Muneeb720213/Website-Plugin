<?php
if (!defined('ABSPATH')) exit;

class SystemeIO_Tag_Manager {
    private $api;
    private $tag_mappings = array();

    public function __construct($api) {
        $this->api = $api;
        $this->tag_mappings = get_option('systemeio_tag_mappings', array());
        $this->setup_hook_actions();
    }

    private function setup_hook_actions() {
        foreach ($this->tag_mappings as $hook => $mapping) {
            add_action($hook, function($data) use ($hook) {
                $this->process_hook_tags($hook, $data);
            }, 10, 1);
        }
    }

    public function process_hook_tags($hook, $data) {
        if (!isset($this->tag_mappings[$hook])) return;

        $email = systemeio_find_email($data);
        if (empty($email)) return;

        $contact = $this->api->create_or_update_contact($email);
        if (!$contact || empty($contact['id'])) {
            systemeio_log_activity('Failed to create/update contact', [
                'email' => $email,
                'hook' => $hook,
                'error' => $this->api->get_last_error()
            ]);
            return;
        }

        $mapping = $this->tag_mappings[$hook];

        // Add tags
        if (!empty($mapping['add_tags'])) {
            foreach ($mapping['add_tags'] as $tag_id) {
                $result = $this->api->add_tag_to_contact($contact['id'], $tag_id);
                if (!$result) {
                    systemeio_log_activity('Failed to add tag to contact', [
                        'contact_id' => $contact['id'],
                        'tag_id' => $tag_id,
                        'error' => $this->api->get_last_error()
                    ]);
                }
            }
        }

        // Remove tags
        if (!empty($mapping['remove_tags'])) {
            foreach ($mapping['remove_tags'] as $tag_id) {
                $result = $this->api->remove_tag_from_contact($contact['id'], $tag_id);
                if (!$result) {
                    systemeio_log_activity('Failed to remove tag from contact', [
                        'contact_id' => $contact['id'],
                        'tag_id' => $tag_id,
                        'error' => $this->api->get_last_error()
                    ]);
                }
            }
        }

        systemeio_log_activity('Processed hook tags', [
            'hook' => $hook,
            'email' => $email,
            'contact_id' => $contact['id'],
            'tags_added' => $mapping['add_tags'] ?? [],
            'tags_removed' => $mapping['remove_tags'] ?? []
        ]);
    }

    public function get_available_tags() {
        return $this->api->get_all_tags();
    }

    public function update_tag_mappings($mappings) {
        $this->tag_mappings = $mappings;
        update_option('systemeio_tag_mappings', $mappings);
    }

    public function get_tag_mappings() {
        return $this->tag_mappings;
    }
}