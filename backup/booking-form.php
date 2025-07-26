<?php
if (!defined('ABSPATH')) exit;

// Form processing (public and admin)
add_action('admin_post_nopriv_jcubhub_booking', 'jcubhub_handle_booking_submission');
add_action('admin_post_jcubhub_booking', 'jcubhub_handle_booking_submission');

// --- Booking form shortcode ---
function jcubhub_booking_form() {
    ob_start();
    jcubhub_handle_admin_login();
    jcubhub_handle_admin_logout();

    global $wpdb;
    $bookings = $wpdb->get_results("SELECT dates, status FROM {$wpdb->prefix}jcubhub_bookings");
    $unavail = $wpdb->get_col("SELECT date FROM {$wpdb->prefix}jcubhub_unavailable");
    $booked = []; $pending = [];
    foreach ($bookings as $b) {
        $arr = array_map('trim', explode(',', $b->dates));
        foreach ($arr as $d) {
            if ($b->status == 'approved') $booked[] = $d;
            if ($b->status == 'pending') $pending[] = $d;
        }
    }
    ?>
    <div class="booking-container">
        <?php if (isset($_GET['success'])): ?>
            <div class="success-message" id="jcubhub-success-msg">
                Booking received successfully!
                <button class="dismiss-alert" onclick="this.parentElement.style.display='none';" aria-label="Dismiss">&times;</button>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="error-message" id="jcubhub-error-msg">
                <?php if ($_GET['error'] == '1'): ?>Please select at least one date before booking.
                <?php elseif ($_GET['error'] == '2'): ?>Selected dates are unavailable.<?php endif; ?>
                <button class="dismiss-alert" onclick="this.parentElement.style.display='none';" aria-label="Dismiss">&times;</button>
            </div>
        <?php endif; ?>
        <form id="jcubhub-booking-form" method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('jcubhub_booking', 'jcubhub_nonce'); ?>
            <input type="hidden" name="action" value="jcubhub_booking">
            <input type="hidden" name="dates" id="selected-dates">
            <label for="name">Name:</label>
            <input type="text" name="name" required aria-label="Name">
            <label for="email">Email:</label>
            <input type="email" name="email" required aria-label="Email">
            <label for="reason">Reason for Stay (optional):</label>
            <textarea name="reason" aria-label="Reason for Stay (optional)"></textarea>
            <div class="jcubhub-reminder-wrap">
                <input type="checkbox" name="reminder_optin" id="reminder_optin" value="1">
                <label class="jcubhub-reminder-label" for="reminder_optin">Remind me by email before my booking</label>
            </div>
            <button type="submit">Book Now</button>
        </form>
        <div class="calendar-column">
            <div id="calendar"></div>
            <div style="display:flex;gap:8px;align-items:center;margin:12px 0;">
                <button id="today-btn" type="button" style="background:#2ecc40;color:#fff;border:none;padding:7px 16px;border-radius:7px;font-weight:600;cursor:pointer;">Today</button>
                <button id="clear-dates-btn" type="button" style="background:#ffc400;color:#222;border:none;padding:7px 16px;border-radius:7px;font-weight:600;cursor:pointer;">Clear Selection</button>
            </div>
            <div class="jcubhub-calendar-legend">
                <span><span class="legend-box legend-available"></span> Available</span>
                <span><span class="legend-box legend-pending"></span> Pending</span>
                <span><span class="legend-box legend-booked"></span> Booked</span>
                <span><span class="legend-box legend-unavail"></span> Unavailable</span>
                <span><span class="legend-box legend-selected"></span> Selected</span>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var selectedDates = [];
        var calendarEl = document.getElementById('calendar');
        var unavailable = <?php echo json_encode(array_values(array_unique($unavail))); ?>;
        var booked = <?php echo json_encode(array_values(array_unique($booked))); ?>;
        var pending = <?php echo json_encode(array_values(array_unique($pending))); ?>;
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            selectable: true,
            height: 'auto',
            headerToolbar: {
                left: '',
                center: 'title',
                right: 'prev,next'
            },
            dateClick: function(info) {
                var date = info.dateStr;
                if (unavailable.includes(date) || booked.includes(date) || pending.includes(date)) return;
                if (selectedDates.includes(date)) {
                    selectedDates = selectedDates.filter(d => d !== date);
                    info.dayEl.classList.remove('selected-date');
                } else {
                    selectedDates.push(date);
                    info.dayEl.classList.add('selected-date');
                }
                document.getElementById('selected-dates').value = selectedDates.join(', ');
            },
            dayCellDidMount: function(arg) {
                var date = arg.date.toISOString().slice(0,10);
                if (unavailable.includes(date)) {
                    arg.el.style.background = '#b0b0b0';
                    arg.el.style.color = '#fff';
                    arg.el.title = "Unavailable";
                } else if (booked.includes(date)) {
                    arg.el.style.background = '#2ecc40';
                    arg.el.style.color = 'white';
                    arg.el.title = "Booked";
                } else if (pending.includes(date)) {
                    arg.el.style.background = '#ffc400';
                    arg.el.style.color = '#111';
                    arg.el.title = "Pending";
                }
            }
        });
        calendar.render();
        // "Today" button
        document.getElementById('today-btn').onclick = function() {
            calendar.today();
        };
        // "Clear Selection" button
        document.getElementById('clear-dates-btn').onclick = function() {
            selectedDates = [];
            document.getElementById('selected-dates').value = '';
            calendarEl.querySelectorAll('.selected-date').forEach(el => el.classList.remove('selected-date'));
        };
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('jcubhub_booking', 'jcubhub_booking_form');

// --- Booking form handler with immediate email notifications ---
function jcubhub_handle_booking_submission() {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['jcubhub_nonce']) && wp_verify_nonce($_POST['jcubhub_nonce'], 'jcubhub_booking')) {
        global $wpdb;

        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $reason = sanitize_textarea_field($_POST['reason']);
        $dates = sanitize_text_field($_POST['dates']);
        $reminder_optin = isset($_POST['reminder_optin']) ? 1 : 0;
        $status = 'pending';

        if (empty($dates)) {
            wp_redirect(add_query_arg(['error' => '1'], wp_get_referer()));
            exit;
        }
        $dateArr = array_map('trim', explode(',', $dates));
        $table_unavail = $wpdb->prefix . "jcubhub_unavailable";
        foreach ($dateArr as $d) {
            if ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_unavail WHERE date=%s", $d))) {
                wp_redirect(add_query_arg(['error' => '2'], wp_get_referer()));
                exit;
            }
        }

        $table_name = $wpdb->prefix . "jcubhub_bookings";
        $wpdb->insert($table_name, [
            'name' => $name,
            'email' => $email,
            'reason' => $reason,
            'dates' => $dates,
            'status' => $status,
            'created_at' => current_time('mysql'),
            'approved_by' => '',
            'reminder_optin' => $reminder_optin
        ]);

        // *** CRITICAL: Get the booking ID right after insert! ***
        $row_id = $wpdb->insert_id;

        // -- IMMEDIATE EMAILS --

        // Guest confirmation (plain text)
        $guest_subject = "Booking Received – JCubHub";
        $guest_msg = "Hi $name,\n\nYour booking for the following date(s) has been received: $dates\n\nWe will review and notify you when approved.\n\nIf you have questions, just reply to this email!\n\nJCubHub";
        $guest_mail_result = wp_mail($email, $guest_subject, $guest_msg);

        // Admin notification (HTML, with Approve/Reject buttons)
        if (function_exists('jcubhub_get_admin_emails')) {
            foreach (jcubhub_get_admin_emails() as $admin_email) {
                $approve_url = add_query_arg([
                    'jcubhub_action' => 'approve',
                    'booking_id' => $row_id,
                    'jcubhub_nonce' => wp_create_nonce('jcubhub_action_' . $row_id)
                ], site_url('/'));
                $reject_url = add_query_arg([
                    'jcubhub_action' => 'reject',
                    'booking_id' => $row_id,
                    'jcubhub_nonce' => wp_create_nonce('jcubhub_action_' . $row_id)
                ], site_url('/'));

                $admin_subject = "New Booking Submitted – JCubHub";
                $admin_html = '<div style="font-family: Arial,sans-serif; max-width:520px; margin:0 auto; background:#f8f8f8; border-radius:12px; box-shadow:0 4px 16px rgba(0,0,0,0.09); padding:32px 30px 24px 30px;">';
                $admin_html .= "<h2 style='color:#007bff; margin-top:0; font-size:1.4em;'>New Booking Submitted</h2>";
                $admin_html .= "<p><strong>Name:</strong> " . esc_html($name) . "<br>";
                $admin_html .= "<strong>Email:</strong> " . esc_html($email) . "<br>";
                $admin_html .= "<strong>Dates:</strong> " . esc_html($dates) . "<br>";
                $admin_html .= "<strong>Reason:</strong> " . esc_html($reason) . "</p>";
                $admin_html .= "<div style='display:flex; gap:18px; margin:26px 0 8px 0;'>";
                $admin_html .= "<a href='$approve_url' style='background:#2ecc40; color:#fff; font-weight:600; text-decoration:none; padding:12px 28px; border-radius:9px; font-size:1.08em; display:inline-block;'>Approve</a>";
                $admin_html .= "<a href='$reject_url' style='background:#ff5757; color:#fff; font-weight:600; text-decoration:none; padding:12px 28px; border-radius:9px; font-size:1.08em; display:inline-block;'>Reject</a>";
                $admin_html .= "</div>";
                $admin_html .= "<div style='margin-top:30px; font-size:0.96em; color:#888;'>You can also manage bookings in the <a href='" . site_url('/admin-panel/') . "' style='color:#007bff;'>JCubHub Admin Panel</a>.</div>";
                $admin_html .= '</div>';

                wp_mail(
                    $admin_email,
                    $admin_subject,
                    $admin_html,
                    [
                        'Content-Type: text/html; charset=UTF-8'
                    ]
                );
            }
        }

        // Optional: Debug log for tracking
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[JCUBHUB] Sent booking email to guest ($email): " . ($guest_mail_result ? 'SUCCESS' : 'FAIL') . " | Booking ID: $row_id");
        }

        wp_redirect(add_query_arg(['success' => '1'], wp_get_referer()));
        exit;
    }
}
