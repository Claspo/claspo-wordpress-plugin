<?php

/**
 * Plugin Name: Claspo
 * Plugin URI: https://github.com/Claspo/claspo-wordpress-plugin
 * Description: Adds the Claspo script to all pages of the site.
 * Version: 1.0.0
 * Author: Claspo
 * Author URI: https://github.com/Claspo
 * License: GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const CLASPO_GET_SCRIPT_URL = 'https://script.claspo.io/site-script/v1/site/script/';

add_action( 'admin_menu', 'claspo_add_admin_menu' );
add_action( 'admin_post_claspo_save_script', 'claspo_save_script' );
add_action( 'admin_post_claspo_disconnect_script', 'claspo_disconnect_script' );
add_action( 'admin_init', 'claspo_check_script_id' );
add_action( 'admin_enqueue_scripts', 'claspo_enqueue_admin_scripts' );

function claspo_add_admin_menu() {
//    add_options_page( 'Claspo', 'Claspo', 'manage_options', 'claspo_script_plugin', 'claspo_options_page' );
    add_menu_page( 'Claspo', 'Claspo', 'manage_options', 'claspo_script_plugin', 'claspo_options_page', plugin_dir_url( __FILE__ ) . 'img/claspo_logo.png');
}

function claspo_check_script_id() {
    if ( isset( $_GET['script_id'] ) && ! empty( $_GET['script_id'] ) ) {
        $script_id = sanitize_text_field( wp_unslash($_GET['script_id']) );
        update_option( 'claspo_script_id', $script_id );

        $response = wp_remote_get( CLASPO_GET_SCRIPT_URL . $script_id);

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            set_transient( 'claspo_api_error', $error_message, 30 );
        } else {
            $body = wp_remote_retrieve_body( $response );

            if ( ! empty( $body ) ) {
                update_option( 'claspo_script_id', $script_id );
                update_option( 'claspo_script_code', $body );
                set_transient( 'claspo_success_message', true, 30 );
                delete_transient( 'claspo_api_error' );
            } else {
                set_transient( 'claspo_api_error', 'Invalid response from API', 30 );
            }
        }

        wp_safe_redirect( admin_url( 'admin.php?page=claspo_script_plugin' ) );
        exit;
    }
}

function claspo_enqueue_admin_scripts( $hook ) {
    if ( $hook != 'toplevel_page_claspo_script_plugin' ) {
        return;
    }

    wp_enqueue_style( 'claspo-admin-style', plugin_dir_url( __FILE__ ) . 'css/main.css' );
    wp_enqueue_script( 'claspo-admin-script', plugin_dir_url( __FILE__ ) . 'js/main2.js', array(), false, true );
}

function claspo_options_page() {
    $script_code     = get_option( 'claspo_script_code' );
    $error_message   = get_transient( 'claspo_api_error' );
    $success_message = get_transient( 'claspo_success_message' );

    if ( isset( $_GET['deactivation_feedback'] ) && $_GET['deactivation_feedback'] == 1 ) {
        include plugin_dir_path( __FILE__ ) . 'templates/feedback.php';
    } elseif ( $success_message && $script_code ) {
        include plugin_dir_path( __FILE__ ) . 'templates/success.php';
        delete_transient( 'claspo_success_message' );
    } /*elseif ( $error_message ) {
        include plugin_dir_path( __FILE__ ) . 'templates/error.php';
        delete_transient( 'claspo_api_error' );
    }*/ elseif ( ! $script_code || $error_message) {
        include plugin_dir_path( __FILE__ ) . 'templates/form.php';

        if ( $error_message ) {
            delete_transient( 'claspo_api_error' );
        }
    } else {
        include plugin_dir_path( __FILE__ ) . 'templates/main.php';
    }
}

function claspo_save_script() {
    /*if ( ! isset( $_POST['claspo_nonce'] ) || ! wp_verify_nonce( $_POST['claspo_nonce'], 'claspo_save_script' ) ) {
        return;
    }*/

    if ( ! isset( $_POST['claspo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['claspo_nonce'] ) ), 'claspo_save_script' ) ) {
        wp_die( 'Security check failed', 'Security Error', array( 'response' => 403 ) );
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'You do not have sufficient permissions to access this page.', 'Permission Error', array( 'response' => 403 ) );
    }

    if ( isset( $_POST['claspo_script_id'] ) ) {
        $script_id = sanitize_text_field( wp_unslash($_POST['claspo_script_id'] ));

        $response = wp_remote_get( CLASPO_GET_SCRIPT_URL . $script_id);

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            set_transient( 'claspo_api_error', $error_message, 30 );
        } else {
            $body = wp_remote_retrieve_body( $response );

            if ( ! empty( $body ) ) {
                update_option( 'claspo_script_id', $script_id );
                update_option( 'claspo_script_code', $body );
                set_transient( 'claspo_success_message', true, 30 );
                delete_transient( 'claspo_api_error' );
            } else {
                set_transient( 'claspo_api_error', 'Invalid response from API', 30 );
            }
        }
    }

    wp_redirect( admin_url( 'admin.php?page=claspo_script_plugin' ) );
    exit;
}

function claspo_disconnect_script() {
    if ( ! isset( $_POST['claspo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['claspo_nonce'] ) ), 'claspo_disconnect_script' ) ) {
        wp_die( 'Security check failed', 'Security Error', array( 'response' => 403 ) );
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'You do not have sufficient permissions to access this page.', 'Permission Error', array( 'response' => 403 ) );
    }

    delete_option( 'claspo_script_id' );
    delete_option( 'claspo_script_code' );

    wp_safe_redirect( admin_url( 'admin.php?page=claspo_script_plugin' ) );
    exit;
}

add_action( 'wp_footer', 'claspo_add_claspo_script' );

function claspo_add_claspo_script() {
    // Реєструємо пустий скрипт
    wp_register_script('claspo-script', false);
    wp_enqueue_script('claspo-script');

    // Отримуємо скрипт з бази даних
    $script_code = get_option( 'claspo_script_code' );

    // Видаляємо теги <script> з коду
    $script_code = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '$1', $script_code);

    // Додаємо скрипт без тегів <script>, якщо він існує
    if ( $script_code ) {
        wp_add_inline_script('claspo-script', $script_code);
    }
}

register_deactivation_hook( __FILE__, 'claspo_deactivation_feedback' );

function claspo_deactivation_feedback() {
    if ( current_user_can( 'manage_options' ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=claspo_script_plugin&deactivation_feedback=1' ) );
        exit;
    }
}

add_action( 'admin_post_claspo_send_feedback', 'claspo_send_feedback' );

function claspo_send_feedback() {
    if ( ! isset( $_POST['claspo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['claspo_nonce'] ) ), 'claspo_feedback_nonce' ) ) {
        wp_die( 'Security check failed', 'Security Error', array( 'response' => 403 ) );
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'You do not have sufficient permissions to access this page.', 'Permission Error', array( 'response' => 403 ) );
    }

    $script_id = get_option( 'claspo_script_id' );
    $feedback = isset( $_POST['feedback'] ) ? sanitize_textarea_field( wp_unslash($_POST['feedback']) ) : 'No feedback provided';

    $to = 'integrations.feedback@claspo.io';
    $subject = 'Feedback from WordPress plugin';
    $body = "Script ID: " . esc_html($script_id) . "\n\nFeedback:\n" . esc_html($feedback);

    wp_mail( $to, $subject, $body );

    deactivate_plugins( plugin_basename( __FILE__ ), true );
    wp_safe_redirect( admin_url( 'plugins.php?deactivated=true' ) );
    exit;
}

add_action( 'admin_init', 'claspo_register_settings' );
function claspo_register_settings() {
    register_setting( 'claspo_options_group', 'claspo_script_id' );
}
