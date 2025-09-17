<?php
// File: elevate-client-portal/includes/class-ecp-asset-manager.php
/**
 * Handles loading of all CSS and JavaScript assets for the plugin.
 *
 * @package Elevate_Client_Portal
 * @version 43.0.0 (Final Audit & Refactor)
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class ECP_Asset_Manager {

    private static $instance;
    private $plugin_path;
    private $plugin_url;

    public static function get_instance( $path, $url ) {
        if ( null === self::$instance ) self::$instance = new self( $path, $url );
        return self::$instance;
    }

    private function __construct( $path, $url ) {
        $this->plugin_path = $path;
        $this->plugin_url = $url;
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_scripts' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
        add_action( 'wp_head', [ $this, 'output_custom_styles' ] );
    }

    public function enqueue_frontend_scripts() {
        $has_client_shortcode = ECP_Shortcode_Helper::page_has_shortcode('client_portal');
        $has_account_shortcode = ECP_Shortcode_Helper::page_has_shortcode('ecp_account');
        $has_admin_shortcode = ECP_Shortcode_Helper::page_has_shortcode('elevate_admin_dashboard');
        $has_login_shortcode = ECP_Shortcode_Helper::page_has_shortcode('elevate_login');

        if ( $has_client_shortcode || $has_account_shortcode || $has_admin_shortcode || $has_login_shortcode ) {
             wp_enqueue_style( 'ecp-styles', $this->plugin_url . 'assets/css/ecp-styles.css', [], ECP_VERSION );
        }
        
        if ( $has_client_shortcode || $has_account_shortcode ) {
             wp_enqueue_script( 'ecp-client-portal-js', $this->plugin_url . 'assets/js/ecp-client-portal.js', ['jquery'], ECP_VERSION, true );
             wp_localize_script( 'ecp-client-portal-js', 'ecp_ajax', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'home_url' => home_url('/'),
                'nonces' => ECP_Security_Helper::get_script_nonces(['client_portal', 'decrypt_file', 'contact_manager', 'update_account']),
                'strings' => [
                    'error_zip' => __('An unknown error occurred while creating the ZIP file.', 'ecp'),
                    'copied' => __('Copied!', 'ecp'),
                    'decrypt_prompt' => __('This file is encrypted. Please enter the password to download:', 'ecp'),
                ]
             ]);
        }

        if ( $has_admin_shortcode ) {
            // ** FIX: Load the single, consolidated dashboard script. **
            wp_enqueue_script( 'ecp-admin-dashboard-js', $this->plugin_url . 'assets/js/ecp-admin-dashboard.js', ['jquery'], ECP_VERSION, true );
            
            wp_localize_script( 'ecp-admin-dashboard-js', 'ecp_ajax', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'home_url' => home_url('/'),
                'nonces' => ECP_Security_Helper::get_script_nonces(['dashboard', 'view', 'file_manager', 'decrypt_file']),
                'strings' => [
                    'confirm_action' => __('Are you sure you want to %s this user?', 'ecp'),
                    'confirm_delete_user' => __('Are you sure you want to permanently remove this user and all their files?', 'ecp'),
                    'confirm_delete_file' => __('Are you sure you want to delete the selected files? This cannot be undone.', 'ecp'),
                    'confirm_delete_folder' => __('Are you sure you want to delete this folder? All files inside will be moved to Uncategorized.', 'ecp'),
                    'encrypt_prompt' => __('Please enter a password to encrypt this file:', 'ecp'),
                    'decrypt_prompt' => __('Please enter the password to decrypt this file:', 'ecp'),
                ]
            ]);
        }
    }

    public function enqueue_admin_scripts($hook) {
        $allowed_hooks = ['ecp_client_page_ecp-settings', 'ecp_client_page_ecp-s3-browser', 'ecp_client_page_ecp-audit-log'];
        if ( in_array($hook, $allowed_hooks) ) {
             wp_enqueue_style( 'ecp-admin-styles', $this->plugin_url . 'assets/css/ecp-admin-styles.css', [], ECP_VERSION );
        }

        if ( 'ecp_client_page_ecp-settings' === $hook ) {
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'ecp-admin-settings-js', $this->plugin_url . 'assets/js/ecp-admin-settings.js', [ 'jquery', 'wp-color-picker' ], ECP_VERSION, true );
            wp_localize_script( 'ecp-admin-settings-js', 'ecp_ajax', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => ECP_Security_Helper::create_nonce('admin_settings'),
            ]);
        }
    }

    public function output_custom_styles() {
        $options = get_option('ecp_style_options');
        $primary_color = !empty($options['primary_color']) ? sanitize_hex_color($options['primary_color']) : '#007cba';
        $secondary_color = !empty($options['secondary_color']) ? sanitize_hex_color($options['secondary_color']) : '#f0f6fc';
        ?>
        <style type="text/css">
            :root {
                --ecp-primary-color: <?php echo esc_html($primary_color); ?>;
                --ecp-secondary-color: <?php echo esc_html($secondary_color); ?>;
            }
        </style>
        <?php
    }
}

