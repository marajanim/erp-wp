<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * CSV export for products with dynamic column selection.
 */
class JESP_ERP_Export {

    /**
     * All supported export fields with their CSV header labels.
     */
    public static function all_fields() {
        return array(
            'sku'                => 'SKU',
            'product_name'       => 'Product Name',
            'category'           => 'Category',
            'buying_price'       => 'Buying Price',
            'selling_price'      => 'Selling Price',
            'sale_price'         => 'Sale Price',
            'warehouse_stock'    => 'Warehouse Stock',
            'sales_center_stock' => 'Sales Center Stock',
            'total_stock'        => 'Total Stock',
            'min_level'          => 'Min Stock Level',
            'status'             => 'Status',
            'created_at'         => 'Created At',
            'updated_at'         => 'Updated At',
        );
    }

    /**
     * Default fields when none are specified (matches the old fixed export).
     */
    private static function default_fields() {
        return array( 'sku', 'product_name', 'selling_price', 'warehouse_stock', 'sales_center_stock', 'total_stock', 'min_level', 'status' );
    }

    /**
     * Generate and stream a CSV download.
     *
     * @param array $args {
     *     @type int    $category     Product category term ID. 0 = all.
     *     @type string $stock_status 'low', 'sufficient', or '' for all.
     *     @type array  $fields       Ordered list of field keys to export. Falls back to default set.
     * }
     */
    public static function export_csv( $args = array() ) {
        $defaults = array(
            'category'     => 0,
            'stock_status' => '',
            'fields'       => array(),
        );
        $args = wp_parse_args( $args, $defaults );

        // Resolve and validate requested fields.
        $all_fields = self::all_fields();
        $requested  = ! empty( $args['fields'] ) ? (array) $args['fields'] : self::default_fields();
        $fields     = array_values( array_intersect( $requested, array_keys( $all_fields ) ) );
        if ( empty( $fields ) ) {
            $fields = self::default_fields();
        }

        // Fetch all matching products (no pagination).
        $data = JESP_ERP_Stock::get_stock_list( array(
            'per_page'     => 99999,
            'page'         => 1,
            'category'     => $args['category'],
            'stock_status' => $args['stock_status'],
        ) );

        // Pre-fetch supplemental data only when the field is actually requested.
        $need_category = in_array( 'category', $fields, true );
        $need_dates    = in_array( 'created_at', $fields, true ) || in_array( 'updated_at', $fields, true );

        $category_cache = array();
        $post_cache     = array();

        if ( $need_category || $need_dates ) {
            foreach ( $data['items'] as $item ) {
                $pid = (int) $item->product_id;
                if ( $need_category ) {
                    $terms = get_the_terms( $pid, 'product_cat' );
                    $category_cache[ $pid ] = ( ! is_wp_error( $terms ) && ! empty( $terms ) )
                        ? implode( ', ', wp_list_pluck( $terms, 'name' ) )
                        : '';
                }
                if ( $need_dates ) {
                    $post_cache[ $pid ] = get_post( $pid );
                }
            }
        }

        // Stream the CSV.
        $filename = 'erp-stock-export-' . gmdate( 'Y-m-d-His' ) . '.csv';
        if ( ob_get_level() ) {
            ob_end_clean();
        }
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );
        fwrite( $output, "\xEF\xBB\xBF" ); // UTF-8 BOM for Excel.

        // Header row.
        $header = array();
        foreach ( $fields as $key ) {
            $header[] = $all_fields[ $key ];
        }
        fputcsv( $output, $header );

        // Data rows.
        foreach ( $data['items'] as $item ) {
            $pid        = (int) $item->product_id;
            $stock_str  = ( (int) $item->min_level > 0 && (int) $item->total_qty <= (int) $item->min_level )
                ? 'Low Stock'
                : 'Sufficient';

            $row = array();
            foreach ( $fields as $key ) {
                switch ( $key ) {
                    case 'sku':
                        $row[] = $item->sku ?? '';
                        break;
                    case 'product_name':
                        $row[] = $item->product_name;
                        break;
                    case 'category':
                        $row[] = $category_cache[ $pid ] ?? '';
                        break;
                    case 'buying_price':
                        $row[] = $item->buying_price ?? '';
                        break;
                    case 'selling_price':
                        $row[] = $item->regular_price ?? $item->price ?? '0.00';
                        break;
                    case 'sale_price':
                        $row[] = $item->sale_price ?? '';
                        break;
                    case 'warehouse_stock':
                        $row[] = $item->warehouse_qty;
                        break;
                    case 'sales_center_stock':
                        $row[] = $item->sales_center_qty;
                        break;
                    case 'total_stock':
                        $row[] = $item->total_qty;
                        break;
                    case 'min_level':
                        $row[] = $item->min_level;
                        break;
                    case 'status':
                        $row[] = $stock_str;
                        break;
                    case 'created_at':
                        $row[] = isset( $post_cache[ $pid ] ) ? $post_cache[ $pid ]->post_date : '';
                        break;
                    case 'updated_at':
                        $row[] = isset( $post_cache[ $pid ] ) ? $post_cache[ $pid ]->post_modified : '';
                        break;
                    default:
                        $row[] = '';
                }
            }
            fputcsv( $output, $row );
        }

        fclose( $output );
        exit;
    }
}
