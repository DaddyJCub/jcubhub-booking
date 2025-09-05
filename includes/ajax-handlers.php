<?php
if (!defined('ABSPATH')) exit;

// IMPORTANT: Add both wp_ajax and wp_ajax_nopriv for ALL actions
// This ensures they work whether WordPress considers user "logged in" or not

// Manage bookings - works for custom admin sessions
add_action('wp_ajax_jcubhub_manage_booking', 'jcubhub_manage_booking');
add_action('wp_ajax_nopriv_jcubhub_manage_booking', 'jcubhub_manage_booking');

function jcubhub_manage_booking() {
    // Check the nonce first
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'jcubhub-nonce')) {
        wp_send_json_error('Security check failed. Please refresh the page and try again.');
        wp_die();
    }
    
    // Start session if not started
    if (!session_id()) {
        session_start();
    }
    
    // Check if admin is logged in via our custom session
    if (!isset($_SESSION['jcubhub_admin'])) {
        wp_send_json_error('You must be logged in as admin to perform this action.');
        wp_die();
    }
    
    global $wpdb;
    $id = intval($_POST['id']);
    $status = sanitize_text_field($_POST['status']);
    $table = $wpdb->prefix . "jcubhub_bookings";
    $user = $_SESSION['jcubhub_admin'];

    // Perform the action based on status
    if ($status === 'approved') {
        $result = $wpdb->update(
            $table, 
            ['status' => 'approved', 'approved_by' => $user], 
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );
    } elseif ($status === 'rejected') {
        $result = $wpdb->update(
            $table, 
            ['status' => 'rejected'], 
            ['id' => $id],
            ['%s'],
            ['%d']
        );
    } elseif ($status === 'deleted') {
        $result = $wpdb->delete($table, ['id' => $id], ['%d']);
    } else {
        wp_send_json_error('Invalid action specified.');
        wp_die();
    }
    
    // Check if the database operation was successful
    if ($result === false) {
        wp_send_json_error('Database error. Please try again.');
    } else {
        wp_send_json_success(['message' => 'Booking ' . $status . ' successfully']);
    }
    
    wp_die();
}

// Add unavailable dates
add_action('wp_ajax_jcubhub_unavail_add', 'jcubhub_unavail_add');
add_action('wp_ajax_nopriv_jcubhub_unavail_add', 'jcubhub_unavail_add');

function jcubhub_unavail_add() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'jcubhub-nonce')) {
        wp_send_json_error('Security check failed. Please refresh the page and try again.');
        wp_die();
    }
    
    // Start session if needed
    if (!session_id()) {
        session_start();
    }
    
    // Check admin session
    if (!isset($_SESSION['jcubhub_admin'])) {
        wp_send_json_error('You must be logged in as admin to perform this action.');
        wp_die();
    }
    
    global $wpdb;
    $dates = explode(',', sanitize_text_field($_POST['dates']));
    $table = $wpdb->prefix . "jcubhub_unavailable";
    $added_count = 0;
    $already_exists = 0;
    
    foreach ($dates as $date) {
        $date = trim($date);
        // Validate date format
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            // Check if date already exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE date = %s", 
                $date
            ));
            
            if ($exists) {
                $already_exists++;
            } else {
                $result = $wpdb->insert(
                    $table, 
                    ['date' => $date], 
                    ['%s']
                );
                if ($result) {
                    $added_count++;
                }
            }
        }
    }
    
    $message = "Added $added_count unavailable date(s).";
    if ($already_exists > 0) {
        $message .= " $already_exists date(s) were already marked unavailable.";
    }
    
    wp_send_json_success(['message' => $message, 'added' => $added_count]);
    wp_die();
}

// Remove unavailable dates
add_action('wp_ajax_jcubhub_unavail_remove', 'jcubhub_unavail_remove');
add_action('wp_ajax_nopriv_jcubhub_unavail_remove', 'jcubhub_unavail_remove');

function jcubhub_unavail_remove() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'jcubhub-nonce')) {
        wp_send_json_error('Security check failed. Please refresh the page and try again.');
        wp_die();
    }
    
    // Start session if needed
    if (!session_id()) {
        session_start();
    }
    
    // Check admin session
    if (!isset($_SESSION['jcubhub_admin'])) {
        wp_send_json_error('You must be logged in as admin to perform this action.');
        wp_die();
    }
    
    global $wpdb;
    $date = sanitize_text_field($_POST['date']);
    $table = $wpdb->prefix . "jcubhub_unavailable";
    
    // Delete the date
    $result = $wpdb->delete(
        $table, 
        ['date' => $date],
        ['%s']
    );
    
    if ($result === false) {
        wp_send_json_error('Database error. Could not remove date.');
    } elseif ($result === 0) {
        wp_send_json_error('Date was not found in unavailable list.');
    } else {
        wp_send_json_success(['message' => 'Date removed from unavailable list.']);
    }
    
    wp_die();
}