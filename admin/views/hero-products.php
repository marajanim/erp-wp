<?php if (!defined('ABSPATH')) {
    exit;
}?>
<div class="wrap jesp-erp-wrap">
    <div class="jesp-erp-header" style="display:flex;justify-content:space-between;align-items:center;">
        <div>
            <h1><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e('Hero Products', 'jesp-erp'); ?></h1>
            <p class="jesp-erp-subtitle"><?php esc_html_e('All products ranked by revenue for the selected period', 'jesp-erp'); ?></p>
        </div>
        <a href="<?php echo esc_url(admin_url('admin.php?page=jesp-erp')); ?>" class="button button-secondary">
            <span class="dashicons dashicons-arrow-left-alt" style="margin-top:3px;"></span>
            <?php esc_html_e('Back to Dashboard', 'jesp-erp'); ?>
        </a>
    </div>

    <!-- Date Filters -->
    <div class="jesp-erp-card jesp-erp-filters jesp-dash-date-bar">
        <div class="jesp-filter-row">
            <div class="jesp-filter-group">
                <label><?php esc_html_e('Date Range', 'jesp-erp'); ?></label>
                <div class="jesp-btn-group">
                    <button class="button jesp-hp-range" data-days="1"><?php esc_html_e('Last Day', 'jesp-erp'); ?></button>
                    <button class="button jesp-hp-range" data-days="7"><?php esc_html_e('7 Days', 'jesp-erp'); ?></button>
                    <button class="button jesp-hp-range active" data-days="30"><?php esc_html_e('30 Days', 'jesp-erp'); ?></button>
                    <button class="button jesp-hp-range" data-days="0"><?php esc_html_e('Custom', 'jesp-erp'); ?></button>
                </div>
            </div>
            <div class="jesp-filter-group jesp-hp-custom-range" style="display:none;">
                <label><?php esc_html_e('From', 'jesp-erp'); ?></label>
                <input type="date" id="hp-date-from" class="jesp-input">
            </div>
            <div class="jesp-filter-group jesp-hp-custom-range" style="display:none;">
                <label><?php esc_html_e('To', 'jesp-erp'); ?></label>
                <input type="date" id="hp-date-to" class="jesp-input">
            </div>
            <div class="jesp-filter-group jesp-hp-custom-range" style="display:none;">
                <button class="button button-primary" id="hp-apply-custom"><?php esc_html_e('Apply', 'jesp-erp'); ?></button>
            </div>
        </div>
    </div>

    <!-- Search + Per Page -->
    <div class="jesp-erp-card jesp-erp-filters" style="margin-top:0;">
        <div class="jesp-filter-row">
            <div class="jesp-filter-group">
                <label><?php esc_html_e('Search Product', 'jesp-erp'); ?></label>
                <input type="text" id="hp-search" class="jesp-input" placeholder="<?php esc_attr_e('Product name...', 'jesp-erp'); ?>">
            </div>
            <div class="jesp-filter-group">
                <label><?php esc_html_e('Per Page', 'jesp-erp'); ?></label>
                <select id="hp-per-page" class="jesp-select">
                    <option value="20">20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="jesp-erp-stats-grid" id="hp-summary-cards" style="margin-bottom:20px;">
        <div class="jesp-stat-card jesp-stat-purple">
            <div class="jesp-stat-icon"><span class="dashicons dashicons-products"></span></div>
            <div class="jesp-stat-content">
                <span class="jesp-stat-value" id="hp-summary-products">—</span>
                <span class="jesp-stat-label"><?php esc_html_e('Total Products Sold', 'jesp-erp'); ?></span>
            </div>
        </div>
        <div class="jesp-stat-card jesp-stat-green">
            <div class="jesp-stat-icon"><span class="dashicons dashicons-money-alt"></span></div>
            <div class="jesp-stat-content">
                <span class="jesp-stat-value" id="hp-summary-revenue">—</span>
                <span class="jesp-stat-label"><?php esc_html_e('Total Revenue', 'jesp-erp'); ?></span>
            </div>
        </div>
        <div class="jesp-stat-card jesp-stat-blue">
            <div class="jesp-stat-icon"><span class="dashicons dashicons-star-filled"></span></div>
            <div class="jesp-stat-content">
                <span class="jesp-stat-value" id="hp-summary-top">—</span>
                <span class="jesp-stat-label"><?php esc_html_e('Top Product', 'jesp-erp'); ?></span>
            </div>
        </div>
    </div>

    <!-- Products Table -->
    <div class="jesp-erp-card">
        <div class="jesp-table-responsive">
            <table class="jesp-table" id="hp-table">
                <thead>
                    <tr>
                        <th style="width:60px;text-align:center;"><?php esc_html_e('Rank', 'jesp-erp'); ?></th>
                        <th style="width:60px;"><?php esc_html_e('Image', 'jesp-erp'); ?></th>
                        <th><?php esc_html_e('Product Name', 'jesp-erp'); ?></th>
                        <th><?php esc_html_e('SKU', 'jesp-erp'); ?></th>
                        <th style="text-align:center;"><?php esc_html_e('Orders', 'jesp-erp'); ?></th>
                        <th style="text-align:center;"><?php esc_html_e('Qty Sold', 'jesp-erp'); ?></th>
                        <th><?php esc_html_e('Revenue', 'jesp-erp'); ?></th>
                        <th><?php esc_html_e('Performance', 'jesp-erp'); ?></th>
                    </tr>
                </thead>
                <tbody id="hp-table-body">
                    <tr><td colspan="8" class="jesp-loading"><?php esc_html_e('Loading...', 'jesp-erp'); ?></td></tr>
                </tbody>
            </table>
        </div>
        <div class="jesp-pagination" id="hp-pagination"></div>
    </div>
</div>
