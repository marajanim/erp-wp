<?php if (!defined('ABSPATH')) {
    exit;
}?>
<div class="wrap jesp-erp-wrap">
    <div class="jesp-erp-header" style="display:flex;justify-content:space-between;align-items:center;">
        <div>
            <h1><span class="dashicons dashicons-chart-area"></span> <?php esc_html_e('Finance', 'jesp-erp'); ?></h1>
            <p class="jesp-erp-subtitle"><?php esc_html_e('Financial overview, payment methods, and expense tracking', 'jesp-erp'); ?></p>
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
                    <button class="button jesp-fin-range" data-days="1"><?php esc_html_e('Last Day', 'jesp-erp'); ?></button>
                    <button class="button jesp-fin-range" data-days="7"><?php esc_html_e('7 Days', 'jesp-erp'); ?></button>
                    <button class="button jesp-fin-range active" data-days="30"><?php esc_html_e('30 Days', 'jesp-erp'); ?></button>
                    <button class="button jesp-fin-range" data-days="90"><?php esc_html_e('90 Days', 'jesp-erp'); ?></button>
                    <button class="button jesp-fin-range" data-days="0"><?php esc_html_e('Custom', 'jesp-erp'); ?></button>
                </div>
            </div>
            <div class="jesp-filter-group jesp-fin-custom-range" style="display:none;">
                <label><?php esc_html_e('From', 'jesp-erp'); ?></label>
                <input type="date" id="fin-date-from" class="jesp-input">
            </div>
            <div class="jesp-filter-group jesp-fin-custom-range" style="display:none;">
                <label><?php esc_html_e('To', 'jesp-erp'); ?></label>
                <input type="date" id="fin-date-to" class="jesp-input">
            </div>
            <div class="jesp-filter-group jesp-fin-custom-range" style="display:none;">
                <button class="button button-primary" id="fin-apply-custom"><?php esc_html_e('Apply', 'jesp-erp'); ?></button>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="jesp-erp-stats-grid" id="fin-summary-cards" style="margin-bottom:20px;">
        <div class="jesp-stat-card jesp-stat-green">
            <div class="jesp-stat-icon"><span class="dashicons dashicons-money-alt"></span></div>
            <div class="jesp-stat-content">
                <span class="jesp-stat-value" id="fin-total-revenue">—</span>
                <span class="jesp-stat-label"><?php esc_html_e('Total Revenue', 'jesp-erp'); ?></span>
            </div>
        </div>
        <div class="jesp-stat-card jesp-stat-red">
            <div class="jesp-stat-icon"><span class="dashicons dashicons-undo"></span></div>
            <div class="jesp-stat-content">
                <span class="jesp-stat-value" id="fin-total-refunds">—</span>
                <span class="jesp-stat-label"><?php esc_html_e('Total Refunds', 'jesp-erp'); ?></span>
            </div>
        </div>
        <div class="jesp-stat-card jesp-stat-blue">
            <div class="jesp-stat-icon"><span class="dashicons dashicons-chart-line"></span></div>
            <div class="jesp-stat-content">
                <span class="jesp-stat-value" id="fin-net-profit">—</span>
                <span class="jesp-stat-label"><?php esc_html_e('Net Profit', 'jesp-erp'); ?></span>
            </div>
        </div>
        <div class="jesp-stat-card" style="border-left:4px solid #f59e0b;">
            <div class="jesp-stat-icon"><span class="dashicons dashicons-clipboard" style="color:#f59e0b;"></span></div>
            <div class="jesp-stat-content">
                <span class="jesp-stat-value" id="fin-total-tax">—</span>
                <span class="jesp-stat-label"><?php esc_html_e('Total Tax', 'jesp-erp'); ?></span>
            </div>
        </div>
        <div class="jesp-stat-card jesp-stat-purple">
            <div class="jesp-stat-icon"><span class="dashicons dashicons-car"></span></div>
            <div class="jesp-stat-content">
                <span class="jesp-stat-value" id="fin-total-shipping">—</span>
                <span class="jesp-stat-label"><?php esc_html_e('Shipping Collected', 'jesp-erp'); ?></span>
            </div>
        </div>
        <div class="jesp-stat-card" style="border-left:4px solid #64748b;">
            <div class="jesp-stat-icon"><span class="dashicons dashicons-cart" style="color:#64748b;"></span></div>
            <div class="jesp-stat-content">
                <span class="jesp-stat-value" id="fin-order-count">—</span>
                <span class="jesp-stat-label"><?php esc_html_e('Total Orders', 'jesp-erp'); ?></span>
            </div>
        </div>
    </div>

    <!-- Revenue Chart -->
    <div class="jesp-erp-card" style="margin-bottom:20px;">
        <h2 style="margin:0 0 16px;"><?php esc_html_e('Revenue vs Refunds', 'jesp-erp'); ?></h2>
        <div style="position:relative;height:300px;">
            <canvas id="fin-revenue-chart" height="300"></canvas>
        </div>
    </div>

    <!-- Payment Methods + Expenses Row -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
        <!-- Payment Methods -->
        <div class="jesp-erp-card">
            <h2 style="margin:0 0 16px;"><?php esc_html_e('Payment Methods', 'jesp-erp'); ?></h2>
            <div style="position:relative;height:250px;">
                <canvas id="fin-payment-chart" height="250"></canvas>
            </div>
            <div class="jesp-table-responsive" style="margin-top:16px;">
                <table class="jesp-table" id="fin-payment-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Method', 'jesp-erp'); ?></th>
                            <th style="text-align:center;"><?php esc_html_e('Orders', 'jesp-erp'); ?></th>
                            <th><?php esc_html_e('Revenue', 'jesp-erp'); ?></th>
                            <th><?php esc_html_e('Share', 'jesp-erp'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="fin-payment-body">
                        <tr><td colspan="4" class="jesp-loading"><?php esc_html_e('Loading...', 'jesp-erp'); ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Expense Categories Summary -->
        <div class="jesp-erp-card">
            <h2 style="margin:0 0 16px;"><?php esc_html_e('Expense Summary', 'jesp-erp'); ?></h2>
            <div class="jesp-erp-stats-grid" style="grid-template-columns:1fr 1fr;margin-bottom:16px;">
                <div class="jesp-stat-card jesp-stat-red" style="padding:14px;">
                    <div class="jesp-stat-content">
                        <span class="jesp-stat-value" id="fin-total-expenses" style="font-size:20px;">—</span>
                        <span class="jesp-stat-label"><?php esc_html_e('Total Expenses', 'jesp-erp'); ?></span>
                    </div>
                </div>
                <div class="jesp-stat-card jesp-stat-green" style="padding:14px;">
                    <div class="jesp-stat-content">
                        <span class="jesp-stat-value" id="fin-total-discount" style="font-size:20px;">—</span>
                        <span class="jesp-stat-label"><?php esc_html_e('Discounts Given', 'jesp-erp'); ?></span>
                    </div>
                </div>
            </div>
            <div id="fin-expense-cats">
                <p class="jesp-loading"><?php esc_html_e('Loading...', 'jesp-erp'); ?></p>
            </div>
        </div>
    </div>

    <!-- Expenses Table -->
    <div class="jesp-erp-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h2 style="margin:0;"><?php esc_html_e('Expenses', 'jesp-erp'); ?></h2>
            <button class="button button-primary" id="fin-add-expense">
                <span class="dashicons dashicons-plus-alt2" style="margin-top:3px;"></span>
                <?php esc_html_e('Add Expense', 'jesp-erp'); ?>
            </button>
        </div>
        <div class="jesp-table-responsive">
            <table class="jesp-table" id="fin-expenses-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date', 'jesp-erp'); ?></th>
                        <th><?php esc_html_e('Title', 'jesp-erp'); ?></th>
                        <th><?php esc_html_e('Category', 'jesp-erp'); ?></th>
                        <th><?php esc_html_e('Amount', 'jesp-erp'); ?></th>
                        <th><?php esc_html_e('Notes', 'jesp-erp'); ?></th>
                        <th style="width:80px;"><?php esc_html_e('Actions', 'jesp-erp'); ?></th>
                    </tr>
                </thead>
                <tbody id="fin-expenses-body">
                    <tr><td colspan="6" class="jesp-loading"><?php esc_html_e('Loading...', 'jesp-erp'); ?></td></tr>
                </tbody>
            </table>
        </div>
        <div class="jesp-pagination" id="fin-expenses-pagination"></div>
    </div>
</div>

<!-- Add/Edit Expense Modal -->
<div class="jesp-modal" id="fin-expense-modal" style="display:none;">
    <div class="jesp-modal-overlay"></div>
    <div class="jesp-modal-content" style="max-width:480px;">
        <div class="jesp-modal-header">
            <h3 id="fin-expense-modal-title"><?php esc_html_e('Add Expense', 'jesp-erp'); ?></h3>
            <button class="jesp-modal-close">&times;</button>
        </div>
        <div class="jesp-modal-body">
            <input type="hidden" id="fin-expense-id" value="0">
            <div class="jesp-form-group" style="margin-bottom:12px;">
                <label><?php esc_html_e('Title', 'jesp-erp'); ?> <span style="color:#ef4444;">*</span></label>
                <input type="text" id="fin-expense-title" class="jesp-input" style="width:100%;" placeholder="<?php esc_attr_e('e.g. Rent, Salary, Shipping Cost', 'jesp-erp'); ?>">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                <div class="jesp-form-group">
                    <label><?php esc_html_e('Amount', 'jesp-erp'); ?> <span style="color:#ef4444;">*</span></label>
                    <input type="number" id="fin-expense-amount" class="jesp-input" style="width:100%;" min="0" step="0.01" placeholder="0.00">
                </div>
                <div class="jesp-form-group">
                    <label><?php esc_html_e('Date', 'jesp-erp'); ?></label>
                    <input type="date" id="fin-expense-date" class="jesp-input" style="width:100%;">
                </div>
            </div>
            <div class="jesp-form-group" style="margin-bottom:12px;">
                <label><?php esc_html_e('Category', 'jesp-erp'); ?></label>
                <select id="fin-expense-category" class="jesp-select" style="width:100%;">
                    <option value=""><?php esc_html_e('— Select —', 'jesp-erp'); ?></option>
                    <option value="Rent"><?php esc_html_e('Rent', 'jesp-erp'); ?></option>
                    <option value="Salary"><?php esc_html_e('Salary', 'jesp-erp'); ?></option>
                    <option value="Shipping"><?php esc_html_e('Shipping', 'jesp-erp'); ?></option>
                    <option value="Marketing"><?php esc_html_e('Marketing', 'jesp-erp'); ?></option>
                    <option value="Utilities"><?php esc_html_e('Utilities', 'jesp-erp'); ?></option>
                    <option value="Supplies"><?php esc_html_e('Supplies', 'jesp-erp'); ?></option>
                    <option value="Other"><?php esc_html_e('Other', 'jesp-erp'); ?></option>
                </select>
            </div>
            <div class="jesp-form-group" style="margin-bottom:12px;">
                <label><?php esc_html_e('Notes', 'jesp-erp'); ?></label>
                <textarea id="fin-expense-notes" class="jesp-input" rows="3" style="width:100%;" placeholder="<?php esc_attr_e('Optional notes...', 'jesp-erp'); ?>"></textarea>
            </div>
        </div>
        <div class="jesp-modal-footer" style="display:flex;justify-content:flex-end;gap:8px;padding:16px 20px;border-top:1px solid #e2e8f0;">
            <button class="button jesp-modal-close"><?php esc_html_e('Cancel', 'jesp-erp'); ?></button>
            <button class="button button-primary" id="fin-save-expense"><?php esc_html_e('Save Expense', 'jesp-erp'); ?></button>
        </div>
    </div>
</div>
