<?php
// File: elevate-client-portal/frontend/class-ecp-login.php
/**
 * Handles all logic for the [elevate_login] shortcode.
 * @package Elevate_Client_Portal
 * @version 122.0.0 (JS Loading Fix)
 * @comment Removed direct script enqueuing. Asset loading is now handled globally by the ECP_Asset_Manager.
 */
class ECP_Login {

    private static $instance;
    private $plugin_path;
    private $plugin_url;
    
    public static function get_instance( $path, $url ) { 
        if ( null === self::$instance ) { 
            self::$instance = new self( $path, $url ); 
        } 
        return self::$instance; 
    }

    private function __construct( $path, $url ) {
        $this->plugin_path = $path;
        $this->plugin_url = $url;
        add_shortcode( 'elevate_login', [ $this, 'render_shortcode' ] );
    }
    
    public function render_shortcode() {
        // ** REMOVED: Script loading is now handled by the ECP_Asset_Manager. **
        
        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            $allowed_admin_roles = ['ecp_business_admin', 'administrator', 'ecp_client_manager'];
            $logout_button = '<a href="#" class="button ecp-ajax-logout-btn">' . __('Logout', 'ecp') . '</a>';

            if( count(array_intersect($allowed_admin_roles, $user->roles)) > 0 ) {
                 $admin_url = home_url('/admin-dashboard');
                 return '<div class="ecp-login-form-wrapper"><p>' . sprintf( __('You are logged in. %s or %s.', 'ecp'), '<a href="' . esc_url($admin_url) . '">' . __('Go to Admin Dashboard', 'ecp') . '</a>', $logout_button) . '</p></div>';
            } else {
                 $portal_url = home_url('/client-portal');
                 return '<div class="ecp-login-form-wrapper"><p>' . sprintf( __('You are already logged in. %s or %s.', 'ecp'), '<a href="' . esc_url($portal_url) . '">' . __('View your files', 'ecp') . '</a>', $logout_button) . '</p></div>';
            }
        }
        
        // ** FIX: Use the native WordPress login form for maximum compatibility. **
        ob_start();
        ?>
        <div class="ecp-login-form-wrapper">
            <h3><?php _e('Client Login', 'ecp'); ?></h3>
            <?php
            // Display any login errors passed in the URL
            if ( !empty($_REQUEST['login']) ) {
                $error_code = sanitize_key($_REQUEST['login']);
                $error_message = '';
                if ( $error_code === 'failed' ) {
                    $error_message = __('Invalid username or password. Please try again.', 'ecp');
                } elseif ( $error_code === 'empty' ) {
                    $error_message = __('Username and password fields cannot be empty.', 'ecp');
                } elseif ( $error_code === 'disabled' ) {
                    $error_message = __('Your account has been disabled.', 'ecp');
                }
                if($error_message) {
                   echo '<p class="ecp-login-error"><strong>' . __('Error:', 'ecp') . '</strong> ' . esc_html($error_message) . '</p>';
                }
            }

            // Determine the redirect URL after login.
            $redirect_to = home_url();
            if ( isset( $_REQUEST['redirect_to'] ) ) {
                $redirect_to = esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) );
            }

            // Arguments for the native WordPress login form
            $args = array(
                'echo'           => true,
                'redirect'       => $redirect_to, 
                'form_id'        => 'loginform',
                'label_username' => __( 'Email Address', 'ecp' ),
                'label_password' => __( 'Password', 'ecp' ),
                'label_remember' => __( 'Remember Me', 'ecp' ),
                'label_log_in'   => __( 'Log In', 'ecp' ),
                'id_username'    => 'user_login',
                'id_password'    => 'user_pass',
                'id_remember'    => 'rememberme',
                'id_submit'      => 'wp-submit',
                'remember'       => true,
                'value_username' => '',
                'value_remember' => false,
            );
            wp_login_form( $args );
            ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

