<?php
if (!defined('ABSPATH')) exit;

// Manage bookings -- allow admin panel to always override!
add_action('wp_ajax_jcubhub_manage_booking', 'jcubhub_manage_booking');
function jcubhub_manage_booking() {
    check_ajax_referer('jcubhub-nonce', 'nonce');

    global $wpdb;
    $id = intval($_POST['id']);
    $status = sanitize_text_field($_POST['status']);
    $table = $wpdb->prefix . "jcubhub_bookings";
    $user = isset($_SESSION['jcubhub_admin']) ? $_SESSION['jcubhub_admin'] : 'admin';

    if ($status === 'approved') {
        $wpdb->update($table, ['status' => $status, 'approved_by' => $user], ['id' => $id]);
    } elseif ($status === 'rejected') {
        $wpdb->update($table, ['status' => $status], ['id' => $id]);
    } elseif ($status === 'deleted') {
        $wpdb->delete($table, ['id' => $id]);
    }
    wp_send_json_success();
    wp_die();
}

// Add/Remove unavailable dates
add_action('wp_ajax_jcubhub_unavail_add', function() {
    check_ajax_referer('jcubhub-nonce', 'nonce');
    if (!isset($_SESSION['jcubhub_admin'])) wp_send_json_error('Unauthorized');
    global $wpdb;
    $dates = explode(',', sanitize_text_field($_POST['dates']));
    $table = $wpdb->prefix . "jcubhub_unavailable";
    foreach ($dates as $date) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $wpdb->insert($table, ['date' => $date], ['%s']);
        }
    }
    wp_send_json_success();
    wp_die();
});

add_action('wp_ajax_jcubhub_unavail_remove', function() {
    check_ajax_referer('jcubhub-nonce', 'nonce');
    if (!isset($_SESSION['jcubhub_admin'])) wp_send_json_error('Unauthorized');
    global $wpdb;
    $date = sanitize_text_field($_POST['date']);
    $table = $wpdb->prefix . "jcubhub_unavailable";
    $wpdb->delete($table, ['date' => $date]);
    wp_send_json_success();
    wp_die();
});

// Optional future-proof: Booking via AJAX (uses same protections)
add_action('wp_ajax_nopriv_jcubhub_ajax_booking', 'jcubhub_ajax_booking_handler');
add_action('wp_ajax_jcubhub_ajax_booking', 'jcubhub_ajax_booking_handler');
function jcubhub_ajax_booking_handler() {
    global $wpdb;

    // Honeypot field check
    if (!empty($_POST['company'])) {
        wp_send_json_error("Bot detected.");
        return;
    }

    $name   = sanitize_text_field($_POST['name']);
    $email  = sanitize_email($_POST['email']);
    $reason = sanitize_textarea_field($_POST['reason']);
    $dates  = sanitize_text_field($_POST['dates']);
    $reminder_optin = isset($_POST['reminder_optin']) ? 1 : 0;

    // Email allow-list check
    $allowed_domains = ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'icloud.com', 'aol.com', 'protonmail.com', 'pm.me', 'live.com', 'me.com'];
    $email_domain = strtolower(substr(strrchr($email, "@"), 1));
    if (!in_array($email_domain, $allowed_domains)) {
        wp_send_json_error("Email domain not allowed.");
        return;
    }

    if (empty($name) || empty($email) || empty($dates)) {
        wp_send_json_error("Missing required fields.");
        return;
    }

    $table_name = $wpdb->prefix . "jcubhub_bookings";
    $wpdb->insert($table_name, [
        'name' => $name,
        'email' => $email,
        'reason' => $reason,
        'dates' => $dates,
        'status' => 'pending',
        'created_at' => current_time('mysql'),
        'approved_by' => '',
        'reminder_optin' => $reminder_optin
    ]);

    wp_send_json_success("Booking submitted.");
    wp_die();
}
