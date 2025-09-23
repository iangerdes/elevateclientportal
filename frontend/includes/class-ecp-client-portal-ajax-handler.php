<?php
// File: elevate-client-portal/frontend/includes/class-ecp-client-portal-ajax-handler.php
/**
 * Handles all server-side AJAX logic for the front-end Client Portal.
 *
 * @package Elevate_Client_Portal
 * @version 115.0.0 (Multi-Fix)
 * @comment Fixed the 'ecp_delete_ready_zip' action hook to resolve a 400 Bad Request error. Added cache clearing to the ZIP list function to ensure the "Ready Downloads" list updates immediately after a file is created.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class ECP_Client_Portal_Ajax_Handler {

    private static $instance;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_ajax_ecp_filter_files', [ $this, 'ajax_filter_files_handler' ] );
        add_action( 'wp_ajax_ecp_prepare_zip', [ $this, 'ajax_prepare_zip_handler' ] );
        add_action( 'wp_ajax_ecp_get_ready_downloads', [ $this, 'ajax_get_ready_zips_handler' ] );
        // ** FIX: Changed hook from 'ecp_delete_zip' to 'ecp_delete_ready_zip' to match JS call. **
        add_action( 'wp_ajax_ecp_delete_ready_zip', [ $this, 'ajax_delete_zip_handler' ] );
        add_action( 'wp_ajax_ecp_send_manager_email', [ $this, 'ajax_send_manager_email' ] );
        add_action( 'wp_ajax_ecp_update_account', [ $this, 'ajax_update_account_handler' ] );
        add_action( 'wp_ajax_ecp_ajax_logout', [ $this, 'ajax_logout_handler' ] );
    }
    
    public function ajax_filter_files_handler() {
        ECP_Security_Helper::verify_nonce_or_die('client_portal');
        $user_id = get_current_user_id(); 
        if(!$user_id) { wp_send_json_error(['message' => 'Not logged in.']); }

        $search_term = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $sort_order = isset($_POST['sort']) ? sanitize_text_field($_POST['sort']) : 'date_desc';
        $folder = isset($_POST['folder']) ? sanitize_text_field($_POST['folder']) : 'all';
        
        $all_files = ECP_File_Helper::get_hydrated_files_for_user($user_id);
        
        $filtered_files_data = [];

        if (is_array($all_files)) {
            $filtered_files = [];
            foreach($all_files as $key => $file) {
                if (is_array($file) && isset($file['name'])) {
                    $in_search = empty($search_term) || stripos($file['name'], $search_term) !== false;
                    $current_folder_name = is_array($file['folder']) ? ($file['folder']['name'] ?? '/') : ($file['folder'] ?? '/');
                    $in_folder = $folder === 'all' || $current_folder_name === $folder;

                    if($in_search && $in_folder) { 
                        $file['original_key'] = $key;
                        $filtered_files[] = $file;
                    }
                }
            }

            uasort($filtered_files, function($a, $b) use ($sort_order) {
                switch ($sort_order) {
                    case 'name_asc': return strcasecmp($a['name'], $b['name']);
                    case 'name_desc': return strcasecmp($b['name'], $a['name']);
                    case 'date_asc': return ($a['timestamp'] ?? 0) <=> ($b['timestamp'] ?? 0);
                    default: return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
                }
            });
            
            foreach ($filtered_files as $file_data) {
                $file_key = $file_data['s3_key'] ?? (isset($file_data['path']) ? md5($file_data['path']) : '');
                $download_link = wp_nonce_url( add_query_arg( [ 'ecp_action' => 'download_file', 'file_key' => urlencode($file_key) ], home_url() ), 'ecp_download_file_nonce' );
                $folder_name = ($file_data['folder'] ?? '/') === '/' ? 'Uncategorized' : ($file_data['folder']['name'] ?? $file_data['folder']);

                $filtered_files_data[] = [
                    'key'          => $file_key,
                    'name'         => esc_html($file_data['name']),
                    'folder'       => esc_html($folder_name),
                    'date'         => esc_html(date_i18n(get_option('date_format'), $file_data['timestamp'] ?? time())),
                    'download_url' => $download_link,
                    'size'         => ECP_File_Helper::format_file_size($file_data['size']),
                    'size_bytes'   => $file_data['size'],
                    'is_encrypted' => !empty($file_data['is_encrypted']),
                    'type'         => $file_data['type'] ?? 'application/octet-stream'
                ];
            }
        }
        
        wp_send_json_success($filtered_files_data);
    }

    public function ajax_prepare_zip_handler() { 
        ECP_Security_Helper::verify_nonce_or_die('zip_prepare');
        $user_id = get_current_user_id();
        $file_keys_str = isset($_POST['file_keys']) ? sanitize_text_field($_POST['file_keys']) : '';

        if (!$user_id || empty($file_keys_str)) {
            wp_send_json_error(['message' => __('Invalid request or no files selected.', 'ecp')]);
        }

        // Schedule the background task
        wp_schedule_single_event(time(), 'ecp_background_create_zip_action', [
            'user_id' => $user_id,
            'file_keys' => explode(',', $file_keys_str),
        ]);
        
        // Spawn the background process
        spawn_cron();
    
        wp_send_json_success([ 'message' => __('Your ZIP file is being prepared in the background. It will appear in your Ready Downloads list shortly.', 'ecp') ]);
    }
    
    public function ajax_get_ready_zips_handler() {
        ECP_Security_Helper::verify_nonce_or_die('zip_get_list');
        $user_id = get_current_user_id();
        if ( !$user_id ) { wp_send_json_error([]); }

        // ** FIX: Clear user cache to get the most up-to-date list of ZIPs. **
        clean_user_cache($user_id);

        $user_zips = get_user_meta($user_id, '_ecp_ready_zips', true) ?: [];
        $response_data = [];

        if (!empty($user_zips)) {
            krsort($user_zips); 
            foreach ($user_zips as $filename => $data) {
                 $download_url = add_query_arg([
                    'ecp_action' => 'download_zip',
                    'zip_file'   => urlencode($filename),
                    '_wpnonce'   => ECP_Security_Helper::create_nonce('zip_download')
                ], home_url('/'));
                $response_data[] = [
                    'filename' => esc_html($filename),
                    'date' => esc_html(date_i18n(get_option('date_format'), $data['timestamp'])),
                    'password' => esc_html($data['password']),
                    'download_url' => esc_url($download_url)
                ];
            }
        }
        wp_send_json_success($response_data);
    }

    public function ajax_delete_zip_handler() {
        ECP_Security_Helper::verify_nonce_or_die('zip_delete');
        $user_id = get_current_user_id();
        $filename = isset($_POST['filename']) ? sanitize_file_name($_POST['filename']) : '';

        if (!$user_id || empty($filename)) {
            wp_send_json_error(['message' => 'Invalid request.']);
        }
        
        $user_zips = get_user_meta($user_id, '_ecp_ready_zips', true) ?: [];
        if (isset($user_zips[$filename])) {
            $upload_dir = wp_upload_dir();
            $tmp_dir = $upload_dir['basedir'] . '/ecp_client_files/temp_zips/';
            $filepath = $tmp_dir . $filename;
            
            if (file_exists($filepath)) {
                wp_delete_file($filepath);
            }

            unset($user_zips[$filename]);
            update_user_meta($user_id, '_ecp_ready_zips', $user_zips);
            wp_send_json_success(['message' => 'ZIP file deleted.']);
        } else {
            wp_send_json_error(['message' => 'File not found in your list.']);
        }
    }
    
    public function ajax_logout_handler() {
        ECP_Security_Helper::verify_nonce_or_die('ajax_logout');
        wp_logout();
        $login_page = get_page_by_path('login');
        $redirect_url = $login_page ? get_permalink($login_page->ID) : home_url('/');
        wp_send_json_success(['redirect_url' => $redirect_url]);
    }

    public function ajax_send_manager_email() {
        ECP_Security_Helper::verify_nonce_or_die('contact_manager');
        $client_user = wp_get_current_user();
        if ( !$client_user->ID ) { wp_send_json_error(['message' => __('You must be logged in to send a message.', 'ecp')]); }
        $manager_id = get_user_meta($client_user->ID, '_ecp_managed_by', true) ?: get_user_meta($client_user->ID, '_ecp_created_by', true);
        if ( !$manager_id ) { wp_send_json_error(['message' => __('Account manager not found.', 'ecp')]); }
        $manager = get_userdata($manager_id);
        if ( !$manager ) { wp_send_json_error(['message' => __('Manager not found.', 'ecp')]); }
        $subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        if ( empty($subject) || empty($message) ) { wp_send_json_error(['message' => __('Please fill out all fields.', 'ecp')]); }

        $to = $manager->user_email;
        $email_subject = sprintf(__('[Client Portal] Message from %s: %s', 'ecp'), $client_user->display_name, $subject);
        $body = "You have received a new message from a client via the Elevate Client Portal.\n\n" .
                "Client Name: " . $client_user->display_name . "\n" .
                "Client Email: " . $client_user->user_email . "\n\n" .
                "Message:\n\n" . $message;
        $headers = [ 'Reply-To: ' . $client_user->display_name . ' <' . $client_user->user_email . '>' ];

        if ( wp_mail( $to, $email_subject, $body, $headers ) ) {
            wp_send_json_success(['message' => __('Your message has been sent successfully.', 'ecp')]);
        } else {
            wp_send_json_error(['message' => __('There was an error sending your message.', 'ecp')]);
        }
    }
    
    public function ajax_update_account_handler() {
        ECP_Security_Helper::verify_nonce_or_die('update_account');
        $user_id = get_current_user_id();
        if ( !$user_id ) { wp_send_json_error(['message' => __('You must be logged in.', 'ecp')]); }

        $data = $_POST;
        $user_data = [ 'ID' => $user_id ];

        if (isset($data['ecp_user_firstname']) && isset($data['ecp_user_surname'])) {
            $user_data['first_name'] = sanitize_text_field($data['ecp_user_firstname']);
            $user_data['last_name'] = sanitize_text_field($data['ecp_user_surname']);
            $user_data['display_name'] = $user_data['first_name'] . ' ' . $user_data['last_name'];
        }
        if (isset($data['ecp_user_title'])) {
            update_user_meta($user_id, 'ecp_user_title', sanitize_text_field($data['ecp_user_title']));
        }
        if (isset($data['ecp_user_mobile'])) {
            update_user_meta($user_id, 'ecp_user_mobile', sanitize_text_field($data['ecp_user_mobile']));
        }
        if ( !empty($data['ecp_user_email']) ) {
            $new_email = sanitize_email($data['ecp_user_email']);
            if ( !is_email($new_email) ) { wp_send_json_error(['message' => __('The new email address is not valid.', 'ecp')]); }
            if ( email_exists($new_email) && email_exists($new_email) != $user_id ) { wp_send_json_error(['message' => __('That email address is already in use.', 'ecp')]); }
            $user_data['user_email'] = $new_email;
        }

        if ( !empty($data['ecp_user_password']) ) {
            if ( $data['ecp_user_password'] !== $data['ecp_user_password_confirm'] ) { wp_send_json_error(['message' => __('The new passwords do not match.', 'ecp')]); }
            wp_set_password($data['ecp_user_password'], $user_id);
        }

        $result = wp_update_user($user_data);

        if ( is_wp_error($result) ) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            wp_send_json_success(['message' => __('Your account has been updated successfully.', 'ecp')]);
        }
    }
}

