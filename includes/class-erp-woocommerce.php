<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * WooCommerce integration — hooks for order and stock events,
 * and custom inventory fields on the product edit screen.
 */
class JESP_ERP_WooCommerce {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Order completed — deduct stock from sales center, sync customer.
        add_action( 'woocommerce_order_status_completed', array( $this, 'on_order_completed' ), 10, 1 );
        add_action( 'woocommerce_order_status_processing', array( $this, 'on_order_completed' ), 10, 1 );

        // Order refunded — restore stock.
        add_action( 'woocommerce_order_status_refunded', array( $this, 'on_order_refunded' ), 10, 1 );
        add_action( 'woocommerce_order_status_cancelled', array( $this, 'on_order_refunded' ), 10, 1 );

        // New order placed — sync customer data.
        add_action( 'woocommerce_new_order', array( $this, 'on_new_order' ), 10, 1 );

        // Custom stock fields on WooCommerce product Inventory tab.
        add_action( 'woocommerce_product_options_inventory_product_data', array( $this, 'add_stock_fields' ) );
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_stock_fields' ), 10, 1 );
    }

    /* ------------------------------------------------------------------ */
    /*  Custom Inventory Fields on Product Edit Page                      */
    /* ------------------------------------------------------------------ */

    /**
     * Add Warehouse / Sales Center quantity + min stock fields
     * to the WooCommerce product Inventory tab.
     */
    public function add_stock_fields() {
        global $post, $wpdb;
        $product_id = $post->ID;
        $table      = JESP_ERP_Database::stock_locations_table();

        // Get current ERP stock values.
        $warehouse = $wpdb->get_row( $wpdb->prepare(
            "SELECT quantity, min_stock FROM {$table} WHERE product_id = %d AND location_type = 'warehouse' AND location_name = ''",
            $product_id
        ) );
        $sales_center = $wpdb->get_row( $wpdb->prepare(
            "SELECT quantity, min_stock FROM {$table} WHERE product_id = %d AND location_type = 'sales_center' AND location_name = ''",
            $product_id
        ) );

        $wh_qty  = $warehouse ? (int) $warehouse->quantity : 0;
        $wh_min  = $warehouse ? (int) $warehouse->min_stock : 0;
        $sc_qty  = $sales_center ? (int) $sales_center->quantity : 0;
        $sc_min  = $sales_center ? (int) $sales_center->min_stock : 0;
        $total   = $wh_qty + $sc_qty;

        echo '<div class="options_group" style="border-top:1px solid #eee;padding-top:12px;">';
        echo '<h4 style="padding:0 12px;margin:0 0 8px;color:#6366f1;font-size:13px;">'
            . '<span class="dashicons dashicons-building" style="font-size:16px;margin-right:4px;vertical-align:text-bottom;color:#6366f1;"></span>'
            . esc_html__( 'ERP Stock Locations', 'jesp-erp' )
            . '<span style="font-weight:400;color:#64748b;margin-left:8px;">('
            . sprintf( esc_html__( 'Total: %d', 'jesp-erp' ), $total )
            . ')</span></h4>';

        woocommerce_wp_text_input( array(
            'id'                => '_jesp_warehouse_qty',
            'label'             => __( 'Warehouse Quantity', 'jesp-erp' ),
            'desc_tip'          => true,
            'description'       => __( 'Stock quantity at the Warehouse location.', 'jesp-erp' ),
            'type'              => 'number',
            'custom_attributes' => array( 'min' => 0, 'step' => 1 ),
            'value'             => $wh_qty,
        ) );

        woocommerce_wp_text_input( array(
            'id'                => '_jesp_sales_center_qty',
            'label'             => __( 'Sales Center Quantity', 'jesp-erp' ),
            'desc_tip'          => true,
            'description'       => __( 'Stock quantity at the Sales Center location.', 'jesp-erp' ),
            'type'              => 'number',
            'custom_attributes' => array( 'min' => 0, 'step' => 1 ),
            'value'             => $sc_qty,
        ) );

        woocommerce_wp_text_input( array(
            'id'                => '_jesp_min_stock',
            'label'             => __( 'Minimum Stock Level', 'jesp-erp' ),
            'desc_tip'          => true,
            'description'       => __( 'Alert threshold — when total stock falls at or below this level, the product will be marked as Low Stock.', 'jesp-erp' ),
            'type'              => 'number',
            'custom_attributes' => array( 'min' => 0, 'step' => 1 ),
            'value'             => max( $wh_min, $sc_min ),
        ) );

        echo '</div>';
    }

    /**
     * Save the custom Warehouse / Sales Center stock fields
     * when the product is saved. Syncs to ERP stock tables and WooCommerce.
     */
    public function save_stock_fields( $product_id ) {
        // Only process on actual product save, not auto-drafts.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $product_id ) ) {
            return;
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return;
        }

        // Get the submitted values. Use null coalescing — if not posted, ignore.
        if ( ! isset( $_POST['_jesp_warehouse_qty'] ) && ! isset( $_POST['_jesp_sales_center_qty'] ) ) {
            return;
        }

        $wh_qty  = isset( $_POST['_jesp_warehouse_qty'] ) ? absint( $_POST['_jesp_warehouse_qty'] ) : 0;
        $sc_qty  = isset( $_POST['_jesp_sales_center_qty'] ) ? absint( $_POST['_jesp_sales_center_qty'] ) : 0;
        $min_stk = isset( $_POST['_jesp_min_stock'] ) ? absint( $_POST['_jesp_min_stock'] ) : 0;

        // Update ERP stock locations.
        JESP_ERP_Stock::update_stock( $product_id, 'warehouse', $wh_qty, 'Product page save', 'set' );
        JESP_ERP_Stock::update_stock( $product_id, 'sales_center', $sc_qty, 'Product page save', 'set' );

        // Update min stock on both locations.
        JESP_ERP_Stock::update_min_stock( $product_id, 'warehouse', $min_stk );
        JESP_ERP_Stock::update_min_stock( $product_id, 'sales_center', $min_stk );

        // sync_wc_stock is called inside update_stock, so WooCommerce total is already synced.
    }

    /* ------------------------------------------------------------------ */
    /*  Order Events                                                     */
    /* ------------------------------------------------------------------ */

    /**
     * When an order is completed/processing, deduct from sales_center stock.
     */
    public function on_order_completed( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // Prevent double processing.
        if ( $order->get_meta( '_jesp_erp_stock_deducted' ) === 'yes' ) {
            return;
        }

        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            $qty        = $item->get_quantity();

            if ( $product_id && $qty > 0 ) {
                JESP_ERP_Stock::update_stock(
                    $product_id,
                    'sales_center',
                    $qty,
                    sprintf( 'Order #%d completed', $order_id ),
                    'subtract'
                );
            }
        }

        $order->update_meta_data( '_jesp_erp_stock_deducted', 'yes' );
        $order->save();

        // Sync customer data.
        JESP_ERP_Customers::sync_from_order( $order_id );
    }

    /**
     * When an order is refunded/cancelled, restore stock to sales_center.
     */
    public function on_order_refunded( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        if ( $order->get_meta( '_jesp_erp_stock_deducted' ) !== 'yes' ) {
            return;
        }

        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            $qty        = $item->get_quantity();

            if ( $product_id && $qty > 0 ) {
                JESP_ERP_Stock::update_stock(
                    $product_id,
                    'sales_center',
                    $qty,
                    sprintf( 'Order #%d refunded/cancelled', $order_id ),
                    'add'
                );
            }
        }

        $order->update_meta_data( '_jesp_erp_stock_deducted', 'no' );
        $order->save();
    }

    /**
     * On new order — sync customer data.
     */
    public function on_new_order( $order_id ) {
        JESP_ERP_Customers::sync_from_order( $order_id );
    }
}
