<?php
// File: elevate-client-portal/includes/download-handlers/class-ecp-encrypted-file-handler.php
/**
 * Handles download requests for encrypted files.
 *
 * @package Elevate_Client_Portal
 * @version 42.0.0 (Final Audit & Refactor)
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class ECP_Encrypted_File_Handler {
    
    public function process() {
        if ( ! ECP_Security_Helper::verify_nonce('decrypt_file') ) {
            wp_die( 'Security check failed.', 403 );
        }

        $file_key = isset( $_REQUEST['file_key'] ) ? sanitize_text_field( urldecode( $_REQUEST['file_key'] ) ) : '';
        $password = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : '';
        $target_user_id = isset( $_REQUEST['target_user_id'] ) ? intval( $_REQUEST['target_user_id'] ) : 0;

        if ( empty( $file_key ) || empty( $password ) ) {
            wp_die( 'Invalid request.', 400 );
        }

        $file_info = ECP_File_Helper::find_and_authorize_file( $file_key, $target_user_id );
        
        if ( ! $file_info || empty( $file_info['is_encrypted'] ) ) {
            wp_die( 'File not found, not encrypted, or permission denied.', 404 );
        }

        $file_contents = ECP_File_Operations::get_file_contents( $file_info );
        if ( is_wp_error( $file_contents ) ) {
            wp_die( $file_contents->get_error_message(), 500 );
        }

        $decrypted_contents = ECP_File_Operations::decrypt_file_contents( $file_contents, $password );
        if ( $decrypted_contents === false ) {
            wp_die( 'Incorrect password or corrupted file.', 403 );
        }

        ECP_Audit_Log::log_event( get_current_user_id(), $file_info['name'] );
        
        $this->stream_decrypted_file( $file_info, $decrypted_contents );
    }

    private function stream_decrypted_file( $file_data, $decrypted_contents ) {
        header( 'Content-Description: File Transfer' );
        header( 'Content-Type: ' . esc_attr( $file_data['type'] ) );
        header( 'Content-Disposition: attachment; filename="' . esc_attr( $file_data['name'] ) . '"' );
        header( 'Expires: 0' );
        header( 'Cache-Control: must-revalidate' );
        header( 'Pragma: public' );
        header( 'Content-Length: ' . strlen( $decrypted_contents ) );
        
        ob_clean();
        flush();
        echo $decrypted_contents;
        exit;
    }
}

