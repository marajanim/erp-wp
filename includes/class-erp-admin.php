<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin menus, page routing, asset enqueuing, and dashboard widget.
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
        add_action('wp_dashboard_setup', array($this, 'register_dashboard_widget'));
        add_action('admin_init', array($this, 'maybe_create_invoice_tables'));
        add_action('admin_init', array($this, 'maybe_create_finance_tables'));
    }

    public function maybe_create_invoice_tables()
    {
        if (get_option('jesp_erp_invoices_db') !== '1.0') {
            JESP_ERP_Invoices::create_tables();
            update_option('jesp_erp_invoices_db', '1.0');
        }
    }


    public function maybe_create_finance_tables()
    {
        if (get_option('jesp_erp_finance_db') !== '1.0') {
            JESP_ERP_Finance::create_tables();
            update_option('jesp_erp_finance_db', '1.0');
        }
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
            1  // Position 1 = top of sidebar, above Dashboard.
        );

        $hidden = (array) get_option('jesp_erp_hidden_tabs', array());

        add_submenu_page('jesp-erp', __('Dashboard', 'jesp-erp'), __('Dashboard', 'jesp-erp'), 'manage_woocommerce', 'jesp-erp', array($this, 'render_dashboard'));
        if (!in_array('jesp-erp-stock', $hidden, true))     add_submenu_page('jesp-erp', __('Stock Management', 'jesp-erp'), __('Stock Management', 'jesp-erp'), 'manage_woocommerce', 'jesp-erp-stock', array($this, 'render_stock'));
        if (!in_array('jesp-erp-import', $hidden, true))    add_submenu_page('jesp-erp', __('Import Products', 'jesp-erp'), __('Import Products', 'jesp-erp'), 'manage_woocommerce', 'jesp-erp-import', array($this, 'render_import'));
        if (!in_array('jesp-erp-export', $hidden, true))    add_submenu_page('jesp-erp', __('Export Products', 'jesp-erp'), __('Export Products', 'jesp-erp'), 'manage_woocommerce', 'jesp-erp-export', array($this, 'render_export'));
        if (!in_array('jesp-erp-discounts', $hidden, true)) add_submenu_page('jesp-erp', __('Bulk Discounts', 'jesp-erp'), __('Bulk Discounts', 'jesp-erp'), 'manage_woocommerce', 'jesp-erp-discounts', array($this, 'render_discounts'));
        if (!in_array('jesp-erp-orders', $hidden, true))    add_submenu_page('jesp-erp', __('Orders & Analytics', 'jesp-erp'), __('Orders & Analytics', 'jesp-erp'), 'manage_woocommerce', 'jesp-erp-orders', array($this, 'render_orders'));
        if (!in_array('jesp-erp-customers', $hidden, true)) add_submenu_page('jesp-erp', __('Customers', 'jesp-erp'), __('Customers', 'jesp-erp'), 'manage_woocommerce', 'jesp-erp-customers', array($this, 'render_customers'));
        if (!in_array('jesp-erp-hero', $hidden, true))      add_submenu_page('jesp-erp', __('Hero Products', 'jesp-erp'), __('Hero Products', 'jesp-erp'), 'manage_woocommerce', 'jesp-erp-hero', array($this, 'render_hero_products'));
        if (!in_array('jesp-erp-invoices', $hidden, true))  add_submenu_page('jesp-erp', __('Invoice Maker', 'jesp-erp'), __('Invoice Maker', 'jesp-erp'), 'manage_woocommerce', 'jesp-erp-invoices', array($this, 'render_invoice_maker'));
        if (!in_array('jesp-erp-finance', $hidden, true))   add_submenu_page('jesp-erp', __('Finance', 'jesp-erp'), __('Finance', 'jesp-erp'), 'manage_woocommerce', 'jesp-erp-finance', array($this, 'render_finance'));
        add_submenu_page('jesp-erp', __('Settings', 'jesp-erp'), __('Settings', 'jesp-erp'), 'manage_woocommerce', 'jesp-erp-settings', array($this, 'render_settings'));
    }

    /* ------------------------------------------------------------------ */
    /*  WordPress Dashboard widget                                         */
    /* ------------------------------------------------------------------ */
    public function register_dashboard_widget()
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        wp_add_dashboard_widget(
            'jesp_erp_store_overview',
            __('ERP Store Overview', 'jesp-erp'),
            array($this, 'render_dashboard_widget')
        );
    }

    public function render_dashboard_widget()
    {
        $today        = gmdate('Y-m-d');
        $from_30      = gmdate('Y-m-d', strtotime('-30 days'));
        $currency     = get_woocommerce_currency_symbol();

        $month        = JESP_ERP_Orders::get_summary($from_30, $today);
        $day          = JESP_ERP_Orders::get_summary($today, $today);
        $low_stock    = JESP_ERP_Stock::get_low_stock_count();

        global $wpdb;
        $total_products = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'"
        );
        $total_customers = (int) $wpdb->get_var(
            'SELECT COUNT(*) FROM ' . JESP_ERP_Database::customer_purchases_table()
        );

        $fmt = function ($val) use ($currency) {
            $n = (float) $val;
            return $currency . ($n >= 1000 ? number_format($n / 1000, 1) . 'k' : number_format($n, 2));
        };

        $month_orders  = (int) ($month->total_orders  ?? 0);
        $month_revenue = $fmt($month->total_revenue   ?? 0);
        $day_orders    = (int) ($day->total_orders    ?? 0);
        $day_revenue   = $fmt($day->total_revenue     ?? 0);
        $warn_style    = $low_stock > 0 ? 'color:#dc2626;font-weight:700;' : '';
        $warn_bg       = $low_stock > 0 ? 'background:#fff1f2;border-color:#fecaca;' : '';
        ?>
        <style>
        #jesp_erp_store_overview .jesp-dw-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px;}
        #jesp_erp_store_overview .jesp-dw-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:11px 13px;}
        #jesp_erp_store_overview .jesp-dw-box.blue{background:#eef2ff;border-color:#c7d2fe;}
        #jesp_erp_store_overview .jesp-dw-num{font-size:20px;font-weight:700;color:#1e293b;line-height:1.2;display:block;}
        #jesp_erp_store_overview .jesp-dw-lbl{font-size:10px;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-top:2px;display:block;}
        #jesp_erp_store_overview .jesp-dw-today{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 13px;margin-bottom:10px;text-align:center;}
        #jesp_erp_store_overview .jesp-dw-today-num{font-size:17px;font-weight:700;color:#16a34a;display:block;}
        #jesp_erp_store_overview .jesp-dw-today-lbl{font-size:10px;color:#15803d;text-transform:uppercase;display:block;}
        #jesp_erp_store_overview .jesp-dw-section{font-size:10px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.6px;margin:0 0 6px;}
        #jesp_erp_store_overview .jesp-dw-footer{display:flex;justify-content:space-between;align-items:center;border-top:1px solid #f1f5f9;padding-top:9px;margin-top:4px;}
        #jesp_erp_store_overview .jesp-dw-link{color:#6366f1!important;font-size:12px;font-weight:600;text-decoration:none;}
        #jesp_erp_store_overview .jesp-dw-link:hover{color:#4f46e5!important;}
        #jesp_erp_store_overview .jesp-dw-ts{font-size:11px;color:#94a3b8;}
        </style>

        <p class="jesp-dw-section"><?php esc_html_e('Last 30 Days', 'jesp-erp'); ?></p>
        <div class="jesp-dw-grid">
            <div class="jesp-dw-box blue">
                <span class="jesp-dw-num"><?php echo esc_html($month_revenue); ?></span>
                <span class="jesp-dw-lbl"><?php esc_html_e('Revenue', 'jesp-erp'); ?></span>
            </div>
            <div class="jesp-dw-box blue">
                <span class="jesp-dw-num"><?php echo esc_html($month_orders); ?></span>
                <span class="jesp-dw-lbl"><?php esc_html_e('Orders', 'jesp-erp'); ?></span>
            </div>
            <div class="jesp-dw-box">
                <span class="jesp-dw-num"><?php echo esc_html($total_products); ?></span>
                <span class="jesp-dw-lbl"><?php esc_html_e('Products', 'jesp-erp'); ?></span>
            </div>
            <div class="jesp-dw-box" style="<?php echo esc_attr($warn_bg); ?>">
                <span class="jesp-dw-num" style="<?php echo esc_attr($warn_style); ?>"><?php echo esc_html($low_stock); ?></span>
                <span class="jesp-dw-lbl"><?php esc_html_e('Low Stock', 'jesp-erp'); ?></span>
            </div>
        </div>

        <p class="jesp-dw-section"><?php esc_html_e("Today", 'jesp-erp'); ?></p>
        <div class="jesp-dw-today">
            <div>
                <span class="jesp-dw-today-num"><?php echo esc_html($day_orders); ?></span>
                <span class="jesp-dw-today-lbl"><?php esc_html_e('Orders', 'jesp-erp'); ?></span>
            </div>
            <div>
                <span class="jesp-dw-today-num"><?php echo esc_html($day_revenue); ?></span>
                <span class="jesp-dw-today-lbl"><?php esc_html_e('Revenue', 'jesp-erp'); ?></span>
            </div>
            <div>
                <span class="jesp-dw-today-num"><?php echo esc_html($total_customers); ?></span>
                <span class="jesp-dw-today-lbl"><?php esc_html_e('Customers', 'jesp-erp'); ?></span>
            </div>
        </div>

        <div class="jesp-dw-footer">
            <span class="jesp-dw-ts"><?php echo esc_html(gmdate('M j, Y g:i A')); ?></span>
            <a href="<?php echo esc_url(admin_url('admin.php?page=jesp-erp')); ?>" class="jesp-dw-link">
                <?php esc_html_e('View Full Dashboard', 'jesp-erp'); ?> &rarr;
            </a>
        </div>
        <?php
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
            'erp-manager_page_jesp-erp-hero',
            'erp-manager_page_jesp-erp-invoices',
            'erp-manager_page_jesp-erp-finance',
            'erp-manager_page_jesp-erp-settings',
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

        // Inject saved custom CSS on all plugin pages.
        $custom_css = get_option('jesp_erp_custom_css', '');
        if (!empty($custom_css)) {
            wp_add_inline_style('jesp-erp-admin', $custom_css);
        }

        // Load WordPress code editor (CodeMirror) only on the settings page.
        if ($hook === 'erp-manager_page_jesp-erp-settings') {
            $editor_settings = wp_enqueue_code_editor(array('type' => 'text/css'));
            wp_localize_script('jquery', 'jespErpCodeEditor', $editor_settings);
        }

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
            JESP_ERP_VERSION . '.17',
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
    public function render_hero_products()
    {
        include JESP_ERP_PLUGIN_DIR . 'admin/views/hero-products.php';
    }
    public function render_invoice_maker()
    {
        include JESP_ERP_PLUGIN_DIR . 'admin/views/invoice-maker.php';
    }
    public function render_settings()
    {
        include JESP_ERP_PLUGIN_DIR . 'admin/views/settings.php';
    }
    public function render_finance()
    {
        include JESP_ERP_PLUGIN_DIR . 'admin/views/finance.php';
    }
}
