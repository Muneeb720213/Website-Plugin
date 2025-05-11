<?php
if (!defined('ABSPATH')) exit;

class SystemeIO_Admin_Settings {
    private $api;
    private $hook_logger;
    private $tag_manager;
    private $settings_page = 'systemeio-settings';
    private $settings_group = 'systemeio_settings_group';

    public function __construct($api, $hook_logger, $tag_manager) {
        $this->api = $api;
        $this->hook_logger = $hook_logger;
        $this->tag_manager = $tag_manager;

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'setup_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_notices', array($this, 'display_admin_notices'));
        
        // AJAX handlers
        add_action('wp_ajax_systemeio_test_connection', array($this, 'ajax_test_connection'));
    }

    public function activate() {
        if (!get_option('systemeio_api_key')) {
            add_option('systemeio_api_key', '');
        }
        if (!get_option('systemeio_tag_mappings')) {
            add_option('systemeio_tag_mappings', array());
        }
        if (!get_option('systemeio_logged_hooks')) {
            add_option('systemeio_logged_hooks', array());
        }
        if (!get_option('systemeio_active_hooks')) {
            add_option('systemeio_active_hooks', array(
                'user_register',
                'wpforms_process_complete',
                'gform_after_submission',
                'cf7_submit'
            ));
        }
        if (!get_option('systemeio_activity_log')) {
            add_option('systemeio_activity_log', array());
        }
    }

    public function deactivate() {
        // Optional: Clean up options
    }

    public static function uninstall() {
        delete_option('systemeio_api_key');
        delete_option('systemeio_tag_mappings');
        delete_option('systemeio_logged_hooks');
        delete_option('systemeio_active_hooks');
        delete_option('systemeio_activity_log');
    }

    public function add_admin_menu() {
        add_options_page(
            __('Systeme.io Sync Settings', 'systemeio-hook-sync'),
            __('Systeme.io Sync', 'systemeio-hook-sync'),
            'manage_options',
            $this->settings_page,
            array($this, 'render_settings_page')
        );
    }

    public function enqueue_admin_assets($hook) {
        if ($hook !== 'settings_page_' . $this->settings_page) return;

        wp_enqueue_style(
            'systemeio-admin-css',
            SYSTEMEIO_PLUGIN_URL . 'assets/admin-style.css',
            array(),
            SYSTEMEIO_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'systemeio-admin-js',
            SYSTEMEIO_PLUGIN_URL . 'assets/admin-scripts.js',
            array('jquery'),
            SYSTEMEIO_PLUGIN_VERSION,
            true
        );

        wp_localize_script('systemeio-admin-js', 'systemeio_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('systemeio_ajax_nonce'),
            'testing' => __('Testing...', 'systemeio-hook-sync'),
            'connected' => __('Connected successfully', 'systemeio-hook-sync'),
            'failed' => __('Connection failed', 'systemeio-hook-sync')
        ));
    }

    public function display_admin_notices() {
        if (!current_user_can('manage_options')) return;

        if (!get_option('systemeio_api_key')) {
            echo '<div class="notice notice-warning"><p>';
            echo __('Systeme.io Sync is not configured. Please enter your API key.', 'systemeio-hook-sync');
            echo '</p></div>';
        }

        $screen = get_current_screen();
        if ($screen->id === 'settings_page_' . $this->settings_page) {
            $logs = get_option('systemeio_activity_log', array());
            foreach (array_slice($logs, 0, 3) as $log) {
                $class = strpos($log['message'], 'failed') !== false ? 'error' : 'updated';
                echo '<div class="notice notice-' . $class . '"><p>';
                echo '<strong>' . esc_html($log['message']) . '</strong><br>';
                echo esc_html($log['timestamp']);
                echo '</p></div>';
            }
        }
    }

    public function setup_settings() {
        register_setting($this->settings_group, 'systemeio_api_key', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));

        add_settings_section(
            'systemeio_api_section',
            __('API Settings', 'systemeio-hook-sync'),
            array($this, 'render_api_section'),
            $this->settings_page
        );

        add_settings_field(
            'systemeio_api_key',
            __('API Key', 'systemeio-hook-sync'),
            array($this, 'render_api_key_field'),
            $this->settings_page,
            'systemeio_api_section'
        );
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'systemeio-hook-sync'));
        }

        $tags = $this->tag_manager->get_available_tags();
        $tag_mappings = $this->tag_manager->get_tag_mappings();
        $active_hooks = get_option('systemeio_active_hooks', array());
        $logged_hooks = $this->hook_logger->get_logged_hooks();
        ?>
        <div class="wrap systemeio-settings">
            <h1><?php esc_html_e('Systeme.io Hook Sync Settings', 'systemeio-hook-sync'); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields($this->settings_group);
                do_settings_sections($this->settings_page);
                submit_button();
                ?>
            </form>

            <div id="systemeio-connection-status" class="systemeio-status-box">
                <h3><?php esc_html_e('API Connection Status', 'systemeio-hook-sync'); ?></h3>
                <div class="status-indicator"></div>
                <button id="systemeio-test-connection" class="button">
                    <?php esc_html_e('Test Connection', 'systemeio-hook-sync'); ?>
                </button>
                <div class="status-message"></div>
            </div>

            <div class="systemeio-hook-mappings">
                <h2><?php esc_html_e('Hook to Tag Mappings', 'systemeio-hook-sync'); ?></h2>
                
                <form method="post" action="">
                    <?php wp_nonce_field('systemeio_add_hook'); ?>
                    <h3><?php esc_html_e('Add New Hook', 'systemeio-hook-sync'); ?></h3>
                    <input type="text" name="new_hook_name" placeholder="<?php esc_attr_e('Enter hook name', 'systemeio-hook-sync'); ?>">
                    <input type="submit" name="add_new_hook" class="button" value="<?php esc_attr_e('Add Hook', 'systemeio-hook-sync'); ?>">
                </form>

                <form method="post" action="">
                    <?php wp_nonce_field('systemeio_update_mappings'); ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Hook', 'systemeio-hook-sync'); ?></th>
                                <th><?php esc_html_e('Tags to Add', 'systemeio-hook-sync'); ?></th>
                                <th><?php esc_html_e('Tags to Remove', 'systemeio-hook-sync'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active_hooks as $hook): ?>
                            <tr>
                                <td><code><?php echo esc_html($hook); ?></code></td>
                                <td>
                                    <select name="tag_mappings[<?php echo esc_attr($hook); ?>][add_tags][]" multiple class="systemeio-tags-select">
                                        <?php foreach ($tags as $tag): ?>
                                        <option value="<?php echo esc_attr($tag['id']); ?>" <?php selected(isset($tag_mappings[$hook]['add_tags']) && in_array($tag['id'], $tag_mappings[$hook]['add_tags'])); ?>>
                                            <?php echo esc_html($tag['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <select name="tag_mappings[<?php echo esc_attr($hook); ?>][remove_tags][]" multiple class="systemeio-tags-select">
                                        <?php foreach ($tags as $tag): ?>
                                        <option value="<?php echo esc_attr($tag['id']); ?>" <?php selected(isset($tag_mappings[$hook]['remove_tags']) && in_array($tag['id'], $tag_mappings[$hook]['remove_tags'])); ?>>
                                            <?php echo esc_html($tag['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <input type="submit" name="update_mappings" class="button button-primary" value="<?php esc_attr_e('Save Mappings', 'systemeio-hook-sync'); ?>">
                </form>
            </div>

            <div class="systemeio-hook-logs">
                <h2><?php esc_html_e('Hook Activity Log', 'systemeio-hook-sync'); ?></h2>
                
                <form method="post" action="">
                    <?php wp_nonce_field('systemeio_clear_logs'); ?>
                    <input type="submit" name="clear_logs" class="button" value="<?php esc_attr_e('Clear Logs', 'systemeio-hook-sync'); ?>">
                </form>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Date', 'systemeio-hook-sync'); ?></th>
                            <th><?php esc_html_e('Hook', 'systemeio-hook-sync'); ?></th>
                            <th><?php esc_html_e('Email', 'systemeio-hook-sync'); ?></th>
                            <th><?php esc_html_e('Details', 'systemeio-hook-sync'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logged_hooks)): ?>
                            <tr>
                                <td colspan="4"><?php esc_html_e('No hooks logged yet.', 'systemeio-hook-sync'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach (array_reverse($logged_hooks) as $log): ?>
                            <tr>
                                <td><?php echo esc_html($log['timestamp']); ?></td>
                                <td><code><?php echo esc_html($log['hook']); ?></code></td>
                                <td><?php echo esc_html($log['email']); ?></td>
                                <td>
                                    <?php
                                    $details = array_diff_key($log, array_flip(array('hook', 'email', 'timestamp')));
                                    echo esc_html(implode(', ', array_map(function($k, $v) {
                                        return "$k: $v";
                                    }, array_keys($details), $details)));
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public function render_api_section() {
        echo '<p>' . esc_html__('Enter your Systeme.io API credentials below.', 'systemeio-hook-sync') . '</p>';
    }

    public function render_api_key_field() {
        $api_key = get_option('systemeio_api_key', '');
        echo '<input type="password" id="systemeio_api_key" name="systemeio_api_key" value="' . esc_attr($api_key) . '" class="regular-text">';
        echo '<p class="description">' . esc_html__('Get your API key from Systeme.io settings.', 'systemeio-hook-sync') . '</p>';
    }

    public function ajax_test_connection() {
        check_ajax_referer('systemeio_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'systemeio-hook-sync'), 403);
        }
        
        $result = $this->api->test_api_connection();
        
        if ($result) {
            wp_send_json_success([
                'message' => __('API connection successful', 'systemeio-hook-sync'),
                'tags' => $this->api->get_all_tags()
            ]);
        } else {
            wp_send_json_error(__('API connection failed. Please check your API key.', 'systemeio-hook-sync'));
        }
    }
}