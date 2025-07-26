<?php
/*
Plugin Name: JCubHub Booking
Description: Modern booking system with styled calendar, multi-date selection, admin panel, unavailable date management, and reminders.
Version: 1.0
Author: Jacob Zillmer
*/

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'includes/db-schema-check.php';
add_action('plugins_loaded', 'jcubhub_migrate_database_schema');

// Loader for all components
require_once plugin_dir_path(__FILE__) . 'includes/helpers.php';
require_once plugin_dir_path(__FILE__) . 'includes/db-tables.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax-handlers.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-auth.php';
require_once plugin_dir_path(__FILE__) . 'includes/reminders.php';
require_once plugin_dir_path(__FILE__) . 'includes/booking-form.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-panel.php';

// Enqueue styles/scripts
function jcubhub_enqueue_scripts() {
    wp_enqueue_script('jquery');
    wp_enqueue_script('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js', [], null, true);
    wp_enqueue_style('fullcalendar-css', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css');
    wp_enqueue_style('jcubhub-style', plugins_url('/assets/jcubhub-booking.css', __FILE__));
    wp_localize_script('jquery', 'jcubhub_ajax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('jcubhub-nonce')
    ]);
}
add_action('wp_enqueue_scripts', 'jcubhub_enqueue_scripts');
