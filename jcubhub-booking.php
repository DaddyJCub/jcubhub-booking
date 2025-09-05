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

// Enqueue styles/scripts (frontend)
// Enqueue styles/scripts (frontend)
function jcubhub_enqueue_scripts() {
    // Always enqueue jQuery first
    wp_enqueue_script('jquery');
    
    // Enqueue FullCalendar with jQuery dependency
    wp_enqueue_script('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js', array('jquery'), '6.1.8', true);
    wp_enqueue_style('fullcalendar-css', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css', array(), '6.1.8');
    
    // Enqueue plugin styles
    wp_enqueue_style('jcubhub-style', plugins_url('/assets/jcubhub-booking.css', __FILE__), array(), '1.0');

    // Localize script with AJAX data
    wp_localize_script('jquery', 'jcubhub_ajax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('jcubhub-nonce')
    ));
}
add_action('wp_enqueue_scripts', 'jcubhub_enqueue_scripts');

// Enqueue scripts for admin panel AJAX support
add_action('admin_enqueue_scripts', 'jcubhub_admin_ajax_vars');
function jcubhub_admin_ajax_vars() {
    wp_enqueue_script('jquery');
    wp_localize_script('jquery', 'jcubhub_ajax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('jcubhub-nonce')
    ]);
}

// Register [jcubhub_user_panel] shortcode
add_shortcode('jcubhub_user_panel', 'jcubhub_user_panel_shortcode');
function jcubhub_user_panel_shortcode() {
    if (!isset($_GET['jcubhub_user_token'])) return 'Invalid access.';

    global $wpdb;
    $token = sanitize_text_field($_GET['jcubhub_user_token']);
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}jcubhub_bookings WHERE token = %s", $token
    ));

    if (!$row || (!empty($row->token_expires) && strtotime($row->token_expires) < time())) {
        return 'This link is invalid or expired.';
    }

    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}jcubhub_bookings WHERE email = %s ORDER BY created_at DESC", $row->email
    ));

    ob_start();
    ?>
    <div class="admin-panel-mainbox">
        <div class="admin-panel-header">
            <h2>Your JCubHub Bookings</h2>
            <span style="font-size:0.95em;color:#555;">Logged in as: <strong><?php echo esc_html($row->email); ?></strong></span>
        </div>

        <?php if (isset($_GET['cancelled'])): ?>
            <div class="success-message">Booking cancelled successfully.</div>
        <?php endif; ?>

        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Date(s)</th>
                        <th>Status</th>
                        <th>Reason</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b): ?>
                        <tr>
                            <td><?php echo esc_html($b->dates); ?></td>
                            <td><span class="status-badge <?php echo esc_attr(strtolower($b->status)); ?>"><?php echo esc_html(ucfirst($b->status)); ?></span></td>
                            <td><?php echo esc_html($b->reason ?: '—'); ?></td>
                            <td>
                                <?php if ($b->status !== 'cancelled'): ?>
                                    <form method="POST" onsubmit="return confirm('Cancel this booking?');">
                                        <input type="hidden" name="booking_id" value="<?php echo esc_attr($b->id); ?>">
                                        <input type="hidden" name="cancel_token" value="<?php echo esc_attr($token); ?>">
                                        <button type="submit" name="jcubhub_cancel_booking" class="delete-btn">Cancel</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color:#999;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($bookings)): ?>
                        <tr><td colspan="4" style="text-align:center; padding:24px;">No bookings found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    return ob_get_clean();
}



// Handle booking cancellation by token
add_action('init', function () {
    if (isset($_POST['jcubhub_cancel_booking'])) {
        global $wpdb;
        $id = intval($_POST['booking_id']);
        $token = sanitize_text_field($_POST['cancel_token']);

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}jcubhub_bookings WHERE id = %d AND token = %s", $id, $token
        ));

        if ($row) {
            $wpdb->update(
                "{$wpdb->prefix}jcubhub_bookings",
                ['status' => 'cancelled'],
                ['id' => $id]
            );
            wp_redirect(add_query_arg('cancelled', '1', wp_get_referer()));
            exit;
        }
    }
});
