<?php if (!defined('ABSPATH')) {
    exit;
}
$custom_css = get_option('jesp_erp_custom_css', '');
$hidden_tabs = (array)get_option('jesp_erp_hidden_tabs', array());

$manageable_tabs = array(
    'jesp-erp-stock' => array('label' => __('Stock Management', 'jesp-erp'), 'icon' => 'dashicons-archive'),
    'jesp-erp-import' => array('label' => __('Import Products', 'jesp-erp'), 'icon' => 'dashicons-upload'),
    'jesp-erp-export' => array('label' => __('Export Products', 'jesp-erp'), 'icon' => 'dashicons-download'),
    'jesp-erp-discounts' => array('label' => __('Bulk Discounts', 'jesp-erp'), 'icon' => 'dashicons-tag'),
    'jesp-erp-orders' => array('label' => __('Orders & Analytics', 'jesp-erp'), 'icon' => 'dashicons-chart-bar'),
    'jesp-erp-customers' => array('label' => __('Customers', 'jesp-erp'), 'icon' => 'dashicons-groups'),
    'jesp-erp-hero' => array('label' => __('Hero Products', 'jesp-erp'), 'icon' => 'dashicons-star-filled'),
    'jesp-erp-invoices' => array('label' => __('Invoice Maker', 'jesp-erp'), 'icon' => 'dashicons-media-spreadsheet'),
    'jesp-erp-finance' => array('label' => __('Finance', 'jesp-erp'), 'icon' => 'dashicons-chart-area'),
);
?>
<style>
.jesp-tab-toggle-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #f1f5f9;}
.jesp-tab-toggle-row:last-child{border-bottom:none;}
.jesp-tab-toggle-label{display:flex;align-items:center;gap:8px;font-size:13px;color:#374151;}
.jesp-tab-toggle-label .dashicons{color:#6366f1;font-size:16px;width:16px;height:16px;}
.jesp-toggle-switch{position:relative;display:inline-block;width:40px;height:22px;flex-shrink:0;}
.jesp-toggle-switch input{opacity:0;width:0;height:0;}
.jesp-toggle-slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#d1d5db;border-radius:22px;transition:.3s;}
.jesp-toggle-slider:before{position:absolute;content:"";height:16px;width:16px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s;}
.jesp-toggle-switch input:checked+.jesp-toggle-slider{background:#6366f1;}
.jesp-toggle-switch input:checked+.jesp-toggle-slider:before{transform:translateX(18px);}
</style>
<div class="wrap jesp-erp-wrap">
    <div class="jesp-erp-header">
        <h1><span class="dashicons dashicons-admin-customizer"></span> <?php esc_html_e('ERP Settings', 'jesp-erp'); ?></h1>
        <p class="jesp-erp-subtitle"><?php esc_html_e('Customise the look and feel of ERP Manager pages', 'jesp-erp'); ?></p>
    </div>

    <!-- Tab Visibility -->
    <div class="jesp-erp-card" style="margin-bottom:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h2 style="margin:0;"><span class="dashicons dashicons-visibility" style="color:#6366f1;margin-right:6px;"></span><?php esc_html_e('Tab Visibility', 'jesp-erp'); ?></h2>
            <button class="button button-primary" id="jesp-settings-save"><?php esc_html_e('Save Settings', 'jesp-erp'); ?></button>
        </div>
        <p style="color:#64748b;font-size:13px;margin-bottom:16px;">
            <?php esc_html_e('Toggle which menu tabs appear in the sidebar. Dashboard and Settings are always visible. Changes take effect after saving (page will reload).', 'jesp-erp'); ?>
        </p>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:0 24px;">
            <?php foreach ($manageable_tabs as $slug => $tab): ?>
            <div class="jesp-tab-toggle-row">
                <span class="jesp-tab-toggle-label">
                    <span class="dashicons <?php echo esc_attr($tab['icon']); ?>"></span>
                    <?php echo esc_html($tab['label']); ?>
                </span>
                <label class="jesp-toggle-switch">
                    <input type="checkbox" class="jesp-tab-toggle" value="<?php echo esc_attr($slug); ?>" <?php checked(!in_array($slug, $hidden_tabs, true)); ?>>
                    <span class="jesp-toggle-slider"></span>
                </label>
            </div>
            <?php
endforeach; ?>
        </div>
    </div>

    <div class="jesp-erp-row">
        <div class="jesp-erp-col-8">
            <div class="jesp-erp-card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                    <h2 style="margin:0;"><span class="dashicons dashicons-editor-code" style="color:#6366f1;margin-right:6px;"></span><?php esc_html_e('Custom CSS', 'jesp-erp'); ?></h2>
                    <div style="display:flex;gap:8px;">
                        <button class="button button-secondary" id="jesp-settings-clear"><?php esc_html_e('Clear', 'jesp-erp'); ?></button>
                        <button class="button button-primary" id="jesp-settings-save"><?php esc_html_e('Save Settings', 'jesp-erp'); ?></button>
                    </div>
                </div>
                <p style="color:#64748b;margin-bottom:12px;font-size:13px;">
                    <?php esc_html_e('CSS added here is applied to all ERP Manager admin pages. Changes take effect immediately after saving.', 'jesp-erp'); ?>
                </p>
                <textarea
                    id="jesp-custom-css-editor"
                    name="jesp_custom_css"
                    rows="24"
                    style="width:100%;font-family:monospace;font-size:13px;line-height:1.6;border:1px solid #e2e8f0;border-radius:6px;padding:12px;resize:vertical;background:#1e1e2e;color:#cdd6f4;"
                    placeholder="/* Add your custom CSS here */
.jesp-erp-wrap { }
.jesp-stat-card { }
.jesp-table th { }"
                ><?php echo esc_textarea($custom_css); ?></textarea>
                <p style="color:#94a3b8;font-size:11px;margin-top:8px;">
                    <?php esc_html_e('Tip: Use browser DevTools (F12) to inspect element class names, then override them here.', 'jesp-erp'); ?>
                </p>
            </div>
        </div>

        <div class="jesp-erp-col-4">
            <!-- Quick Reference -->
            <div class="jesp-erp-card">
                <h3 style="margin-top:0;"><span class="dashicons dashicons-info" style="color:#6366f1;"></span> <?php esc_html_e('Common Selectors', 'jesp-erp'); ?></h3>
                <div class="jesp-settings-ref">
                    <?php
$refs = array(
    '.jesp-erp-wrap' => 'Page wrapper',
    '.jesp-erp-header h1' => 'Page title',
    '.jesp-erp-card' => 'Card / panel',
    '.jesp-stat-card' => 'Summary stat card',
    '.jesp-stat-value' => 'Stat number',
    '.jesp-table' => 'Data table',
    '.jesp-table th' => 'Table header',
    '.jesp-badge' => 'Status badge',
    '.jesp-tab-btn' => 'Tab button',
    '.jesp-tab-btn.active' => 'Active tab',
    '.jesp-pagination' => 'Pagination bar',
    '.jesp-hero-item' => 'Hero product row',
    '.jesp-stat-blue' => 'Blue stat card',
    '.jesp-stat-green' => 'Green stat card',
    '.jesp-stat-red' => 'Red stat card',
    '.jesp-stat-purple' => 'Purple stat card',
);
foreach ($refs as $selector => $label):
?>
                    <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f1f5f9;font-size:12px;">
                        <code style="background:#f8fafc;padding:2px 6px;border-radius:3px;color:#6366f1;font-size:11px;"><?php echo esc_html($selector); ?></code>
                        <span style="color:#64748b;"><?php echo esc_html($label); ?></span>
                    </div>
                    <?php
endforeach; ?>
                </div>
            </div>

            <!-- Example Snippets -->
            <div class="jesp-erp-card" style="margin-top:0;">
                <h3 style="margin-top:0;"><span class="dashicons dashicons-clipboard" style="color:#6366f1;"></span> <?php esc_html_e('Example Snippets', 'jesp-erp'); ?></h3>
                <div style="font-size:12px;color:#64748b;line-height:1.8;">
                    <p style="margin:0 0 8px;font-weight:600;color:#374151;"><?php esc_html_e('Change card background:', 'jesp-erp'); ?></p>
                    <pre style="background:#f8fafc;padding:8px;border-radius:4px;font-size:11px;overflow:auto;margin:0 0 12px;">.jesp-erp-card {
  background: #f0f9ff;
  border: 1px solid #bae6fd;
}</pre>
                    <p style="margin:0 0 8px;font-weight:600;color:#374151;"><?php esc_html_e('Resize table font:', 'jesp-erp'); ?></p>
                    <pre style="background:#f8fafc;padding:8px;border-radius:4px;font-size:11px;overflow:auto;margin:0 0 12px;">.jesp-table td,
.jesp-table th {
  font-size: 12px;
  padding: 6px 10px;
}</pre>
                    <p style="margin:0 0 8px;font-weight:600;color:#374151;"><?php esc_html_e('Custom accent colour:', 'jesp-erp'); ?></p>
                    <pre style="background:#f8fafc;padding:8px;border-radius:4px;font-size:11px;overflow:auto;margin:0;">.jesp-tab-btn.active {
  background: #059669;
  color: #fff;
}</pre>
                </div>
            </div>
        </div>
    </div>
</div>
