<?php
// File: elevate-client-portal/includes/class-ecp-auth-handler.php
/**
 * Handles all authentication-related functionality for the plugin.
 *
 * @package Elevate_Client_Portal
 * @version 114.0.0 (Capability-Based Redirect)
 * @comment Re-engineered the post-login redirect to use user capabilities instead of role names. This is a more robust and flexible method that correctly redirects all custom roles, fixing the issue where non-admins were sent back to the login page.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class ECP_Auth_Handler {

    private static $instance;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_login', [ $this, 'handle_successful_login_redirect' ], 10, 2 );
        add_action( 'after_setup_theme', [ $this, 'remove_admin_bar' ] );
        add_filter( 'wp_authenticate_user', [ $this, 'check_if_user_is_disabled' ], 10, 2 );
        add_action( 'template_redirect', [ $this, 'force_login_redirect' ] );
        add_action( 'template_redirect', [ $this, 'prevent_plugin_page_caching' ] );
        add_filter( 'wp_mail_from', [ $this, 'set_mail_from_address' ] );
        add_filter( 'wp_mail_from_name', [ $this, 'set_mail_from_name' ] );
        add_filter( 'retrieve_password_message', [ $this, 'custom_password_reset_message' ], 10, 4 );
    }
    
    /**
     * Handles the redirect after a user successfully logs in using capabilities.
     */
    public function handle_successful_login_redirect( $user_login, $user ) {
        if ( isset( $_REQUEST['redirect_to'] ) && ! empty( $_REQUEST['redirect_to'] ) ) {
            $redirect_url = esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) );
            if ( wp_validate_redirect( $redirect_url, home_url() ) ) {
                wp_safe_redirect( $redirect_url );
                exit;
            }
        }

        // ** FIX: Use capability checks instead of role checks for a more robust redirect. **
        if ( user_can( $user, 'edit_users' ) ) {
            $dashboard_page = get_page_by_path('admin-dashboard');
            if ($dashboard_page) {
                wp_safe_redirect( get_permalink($dashboard_page->ID) );
                exit;
            }
        } elseif ( user_can( $user, 'read' ) ) {
            $portal_page = get_page_by_path('client-portal');
            if ($portal_page) {
                wp_safe_redirect( get_permalink($portal_page->ID) );
                exit;
            }
        }

        wp_safe_redirect( home_url() );
        exit;
    }
    
    public function prevent_plugin_page_caching() {
        $shortcodes_to_check = ['elevate_login', 'client_portal', 'elevate_admin_dashboard'];
        $found_shortcode = false;
        foreach ($shortcodes_to_check as $shortcode) {
            if ( ECP_Shortcode_Helper::page_has_shortcode($shortcode) ) {
                $found_shortcode = true;
                break;
            }
        }
        if ( $found_shortcode ) {
            if ( ! defined( 'DONOTCACHEPAGE' ) ) {
                define( 'DONOTCACHEPAGE', true );
            }
            nocache_headers();
        }
    }

    public function remove_admin_bar() {
        if ( ! current_user_can('administrator') && ! is_admin() && ! ECP_Impersonation_Handler::is_impersonating() ) {
            show_admin_bar(false);
        }
    }

    public function check_if_user_is_disabled( $user, $password ) {
        if ( is_a($user, 'WP_User') && get_user_meta( $user->ID, 'ecp_user_disabled', true ) ) {
            return new WP_Error( 'user_disabled', __( '<strong>ERROR</strong>: Your account has been disabled.', 'ecp' ) );
        }
        return $user;
    }

    public function force_login_redirect() {
        if ( ! is_user_logged_in() && ( ECP_Shortcode_Helper::page_has_shortcode('client-portal') || ECP_Shortcode_Helper::page_has_shortcode('elevate_admin_dashboard') ) ) {
            global $post;
            $login_page = get_page_by_path('login');
            if ( ! $login_page ) {
                wp_die('Elevate Client Portal Error: The "login" page could not be found. Please ensure a page with the slug "login" exists and contains the [elevate_login] shortcode.');
            }
            $login_url = get_permalink( $login_page->ID );
            $redirect_url = add_query_arg( 'redirect_to', urlencode( get_permalink( $post->ID ) ), $login_url );
            wp_safe_redirect( $redirect_url );
            exit;
        }
    }

    public function set_mail_from_address( $original_email_address ) {
        $domain = wp_parse_url( home_url(), PHP_URL_HOST );
        if (substr($domain, 0, 4) == 'www.') {
            $domain = substr($domain, 4);
        }
        return 'noreply@' . $domain;
    }

    public function set_mail_from_name( $original_email_from ) {
        return get_bloginfo( 'name' );
    }

    public function custom_password_reset_message( $message, $key, $user_login, $user_data ) {
        $login_page = get_page_by_path( 'login' );
        if ( ! $login_page ) {
            return $message;
        }
        $login_page_url = get_permalink( $login_page->ID );
        $reset_url = add_query_arg( [
            'action' => 'rp',
            'key'    => $key,
            'login'  => rawurlencode( $user_login ),
        ], $login_page_url );
        $message = preg_replace( '/<(.+?)>/', '<' . esc_url_raw( $reset_url ) . '>', $message );
        return $message;
    }
}

