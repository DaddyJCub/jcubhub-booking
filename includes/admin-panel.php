<?php
if (!defined('ABSPATH')) exit;

function jcubhub_admin_panel() {
    ob_start();
    jcubhub_handle_admin_login();
    jcubhub_handle_admin_logout();

    // Show login if not admin
    if (!isset($_SESSION['jcubhub_admin'])) {
        ?>
        <div class="admin-login-box">
            <h2>Admin Login</h2>
            <?php if (!empty($_SESSION['jcubhub_admin_error'])): ?>
                <div class="error-message"><?php echo esc_html($_SESSION['jcubhub_admin_error']); unset($_SESSION['jcubhub_admin_error']); ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="text" name="username" placeholder="Username" autocomplete="username">
                <input type="password" name="password" placeholder="Password" autocomplete="current-password">
                <button type="submit" name="jcubhub_admin_login">Login</button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    // --- Save Reminder Settings ---
    $admin_emails = jcubhub_get_admin_emails();
    $settings = jcubhub_get_reminder_settings();

    if (isset($_POST['toggle_admin_notify'])) {
        // Get current, flip target
        $toggle_email = sanitize_email($_POST['toggle_admin_notify']);
        $settings = jcubhub_get_reminder_settings();
        $settings['notify_' . $toggle_email] = empty($settings['notify_' . $toggle_email]) ? 1 : 0;
        jcubhub_update_reminder_settings($settings);
        echo "<div class='success-message'>Updated notification preference for " . esc_html($toggle_email) . ".</div>";
    }
    if (isset($_POST['jcubhub_save_reminder_settings'])) {
        $enabled = isset($_POST['admin_reminder_enabled']) ? 1 : 0;
        $hours = intval($_POST['admin_reminder_hours']);
        if ($hours < 1) $hours = 48;
        $settings['admin_reminder_enabled'] = $enabled;
        $settings['admin_reminder_hours'] = $hours;

        // Note: notify_X handled separately by toggle button

        // Pending reminder frequency and reschedule
        if (isset($_POST['pending_reminder_hours'])) {
            $freq = (int) $_POST['pending_reminder_hours'];
            if ($freq < 1) $freq = 24;
            update_option('jcubhub_pending_reminder_freq', $freq);
            $timestamp = wp_next_scheduled('jcubhub_pending_reminder_event');
            if ($timestamp) wp_unschedule_event($timestamp, 'jcubhub_pending_reminder_event');
            wp_schedule_event(time() + 60, "jcubhub_pending_reminder_$freq", 'jcubhub_pending_reminder_event');
        }
        jcubhub_update_reminder_settings($settings);
        echo "<div class='success-message'>Settings saved.</div>";
    }
    // Reload latest
    $settings = jcubhub_get_reminder_settings();

    global $wpdb;
    $table = $wpdb->prefix . "jcubhub_bookings";
    $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
    $unavail = $wpdb->get_col("SELECT date FROM {$wpdb->prefix}jcubhub_unavailable");
    $pending = $approved = $rejected = [];
    foreach ($rows as $row) {
        if ($row->status === "pending") $pending[] = $row;
        elseif ($row->status === "approved") $approved[] = $row;
        elseif ($row->status === "rejected") $rejected[] = $row;
    }
    $events = [];
    foreach ($rows as $row) {
        $statusColor = ($row->status === 'approved') ? '#2ecc40' : ($row->status === 'pending' ? '#ffc400' : '#ff5757');
        $dateArr = array_map('trim', explode(',', $row->dates));
        foreach ($dateArr as $date) {
            $events[] = [
                'title' => esc_html($row->name),
                'start' => $date,
                'color' => $statusColor
            ];
        }
    }
    foreach ($unavail as $d) {
        $events[] = [
            'title' => 'Unavailable',
            'start' => $d,
            'color' => '#b0b0b0'
        ];
    }
    ?>
    <div class="admin-panel-mainbox">
        <div class="admin-panel-header">
            <h2>Admin Panel – Manage Bookings</h2>
            <a href="<?php echo esc_url(add_query_arg('jcubhub_admin_logout', '1')); ?>" class="logout-btn">Logout</a>
        </div>
        <div id="admin-calendar"></div>
        <div style="margin:14px 0 24px 0;">
            <button id="unavail-select-btn" style="background:#b0b0b0;color:#fff;border:none;padding:10px 18px;border-radius:9px;font-weight:600;cursor:pointer;">Mark/Unmark Unavailable Date(s)</button>
        </div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Admin panel JavaScript loading...');
    
    // Check if jQuery is available
    if (typeof jQuery === 'undefined') {
        console.error('jQuery is not loaded! Cannot proceed.');
        alert('There is a JavaScript loading issue. Please refresh the page.');
        return;
    }
    
    console.log('jQuery is loaded, version:', jQuery.fn.jquery);
    console.log('AJAX URL:', jcubhub_ajax.ajaxurl);
    
    var calendarEl = document.getElementById('admin-calendar');
    if (!calendarEl) {
        console.error('Calendar element not found!');
        return;
    }
    
    var unavailable = <?php echo json_encode($unavail); ?>;
    var selected = [];
    
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        selectable: true,
        height: 'auto',
        events: <?php echo json_encode($events); ?>,
        dateClick: function(info) {
            var date = info.dateStr;
            if (selected.includes(date)) {
                selected = selected.filter(d => d !== date);
                info.dayEl.classList.remove('selected-date');
            } else {
                selected.push(date);
                info.dayEl.classList.add('selected-date');
            }
            console.log('Currently selected dates:', selected);
        },
        dayCellDidMount: function(arg) {
            var date = arg.date.toISOString().slice(0,10);
            if (unavailable.includes(date)) {
                arg.el.style.background = '#b0b0b0';
                arg.el.style.color = '#fff';
                arg.el.title = "Unavailable";
                arg.el.classList.add('unavail-date');
            }
        }
    });
    
    calendar.render();

    // Mark/Unmark Unavailable button
    document.getElementById('unavail-select-btn').onclick = function() {
        if (selected.length === 0) {
            alert("Please select date(s) on the calendar first.");
            return;
        }
        
        console.log('Processing dates:', selected);
        
        // Check if all selected dates are already unavailable
        var all_unavail = selected.every(date => unavailable.includes(date));
        
        if (all_unavail) {
            // Remove each date individually
            console.log('Removing unavailable dates...');
            var completed = 0;
            var errors = 0;
            
            selected.forEach(function(date) {
                jQuery.ajax({
                    url: jcubhub_ajax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'jcubhub_unavail_remove',
                        date: date,
                        nonce: jcubhub_ajax.nonce
                    },
                    success: function(response) {
                        completed++;
                        console.log('Removed date:', date, response);
                        if (completed === selected.length) {
                            location.reload();
                        }
                    },
                    error: function(xhr, status, error) {
                        errors++;
                        console.error('Error removing date:', date, error);
                        if (completed + errors === selected.length) {
                            alert('Some dates could not be removed. Please try again.');
                            location.reload();
                        }
                    }
                });
            });
        } else {
            // Add all selected dates as unavailable
            console.log('Adding unavailable dates...');
            jQuery.ajax({
                url: jcubhub_ajax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'jcubhub_unavail_add',
                    dates: selected.join(','),
                    nonce: jcubhub_ajax.nonce
                },
                success: function(response) {
                    console.log('Add response:', response);
                    if (response.success) {
                        alert(response.data.message || 'Dates marked as unavailable');
                        location.reload();
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', xhr.responseText);
                    alert('Error adding unavailable dates. Please check the console and try again.');
                }
            });
        }
    };
});
</script>
        <div class="admin-table-container">
            <?php
            if (!function_exists('jcubhub_booking_table')) {
                function jcubhub_booking_table($title, $data, $status) {
                    ?>
                    <h3><?php echo $title; ?></h3>
                    <table class="admin-table">
                        <tr>
                            <th>Name</th><th>Email</th><th>Reason</th><th>Dates</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <?php if ($status === "approved") echo "<th>Approved By</th>"; ?>
                            <th>Reminder</th>
                            <th>Actions</th>
                        </tr>
                        <?php foreach ($data as $row): ?>
                            <tr>
                                <td><?php echo esc_html($row->name); ?></td>
                                <td><?php echo esc_html($row->email); ?></td>
                                <td><?php echo esc_html($row->reason); ?></td>
                                <td><?php echo esc_html($row->dates); ?></td>
                                <td><?php echo esc_html(date('Y-m-d H:i', strtotime($row->created_at))); ?></td>
                                <td>
                                    <span class="status-badge <?php echo esc_attr($row->status); ?>">
                                        <?php echo ucfirst($row->status); ?>
                                    </span>
                                </td>
                                <?php if ($status === "approved") echo "<td>".esc_html($row->approved_by)."</td>"; ?>
                                <td>
                                    <?php if (!empty($row->reminder_optin)): ?>
                                        <span style="color:#2ecc40;font-weight:600;">✔</span>
                                    <?php else: ?>
                                        <span style="color:#888;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-btns">
                                    <?php if ($status === 'pending'): ?>
                                        <button class="approve-btn" data-id="<?php echo $row->id; ?>">Approve</button>
                                        <button class="reject-btn" data-id="<?php echo $row->id; ?>">Reject</button>
                                    <?php elseif ($status === 'approved'): ?>
                                        <button class="reject-btn" data-id="<?php echo $row->id; ?>">Reject</button>
                                    <?php elseif ($status === 'rejected'): ?>
                                        <button class="approve-btn" data-id="<?php echo $row->id; ?>">Approve</button>
                                    <?php endif; ?>
                                        <button class="delete-btn" data-id="<?php echo $row->id; ?>">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                    <?php
                }
            }
            if ($pending) jcubhub_booking_table("Pending Bookings", $pending, 'pending');
            if ($approved) jcubhub_booking_table("Approved Bookings", $approved, 'approved');
            if ($rejected) jcubhub_booking_table("Rejected Bookings", $rejected, 'rejected');
            ?>
        </div>
        <div class="reminder-settings-box">
            <h3>Reminder Notification Settings</h3>
            <form method="post">
                <label>
                    <input type="checkbox" name="admin_reminder_enabled" value="1" <?php checked($settings['admin_reminder_enabled']); ?> />
                    Enable admin reminder notifications
                </label>
                <br>
                <label>
                    Reminder lead time (hours): 
                    <input type="number" name="admin_reminder_hours" min="1" value="<?php echo esc_attr($settings['admin_reminder_hours']); ?>" style="width:60px;">
                </label>
                <br>
                <div style="margin: 12px 0 4px 0; padding: 6px 0;">
                    <strong>Notify these admins:</strong>
                    <?php foreach ($admin_emails as $email): ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="toggle_admin_notify" value="<?php echo esc_attr($email); ?>">
                            <button
                                type="submit"
                                class="admin-toggle-btn <?php echo ($settings['notify_' . $email]) ? 'enabled' : 'disabled'; ?>"
                                style="margin-right:12px;margin-bottom:5px;"
                            >
                                <?php echo ($settings['notify_' . $email]) ? "Enabled" : "Disabled"; ?>
                            </button>
                            <span><?php echo esc_html($email); ?></span>
                        </form>
                        <br>
                    <?php endforeach; ?>
                </div>
                <label style="margin-top:14px;display:block;">
                    Pending bookings reminder (hours): 
                    <input type="number" name="pending_reminder_hours" min="1" max="168"
                        value="<?php echo esc_attr(get_option('jcubhub_pending_reminder_freq', 24)); ?>" style="width:60px;" />
                    <span style="font-size:0.95em;color:#666;">Send a summary of all pending bookings to admins every X hours</span>
                </label>
                <button type="submit" name="jcubhub_save_reminder_settings" style="margin-top:15px;">Save Settings</button>
            </form>
        </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Booking actions JavaScript loading...');
    
    // Approve buttons
    document.querySelectorAll(".approve-btn").forEach(btn => {
        btn.addEventListener("click", function(e) {
            e.preventDefault();
            if (confirm('Approve this booking?')) {
                handleAction(btn.dataset.id, 'approved', btn);
            }
        });
    });
    
    // Reject buttons
    document.querySelectorAll(".reject-btn").forEach(btn => {
        btn.addEventListener("click", function(e) {
            e.preventDefault();
            if (confirm('Reject this booking?')) {
                handleAction(btn.dataset.id, 'rejected', btn);
            }
        });
    });
    
    // Delete buttons
    document.querySelectorAll(".delete-btn").forEach(btn => {
        btn.addEventListener("click", function(e) {
            e.preventDefault();
            if (confirm('Delete this booking permanently? This cannot be undone.')) {
                handleAction(btn.dataset.id, 'deleted', btn);
            }
        });
    });
    
    function handleAction(id, action, buttonElement) {
        console.log('Handling action:', action, 'for booking ID:', id);
        
        // Disable the button and show loading state
        var originalText = buttonElement.textContent;
        buttonElement.textContent = 'Processing...';
        buttonElement.disabled = true;
        
        // Make the AJAX request
        jQuery.ajax({
            url: jcubhub_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'jcubhub_manage_booking',
                id: id,
                status: action,
                nonce: jcubhub_ajax.nonce
            },
            success: function(response) {
                console.log('Server response:', response);
                if (response.success) {
                    // Success - reload the page to show updated data
                    window.location.reload();
                } else {
                    // Error - show message and restore button
                    alert('Error: ' + (response.data || 'Unknown error occurred'));
                    buttonElement.textContent = originalText;
                    buttonElement.disabled = false;
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                alert('Connection error. Please check your internet connection and try again.');
                buttonElement.textContent = originalText;
                buttonElement.disabled = false;
            }
        });
    }
});
</script>
 <script>
// Add data-labels for mobile table display
document.addEventListener('DOMContentLoaded', function() {
    // Only run on mobile
    if (window.innerWidth <= 768) {
        // Get all admin tables
        var tables = document.querySelectorAll('.admin-table');
        
        tables.forEach(function(table) {
            // Get headers from this specific table
            var headers = table.querySelectorAll('th');
            var headerTexts = [];
            
            headers.forEach(function(header) {
                headerTexts.push(header.textContent.trim());
            });
            
            // Apply labels to each row's cells
            var rows = table.querySelectorAll('tbody tr');
            rows.forEach(function(row) {
                var cells = row.querySelectorAll('td');
                cells.forEach(function(cell, index) {
                    if (headerTexts[index]) {
                        // Skip the "Actions" label for action buttons
                        if (headerTexts[index] === 'Actions') {
                            cell.setAttribute('data-label', '');
                        } else {
                            cell.setAttribute('data-label', headerTexts[index] + ':');
                        }
                    }
                });
            });
        });
        
        // Special handling for status badges to ensure they display inline
        document.querySelectorAll('.status-badge').forEach(function(badge) {
            badge.style.display = 'inline-block';
        });
    }
});

// Also run on window resize
window.addEventListener('resize', function() {
    if (window.innerWidth <= 768) {
        // Re-run the labeling if needed
        var tables = document.querySelectorAll('.admin-table');
        tables.forEach(function(table) {
            var firstRow = table.querySelector('tbody tr');
            if (firstRow) {
                var firstCell = firstRow.querySelector('td');
                // Check if labels already applied
                if (!firstCell.hasAttribute('data-label')) {
                    // Re-apply labels
                    location.reload(); // Simple solution: reload to reapply
                }
            }
        });
    }
});
</script>   
</div>

<?php
$log_path = plugin_dir_path(__FILE__) . '../jcubhub-bot-log.txt';
if (file_exists($log_path)) {
    $log_content = file_get_contents($log_path);
    ?>
    <div class="reminder-settings-box" style="margin-top:30px;">
        <h3>Bot Log</h3>
        <button id="toggle-bot-log" style="margin-bottom:12px;">View Bot Log</button>
        <pre id="bot-log-content" style="display:none; max-height:300px; overflow:auto; background:#111; color:#eee; padding:14px; border-radius:10px; font-size:0.9em;"><?php echo esc_html($log_content); ?></pre>
    </div>
    <script>
        const toggleBtn = document.getElementById("toggle-bot-log");
        const logBox = document.getElementById("bot-log-content");
        toggleBtn.addEventListener("click", () => {
            logBox.style.display = logBox.style.display === "none" ? "block" : "none";
            if (logBox.style.display === "block") {
                logBox.scrollTop = logBox.scrollHeight;
            }
        });
    </script>
    <?php
}
?>

    <?php
    return ob_get_clean();
}
add_shortcode('jcubhub_admin_panel', 'jcubhub_admin_panel');
?>
