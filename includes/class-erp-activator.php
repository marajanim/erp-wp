<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class JESP_ERP_Activator {

    public static function activate() {
        self::create_tables();
        update_option( 'jesp_erp_db_version', JESP_ERP_VERSION );

        // Seed ERP stock from existing WC stock quantities (skips products already tracked).
        if ( class_exists( 'WooCommerce' ) ) {
            JESP_ERP_Stock::sync_stock_from_woocommerce();
            JESP_ERP_Customers::sync_all_historical_orders();
        }
    }

    private static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $t1 = $wpdb->prefix . 'jesp_erp_stock_locations';
        dbDelta( "CREATE TABLE {$t1} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT(20) UNSIGNED NOT NULL,
            location_type VARCHAR(50) NOT NULL DEFAULT 'warehouse',
            location_name VARCHAR(255) NOT NULL DEFAULT '',
            quantity INT(11) NOT NULL DEFAULT 0,
            min_stock INT(11) NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY product_location (product_id, location_type, location_name),
            KEY idx_product_id (product_id),
            KEY idx_location_type (location_type)
        ) {$charset};" );

        $t2 = $wpdb->prefix . 'jesp_erp_customer_purchases';
        dbDelta( "CREATE TABLE {$t2} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_name VARCHAR(255) NOT NULL DEFAULT '',
            email VARCHAR(255) NOT NULL DEFAULT '',
            phone VARCHAR(50) NOT NULL DEFAULT '',
            address TEXT NOT NULL,
            total_spent DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            order_count INT(11) NOT NULL DEFAULT 0,
            last_order_date DATETIME DEFAULT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_phone (phone),
            KEY idx_email (email),
            KEY idx_total_spent (total_spent)
        ) {$charset};" );

        $t3 = $wpdb->prefix . 'jesp_erp_bulk_discounts';
        dbDelta( "CREATE TABLE {$t3} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL DEFAULT '',
            discount_type VARCHAR(20) NOT NULL DEFAULT 'percentage',
            discount_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            filters_json LONGTEXT NOT NULL,
            affected_products LONGTEXT NOT NULL,
            original_prices LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            start_date DATETIME DEFAULT NULL,
            end_date DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_status (status)
        ) {$charset};" );

        $t4 = $wpdb->prefix . 'jesp_erp_stock_log';
        dbDelta( "CREATE TABLE {$t4} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT(20) UNSIGNED NOT NULL,
            location_type VARCHAR(50) NOT NULL DEFAULT 'warehouse',
            location_name VARCHAR(255) NOT NULL DEFAULT '',
            change_qty INT(11) NOT NULL DEFAULT 0,
            new_qty INT(11) NOT NULL DEFAULT 0,
            reason VARCHAR(255) NOT NULL DEFAULT '',
            user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_product_id (product_id),
            KEY idx_created_at (created_at)
        ) {$charset};" );

        JESP_ERP_Invoices::create_tables();
    }
}
