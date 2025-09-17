<?php
// File: elevate-client-portal/includes/class-ecp-auth-handler.php
/**
 * Handles all authentication-related functionality for the plugin.
 *
 * @package Elevate_Client_Portal
 * @version 43.0.0 (Final Audit & Refactor)
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
        add_action( 'after_setup_theme', [ $this, 'remove_admin_bar' ] );
        add_filter( 'login_redirect', [ $this, 'login_redirect' ], 10, 3 );
        add_action( 'wp_authenticate_user', [ $this, 'check_if_user_is_disabled' ], 10, 2 );
        add_action( 'template_redirect', [ $this, 'force_login_redirect' ] );
        add_action( 'init', [ $this, 'handle_custom_logout' ] );
    }

    public function remove_admin_bar() {
        if ( ! current_user_can('administrator') && ! is_admin() && ! ECP_Impersonation_Handler::is_impersonating() ) {
            show_admin_bar(false);
        }
    }

    public function login_redirect( $redirect_to, $request, $user ) {
        if ( isset( $user->roles ) && is_array( $user->roles ) ) {
            if ( !empty($request) && $request !== home_url('/') && $request !== admin_url() ) {
                if (wp_validate_redirect($request, home_url())) {
                    return $request;
                }
            }
            if ( in_array( 'ecp_business_admin', $user->roles ) || in_array( 'administrator', $user->roles ) || in_array( 'ecp_client_manager', $user->roles ) ) {
                return home_url('/admin-dashboard');
            }
            if ( in_array( 'ecp_client', $user->roles ) || in_array( 'scp_client', $user->roles ) ) {
                return home_url('/client-portal');
            }
        }
        return $redirect_to;
    }

    public function check_if_user_is_disabled( $user, $password ) {
        if ( isset($user->ID) && get_user_meta( $user->ID, 'ecp_user_disabled', true ) ) {
            return new WP_Error( 'user_disabled', __( '<strong>ERROR</strong>: Your account has been disabled.', 'ecp' ) );
        }
        return $user;
    }

    public function handle_custom_logout() {
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'ecp_logout' ) {
            if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'ecp_logout_nonce' ) ) {
                wp_die( 'Security check failed.' );
            }
            wp_logout();
            wp_safe_redirect( home_url( '/login/' ) );
            exit;
        }
    }

    public function force_login_redirect() {
        if ( ! is_user_logged_in() && ( ECP_Shortcode_Helper::page_has_shortcode('client_portal') || ECP_Shortcode_Helper::page_has_shortcode('elevate_admin_dashboard') ) ) {
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
}

