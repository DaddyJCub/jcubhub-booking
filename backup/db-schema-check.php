<?php
if (!defined('ABSPATH')) exit;

function jcubhub_migrate_database_schema() {
    global $wpdb;
    $prefix = $wpdb->prefix;

    // Table creation SQL
    $tables = [
        "{$prefix}jcubhub_bookings" => "
            CREATE TABLE IF NOT EXISTS {$prefix}jcubhub_bookings (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                reason TEXT,
                dates TEXT,
                status VARCHAR(20) DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                approved_by VARCHAR(100),
                reminder_optin BOOLEAN DEFAULT 0,
                PRIMARY KEY (id)
            )
        ",
        "{$prefix}jcubhub_unavailable" => "
            CREATE TABLE IF NOT EXISTS {$prefix}jcubhub_unavailable (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                date DATE NOT NULL,
                recurring BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            )
        ",
        "{$prefix}jcubhub_admins" => "
            CREATE TABLE IF NOT EXISTS {$prefix}jcubhub_admins (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                username VARCHAR(100) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            )
        ",
        "{$prefix}jcubhub_reminders" => "
            CREATE TABLE IF NOT EXISTS {$prefix}jcubhub_reminders (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                days_before INT NOT NULL,
                active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            )
        ",
    ];

    // Expected columns per table
    $expected_columns = [
        "{$prefix}jcubhub_bookings" => [
            'id', 'name', 'email', 'reason', 'dates', 'status',
            'created_at', 'approved_by', 'reminder_optin'
        ],
        "{$prefix}jcubhub_unavailable" => ['id', 'date', 'recurring', 'created_at'],
        "{$prefix}jcubhub_admins" => ['id', 'username', 'password_hash', 'created_at'],
        "{$prefix}jcubhub_reminders" => ['id', 'days_before', 'active', 'created_at'],
    ];

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    foreach ($tables as $table => $create_sql) {
        // Create if needed
        dbDelta($create_sql);

        // Check and patch missing columns
        $existing_columns = $wpdb->get_col("SHOW COLUMNS FROM $table", 0);

        foreach ($expected_columns[$table] as $column) {
            if (!in_array($column, $existing_columns)) {
                switch ($column) {
                    case 'reason':
                        $wpdb->query("ALTER TABLE $table ADD COLUMN reason TEXT;");
                        break;
                    case 'dates':
                        $wpdb->query("ALTER TABLE $table ADD COLUMN dates TEXT;");
                        break;
                    case 'approved_by':
                        $wpdb->query("ALTER TABLE $table ADD COLUMN approved_by VARCHAR(100);");
                        break;
                    case 'reminder_optin':
                        $wpdb->query("ALTER TABLE $table ADD COLUMN reminder_optin BOOLEAN DEFAULT 0;");
                        break;
                    case 'recurring':
                        $wpdb->query("ALTER TABLE $table ADD COLUMN recurring BOOLEAN DEFAULT FALSE;");
                        break;
                    case 'password_hash':
                        $wpdb->query("ALTER TABLE $table ADD COLUMN password_hash VARCHAR(255) NOT NULL;");
                        break;
                    case 'active':
                        $wpdb->query("ALTER TABLE $table ADD COLUMN active BOOLEAN DEFAULT TRUE;");
                        break;
                    case 'created_at':
                        $wpdb->query("ALTER TABLE $table ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;");
                        break;
                    case 'status':
                        $wpdb->query("ALTER TABLE $table ADD COLUMN status VARCHAR(20) DEFAULT 'pending';");
                        break;
                    case 'time':
                        $wpdb->query("ALTER TABLE $table ADD COLUMN time VARCHAR(100);");
                        break;
                }
            }
        }
    }
}
