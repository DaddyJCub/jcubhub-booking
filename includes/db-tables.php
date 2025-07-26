<?php
if (!defined('ABSPATH')) exit;

register_activation_hook(plugin_dir_path(__DIR__) . 'jcubhub-booking.php', function() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table = $wpdb->prefix . "jcubhub_unavailable";
    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL UNIQUE
    ) $charset_collate;";
    require_once(ABSPATH . "wp-admin/includes/upgrade.php");
    dbDelta($sql);

    $booking_table = $wpdb->prefix . "jcubhub_bookings";
    $sql2 = "CREATE TABLE IF NOT EXISTS $booking_table (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        reason TEXT,
        dates TEXT NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        approved_by VARCHAR(100) DEFAULT '',
        reminder_optin TINYINT(1) DEFAULT 0
    ) $charset_collate;";
    dbDelta($sql2);
});
