<?php
// File: elevate-client-portal/includes/helpers/class-ecp-permissions-helper.php
/**
 * A dedicated helper class for managing user capabilities and permissions checks.
 *
 * @package Elevate_Client_Portal
 * @version 42.1.0 (Fatal Error Fix)
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

// ** FIX: Corrected the class name from ECP_Security_Helper to ECP_Permissions_Helper **
class ECP_Permissions_Helper {

    /**
     * Checks if the current user has the required capability.
     * Sends a JSON error and dies if the check fails.
     *
     * @param string $capability The capability to check (e.g., 'edit_users').
     * @param string $error_message The message to send on failure.
     */
    public static function check_permission_or_die( $capability, $error_message = 'Permission Denied.' ) {
        if ( ! current_user_can( $capability ) ) {
            wp_send_json_error( [ 'message' => __( $error_message, 'ecp' ) ] );
        }
    }

    /**
     * Checks if a user has permission to manage a specific user's files.
     *
     * @param int $target_user_id The ID of the user whose files are being accessed.
     * @return bool True if the current user has permission, false otherwise.
     */
    public static function can_manage_user_files( $target_user_id ) {
        if ( current_user_can( 'ecp_manage_all_users_files' ) ) {
            return true;
        }

        if ( current_user_can( 'ecp_manage_user_files' ) ) {
            $managed_users = get_users([
                'meta_key' => '_ecp_managed_by',
                'meta_value' => get_current_user_id(),
                'fields' => 'ID',
            ]);
            return in_array( $target_user_id, $managed_users );
        }

        return false;
    }
}

