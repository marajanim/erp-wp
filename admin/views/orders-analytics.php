<?php if (!defined('ABSPATH')) {
    exit;
}?>
<div class="wrap jesp-erp-wrap">
    <div class="jesp-erp-header">
        <h1><span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e('Orders & Analytics', 'jesp-erp'); ?></h1>
    </div>

    <!-- Date Filters (shared) -->
    <div class="jesp-erp-card jesp-erp-filters">
        <div class="jesp-filter-row">
            <div class="jesp-filter-group">
                <label><?php esc_html_e('Quick Range', 'jesp-erp'); ?></label>
                <div class="jesp-btn-group">
                    <button class="button jesp-range-btn" data-days="7"><?php esc_html_e('7 Days', 'jesp-erp'); ?></button>
                    <button class="button jesp-range-btn active" data-days="30"><?php esc_html_e('30 Days', 'jesp-erp'); ?></button>
                    <button class="button jesp-range-btn" data-days="90"><?php esc_html_e('90 Days', 'jesp-erp'); ?></button>
                </div>
            </div>
            <div class="jesp-filter-group">
                <label><?php esc_html_e('From', 'jesp-erp'); ?></label>
                <input type="date" id="erp-orders-from" class="jesp-input">
            </div>
            <div class="jesp-filter-group">
                <label><?php esc_html_e('To', 'jesp-erp'); ?></label>
                <input type="date" id="erp-orders-to" class="jesp-input">
            </div>
            <div class="jesp-filter-group">
                <button class="button button-primary" id="erp-orders-filter"><?php esc_html_e('Apply', 'jesp-erp'); ?></button>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="jesp-erp-card jesp-tabs" id="erp-orders-tabs">
        <div class="jesp-tab-nav">
            <button class="jesp-tab-btn active" data-tab="all-orders">
                <span class="dashicons dashicons-list-view"></span> <?php esc_html_e('All Orders', 'jesp-erp'); ?>
            </button>
            <button class="jesp-tab-btn" data-tab="product-performance">
                <span class="dashicons dashicons-chart-area"></span> <?php esc_html_e('Product Performance', 'jesp-erp'); ?>
            </button>
        </div>

        <!-- TAB: All Orders -->
        <div class="jesp-tab-panel active" data-panel="all-orders">
            <!-- All Orders Filters -->
            <div class="jesp-filter-row" style="margin-bottom:20px;">
                <div class="jesp-filter-group">
                    <label><?php esc_html_e('Search', 'jesp-erp'); ?></label>
                    <input type="text" id="erp-ao-search" class="jesp-input" placeholder="<?php esc_attr_e('Order # or customer name...', 'jesp-erp'); ?>">
                </div>
                <div class="jesp-filter-group">
                    <label><?php esc_html_e('Status', 'jesp-erp'); ?></label>
                    <select id="erp-ao-status" class="jesp-select">
                        <option value="all"><?php esc_html_e('All Statuses', 'jesp-erp'); ?></option>
                        <option value="wc-processing"><?php esc_html_e('Processing', 'jesp-erp'); ?></option>
                        <option value="wc-completed"><?php esc_html_e('Completed', 'jesp-erp'); ?></option>
                        <option value="wc-on-hold"><?php esc_html_e('On Hold', 'jesp-erp'); ?></option>
                        <option value="wc-pending"><?php esc_html_e('Pending', 'jesp-erp'); ?></option>
                        <option value="wc-cancelled"><?php esc_html_e('Cancelled', 'jesp-erp'); ?></option>
                        <option value="wc-refunded"><?php esc_html_e('Refunded', 'jesp-erp'); ?></option>
                        <option value="wc-failed"><?php esc_html_e('Failed', 'jesp-erp'); ?></option>
                    </select>
                </div>
                <div class="jesp-filter-group">
                    <label><?php esc_html_e('Per Page', 'jesp-erp'); ?></label>
                    <select id="erp-ao-per-page" class="jesp-select">
                        <option value="20">20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div class="jesp-filter-group" style="display:flex;align-items:flex-end;">
                    <button class="button button-primary" id="erp-ao-export" style="white-space:nowrap;">
                        <span class="dashicons dashicons-download" style="vertical-align:middle;margin-right:4px;font-size:16px;width:16px;height:16px;"></span>
                        <?php esc_html_e('Export CSV', 'jesp-erp'); ?>
                    </button>
                </div>
            </div>

            <!-- All Orders Table -->
            <div class="jesp-table-responsive">
                <table class="jesp-table" id="erp-all-orders-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Order #', 'jesp-erp'); ?></th>
                            <th><?php esc_html_e('Date', 'jesp-erp'); ?></th>
                            <th><?php esc_html_e('Customer', 'jesp-erp'); ?></th>
                            <th><?php esc_html_e('Items', 'jesp-erp'); ?></th>
                            <th><?php esc_html_e('Total', 'jesp-erp'); ?></th>
                            <th><?php esc_html_e('Payment', 'jesp-erp'); ?></th>
                            <th><?php esc_html_e('Status', 'jesp-erp'); ?></th>
                            <th><?php esc_html_e('Details', 'jesp-erp'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="erp-all-orders-body">
                        <tr><td colspan="8" class="jesp-loading"><?php esc_html_e('Loading...', 'jesp-erp'); ?></td></tr>
                    </tbody>
                </table>
            </div>
            <div class="jesp-pagination" id="erp-all-orders-pagination"></div>
        </div>

        <!-- TAB: Product Performance (existing) -->
        <div class="jesp-tab-panel" data-panel="product-performance">
            <!-- Charts -->
            <div class="jesp-erp-row">
                <div class="jesp-erp-col-8">
                    <div class="jesp-erp-card" style="box-shadow:none;border:none;padding:0;">
                        <h2><?php esc_html_e('Revenue Trend', 'jesp-erp'); ?></h2>
                        <canvas id="erp-orders-revenue-chart" height="280"></canvas>
                    </div>
                </div>
                <div class="jesp-erp-col-4">
                    <div class="jesp-erp-card" style="box-shadow:none;border:none;padding:0;">
                        <h2><?php esc_html_e('Period Summary', 'jesp-erp'); ?></h2>
                        <div class="jesp-summary-list">
                            <div class="jesp-summary-item">
                                <span class="jesp-summary-label"><?php esc_html_e('Total Orders', 'jesp-erp'); ?></span>
                                <span class="jesp-summary-value" id="orders-total-count">—</span>
                            </div>
                            <div class="jesp-summary-item">
                                <span class="jesp-summary-label"><?php esc_html_e('Total Revenue', 'jesp-erp'); ?></span>
                                <span class="jesp-summary-value" id="orders-total-revenue">—</span>
                            </div>
                            <div class="jesp-summary-item">
                                <span class="jesp-summary-label"><?php esc_html_e('Unique Products Sold', 'jesp-erp'); ?></span>
                                <span class="jesp-summary-value" id="orders-unique-products">—</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Per-product orders table -->
            <div class="jesp-table-responsive">
                <table class="jesp-table" id="erp-orders-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Product', 'jesp-erp'); ?></th>
                            <th><?php esc_html_e('Orders', 'jesp-erp'); ?></th>
                            <th><?php esc_html_e('Qty Sold', 'jesp-erp'); ?></th>
                            <th><?php esc_html_e('Revenue', 'jesp-erp'); ?></th>
                            <th><?php esc_html_e('Details', 'jesp-erp'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="erp-orders-body">
                        <tr><td colspan="5" class="jesp-loading"><?php esc_html_e('Loading...', 'jesp-erp'); ?></td></tr>
                    </tbody>
                </table>
            </div>
            <div class="jesp-pagination" id="erp-orders-pagination"></div>
        </div>
    </div>
</div>

<!-- Order Detail Modal -->
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
