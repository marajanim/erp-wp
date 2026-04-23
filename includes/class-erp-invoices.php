<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Invoice Maker — database operations and print HTML generation.
 */
class JESP_ERP_Invoices {

    public static function invoices_table() {
        global $wpdb;
        return $wpdb->prefix . 'jesp_erp_invoices';
    }

    public static function items_table() {
        global $wpdb;
        return $wpdb->prefix . 'jesp_erp_invoice_items';
    }

    /* ------------------------------------------------------------------ */
    /*  Table creation (idempotent via dbDelta)                            */
    /* ------------------------------------------------------------------ */
    public static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $t1 = self::invoices_table();
        dbDelta( "CREATE TABLE {$t1} (
            id               BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_number   VARCHAR(50)   NOT NULL DEFAULT '',
            customer_name    VARCHAR(200)  NOT NULL DEFAULT '',
            customer_phone   VARCHAR(50)   NOT NULL DEFAULT '',
            customer_email   VARCHAR(200)  NOT NULL DEFAULT '',
            customer_address TEXT          NOT NULL,
            invoice_date     DATE          NOT NULL,
            subtotal         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            discount_type    VARCHAR(20)   NOT NULL DEFAULT 'none',
            discount_value   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            tax_rate         DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
            total            DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            notes            TEXT          NOT NULL,
            status           VARCHAR(20)   NOT NULL DEFAULT 'draft',
            created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY invoice_number (invoice_number),
            KEY idx_status (status),
            KEY idx_invoice_date (invoice_date)
        ) {$charset};" );

        $t2 = self::items_table();
        dbDelta( "CREATE TABLE {$t2} (
            id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_id   BIGINT(20) UNSIGNED NOT NULL,
            product_name VARCHAR(200)  NOT NULL DEFAULT '',
            sku          VARCHAR(100)  NOT NULL DEFAULT '',
            qty          DECIMAL(10,2) NOT NULL DEFAULT 1.00,
            unit_price   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            line_total   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY  (id),
            KEY idx_invoice_id (invoice_id)
        ) {$charset};" );
    }

    /* ------------------------------------------------------------------ */
    /*  Auto-generate invoice number: INV-YYYY-NNNN                       */
    /* ------------------------------------------------------------------ */
    public static function next_invoice_number() {
        global $wpdb;
        $year   = gmdate( 'Y' );
        $prefix = 'INV-' . $year . '-';
        $last   = $wpdb->get_var( $wpdb->prepare(
            'SELECT invoice_number FROM ' . self::invoices_table() . ' WHERE invoice_number LIKE %s ORDER BY id DESC LIMIT 1',
            $wpdb->esc_like( $prefix ) . '%'
        ) );
        $seq = $last ? ( (int) substr( $last, strlen( $prefix ) ) + 1 ) : 1;
        return $prefix . str_pad( $seq, 4, '0', STR_PAD_LEFT );
    }

    /* ------------------------------------------------------------------ */
    /*  List                                                               */
    /* ------------------------------------------------------------------ */
    public static function get_invoices( $args = array() ) {
        global $wpdb;
        $args  = wp_parse_args( $args, array( 'per_page' => 20, 'page' => 1, 'search' => '', 'status' => '' ) );
        $where = '1=1';
        if ( ! empty( $args['status'] ) && in_array( $args['status'], array( 'draft', 'paid' ), true ) ) {
            $where .= $wpdb->prepare( ' AND status = %s', $args['status'] );
        }
        return JESP_ERP_Database::paginate( self::invoices_table(), array(
            'where'      => $where,
            'orderby'    => 'created_at',
            'order'      => 'DESC',
            'per_page'   => (int) $args['per_page'],
            'page'       => (int) $args['page'],
            'search_col' => ! empty( $args['search'] ) ? 'customer_name' : '',
            'search_val' => $args['search'],
        ) );
    }

    /* ------------------------------------------------------------------ */
    /*  Single invoice with items                                          */
    /* ------------------------------------------------------------------ */
    public static function get_invoice( $id ) {
        global $wpdb;
        $invoice = JESP_ERP_Database::get_by_id( self::invoices_table(), $id );
        if ( ! $invoice ) return null;
        $invoice->items = $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . self::items_table() . ' WHERE invoice_id = %d ORDER BY id ASC',
            (int) $id
        ) );
        return $invoice;
    }

    /* ------------------------------------------------------------------ */
    /*  Save (create or update)                                            */
    /* ------------------------------------------------------------------ */
    public static function save_invoice( $data, $items ) {
        global $wpdb;
        $invoice_id = absint( $data['id'] ?? 0 );

        $row = array(
            'invoice_number'   => sanitize_text_field( $data['invoice_number']   ?? '' ),
            'customer_name'    => sanitize_text_field( $data['customer_name']    ?? '' ),
            'customer_phone'   => sanitize_text_field( $data['customer_phone']   ?? '' ),
            'customer_email'   => sanitize_email(      $data['customer_email']   ?? '' ),
            'customer_address' => sanitize_textarea_field( $data['customer_address'] ?? '' ),
            'invoice_date'     => sanitize_text_field( $data['invoice_date']     ?? gmdate( 'Y-m-d' ) ),
            'subtotal'         => round( floatval( $data['subtotal']       ?? 0 ), 2 ),
            'discount_type'    => in_array( $data['discount_type'] ?? '', array( 'none', 'percentage', 'fixed' ), true )
                                    ? $data['discount_type'] : 'none',
            'discount_value'   => round( floatval( $data['discount_value'] ?? 0 ), 2 ),
            'tax_rate'         => round( floatval( $data['tax_rate']       ?? 0 ), 2 ),
            'total'            => round( floatval( $data['total']          ?? 0 ), 2 ),
            'notes'            => sanitize_textarea_field( $data['notes']  ?? '' ),
            'status'           => in_array( $data['status'] ?? '', array( 'draft', 'paid' ), true )
                                    ? $data['status'] : 'draft',
        );

        if ( $invoice_id ) {
            $wpdb->update( self::invoices_table(), $row, array( 'id' => $invoice_id ) );
        } else {
            if ( empty( $row['invoice_number'] ) ) {
                $row['invoice_number'] = self::next_invoice_number();
            }
            $row['created_at'] = gmdate( 'Y-m-d H:i:s' );
            $wpdb->insert( self::invoices_table(), $row );
            $invoice_id = (int) $wpdb->insert_id;
        }

        if ( $invoice_id && is_array( $items ) ) {
            $wpdb->delete( self::items_table(), array( 'invoice_id' => $invoice_id ), array( '%d' ) );
            foreach ( $items as $item ) {
                if ( empty( $item['product_name'] ) ) continue;
                $qty   = round( floatval( $item['qty']        ?? 1 ), 2 );
                $price = round( floatval( $item['unit_price'] ?? 0 ), 2 );
                $wpdb->insert( self::items_table(), array(
                    'invoice_id'   => $invoice_id,
                    'product_name' => sanitize_text_field( $item['product_name'] ),
                    'sku'          => sanitize_text_field( $item['sku'] ?? '' ),
                    'qty'          => $qty,
                    'unit_price'   => $price,
                    'line_total'   => round( $qty * $price, 2 ),
                ) );
            }
        }

        return $invoice_id;
    }

    /* ------------------------------------------------------------------ */
    /*  Delete                                                             */
    /* ------------------------------------------------------------------ */
    public static function delete_invoice( $id ) {
        global $wpdb;
        $wpdb->delete( self::items_table(),   array( 'invoice_id' => $id ), array( '%d' ) );
        return $wpdb->delete( self::invoices_table(), array( 'id' => $id ), array( '%d' ) );
    }

    /* ------------------------------------------------------------------ */
    /*  Generate printable HTML page                                       */
    /* ------------------------------------------------------------------ */
    public static function generate_print_html( $invoice ) {
        $store_name    = get_bloginfo( 'name' );
        $currency      = get_woocommerce_currency_symbol();
        $store_address = '';

        if ( class_exists( 'WooCommerce' ) && WC()->countries ) {
            $parts = array_filter( array(
                WC()->countries->get_base_address(),
                WC()->countries->get_base_city(),
                WC()->countries->get_base_postcode(),
            ) );
            $store_address = implode( ', ', $parts );
        }

        $fmt = function ( $val ) use ( $currency ) {
            return $currency . number_format( (float) $val, 2 );
        };

        $subtotal = (float) $invoice->subtotal;
        if ( $invoice->discount_type === 'percentage' ) {
            $discount_amt = round( $subtotal * ( (float) $invoice->discount_value / 100 ), 2 );
        } elseif ( $invoice->discount_type === 'fixed' ) {
            $discount_amt = min( (float) $invoice->discount_value, $subtotal );
        } else {
            $discount_amt = 0;
        }
        $after_discount = $subtotal - $discount_amt;
        $tax_amt        = round( $after_discount * ( (float) $invoice->tax_rate / 100 ), 2 );

        $status_label = $invoice->status === 'paid' ? 'PAID' : 'DRAFT';
        $status_color = $invoice->status === 'paid' ? '#16a34a' : '#64748b';

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Invoice <?php echo esc_html( $invoice->invoice_number ); ?> — <?php echo esc_html( $store_name ); ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#1e293b;background:#fff;padding:32px 40px;}
.inv-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:26px;padding-bottom:18px;border-bottom:3px solid #6366f1;}
.inv-store-name{font-size:22px;font-weight:700;color:#6366f1;margin-bottom:4px;}
.inv-store-sub{font-size:11px;color:#64748b;}
.inv-title-block{text-align:right;}
.inv-title{font-size:30px;font-weight:700;letter-spacing:2px;color:#1e293b;}
.inv-num{font-size:13px;color:#64748b;margin-top:4px;}
.inv-badge{display:inline-block;margin-top:8px;padding:3px 14px;border-radius:20px;font-size:11px;font-weight:700;color:#fff;}
.inv-parties{display:flex;gap:20px;margin-bottom:22px;}
.inv-party{flex:1;border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px;background:#f8fafc;}
.inv-party h4{font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.6px;margin-bottom:8px;}
.inv-party p{font-size:13px;line-height:1.8;}
.inv-party strong{display:block;font-size:14px;color:#1e293b;margin-bottom:2px;}
table{width:100%;border-collapse:collapse;margin-bottom:20px;}
thead{background:#f1f5f9;}
th{padding:9px 12px;text-align:left;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.4px;border-bottom:2px solid #e2e8f0;}
th.r{text-align:right;}
td{padding:9px 12px;border-bottom:1px solid #f1f5f9;vertical-align:middle;font-size:13px;}
td.r{text-align:right;}
tbody tr:last-child td{border-bottom:none;}
.totals-wrap{display:flex;justify-content:flex-end;margin-bottom:20px;}
.totals{width:300px;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;}
.total-row{display:flex;justify-content:space-between;padding:8px 14px;font-size:13px;border-bottom:1px solid #f1f5f9;}
.total-row:last-child{border-bottom:none;}
.total-row.grand{background:#6366f1;color:#fff;font-size:15px;font-weight:700;}
.total-row .lbl{color:#64748b;}
.total-row.grand .lbl{color:rgba(255,255,255,.8);}
.notes{border:1px solid #e2e8f0;border-radius:6px;padding:12px 16px;margin-bottom:22px;background:#f8fafc;}
.notes h4{font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px;}
.notes p{font-size:12px;color:#64748b;line-height:1.7;}
.inv-footer{text-align:center;font-size:11px;color:#94a3b8;padding-top:14px;border-top:1px solid #f1f5f9;}
.print-bar{text-align:center;margin-bottom:22px;}
.print-bar button{padding:8px 20px;font-size:13px;border:none;border-radius:6px;cursor:pointer;margin:0 4px;}
.print-bar .btn-p{background:#6366f1;color:#fff;}
.print-bar .btn-c{background:#f1f5f9;color:#374151;}
@media print{.print-bar{display:none;} body{padding:0;}}
</style>
</head>
<body>
<div class="print-bar">
    <button class="btn-p" onclick="window.print()">🖨 Print Invoice</button>
    <button class="btn-c" onclick="window.close()">✕ Close</button>
</div>

<div class="inv-header">
    <div>
        <div class="inv-store-name"><?php echo esc_html( $store_name ); ?></div>
        <?php if ( $store_address ) : ?><div class="inv-store-sub"><?php echo esc_html( $store_address ); ?></div><?php endif; ?>
    </div>
    <div class="inv-title-block">
        <div class="inv-title">INVOICE</div>
        <div class="inv-num"><?php echo esc_html( $invoice->invoice_number ); ?></div>
        <span class="inv-badge" style="background:<?php echo esc_attr( $status_color ); ?>;"><?php echo esc_html( $status_label ); ?></span>
    </div>
</div>

<div class="inv-parties">
    <div class="inv-party">
        <h4>Bill To</h4>
        <p>
            <strong><?php echo esc_html( $invoice->customer_name ?: '—' ); ?></strong>
            <?php if ( $invoice->customer_phone ) : ?><?php echo esc_html( $invoice->customer_phone ); ?><br><?php endif; ?>
            <?php if ( $invoice->customer_email ) : ?><?php echo esc_html( $invoice->customer_email ); ?><br><?php endif; ?>
            <?php if ( $invoice->customer_address ) : ?><?php echo nl2br( esc_html( $invoice->customer_address ) ); ?><?php endif; ?>
        </p>
    </div>
    <div class="inv-party">
        <h4>Invoice Details</h4>
        <p>
            <strong><?php echo esc_html( $invoice->invoice_number ); ?></strong>
            <b>Date:</b> <?php echo esc_html( wp_date( 'F j, Y', strtotime( $invoice->invoice_date ) ) ); ?><br>
            <b>Status:</b> <?php echo esc_html( ucfirst( $invoice->status ) ); ?>
        </p>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th style="width:36px;">#</th>
            <th>Product / Description</th>
            <th style="width:110px;">SKU</th>
            <th class="r" style="width:70px;">Qty</th>
            <th class="r" style="width:120px;">Unit Price</th>
            <th class="r" style="width:120px;">Total</th>
        </tr>
    </thead>
    <tbody>
        <?php $i = 1; foreach ( $invoice->items as $item ) : ?>
        <tr>
            <td style="color:#94a3b8;"><?php echo esc_html( $i++ ); ?></td>
            <td><?php echo esc_html( $item->product_name ); ?></td>
            <td style="color:#94a3b8;"><?php echo esc_html( $item->sku ?: '—' ); ?></td>
            <td class="r"><?php echo esc_html( rtrim( rtrim( number_format( (float) $item->qty, 2 ), '0' ), '.' ) ); ?></td>
            <td class="r"><?php echo esc_html( $fmt( $item->unit_price ) ); ?></td>
            <td class="r" style="font-weight:600;"><?php echo esc_html( $fmt( $item->line_total ) ); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="totals-wrap">
    <div class="totals">
        <div class="total-row"><span class="lbl">Subtotal</span><span><?php echo esc_html( $fmt( $subtotal ) ); ?></span></div>
        <?php if ( $discount_amt > 0 ) : ?>
        <div class="total-row">
            <span class="lbl">Discount<?php echo $invoice->discount_type === 'percentage' ? ' (' . esc_html( $invoice->discount_value ) . '%)' : ''; ?></span>
            <span style="color:#dc2626;">-<?php echo esc_html( $fmt( $discount_amt ) ); ?></span>
        </div>
        <?php endif; ?>
        <?php if ( (float) $invoice->tax_rate > 0 ) : ?>
        <div class="total-row"><span class="lbl">Tax (<?php echo esc_html( $invoice->tax_rate ); ?>%)</span><span><?php echo esc_html( $fmt( $tax_amt ) ); ?></span></div>
        <?php endif; ?>
        <div class="total-row grand"><span class="lbl">TOTAL</span><span><?php echo esc_html( $fmt( $invoice->total ) ); ?></span></div>
    </div>
</div>

<?php if ( ! empty( $invoice->notes ) ) : ?>
<div class="notes">
    <h4>Notes</h4>
    <p><?php echo nl2br( esc_html( $invoice->notes ) ); ?></p>
</div>
<?php endif; ?>

<div class="inv-footer">Thank you for your business! &bull; <?php echo esc_html( $store_name ); ?></div>
</body>
</html>
        <?php
        return ob_get_clean();
    }
}
