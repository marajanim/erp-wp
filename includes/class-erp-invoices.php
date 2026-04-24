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
        // Load company settings (falls back to WP site defaults when empty).
        $co = (array) get_option( 'jesp_erp_invoice_company', array() );

        $store_name    = ! empty( $co['name'] ) ? $co['name'] : get_bloginfo( 'name' );
        $store_tagline = get_bloginfo( 'description' );
        $co_address    = $co['address'] ?? '';
        $co_phone      = $co['phone']   ?? '';
        $co_email      = $co['email']   ?? '';
        $inv_footer    = $co['footer']  ?? '';
        $inv_terms     = $co['terms']   ?? '';
        $currency      = get_woocommerce_currency_symbol();

        // Resolve logo: custom option → theme logo → site icon → text fallback.
        $logo_url = '';
        if ( ! empty( $co['logo_url'] ) ) {
            $logo_url = esc_url( $co['logo_url'] );
        }
        if ( ! $logo_url ) {
            $custom_logo_id = get_theme_mod( 'custom_logo' );
            if ( $custom_logo_id ) {
                $logo_src = wp_get_attachment_image_src( $custom_logo_id, array( 160, 80 ) );
                if ( $logo_src ) {
                    $logo_url = $logo_src[0];
                }
            }
        }
        if ( ! $logo_url ) {
            $logo_url = get_site_icon_url( 80 );
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
        $is_paid        = $invoice->status === 'paid';

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice <?php echo esc_html( $invoice->invoice_number ); ?> — <?php echo esc_html( $store_name ); ?></title>
<style>
*{box-sizing:border-box;}
body{font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background-color:#f0f0f0;margin:0;padding:20px;font-size:14px;color:#222;}
h1,h2,h3,h4,p{margin:0;padding:0;}
.invoice-box{max-width:800px;margin:auto;background:#fff;padding:40px;border:1px solid #ddd;box-shadow:0 0 10px rgba(0,0,0,.1);}

/* Header */
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:0;}
.title{font-size:42px;font-weight:bold;color:#2c3e50;line-height:1;}
.logo-wrap img{width:80px;height:80px;border-radius:50%;border:2px solid #ccc;object-fit:cover;}
.logo-wrap-text{font-size:18px;font-weight:700;color:#2c3e50;border:2px solid #ccc;border-radius:50%;width:80px;height:80px;display:flex;align-items:center;justify-content:center;text-align:center;padding:6px;font-size:11px;}

/* Company name */
.company-name{text-align:center;margin:18px 0 10px;}
.company-name h1{font-size:22px;font-weight:bold;letter-spacing:3px;color:#1a1a1a;}
.tagline{display:inline-block;background:#e0e0e0;padding:3px 18px;border-radius:12px;font-size:13px;font-weight:bold;margin-top:6px;}

/* Meta */
.meta-info{display:flex;justify-content:space-between;margin-top:24px;line-height:1.8;font-size:14px;}
.meta-info p{margin:0;padding:0;}

/* Addresses */
.address-container{display:flex;justify-content:space-between;margin-top:24px;line-height:1.7;font-size:14px;}
.address-block{width:45%;}
.address-block h3{font-size:15px;font-weight:bold;color:#1a1a1a;border-bottom:2px solid #333;display:inline-block;padding-bottom:2px;margin-bottom:8px;}
.address-block p{margin:0;padding:0;}
.blue-text{color:#0044cc;}

/* Items table */
.items-table{width:100%;border-collapse:collapse;margin-top:28px;font-size:14px;}
.items-table th,.items-table td{border:1px solid #333;padding:10px 12px;text-align:left;vertical-align:top;}
.items-table thead tr{background:#f9f9f9;}
.items-table tfoot tr td{border:1px solid #333;}
.center{text-align:center;}
.label{font-weight:bold;}

/* Footer */
.footer{margin-top:48px;display:flex;justify-content:space-between;align-items:flex-end;}
.stamp{border:3px solid #b22222;color:#b22222;padding:6px 12px;font-weight:bold;font-size:13px;transform:rotate(-15deg);display:inline-block;margin-left:12px;text-transform:uppercase;line-height:1.3;text-align:center;}
.store-sig{font-size:14px;font-weight:600;color:#333;}
.cursive{font-family:'Brush Script MT',cursive;font-size:26px;font-weight:normal;}

/* Notes */
.notes-block{margin-top:18px;padding:10px 14px;border:1px solid #ddd;background:#fafafa;font-size:13px;line-height:1.6;}

/* Print bar (hidden on print) */
.print-bar{text-align:center;margin-bottom:22px;}
.print-bar button{padding:9px 22px;font-size:13px;border:none;border-radius:6px;cursor:pointer;margin:0 5px;font-family:inherit;}
.btn-p{background:#2c3e50;color:#fff;}
.btn-c{background:#e0e0e0;color:#333;}
@page{size:A4 portrait;margin:15mm;}
@media print{.print-bar{display:none;}body{background:#fff;padding:0;margin:0;}html{background:#fff;}.invoice-box{max-width:100%;margin:0;padding:0;border:none;box-shadow:none;}}
</style>
</head>
<body>

<div class="print-bar">
    <button class="btn-p" onclick="window.print()">&#128438; Print Invoice</button>
    <button class="btn-c" onclick="window.close()">&#10005; Close</button>
</div>

<div class="invoice-box">

    <!-- Header: INVOICE + Logo -->
    <div class="header">
        <div class="title">INVOICE</div>
        <div class="logo-wrap">
            <?php if ( $logo_url ) : ?>
            <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $store_name ); ?>">
            <?php else : ?>
            <div class="logo-wrap-text"><?php echo esc_html( $store_name ); ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Store name + tagline -->
    <div class="company-name">
        <h1><?php echo esc_html( strtoupper( $store_name ) ); ?></h1>
        <?php if ( $store_tagline ) : ?>
        <div><span class="tagline"><?php echo esc_html( strtoupper( $store_tagline ) ); ?></span></div>
        <?php endif; ?>
    </div>

    <!-- Meta info: two columns -->
    <div class="meta-info">
        <div class="meta-left">
            <p><strong>Date:</strong> <?php echo esc_html( wp_date( 'F j, Y', strtotime( $invoice->invoice_date ) ) ); ?></p>
            <p><strong>Payment method:</strong> <?php echo esc_html( $is_paid ? 'Paid' : 'Pending payment' ); ?></p>
            <?php if ( $invoice->customer_email ) : ?>
            <p><strong>Email:</strong> <?php echo esc_html( $invoice->customer_email ); ?></p>
            <?php endif; ?>
        </div>
        <div class="meta-right">
            <p><strong>Invoice No:</strong> #<?php echo esc_html( $invoice->invoice_number ); ?></p>
            <?php if ( $invoice->customer_phone ) : ?>
            <p><strong>Phone:</strong> <?php echo esc_html( $invoice->customer_phone ); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Addresses: Issued to / Ship to / Issued from (company) -->
    <div class="address-container">
        <div class="address-block">
            <h3>Issued to</h3>
            <p><?php echo esc_html( $invoice->customer_name ?: '—' ); ?></p>
            <?php if ( $invoice->customer_address ) :
                $addr_lines = explode( "\n", $invoice->customer_address );
                foreach ( $addr_lines as $line ) :
                    $line = trim( $line );
                    if ( $line !== '' ) : ?>
            <p><?php echo esc_html( $line ); ?></p>
                    <?php endif;
                endforeach;
            endif; ?>
            <?php if ( $invoice->customer_phone ) : ?>
            <p class="blue-text"><?php echo esc_html( $invoice->customer_phone ); ?></p>
            <?php endif; ?>
            <?php if ( $invoice->customer_email ) : ?>
            <p><?php echo esc_html( $invoice->customer_email ); ?></p>
            <?php endif; ?>
        </div>
        <div class="address-block">
            <h3>Ship to</h3>
            <p><?php echo esc_html( $invoice->customer_name ?: '—' ); ?></p>
            <?php if ( $invoice->customer_address ) :
                $addr_lines = explode( "\n", $invoice->customer_address );
                foreach ( $addr_lines as $line ) :
                    $line = trim( $line );
                    if ( $line !== '' ) : ?>
            <p><?php echo esc_html( $line ); ?></p>
                    <?php endif;
                endforeach;
            endif; ?>
        </div>
        <?php if ( $co_address || $co_phone || $co_email ) : ?>
        <div class="address-block">
            <h3>Issued from</h3>
            <p><?php echo esc_html( $store_name ); ?></p>
            <?php if ( $co_address ) :
                $co_addr_lines = explode( "\n", $co_address );
                foreach ( $co_addr_lines as $co_line ) :
                    $co_line = trim( $co_line );
                    if ( $co_line !== '' ) : ?>
            <p><?php echo esc_html( $co_line ); ?></p>
                    <?php endif;
                endforeach;
            endif; ?>
            <?php if ( $co_phone ) : ?>
            <p class="blue-text"><?php echo esc_html( $co_phone ); ?></p>
            <?php endif; ?>
            <?php if ( $co_email ) : ?>
            <p><?php echo esc_html( $co_email ); ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Line items table -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:90px;">SKU</th>
                <th>Product</th>
                <th class="center" style="width:90px;">Quantity</th>
                <th style="width:120px;">Price</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $invoice->items as $item ) : ?>
            <tr>
                <td><?php echo esc_html( $item->sku ?: '—' ); ?></td>
                <td><?php echo esc_html( $item->product_name ); ?></td>
                <td class="center"><?php echo esc_html( rtrim( rtrim( number_format( (float) $item->qty, 2 ), '0' ), '.' ) ); ?></td>
                <td><?php echo esc_html( $fmt( $item->line_total ) ); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="label">Subtotal:</td>
                <td><?php echo esc_html( $fmt( $subtotal ) ); ?></td>
            </tr>
            <?php if ( $discount_amt > 0 ) : ?>
            <tr>
                <td colspan="3" class="label">Discount<?php echo $invoice->discount_type === 'percentage' ? ' (' . esc_html( $invoice->discount_value ) . '%)' : ''; ?>:</td>
                <td style="color:#b22222;">- <?php echo esc_html( $fmt( $discount_amt ) ); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ( (float) $invoice->tax_rate > 0 ) : ?>
            <tr>
                <td colspan="3" class="label">Tax (<?php echo esc_html( $invoice->tax_rate ); ?>%):</td>
                <td><?php echo esc_html( $fmt( $tax_amt ) ); ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td colspan="3" class="label">Total:</td>
                <td><strong><?php echo esc_html( $fmt( (float) $invoice->total ) ); ?></strong></td>
            </tr>
            <tr>
                <td colspan="3" class="label">Payment method:</td>
                <td><?php echo esc_html( $is_paid ? 'Paid in full' : 'Pending payment' ); ?></td>
            </tr>
        </tfoot>
    </table>

    <!-- Notes -->
    <?php if ( ! empty( $invoice->notes ) ) : ?>
    <div class="notes-block">
        <strong>Notes:</strong> <?php echo nl2br( esc_html( $invoice->notes ) ); ?>
    </div>
    <?php endif; ?>

    <!-- Terms & Conditions -->
    <?php if ( ! empty( $inv_terms ) ) : ?>
    <div class="notes-block" style="margin-top:10px;">
        <strong>Terms &amp; Conditions:</strong> <?php echo nl2br( esc_html( $inv_terms ) ); ?>
    </div>
    <?php endif; ?>

    <!-- Dynamic footer text -->
    <?php if ( ! empty( $inv_footer ) ) : ?>
    <div style="text-align:center;margin-top:18px;font-size:13px;color:#64748b;font-style:italic;">
        <?php echo esc_html( $inv_footer ); ?>
    </div>
    <?php endif; ?>

    <!-- Footer: stamp + store signature -->
    <div class="footer">
        <div class="payment-info" style="display:flex;align-items:center;">
            <span style="font-weight:600;font-size:13px;">PAYMENT INFO:</span>
            <?php if ( $is_paid ) : ?>
            <div class="stamp">PAYMENT<br>RECEIVED</div>
            <?php endif; ?>
        </div>
        <div class="store-sig">
            STORE: <span class="cursive"><?php echo esc_html( $store_name ); ?></span>
        </div>
    </div>

</div>
</body>
</html>
        <?php
        return ob_get_clean();
    }
}
