<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin menus, page routing, and asset enqueuing.
 */
class JESP_ERP_Admin
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_menu', array($this, 'register_menus'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Register admin menu and submenus.
     */
    public function register_menus()
    {
        add_menu_page(
            __('ERP Manager', 'jesp-erp'),
            __('ERP Manager', 'jesp-erp'),
            'manage_woocommerce',
            'jesp-erp',
            array($this, 'render_dashboard'),
            'dashicons-analytics',
            56
        );

        add_submenu_page('jesp-erp', __('Dashboard', 'jesp-erp'), __('Dashboard', 'jesp-erp'), 'manage_woocommerce', 'jesp-erp', array($this, 'render_dashboard'));
        add_submenu_page('jesp-erp', __('Stock Management', 'jesp-erp'), __('Stock Management', 'jesp-erp'), 'manage_woocommerce', 'jesp-erp-stock', array($this, 'render_stock'));
        add_submenu_page('jesp-erp', __('Import Products', 'jesp-erp'), __('Import Products', 'jesp-erp'), 'manage_woocommerce', 'jesp-erp-import', array($this, 'render_import'));
        add_submenu_page('jesp-erp', __('Export Products', 'jesp-erp'), __('Export Products', 'jesp-erp'), 'manage_woocommerce', 'jesp-erp-export', array($this, 'render_export'));
        add_submenu_page('jesp-erp', __('Bulk Discounts', 'jesp-erp'), __('Bulk Discounts', 'jesp-erp'), 'manage_woocommerce', 'jesp-erp-discounts', array($this, 'render_discounts'));
        add_submenu_page('jesp-erp', __('Orders & Analytics', 'jesp-erp'), __('Orders & Analytics', 'jesp-erp'), 'manage_woocommerce', 'jesp-erp-orders', array($this, 'render_orders'));
        add_submenu_page('jesp-erp', __('Customers', 'jesp-erp'), __('Customers', 'jesp-erp'), 'manage_woocommerce', 'jesp-erp-customers', array($this, 'render_customers'));
    }

    /**
     * Enqueue styles and scripts only on plugin pages.
     */
    public function enqueue_assets($hook)
    {
        $plugin_pages = array(
            'toplevel_page_jesp-erp',
            'erp-manager_page_jesp-erp-stock',
            'erp-manager_page_jesp-erp-import',
            'erp-manager_page_jesp-erp-export',
            'erp-manager_page_jesp-erp-discounts',
            'erp-manager_page_jesp-erp-orders',
            'erp-manager_page_jesp-erp-customers',
        );

        if (!in_array($hook, $plugin_pages, true)) {
            return;
        }

        wp_enqueue_style(
            'jesp-erp-admin',
            JESP_ERP_PLUGIN_URL . 'admin/css/erp-admin.css',
            array(),
            JESP_ERP_VERSION
        );

        // WordPress media uploader (for Quick Edit image selection).
        wp_enqueue_media();

        // Chart.js from CDN.
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js',
            array(),
            '4.4.4',
            true
        );

        wp_enqueue_script(
            'jesp-erp-admin',
            JESP_ERP_PLUGIN_URL . 'admin/js/erp-admin.js',
            array('jquery', 'chartjs'),
            JESP_ERP_VERSION . '.7',
            true
        );

        // Get product categories for filters.
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'fields' => 'id=>name',
        ));

        wp_localize_script('jesp-erp-admin', 'jespErp', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('jesp_erp_nonce'),
            'categories' => is_array($categories) ? $categories : array(),
            'currency' => get_woocommerce_currency_symbol(),
            'strings' => array(
                'confirm_delete' => __('Are you sure?', 'jesp-erp'),
                'saving' => __('Saving...', 'jesp-erp'),
                'saved' => __('Saved!', 'jesp-erp'),
                'error' => __('An error occurred.', 'jesp-erp'),
                'importing' => __('Importing...', 'jesp-erp'),
                'export_ready' => __('Export ready!', 'jesp-erp'),
                'no_results' => __('No results found.', 'jesp-erp'),
                'loading' => __('Loading...', 'jesp-erp'),
            ),
        ));
    }

    /* View renderers */
    public function render_dashboard()
    {
        include JESP_ERP_PLUGIN_DIR . 'admin/views/dashboard.php';
    }
    public function render_stock()
    {
        include JESP_ERP_PLUGIN_DIR . 'admin/views/stock-management.php';
    }
    public function render_import()
    {
        include JESP_ERP_PLUGIN_DIR . 'admin/views/import.php';
    }
    public function render_export()
    {
        include JESP_ERP_PLUGIN_DIR . 'admin/views/export.php';
    }
    public function render_discounts()
    {
        include JESP_ERP_PLUGIN_DIR . 'admin/views/bulk-discounts.php';
    }
    public function render_orders()
    {
        include JESP_ERP_PLUGIN_DIR . 'admin/views/orders-analytics.php';
    }
    public function render_customers()
    {
        include JESP_ERP_PLUGIN_DIR . 'admin/views/customers.php';
    }
}
