<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}
global $wpdb;
$tables = array(
    $wpdb->prefix . 'jesp_erp_stock_locations',
    $wpdb->prefix . 'jesp_erp_customer_purchases',
    $wpdb->prefix . 'jesp_erp_bulk_discounts',
    $wpdb->prefix . 'jesp_erp_stock_log',
);
foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore
}
delete_option( 'jesp_erp_db_version' );
// NOTE: _jesp_buying_price post meta is intentionally NOT deleted here
// so that buying prices survive uninstall and are restored on reinstall.
