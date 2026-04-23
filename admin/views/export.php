<?php if ( ! defined( 'ABSPATH' ) ) { exit; }

$fields_config = array(
    'sku'                => array( 'label' => 'SKU',                'icon' => 'dashicons-tag',          'default' => true  ),
    'product_name'       => array( 'label' => 'Product Name',       'icon' => 'dashicons-admin-post',   'default' => true  ),
    'category'           => array( 'label' => 'Category',           'icon' => 'dashicons-category',     'default' => false ),
    'buying_price'       => array( 'label' => 'Buying Price',       'icon' => 'dashicons-cart',         'default' => false ),
    'selling_price'      => array( 'label' => 'Selling Price',      'icon' => 'dashicons-money-alt',    'default' => true  ),
    'sale_price'         => array( 'label' => 'Sale Price',         'icon' => 'dashicons-tag',          'default' => false ),
    'warehouse_stock'    => array( 'label' => 'Warehouse Stock',    'icon' => 'dashicons-archive',      'default' => true  ),
    'sales_center_stock' => array( 'label' => 'Sales Center Stock', 'icon' => 'dashicons-store',        'default' => true  ),
    'total_stock'        => array( 'label' => 'Total Stock',        'icon' => 'dashicons-database',     'default' => true  ),
    'min_level'          => array( 'label' => 'Min Stock Level',    'icon' => 'dashicons-warning',      'default' => true  ),
    'status'             => array( 'label' => 'Status',             'icon' => 'dashicons-marker',       'default' => true  ),
    'created_at'         => array( 'label' => 'Created At',         'icon' => 'dashicons-calendar-alt', 'default' => false ),
    'updated_at'         => array( 'label' => 'Updated At',         'icon' => 'dashicons-update',       'default' => false ),
);
?>
<style>
.jesp-export-field-item{display:flex;align-items:center;gap:8px;font-size:13px;padding:9px 11px;border:1px solid #e2e8f0;border-radius:6px;cursor:pointer;background:#f8fafc;transition:border-color .15s,background .15s;user-select:none;}
.jesp-export-field-item:hover{border-color:#6366f1;background:#eef2ff;}
.jesp-export-field-item input[type="checkbox"]{margin:0;cursor:pointer;accent-color:#6366f1;}
.jesp-export-field-item .dashicons{color:#6366f1;font-size:14px;width:14px;height:14px;flex-shrink:0;}
.jesp-export-field-item.is-checked{border-color:#6366f1;background:#eef2ff;}
</style>
<div class="wrap jesp-erp-wrap">
    <div class="jesp-erp-header">
        <h1><span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Export Products', 'jesp-erp' ); ?></h1>
        <p class="jesp-erp-subtitle"><?php esc_html_e( 'Choose your fields and filters, then download a CSV file.', 'jesp-erp' ); ?></p>
    </div>

    <!-- ── Field Selection ── -->
    <div class="jesp-erp-card" style="max-width:740px;margin-bottom:16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
            <h2 style="margin:0;">
                <span class="dashicons dashicons-list-view" style="color:#6366f1;margin-right:5px;vertical-align:middle;"></span>
                <?php esc_html_e( 'Select Fields', 'jesp-erp' ); ?>
            </h2>
            <label id="erp-select-all-label" style="display:flex;align-items:center;gap:6px;font-size:13px;color:#6366f1;font-weight:600;cursor:pointer;">
                <input type="checkbox" id="erp-export-select-all" style="margin:0;accent-color:#6366f1;">
                <?php esc_html_e( 'Select All', 'jesp-erp' ); ?>
            </label>
        </div>

        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;" id="erp-export-fields-grid">
            <?php foreach ( $fields_config as $key => $cfg ) : ?>
            <label class="jesp-export-field-item<?php echo $cfg['default'] ? ' is-checked' : ''; ?>">
                <input
                    type="checkbox"
                    class="erp-export-field"
                    value="<?php echo esc_attr( $key ); ?>"
                    <?php checked( $cfg['default'] ); ?>
                >
                <span class="dashicons <?php echo esc_attr( $cfg['icon'] ); ?>"></span>
                <?php echo esc_html( $cfg['label'] ); ?>
                <?php if ( $cfg['default'] ) : ?>
                    <span style="margin-left:auto;color:#a5b4fc;font-size:10px;font-weight:700;">DEFAULT</span>
                <?php endif; ?>
            </label>
            <?php endforeach; ?>
        </div>

        <p style="color:#94a3b8;font-size:11px;margin-top:10px;margin-bottom:0;">
            <?php esc_html_e( 'DEFAULT fields match the previous fixed export. At least one field must be selected.', 'jesp-erp' ); ?>
        </p>
    </div>

    <!-- ── Export Settings ── -->
    <div class="jesp-erp-card" style="max-width:740px;">
        <h2><?php esc_html_e( 'Export Settings', 'jesp-erp' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Apply optional filters before downloading.', 'jesp-erp' ); ?></p>

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

        <form method="get" id="erp-export-form" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
            <input type="hidden" name="action" value="erp_export_csv">
            <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'jesp_erp_nonce' ) ); ?>">
            <input type="hidden" name="category" id="erp-export-cat-hidden" value="">
            <input type="hidden" name="stock_status" id="erp-export-status-hidden" value="">
            <input type="hidden" name="fields" id="erp-export-fields-hidden" value="">
            <button type="submit" class="button button-primary button-hero" id="erp-export-btn">
                <span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Download CSV', 'jesp-erp' ); ?>
            </button>
        </form>
    </div>
</div>
