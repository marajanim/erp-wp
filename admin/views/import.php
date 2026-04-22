<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="wrap jesp-erp-wrap">
    <div class="jesp-erp-header">
        <h1><span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Import Products', 'jesp-erp' ); ?></h1>
    </div>

    <div class="jesp-erp-row">
        <div class="jesp-erp-col-8">
            <div class="jesp-erp-card">
                <h2><?php esc_html_e( 'Upload CSV File', 'jesp-erp' ); ?></h2>
                <p class="description">
                    <?php esc_html_e( 'Upload a CSV file to bulk import or update products with stock data.', 'jesp-erp' ); ?>
                </p>

                <div class="jesp-upload-zone" id="erp-upload-zone">
                    <span class="dashicons dashicons-cloud-upload"></span>
                    <p><?php esc_html_e( 'Drag & drop your CSV file here, or click to browse', 'jesp-erp' ); ?></p>
                    <input type="file" id="erp-csv-file" accept=".csv,.txt" style="display:none">
                    <button class="button button-secondary" id="erp-browse-btn"><?php esc_html_e( 'Browse Files', 'jesp-erp' ); ?></button>
                </div>

                <div class="jesp-file-info" id="erp-file-info" style="display:none;">
                    <span class="dashicons dashicons-media-spreadsheet"></span>
                    <span id="erp-file-name"></span>
                    <button class="jesp-btn-remove" id="erp-remove-file">&times;</button>
                </div>

                <div class="jesp-progress-wrap" id="erp-import-progress" style="display:none;">
                    <div class="jesp-progress-bar">
                        <div class="jesp-progress-fill" id="erp-progress-fill"></div>
                    </div>
                    <span class="jesp-progress-text" id="erp-progress-text"><?php esc_html_e( 'Importing...', 'jesp-erp' ); ?></span>
                </div>

                <div class="jesp-import-actions">
                    <button class="button button-primary button-hero" id="erp-start-import" disabled>
                        <span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Start Import', 'jesp-erp' ); ?>
                    </button>
                </div>

                <!-- Results -->
                <div class="jesp-import-results" id="erp-import-results" style="display:none;">
                    <h3><?php esc_html_e( 'Import Results', 'jesp-erp' ); ?></h3>
                    <div class="jesp-result-grid">
                        <div class="jesp-result-item jesp-result-created">
                            <span class="jesp-result-num" id="result-created">0</span>
                            <span class="jesp-result-label"><?php esc_html_e( 'Created', 'jesp-erp' ); ?></span>
                        </div>
                        <div class="jesp-result-item jesp-result-updated">
                            <span class="jesp-result-num" id="result-updated">0</span>
                            <span class="jesp-result-label"><?php esc_html_e( 'Updated', 'jesp-erp' ); ?></span>
                        </div>
                        <div class="jesp-result-item jesp-result-skipped">
                            <span class="jesp-result-num" id="result-skipped">0</span>
                            <span class="jesp-result-label"><?php esc_html_e( 'Skipped', 'jesp-erp' ); ?></span>
                        </div>
                        <div class="jesp-result-item jesp-result-errors">
                            <span class="jesp-result-num" id="result-errors">0</span>
                            <span class="jesp-result-label"><?php esc_html_e( 'Errors', 'jesp-erp' ); ?></span>
                        </div>
                    </div>
                    <div id="erp-import-error-list" style="display:none;">
                        <h4><?php esc_html_e( 'Error Details', 'jesp-erp' ); ?></h4>
                        <ul id="erp-error-messages"></ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="jesp-erp-col-4">
            <div class="jesp-erp-card">
                <h2><?php esc_html_e( 'CSV Format Guide', 'jesp-erp' ); ?></h2>
                <p><?php esc_html_e( 'Your CSV file should contain the following columns:', 'jesp-erp' ); ?></p>
                <table class="jesp-table jesp-table-sm">
                    <thead>
                        <tr><th><?php esc_html_e( 'Column', 'jesp-erp' ); ?></th><th><?php esc_html_e( 'Required', 'jesp-erp' ); ?></th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Product Name</td><td><span class="jesp-badge jesp-badge-red"><?php esc_html_e( 'Yes', 'jesp-erp' ); ?></span></td></tr>
                        <tr><td>SKU</td><td><span class="jesp-badge jesp-badge-gray"><?php esc_html_e( 'Optional', 'jesp-erp' ); ?></span></td></tr>
                        <tr><td>Description</td><td><span class="jesp-badge jesp-badge-gray"><?php esc_html_e( 'Optional', 'jesp-erp' ); ?></span></td></tr>
                        <tr><td>Price</td><td><span class="jesp-badge jesp-badge-gray"><?php esc_html_e( 'Optional', 'jesp-erp' ); ?></span></td></tr>
                        <tr><td>Image URL</td><td><span class="jesp-badge jesp-badge-gray"><?php esc_html_e( 'Optional', 'jesp-erp' ); ?></span></td></tr>
                        <tr><td>Current Stock</td><td><span class="jesp-badge jesp-badge-gray"><?php esc_html_e( 'Optional', 'jesp-erp' ); ?></span></td></tr>
                        <tr><td>Min Stock Level</td><td><span class="jesp-badge jesp-badge-gray"><?php esc_html_e( 'Optional', 'jesp-erp' ); ?></span></td></tr>
                        <tr><td>Stock Location</td><td><span class="jesp-badge jesp-badge-gray"><?php esc_html_e( 'Optional', 'jesp-erp' ); ?></span></td></tr>
                    </tbody>
                </table>
                <p class="description" style="margin-top:12px;">
                    <?php esc_html_e( 'Stock Location values: "Warehouse" or "Sales Center". Defaults to Warehouse if omitted.', 'jesp-erp' ); ?>
                </p>
                <a href="<?php echo esc_url( JESP_ERP_PLUGIN_URL . 'assets/sample-import.csv' ); ?>" class="button" style="margin-top:12px;">
                    <span class="dashicons dashicons-download" style="margin-top:4px;"></span> <?php esc_html_e( 'Download Sample CSV', 'jesp-erp' ); ?>
                </a>
            </div>
        </div>
    </div>
</div>
