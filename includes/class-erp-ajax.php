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
            'erp_export_orders_pdf',
            'erp_export_orders_csv',
            // v5: Historical customer sync
            'erp_sync_customers',
            // v6: Brand revenue
            'erp_get_brand_revenue',
            // v7: Hero products full list
            'erp_get_hero_products_list',
            // v8: Settings
            'erp_save_settings',
            // v9: Sample CSV download
            'erp_download_sample_csv',
            // v10: Invoice Maker
            'erp_get_invoices',
            'erp_get_invoice',
            'erp_save_invoice',
            'erp_delete_invoice',
            'erp_print_invoice',
            // v11: Finance
            'erp_get_finance_summary',
            'erp_get_expenses',
            'erp_save_expense',
            'erp_delete_expense',
            // v12: Invoice company settings
            'erp_save_invoice_company',
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

        foreach ($result['items'] as &$item) {
            $pid = absint($item->product_id);
            $product = wc_get_product($pid);
            $item->sku = $product ? $product->get_sku() : '';
        }
        unset($item);

        wp_send_json_success($result);
    }

    /* ------------------------------------------------------------------ */
    /*  Hero Products — full paginated list with thumbnails               */
    /* ------------------------------------------------------------------ */
    public function erp_get_hero_products_list()
    {
        $this->verify();

        $result = JESP_ERP_Orders::get_product_analytics(array(
            'date_from' => sanitize_text_field($_POST['date_from'] ?? gmdate('Y-m-d', strtotime('-30 days'))),
            'date_to'   => sanitize_text_field($_POST['date_to']   ?? gmdate('Y-m-d')),
            'search'    => sanitize_text_field($_POST['search']    ?? ''),
            'per_page'  => absint($_POST['per_page'] ?? 20),
            'page'      => absint($_POST['page']     ?? 1),
        ));

        foreach ($result['items'] as &$item) {
            $pid = absint($item->product_id);
            $product = wc_get_product($pid);
            $item->thumbnail_url = get_the_post_thumbnail_url($pid, 'thumbnail') ?: '';
            $item->sku = $product ? $product->get_sku() : '';
        }
        unset($item);

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

        $allowed_fields = array('sku', 'regular_price', 'sale_price', 'buying_price', 'warehouse_stock', 'sales_center_stock', 'min_stock');
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

            case 'buying_price':
                $price = $value === '' ? '' : floatval($value);
                if (is_numeric($price) && $price < 0) {
                    wp_send_json_error(array('message' => __('Buying price cannot be negative.', 'jesp-erp')));
                }
                if ($price === '' || $price == 0) {
                    delete_post_meta($product_id, '_jesp_buying_price');
                    wp_send_json_success(array('field' => $field, 'value' => '', 'message' => __('Buying price cleared.', 'jesp-erp')));
                } else {
                    update_post_meta($product_id, '_jesp_buying_price', (string)$price);
                    wp_send_json_success(array('field' => $field, 'value' => (string)$price, 'message' => __('Buying price updated.', 'jesp-erp')));
                }
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
        // Export is triggered via GET (window.location redirect), so use $_REQUEST.
        $fields_str = sanitize_text_field( $_REQUEST['fields'] ?? '' );
        $fields     = $fields_str !== '' ? explode( ',', $fields_str ) : array();
        JESP_ERP_Export::export_csv( array(
            'category'     => absint( $_REQUEST['category'] ?? 0 ),
            'stock_status' => sanitize_text_field( $_REQUEST['stock_status'] ?? '' ),
            'fields'       => $fields,
        ) );
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

        // Append computed AOV to each customer row.
        foreach ($result['items'] as &$customer) {
            $count = (int) $customer->order_count;
            $customer->aov = $count > 0 ? round( (float) $customer->total_spent / $count, 2 ) : 0;
        }
        unset($customer);

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

        // Compute average order value from existing fields — no extra query needed.
        $order_count = (int) $customer->order_count;
        $customer->aov = $order_count > 0 ? round( (float) $customer->total_spent / $order_count, 2 ) : 0;

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

    public function erp_export_orders_pdf()
    {
        $this->verify();

        $status    = sanitize_text_field($_POST['status'] ?? '');
        $date_from = sanitize_text_field($_POST['date_from'] ?? '');
        $date_to   = sanitize_text_field($_POST['date_to'] ?? '');
        $search    = sanitize_text_field($_POST['search'] ?? '');

        $args = array(
            'limit'   => -1,
            'orderby' => 'date',
            'order'   => 'DESC',
        );

        if (!empty($status) && $status !== 'all') {
            $args['status'] = $status;
        } else {
            $args['status'] = array('wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending', 'wc-cancelled', 'wc-refunded', 'wc-failed');
        }

        if (!empty($date_from)) {
            $args['date_created'] = $date_from . '...' . ($date_to ?: gmdate('Y-m-d'));
        }

        if (!empty($search) && is_numeric($search)) {
            $args['post__in'] = array(absint($search));
        }

        $orders = wc_get_orders($args);

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
                            $img_html = '<img src="' . esc_url($image_url) . '" width="50" height="50" style="object-fit:cover;border-radius:3px;">';
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

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/html; charset=utf-8');

        echo '<!DOCTYPE html><html><head><meta charset="utf-8">'
            . '<title>Orders PDF &mdash; ' . esc_html(gmdate('Y-m-d')) . '</title>'
            . '<style>'
            . 'body{font-family:Arial,sans-serif;font-size:12px;margin:20px;}'
            . 'h2{font-size:16px;margin-bottom:10px;}'
            . '.meta{color:#555;font-size:11px;margin-bottom:16px;}'
            . 'table{border-collapse:collapse;width:100%;page-break-inside:auto;}'
            . 'thead{display:table-header-group;}'
            . 'tr{page-break-inside:avoid;page-break-after:auto;}'
            . 'th,td{border:1px solid #bbb;padding:6px 10px;vertical-align:middle;}'
            . 'th{background:#eef2f7;font-weight:bold;font-size:11px;text-transform:uppercase;letter-spacing:.5px;}'
            . 'tr:nth-child(even){background:#f9f9f9;}'
            . '@media print{'
            . 'body{margin:0;}'
            . '.no-print{display:none;}'
            . '}'
            . '</style></head><body>'
            . '<div class="no-print" style="margin-bottom:16px;">'
            . '<button onclick="window.print()" style="padding:8px 18px;font-size:13px;cursor:pointer;background:#2271b1;color:#fff;border:none;border-radius:4px;">Print / Save as PDF</button>'
            . '</div>'
            . '<h2>Orders Report</h2>'
            . '<p class="meta">Generated: ' . esc_html(gmdate('Y-m-d H:i')) . '</p>'
            . '<table>'
            . '<thead><tr>'
            . '<th>SKU</th><th>Image</th><th>Product Name</th><th>Order #</th><th>Qty</th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table>'
            . '<script>window.onload=function(){window.print();};</script>'
            . '</body></html>';
        exit;
    }

    public function erp_export_orders_csv()
    {
        $this->verify();

        $status    = sanitize_text_field($_POST['status'] ?? '');
        $date_from = sanitize_text_field($_POST['date_from'] ?? '');
        $date_to   = sanitize_text_field($_POST['date_to'] ?? '');
        $search    = sanitize_text_field($_POST['search'] ?? '');

        $args = array(
            'limit'   => -1,
            'orderby' => 'date',
            'order'   => 'DESC',
        );

        if (!empty($status) && $status !== 'all') {
            $args['status'] = $status;
        } else {
            $args['status'] = array('wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending', 'wc-cancelled', 'wc-refunded', 'wc-failed');
        }

        if (!empty($date_from)) {
            $args['date_created'] = $date_from . '...' . ($date_to ?: gmdate('Y-m-d'));
        }

        if (!empty($search) && is_numeric($search)) {
            $args['post__in'] = array(absint($search));
        }

        $orders = wc_get_orders($args);

        $filename = 'orders-' . gmdate('Y-m-d-His') . '.csv';

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, array('SKU', 'Product Name', 'Order Number', 'Quantity'));

        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                fputcsv($output, array(
                    $product ? $product->get_sku() : '',
                    $item->get_name(),
                    $order->get_order_number(),
                    $item->get_quantity(),
                ));
            }
        }

        fclose($output);
        exit;
    }

    /* ------------------------------------------------------------------ */
    /*  v8: Settings — save custom CSS                                    */
    /* ------------------------------------------------------------------ */
    public function erp_save_settings()
    {
        $this->verify();

        // Save custom CSS — strip all HTML tags then block any style-tag breakout (case-insensitive).
        $css = wp_strip_all_tags(wp_unslash($_POST['custom_css'] ?? ''));
        $css = preg_replace('/<\/?style\b[^>]*>/i', '', $css);
        update_option('jesp_erp_custom_css', $css);

        // Save tab visibility.
        $allowed = array('jesp-erp-stock', 'jesp-erp-import', 'jesp-erp-export', 'jesp-erp-discounts', 'jesp-erp-orders', 'jesp-erp-customers', 'jesp-erp-hero', 'jesp-erp-invoices', 'jesp-erp-finance');
        $raw     = isset($_POST['hidden_tabs']) && is_array($_POST['hidden_tabs']) ? $_POST['hidden_tabs'] : array();
        $hidden  = array_values(array_intersect(array_map('sanitize_text_field', $raw), $allowed));
        update_option('jesp_erp_hidden_tabs', $hidden);

        wp_send_json_success(array('message' => __('Settings saved.', 'jesp-erp')));
    }

    /* ------------------------------------------------------------------ */
    /*  Brand Revenue                                                       */
    /* ------------------------------------------------------------------ */

    /* ------------------------------------------------------------------ */
    /*  v9: Sample CSV download                                           */
    /* ------------------------------------------------------------------ */
    public function erp_download_sample_csv()
    {
        $this->verify();

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sample-import.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, array('Product Name', 'SKU', 'Description', 'Price', 'Current Stock', 'Minimum Stock Level', 'Stock Location', 'Image URL'));
        fputcsv($output, array('Widget A',     'WDG-001', 'A premium quality widget',         '29.99', '150', '20', 'Warehouse',    ''));
        fputcsv($output, array('Widget B',     'WDG-002', 'Economy widget for daily use',      '14.99', '75',  '10', 'Sales Center', ''));
        fputcsv($output, array('Gadget Pro',   'GDG-001', 'Advanced gadget with Bluetooth',    '89.99', '30',  '15', 'Warehouse',    ''));
        fputcsv($output, array('Gadget Mini',  'GDG-002', 'Compact gadget for travel',         '49.99', '5',   '10', 'Warehouse',    ''));
        fputcsv($output, array('Accessory Pack','ACC-001','Universal accessory bundle',         '19.99', '200', '25', 'Sales Center', ''));

        fclose($output);
        exit;
    }

    /* ------------------------------------------------------------------ */
    /*  v10: Invoice Maker                                                 */
    /* ------------------------------------------------------------------ */
    public function erp_get_invoices()
    {
        $this->verify();

        $result = JESP_ERP_Invoices::get_invoices(array(
            'per_page' => absint($_POST['per_page'] ?? 20),
            'page'     => absint($_POST['page']     ?? 1),
            'search'   => sanitize_text_field($_POST['search'] ?? ''),
            'status'   => sanitize_text_field($_POST['status'] ?? ''),
        ));

        wp_send_json_success($result);
    }

    public function erp_get_invoice()
    {
        $this->verify();

        $id      = absint($_POST['id'] ?? 0);
        $invoice = JESP_ERP_Invoices::get_invoice($id);

        if (!$invoice) {
            wp_send_json_error(array('message' => __('Invoice not found.', 'jesp-erp')));
        }

        wp_send_json_success($invoice);
    }

    public function erp_save_invoice()
    {
        $this->verify();

        $data = array(
            'id'               => absint($_POST['id']               ?? 0),
            'invoice_number'   => sanitize_text_field($_POST['invoice_number']   ?? ''),
            'customer_name'    => sanitize_text_field($_POST['customer_name']    ?? ''),
            'customer_phone'   => sanitize_text_field($_POST['customer_phone']   ?? ''),
            'customer_email'   => sanitize_email($_POST['customer_email']        ?? ''),
            'customer_address' => sanitize_textarea_field($_POST['customer_address'] ?? ''),
            'invoice_date'     => sanitize_text_field($_POST['invoice_date']     ?? gmdate('Y-m-d')),
            'subtotal'         => floatval($_POST['subtotal']      ?? 0),
            'discount_type'    => sanitize_text_field($_POST['discount_type']    ?? 'none'),
            'discount_value'   => floatval($_POST['discount_value'] ?? 0),
            'tax_rate'         => floatval($_POST['tax_rate']      ?? 0),
            'total'            => floatval($_POST['total']         ?? 0),
            'notes'            => sanitize_textarea_field($_POST['notes']        ?? ''),
            'status'           => sanitize_text_field($_POST['status']           ?? 'draft'),
        );

        $raw_items = isset($_POST['items']) ? json_decode(wp_unslash($_POST['items']), true) : array();
        $items = array();
        if (is_array($raw_items)) {
            foreach ($raw_items as $item) {
                $items[] = array(
                    'product_name' => sanitize_text_field($item['product_name'] ?? ''),
                    'sku'          => sanitize_text_field($item['sku']          ?? ''),
                    'qty'          => floatval($item['qty']        ?? 1),
                    'unit_price'   => floatval($item['unit_price'] ?? 0),
                );
            }
        }

        $id = JESP_ERP_Invoices::save_invoice($data, $items);

        if (!$id) {
            wp_send_json_error(array('message' => __('Could not save invoice.', 'jesp-erp')));
        }

        wp_send_json_success(array('id' => $id, 'message' => __('Invoice saved.', 'jesp-erp')));
    }

    public function erp_delete_invoice()
    {
        $this->verify();

        $id = absint($_POST['id'] ?? 0);
        JESP_ERP_Invoices::delete_invoice($id);

        wp_send_json_success(array('message' => __('Invoice deleted.', 'jesp-erp')));
    }

    public function erp_print_invoice()
    {
        check_ajax_referer('jesp_erp_nonce', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Insufficient permissions.');
        }

        $id      = absint($_REQUEST['id'] ?? 0);
        $invoice = JESP_ERP_Invoices::get_invoice($id);

        if (!$invoice) {
            wp_die('Invoice not found.');
        }

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/html; charset=utf-8');
        echo JESP_ERP_Invoices::generate_print_html($invoice); // phpcs:ignore WordPress.Security.EscapeOutput
        exit;
    }

    private function detect_brand_taxonomy()
    {
        $candidates = array('product_brand', 'pwb-brand', 'yith_product_brand', 'pa_brand');
        foreach ($candidates as $tax) {
            if (taxonomy_exists($tax)) {
                return $tax;
            }
        }
        return '';
    }

    public function erp_get_brand_revenue()
    {
        $this->verify();

        $date_from = sanitize_text_field($_POST['date_from'] ?? '');
        $date_to   = sanitize_text_field($_POST['date_to'] ?? '');

        $args = array(
            'limit'   => -1,
            'orderby' => 'date',
            'order'   => 'DESC',
            'status'  => array('wc-completed', 'wc-processing', 'wc-on-hold'),
        );

        if (!empty($date_from)) {
            $args['date_created'] = $date_from . '...' . ($date_to ?: gmdate('Y-m-d'));
        }

        $orders       = wc_get_orders($args);
        $brand_tax    = $this->detect_brand_taxonomy();
        $brand_data   = array();
        $no_brand     = array('revenue' => 0.0, 'order_ids' => array());
        $terms_cache  = array(); // Per-product cache — eliminates N+1 wp_get_post_terms calls.

        foreach ($orders as $order) {
            $order_id = $order->get_id();
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                $line_total = (float) $item->get_total();
                $assigned   = false;

                if ($brand_tax) {
                    if (!array_key_exists($product_id, $terms_cache)) {
                        $terms_cache[$product_id] = wp_get_post_terms($product_id, $brand_tax, array('fields' => 'all'));
                    }
                    $terms = $terms_cache[$product_id];
                    if (!is_wp_error($terms) && !empty($terms)) {
                        foreach ($terms as $term) {
                            $key = $term->term_id;
                            if (!isset($brand_data[$key])) {
                                $brand_data[$key] = array('name' => $term->name, 'revenue' => 0.0, 'order_ids' => array());
                            }
                            $brand_data[$key]['revenue'] += $line_total;
                            $brand_data[$key]['order_ids'][$order_id] = true;
                            $assigned = true;
                        }
                    }
                }

                if (!$assigned) {
                    $no_brand['revenue'] += $line_total;
                    $no_brand['order_ids'][$order_id] = true;
                }
            }
        }

        $items        = array();
        $total_revenue = 0.0;

        foreach ($brand_data as $entry) {
            $rev            = round($entry['revenue'], 2);
            $total_revenue += $rev;
            $items[]        = array(
                'brand'       => $entry['name'],
                'order_count' => count($entry['order_ids']),
                'revenue'     => $rev,
            );
        }

        // Sort by revenue descending.
        usort($items, function ($a, $b) { return $b['revenue'] <=> $a['revenue']; });

        // Append unbranded row if any.
        if ($no_brand['revenue'] > 0 || !empty($no_brand['order_ids'])) {
            $rev            = round($no_brand['revenue'], 2);
            $total_revenue += $rev;
            $items[]        = array(
                'brand'       => 'Unbranded',
                'order_count' => count($no_brand['order_ids']),
                'revenue'     => $rev,
            );
        }

        wp_send_json_success(array(
            'items'         => $items,
            'total_revenue' => round($total_revenue, 2),
            'brand_tax'     => $brand_tax ?: 'none',
        ));
    }
    /* ------------------------------------------------------------------ */
    /*  v11: Finance                                                       */
    /* ------------------------------------------------------------------ */
    public function erp_get_finance_summary()
    {
        $this->verify();

        $date_from = sanitize_text_field($_POST['date_from'] ?? gmdate('Y-m-d', strtotime('-30 days')));
        $date_to   = sanitize_text_field($_POST['date_to']   ?? gmdate('Y-m-d'));

        $summary         = JESP_ERP_Finance::get_finance_summary($date_from, $date_to);
        $payment_methods = JESP_ERP_Finance::get_payment_methods($date_from, $date_to);
        $daily_revenue   = JESP_ERP_Finance::get_daily_revenue($date_from, $date_to);

        wp_send_json_success(array(
            'summary'         => $summary,
            'payment_methods' => $payment_methods,
            'daily_revenue'   => $daily_revenue,
        ));
    }

    public function erp_get_expenses()
    {
        $this->verify();

        $result = JESP_ERP_Finance::get_expenses(array(
            'date_from' => sanitize_text_field($_POST['date_from'] ?? gmdate('Y-m-d', strtotime('-30 days'))),
            'date_to'   => sanitize_text_field($_POST['date_to']   ?? gmdate('Y-m-d')),
            'category'  => sanitize_text_field($_POST['category']  ?? ''),
            'per_page'  => absint($_POST['per_page'] ?? 20),
            'page'      => absint($_POST['page']     ?? 1),
        ));

        wp_send_json_success($result);
    }

    public function erp_save_expense()
    {
        $this->verify();

        $result = JESP_ERP_Finance::save_expense(array(
            'id'           => absint($_POST['id'] ?? 0),
            'title'        => sanitize_text_field($_POST['title'] ?? ''),
            'amount'       => floatval($_POST['amount'] ?? 0),
            'category'     => sanitize_text_field($_POST['category'] ?? ''),
            'expense_date' => sanitize_text_field($_POST['expense_date'] ?? gmdate('Y-m-d')),
            'notes'        => sanitize_textarea_field($_POST['notes'] ?? ''),
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        wp_send_json_success($result);
    }

    public function erp_delete_expense()
    {
        $this->verify();

        $id = absint($_POST['id'] ?? 0);
        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid expense ID.', 'jesp-erp')));
        }

        $result = JESP_ERP_Finance::delete_expense($id);
        wp_send_json_success($result);
    }

    /* ------------------------------------------------------------------ */
    /*  v12: Invoice company info save                                     */
    /* ------------------------------------------------------------------ */
    public function erp_save_invoice_company()
    {
        $this->verify();

        $data = array(
            'name'    => sanitize_text_field( wp_unslash( $_POST['name']    ?? '' ) ),
            'address' => sanitize_textarea_field( wp_unslash( $_POST['address'] ?? '' ) ),
            'phone'   => sanitize_text_field( wp_unslash( $_POST['phone']   ?? '' ) ),
            'email'   => sanitize_email( wp_unslash( $_POST['email']        ?? '' ) ),
            'logo_url'=> esc_url_raw( wp_unslash( $_POST['logo_url']        ?? '' ) ),
            'footer'  => sanitize_text_field( wp_unslash( $_POST['footer']  ?? '' ) ),
            'terms'   => sanitize_textarea_field( wp_unslash( $_POST['terms'] ?? '' ) ),
        );

        update_option( 'jesp_erp_invoice_company', $data );
        wp_send_json_success( array( 'message' => __( 'Company info saved.', 'jesp-erp' ) ) );
    }
}
