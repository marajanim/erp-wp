<?php if (!defined('ABSPATH')) {
    exit;
}?>
<div class="wrap jesp-erp-wrap">
    <div class="jesp-erp-header">
        <h1><span class="dashicons dashicons-clipboard"></span> <?php esc_html_e('Stock Management', 'jesp-erp'); ?></h1>
        <p class="jesp-erp-subtitle"><?php esc_html_e('Click any pen icon to edit values inline. All changes sync with WooCommerce instantly.', 'jesp-erp'); ?></p>
    </div>

    <!-- Filters -->
    <div class="jesp-erp-card jesp-erp-filters">
        <div class="jesp-filter-row">
            <div class="jesp-filter-group">
                <label><?php esc_html_e('Search', 'jesp-erp'); ?></label>
                <input type="text" id="erp-stock-search" placeholder="<?php esc_attr_e('Search by name or SKU...', 'jesp-erp'); ?>" class="jesp-input">
            </div>
            <div class="jesp-filter-group">
                <label><?php esc_html_e('Category', 'jesp-erp'); ?></label>
                <select id="erp-stock-category" class="jesp-select"><option value=""><?php esc_html_e('All Categories', 'jesp-erp'); ?></option></select>
            </div>
            <div class="jesp-filter-group">
                <label><?php esc_html_e('Stock Status', 'jesp-erp'); ?></label>
                <select id="erp-stock-status" class="jesp-select">
                    <option value=""><?php esc_html_e('All', 'jesp-erp'); ?></option>
                    <option value="low"><?php esc_html_e('Low Stock', 'jesp-erp'); ?></option>
                    <option value="sufficient"><?php esc_html_e('Sufficient', 'jesp-erp'); ?></option>
                </select>
            </div>
            <div class="jesp-filter-group">
                <label><?php esc_html_e('Per Page', 'jesp-erp'); ?></label>
                <select id="erp-stock-per-page" class="jesp-select">
                    <option value="20">20</option><option value="50">50</option><option value="100">100</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Stock Table with Inline Editing -->
    <div class="jesp-erp-card">
        <div class="jesp-table-responsive">
            <table class="jesp-table" id="erp-stock-table">
                <thead>
                    <tr>
                        <th class="jesp-col-img"><?php esc_html_e('Image', 'jesp-erp'); ?></th>
                        <th class="jesp-sortable" data-sort="product_name"><?php esc_html_e('Product Name', 'jesp-erp'); ?> <span class="jesp-sort-icon"></span></th>
                        <th><?php esc_html_e('SKU', 'jesp-erp'); ?></th>
                        <th><?php esc_html_e('Regular Price', 'jesp-erp'); ?></th>
                        <th><?php esc_html_e('Sale Price', 'jesp-erp'); ?></th>
                        <th class="jesp-sortable" data-sort="warehouse"><?php esc_html_e('Warehouse', 'jesp-erp'); ?> <span class="jesp-sort-icon"></span></th>
                        <th class="jesp-sortable" data-sort="sales_center"><?php esc_html_e('Sales Center', 'jesp-erp'); ?> <span class="jesp-sort-icon"></span></th>
                        <th class="jesp-sortable" data-sort="total_qty"><?php esc_html_e('Total', 'jesp-erp'); ?> <span class="jesp-sort-icon"></span></th>
                        <th><?php esc_html_e('Buying Price', 'jesp-erp'); ?></th>
                        <th><?php esc_html_e('Min Level', 'jesp-erp'); ?></th>
                        <th><?php esc_html_e('Status', 'jesp-erp'); ?></th>
                        <th><?php esc_html_e('Active', 'jesp-erp'); ?></th>
                        <th><?php esc_html_e('Actions', 'jesp-erp'); ?></th>
                    </tr>
                </thead>
                <tbody id="erp-stock-body">
                    <tr><td colspan="13" class="jesp-loading"><?php esc_html_e('Loading...', 'jesp-erp'); ?></td></tr>
                </tbody>
            </table>
        </div>
        <div class="jesp-pagination" id="erp-stock-pagination"></div>
    </div>
</div>

<!-- Quick Stock Edit Modal (kept for bulk/advanced adjustments) -->
<div id="erp-stock-modal" class="jesp-modal" style="display:none;">
    <div class="jesp-modal-overlay"></div>
    <div class="jesp-modal-content">
        <div class="jesp-modal-header">
            <h3><?php esc_html_e('Quick Stock Update', 'jesp-erp'); ?></h3>
            <button class="jesp-modal-close">&times;</button>
        </div>
        <div class="jesp-modal-body">
            <input type="hidden" id="modal-product-id">
            <p class="jesp-modal-product-name" id="modal-product-name"></p>
            <div class="jesp-form-group">
                <label><?php esc_html_e('Location', 'jesp-erp'); ?></label>
                <select id="modal-location" class="jesp-select">
                    <option value="warehouse"><?php esc_html_e('Warehouse', 'jesp-erp'); ?></option>
                    <option value="sales_center"><?php esc_html_e('Sales Center', 'jesp-erp'); ?></option>
                </select>
            </div>
            <div class="jesp-form-group">
                <label><?php esc_html_e('Adjustment Mode', 'jesp-erp'); ?></label>
                <select id="modal-mode" class="jesp-select">
                    <option value="set"><?php esc_html_e('Set to value', 'jesp-erp'); ?></option>
                    <option value="add"><?php esc_html_e('Add to stock', 'jesp-erp'); ?></option>
                    <option value="subtract"><?php esc_html_e('Subtract from stock', 'jesp-erp'); ?></option>
                </select>
            </div>
            <div class="jesp-form-group">
                <label><?php esc_html_e('Quantity', 'jesp-erp'); ?></label>
                <input type="number" id="modal-quantity" class="jesp-input" min="0" step="1" value="0">
            </div>
            <div class="jesp-form-group">
                <label><?php esc_html_e('Reason', 'jesp-erp'); ?></label>
                <input type="text" id="modal-reason" class="jesp-input" placeholder="<?php esc_attr_e('e.g. Manual adjustment, Recount...', 'jesp-erp'); ?>">
            </div>
            <div class="jesp-form-group">
                <label><?php esc_html_e('Min Stock Level', 'jesp-erp'); ?></label>
                <input type="number" id="modal-min-stock" class="jesp-input" min="0" step="1" value="0">
            </div>
        </div>
        <div class="jesp-modal-footer">
            <button class="button button-secondary jesp-modal-close"><?php esc_html_e('Cancel', 'jesp-erp'); ?></button>
            <button class="button button-primary" id="erp-save-stock"><?php esc_html_e('Save Changes', 'jesp-erp'); ?></button>
        </div>
    </div>
</div>

<!-- Quick Edit Product Modal -->
<div id="erp-quick-edit-modal" class="jesp-modal" style="display:none;">
    <div class="jesp-modal-overlay"></div>
    <div class="jesp-modal-content" style="max-width:560px;">
        <div class="jesp-modal-header">
            <h3><span class="dashicons dashicons-edit"></span> <?php esc_html_e('Quick Edit Product', 'jesp-erp'); ?></h3>
            <button class="jesp-modal-close">&times;</button>
        </div>
        <div class="jesp-modal-body">
            <input type="hidden" id="qe-product-id">
            <div class="jesp-form-group">
                <label><?php esc_html_e('Product Title', 'jesp-erp'); ?></label>
                <input type="text" id="qe-title" class="jesp-input">
            </div>
            <div class="jesp-form-group">
                <label><?php esc_html_e('Description', 'jesp-erp'); ?></label>
                <textarea id="qe-description" class="jesp-input" rows="4"></textarea>
            </div>
            <div class="jesp-form-group">
                <label><?php esc_html_e('Product Image', 'jesp-erp'); ?></label>
                <div style="display:flex;align-items:center;gap:12px;">
                    <img id="qe-image-preview" src="" style="width:60px;height:60px;object-fit:cover;border-radius:8px;border:2px solid #e2e8f0;display:none;">
                    <input type="hidden" id="qe-image-id" value="0">
                    <button class="button" id="qe-select-image"><?php esc_html_e('Select Image', 'jesp-erp'); ?></button>
                    <button class="button" id="qe-remove-image" style="display:none;"><?php esc_html_e('Remove', 'jesp-erp'); ?></button>
                </div>
            </div>
            <div class="jesp-form-group">
                <label><?php esc_html_e('Category', 'jesp-erp'); ?></label>
                <select id="qe-category" class="jesp-select">
                    <option value=""><?php esc_html_e('Select Category', 'jesp-erp'); ?></option>
                </select>
            </div>
        </div>
        <div class="jesp-modal-footer">
            <button class="button button-secondary jesp-modal-close"><?php esc_html_e('Cancel', 'jesp-erp'); ?></button>
            <button class="button button-primary" id="qe-save"><?php esc_html_e('Save Changes', 'jesp-erp'); ?></button>
        </div>
    </div>
</div>

<!-- Order Detail Modal (used by orders page) -->
<div id="erp-order-detail-modal" class="jesp-modal" style="display:none;">
    <div class="jesp-modal-overlay"></div>
    <div class="jesp-modal-content" style="max-width:640px;">
        <div class="jesp-modal-header">
            <h3><span class="dashicons dashicons-cart"></span> <?php esc_html_e('Order Details', 'jesp-erp'); ?> <span id="od-order-id"></span></h3>
            <button class="jesp-modal-close">&times;</button>
        </div>
        <div class="jesp-modal-body" id="od-body">
            <p class="jesp-loading"><?php esc_html_e('Loading...', 'jesp-erp'); ?></p>
        </div>
    </div>
</div>
