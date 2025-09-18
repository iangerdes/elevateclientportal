<?php
// File: elevate-client-portal/admin/includes/class-ecp-ajax-file-manager.php
/**
 * Handles all AJAX requests related to the File Manager component.
 * This file was created to separate file management logic from the main dashboard controller.
 *
 * @package Elevate_Client_Portal
 * @version 45.0.0
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class ECP_Ajax_File_Manager {

    private static $instance;
    private $file_manager_component;
    private $plugin_path;

    public static function get_instance( $path, $url ) {
        if ( null === self::$instance ) {
            self::$instance = new self( $path, $url );
        }
        return self::$instance;
    }

    private function __construct( $path, $url ) {
        $this->plugin_path = $path;
        // The file manager component is needed to render table rows on filter actions.
        require_once $this->plugin_path . 'frontend/class-ecp-file-manager.php';
        $this->file_manager_component = new ECP_File_Manager_Component($path, $url);

        add_action( 'wp_ajax_ecp_file_manager_actions', [ $this, 'handle_request' ] );
    }

    /**
     * Handles all incoming AJAX requests for file and folder actions.
     * @version 45.0.0
     * @comment Added a granular permission check to fix upload errors for manager roles.
     */
    public function handle_request() {
        check_ajax_referer('ecp_file_manager_nonce', 'nonce');
        
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        // ** FIX: Perform a granular permission check for the target user. **
        // This ensures a Client Manager can't operate on files they don't manage,
        // and resolves the upload permission error for manager roles.
        if ( ! ECP_Permissions_Helper::can_manage_user_files( $user_id ) ) {
            wp_send_json_error(['message' => __('You do not have permission to manage these files.', 'ecp')]);
        }
        
        $sub_action = $_POST['sub_action'] ?? '';
        $result = ['success' => false, 'message' => __('Invalid action.', 'ecp')];

        switch($sub_action) {
            case 'bulk_actions':
                $result = ECP_Bulk_Actions::handle_bulk_file_actions($user_id, $_POST['file_keys'] ?? [], $_POST['bulk_action'] ?? '', $_POST['details'] ?? '');
                break;
            case 'upload_file':
                if ( ! empty( $_FILES['ecp_file_upload'] ) ) $result = ECP_File_Operations::handle_file_upload($user_id, $_FILES['ecp_file_upload'], $_POST);
                else $result = ['success' => false, 'message' => __('No file was uploaded.', 'ecp')];
                break;
            case 'add_folder':
                $result = ($user_id === 0) ? ECP_Folder_Operations::handle_all_users_add_folder_logic($_POST) : ECP_Folder_Operations::handle_add_folder_logic($user_id, $_POST);
                break;
            case 'delete_folder':
                $folder_to_delete = isset($_POST['folder_name']) ? sanitize_text_field($_POST['folder_name']) : '';
                if (empty($folder_to_delete)) {
                    $result = ['success' => false, 'message' => __('Folder name was not provided.', 'ecp')];
                } else {
                    $result = ($user_id === 0) ? ECP_Folder_Operations::handle_all_users_delete_folder_logic($folder_to_delete) : ECP_Folder_Operations::handle_delete_folder_logic($user_id, $folder_to_delete);
                }
                break;
            case 'update_category':
                $result = ECP_Bulk_Actions::update_file_category_logic($user_id, $_POST['file_key'], $_POST['new_folder']);
                break;
            case 'filter_files':
                $folder = $_POST['folder'] ?? 'all';
                $html = ($user_id === 0) ? $this->file_manager_component->render_all_users_table_rows($folder) : $this->file_manager_component->render_table_rows($user_id, $folder);
                wp_send_json_success($html);
                return; // End execution here
        }

        if($result['success']) { wp_send_json_success(['message' => $result['message']]); } 
        else { wp_send_json_error(['message' => $result['message'] ?? __('An unspecified error occurred.', 'ecp')]); }
    }
}
