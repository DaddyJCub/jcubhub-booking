<?php
if (!defined('ABSPATH')) exit;

// Start session for admin handling
function jcubhub_start_session() {
    if (!session_id() && !headers_sent()) {
        session_start();
    }
}
// Start session early with high priority
add_action('init', 'jcubhub_start_session', 1);

// Admin login/logout logic (with multiple admins)
function jcubhub_handle_admin_login() {
    $admins = [
        'jacob' => 'password',
        'brad'  => 'password'
    ];
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['jcubhub_admin_login'])) {
        $username = strtolower(sanitize_text_field($_POST['username']));
        $password = sanitize_text_field($_POST['password']);
        if (isset($admins[$username]) && $admins[$username] === $password) {
            $_SESSION['jcubhub_admin'] = $username;
            wp_redirect($_SERVER['REQUEST_URI']);
            exit;
        } else {
            $_SESSION['jcubhub_admin_error'] = "Invalid credentials.";
        }
    }
}
function jcubhub_handle_admin_logout() {
    if (isset($_GET['jcubhub_admin_logout'])) {
        unset($_SESSION['jcubhub_admin']);
        wp_redirect(remove_query_arg('jcubhub_admin_logout'));
        exit;
    }
}

// Shortcode for login box
function jcubhub_admin_login_shortcode() {
    ob_start();
    jcubhub_handle_admin_login();
    jcubhub_handle_admin_logout();

    if (isset($_SESSION['jcubhub_admin'])) {
        $admin_panel_url = site_url('/admin-panel/'); // adjust if your admin panel is elsewhere
        ?>
        <div class="admin-login-box" style="text-align:center;">
            <div class="success-message" style="margin-bottom:15px;">
                You are already logged in as <strong><?php echo esc_html($_SESSION['jcubhub_admin']); ?></strong>.
            </div>
            <a href="<?php echo esc_url($admin_panel_url); ?>" class="admin-panel-btn" style="margin-right:10px;">Admin Panel</a>
            <a href="<?php echo esc_url(add_query_arg('jcubhub_admin_logout', '1')); ?>" class="logout-btn">Logout</a>
        </div>
        <?php
        return ob_get_clean();
    }
    ?>
    <div class="admin-login-box">
        <h2>Admin Login</h2>
        <?php if (!empty($_SESSION['jcubhub_admin_error'])): ?>
            <div class="error-message"><?php echo esc_html($_SESSION['jcubhub_admin_error']); unset($_SESSION['jcubhub_admin_error']); ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Username" autocomplete="username" required>
            <input type="password" name="password" placeholder="Password" autocomplete="current-password" required>
            <button type="submit" name="jcubhub_admin_login">Login</button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('jcubhub_admin_login', 'jcubhub_admin_login_shortcode');
?>
