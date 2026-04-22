<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="wrap jesp-erp-wrap">
    <div class="jesp-erp-header">
        <h1><span class="dashicons dashicons-tag"></span> <?php esc_html_e( 'Bulk Discounts', 'jesp-erp' ); ?></h1>
    </div>

    <div class="jesp-erp-row">
        <!-- Create New Discount -->
        <div class="jesp-erp-col-6">
            <div class="jesp-erp-card">
                <h2><?php esc_html_e( 'Create New Discount', 'jesp-erp' ); ?></h2>
                <div class="jesp-form-group">
                    <label><?php esc_html_e( 'Discount Name', 'jesp-erp' ); ?></label>
                    <input type="text" id="discount-name" class="jesp-input" placeholder="<?php esc_attr_e( 'e.g. Summer Sale 2025', 'jesp-erp' ); ?>">
                </div>
                <div class="jesp-form-group">
                    <label><?php esc_html_e( 'Discount Type', 'jesp-erp' ); ?></label>
                    <select id="discount-type" class="jesp-select">
                        <option value="percentage"><?php esc_html_e( 'Percentage (%)', 'jesp-erp' ); ?></option>
                        <option value="fixed"><?php esc_html_e( 'Fixed Amount', 'jesp-erp' ); ?></option>
                    </select>
                </div>
                <div class="jesp-form-group">
                    <label><?php esc_html_e( 'Discount Value', 'jesp-erp' ); ?></label>
                    <input type="number" id="discount-value" class="jesp-input" min="0" step="0.01" placeholder="10">
                </div>
                <hr>
                <h3><?php esc_html_e( 'Filters (Optional)', 'jesp-erp' ); ?></h3>
                <div class="jesp-form-group">
                    <label><?php esc_html_e( 'Category', 'jesp-erp' ); ?></label>
                    <select id="discount-category" class="jesp-select">
                        <option value=""><?php esc_html_e( 'All Categories', 'jesp-erp' ); ?></option>
                    </select>
                </div>
                <div class="jesp-form-row">
                    <div class="jesp-form-group jesp-form-half">
                        <label><?php esc_html_e( 'Min Stock Qty', 'jesp-erp' ); ?></label>
                        <input type="number" id="discount-min-stock" class="jesp-input" min="0" placeholder="0">
                    </div>
                    <div class="jesp-form-group jesp-form-half">
                        <label><?php esc_html_e( 'Max Stock Qty', 'jesp-erp' ); ?></label>
                        <input type="number" id="discount-max-stock" class="jesp-input" min="0" placeholder="999">
                    </div>
                </div>
                <div class="jesp-form-row">
                    <div class="jesp-form-group jesp-form-half">
                        <label><?php esc_html_e( 'Start Date', 'jesp-erp' ); ?></label>
                        <input type="date" id="discount-start-date" class="jesp-input">
                    </div>
                    <div class="jesp-form-group jesp-form-half">
                        <label><?php esc_html_e( 'End Date', 'jesp-erp' ); ?></label>
                        <input type="date" id="discount-end-date" class="jesp-input">
                    </div>
                </div>
                <div class="jesp-form-actions">
                    <button class="button button-primary" id="erp-apply-discount">
                        <span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Apply Discount', 'jesp-erp' ); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Active Discounts -->
        <div class="jesp-erp-col-6">
            <div class="jesp-erp-card">
                <h2><?php esc_html_e( 'Discount Campaigns', 'jesp-erp' ); ?></h2>
                <div class="jesp-table-responsive">
                    <table class="jesp-table" id="erp-discounts-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Name', 'jesp-erp' ); ?></th>
                                <th><?php esc_html_e( 'Type', 'jesp-erp' ); ?></th>
                                <th><?php esc_html_e( 'Value', 'jesp-erp' ); ?></th>
                                <th><?php esc_html_e( 'Products', 'jesp-erp' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'jesp-erp' ); ?></th>
                                <th><?php esc_html_e( 'Action', 'jesp-erp' ); ?></th>
                            </tr>
                        </thead>
                        <tbody id="erp-discounts-body">
                            <tr><td colspan="6" class="jesp-loading"><?php esc_html_e( 'Loading...', 'jesp-erp' ); ?></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="jesp-pagination" id="erp-discounts-pagination"></div>
            </div>
        </div>
    </div>
</div>
