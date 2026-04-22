<?php if (!defined('ABSPATH')) {
    exit;
}?>
<div class="wrap jesp-erp-wrap">
    <div class="jesp-erp-header">
        <h1><span class="dashicons dashicons-businessman"></span> <?php esc_html_e('Customer Management', 'jesp-erp'); ?></h1>
    </div>

    <!-- Customer List View -->
    <div id="erp-customers-list-view">
        <div class="jesp-erp-card jesp-erp-filters">
            <div class="jesp-filter-row">
                <div class="jesp-filter-group">
                    <label><?php esc_html_e('Search', 'jesp-erp'); ?></label>
                    <input type="text" id="erp-customer-search" placeholder="<?php esc_attr_e('Search by name, phone, or email...', 'jesp-erp'); ?>" class="jesp-input">
                </div>
                <div class="jesp-filter-group">
                    <label><?php esc_html_e('Min Purchase Value', 'jesp-erp'); ?></label>
                    <input type="number" id="erp-customer-min-spent" class="jesp-input" min="0" step="1" placeholder="0">
                </div>
                <div class="jesp-filter-group">
                    <button class="button button-primary" id="erp-customer-filter"><?php esc_html_e('Filter', 'jesp-erp'); ?></button>
                </div>
                <div class="jesp-filter-group" style="margin-left:auto;">
                    <button class="button" id="erp-sync-customers">
                        <span class="dashicons dashicons-update" style="margin-top:3px;"></span>
                        <?php esc_html_e('Sync Existing Customers', 'jesp-erp'); ?>
                    </button>
                    <span id="erp-sync-customers-status" style="margin-left:8px;font-size:13px;color:#64748b;"></span>
                </div>
            </div>
        </div>

        <div class="jesp-erp-card">
            <div class="jesp-table-responsive">
                <table class="jesp-table" id="erp-customers-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Name', 'jesp-erp'); ?></th>
                            <th><?php esc_html_e('Email', 'jesp-erp'); ?></th>
                            <th><?php esc_html_e('Phone', 'jesp-erp'); ?></th>
                            <th><?php esc_html_e('Orders', 'jesp-erp'); ?></th>
                            <th><?php esc_html_e('Total Spent', 'jesp-erp'); ?></th>
                            <th><?php esc_html_e('AOV', 'jesp-erp'); ?></th>
                            <th><?php esc_html_e('Last Order', 'jesp-erp'); ?></th>
                            <th><?php esc_html_e('Actions', 'jesp-erp'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="erp-customers-body">
                        <tr><td colspan="8" class="jesp-loading"><?php esc_html_e('Loading...', 'jesp-erp'); ?></td></tr>
                    </tbody>
                </table>
            </div>
            <div class="jesp-pagination" id="erp-customers-pagination"></div>
        </div>
    </div>

    <!-- Customer Detail View (hidden by default) -->
    <div id="erp-customer-detail-view" style="display:none;">
        <div class="jesp-erp-card">
            <button class="button" id="erp-back-to-customers">
                <span class="dashicons dashicons-arrow-left-alt" style="margin-top:4px;"></span> <?php esc_html_e('Back to Customers', 'jesp-erp'); ?>
            </button>
        </div>

        <div class="jesp-erp-row">
            <div class="jesp-erp-col-4">
                <div class="jesp-erp-card jesp-customer-profile">
                    <div class="jesp-profile-avatar">
                        <span class="dashicons dashicons-admin-users"></span>
                    </div>
                    <h2 id="profile-name">—</h2>
                    <div class="jesp-profile-details">
                        <div class="jesp-profile-field">
                            <span class="dashicons dashicons-email"></span>
                            <span id="profile-email">—</span>
                        </div>
                        <div class="jesp-profile-field">
                            <span class="dashicons dashicons-phone"></span>
                            <span id="profile-phone">—</span>
                        </div>
                        <div class="jesp-profile-field">
                            <span class="dashicons dashicons-location"></span>
                            <span id="profile-address">—</span>
                        </div>
                    </div>
                    <div class="jesp-profile-stats">
                        <div class="jesp-profile-stat">
                            <span class="jesp-profile-stat-value" id="profile-total-spent">—</span>
                            <span class="jesp-profile-stat-label"><?php esc_html_e('Total Spent', 'jesp-erp'); ?></span>
                        </div>
                        <div class="jesp-profile-stat">
                            <span class="jesp-profile-stat-value" id="profile-order-count">—</span>
                            <span class="jesp-profile-stat-label"><?php esc_html_e('Orders', 'jesp-erp'); ?></span>
                        </div>
                        <div class="jesp-profile-stat">
                            <span class="jesp-profile-stat-value" id="profile-aov">—</span>
                            <span class="jesp-profile-stat-label"><?php esc_html_e('Avg. Order Value', 'jesp-erp'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="jesp-erp-col-8">
                <div class="jesp-erp-card">
                    <h2><?php esc_html_e('Order History', 'jesp-erp'); ?></h2>
                    <div class="jesp-table-responsive">
                        <table class="jesp-table" id="erp-customer-orders-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Order #', 'jesp-erp'); ?></th>
                                    <th><?php esc_html_e('Date', 'jesp-erp'); ?></th>
                                    <th><?php esc_html_e('Items', 'jesp-erp'); ?></th>
                                    <th><?php esc_html_e('Status', 'jesp-erp'); ?></th>
                                    <th><?php esc_html_e('Total', 'jesp-erp'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="erp-customer-orders-body">
                                <tr><td colspan="5" class="jesp-loading"><?php esc_html_e('Loading...', 'jesp-erp'); ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="jesp-pagination" id="erp-customer-orders-pagination"></div>
                </div>
            </div>
        </div>
    </div>
</div>
