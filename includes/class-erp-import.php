<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * CSV import — parse, validate, and create/update products with stock.
 */
class JESP_ERP_Import {

    /**
     * Process uploaded CSV file.
     *
     * Expected columns: Product Name, Description, Image URL (optional),
     *                   Current Stock, Minimum Stock Level, Stock Location
     *
     * @param string $file_path Path to the uploaded CSV.
     * @return array Summary of import results.
     */
    public static function process_csv( $file_path ) {
        $results = array(
            'created'  => 0,
            'updated'  => 0,
            'skipped'  => 0,
            'errors'   => array(),
            'total'    => 0,
        );

        if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
            $results['errors'][] = __( 'File not found or not readable.', 'jesp-erp' );
            return $results;
        }

        $handle = fopen( $file_path, 'r' );
        if ( false === $handle ) {
            $results['errors'][] = __( 'Failed to open file.', 'jesp-erp' );
            return $results;
        }

        // Read header row.
        $header = fgetcsv( $handle );
        if ( ! $header ) {
            fclose( $handle );
            $results['errors'][] = __( 'Empty CSV file.', 'jesp-erp' );
            return $results;
        }

        // Normalize headers.
        $header = array_map( function ( $h ) {
            return strtolower( trim( str_replace( array( ' ', '-' ), '_', $h ) ) );
        }, $header );

        // Map expected columns.
        $col_map = array(
            'product_name'      => self::find_col( $header, array( 'product_name', 'name', 'product', 'title' ) ),
            'description'       => self::find_col( $header, array( 'description', 'desc', 'product_description' ) ),
            'image'             => self::find_col( $header, array( 'image', 'image_url', 'img', 'photo' ) ),
            'current_stock'     => self::find_col( $header, array( 'current_stock', 'stock', 'quantity', 'qty', 'stock_qty' ) ),
            'min_stock_level'   => self::find_col( $header, array( 'minimum_stock_level', 'min_stock_level', 'min_stock', 'min_qty' ) ),
            'stock_location'    => self::find_col( $header, array( 'stock_location', 'location', 'warehouse', 'location_type' ) ),
            'sku'               => self::find_col( $header, array( 'sku', 'product_sku' ) ),
            'price'             => self::find_col( $header, array( 'price', 'regular_price', 'product_price' ) ),
        );

        if ( false === $col_map['product_name'] ) {
            fclose( $handle );
            $results['errors'][] = __( 'Missing required column: Product Name.', 'jesp-erp' );
            return $results;
        }

        $row_num = 1;
        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $row_num++;
            $results['total']++;

            $name = isset( $row[ $col_map['product_name'] ] ) ? sanitize_text_field( trim( $row[ $col_map['product_name'] ] ) ) : '';
            if ( empty( $name ) ) {
                $results['errors'][] = sprintf( __( 'Row %d: Empty product name, skipped.', 'jesp-erp' ), $row_num );
                $results['skipped']++;
                continue;
            }

            $desc       = ( false !== $col_map['description'] && isset( $row[ $col_map['description'] ] ) ) ? sanitize_textarea_field( $row[ $col_map['description'] ] ) : '';
            $image_url  = ( false !== $col_map['image'] && isset( $row[ $col_map['image'] ] ) ) ? esc_url_raw( trim( $row[ $col_map['image'] ] ) ) : '';
            $stock_qty  = ( false !== $col_map['current_stock'] && isset( $row[ $col_map['current_stock'] ] ) ) ? intval( $row[ $col_map['current_stock'] ] ) : 0;
            $min_stock  = ( false !== $col_map['min_stock_level'] && isset( $row[ $col_map['min_stock_level'] ] ) ) ? absint( $row[ $col_map['min_stock_level'] ] ) : 0;
            $location   = ( false !== $col_map['stock_location'] && isset( $row[ $col_map['stock_location'] ] ) ) ? sanitize_text_field( strtolower( trim( $row[ $col_map['stock_location'] ] ) ) ) : 'warehouse';
            $sku        = ( false !== $col_map['sku'] && isset( $row[ $col_map['sku'] ] ) ) ? sanitize_text_field( trim( $row[ $col_map['sku'] ] ) ) : '';
            $price      = ( false !== $col_map['price'] && isset( $row[ $col_map['price'] ] ) ) ? floatval( $row[ $col_map['price'] ] ) : 0;

            // Normalize location.
            if ( strpos( $location, 'sales' ) !== false ) {
                $location = 'sales_center';
            } else {
                $location = 'warehouse';
            }

            // Try to find existing product by SKU or name.
            $product_id = 0;
            if ( ! empty( $sku ) ) {
                $product_id = wc_get_product_id_by_sku( $sku );
            }
            if ( ! $product_id ) {
                // Search by exact title.
                global $wpdb;
                $product_id = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'product' AND post_status = 'publish' LIMIT 1",
                    $name
                ) );
            }

            if ( $product_id ) {
                // Update existing product.
                $product = wc_get_product( $product_id );
                if ( $product ) {
                    if ( ! empty( $desc ) ) {
                        $product->set_description( $desc );
                    }
                    if ( $price > 0 ) {
                        $product->set_regular_price( $price );
                    }
                    $product->save();
                }
                $results['updated']++;
            } else {
                // Create new product.
                $product = new \WC_Product_Simple();
                $product->set_name( $name );
                $product->set_description( $desc );
                $product->set_status( 'publish' );
                if ( ! empty( $sku ) ) {
                    $product->set_sku( $sku );
                }
                if ( $price > 0 ) {
                    $product->set_regular_price( $price );
                }
                $product->set_manage_stock( true );
                $product_id = $product->save();

                if ( ! $product_id ) {
                    $results['errors'][] = sprintf( __( 'Row %d: Failed to create product "%s".', 'jesp-erp' ), $row_num, $name );
                    $results['skipped']++;
                    continue;
                }

                // Handle image URL.
                if ( ! empty( $image_url ) ) {
                    self::set_product_image( $product_id, $image_url );
                }

                $results['created']++;
            }

            // Set stock at location.
            JESP_ERP_Stock::update_stock( $product_id, $location, $stock_qty, 'CSV Import', 'set' );
            JESP_ERP_Stock::update_min_stock( $product_id, $location, $min_stock );
        }

        fclose( $handle );
        return $results;
    }

    /**
     * Find a column index from possible header names.
     */
    private static function find_col( $header, $possible_names ) {
        foreach ( $possible_names as $name ) {
            $idx = array_search( $name, $header, true );
            if ( false !== $idx ) {
                return $idx;
            }
        }
        return false;
    }

    /**
     * Download and attach a remote image to a product.
     */
    private static function set_product_image( $product_id, $image_url ) {
        if ( empty( $image_url ) ) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_sideload_image( $image_url, $product_id, '', 'id' );
        if ( ! is_wp_error( $attachment_id ) ) {
            set_post_thumbnail( $product_id, $attachment_id );
        }
    }
}
