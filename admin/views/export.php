<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="wrap jesp-erp-wrap">
    <div class="jesp-erp-header">
        <h1><span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Export Products', 'jesp-erp' ); ?></h1>
    </div>

    <div class="jesp-erp-card" style="max-width:600px;">
        <h2><?php esc_html_e( 'Export Settings', 'jesp-erp' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Download your product and stock data as a CSV file.', 'jesp-erp' ); ?></p>

        <div class="jesp-form-group">
            <label><?php esc_html_e( 'Category Filter', 'jesp-erp' ); ?></label>
            <select id="erp-export-category" class="jesp-select">
                <option value=""><?php esc_html_e( 'All Categories', 'jesp-erp' ); ?></option>
            </select>
        </div>

        <div class="jesp-form-group">
            <label><?php esc_html_e( 'Stock Status', 'jesp-erp' ); ?></label>
            <div class="jesp-radio-group">
                <label><input type="radio" name="export_stock_status" value="" checked> <?php esc_html_e( 'All Products', 'jesp-erp' ); ?></label>
                <label><input type="radio" name="export_stock_status" value="low"> <?php esc_html_e( 'Low Stock Only', 'jesp-erp' ); ?></label>
                <label><input type="radio" name="export_stock_status" value="sufficient"> <?php esc_html_e( 'Sufficient Stock Only', 'jesp-erp' ); ?></label>
            </div>
        </div>

        <div class="jesp-export-preview" id="erp-export-preview">
            <p><?php esc_html_e( 'Exported columns: SKU, Product Name, Warehouse Stock, Sales Center Stock, Total Stock, Min Level, Price, Status', 'jesp-erp' ); ?></p>
        </div>

        <form method="post" id="erp-export-form">
            <input type="hidden" name="action" value="erp_export_csv">
            <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'jesp_erp_nonce' ) ); ?>">
            <input type="hidden" name="category" id="erp-export-cat-hidden" value="">
            <input type="hidden" name="stock_status" id="erp-export-status-hidden" value="">
            <button type="submit" class="button button-primary button-hero" id="erp-export-btn">
                <span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Download CSV', 'jesp-erp' ); ?>
            </button>
        </form>
    </div>
</div>
