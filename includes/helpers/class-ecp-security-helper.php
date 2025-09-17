<?php
// File: elevate-client-portal/includes/helpers/class-ecp-security-helper.php
/**
 * A dedicated helper class for handling all security-related functionality,
 * primarily WordPress nonces for verifying AJAX requests.
 *
 * @package Elevate_Client_Portal
 * @version 40.0.0 (Full Refactor)
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class ECP_Security_Helper {

    private static $nonces = [
        'dashboard' => 'ecp_dashboard_nonce',
        'view' => 'ecp_view_nonce',
        'file_manager' => 'ecp_file_manager_nonce',
        'decrypt_file' => 'ecp_decrypt_file_nonce',
        'client_portal' => 'ecp_client_ajax_nonce',
        'admin_settings' => 'ecp_admin_ajax_nonce',
        'update_account' => 'ecp_update_account_nonce',
        'contact_manager' => 'ecp_contact_manager_nonce',
        'login' => 'ecp-login-nonce-action',
        'logout' => 'ecp_logout_nonce',
        'download_file' => 'ecp_download_file_nonce',
        'download_zip' => 'ecp_zip_download_nonce',
        'impersonate' => 'ecp_impersonate_nonce',
        'stop_impersonate' => 'ecp_stop_impersonate_nonce',
    ];

    /**
     * Creates a WordPress nonce for a specific action.
     *
     * @param string $action The key for the action (e.g., 'dashboard', 'view').
     * @return string The generated nonce.
     */
    public static function create_nonce( $action ) {
        return wp_create_nonce( self::$nonces[ $action ] ?? 'ecp_general_nonce' );
    }

    /**
     * Verifies a WordPress nonce from a request.
     *
     * @param string $action  The key for the action to verify.
     * @param string $nonce_key The key in the request data (e.g., $_POST) where the nonce is stored.
     * @return bool True if the nonce is valid, false otherwise.
     */
    public static function verify_nonce( $action, $nonce_key = 'nonce' ) {
        $nonce_value = $_REQUEST[ $nonce_key ] ?? '';
        return wp_verify_nonce( $nonce_value, self::$nonces[ $action ] ?? 'ecp_general_nonce' );
    }

    /**
     * Generates an array of all nonces needed for a frontend script.
     *
     * @param array $actions A list of action keys to generate nonces for.
     * @return array An associative array of nonces.
     */
    public static function get_script_nonces( $actions ) {
        $nonces = [];
        foreach ( $actions as $action ) {
            $key = str_replace( '_', '', ucwords( $action, '_' ) ) . 'Nonce';
            $nonces[ lcfirst( $key ) ] = self::create_nonce( $action );
        }
        return $nonces;
    }
}
