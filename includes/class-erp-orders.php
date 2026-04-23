<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Order analytics — queries WooCommerce orders (HPOS compatible) for per-product insights.
 */
class JESP_ERP_Orders
{

    /**
     * Get per-product order analytics.
     */
    public static function get_product_analytics($args = array())
    {
        global $wpdb;

        $defaults = array(
            'date_from' => gmdate('Y-m-d', strtotime('-30 days')),
            'date_to' => gmdate('Y-m-d'),
            'product_id' => 0,
            'customer' => '',
            'search' => '',
            'per_page' => 20,
            'page' => 1,
        );
        $args = wp_parse_args($args, $defaults);

        // Use HPOS tables if available, fallback to posts.
        $orders_table = $wpdb->prefix . 'wc_orders';
        $items_table = $wpdb->prefix . 'woocommerce_order_items';
        $itemmeta = $wpdb->prefix . 'woocommerce_order_itemmeta';

        $use_hpos = self::is_hpos_enabled();

        if ($use_hpos) {
            $date_col = 'o.date_created_gmt';
            $status_col = 'o.status';
            $from_table = "{$orders_table} o";
            $join = "INNER JOIN {$items_table} oi ON o.id = oi.order_id AND oi.order_item_type = 'line_item'";
        }
        else {
            $date_col = 'o.post_date_gmt';
            $status_col = 'o.post_status';
            $from_table = "{$wpdb->posts} o";
            $join = "INNER JOIN {$items_table} oi ON o.ID = oi.order_id AND oi.order_item_type = 'line_item'";
        }

        $where = array();
        $where[] = "{$date_col} >= '" . esc_sql($args['date_from']) . " 00:00:00'";
        $where[] = "{$date_col} <= '" . esc_sql($args['date_to']) . " 23:59:59'";

        if ($use_hpos) {
            $where[] = "{$status_col} IN ('wc-completed','wc-processing')";
        }
        else {
            $where[] = "o.post_type = 'shop_order'";
            $where[] = "{$status_col} IN ('wc-completed','wc-processing')";
        }

        if (!empty($args['product_id'])) {
            $where[] = $wpdb->prepare('oim_pid.meta_value = %d', absint($args['product_id']));
        }

        if (!empty($args['search'])) {
            $where[] = $wpdb->prepare('p.post_title LIKE %s', '%' . $wpdb->esc_like($args['search']) . '%');
        }

        $where_clause = implode(' AND ', $where);

        $per_page = absint($args['per_page']);
        $page = max(1, absint($args['page']));
        $offset = ($page - 1) * $per_page;

        $sql = "
            SELECT
                oim_pid.meta_value as product_id,
                p.post_title as product_name,
                COUNT(DISTINCT oi.order_id) as order_count,
                SUM(oim_qty.meta_value) as total_qty_sold,
                SUM(oim_total.meta_value) as total_revenue,\r\n                GROUP_CONCAT(DISTINCT oi.order_id ORDER BY oi.order_id DESC) as order_ids
            FROM {$from_table}
            {$join}
            INNER JOIN {$itemmeta} oim_pid ON oi.order_item_id = oim_pid.order_item_id AND oim_pid.meta_key = '_product_id'
            LEFT JOIN {$itemmeta} oim_qty ON oi.order_item_id = oim_qty.order_item_id AND oim_qty.meta_key = '_qty'
            LEFT JOIN {$itemmeta} oim_total ON oi.order_item_id = oim_total.order_item_id AND oim_total.meta_key = '_line_total'
            LEFT JOIN {$wpdb->posts} p ON oim_pid.meta_value = p.ID
            WHERE {$where_clause}
            GROUP BY oim_pid.meta_value, p.post_title
            ORDER BY total_revenue DESC
            LIMIT %d OFFSET %d
        ";

        $items = $wpdb->get_results($wpdb->prepare($sql, $per_page, $offset)); // phpcs:ignore

        // Total count.
        $count_sql = "
            SELECT COUNT(DISTINCT oim_pid.meta_value)
            FROM {$from_table}
            {$join}
            INNER JOIN {$itemmeta} oim_pid ON oi.order_item_id = oim_pid.order_item_id AND oim_pid.meta_key = '_product_id'
            WHERE {$where_clause}
        ";
        $total = (int)$wpdb->get_var($count_sql); // phpcs:ignore

        return array(
            'items' => $items,
            'total' => $total,
            'pages' => (int)ceil($total / max(1, $per_page)),
        );
    }

    /**
     * Get summary stats for the dashboard.
     */
    public static function get_summary($date_from = '', $date_to = '')
    {
        global $wpdb;

        if (empty($date_from)) {
            $date_from = gmdate('Y-m-d', strtotime('-30 days'));
        }
        if (empty($date_to)) {
            $date_to = gmdate('Y-m-d');
        }

        $use_hpos = self::is_hpos_enabled();

        if ($use_hpos) {
            $orders_table = $wpdb->prefix . 'wc_orders';
            $sql = $wpdb->prepare(
                "SELECT
                    COUNT(*) as total_orders,
                    COALESCE(SUM(total_amount), 0) as total_revenue
                FROM {$orders_table}
                WHERE status IN ('wc-completed','wc-processing')
                AND date_created_gmt >= %s
                AND date_created_gmt <= %s",
                $date_from . ' 00:00:00',
                $date_to . ' 23:59:59'
            );
        }
        else {
            $sql = $wpdb->prepare(
                "SELECT
                    COUNT(*) as total_orders,
                    COALESCE(SUM(pm.meta_value), 0) as total_revenue
                FROM {$wpdb->posts} o
                LEFT JOIN {$wpdb->postmeta} pm ON o.ID = pm.post_id AND pm.meta_key = '_order_total'
                WHERE o.post_type = 'shop_order'
                AND o.post_status IN ('wc-completed','wc-processing')
                AND o.post_date_gmt >= %s
                AND o.post_date_gmt <= %s",
                $date_from . ' 00:00:00',
                $date_to . ' 23:59:59'
            );
        }

        return $wpdb->get_row($sql); // phpcs:ignore
    }

    /**
     * Get revenue data grouped by date (for charts).
     */
    public static function get_revenue_chart_data($date_from, $date_to)
    {
        global $wpdb;

        $use_hpos = self::is_hpos_enabled();

        if ($use_hpos) {
            $orders_table = $wpdb->prefix . 'wc_orders';
            $sql = $wpdb->prepare(
                "SELECT DATE(date_created_gmt) as order_date,
                    COUNT(*) as order_count,
                    COALESCE(SUM(total_amount), 0) as revenue
                FROM {$orders_table}
                WHERE status IN ('wc-completed','wc-processing')
                AND date_created_gmt >= %s AND date_created_gmt <= %s
                GROUP BY DATE(date_created_gmt)
                ORDER BY order_date ASC",
                $date_from . ' 00:00:00',
                $date_to . ' 23:59:59'
            );
        }
        else {
            $sql = $wpdb->prepare(
                "SELECT DATE(o.post_date_gmt) as order_date,
                    COUNT(*) as order_count,
                    COALESCE(SUM(pm.meta_value), 0) as revenue
                FROM {$wpdb->posts} o
                LEFT JOIN {$wpdb->postmeta} pm ON o.ID = pm.post_id AND pm.meta_key = '_order_total'
                WHERE o.post_type = 'shop_order'
                AND o.post_status IN ('wc-completed','wc-processing')
                AND o.post_date_gmt >= %s AND o.post_date_gmt <= %s
                GROUP BY DATE(o.post_date_gmt)
                ORDER BY order_date ASC",
                $date_from . ' 00:00:00',
                $date_to . ' 23:59:59'
            );
        }

        return $wpdb->get_results($sql); // phpcs:ignore
    }

    /**
     * Check if HPOS (High-Performance Order Storage) is in use.
     */
    private static function is_hpos_enabled()
    {
        if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
            return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        }
        return false;
    }
}
