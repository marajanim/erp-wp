<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Finance module - queries WooCommerce for revenue, refunds, tax, payment methods,
 * and manages a custom expenses table.
 */
class JESP_ERP_Finance
{

    public static function expenses_table()
    {
        global $wpdb;
        return $wpdb->prefix . 'jesp_expenses';
    }

    public static function create_tables()
    {
        global $wpdb;
        $table   = self::expenses_table();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL DEFAULT '',
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            category VARCHAR(100) NOT NULL DEFAULT '',
            expense_date DATE NOT NULL,
            notes TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY expense_date (expense_date),
            KEY category (category)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function get_finance_summary($date_from, $date_to)
    {
        $args = array(
            'status'       => array('wc-completed', 'wc-processing'),
            'date_created' => $date_from . '...' . $date_to,
            'limit'        => -1,
        );

        $orders = wc_get_orders($args);

        $total_revenue  = 0;
        $total_tax      = 0;
        $total_shipping = 0;
        $total_discount = 0;
        $order_count    = count($orders);

        foreach ($orders as $order) {
            $total_revenue  += (float) $order->get_total();
            $total_tax      += (float) $order->get_total_tax();
            $total_shipping += (float) $order->get_shipping_total();
            $total_discount += (float) $order->get_discount_total();
        }

        $refund_args = array(
            'status'       => array('wc-refunded'),
            'date_created' => $date_from . '...' . $date_to,
            'limit'        => -1,
        );
        $refunded_orders = wc_get_orders($refund_args);
        $total_refunds = 0;
        foreach ($refunded_orders as $order) {
            $total_refunds += abs((float) $order->get_total());
        }

        foreach ($orders as $order) {
            $refunds = $order->get_refunds();
            foreach ($refunds as $refund) {
                $total_refunds += abs((float) $refund->get_total());
            }
        }

        global $wpdb;
        $expenses_table = self::expenses_table();
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$expenses_table}'") === $expenses_table;
        $total_expenses = 0;
        if ($table_exists) {
            $total_expenses = (float) $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(amount), 0) FROM {$expenses_table} WHERE expense_date >= %s AND expense_date <= %s",
                $date_from,
                $date_to
            ));
        }

        $net_profit = $total_revenue - $total_refunds - $total_expenses;

        return array(
            'total_revenue'  => round($total_revenue, 2),
            'total_refunds'  => round($total_refunds, 2),
            'total_tax'      => round($total_tax, 2),
            'total_shipping' => round($total_shipping, 2),
            'total_discount' => round($total_discount, 2),
            'total_expenses' => round($total_expenses, 2),
            'net_profit'     => round($net_profit, 2),
            'order_count'    => $order_count,
        );
    }

    public static function get_payment_methods($date_from, $date_to)
    {
        $orders = wc_get_orders(array(
            'status'       => array('wc-completed', 'wc-processing'),
            'date_created' => $date_from . '...' . $date_to,
            'limit'        => -1,
        ));

        $methods = array();
        foreach ($orders as $order) {
            $key   = $order->get_payment_method();
            $label = $order->get_payment_method_title() ?: $key ?: 'Unknown';
            if (!isset($methods[$key])) {
                $methods[$key] = array(
                    'method'      => $key,
                    'label'       => $label,
                    'total'       => 0,
                    'order_count' => 0,
                );
            }
            $methods[$key]['total']       += (float) $order->get_total();
            $methods[$key]['order_count'] += 1;
        }

        usort($methods, function ($a, $b) {
            return $b['total'] <=> $a['total'];
        });

        return array_values($methods);
    }

    public static function get_daily_revenue($date_from, $date_to)
    {
        global $wpdb;
        $use_hpos = self::is_hpos_enabled();

        if ($use_hpos) {
            $orders_table = $wpdb->prefix . 'wc_orders';
            $sql = $wpdb->prepare(
                "SELECT DATE(date_created_gmt) as day, COALESCE(SUM(total_amount), 0) as revenue, COUNT(*) as orders
                FROM {$orders_table}
                WHERE status IN ('wc-completed','wc-processing')
                AND date_created_gmt >= %s AND date_created_gmt <= %s
                GROUP BY DATE(date_created_gmt) ORDER BY day ASC",
                $date_from . ' 00:00:00', $date_to . ' 23:59:59'
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT DATE(o.post_date_gmt) as day, COALESCE(SUM(pm.meta_value), 0) as revenue, COUNT(*) as orders
                FROM {$wpdb->posts} o
                LEFT JOIN {$wpdb->postmeta} pm ON o.ID = pm.post_id AND pm.meta_key = '_order_total'
                WHERE o.post_type = 'shop_order' AND o.post_status IN ('wc-completed','wc-processing')
                AND o.post_date_gmt >= %s AND o.post_date_gmt <= %s
                GROUP BY DATE(o.post_date_gmt) ORDER BY day ASC",
                $date_from . ' 00:00:00', $date_to . ' 23:59:59'
            );
        }

        $results = $wpdb->get_results($sql);

        if ($use_hpos) {
            $refund_sql = $wpdb->prepare(
                "SELECT DATE(date_created_gmt) as day, COALESCE(SUM(ABS(total_amount)), 0) as refunds
                FROM {$wpdb->prefix}wc_orders WHERE status = 'wc-refunded'
                AND date_created_gmt >= %s AND date_created_gmt <= %s
                GROUP BY DATE(date_created_gmt) ORDER BY day ASC",
                $date_from . ' 00:00:00', $date_to . ' 23:59:59'
            );
        } else {
            $refund_sql = $wpdb->prepare(
                "SELECT DATE(o.post_date_gmt) as day, COALESCE(SUM(ABS(pm.meta_value)), 0) as refunds
                FROM {$wpdb->posts} o
                LEFT JOIN {$wpdb->postmeta} pm ON o.ID = pm.post_id AND pm.meta_key = '_order_total'
                WHERE o.post_type = 'shop_order' AND o.post_status = 'wc-refunded'
                AND o.post_date_gmt >= %s AND o.post_date_gmt <= %s
                GROUP BY DATE(o.post_date_gmt) ORDER BY day ASC",
                $date_from . ' 00:00:00', $date_to . ' 23:59:59'
            );
        }

        $refund_results = $wpdb->get_results($refund_sql);
        $refund_map = array();
        foreach ($refund_results as $r) {
            $refund_map[$r->day] = (float) $r->refunds;
        }

        $data = array();
        foreach ($results as $row) {
            $data[] = array(
                'day'     => $row->day,
                'revenue' => round((float) $row->revenue, 2),
                'orders'  => (int) $row->orders,
                'refunds' => round($refund_map[$row->day] ?? 0, 2),
            );
        }

        return $data;
    }

    public static function get_expenses($args = array())
    {
        global $wpdb;
        $table = self::expenses_table();

        $defaults = array(
            'date_from' => gmdate('Y-m-d', strtotime('-30 days')),
            'date_to'   => gmdate('Y-m-d'),
            'category'  => '',
            'per_page'  => 20,
            'page'      => 1,
        );
        $args = wp_parse_args($args, $defaults);

        $where_parts = array();
        $where_args  = array();

        $where_parts[] = 'expense_date >= %s';
        $where_args[]  = $args['date_from'];
        $where_parts[] = 'expense_date <= %s';
        $where_args[]  = $args['date_to'];

        if (!empty($args['category'])) {
            $where_parts[] = 'category = %s';
            $where_args[]  = $args['category'];
        }

        $where_clause = implode(' AND ', $where_parts);

        $per_page = absint($args['per_page']);
        $page     = max(1, absint($args['page']));
        $offset   = ($page - 1) * $per_page;

        $items_args = array_merge($where_args, array($per_page, $offset));
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY expense_date DESC LIMIT %d OFFSET %d",
            $items_args
        ));

        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}",
            $where_args
        ));

        $cat_totals = $wpdb->get_results($wpdb->prepare(
            "SELECT category, SUM(amount) as total, COUNT(*) as cnt FROM {$table} WHERE {$where_clause} GROUP BY category ORDER BY total DESC",
            $where_args
        ));

        return array(
            'items'      => $items,
            'total'      => $total,
            'pages'      => (int) ceil($total / max(1, $per_page)),
            'cat_totals' => $cat_totals,
        );
    }

    public static function save_expense($data)
    {
        global $wpdb;
        $table = self::expenses_table();

        $row = array(
            'title'        => sanitize_text_field($data['title'] ?? ''),
            'amount'       => round(floatval($data['amount'] ?? 0), 2),
            'category'     => sanitize_text_field($data['category'] ?? ''),
            'expense_date' => sanitize_text_field($data['expense_date'] ?? gmdate('Y-m-d')),
            'notes'        => sanitize_textarea_field($data['notes'] ?? ''),
        );

        if (empty($row['title'])) {
            return new \WP_Error('missing_title', __('Expense title is required.', 'jesp-erp'));
        }

        $id = absint($data['id'] ?? 0);
        if ($id > 0) {
            $wpdb->update($table, $row, array('id' => $id));
        } else {
            $wpdb->insert($table, $row);
            $id = $wpdb->insert_id;
        }

        return array('id' => $id, 'message' => __('Expense saved.', 'jesp-erp'));
    }

    public static function delete_expense($id)
    {
        global $wpdb;
        $wpdb->delete(self::expenses_table(), array('id' => absint($id)));
        return array('message' => __('Expense deleted.', 'jesp-erp'));
    }

    private static function is_hpos_enabled()
    {
        if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
            return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        }
        return false;
    }
}