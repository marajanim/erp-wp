<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Stock management logic — query, update, and track stock by location.
 */
class JESP_ERP_Stock
{

    /**
     * Get paginated stock list joined with WooCommerce product data.
     * Now includes regular_price, sale_price for inline editing.
     */
    public static function get_stock_list($args = array())
    {
        global $wpdb;

        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'search' => '',
            'category' => 0,
            'stock_status' => '',
            'orderby' => 'p.post_title',
            'order' => 'ASC',
        );
        $args = wp_parse_args($args, $defaults);
        $table = JESP_ERP_Database::stock_locations_table();
        $per_page = absint($args['per_page']);
        $page = max(1, absint($args['page']));
        $offset = ($page - 1) * $per_page;

        $where = "p.post_type = 'product' AND p.post_status IN ('publish','draft')";

        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where .= $wpdb->prepare(" AND (p.post_title LIKE %s OR pm_sku.meta_value LIKE %s)", $like, $like);
        }

        if (!empty($args['category'])) {
            $cat_id = absint($args['category']);
            $where .= " AND tt.term_id = {$cat_id}";
        }

        $join_category = '';
        if (!empty($args['category'])) {
            $join_category = "
                INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'product_cat'
            ";
        }

        $having = '';
        if ('low' === $args['stock_status']) {
            $having = 'HAVING total_qty <= min_level AND min_level > 0';
        }
        elseif ('sufficient' === $args['stock_status']) {
            $having = 'HAVING total_qty > min_level OR min_level = 0';
        }

        $allowed_order = in_array(strtoupper($args['order']), array('ASC', 'DESC'), true) ? strtoupper($args['order']) : 'ASC';
        $allowed_orderby_map = array(
            'product_name' => 'p.post_title',
            'sku' => 'pm_sku.meta_value',
            'total_qty' => 'total_qty',
            'warehouse' => 'warehouse_qty',
            'sales_center' => 'sales_center_qty',
        );
        $orderby_col = isset($allowed_orderby_map[$args['orderby']]) ? $allowed_orderby_map[$args['orderby']] : 'p.post_title';

        $count_sql = "
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
            {$join_category}
            WHERE {$where}
        ";

        $sql = "
            SELECT
                p.ID as product_id,
                p.post_title as product_name,
                p.post_status as product_status,
                pm_sku.meta_value as sku,
                pm_price.meta_value as price,
                pm_reg.meta_value as regular_price,
                pm_sale.meta_value as sale_price,
                pm_img.meta_value as thumbnail_id,
                pm_buying.meta_value as buying_price,
                COALESCE(SUM(CASE WHEN sl.location_type = 'warehouse' THEN sl.quantity ELSE 0 END), 0) as warehouse_qty,
                COALESCE(SUM(CASE WHEN sl.location_type = 'sales_center' THEN sl.quantity ELSE 0 END), 0) as sales_center_qty,
                COALESCE(SUM(sl.quantity), 0) as total_qty,
                COALESCE(MAX(sl.min_stock), 0) as min_level
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
            LEFT JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
            LEFT JOIN {$wpdb->postmeta} pm_reg ON p.ID = pm_reg.post_id AND pm_reg.meta_key = '_regular_price'
            LEFT JOIN {$wpdb->postmeta} pm_sale ON p.ID = pm_sale.post_id AND pm_sale.meta_key = '_sale_price'
            LEFT JOIN {$wpdb->postmeta} pm_img ON p.ID = pm_img.post_id AND pm_img.meta_key = '_thumbnail_id'
            LEFT JOIN {$wpdb->postmeta} pm_buying ON p.ID = pm_buying.post_id AND pm_buying.meta_key = '_jesp_buying_price'
            LEFT JOIN {$table} sl ON p.ID = sl.product_id
            {$join_category}
            WHERE {$where}
            GROUP BY p.ID, p.post_title, p.post_status, pm_sku.meta_value, pm_price.meta_value, pm_reg.meta_value, pm_sale.meta_value, pm_img.meta_value, pm_buying.meta_value
            {$having}
            ORDER BY {$orderby_col} {$allowed_order}
            LIMIT %d OFFSET %d
        ";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $items = $wpdb->get_results($wpdb->prepare($sql, $per_page, $offset));

        if (!empty($having)) {
            $total_sql = "
                SELECT COUNT(*) FROM (
                    SELECT p.ID,
                        COALESCE(SUM(sl.quantity), 0) as total_qty,
                        COALESCE(MAX(sl.min_stock), 0) as min_level
                    FROM {$wpdb->posts} p
                    LEFT JOIN {$table} sl ON p.ID = sl.product_id
                    LEFT JOIN {$wpdb->postmeta} pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
                    {$join_category}
                    WHERE {$where}
                    GROUP BY p.ID
                    {$having}
                ) sub
            ";
            $total = (int)$wpdb->get_var($total_sql); // phpcs:ignore
        }
        else {
            $total = (int)$wpdb->get_var($count_sql); // phpcs:ignore
        }

        // Enrich items with thumbnail URLs.
        foreach ($items as &$item) {
            $item->thumbnail_url = '';
            if (!empty($item->thumbnail_id)) {
                $img = wp_get_attachment_image_url((int)$item->thumbnail_id, 'thumbnail');
                $item->thumbnail_url = $img ? $img : '';
            }
        }

        return array(
            'items' => $items,
            'total' => $total,
            'pages' => (int)ceil($total / max(1, $per_page)),
        );
    }

    /**
     * Get stock value per product with category filter.
     * Returns items with stock * price calculations and totals.
     */
    public static function get_stock_value($args = array())
    {
        global $wpdb;

        $defaults = array(
            'category' => 0,
            'per_page' => 50,
            'page' => 1,
        );
        $args = wp_parse_args($args, $defaults);
        $table = JESP_ERP_Database::stock_locations_table();
        $per_page = absint($args['per_page']);
        $page = max(1, absint($args['page']));
        $offset = ($page - 1) * $per_page;

        $where = "p.post_type = 'product' AND p.post_status = 'publish'";
        $join_category = '';

        if (!empty($args['category'])) {
            $cat_id = absint($args['category']);
            $where .= " AND tt.term_id = {$cat_id}";
            $join_category = "
                INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'product_cat'
            ";
        }

        $sql = "
            SELECT
                p.ID as product_id,
                p.post_title as product_name,
                pm_sku.meta_value as sku,
                COALESCE(pm_price.meta_value, 0) as price,
                COALESCE(SUM(CASE WHEN sl.location_type = 'warehouse' THEN sl.quantity ELSE 0 END), 0) as warehouse_qty,
                COALESCE(SUM(CASE WHEN sl.location_type = 'sales_center' THEN sl.quantity ELSE 0 END), 0) as sales_center_qty,
                COALESCE(SUM(sl.quantity), 0) as total_qty,
                COALESCE(MAX(sl.min_stock), 0) as min_level,
                COALESCE(SUM(sl.quantity), 0) * COALESCE(pm_price.meta_value, 0) as total_value,
                COALESCE(SUM(CASE WHEN sl.location_type = 'warehouse' THEN sl.quantity ELSE 0 END), 0) * COALESCE(pm_price.meta_value, 0) as warehouse_value,
                COALESCE(SUM(CASE WHEN sl.location_type = 'sales_center' THEN sl.quantity ELSE 0 END), 0) * COALESCE(pm_price.meta_value, 0) as sales_center_value
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
            LEFT JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
            LEFT JOIN {$table} sl ON p.ID = sl.product_id
            {$join_category}
            WHERE {$where}
            GROUP BY p.ID, p.post_title, pm_sku.meta_value, pm_price.meta_value
            ORDER BY total_value DESC
            LIMIT %d OFFSET %d
        ";

        $items = $wpdb->get_results($wpdb->prepare($sql, $per_page, $offset)); // phpcs:ignore

        // Summary totals.
        $summary_sql = "
            SELECT
                COALESCE(SUM(sub.total_value), 0) as grand_total,
                COALESCE(SUM(sub.warehouse_value), 0) as warehouse_total,
                COALESCE(SUM(sub.sales_center_value), 0) as sales_center_total
            FROM (
                SELECT
                    COALESCE(SUM(sl.quantity), 0) * COALESCE(pm_price.meta_value, 0) as total_value,
                    COALESCE(SUM(CASE WHEN sl.location_type = 'warehouse' THEN sl.quantity ELSE 0 END), 0) * COALESCE(pm_price.meta_value, 0) as warehouse_value,
                    COALESCE(SUM(CASE WHEN sl.location_type = 'sales_center' THEN sl.quantity ELSE 0 END), 0) * COALESCE(pm_price.meta_value, 0) as sales_center_value
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
                LEFT JOIN {$table} sl ON p.ID = sl.product_id
                {$join_category}
                WHERE {$where}
                GROUP BY p.ID, pm_price.meta_value
            ) sub
        ";

        $summary = $wpdb->get_row($summary_sql); // phpcs:ignore

        // Total count.
        $count_sql = "
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
            {$join_category}
            WHERE {$where}
        ";
        $total = (int)$wpdb->get_var($count_sql); // phpcs:ignore

        return array(
            'items' => $items,
            'total' => $total,
            'pages' => (int)ceil($total / max(1, $per_page)),
            'summary' => array(
                'grand_total' => $summary->grand_total ?? 0,
                'warehouse_total' => $summary->warehouse_total ?? 0,
                'sales_center_total' => $summary->sales_center_total ?? 0,
            ),
        );
    }

    /**
     * Update stock for a product at a specific location.
     */
    public static function update_stock($product_id, $location_type, $quantity, $reason = '', $mode = 'set')
    {
        global $wpdb;

        $product_id = absint($product_id);
        $location_type = sanitize_text_field($location_type);
        $quantity = intval($quantity);
        $table = JESP_ERP_Database::stock_locations_table();

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE product_id = %d AND location_type = %s AND location_name = ''", // phpcs:ignore
            $product_id,
            $location_type
        ));

        $old_qty = $existing ? (int)$existing->quantity : 0;

        if ('add' === $mode) {
            $new_qty = $old_qty + $quantity;
        }
        elseif ('subtract' === $mode) {
            $new_qty = max(0, $old_qty - $quantity);
        }
        else {
            $new_qty = $quantity;
        }

        $change_qty = $new_qty - $old_qty;

        if ($existing) {
            JESP_ERP_Database::update($table, array('quantity' => $new_qty), array('id' => $existing->id), array('%d'), array('%d'));
        }
        else {
            JESP_ERP_Database::insert($table, array(
                'product_id' => $product_id,
                'location_type' => $location_type,
                'location_name' => '',
                'quantity' => $new_qty,
                'min_stock' => 0,
            ), array('%d', '%s', '%s', '%d', '%d'));
        }

        // Log the movement.
        JESP_ERP_Database::insert(JESP_ERP_Database::stock_log_table(), array(
            'product_id' => $product_id,
            'location_type' => $location_type,
            'location_name' => '',
            'change_qty' => $change_qty,
            'new_qty' => $new_qty,
            'reason' => sanitize_text_field($reason),
            'user_id' => get_current_user_id(),
        ), array('%d', '%s', '%s', '%d', '%d', '%s', '%d'));

        // Sync total stock to WooCommerce product.
        self::sync_wc_stock($product_id);

        return array(
            'old_qty' => $old_qty,
            'new_qty' => $new_qty,
            'change' => $change_qty,
        );
    }

    /**
     * Update minimum stock level for a product.
     */
    public static function update_min_stock($product_id, $location_type, $min_stock)
    {
        global $wpdb;
        $table = JESP_ERP_Database::stock_locations_table();

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table} WHERE product_id = %d AND location_type = %s AND location_name = ''", // phpcs:ignore
            absint($product_id),
            sanitize_text_field($location_type)
        ));

        if ($existing) {
            return JESP_ERP_Database::update($table, array('min_stock' => absint($min_stock)), array('id' => $existing->id), array('%d'), array('%d'));
        }
        else {
            return JESP_ERP_Database::insert($table, array(
                'product_id' => absint($product_id),
                'location_type' => sanitize_text_field($location_type),
                'location_name' => '',
                'quantity' => 0,
                'min_stock' => absint($min_stock),
            ), array('%d', '%s', '%s', '%d', '%d'));
        }
    }

    /**
     * Sync total ERP stock to WooCommerce _stock meta.
     */
    public static function sync_wc_stock($product_id)
    {
        global $wpdb;
        $table = JESP_ERP_Database::stock_locations_table();

        $total = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(quantity), 0) FROM {$table} WHERE product_id = %d", // phpcs:ignore
            $product_id
        ));

        $product = wc_get_product($product_id);
        if ($product) {
            $product->set_manage_stock(true);
            $product->set_stock_quantity($total);
            $stock_status = $total > 0 ? 'instock' : 'outofstock';
            $product->set_stock_status($stock_status);
            $product->save();
        }
    }

    /**
     * Get low stock products count.
     */
    public static function get_low_stock_count()
    {
        global $wpdb;
        $table = JESP_ERP_Database::stock_locations_table();

        return (int)$wpdb->get_var(
            "SELECT COUNT(DISTINCT product_id) FROM (
                SELECT product_id, SUM(quantity) as total_qty, MAX(min_stock) as min_level
                FROM {$table}
                GROUP BY product_id
                HAVING total_qty <= min_level AND min_level > 0
            ) sub" // phpcs:ignore
        );
    }

    /**
     * Get stock movement log for a product.
     */
    public static function get_stock_log($product_id = 0, $args = array())
    {
        global $wpdb;
        $table = JESP_ERP_Database::stock_log_table();

        $where = '1=1';
        if ($product_id > 0) {
            $where = $wpdb->prepare('product_id = %d', absint($product_id));
        }

        return JESP_ERP_Database::paginate($table, array_merge($args, array(
            'where' => $where,
            'orderby' => 'created_at',
            'order' => 'DESC',
        )));
    }
}
