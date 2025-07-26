<?php
if (!defined('ABSPATH')) exit;

// SCHEDULE: Send reminders on plugin activation/init
add_action('init', 'jcubhub_schedule_reminder_event');
function jcubhub_schedule_reminder_event() {
    if (!wp_next_scheduled('jcubhub_send_reminders_event')) {
        wp_schedule_event(time() + 60, 'hourly', 'jcubhub_send_reminders_event');
    }
}
add_action('jcubhub_send_reminders_event', 'jcubhub_send_reminders_cron');

// PERIODIC ADMIN REMINDER FOR PENDING BOOKINGS (NEW)
add_action('init', 'jcubhub_schedule_pending_reminder_event');
function jcubhub_schedule_pending_reminder_event() {
    $freq = (int) get_option('jcubhub_pending_reminder_freq', 24); // default every 24 hours
    $freq = ($freq < 1) ? 24 : $freq;
    if (!wp_next_scheduled('jcubhub_pending_reminder_event')) {
        wp_schedule_event(time() + 120, "jcubhub_pending_reminder_$freq", 'jcubhub_pending_reminder_event');
    }
}
add_action('jcubhub_pending_reminder_event', 'jcubhub_send_pending_reminders_cron');

// Register custom interval
add_filter('cron_schedules', function($schedules) {
    $freq = (int) get_option('jcubhub_pending_reminder_freq', 24);
    $freq = ($freq < 1) ? 24 : $freq;
    $schedules["jcubhub_pending_reminder_$freq"] = [
        'interval' => $freq * 3600,
        'display'  => "Every $freq hours for pending booking reminders"
    ];
    return $schedules;
});

// --- MAIN GUEST/ADMIN REMINDERS (NO CHANGE) ---
function jcubhub_send_reminders_cron() {
    global $wpdb;
    $now = current_time('timestamp');
    $settings = jcubhub_get_reminder_settings();
    $admin_emails = jcubhub_get_admin_emails();
    $lead_seconds = $settings['admin_reminder_hours'] * 3600;
    $table = $wpdb->prefix . "jcubhub_bookings";
    $rows = $wpdb->get_results("SELECT * FROM $table WHERE status IN ('pending', 'approved')");

    foreach ($rows as $row) {
        $dates = array_filter(array_map('trim', explode(',', $row->dates)));
        if (empty($dates)) continue;
        $first_date = min($dates);

        $flag_key = "jcubhub_reminder_sent_" . $row->id;
        if (get_option($flag_key)) continue;

        $booking_ts = strtotime($first_date . ' 00:00:00');
        $delta = $booking_ts - $now;

        // Guest reminder
        if (!empty($row->reminder_optin) && $delta > 0 && $delta <= (48 * 3600)) {
            $subject = "Your Booking Reminder (JCubHub)";
            $msg = "Hello " . esc_html($row->name) . ",\n\n"
                 . "This is a reminder for your upcoming booking starting on $first_date.\n\n"
                 . "If you have any questions, please reply to this email.\n\n"
                 . "JCubHub";
            wp_mail($row->email, $subject, $msg);
            update_option($flag_key, 1);
        }

        // Admin reminders
        if ($settings['admin_reminder_enabled'] && $delta > 0 && $delta <= $lead_seconds) {
            $subject = "Booking Reminder (JCubHub): " . esc_html($row->name) . " ($first_date)";
            $approve_url = add_query_arg([
                'jcubhub_action' => 'approve',
                'booking_id' => $row->id,
                'jcubhub_nonce' => wp_create_nonce('jcubhub_action_' . $row->id)
            ], site_url('/'));

            $reject_url = add_query_arg([
                'jcubhub_action' => 'reject',
                'booking_id' => $row->id,
                'jcubhub_nonce' => wp_create_nonce('jcubhub_action_' . $row->id)
            ], site_url('/'));

            $html = '<div style="font-family: Arial,sans-serif; max-width:520px; margin:0 auto; background:#f8f8f8; border-radius:12px; box-shadow:0 4px 16px rgba(0,0,0,0.09); padding:32px 30px 24px 30px;">';
            $html .= "<h2 style='color:#007bff; margin-top:0; font-size:1.4em;'>New Booking Needs Approval</h2>";
            $html .= "<p><strong>Name:</strong> " . esc_html($row->name) . "<br>";
            $html .= "<strong>Email:</strong> " . esc_html($row->email) . "<br>";
            $html .= "<strong>Dates:</strong> " . esc_html($row->dates) . "<br>";
            $html .= "<strong>Reason:</strong> " . esc_html($row->reason) . "</p>";
            $html .= "<div style='display:flex; gap:18px; margin:26px 0 8px 0;'>";
            $html .= "<a href='$approve_url' style='background:#2ecc40; color:#fff; font-weight:600; text-decoration:none; padding:12px 28px; border-radius:9px; font-size:1.08em; display:inline-block;'>Approve</a>";
            $html .= "<a href='$reject_url' style='background:#ff5757; color:#fff; font-weight:600; text-decoration:none; padding:12px 28px; border-radius:9px; font-size:1.08em; display:inline-block;'>Reject</a>";
            $html .= "</div>";
            $html .= "<div style='margin-top:30px; font-size:0.96em; color:#888;'>You can also manage bookings in the <a href='" . site_url('/admin-panel/') . "' style='color:#007bff;'>JCubHub Admin Panel</a>.</div>";
            $html .= '</div>';

            foreach ($admin_emails as $admin_email) {
                if (!empty($settings['notify_' . $admin_email])) {
                    wp_mail($admin_email, $subject, $html, [
                        'Content-Type: text/html; charset=UTF-8'
                    ]);
                }
            }
            update_option($flag_key, 1);
        }
    }
}

// SEND PENDING BOOKINGS REMINDER EMAIL TO ADMINS (NEW)
function jcubhub_send_pending_reminders_cron() {
    global $wpdb;
    $settings = jcubhub_get_reminder_settings();
    $admin_emails = jcubhub_get_admin_emails();
    $table = $wpdb->prefix . "jcubhub_bookings";
    $pending = $wpdb->get_results("SELECT * FROM $table WHERE status = 'pending' ORDER BY created_at ASC");
    if (!$pending) return;

    $subject = "Pending Bookings Reminder (JCubHub)";
    $html = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#f9f9f9;border-radius:13px;box-shadow:0 6px 22px rgba(0,0,0,0.07);padding:38px 34px 24px 34px;">';
    $html .= "<h2 style='color:#0880ff; font-size:1.32em;margin-top:0;margin-bottom:18px;'>Pending Bookings – Action Needed</h2>";
    $html .= "<div style='font-size:1.05em;color:#222;margin-bottom:26px;'>Below are all bookings still pending. Click <b>Approve</b> or <b>Reject</b> for each.<br><br>";

    foreach ($pending as $row) {
        $approve_url = add_query_arg([
            'jcubhub_action' => 'approve',
            'booking_id' => $row->id,
            'jcubhub_nonce' => wp_create_nonce('jcubhub_action_' . $row->id)
        ], site_url('/'));

        $reject_url = add_query_arg([
            'jcubhub_action' => 'reject',
            'booking_id' => $row->id,
            'jcubhub_nonce' => wp_create_nonce('jcubhub_action_' . $row->id)
        ], site_url('/'));

        $html .= "<div style='background:#fff;border-radius:9px;padding:16px 20px 10px 20px;margin-bottom:18px;box-shadow:0 2px 12px rgba(0,0,0,0.05);'>";
        $html .= "<strong>Name:</strong> " . esc_html($row->name) . "<br>";
        $html .= "<strong>Email:</strong> " . esc_html($row->email) . "<br>";
        $html .= "<strong>Dates:</strong> " . esc_html($row->dates) . "<br>";
        $html .= "<strong>Reason:</strong> " . esc_html($row->reason) . "<br>";
        $html .= "<div style='display:flex;gap:18px;margin:14px 0 8px 0;'>";
        $html .= "<a href='$approve_url' style='background:#2ecc40;color:#fff;font-weight:600;text-decoration:none;padding:10px 20px;border-radius:8px;font-size:1em;display:inline-block;'>Approve</a>";
        $html .= "<a href='$reject_url' style='background:#ff5757;color:#fff;font-weight:600;text-decoration:none;padding:10px 20px;border-radius:8px;font-size:1em;display:inline-block;'>Reject</a>";
        $html .= "</div>";
        $html .= "</div>";
    }

    $html .= "<div style='margin-top:32px;font-size:0.97em;color:#777;'>You can also manage all bookings in the <a href='" . site_url('/admin-panel/') . "' style='color:#007bff;'>JCubHub Admin Panel</a>.</div>";
    $html .= '</div></div>';

    foreach ($admin_emails as $admin_email) {
        if (!empty($settings['notify_' . $admin_email])) {
            wp_mail($admin_email, $subject, $html, [
                'Content-Type: text/html; charset=UTF-8'
            ]);
        }
    }
}

// Handle Approve/Reject from email link (works for all cases, block double-action)
add_action('template_redirect', function() {
    if (!isset($_GET['jcubhub_action']) || !isset($_GET['booking_id']) || !isset($_GET['jcubhub_nonce'])) return;
    $action = sanitize_text_field($_GET['jcubhub_action']);
    $id = intval($_GET['booking_id']);
    $nonce = $_GET['jcubhub_nonce'];
    if (!in_array($action, ['approve', 'reject'])) return;
    if (!wp_verify_nonce($nonce, 'jcubhub_action_' . $id)) {
        wp_die('Invalid or expired action link.');
    }

    global $wpdb;
    $table = $wpdb->prefix . "jcubhub_bookings";
    $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $id));
    if (!$booking) {
        wp_die('Booking not found.');
    }

    if ($booking->status === 'approved') {
        $msg = "No action taken; booking is already <strong>approved</strong>.";
    } elseif ($booking->status === 'rejected') {
        $msg = "No action taken; booking is already <strong>rejected</strong>.";
    } elseif ($action === 'approve') {
        $wpdb->update($table, ['status' => 'approved', 'approved_by' => 'admin-email'], ['id' => $id]);
        $msg = "Booking for <strong>" . esc_html($booking->name) . "</strong> approved!";
    } elseif ($action === 'reject') {
        $wpdb->update($table, ['status' => 'rejected'], ['id' => $id]);
        $msg = "Booking for <strong>" . esc_html($booking->name) . "</strong> rejected.";
    } else {
        $msg = "No valid action taken.";
    }

    // Styled confirmation
    get_header();
    echo "<div style='max-width:520px;margin:30px auto;background:#fff;padding:32px 28px 26px 28px;border-radius:16px;box-shadow:0 4px 18px rgba(0,0,0,0.11);font-size:1.2em;text-align:center;'>";
    echo "<h2>JCubHub Booking Action</h2>";
    echo "<p>$msg</p>";
    echo "<a href='" . esc_url(site_url('/admin-panel/')) . "' style='color:#0880ff;font-weight:600;font-size:1.1em;'>Go to Admin Panel</a>";
    echo "</div>";
    get_footer();
    exit;
});
?>
