<?php
if (!defined('ABSPATH')) exit;

// Helper to get all admin emails
function jcubhub_get_admin_emails() {
    return [
        'jacobrzillmer@gmail.com',
        'bradmrrs7@gmail.com'
    ];
}

// Helper to get/set admin reminder settings
function jcubhub_get_reminder_settings() {
    $defaults = [
        'admin_reminder_enabled' => 1,
        'admin_reminder_hours' => 48,
    ];
    foreach (jcubhub_get_admin_emails() as $email) {
        $defaults['notify_' . $email] = 1;
    }
    $settings = get_option('jcubhub_reminder_settings', []);
    // Don't overwrite a 0 with a default of 1; if it's set, use it, even if it's 0
    foreach ($defaults as $key => $val) {
        if (!array_key_exists($key, $settings)) $settings[$key] = $val;
    }
    return $settings;
}
function jcubhub_update_reminder_settings($values) {
    // Always store ALL notify fields, even if unchecked (so 0 is saved)
    foreach (jcubhub_get_admin_emails() as $email) {
        if (!isset($values['notify_' . $email])) {
            $values['notify_' . $email] = 0;
        }
    }
    update_option('jcubhub_reminder_settings', $values);
}
