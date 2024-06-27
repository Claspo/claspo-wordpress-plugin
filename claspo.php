<?php

/*
Plugin Name: Claspo
Description: Додає скрипт Claspo на всі сторінки сайту.
Version: 1.0
Author: Claspo
*/

//const GET_SCRIPT_URL = 'http://localhost:8000/';
const GET_SCRIPT_URL = 'https://script.claspo.io/site-script/v1/site/script/';

add_action( 'admin_menu', 'csp_add_admin_menu' );
add_action( 'admin_post_csp_save_script', 'csp_save_script' );
add_action( 'admin_post_csp_disconnect_script', 'csp_disconnect_script' );
add_action( 'admin_init', 'csp_check_script_id' );
add_action( 'admin_enqueue_scripts', 'csp_enqueue_admin_scripts' );

function csp_add_admin_menu() {
//	add_options_page( 'Claspo', 'Claspo', 'manage_options', 'claspo_script_plugin', 'csp_options_page' );
	add_menu_page( 'Claspo', 'Claspo', 'manage_options', 'claspo_script_plugin', 'csp_options_page', plugin_dir_url( __FILE__ ) . 'img/claspo_logo.png');
}

function csp_check_script_id() {
	if ( isset( $_GET['script_id'] ) && ! empty( $_GET['script_id'] ) ) {
		$script_id = sanitize_text_field( $_GET['script_id'] );
		update_option( 'csp_script_id', $script_id );

		$response = wp_remote_get( GET_SCRIPT_URL . $script_id);

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			set_transient( 'csp_api_error', $error_message, 30 );
		} else {
			$body = wp_remote_retrieve_body( $response );

			if ( ! empty( $body ) ) {
				update_option( 'csp_script_id', $script_id );
				update_option( 'csp_script_code', $body );
				set_transient( 'csp_success_message', true, 30 );
				delete_transient( 'csp_api_error' );
			} else {
				set_transient( 'csp_api_error', 'Invalid response from API', 30 );
			}
		}

		wp_redirect( admin_url( 'admin.php?page=claspo_script_plugin' ) );
		exit;
	}
}

function csp_enqueue_admin_scripts( $hook ) {
	if ( $hook != 'toplevel_page_claspo_script_plugin' ) {
		return;
	}

	wp_enqueue_style( 'csp-main-css', plugin_dir_url( __FILE__ ) . 'css/main.css' );
	wp_enqueue_script( 'csp-main-js', plugin_dir_url( __FILE__ ) . 'js/main2.js', array(), false, true );
}

function csp_options_page() {
	$script_code     = get_option( 'csp_script_code' );
	$error_message   = get_transient( 'csp_api_error' );
	$success_message = get_transient( 'csp_success_message' );

	if ( isset( $_GET['deactivation_feedback'] ) && $_GET['deactivation_feedback'] == 1 ) {
		include plugin_dir_path( __FILE__ ) . 'templates/feedback.php';
	} elseif ( $success_message && $script_code ) {
		include plugin_dir_path( __FILE__ ) . 'templates/success.php';
		delete_transient( 'csp_success_message' );
	} /*elseif ( $error_message ) {
		include plugin_dir_path( __FILE__ ) . 'templates/error.php';
		delete_transient( 'csp_api_error' );
	}*/ elseif ( ! $script_code || $error_message) {
		include plugin_dir_path( __FILE__ ) . 'templates/form.php';

		if ( $error_message ) {
			delete_transient( 'csp_api_error' );
		}
	} else {
		include plugin_dir_path( __FILE__ ) . 'templates/main.php';
	}
}

function csp_save_script() {
	if ( ! isset( $_POST['csp_nonce'] ) || ! wp_verify_nonce( $_POST['csp_nonce'], 'csp_save_script' ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['csp_script_id'] ) ) {
		$script_id = sanitize_text_field( $_POST['csp_script_id'] );

		$response = wp_remote_get( GET_SCRIPT_URL . $script_id);

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			set_transient( 'csp_api_error', $error_message, 30 );
		} else {
			$body = wp_remote_retrieve_body( $response );

			if ( ! empty( $body ) ) {
				update_option( 'csp_script_id', $script_id );
				update_option( 'csp_script_code', $body );
				set_transient( 'csp_success_message', true, 30 );
				delete_transient( 'csp_api_error' );
			} else {
				set_transient( 'csp_api_error', 'Invalid response from API', 30 );
			}
		}
	}

	wp_redirect( admin_url( 'admin.php?page=claspo_script_plugin' ) );
	exit;
}

function csp_disconnect_script() {
	if ( ! isset( $_POST['csp_nonce'] ) || ! wp_verify_nonce( $_POST['csp_nonce'], 'csp_disconnect_script' ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	delete_option( 'csp_script_id' );
	delete_option( 'csp_script_code' );

	wp_redirect( admin_url( 'admin.php?page=claspo_script_plugin' ) );
	exit;
}

add_action( 'wp_footer', 'csp_add_claspo_script' );
function csp_add_claspo_script() {
	$script_code = get_option( 'csp_script_code' );

	if ( $script_code ) {
		echo "<script>{$script_code}</script>";
	}
}

register_deactivation_hook( __FILE__, 'csp_deactivation_feedback' );

function csp_deactivation_feedback() {
	if ( current_user_can( 'manage_options' ) ) {
		wp_redirect( admin_url( 'admin.php?page=claspo_script_plugin&deactivation_feedback=1' ) );
		exit;
	}
}

add_action( 'admin_post_csp_send_feedback', 'csp_send_feedback' );

function csp_send_feedback() {
	if ( ! isset( $_POST['csp_nonce'] ) || ! wp_verify_nonce( $_POST['csp_nonce'], 'csp_feedback_nonce' ) ) {
		wp_die( 'Security check failed' );
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have sufficient permissions to access this page.' );
	}

	$script_id = get_option( 'csp_script_id' );
	$feedback = isset( $_POST['feedback'] ) ? sanitize_textarea_field( $_POST['feedback'] ) : 'No feedback provided';

	$to = 'integrations.feedback@claspo.io';
	$subject = 'Feedback from WordPress plugin';
	$body = "Script ID: {$script_id}\n\nFeedback:\n{$feedback}";

	wp_mail( $to, $subject, $body );

	deactivate_plugins( plugin_basename( __FILE__ ), true );
	wp_redirect( admin_url( 'plugins.php?deactivated=true' ) );
	exit;
}

add_action( 'admin_init', 'csp_register_settings' );
function csp_register_settings() {
	register_setting( 'csp_options_group', 'csp_script_id' );
}
