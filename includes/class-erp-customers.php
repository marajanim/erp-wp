<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Customer data aggregation — profiles, search, and order history.
 */
class JESP_ERP_Customers {

    /**
     * Get paginated customer list from the custom purchases table.
     */
    public static function get_customers( $args = array() ) {
        $defaults = array(
            'per_page'   => 20,
            'page'       => 1,
            'search'     => '',
            'min_spent'  => 0,
            'orderby'    => 'total_spent',
            'order'      => 'DESC',
        );
        $args = wp_parse_args( $args, $defaults );

        $where = '1=1';
        if ( ! empty( $args['search'] ) ) {
            global $wpdb;
            $like   = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where .= $wpdb->prepare(
                " AND (phone LIKE %s OR customer_name LIKE %s OR email LIKE %s)",
                $like, $like, $like
            );
        }
        if ( floatval( $args['min_spent'] ) > 0 ) {
            $where .= $wpdb->prepare( ' AND total_spent >= %f', floatval( $args['min_spent'] ) );
        }

        return JESP_ERP_Database::paginate(
            JESP_ERP_Database::customer_purchases_table(),
            array(
                'where'    => $where,
                'orderby'  => sanitize_key( $args['orderby'] ),
                'order'    => $args['order'],
                'per_page' => $args['per_page'],
                'page'     => $args['page'],
            )
        );
    }

    /**
     * Get a single customer profile by ID.
     */
    public static function get_customer( $customer_id ) {
        return JESP_ERP_Database::get_by_id(
            JESP_ERP_Database::customer_purchases_table(),
            absint( $customer_id )
        );
    }

    /**
     * Get order history for a customer (by email or phone).
     */
    public static function get_customer_orders( $identifier, $type = 'email', $args = array() ) {
        global $wpdb;

        $defaults = array(
            'per_page' => 20,
            'page'     => 1,
        );
        $args = wp_parse_args( $args, $defaults );

        $per_page = absint( $args['per_page'] );
        $page     = max( 1, absint( $args['page'] ) );
        $offset   = ( $page - 1 ) * $per_page;

        $use_hpos = self::is_hpos_enabled();

        if ( $use_hpos ) {
            $orders_table    = $wpdb->prefix . 'wc_orders';
            $addresses_table = $wpdb->prefix . 'wc_order_addresses';

            if ( 'phone' === $type ) {
                $where_field = 'a.phone';
            } else {
                $where_field = 'a.email';
            }

            $sql    = $wpdb->prepare(
                "SELECT o.id as order_id, o.date_created_gmt as order_date,
                        o.status, o.total_amount as order_total
                 FROM {$orders_table} o
                 INNER JOIN {$addresses_table} a ON o.id = a.order_id AND a.address_type = 'billing'
                 WHERE {$where_field} = %s
                 ORDER BY o.date_created_gmt DESC
                 LIMIT %d OFFSET %d",
                sanitize_text_field( $identifier ),
                $per_page,
                $offset
            );

            $count_sql = $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$orders_table} o
                 INNER JOIN {$addresses_table} a ON o.id = a.order_id AND a.address_type = 'billing'
                 WHERE {$where_field} = %s",
                sanitize_text_field( $identifier )
            );
        } else {
            if ( 'phone' === $type ) {
                $meta_key = '_billing_phone';
            } else {
                $meta_key = '_billing_email';
            }

            $sql = $wpdb->prepare(
                "SELECT o.ID as order_id, o.post_date_gmt as order_date,
                        o.post_status as status,
                        pm_total.meta_value as order_total
                 FROM {$wpdb->posts} o
                 INNER JOIN {$wpdb->postmeta} pm ON o.ID = pm.post_id AND pm.meta_key = %s AND pm.meta_value = %s
                 LEFT JOIN {$wpdb->postmeta} pm_total ON o.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
                 WHERE o.post_type = 'shop_order'
                 ORDER BY o.post_date_gmt DESC
                 LIMIT %d OFFSET %d",
                $meta_key,
                sanitize_text_field( $identifier ),
                $per_page,
                $offset
            );

            $count_sql = $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$wpdb->posts} o
                 INNER JOIN {$wpdb->postmeta} pm ON o.ID = pm.post_id AND pm.meta_key = %s AND pm.meta_value = %s
                 WHERE o.post_type = 'shop_order'",
                $meta_key,
                sanitize_text_field( $identifier )
            );
        }

        $items = $wpdb->get_results( $sql ); // phpcs:ignore
        $total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore

        // Enrich with order items.
        foreach ( $items as &$order_row ) {
            $order = wc_get_order( $order_row->order_id );
            $order_row->items = array();
            if ( $order ) {
                foreach ( $order->get_items() as $item ) {
                    $order_row->items[] = array(
                        'name' => $item->get_name(),
                        'qty'  => $item->get_quantity(),
                        'total'=> $item->get_total(),
                    );
                }
                $order_row->status_label  = wc_get_order_status_name( $order->get_status() );
                $order_row->order_number  = $order->get_order_number();
            }
        }

        return array(
            'items' => $items,
            'total' => $total,
            'pages' => (int) ceil( $total / max( 1, $per_page ) ),
        );
    }

    /**
     * Sync/update customer purchase record from an order.
     */
    public static function sync_from_order( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $email = $order->get_billing_email();
        $phone = $order->get_billing_phone();
        $name  = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $address = $order->get_billing_address_1() . ', ' . $order->get_billing_city() . ', ' . $order->get_billing_state() . ' ' . $order->get_billing_postcode();

        if ( empty( $email ) && empty( $phone ) ) {
            return;
        }

        global $wpdb;
        $table = JESP_ERP_Database::customer_purchases_table();

        // Try to find existing customer by email or phone.
        $existing = null;
        if ( ! empty( $phone ) ) {
            $existing = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$table} WHERE phone = %s LIMIT 1", // phpcs:ignore
                $phone
            ) );
        }
        if ( ! $existing && ! empty( $email ) ) {
            $existing = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$table} WHERE email = %s LIMIT 1", // phpcs:ignore
                $email
            ) );
        }

        $order_total = (float) $order->get_total();

        if ( $existing ) {
            JESP_ERP_Database::update(
                $table,
                array(
                    'customer_name'  => sanitize_text_field( $name ),
                    'email'          => sanitize_email( $email ),
                    'phone'          => sanitize_text_field( $phone ),
                    'address'        => sanitize_textarea_field( $address ),
                    'total_spent'    => (float) $existing->total_spent + $order_total,
                    'order_count'    => (int) $existing->order_count + 1,
                    'last_order_date'=> current_time( 'mysql' ),
                ),
                array( 'id' => $existing->id ),
                array( '%s', '%s', '%s', '%s', '%f', '%d', '%s' ),
                array( '%d' )
            );
        } else {
            JESP_ERP_Database::insert(
                $table,
                array(
                    'customer_name'  => sanitize_text_field( $name ),
                    'email'          => sanitize_email( $email ),
                    'phone'          => sanitize_text_field( $phone ),
                    'address'        => sanitize_textarea_field( $address ),
                    'total_spent'    => $order_total,
                    'order_count'    => 1,
                    'last_order_date'=> current_time( 'mysql' ),
                ),
                array( '%s', '%s', '%s', '%s', '%f', '%d', '%s' )
            );
        }
    }

    /**
     * Sync all historical WooCommerce orders into the customer purchases table.
     * Uses a single aggregation query per HPOS/legacy mode — safe to call on activation
     * and from the manual "Resync" button. Overwrites existing totals with the true
     * aggregated values so it is fully idempotent.
     *
     * @return int Number of customer records inserted or updated.
     */
    public static function sync_all_historical_orders() {
        global $wpdb;
        $table = JESP_ERP_Database::customer_purchases_table();

        if ( self::is_hpos_enabled() ) {
            $orders_table    = $wpdb->prefix . 'wc_orders';
            $addresses_table = $wpdb->prefix . 'wc_order_addresses';

            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $customers = $wpdb->get_results(
                "SELECT
                    TRIM( CONCAT( COALESCE(a.first_name,''), ' ', COALESCE(a.last_name,'') ) ) AS customer_name,
                    COALESCE(a.email,'')   AS email,
                    COALESCE(a.phone,'')   AS phone,
                    TRIM( CONCAT_WS(', ',
                        NULLIF(COALESCE(a.address_1,''),''),
                        NULLIF(COALESCE(a.city,''),''),
                        NULLIF(TRIM(CONCAT(COALESCE(a.state,''),' ',COALESCE(a.postcode,''))),'')
                    ) )                    AS address,
                    SUM(o.total_amount)    AS total_spent,
                    COUNT(DISTINCT o.id)   AS order_count,
                    MAX(o.date_created_gmt) AS last_order_date
                FROM {$orders_table} o
                INNER JOIN {$addresses_table} a ON o.id = a.order_id AND a.address_type = 'billing'
                WHERE o.status IN ('wc-completed','wc-processing')
                  AND ( COALESCE(a.email,'') != '' OR COALESCE(a.phone,'') != '' )
                GROUP BY COALESCE( NULLIF(a.phone,''), a.email )"
            );
            // phpcs:enable
        } else {
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $customers = $wpdb->get_results(
                "SELECT
                    TRIM( CONCAT( COALESCE(pm_fn.meta_value,''), ' ', COALESCE(pm_ln.meta_value,'') ) ) AS customer_name,
                    COALESCE(pm_email.meta_value,'') AS email,
                    COALESCE(pm_phone.meta_value,'') AS phone,
                    TRIM( CONCAT_WS(', ',
                        NULLIF(COALESCE(pm_addr.meta_value,''),''),
                        NULLIF(COALESCE(pm_city.meta_value,''),''),
                        NULLIF(TRIM(CONCAT(COALESCE(pm_state.meta_value,''),' ',COALESCE(pm_post.meta_value,''))),'')
                    ) )                              AS address,
                    SUM( CAST( COALESCE(pm_total.meta_value,'0') AS DECIMAL(12,2) ) ) AS total_spent,
                    COUNT(DISTINCT o.ID)             AS order_count,
                    MAX(o.post_date_gmt)             AS last_order_date
                FROM {$wpdb->posts} o
                LEFT JOIN {$wpdb->postmeta} pm_fn    ON o.ID = pm_fn.post_id    AND pm_fn.meta_key    = '_billing_first_name'
                LEFT JOIN {$wpdb->postmeta} pm_ln    ON o.ID = pm_ln.post_id    AND pm_ln.meta_key    = '_billing_last_name'
                LEFT JOIN {$wpdb->postmeta} pm_email ON o.ID = pm_email.post_id AND pm_email.meta_key = '_billing_email'
                LEFT JOIN {$wpdb->postmeta} pm_phone ON o.ID = pm_phone.post_id AND pm_phone.meta_key = '_billing_phone'
                LEFT JOIN {$wpdb->postmeta} pm_addr  ON o.ID = pm_addr.post_id  AND pm_addr.meta_key  = '_billing_address_1'
                LEFT JOIN {$wpdb->postmeta} pm_city  ON o.ID = pm_city.post_id  AND pm_city.meta_key  = '_billing_city'
                LEFT JOIN {$wpdb->postmeta} pm_state ON o.ID = pm_state.post_id AND pm_state.meta_key = '_billing_state'
                LEFT JOIN {$wpdb->postmeta} pm_post  ON o.ID = pm_post.post_id  AND pm_post.meta_key  = '_billing_postcode'
                LEFT JOIN {$wpdb->postmeta} pm_total ON o.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
                WHERE o.post_type = 'shop_order'
                  AND o.post_status IN ('wc-completed','wc-processing')
                  AND ( COALESCE(pm_email.meta_value,'') != '' OR COALESCE(pm_phone.meta_value,'') != '' )
                GROUP BY COALESCE( NULLIF(pm_phone.meta_value,''), pm_email.meta_value )"
            );
            // phpcs:enable
        }

        if ( empty( $customers ) ) {
            return 0;
        }

        $synced = 0;
        foreach ( $customers as $c ) {
            if ( empty( $c->email ) && empty( $c->phone ) ) {
                continue;
            }

            // Look up any existing ERP record for this customer.
            $existing = null;
            if ( ! empty( $c->phone ) ) {
                $existing = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore
                    "SELECT * FROM {$table} WHERE phone = %s LIMIT 1",
                    $c->phone
                ) );
            }
            if ( ! $existing && ! empty( $c->email ) ) {
                $existing = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore
                    "SELECT * FROM {$table} WHERE email = %s LIMIT 1",
                    $c->email
                ) );
            }

            $data    = array(
                'customer_name'   => sanitize_text_field( $c->customer_name ),
                'email'           => sanitize_email( $c->email ),
                'phone'           => sanitize_text_field( $c->phone ),
                'address'         => sanitize_textarea_field( $c->address ),
                'total_spent'     => (float) $c->total_spent,
                'order_count'     => (int) $c->order_count,
                'last_order_date' => $c->last_order_date,
            );
            $formats = array( '%s', '%s', '%s', '%s', '%f', '%d', '%s' );

            if ( $existing ) {
                $wpdb->update( $table, $data, array( 'id' => $existing->id ), $formats, array( '%d' ) );
            } else {
                $wpdb->insert( $table, $data, $formats );
            }
            $synced++;
        }

        return $synced;
    }

    /**
     * Merge duplicate customer records that share the same email.
     * Totals are summed; the oldest record (lowest ID) is kept.
     *
     * @return int Number of duplicate rows removed.
     */
    public static function deduplicate_customers() {
        global $wpdb;
        $table = JESP_ERP_Database::customer_purchases_table();

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $dupes = $wpdb->get_results(
            "SELECT email, COUNT(*) AS cnt
             FROM {$table}
             WHERE email != ''
             GROUP BY email
             HAVING cnt > 1"
        );
        // phpcs:enable

        $removed = 0;

        foreach ( $dupes as $dupe ) {
            $records = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore
                "SELECT * FROM {$table} WHERE email = %s ORDER BY id ASC",
                $dupe->email
            ) );

            if ( count( $records ) <= 1 ) {
                continue;
            }

            $total_spent     = 0.0;
            $order_count     = 0;
            $last_order_date = '';

            foreach ( $records as $r ) {
                $total_spent += (float) $r->total_spent;
                $order_count += (int) $r->order_count;
                if ( $r->last_order_date > $last_order_date ) {
                    $last_order_date = $r->last_order_date;
                }
            }

            $keep = $records[0];
            $wpdb->update(
                $table,
                array(
                    'total_spent'     => $total_spent,
                    'order_count'     => $order_count,
                    'last_order_date' => $last_order_date,
                ),
                array( 'id' => $keep->id ),
                array( '%f', '%d', '%s' ),
                array( '%d' )
            );

            $ids_to_delete = array_column( array_slice( $records, 1 ), 'id' );
            if ( ! empty( $ids_to_delete ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $ids_to_delete ), '%d' ) );
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", ...$ids_to_delete ) );
                $removed += count( $ids_to_delete );
            }
        }

        return $removed;
    }

    private static function is_hpos_enabled() {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
            return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        }
        return false;
    }
}
