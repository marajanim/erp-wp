<?php
/**
 * Plugin Name: JesusPended ERP - Inventory & Customer Manager
 * Plugin URI:  https://example.com/jesp-erp
 * Description: ERP-style inventory and customer management system fully integrated with WooCommerce. Manage stock by location, bulk discounts, customer insights, and analytics.
 * Version:     1.0.0
 * Author:      ERP Dev Team
 * Author URI:  https://example.com
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: jesp-erp
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 7.0
 * WC tested up to: 8.6
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'JESP_ERP_VERSION', '1.0.0' );
define( 'JESP_ERP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JESP_ERP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'JESP_ERP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once JESP_ERP_PLUGIN_DIR . 'includes/class-erp-activator.php';
require_once JESP_ERP_PLUGIN_DIR . 'includes/class-erp-deactivator.php';
require_once JESP_ERP_PLUGIN_DIR . 'includes/class-erp-database.php';
require_once JESP_ERP_PLUGIN_DIR . 'includes/class-erp-admin.php';
require_once JESP_ERP_PLUGIN_DIR . 'includes/class-erp-ajax.php';
require_once JESP_ERP_PLUGIN_DIR . 'includes/class-erp-stock.php';
require_once JESP_ERP_PLUGIN_DIR . 'includes/class-erp-export.php';
require_once JESP_ERP_PLUGIN_DIR . 'includes/class-erp-import.php';
require_once JESP_ERP_PLUGIN_DIR . 'includes/class-erp-discount.php';
require_once JESP_ERP_PLUGIN_DIR . 'includes/class-erp-orders.php';
require_once JESP_ERP_PLUGIN_DIR . 'includes/class-erp-customers.php';
require_once JESP_ERP_PLUGIN_DIR . 'includes/class-erp-woocommerce.php';
require_once JESP_ERP_PLUGIN_DIR . 'includes/class-erp-invoices.php';

register_activation_hook( __FILE__, array( 'JESP_ERP_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'JESP_ERP_Deactivator', 'deactivate' ) );

add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

add_action( 'plugins_loaded', 'jesp_erp_init' );

function jesp_erp_init() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            esc_html_e( 'ERP Inventory & Customer Manager requires WooCommerce to be installed and active.', 'jesp-erp' );
            echo '</p></div>';
        } );
        return;
    }
    JESP_ERP_Admin::get_instance();
    JESP_ERP_Ajax::get_instance();
    JESP_ERP_WooCommerce::get_instance();
}
