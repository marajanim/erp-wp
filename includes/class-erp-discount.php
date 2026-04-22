<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Bulk discount management — apply/revert discounts to multiple products.
 */
class JESP_ERP_Discount {

    /**
     * Apply a bulk discount.
     *
     * @param array $config {
     *   name, discount_type (percentage|fixed), discount_value, category, product_ids,
     *   min_stock, max_stock, start_date, end_date
     * }
     * @return array|WP_Error
     */
    public static function apply_discount( $config ) {
        $name   = sanitize_text_field( $config['name'] ?? '' );
        $type   = in_array( $config['discount_type'] ?? '', array( 'percentage', 'fixed' ), true ) ? $config['discount_type'] : 'percentage';
        $value  = floatval( $config['discount_value'] ?? 0 );

        if ( empty( $name ) || $value <= 0 ) {
            return new \WP_Error( 'invalid_input', __( 'Name and positive discount value are required.', 'jesp-erp' ) );
        }

        // Build product list based on filters.
        $product_ids = self::get_filtered_products( $config );

        if ( empty( $product_ids ) ) {
            return new \WP_Error( 'no_products', __( 'No products match the selected filters.', 'jesp-erp' ) );
        }

        $original_prices = array();
        $affected        = array();

        foreach ( $product_ids as $pid ) {
            $product = wc_get_product( $pid );
            if ( ! $product ) {
                continue;
            }

            $regular_price = (float) $product->get_regular_price();
            if ( $regular_price <= 0 ) {
                continue;
            }

            $original_prices[ $pid ] = $regular_price;

            if ( 'percentage' === $type ) {
                $new_price = round( $regular_price * ( 1 - $value / 100 ), 2 );
            } else {
                $new_price = max( 0, $regular_price - $value );
            }

            $product->set_sale_price( $new_price );
            $product->save();
            $affected[] = $pid;
        }

        // Store discount record.
        $discount_id = JESP_ERP_Database::insert(
            JESP_ERP_Database::bulk_discounts_table(),
            array(
                'name'              => $name,
                'discount_type'     => $type,
                'discount_value'    => $value,
                'filters_json'      => wp_json_encode( $config ),
                'affected_products' => wp_json_encode( $affected ),
                'original_prices'   => wp_json_encode( $original_prices ),
                'status'            => 'active',
                'start_date'        => ! empty( $config['start_date'] ) ? sanitize_text_field( $config['start_date'] ) : current_time( 'mysql' ),
                'end_date'          => ! empty( $config['end_date'] ) ? sanitize_text_field( $config['end_date'] ) : null,
            ),
            array( '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        return array(
            'discount_id'      => $discount_id,
            'affected_count'   => count( $affected ),
            'affected_products'=> $affected,
        );
    }

    /**
     * Revert a discount campaign — restore original prices.
     */
    public static function revert_discount( $discount_id ) {
        $table    = JESP_ERP_Database::bulk_discounts_table();
        $discount = JESP_ERP_Database::get_by_id( $table, absint( $discount_id ) );

        if ( ! $discount || 'active' !== $discount->status ) {
            return new \WP_Error( 'not_found', __( 'Discount not found or already reverted.', 'jesp-erp' ) );
        }

        $original_prices = json_decode( $discount->original_prices, true );
        $reverted        = 0;

        if ( is_array( $original_prices ) ) {
            foreach ( $original_prices as $pid => $orig_price ) {
                $product = wc_get_product( $pid );
                if ( $product ) {
                    $product->set_sale_price( '' );
                    $product->save();
                    $reverted++;
                }
            }
        }

        JESP_ERP_Database::update(
            $table,
            array( 'status' => 'reverted' ),
            array( 'id' => $discount->id ),
            array( '%s' ),
            array( '%d' )
        );

        return array( 'reverted_count' => $reverted );
    }

    /**
     * Get all discount campaigns.
     */
    public static function get_discounts( $args = array() ) {
        return JESP_ERP_Database::paginate(
            JESP_ERP_Database::bulk_discounts_table(),
            array_merge( array( 'orderby' => 'created_at', 'order' => 'DESC' ), $args )
        );
    }

    /**
     * Build a product ID list based on filter config.
     */
    private static function get_filtered_products( $config ) {
        $args = array(
            'status' => 'publish',
            'limit'  => -1,
            'return' => 'ids',
            'type'   => 'simple',
        );

        // Specific product IDs.
        if ( ! empty( $config['product_ids'] ) ) {
            $ids = array_map( 'absint', (array) $config['product_ids'] );
            $args['include'] = $ids;
        }

        // Category filter.
        if ( ! empty( $config['category'] ) ) {
            $args['category'] = array( sanitize_text_field( $config['category'] ) );
        }

        $products = wc_get_products( $args );

        // Stock quantity filter — only apply when explicitly provided (non-null).
        $has_min = ( isset( $config['min_stock'] ) && null !== $config['min_stock'] );
        $has_max = ( isset( $config['max_stock'] ) && null !== $config['max_stock'] );

        if ( $has_min || $has_max ) {
            global $wpdb;
            $table = JESP_ERP_Database::stock_locations_table();
            $filtered = array();

            foreach ( $products as $pid ) {
                $total = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COALESCE(SUM(quantity), 0) FROM {$table} WHERE product_id = %d", // phpcs:ignore
                    $pid
                ) );

                $pass = true;
                if ( $has_min && $total < intval( $config['min_stock'] ) ) {
                    $pass = false;
                }
                if ( $has_max && $total > intval( $config['max_stock'] ) ) {
                    $pass = false;
                }
                if ( $pass ) {
                    $filtered[] = $pid;
                }
            }
            return $filtered;
        }

        return $products;
    }
}
