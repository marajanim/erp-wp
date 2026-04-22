<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * CSV/Excel export for products with stock data.
 */
class JESP_ERP_Export {

    /**
     * Generate and stream CSV download.
     */
    public static function export_csv( $args = array() ) {
        $defaults = array(
            'category'     => 0,
            'stock_status' => '', // low, all
        );
        $args = wp_parse_args( $args, $defaults );

        // Fetch all matching products (no pagination for export).
        $data = JESP_ERP_Stock::get_stock_list( array(
            'per_page'     => 99999,
            'page'         => 1,
            'category'     => $args['category'],
            'stock_status' => $args['stock_status'],
        ) );

        $filename = 'erp-stock-export-' . gmdate( 'Y-m-d-His' ) . '.csv';

        // Clean output buffer.
        if ( ob_get_level() ) {
            ob_end_clean();
        }

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );

        // BOM for Excel UTF-8.
        fwrite( $output, "\xEF\xBB\xBF" );

        // Header row.
        fputcsv( $output, array(
            'SKU',
            'Product Name',
            'Warehouse Stock',
            'Sales Center Stock',
            'Total Stock',
            'Min Stock Level',
            'Price',
            'Stock Status',
        ) );

        foreach ( $data['items'] as $item ) {
            $status = 'Sufficient';
            if ( (int) $item->min_level > 0 && (int) $item->total_qty <= (int) $item->min_level ) {
                $status = 'Low Stock';
            }

            fputcsv( $output, array(
                $item->sku ?? '',
                $item->product_name,
                $item->warehouse_qty,
                $item->sales_center_qty,
                $item->total_qty,
                $item->min_level,
                $item->price ?? '0.00',
                $status,
            ) );
        }

        fclose( $output );
        exit;
    }
}
