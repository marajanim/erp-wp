<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class JESP_ERP_Deactivator {
    public static function deactivate() {
        wp_clear_scheduled_hook( 'jesp_erp_low_stock_check' );
        delete_transient( 'jesp_erp_dashboard_stats' );
        delete_transient( 'jesp_erp_low_stock_count' );
    }
}
