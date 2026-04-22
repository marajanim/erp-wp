<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class JESP_ERP_Database {

    public static function stock_locations_table() {
        global $wpdb;
        return $wpdb->prefix . 'jesp_erp_stock_locations';
    }

    public static function customer_purchases_table() {
        global $wpdb;
        return $wpdb->prefix . 'jesp_erp_customer_purchases';
    }

    public static function bulk_discounts_table() {
        global $wpdb;
        return $wpdb->prefix . 'jesp_erp_bulk_discounts';
    }

    public static function stock_log_table() {
        global $wpdb;
        return $wpdb->prefix . 'jesp_erp_stock_log';
    }

    public static function insert( $table, $data, $format = array() ) {
        global $wpdb;
        $result = $wpdb->insert( $table, $data, $format );
        return false !== $result ? $wpdb->insert_id : false;
    }

    public static function update( $table, $data, $where, $format = array(), $where_format = array() ) {
        global $wpdb;
        return $wpdb->update( $table, $data, $where, $format, $where_format );
    }

    public static function delete( $table, $where, $where_format = array() ) {
        global $wpdb;
        return $wpdb->delete( $table, $where, $where_format );
    }

    public static function get_by_id( $table, $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore
    }

    public static function paginate( $table, $args = array() ) {
        global $wpdb;
        $defaults = array(
            'where'      => '',
            'orderby'    => 'id',
            'order'      => 'DESC',
            'per_page'   => 20,
            'page'       => 1,
            'search_col' => '',
            'search_val' => '',
        );
        $args = wp_parse_args( $args, $defaults );

        $where_clause = '1=1';
        if ( ! empty( $args['where'] ) ) {
            $where_clause .= ' AND ' . $args['where'];
        }
        if ( ! empty( $args['search_col'] ) && ! empty( $args['search_val'] ) ) {
            $like          = '%' . $wpdb->esc_like( $args['search_val'] ) . '%';
            $where_clause .= $wpdb->prepare( " AND {$args['search_col']} LIKE %s", $like ); // phpcs:ignore
        }

        $allowed_order = in_array( strtoupper( $args['order'] ), array( 'ASC', 'DESC' ), true ) ? strtoupper( $args['order'] ) : 'DESC';
        $orderby       = sanitize_sql_orderby( $args['orderby'] . ' ' . $allowed_order );
        if ( ! $orderby ) {
            $orderby = 'id DESC';
        }

        $per_page = absint( $args['per_page'] );
        $page     = max( 1, absint( $args['page'] ) );
        $offset   = ( $page - 1 ) * $per_page;

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}" ); // phpcs:ignore
        $items = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d", $per_page, $offset ) // phpcs:ignore
        );

        return array(
            'items' => $items,
            'total' => $total,
            'pages' => (int) ceil( $total / max( 1, $per_page ) ),
        );
    }
}
