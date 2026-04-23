<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX handler — all endpoints with nonce verification and capability checks.
 */
class JESP_ERP_Ajax
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
        $actions = array(
            'erp_get_dashboard',
            'erp_get_stock_list',
            'erp_update_stock',
            'erp_update_min_stock',
            'erp_import_csv',
            'erp_export_csv',
            'erp_apply_discount',
            'erp_revert_discount',
            'erp_get_discounts',
            'erp_get_orders',
            'erp_get_order_chart',
            'erp_get_customers',
            'erp_get_customer_orders',
            // New endpoints for v2
            'erp_get_hero_products',
            'erp_get_stock_value',
            'erp_inline_update_product',
            // New endpoints for v3
            'erp_get_low_stock_products',
            'erp_get_order_detail',
            'erp_quick_edit_product',
            'erp_toggle_product_status',
            'erp_dedup_customers',
            // v4: All Orders tab
            'erp_get_all_orders',
            'erp_export_orders',
            // v5: Historical customer sync
            'erp_sync_customers',
        );
        foreach ($actions as $action) {
            add_action("wp_ajax_{$action}", array($this, $action));
        }
    }

    private function verify()
    {
        check_ajax_referer('jesp_erp_nonce', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'jesp-erp')), 403);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Dashboard (updated to accept date range)                          */
    /* ------------------------------------------------------------------ */
    public function erp_get_dashboard()
    {
        $this->verify();

        $date_from = sanitize_text_field($_POST['date_from'] ?? gmdate('Y-m-d', strtotime('-30 days')));
        $date_to = sanitize_text_field($_POST['date_to'] ?? gmdate('Y-m-d'));

        $summary = JESP_ERP_Orders::get_summary($date_from, $date_to);
        $low_stock = JESP_ERP_Stock::get_low_stock_count();

        global $wpdb;
        $total_products = (int)$wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'"
        );
        $total_customers = (int)$wpdb->get_var(
            "SELECT COUNT(*) FROM " . JESP_ERP_Database::customer_purchases_table()
        );

        wp_send_json_success(array(
            'total_orders' => $summary->total_orders ?? 0,
            'total_revenue' => $summary->total_revenue ?? 0,
            'total_products' => $total_products,
            'low_stock_count' => $low_stock,
            'total_customers' => $total_customers,
        ));
    }

    /* ------------------------------------------------------------------ */
    /*  Hero Products (new)                                               */
    /* ------------------------------------------------------------------ */
    public function erp_get_hero_products()
    {
        $this->verify();

        $date_from = sanitize_text_field($_POST['date_from'] ?? gmdate('Y-m-d', strtotime('-30 days')));
        $date_to = sanitize_text_field($_POST['date_to'] ?? gmdate('Y-m-d'));

        $result = JESP_ERP_Orders::get_product_analytics(array(
            'date_from' => $date_from,
            'date_to' => $date_to,
            'per_page' => 10,
            'page' => 1,
        ));

        wp_send_json_success($result);
    }

    /* ------------------------------------------------------------------ */
    /*  Stock Value (new)                                                 */
    /* ------------------------------------------------------------------ */
    public function erp_get_stock_value()
    {
        $this->verify();

        $category = absint($_POST['category'] ?? 0);
        $per_page = absint($_POST['per_page'] ?? 50);
        $page = absint($_POST['page'] ?? 1);

        $result = JESP_ERP_Stock::get_stock_value(array(
            'category' => $category,
            'per_page' => $per_page,
            'page' => $page,
        ));

        wp_send_json_success($result);
    }

    /* ------------------------------------------------------------------ */
    /*  Inline Product Update (new — SKU, prices, stock with WC sync)     */
    /* ------------------------------------------------------------------ */
    public function erp_inline_update_product()
    {
        $this->verify();

        $product_id = absint($_POST['product_id'] ?? 0);
        $field = sanitize_text_field($_POST['field'] ?? '');
        $value = sanitize_text_field($_POST['value'] ?? '');

        if (!$product_id || empty($field)) {
            wp_send_json_error(array('message' => __('Missing parameters.', 'jesp-erp')));
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(array('message' => __('Product not found.', 'jesp-erp')));
        }

        $allowed_fields = array('sku', 'regular_price', 'sale_price', 'warehouse_stock', 'sales_center_stock', 'min_stock');
        if (!in_array($field, $allowed_fields, true)) {
            wp_send_json_error(array('message' => __('Invalid field.', 'jesp-erp')));
        }

        switch ($field) {
            case 'sku':
                $new_sku = sanitize_text_field($value);
                // Validate unique SKU.
                if (!empty($new_sku)) {
                    $existing_id = wc_get_product_id_by_sku($new_sku);
                    if ($existing_id && $existing_id !== $product_id) {
                        wp_send_json_error(array('message' => __('SKU already in use by another product.', 'jesp-erp')));
                    }
                }
                $product->set_sku($new_sku);
                $product->save();
                wp_send_json_success(array('field' => $field, 'value' => $product->get_sku(), 'message' => __('SKU updated.', 'jesp-erp')));
                break;

            case 'regular_price':
                $price = floatval($value);
                if ($price < 0) {
                    wp_send_json_error(array('message' => __('Price cannot be negative.', 'jesp-erp')));
                }
                $product->set_regular_price($price);
                $product->save();
                wp_send_json_success(array('field' => $field, 'value' => $product->get_regular_price(), 'message' => __('Regular price updated.', 'jesp-erp')));
                break;

            case 'sale_price':
                $price = $value === '' ? '' : floatval($value);
                if (is_numeric($price) && $price < 0) {
                    wp_send_json_error(array('message' => __('Price cannot be negative.', 'jesp-erp')));
                }
                $product->set_sale_price($price);
                $product->save();
                wp_send_json_success(array('field' => $field, 'value' => $product->get_sale_price(), 'message' => __('Sale price updated.', 'jesp-erp')));
                break;

            case 'warehouse_stock':
                $qty = intval($value);
                if ($qty < 0) {
                    wp_send_json_error(array('message' => __('Stock cannot be negative.', 'jesp-erp')));
                }
                $result = JESP_ERP_Stock::update_stock($product_id, 'warehouse', $qty, 'Inline edit', 'set');
                wp_send_json_success(array('field' => $field, 'value' => $result['new_qty'], 'message' => __('Warehouse stock updated.', 'jesp-erp')));
                break;

            case 'sales_center_stock':
                $qty = intval($value);
                if ($qty < 0) {
                    wp_send_json_error(array('message' => __('Stock cannot be negative.', 'jesp-erp')));
                }
                $result = JESP_ERP_Stock::update_stock($product_id, 'sales_center', $qty, 'Inline edit', 'set');
                wp_send_json_success(array('field' => $field, 'value' => $result['new_qty'], 'message' => __('Sales center stock updated.', 'jesp-erp')));
                break;

            case 'min_stock':
                $min = absint($value);
                JESP_ERP_Stock::update_min_stock($product_id, 'warehouse', $min);
                wp_send_json_success(array('field' => $field, 'value' => $min, 'message' => __('Min stock updated.', 'jesp-erp')));
                break;
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Stock Management (existing)                                       */
    /* ------------------------------------------------------------------ */
    public function erp_get_stock_list()
    {
        $this->verify();

        $result = JESP_ERP_Stock::get_stock_list(array(
            'per_page' => absint($_POST['per_page'] ?? 20),
            'page' => absint($_POST['page'] ?? 1),
            'search' => sanitize_text_field($_POST['search'] ?? ''),
            'category' => absint($_POST['category'] ?? 0),
            'stock_status' => sanitize_text_field($_POST['stock_status'] ?? ''),
            'orderby' => sanitize_text_field($_POST['orderby'] ?? 'product_name'),
            'order' => sanitize_text_field($_POST['order'] ?? 'ASC'),
        ));

        wp_send_json_success($result);
    }

    public function erp_update_stock()
    {
        $this->verify();

        $product_id = absint($_POST['product_id'] ?? 0);
        $location = sanitize_text_field($_POST['location'] ?? 'warehouse');
        $quantity = intval($_POST['quantity'] ?? 0);
        $mode = sanitize_text_field($_POST['mode'] ?? 'set');
        $reason = sanitize_text_field($_POST['reason'] ?? 'Manual adjustment');

        if (!$product_id) {
            wp_send_json_error(array('message' => __('Invalid product ID.', 'jesp-erp')));
        }

        $result = JESP_ERP_Stock::update_stock($product_id, $location, $quantity, $reason, $mode);
        wp_send_json_success($result);
    }

    public function erp_update_min_stock()
    {
        $this->verify();

        $product_id = absint($_POST['product_id'] ?? 0);
        $location = sanitize_text_field($_POST['location'] ?? 'warehouse');
        $min_stock = absint($_POST['min_stock'] ?? 0);

        if (!$product_id) {
            wp_send_json_error(array('message' => __('Invalid product ID.', 'jesp-erp')));
        }

        JESP_ERP_Stock::update_min_stock($product_id, $location, $min_stock);
        wp_send_json_success(array('message' => __('Minimum stock updated.', 'jesp-erp')));
    }

    /* ------------------------------------------------------------------ */
    /*  Import                                                            */
    /* ------------------------------------------------------------------ */
    public function erp_import_csv()
    {
        $this->verify();

        if (empty($_FILES['csv_file'])) {
            wp_send_json_error(array('message' => __('No file uploaded.', 'jesp-erp')));
        }

        $upload = wp_handle_upload($_FILES['csv_file'], array('test_form' => false, 'mimes' => array('csv' => 'text/csv')));

        if (isset($upload['error'])) {
            $upload = wp_handle_upload($_FILES['csv_file'], array('test_form' => false, 'mimes' => array('csv' => 'text/csv', 'txt' => 'text/plain')));
        }

        if (isset($upload['error'])) {
            wp_send_json_error(array('message' => $upload['error']));
        }

        $result = JESP_ERP_Import::process_csv($upload['file']);
        wp_delete_file($upload['file']);
        wp_send_json_success($result);
    }

    /* ------------------------------------------------------------------ */
    /*  Export                                                            */
    /* ------------------------------------------------------------------ */
    public function erp_export_csv()
    {
        $this->verify();
        JESP_ERP_Export::export_csv(array(
            'category' => absint($_POST['category'] ?? 0),
            'stock_status' => sanitize_text_field($_POST['stock_status'] ?? ''),
        ));
    }

    /* ------------------------------------------------------------------ */
    /*  Discounts                                                        */
    /* ------------------------------------------------------------------ */
    public function erp_apply_discount()
    {
        $this->verify();
        $config = array(
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'discount_type' => sanitize_text_field($_POST['discount_type'] ?? 'percentage'),
            'discount_value' => floatval($_POST['discount_value'] ?? 0),
            'category' => sanitize_text_field($_POST['category'] ?? ''),
            'product_ids' => isset($_POST['product_ids']) ? array_map('absint', (array)$_POST['product_ids']) : array(),
            'min_stock' => (isset($_POST['min_stock']) && $_POST['min_stock'] !== '') ? intval($_POST['min_stock']) : null,
            'max_stock' => (isset($_POST['max_stock']) && $_POST['max_stock'] !== '') ? intval($_POST['max_stock']) : null,
            'start_date' => sanitize_text_field($_POST['start_date'] ?? ''),
            'end_date' => sanitize_text_field($_POST['end_date'] ?? ''),
        );
        $result = JESP_ERP_Discount::apply_discount($config);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        wp_send_json_success($result);
    }

    public function erp_revert_discount()
    {
        $this->verify();
        $result = JESP_ERP_Discount::revert_discount(absint($_POST['discount_id'] ?? 0));
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        wp_send_json_success($result);
    }

    public function erp_get_discounts()
    {
        $this->verify();
        $result = JESP_ERP_Discount::get_discounts(array(
            'per_page' => absint($_POST['per_page'] ?? 20),
            'page' => absint($_POST['page'] ?? 1),
        ));
        wp_send_json_success($result);
    }

    /* ------------------------------------------------------------------ */
    /*  Orders & Analytics                                                */
    /* ------------------------------------------------------------------ */
    public function erp_get_orders()
    {
        $this->verify();
        $result = JESP_ERP_Orders::get_product_analytics(array(
            'date_from' => sanitize_text_field($_POST['date_from'] ?? gmdate('Y-m-d', strtotime('-30 days'))),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? gmdate('Y-m-d')),
            'product_id' => absint($_POST['product_id'] ?? 0),
            'per_page' => absint($_POST['per_page'] ?? 20),
            'page' => absint($_POST['page'] ?? 1),
        ));
        wp_send_json_success($result);
    }

    public function erp_get_order_chart()
    {
        $this->verify();
        $data = JESP_ERP_Orders::get_revenue_chart_data(
            sanitize_text_field($_POST['date_from'] ?? gmdate('Y-m-d', strtotime('-30 days'))),
            sanitize_text_field($_POST['date_to'] ?? gmdate('Y-m-d'))
        );
        wp_send_json_success($data);
    }

    /* ------------------------------------------------------------------ */
    /*  Customers                                                        */
    /* ------------------------------------------------------------------ */
    public function erp_get_customers()
    {
        $this->verify();
        $result = JESP_ERP_Customers::get_customers(array(
            'per_page' => absint($_POST['per_page'] ?? 20),
            'page' => absint($_POST['page'] ?? 1),
            'search' => sanitize_text_field($_POST['search'] ?? ''),
            'min_spent' => floatval($_POST['min_spent'] ?? 0),
            'orderby' => sanitize_text_field($_POST['orderby'] ?? 'total_spent'),
            'order' => sanitize_text_field($_POST['order'] ?? 'DESC'),
        ));
        wp_send_json_success($result);
    }

    public function erp_get_customer_orders()
    {
        $this->verify();
        $customer_id = absint($_POST['customer_id'] ?? 0);
        $customer = JESP_ERP_Customers::get_customer($customer_id);
        if (!$customer) {
            wp_send_json_error(array('message' => __('Customer not found.', 'jesp-erp')));
        }
        $identifier = !empty($customer->phone) ? $customer->phone : $customer->email;
        $type = !empty($customer->phone) ? 'phone' : 'email';
        $orders = JESP_ERP_Customers::get_customer_orders($identifier, $type, array(
            'per_page' => absint($_POST['per_page'] ?? 20),
            'page' => absint($_POST['page'] ?? 1),
        ));
        wp_send_json_success(array('customer' => $customer, 'orders' => $orders));
    }

    /* ------------------------------------------------------------------ */
    /*  v3: Low Stock Widget                                              */
    /* ------------------------------------------------------------------ */
    public function erp_get_low_stock_products()
    {
        $this->verify();
        $limit = absint($_POST['limit'] ?? 5);

        global $wpdb;
        $table = JESP_ERP_Database::stock_locations_table();

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT
                p.ID as product_id,
                p.post_title as product_name,
                COALESCE(SUM(sl.quantity), 0) as total_qty,
                COALESCE(MAX(sl.min_stock), 0) as min_level
            FROM {$wpdb->posts} p
            LEFT JOIN {$table} sl ON p.ID = sl.product_id
            WHERE p.post_type = 'product' AND p.post_status IN ('publish','draft')
            GROUP BY p.ID, p.post_title
            HAVING total_qty <= min_level AND min_level > 0
            ORDER BY total_qty ASC
            LIMIT %d",
            $limit
        ));

        wp_send_json_success(array('items' => $items));
    }

    /* ------------------------------------------------------------------ */
    /*  v3: Order Detail                                                  */
    /* ------------------------------------------------------------------ */
    public function erp_get_order_detail()
    {
        $this->verify();
        $order_id = absint($_POST['order_id'] ?? 0);

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(array('message' => __('Order not found.', 'jesp-erp')));
        }

        $items = array();
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $items[] = array(
                'name' => $item->get_name(),
                'qty' => $item->get_quantity(),
                'subtotal' => $item->get_subtotal(),
                'total' => $item->get_total(),
                'sku' => $product ? $product->get_sku() : '',
            );
        }

        $result = array(
            'order_id' => $order->get_id(),
            'order_date' => $order->get_date_created() ? $order->get_date_created()->format('Y-m-d H:i:s') : '',
            'status' => $order->get_status(),
            'status_label' => wc_get_order_status_name($order->get_status()),
            'total' => $order->get_total(),
            'subtotal' => $order->get_subtotal(),
            'tax_total' => $order->get_total_tax(),
            'shipping' => $order->get_shipping_total(),
            'discount' => $order->get_discount_total(),
            'payment' => $order->get_payment_method_title(),
            'items' => $items,
            'customer' => array(
                'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
                'address' => $order->get_billing_address_1() . ', ' . $order->get_billing_city(),
            ),
        );

        wp_send_json_success($result);
    }

    /* ------------------------------------------------------------------ */
    /*  v3: Quick Edit Product                                            */
    /* ------------------------------------------------------------------ */
    public function erp_quick_edit_product()
    {
        $this->verify();

        $product_id = absint($_POST['product_id'] ?? 0);
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(array('message' => __('Product not found.', 'jesp-erp')));
        }

        // Update title.
        if (isset($_POST['title'])) {
            $title = sanitize_text_field($_POST['title']);
            if (!empty($title)) {
                wp_update_post(array('ID' => $product_id, 'post_title' => $title));
            }
        }

        // Update description.
        if (isset($_POST['description'])) {
            $desc = wp_kses_post($_POST['description']);
            wp_update_post(array('ID' => $product_id, 'post_content' => $desc));
        }

        // Update image.
        if (isset($_POST['image_id'])) {
            $image_id = absint($_POST['image_id']);
            if ($image_id > 0) {
                set_post_thumbnail($product_id, $image_id);
            }
            else {
                delete_post_thumbnail($product_id);
            }
        }

        // Update category.
        if (isset($_POST['category_id'])) {
            $cat_id = absint($_POST['category_id']);
            if ($cat_id > 0) {
                wp_set_object_terms($product_id, array($cat_id), 'product_cat');
            }
        }

        wp_send_json_success(array('message' => __('Product updated.', 'jesp-erp')));
    }

    /* ------------------------------------------------------------------ */
    /*  v3: Toggle Product Status                                         */
    /* ------------------------------------------------------------------ */
    public function erp_toggle_product_status()
    {
        $this->verify();

        $product_id = absint($_POST['product_id'] ?? 0);
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(array('message' => __('Product not found.', 'jesp-erp')));
        }

        $current = get_post_status($product_id);
        $new_status = ('publish' === $current) ? 'draft' : 'publish';

        wp_update_post(array('ID' => $product_id, 'post_status' => $new_status));

        // Clear WC product cache.
        wc_delete_product_transients($product_id);

        wp_send_json_success(array(
            'status' => $new_status,
            'label' => ('publish' === $new_status) ? __('Active', 'jesp-erp') : __('Inactive', 'jesp-erp'),
            'message' => sprintf(__('Product %s.', 'jesp-erp'), ('publish' === $new_status) ? 'activated' : 'deactivated'),
        ));
    }

    /* ------------------------------------------------------------------ */
    /*  v3: Customer Deduplication                                        */
    /* ------------------------------------------------------------------ */
    public function erp_dedup_customers()
    {
        $this->verify();

        $merged = JESP_ERP_Customers::deduplicate_customers();
        wp_send_json_success(array('merged' => $merged, 'message' => sprintf(__('Merged %d duplicate customers.', 'jesp-erp'), $merged)));
    }

    /* ------------------------------------------------------------------ */
    /*  v4: All Orders (individual WC orders list)                        */
    /* ------------------------------------------------------------------ */
    public function erp_get_all_orders()
    {
        $this->verify();

        $per_page = absint($_POST['per_page'] ?? 20);
        $page = max(1, absint($_POST['page'] ?? 1));
        $search = sanitize_text_field($_POST['search'] ?? '');
        $status = sanitize_text_field($_POST['status'] ?? '');
        $date_from = sanitize_text_field($_POST['date_from'] ?? '');
        $date_to = sanitize_text_field($_POST['date_to'] ?? '');

        $args = array(
            'limit' => $per_page,
            'page' => $page,
            'orderby' => 'date',
            'order' => 'DESC',
            'paginate' => true,
        );

        // Status filter.
        if (!empty($status) && $status !== 'all') {
            $args['status'] = $status;
        }
        else {
            $args['status'] = array('wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending', 'wc-cancelled', 'wc-refunded', 'wc-failed');
        }

        // Date range.
        if (!empty($date_from)) {
            $args['date_created'] = $date_from . '...' . ($date_to ?: gmdate('Y-m-d'));
        }

        // Search by order ID or customer name.
        if (!empty($search)) {
            // If search is numeric, search by order ID.
            if (is_numeric($search)) {
                $args['post__in'] = array(absint($search));
            }
            else {
                // Search by billing name — use meta query via wc_get_orders s parameter.
                $args['s'] = $search;
            }
        }

        $results = wc_get_orders($args);

        $items = array();
        foreach ($results->orders as $order) {
            // Build items summary.
            $product_names = array();
            $total_items = 0;
            foreach ($order->get_items() as $item) {
                $product_names[] = $item->get_name();
                $total_items += $item->get_quantity();
            }

            $items[] = array(
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'order_date' => $order->get_date_created() ? $order->get_date_created()->format('Y-m-d H:i:s') : '',
                'status' => $order->get_status(),
                'status_label' => wc_get_order_status_name($order->get_status()),
                'total' => $order->get_total(),
                'subtotal' => $order->get_subtotal(),
                'payment_method' => $order->get_payment_method_title(),
                'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'customer_email' => $order->get_billing_email(),
                'customer_phone' => $order->get_billing_phone(),
                'items_summary' => implode(', ', array_slice($product_names, 0, 3)) . (count($product_names) > 3 ? '...' : ''),
                'items_count' => $total_items,
            );
        }

        wp_send_json_success(array(
            'items' => $items,
            'total' => $results->total,
            'pages' => $results->max_num_pages,
        ));
    }

    /* ------------------------------------------------------------------ */
    /*  v5: Sync Historical Customers                                     */
    /* ------------------------------------------------------------------ */
    public function erp_sync_customers()
    {
        $this->verify();
        $synced = JESP_ERP_Customers::sync_all_historical_orders();
        wp_send_json_success(array(
            'synced'  => $synced,
            'message' => sprintf(
                /* translators: %d = number of customer records synced */
                __( '%d customer records synced from order history.', 'jesp-erp' ),
                $synced
            ),
        ));
    }

    /* ------------------------------------------------------------------ */
    /*  v4: Export Orders CSV                                             */
    /* ------------------------------------------------------------------ */
    public function erp_export_orders()
    {
        $this->verify();

        $status = sanitize_text_field($_POST['status'] ?? '');
        $date_from = sanitize_text_field($_POST['date_from'] ?? '');
        $date_to = sanitize_text_field($_POST['date_to'] ?? '');
        $search = sanitize_text_field($_POST['search'] ?? '');

        $args = array(
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        );

        if (!empty($status) && $status !== 'all') {
            $args['status'] = $status;
        }
        else {
            $args['status'] = array('wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending', 'wc-cancelled', 'wc-refunded', 'wc-failed');
        }

        if (!empty($date_from)) {
            $args['date_created'] = $date_from . '...' . ($date_to ?: gmdate('Y-m-d'));
        }

        if (!empty($search) && is_numeric($search)) {
            $args['post__in'] = array(absint($search));
        }

        $orders = wc_get_orders($args);

        $filename = 'orders-export-' . gmdate('Y-m-d-His') . '.html';

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Build rows
        $rows = '';
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $product   = $item->get_product();
                $sku       = $product ? esc_html($product->get_sku()) : '';
                $name      = esc_html($item->get_name());
                $order_num = esc_html($order->get_order_number());
                $qty       = (int) $item->get_quantity();

                $img_html = '';
                if ($product) {
                    $image_id = $product->get_image_id();
                    if ($image_id) {
                        $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
                        if ($image_url) {
                            $img_html = '<img src="' . esc_url($image_url) . '" width="60" height="60" style="object-fit:cover;border-radius:4px;">';
                        }
                    }
                }

                $rows .= '<tr>'
                    . '<td>' . $sku . '</td>'
                    . '<td style="text-align:center">' . $img_html . '</td>'
                    . '<td>' . $name . '</td>'
                    . '<td style="text-align:center">' . $order_num . '</td>'
                    . '<td style="text-align:center">' . $qty . '</td>'
                    . '</tr>' . "\n";
            }
        }

        echo '<!DOCTYPE html><html><head><meta charset="utf-8">'
            . '<title>Orders Export</title>'
            . '<style>'
            . 'body{font-family:Arial,sans-serif;font-size:13px;padding:20px;}'
            . 'h2{margin-bottom:12px;}'
            . 'table{border-collapse:collapse;width:100%;}'
            . 'th,td{border:1px solid #ccc;padding:8px 12px;vertical-align:middle;}'
            . 'th{background:#f4f4f4;font-weight:bold;}'
            . 'tr:nth-child(even){background:#fafafa;}'
            . '</style></head><body>'
            . '<h2>Orders Export &mdash; ' . esc_html(gmdate('Y-m-d')) . '</h2>'
            . '<table>'
            . '<thead><tr>'
            . '<th>SKU</th><th>Image</th><th>Product Name</th><th>Order #</th><th>Qty</th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table></body></html>';
        exit;
    }
}
