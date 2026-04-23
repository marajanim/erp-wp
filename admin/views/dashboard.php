<?php if (!defined('ABSPATH')) {
    exit;
}?>
<div class="wrap jesp-erp-wrap">
    <div class="jesp-erp-header">
        <h1><span class="dashicons dashicons-analytics"></span> <?php esc_html_e('ERP Dashboard', 'jesp-erp'); ?></h1>
        <p class="jesp-erp-subtitle"><?php esc_html_e('Overview of your inventory and sales performance', 'jesp-erp'); ?></p>
    </div>

    <!-- Global Date Filter -->
    <div class="jesp-erp-card jesp-erp-filters jesp-dash-date-bar">
        <div class="jesp-filter-row">
            <div class="jesp-filter-group">
                <label><?php esc_html_e('Date Range', 'jesp-erp'); ?></label>
                <div class="jesp-btn-group">
                    <button class="button jesp-dash-range" data-days="1"><?php esc_html_e('Last Day', 'jesp-erp'); ?></button>
                    <button class="button jesp-dash-range" data-days="7"><?php esc_html_e('7 Days', 'jesp-erp'); ?></button>
                    <button class="button jesp-dash-range active" data-days="30"><?php esc_html_e('30 Days', 'jesp-erp'); ?></button>
                    <button class="button jesp-dash-range" data-days="0"><?php esc_html_e('Custom', 'jesp-erp'); ?></button>
                </div>
            </div>
            <div class="jesp-filter-group jesp-custom-range" style="display:none;">
                <label><?php esc_html_e('From', 'jesp-erp'); ?></label>
                <input type="date" id="dash-date-from" class="jesp-input">
            </div>
            <div class="jesp-filter-group jesp-custom-range" style="display:none;">
                <label><?php esc_html_e('To', 'jesp-erp'); ?></label>
                <input type="date" id="dash-date-to" class="jesp-input">
            </div>
            <div class="jesp-filter-group jesp-custom-range" style="display:none;">
                <button class="button button-primary" id="dash-apply-custom"><?php esc_html_e('Apply', 'jesp-erp'); ?></button>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="jesp-erp-stats-grid" id="erp-dashboard-stats">
        <div class="jesp-stat-card jesp-stat-blue">
            <div class="jesp-stat-icon"><span class="dashicons dashicons-cart"></span></div>
            <div class="jesp-stat-content">
                <span class="jesp-stat-value" id="stat-total-orders">—</span>
                <span class="jesp-stat-label"><?php esc_html_e('Orders', 'jesp-erp'); ?></span>
            </div>
        </div>
        <div class="jesp-stat-card jesp-stat-green">
            <div class="jesp-stat-icon"><span class="dashicons dashicons-money-alt"></span></div>
            <div class="jesp-stat-content">
                <span class="jesp-stat-value" id="stat-total-revenue">—</span>
                <span class="jesp-stat-label"><?php esc_html_e('Revenue', 'jesp-erp'); ?></span>
            </div>
        </div>
        <div class="jesp-stat-card jesp-stat-purple">
            <div class="jesp-stat-icon"><span class="dashicons dashicons-archive"></span></div>
            <div class="jesp-stat-content">
                <span class="jesp-stat-value" id="stat-total-products">—</span>
                <span class="jesp-stat-label"><?php esc_html_e('Total Products', 'jesp-erp'); ?></span>
            </div>
        </div>
        <a href="<?php echo esc_url(admin_url('admin.php?page=jesp-erp-stock&stock_status=low')); ?>" class="jesp-stat-card jesp-stat-red" style="text-decoration:none;cursor:pointer;" title="<?php esc_attr_e('Click to view low stock products', 'jesp-erp'); ?>">
            <div class="jesp-stat-icon"><span class="dashicons dashicons-warning"></span></div>
            <div class="jesp-stat-content">
                <span class="jesp-stat-value" id="stat-low-stock">—</span>
                <span class="jesp-stat-label"><?php esc_html_e('Low Stock Items', 'jesp-erp'); ?></span>
            </div>
        </a>
        <div class="jesp-stat-card jesp-stat-orange">
            <div class="jesp-stat-icon"><span class="dashicons dashicons-groups"></span></div>
            <div class="jesp-stat-content">
                <span class="jesp-stat-value" id="stat-total-customers">—</span>
                <span class="jesp-stat-label"><?php esc_html_e('Total Customers', 'jesp-erp'); ?></span>
            </div>
        </div>
    </div>

    <!-- Low Stock Alert Widget -->
    <div class="jesp-erp-card" id="low-stock-widget">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <h2 style="margin:0;"><span class="dashicons dashicons-warning" style="color:#ef4444;"></span> <?php esc_html_e('Low Stock Alerts', 'jesp-erp'); ?></h2>
            <a href="<?php echo esc_url(admin_url('admin.php?page=jesp-erp-stock&stock_status=low')); ?>" class="button button-secondary"><?php esc_html_e('View All', 'jesp-erp'); ?></a>
        </div>
        <div id="low-stock-alert-list">
            <p class="jesp-loading"><?php esc_html_e('Loading...', 'jesp-erp'); ?></p>
        </div>
    </div>

    <!-- Tabbed Sections -->
    <div class="jesp-tabs">
        <div class="jesp-tab-nav">
            <button class="jesp-tab-btn active" data-tab="overview"><span class="dashicons dashicons-chart-area"></span> <?php esc_html_e('Revenue Overview', 'jesp-erp'); ?></button>
            <button class="jesp-tab-btn" data-tab="hero"><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e('Hero Products', 'jesp-erp'); ?></button>
            <button class="jesp-tab-btn" data-tab="stock-value"><span class="dashicons dashicons-vault"></span> <?php esc_html_e('Stock Value', 'jesp-erp'); ?></button>
            <button class="jesp-tab-btn" data-tab="customers"><span class="dashicons dashicons-businessman"></span> <?php esc_html_e('Customer Insights', 'jesp-erp'); ?></button>
        </div>

        <!-- TAB: Revenue Overview (existing) -->
        <div class="jesp-tab-panel active" id="tab-overview">
            <div class="jesp-erp-row">
                <div class="jesp-erp-col-8">
                    <div class="jesp-erp-card">
                        <h2><?php esc_html_e('Revenue Trend', 'jesp-erp'); ?></h2>
                        <canvas id="erp-revenue-chart" height="300"></canvas>
                    </div>
                </div>
                <div class="jesp-erp-col-4">
                    <div class="jesp-erp-card">
                        <h2><?php esc_html_e('Quick Actions', 'jesp-erp'); ?></h2>
                        <div class="jesp-quick-actions">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=jesp-erp-stock')); ?>" class="jesp-action-btn"><span class="dashicons dashicons-clipboard"></span><?php esc_html_e('Manage Stock', 'jesp-erp'); ?></a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=jesp-erp-import')); ?>" class="jesp-action-btn"><span class="dashicons dashicons-upload"></span><?php esc_html_e('Import Products', 'jesp-erp'); ?></a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=jesp-erp-export')); ?>" class="jesp-action-btn"><span class="dashicons dashicons-download"></span><?php esc_html_e('Export Data', 'jesp-erp'); ?></a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=jesp-erp-discounts')); ?>" class="jesp-action-btn"><span class="dashicons dashicons-tag"></span><?php esc_html_e('Bulk Discounts', 'jesp-erp'); ?></a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=jesp-erp-orders')); ?>" class="jesp-action-btn"><span class="dashicons dashicons-chart-bar"></span><?php esc_html_e('Order Analytics', 'jesp-erp'); ?></a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=jesp-erp-customers')); ?>" class="jesp-action-btn"><span class="dashicons dashicons-businessman"></span><?php esc_html_e('Customers', 'jesp-erp'); ?></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB: Hero Products -->
        <div class="jesp-tab-panel" id="tab-hero" style="display:none;">
            <div class="jesp-erp-row">
                <div class="jesp-erp-col-8">
                    <div class="jesp-erp-card">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <h2 style="margin:0;"><?php esc_html_e('Top Products by Revenue', 'jesp-erp'); ?></h2>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=jesp-erp-orders')); ?>" class="button button-secondary"><?php esc_html_e('View All', 'jesp-erp'); ?></a>
                        </div>
                        <canvas id="erp-hero-chart" height="300"></canvas>
                    </div>
                </div>
                <div class="jesp-erp-col-4">
                    <div class="jesp-erp-card">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <h2 style="margin:0;"><?php esc_html_e('Best Sellers', 'jesp-erp'); ?></h2>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=jesp-erp-orders')); ?>" class="button button-secondary" style="font-size:11px;"><?php esc_html_e('View All', 'jesp-erp'); ?></a>
                        </div>
                        <div id="erp-hero-list" class="jesp-hero-list">
                            <p class="jesp-loading"><?php esc_html_e('Loading...', 'jesp-erp'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB: Current Stock Value -->
        <div class="jesp-tab-panel" id="tab-stock-value" style="display:none;">
            <div class="jesp-erp-card jesp-erp-filters" style="margin-bottom:0;">
                <div class="jesp-filter-row">
                    <div class="jesp-filter-group">
                        <label><?php esc_html_e('Category', 'jesp-erp'); ?></label>
                        <select id="sv-category" class="jesp-select">
                            <option value=""><?php esc_html_e('All Categories', 'jesp-erp'); ?></option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="jesp-erp-stats-grid" id="sv-summary" style="margin-top:20px;">
                <div class="jesp-stat-card jesp-stat-green">
                    <div class="jesp-stat-icon"><span class="dashicons dashicons-vault"></span></div>
                    <div class="jesp-stat-content">
                        <span class="jesp-stat-value" id="sv-total-value">—</span>
                        <span class="jesp-stat-label"><?php esc_html_e('Total Stock Value', 'jesp-erp'); ?></span>
                    </div>
                </div>
                <div class="jesp-stat-card jesp-stat-blue">
                    <div class="jesp-stat-icon"><span class="dashicons dashicons-building"></span></div>
                    <div class="jesp-stat-content">
                        <span class="jesp-stat-value" id="sv-warehouse-value">—</span>
                        <span class="jesp-stat-label"><?php esc_html_e('Warehouse Value', 'jesp-erp'); ?></span>
                    </div>
                </div>
                <div class="jesp-stat-card jesp-stat-purple">
                    <div class="jesp-stat-icon"><span class="dashicons dashicons-store"></span></div>
                    <div class="jesp-stat-content">
                        <span class="jesp-stat-value" id="sv-sales-value">—</span>
                        <span class="jesp-stat-label"><?php esc_html_e('Sales Center Value', 'jesp-erp'); ?></span>
                    </div>
                </div>
            </div>
            <div class="jesp-erp-card" style="margin-top:24px;">
                <h2><?php esc_html_e('Stock Value by Product', 'jesp-erp'); ?></h2>
                <div class="jesp-table-responsive">
                    <table class="jesp-table" id="sv-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Product', 'jesp-erp'); ?></th>
                                <th><?php esc_html_e('SKU', 'jesp-erp'); ?></th>
                                <th><?php esc_html_e('Buying Price', 'jesp-erp'); ?></th>
                                <th><?php esc_html_e('Warehouse', 'jesp-erp'); ?></th>
                                <th><?php esc_html_e('Sales Center', 'jesp-erp'); ?></th>
                                <th><?php esc_html_e('Total Qty', 'jesp-erp'); ?></th>
                                <th><?php esc_html_e('Total Value', 'jesp-erp'); ?></th>
                                <th><?php esc_html_e('Status', 'jesp-erp'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="sv-body">
                            <tr><td colspan="8" class="jesp-loading"><?php esc_html_e('Loading...', 'jesp-erp'); ?></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="jesp-pagination" id="sv-pagination"></div>
                <div style="text-align:right;margin-top:8px;"><a href="<?php echo esc_url(admin_url('admin.php?page=jesp-erp-stock')); ?>" class="button button-secondary"><?php esc_html_e('View All in Stock Management', 'jesp-erp'); ?></a></div>
            </div>
        </div>

        <!-- TAB: Customer Insights -->
        <div class="jesp-tab-panel" id="tab-customers" style="display:none;">
            <div class="jesp-erp-card jesp-erp-filters" style="margin-bottom:0;">
                <div class="jesp-filter-row">
                    <div class="jesp-filter-group">
                        <label><?php esc_html_e('Search', 'jesp-erp'); ?></label>
                        <input type="text" id="di-cust-search" placeholder="<?php esc_attr_e('Name, phone, or email...', 'jesp-erp'); ?>" class="jesp-input">
                    </div>
                    <div class="jesp-filter-group">
                        <label><?php esc_html_e('Min Purchase', 'jesp-erp'); ?></label>
                        <input type="number" id="di-cust-min" class="jesp-input" min="0" step="1" placeholder="0">
                    </div>
                    <div class="jesp-filter-group">
                        <button class="button button-primary" id="di-cust-filter"><?php esc_html_e('Filter', 'jesp-erp'); ?></button>
                    </div>
                </div>
            </div>
            <div style="text-align:right;margin-top:4px;"><a href="<?php echo esc_url(admin_url('admin.php?page=jesp-erp-customers')); ?>" class="button button-secondary"><?php esc_html_e('View All Customers', 'jesp-erp'); ?></a></div>
            <!-- Customer List -->
            <div id="di-cust-list-view" style="margin-top:24px;">
                <div class="jesp-erp-card">
                    <div class="jesp-table-responsive">
                        <table class="jesp-table" id="di-cust-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Name', 'jesp-erp'); ?></th>
                                    <th><?php esc_html_e('Phone', 'jesp-erp'); ?></th>
                                    <th><?php esc_html_e('Email', 'jesp-erp'); ?></th>
                                    <th><?php esc_html_e('Orders', 'jesp-erp'); ?></th>
                                    <th><?php esc_html_e('Total Spent', 'jesp-erp'); ?></th>
                                    <th><?php esc_html_e('AOV', 'jesp-erp'); ?></th>
                                    <th><?php esc_html_e('Last Order', 'jesp-erp'); ?></th>
                                    <th><?php esc_html_e('View', 'jesp-erp'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="di-cust-body"><tr><td colspan="8" class="jesp-loading"><?php esc_html_e('Loading...', 'jesp-erp'); ?></td></tr></tbody>
                        </table>
                    </div>
                    <div class="jesp-pagination" id="di-cust-pagination"></div>
                </div>
            </div>
            <!-- Customer Detail Panel (inline, no separate page) -->
            <div id="di-cust-detail" style="display:none;margin-top:24px;">
                <button class="button" id="di-cust-back"><span class="dashicons dashicons-arrow-left-alt" style="margin-top:4px;"></span> <?php esc_html_e('Back', 'jesp-erp'); ?></button>
                <div class="jesp-erp-row" style="margin-top:16px;">
                    <div class="jesp-erp-col-4">
                        <div class="jesp-erp-card jesp-customer-profile">
                            <div class="jesp-profile-avatar"><span class="dashicons dashicons-admin-users"></span></div>
                            <h2 id="di-p-name">—</h2>
                            <div class="jesp-profile-details">
                                <div class="jesp-profile-field"><span class="dashicons dashicons-phone"></span><span id="di-p-phone">—</span></div>
                                <div class="jesp-profile-field"><span class="dashicons dashicons-email"></span><span id="di-p-email">—</span></div>
                                <div class="jesp-profile-field"><span class="dashicons dashicons-location"></span><span id="di-p-address">—</span></div>
                            </div>
                            <div class="jesp-profile-stats">
                                <div class="jesp-profile-stat"><span class="jesp-profile-stat-value" id="di-p-spent">—</span><span class="jesp-profile-stat-label"><?php esc_html_e('Total Spent', 'jesp-erp'); ?></span></div>
                                <div class="jesp-profile-stat"><span class="jesp-profile-stat-value" id="di-p-orders">—</span><span class="jesp-profile-stat-label"><?php esc_html_e('Orders', 'jesp-erp'); ?></span></div>
                            </div>
                        </div>
                    </div>
                    <div class="jesp-erp-col-8">
                        <div class="jesp-erp-card">
                            <h2><?php esc_html_e('Order History', 'jesp-erp'); ?></h2>
                            <div class="jesp-table-responsive">
                                <table class="jesp-table"><thead><tr>
                                    <th><?php esc_html_e('Order #', 'jesp-erp'); ?></th>
                                    <th><?php esc_html_e('Date', 'jesp-erp'); ?></th>
                                    <th><?php esc_html_e('Items', 'jesp-erp'); ?></th>
                                    <th><?php esc_html_e('Status', 'jesp-erp'); ?></th>
                                    <th><?php esc_html_e('Total', 'jesp-erp'); ?></th>
                                </tr></thead><tbody id="di-p-orders-body"><tr><td colspan="5" class="jesp-loading">—</td></tr></tbody></table>
                            </div>
                            <div class="jesp-pagination" id="di-p-orders-pag"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
