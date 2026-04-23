<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="wrap jesp-erp-wrap" id="jesp-invoice-wrap">
    <div class="jesp-erp-header">
        <h1><span class="dashicons dashicons-media-spreadsheet"></span> <?php esc_html_e('Invoice Maker', 'jesp-erp'); ?></h1>
        <p class="jesp-erp-subtitle"><?php esc_html_e('Create and manage offline sale invoices', 'jesp-erp'); ?></p>
    </div>

    <!-- ================================================================
         LIST VIEW
    ================================================================= -->
    <div id="jesp-inv-list-view">
        <div class="jesp-erp-card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <input type="text" id="inv-search" placeholder="<?php esc_attr_e('Search customer name…', 'jesp-erp'); ?>" class="regular-text" style="height:34px;">
                    <select id="inv-status-filter" style="height:34px;">
                        <option value=""><?php esc_html_e('All Statuses', 'jesp-erp'); ?></option>
                        <option value="draft"><?php esc_html_e('Draft', 'jesp-erp'); ?></option>
                        <option value="paid"><?php esc_html_e('Paid', 'jesp-erp'); ?></option>
                    </select>
                    <button class="button" id="inv-filter-btn"><?php esc_html_e('Filter', 'jesp-erp'); ?></button>
                </div>
                <button class="button button-primary" id="inv-new-btn">
                    <span class="dashicons dashicons-plus-alt2" style="margin-top:3px;"></span>
                    <?php esc_html_e('New Invoice', 'jesp-erp'); ?>
                </button>
            </div>

            <table class="jesp-table" id="inv-list-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Invoice #', 'jesp-erp'); ?></th>
                        <th><?php esc_html_e('Customer', 'jesp-erp'); ?></th>
                        <th><?php esc_html_e('Date', 'jesp-erp'); ?></th>
                        <th><?php esc_html_e('Total', 'jesp-erp'); ?></th>
                        <th><?php esc_html_e('Status', 'jesp-erp'); ?></th>
                        <th><?php esc_html_e('Actions', 'jesp-erp'); ?></th>
                    </tr>
                </thead>
                <tbody id="inv-list-body">
                    <tr><td colspan="6" class="jesp-loading"><?php esc_html_e('Loading…', 'jesp-erp'); ?></td></tr>
                </tbody>
            </table>
            <div id="inv-list-pagination" class="jesp-pagination"></div>
        </div>
    </div>

    <!-- ================================================================
         EDITOR VIEW
    ================================================================= -->
    <div id="jesp-inv-editor-view" style="display:none;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
            <button class="button" id="inv-back-btn">
                <span class="dashicons dashicons-arrow-left-alt" style="margin-top:3px;"></span>
                <?php esc_html_e('Back to Invoices', 'jesp-erp'); ?>
            </button>
            <div style="display:flex;gap:8px;">
                <button class="button button-secondary" id="inv-print-btn">
                    <span class="dashicons dashicons-printer" style="margin-top:3px;"></span>
                    <?php esc_html_e('Print Invoice', 'jesp-erp'); ?>
                </button>
                <button class="button button-primary" id="inv-save-btn">
                    <span class="dashicons dashicons-saved" style="margin-top:3px;"></span>
                    <?php esc_html_e('Save Invoice', 'jesp-erp'); ?>
                </button>
            </div>
        </div>

        <input type="hidden" id="inv-id" value="0">

        <div class="jesp-erp-row">
            <!-- Left column: customer + invoice meta -->
            <div class="jesp-erp-col-6">
                <div class="jesp-erp-card" style="margin-bottom:16px;">
                    <h3 style="margin-top:0;"><span class="dashicons dashicons-businessman" style="color:#6366f1;"></span> <?php esc_html_e('Customer Details', 'jesp-erp'); ?></h3>
                    <table class="form-table jesp-inv-form-table">
                        <tr>
                            <th><label for="inv-customer-name"><?php esc_html_e('Name', 'jesp-erp'); ?> <span style="color:#dc2626;">*</span></label></th>
                            <td><input type="text" id="inv-customer-name" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th><label for="inv-customer-phone"><?php esc_html_e('Phone', 'jesp-erp'); ?></label></th>
                            <td><input type="tel" id="inv-customer-phone" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="inv-customer-email"><?php esc_html_e('Email', 'jesp-erp'); ?></label></th>
                            <td><input type="email" id="inv-customer-email" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="inv-customer-address"><?php esc_html_e('Address', 'jesp-erp'); ?></label></th>
                            <td><textarea id="inv-customer-address" rows="3" class="large-text"></textarea></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Right column: invoice metadata -->
            <div class="jesp-erp-col-6">
                <div class="jesp-erp-card" style="margin-bottom:16px;">
                    <h3 style="margin-top:0;"><span class="dashicons dashicons-tag" style="color:#6366f1;"></span> <?php esc_html_e('Invoice Details', 'jesp-erp'); ?></h3>
                    <table class="form-table jesp-inv-form-table">
                        <tr>
                            <th><label for="inv-number"><?php esc_html_e('Invoice #', 'jesp-erp'); ?></label></th>
                            <td><input type="text" id="inv-number" class="regular-text" placeholder="<?php esc_attr_e('Auto-generated', 'jesp-erp'); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="inv-date"><?php esc_html_e('Date', 'jesp-erp'); ?></label></th>
                            <td><input type="date" id="inv-date" class="regular-text" value="<?php echo esc_attr(gmdate('Y-m-d')); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="inv-status"><?php esc_html_e('Status', 'jesp-erp'); ?></label></th>
                            <td>
                                <select id="inv-status">
                                    <option value="draft"><?php esc_html_e('Draft', 'jesp-erp'); ?></option>
                                    <option value="paid"><?php esc_html_e('Paid', 'jesp-erp'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Line items -->
        <div class="jesp-erp-card" style="margin-bottom:16px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                <h3 style="margin:0;"><span class="dashicons dashicons-list-view" style="color:#6366f1;"></span> <?php esc_html_e('Line Items', 'jesp-erp'); ?></h3>
                <button class="button button-secondary" id="inv-add-row">
                    <span class="dashicons dashicons-plus-alt2" style="margin-top:3px;"></span> <?php esc_html_e('Add Row', 'jesp-erp'); ?>
                </button>
            </div>
            <div style="overflow-x:auto;">
                <table class="jesp-table" id="inv-items-table">
                    <thead>
                        <tr>
                            <th style="width:38%;"><?php esc_html_e('Product / Description', 'jesp-erp'); ?></th>
                            <th style="width:15%;"><?php esc_html_e('SKU', 'jesp-erp'); ?></th>
                            <th style="width:10%;text-align:right;"><?php esc_html_e('Qty', 'jesp-erp'); ?></th>
                            <th style="width:15%;text-align:right;"><?php esc_html_e('Unit Price', 'jesp-erp'); ?></th>
                            <th style="width:15%;text-align:right;"><?php esc_html_e('Total', 'jesp-erp'); ?></th>
                            <th style="width:7%;text-align:center;"><?php esc_html_e('Del', 'jesp-erp'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="inv-items-body">
                        <!-- rows injected by JS -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Discount, Tax, Totals + Notes -->
        <div class="jesp-erp-row">
            <div class="jesp-erp-col-6">
                <div class="jesp-erp-card">
                    <h3 style="margin-top:0;"><span class="dashicons dashicons-editor-help" style="color:#6366f1;"></span> <?php esc_html_e('Notes', 'jesp-erp'); ?></h3>
                    <textarea id="inv-notes" rows="4" class="large-text" placeholder="<?php esc_attr_e('Payment terms, delivery info, thank you message…', 'jesp-erp'); ?>"></textarea>
                </div>
            </div>
            <div class="jesp-erp-col-6">
                <div class="jesp-erp-card">
                    <h3 style="margin-top:0;"><span class="dashicons dashicons-calculator" style="color:#6366f1;"></span> <?php esc_html_e('Totals', 'jesp-erp'); ?></h3>
                    <table class="jesp-inv-totals-table" style="width:100%;">
                        <tr>
                            <td style="padding:6px 0;color:#64748b;"><?php esc_html_e('Subtotal', 'jesp-erp'); ?></td>
                            <td style="text-align:right;font-weight:600;" id="inv-display-subtotal">—</td>
                        </tr>
                        <tr>
                            <td style="padding:6px 0;color:#64748b;"><?php esc_html_e('Discount', 'jesp-erp'); ?></td>
                            <td style="text-align:right;">
                                <div style="display:flex;gap:6px;justify-content:flex-end;align-items:center;">
                                    <select id="inv-discount-type" style="height:28px;font-size:12px;">
                                        <option value="none"><?php esc_html_e('None', 'jesp-erp'); ?></option>
                                        <option value="percentage">%</option>
                                        <option value="fixed"><?php echo esc_html(get_woocommerce_currency_symbol()); ?></option>
                                    </select>
                                    <input type="number" id="inv-discount-value" min="0" step="0.01" value="0" style="width:70px;height:28px;font-size:12px;text-align:right;">
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:6px 0;color:#64748b;"><?php esc_html_e('Tax Rate (%)', 'jesp-erp'); ?></td>
                            <td style="text-align:right;">
                                <input type="number" id="inv-tax-rate" min="0" max="100" step="0.01" value="0" style="width:70px;height:28px;font-size:12px;text-align:right;">
                            </td>
                        </tr>
                        <tr style="border-top:2px solid #6366f1;">
                            <td style="padding:10px 0 6px;font-size:15px;font-weight:700;color:#1e293b;"><?php esc_html_e('Grand Total', 'jesp-erp'); ?></td>
                            <td style="text-align:right;font-size:18px;font-weight:700;color:#6366f1;" id="inv-display-total">—</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.jesp-inv-form-table th { width: 120px; padding: 8px 0; font-weight: 600; color: #374151; vertical-align: top; padding-top: 10px; }
.jesp-inv-form-table td { padding: 6px 0; }
.jesp-inv-form-table input[type="text"],
.jesp-inv-form-table input[type="tel"],
.jesp-inv-form-table input[type="email"],
.jesp-inv-form-table input[type="date"],
.jesp-inv-form-table textarea { width: 100%; }
.jesp-inv-form-table select { min-width: 140px; }
#inv-items-table td { padding: 5px 8px; vertical-align: middle; }
#inv-items-table input { width: 100%; box-sizing: border-box; border: 1px solid #d1d5db; border-radius: 4px; padding: 4px 6px; font-size: 13px; }
#inv-items-table input[type="number"] { text-align: right; }
.inv-del-row { background: none; border: none; cursor: pointer; color: #dc2626; padding: 4px; display: flex; align-items: center; justify-content: center; margin: 0 auto; }
.inv-del-row:hover { color: #991b1b; }
</style>
