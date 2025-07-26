<?php
if (!defined('ABSPATH')) exit;

// Manage bookings -- allow admin panel to always override!
add_action('wp_ajax_jcubhub_manage_booking', 'jcubhub_manage_booking');
function jcubhub_manage_booking() {
    global $wpdb;
    $id = intval($_POST['id']);
    $status = sanitize_text_field($_POST['status']);
    $table = $wpdb->prefix . "jcubhub_bookings";
    $user = isset($_SESSION['jcubhub_admin']) ? $_SESSION['jcubhub_admin'] : 'admin';

    // Admin panel always overwrites!
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
    if (!isset($_SESSION['jcubhub_admin'])) wp_send_json_error('Unauthorized');
    global $wpdb;
    $date = sanitize_text_field($_POST['date']);
    $table = $wpdb->prefix . "jcubhub_unavailable";
    $wpdb->delete($table, ['date' => $date]);
    wp_send_json_success();
    wp_die();
});
