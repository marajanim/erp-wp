/**
 * ERP Manager — Admin JavaScript v2
 *
 * Handles all AJAX calls, table rendering, charts, modals, pagination,
 * inline editing, tabbed dashboard, date filtering, and UI interactions.
 */
(function ($) {
    'use strict';

    /* ====================================================================
       Utility helpers
       ==================================================================== */
    const ERP = {
        nonce: jespErp.nonce,
        ajaxUrl: jespErp.ajaxUrl,
        currency: jespErp.currency || '$',
        categories: jespErp.categories || {},
        strings: jespErp.strings || {},

        ajax(action, data = {}) {
            data.action = action;
            data.nonce = this.nonce;
            return $.post(this.ajaxUrl, data);
        },

        formatMoney(val) {
            const n = parseFloat(val) || 0;
            return this.currency + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        formatDate(dateStr) {
            if (!dateStr) return '—';
            const d = new Date(dateStr);
            return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
        },

        toast(message, type = 'success') {
            const $t = $(`<div class="jesp-toast jesp-toast-${type}">${message}</div>`).appendTo('body');
            setTimeout(() => $t.fadeOut(400, function () { $(this).remove(); }), 3500);
        },

        buildPagination(container, currentPage, totalPages, callback) {
            const $c = $(container).empty();
            if (totalPages <= 1) return;

            const addBtn = (label, page, cls = '') => {
                $c.append(`<a class="jesp-page-btn ${cls}" data-page="${page}">${label}</a>`);
            };

            addBtn('«', Math.max(1, currentPage - 1), currentPage <= 1 ? 'disabled' : '');

            let start = Math.max(1, currentPage - 2);
            let end = Math.min(totalPages, start + 4);
            if (end - start < 4) start = Math.max(1, end - 4);

            for (let i = start; i <= end; i++) {
                addBtn(i, i, i === currentPage ? 'active' : '');
            }

            addBtn('»', Math.min(totalPages, currentPage + 1), currentPage >= totalPages ? 'disabled' : '');

            $c.append(`<span class="jesp-page-info">Page ${currentPage} of ${totalPages}</span>`);

            $c.off('click').on('click', '.jesp-page-btn:not(.disabled):not(.active)', function () {
                callback(parseInt($(this).data('page'), 10));
            });
        },

        populateCategorySelect(selector) {
            const $sel = $(selector);
            if (!$sel.length) return;
            $.each(this.categories, function (id, name) {
                $sel.append(`<option value="${id}">${name}</option>`);
            });
        },

        /**
         * Flash a cell with a highlight to indicate change.
         */
        flashCell($el) {
            $el.addClass('jesp-cell-updated');
            setTimeout(() => $el.removeClass('jesp-cell-updated'), 2000);
        },

        /**
         * Get the current dashboard date range.
         * Falls back to 30-day range if inputs are empty.
         */
        getDashRange() {
            let from = $('#dash-date-from').val() || '';
            let to = $('#dash-date-to').val() || '';
            if (!from || !to) {
                const now = new Date();
                to = now.toISOString().slice(0, 10);
                from = new Date(Date.now() - 30 * 86400000).toISOString().slice(0, 10);
            }
            return { date_from: from, date_to: to };
        },

        /**
         * Set dashboard date range from days preset.
         */
        setDashRange(days) {
            const to = new Date();
            const from = new Date(Date.now() - days * 86400000);
            $('#dash-date-to').val(to.toISOString().slice(0, 10));
            $('#dash-date-from').val(from.toISOString().slice(0, 10));
        },

        /**
         * Escape HTML for safe insertion.
         */
        esc(str) {
            const div = document.createElement('div');
            div.appendChild(document.createTextNode(str || ''));
            return div.innerHTML;
        }
    };

    /* ====================================================================
       Init on document ready
       ==================================================================== */
    $(function () {
        // Populate category dropdowns.
        ERP.populateCategorySelect('#erp-stock-category');
        ERP.populateCategorySelect('#erp-export-category');
        ERP.populateCategorySelect('#discount-category');
        ERP.populateCategorySelect('#sv-category');

        // Detect current page.
        const page = new URLSearchParams(window.location.search).get('page') || '';

        if (page === 'jesp-erp') initDashboard();
        if (page === 'jesp-erp-stock') initStockManagement();
        if (page === 'jesp-erp-import') initImport();
        if (page === 'jesp-erp-export') initExport();
        if (page === 'jesp-erp-discounts') initDiscounts();
        if (page === 'jesp-erp-orders') initOrders();
        if (page === 'jesp-erp-customers') initCustomers();
        if (page === 'jesp-erp-hero') initHeroProductsPage();
        if (page === 'jesp-erp-settings') initSettings();
        if (page === 'jesp-erp-invoices') initInvoiceMaker();
        if (page === 'jesp-erp-finance') initFinancePage();
    });

    /* ====================================================================
       DASHBOARD (v2 — tabbed, date-filtered)
       ==================================================================== */
    let dashChart = null;
    let heroChart = null;

    function initDashboard() {
        // Set default 30-day range.
        ERP.setDashRange(30);

        // Force-hide inactive tab panels with inline styles (WP admin CSS compat).
        $('.jesp-tab-panel').each(function () {
            if (!$(this).hasClass('active')) {
                $(this).hide();
            } else {
                $(this).show();
            }
        });

        // Load initial data for all sections.
        refreshDashboard();
        loadHeroProducts();
        loadStockValue();
        loadDashCustomers();
        loadLowStockWidget();

        // Tab navigation.
        $(document).on('click', '.jesp-tab-btn', function () {
            const tab = $(this).data('tab');
            $('.jesp-tab-btn').removeClass('active');
            $(this).addClass('active');

            // Hide all panels, show active (jQuery show/hide for WP compat).
            $('.jesp-tab-panel').removeClass('active').hide();
            $(`#tab-${tab}`).addClass('active').show();

            // Refresh data for switched-to tab.
            if (tab === 'hero') loadHeroProducts();
            if (tab === 'stock-value') loadStockValue();
            if (tab === 'customers') loadDashCustomers();
            if (tab === 'brand-revenue') loadBrandRevenue();
        });

        // Date range buttons.
        $(document).on('click', '.jesp-dash-range', function () {
            const days = parseInt($(this).data('days'));
            $('.jesp-dash-range').removeClass('active');
            $(this).addClass('active');

            if (days === 0) {
                // Show custom fields.
                $('.jesp-custom-range').show();
                return;
            }
            $('.jesp-custom-range').hide();
            ERP.setDashRange(days);
            refreshDashboard();
            // Also refresh active tab data.
            const activeTab = $('.jesp-tab-btn.active').data('tab');
            if (activeTab === 'hero') loadHeroProducts();
            if (activeTab === 'brand-revenue') loadBrandRevenue();
        });

        // Custom date apply.
        $('#dash-apply-custom').on('click', function () {
            refreshDashboard();
            const activeTab = $('.jesp-tab-btn.active').data('tab');
            if (activeTab === 'hero') loadHeroProducts();
            if (activeTab === 'brand-revenue') loadBrandRevenue();
        });
    }

    function refreshDashboard() {
        const range = ERP.getDashRange();

        // Stats cards.
        ERP.ajax('erp_get_dashboard', range).done(function (res) {
            if (!res.success) return;
            const d = res.data;
            $('#stat-total-orders').text(d.total_orders);
            $('#stat-total-revenue').text(ERP.formatMoney(d.total_revenue));
            $('#stat-total-products').text(d.total_products);
            $('#stat-low-stock').text(d.low_stock_count);
            $('#stat-total-customers').text(d.total_customers);
        });

        // Revenue chart.
        ERP.ajax('erp_get_order_chart', range).done(function (res) {
            if (!res.success) return;
            const data = res.data || [];
            const ctx = document.getElementById('erp-revenue-chart');
            if (!ctx) return;

            if (dashChart) dashChart.destroy();

            dashChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => d.order_date),
                    datasets: [{
                        label: 'Revenue',
                        data: data.map(d => parseFloat(d.revenue)),
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99,102,241,.1)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2.5,
                        pointRadius: 3,
                        pointBackgroundColor: '#6366f1',
                    }, {
                        label: 'Orders',
                        data: data.map(d => parseInt(d.order_count)),
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16,185,129,.08)',
                        fill: false,
                        tension: 0.4,
                        borderWidth: 2,
                        pointRadius: 2,
                        yAxisID: 'y1',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: { legend: { position: 'bottom' } },
                    scales: {
                        y: { beginAtZero: true, title: { display: true, text: 'Revenue' } },
                        y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Orders' } },
                    }
                }
            });
        });

        // If hero tab is active, refresh it too.
        if ($('#tab-hero').hasClass('active')) loadHeroProducts();
    }

    /* -------- Hero Products Tab -------- */
    function loadHeroProducts() {
        const range = ERP.getDashRange();

        ERP.ajax('erp_get_hero_products', range).done(function (res) {
            if (!res.success) return;
            const items = res.data.items || [];

            // Build list.
            const $list = $('#erp-hero-list').empty();
            if (!items.length) {
                $list.html('<p class="jesp-loading">No sales data for this period.</p>');
                return;
            }

            items.forEach((item, idx) => {
                const rank = idx + 1;
                const medal = rank <= 3 ? ['🥇', '🥈', '🥉'][rank - 1] : `#${rank}`;
                $list.append(`
                    <div class="jesp-hero-item">
                        <span class="jesp-hero-rank">${medal}</span>
                        <div class="jesp-hero-info">
                            <strong>${ERP.esc(item.product_name || 'Product #' + item.product_id)}</strong>
                            <small>${item.order_count} orders · ${item.total_qty_sold} sold</small>
                        </div>
                        <span class="jesp-hero-revenue">${ERP.formatMoney(item.total_revenue)}</span>
                    </div>
                `);
            });

            // Build chart.
            const ctx = document.getElementById('erp-hero-chart');
            if (!ctx) return;
            if (heroChart) heroChart.destroy();

            const top = items.slice(0, 10);
            const colors = ['#6366f1', '#8b5cf6', '#a78bfa', '#3b82f6', '#10b981', '#f97316', '#ef4444', '#14b8a6', '#f59e0b', '#ec4899'];

            heroChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: top.map(i => (i.product_name || 'Product').substring(0, 20)),
                    datasets: [{
                        label: 'Revenue',
                        data: top.map(i => parseFloat(i.total_revenue)),
                        backgroundColor: colors.slice(0, top.length).map(c => c + 'CC'),
                        borderColor: colors.slice(0, top.length),
                        borderWidth: 1.5,
                        borderRadius: 6,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, title: { display: true, text: 'Revenue' } },
                    }
                }
            });
        });
    }

    /* -------- Stock Value Tab -------- */
    function loadStockValue(page = 1) {
        const category = $('#sv-category').val() || 0;

        ERP.ajax('erp_get_stock_value', { category, per_page: 50, page }).done(function (res) {
            if (!res.success) return;
            const d = res.data;

            // Summary cards.
            $('#sv-total-value').text(ERP.formatMoney(d.summary.grand_total));
            $('#sv-warehouse-value').text(ERP.formatMoney(d.summary.warehouse_total));
            $('#sv-sales-value').text(ERP.formatMoney(d.summary.sales_center_total));

            // Table.
            const $body = $('#sv-body');
            if (!d.items.length) {
                $body.html('<tr><td colspan="8" class="jesp-loading">' + (ERP.strings.no_results || 'No results.') + '</td></tr>');
                return;
            }

            let html = '';
            d.items.forEach(item => {
                const isLow = parseInt(item.min_level) > 0 && parseInt(item.total_qty) <= parseInt(item.min_level);
                const rowClass = isLow ? 'jesp-row-low-stock' : '';
                const statusBadge = isLow
                    ? '<span class="jesp-badge jesp-badge-red">Low Stock</span>'
                    : '<span class="jesp-badge jesp-badge-green">Sufficient</span>';

                html += `<tr class="${rowClass}">
                    <td><strong>${ERP.esc(item.product_name)}</strong></td>
                    <td>${ERP.esc(item.sku || '—')}</td>
                    <td>${item.buying_price > 0 ? ERP.formatMoney(item.buying_price) : '—'}</td>
                    <td>${item.warehouse_qty}</td>
                    <td>${item.sales_center_qty}</td>
                    <td><strong>${item.total_qty}</strong></td>
                    <td><strong>${ERP.formatMoney(item.total_value)}</strong></td>
                    <td>${statusBadge}</td>
                </tr>`;
            });
            $body.html(html);

            ERP.buildPagination('#sv-pagination', page, d.pages, (p) => loadStockValue(p));
        });
    }

    // Stock value category filter.
    $(document).on('change', '#sv-category', function () { loadStockValue(); });

    /* -------- Dashboard Customer Insights Tab -------- */
    let dashCustDebounce;

    function loadDashCustomers(page = 1) {
        const $body = $('#di-cust-body');
        $body.html('<tr><td colspan="7" class="jesp-loading">' + (ERP.strings.loading || 'Loading...') + '</td></tr>');

        ERP.ajax('erp_get_customers', {
            per_page: 20,
            page: page,
            search: $('#di-cust-search').val() || '',
            min_spent: $('#di-cust-min').val() || 0,
        }).done(function (res) {
            if (!res.success || !res.data.items.length) {
                $body.html('<tr><td colspan="7" class="jesp-loading">' + (ERP.strings.no_results || 'No results.') + '</td></tr>');
                return;
            }

            let html = '';
            res.data.items.forEach(c => {
                const aov = c.aov ? ERP.formatMoney(c.aov) : '—';
                html += `<tr>
                    <td><strong>${ERP.esc(c.customer_name)}</strong></td>
                    <td>${ERP.esc(c.phone || '—')}</td>
                    <td>${ERP.esc(c.email || '—')}</td>
                    <td>${c.order_count}</td>
                    <td>${ERP.formatMoney(c.total_spent)}</td>
                    <td>${aov}</td>
                    <td>${ERP.formatDate(c.last_order_date)}</td>
                    <td><button class="jesp-action-btn-sm jesp-di-view-cust" data-id="${c.id}" title="View"><span class="dashicons dashicons-visibility"></span></button></td>
                </tr>`;
            });
            $body.html(html);
            ERP.buildPagination('#di-cust-pagination', page, res.data.pages, (p) => loadDashCustomers(p));
        });
    }

    // Dashboard customer search (debounced).
    $(document).on('input', '#di-cust-search', function () {
        clearTimeout(dashCustDebounce);
        dashCustDebounce = setTimeout(() => loadDashCustomers(), 350);
    });
    $(document).on('click', '#di-cust-filter', function () { loadDashCustomers(); });

    // View customer detail (within dashboard tab).
    $(document).on('click', '.jesp-di-view-cust', function () {
        const id = $(this).data('id');
        loadDashCustDetail(id);
    });

    $(document).on('click', '#di-cust-back', function () {
        $('#di-cust-detail').hide();
        $('#di-cust-list-view').show();
    });

    function loadDashCustDetail(customerId, page = 1) {
        ERP.ajax('erp_get_customer_orders', { customer_id: customerId, per_page: 20, page }).done(function (res) {
            if (!res.success) { ERP.toast(res.data?.message || 'Error', 'error'); return; }

            const c = res.data.customer;
            const orders = res.data.orders;

            $('#di-p-name').text(c.customer_name);
            $('#di-p-phone').text(c.phone || '—');
            $('#di-p-email').text(c.email || '—');
            $('#di-p-address').text(c.address || '—');
            $('#di-p-spent').text(ERP.formatMoney(c.total_spent));
            $('#di-p-orders').text(c.order_count);

            const $orderBody = $('#di-p-orders-body');
            if (!orders.items.length) {
                $orderBody.html('<tr><td colspan="5" class="jesp-loading">No orders found.</td></tr>');
            } else {
                let html = '';
                orders.items.forEach(o => {
                    const itemsList = (o.items || []).map(i => `${i.name} × ${i.qty}`).join(', ') || '—';
                    html += `<tr>
                        <td>#${o.order_number || o.order_id}</td>
                        <td>${ERP.formatDate(o.order_date)}</td>
                        <td>${itemsList}</td>
                        <td><span class="jesp-badge jesp-badge-blue">${o.status_label || o.status}</span></td>
                        <td>${ERP.formatMoney(o.order_total)}</td>
                    </tr>`;
                });
                $orderBody.html(html);
            }

            ERP.buildPagination('#di-p-orders-pag', page, orders.pages, (p) => loadDashCustDetail(customerId, p));

            $('#di-cust-list-view').hide();
            $('#di-cust-detail').show();
        });
    }

    /* ====================================================================
       LOW STOCK WIDGET (Dashboard)
       ==================================================================== */
    function loadLowStockWidget() {
        const $list = $('#low-stock-alert-list');
        if (!$list.length) return;
        $list.html('<p class="jesp-loading">' + (ERP.strings.loading || 'Loading...') + '</p>');

        ERP.ajax('erp_get_low_stock_products', { limit: 5 }).done(function (res) {
            if (!res.success || !res.data.items.length) {
                $list.html('<p style="color:#64748b;padding:8px 0;">No low stock items.</p>');
                return;
            }
            let html = '<div class="jesp-low-stock-list">';
            res.data.items.forEach(item => {
                const pct = parseInt(item.min_level) > 0 ? Math.min(100, Math.round((parseInt(item.total_qty) / parseInt(item.min_level)) * 100)) : 0;
                const barColor = pct <= 30 ? '#ef4444' : pct <= 70 ? '#f59e0b' : '#22c55e';
                html += `<div class="jesp-low-stock-item">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <strong>${ERP.esc(item.product_name)}</strong>
                        <span><span style="color:${barColor};font-weight:600;">${item.total_qty}</span> / ${item.min_level}</span>
                    </div>
                    <div style="background:#e2e8f0;border-radius:4px;height:6px;overflow:hidden;">
                        <div style="background:${barColor};height:100%;width:${pct}%;border-radius:4px;transition:width .3s;"></div>
                    </div>
                </div>`;
            });
            html += '</div>';
            $list.html(html);
        });
    }

    /* ====================================================================
       HERO PRODUCTS PAGE (full ranked list)
       ==================================================================== */
    let heroPageState = { page: 1, perPage: 20, search: '', dateFrom: '', dateTo: '' };
    let heroPageMaxRevenue = 0;

    function initHeroProductsPage() {
        // Default to 30-day range.
        const to = new Date().toISOString().slice(0, 10);
        const from = new Date(Date.now() - 30 * 86400000).toISOString().slice(0, 10);
        $('#hp-date-from').val(from);
        $('#hp-date-to').val(to);
        heroPageState.dateFrom = from;
        heroPageState.dateTo = to;

        loadHeroPageList();

        // Quick range buttons.
        $(document).on('click', '.jesp-hp-range', function () {
            const days = parseInt($(this).data('days'));
            $('.jesp-hp-range').removeClass('active');
            $(this).addClass('active');

            if (days === 0) {
                $('.jesp-hp-custom-range').show();
                return;
            }
            $('.jesp-hp-custom-range').hide();
            const t = new Date().toISOString().slice(0, 10);
            const f = new Date(Date.now() - days * 86400000).toISOString().slice(0, 10);
            $('#hp-date-from').val(f);
            $('#hp-date-to').val(t);
            heroPageState.dateFrom = f;
            heroPageState.dateTo = t;
            heroPageState.page = 1;
            loadHeroPageList();
        });

        // Custom date apply.
        $('#hp-apply-custom').on('click', function () {
            heroPageState.dateFrom = $('#hp-date-from').val();
            heroPageState.dateTo = $('#hp-date-to').val();
            heroPageState.page = 1;
            loadHeroPageList();
        });

        // Search (debounced).
        let hpSearchTimer;
        $('#hp-search').on('input', function () {
            clearTimeout(hpSearchTimer);
            hpSearchTimer = setTimeout(function () {
                heroPageState.search = $('#hp-search').val();
                heroPageState.page = 1;
                loadHeroPageList();
            }, 350);
        });

        // Per page.
        $('#hp-per-page').on('change', function () {
            heroPageState.perPage = parseInt($(this).val());
            heroPageState.page = 1;
            loadHeroPageList();
        });
    }

    function loadHeroPageList(page) {
        if (page !== undefined) heroPageState.page = page;

        const $body = $('#hp-table-body');
        $body.html('<tr><td colspan="8" class="jesp-loading">' + (ERP.strings.loading || 'Loading...') + '</td></tr>');

        ERP.ajax('erp_get_hero_products_list', {
            date_from: heroPageState.dateFrom,
            date_to: heroPageState.dateTo,
            search: heroPageState.search,
            per_page: heroPageState.perPage,
            page: heroPageState.page,
        }).done(function (res) {
            if (!res.success || !res.data.items.length) {
                $body.html('<tr><td colspan="8" class="jesp-loading">No products found for this period.</td></tr>');
                $('#hp-summary-products').text('0');
                $('#hp-summary-revenue').text(ERP.formatMoney(0));
                $('#hp-summary-top').text('—');
                return;
            }

            const items = res.data.items;
            const startRank = (heroPageState.page - 1) * heroPageState.perPage + 1;

            // Compute page revenue total + max for performance bar.
            const pageRevTotal = items.reduce((s, i) => s + parseFloat(i.total_revenue), 0);
            heroPageMaxRevenue = Math.max(...items.map(i => parseFloat(i.total_revenue)));

            // Update summary cards.
            $('#hp-summary-products').text(res.data.total);
            $('#hp-summary-revenue').text(ERP.formatMoney(pageRevTotal));
            $('#hp-summary-top').text(
                startRank === 1 && items[0]
                    ? (items[0].sku || items[0].product_name || 'Product #' + items[0].product_id).substring(0, 22)
                    : '—'
            );

            let html = '';
            items.forEach(function (item, idx) {
                const rank = startRank + idx;
                let medal;
                if (rank === 1) medal = '<span style="font-size:22px;">🥇</span>';
                else if (rank === 2) medal = '<span style="font-size:22px;">🥈</span>';
                else if (rank === 3) medal = '<span style="font-size:22px;">🥉</span>';
                else medal = '<span style="font-weight:700;color:#64748b;">#' + rank + '</span>';

                const thumb = item.thumbnail_url
                    ? '<img src="' + item.thumbnail_url + '" width="44" height="44" style="object-fit:cover;border-radius:6px;vertical-align:middle;">'
                    : '<span class="dashicons dashicons-format-image" style="color:#cbd5e1;font-size:32px;width:40px;height:40px;line-height:44px;"></span>';

                const barPct = heroPageMaxRevenue > 0
                    ? ((parseFloat(item.total_revenue) / heroPageMaxRevenue) * 100).toFixed(1)
                    : 0;
                const barColor = rank === 1 ? '#f59e0b' : rank === 2 ? '#94a3b8' : rank === 3 ? '#b45309' : '#6366f1';

                html += '<tr>' +
                    '<td style="text-align:center;">' + medal + '</td>' +
                    '<td>' + thumb + '</td>' +
                    '<td><strong>' + ERP.esc(item.product_name || 'Product #' + item.product_id) + '</strong></td>' +
                    '<td>' + ERP.esc(item.sku || '—') + '</td>' +
                    '<td style="text-align:center;">' + item.order_count + '</td>' +
                    '<td style="text-align:center;">' + item.total_qty_sold + '</td>' +
                    '<td><strong>' + ERP.formatMoney(item.total_revenue) + '</strong></td>' +
                    '<td style="min-width:120px;">' +
                    '<div style="background:#e2e8f0;border-radius:4px;height:8px;overflow:hidden;">' +
                    '<div style="width:' + barPct + '%;height:100%;background:' + barColor + ';border-radius:4px;transition:width .4s;"></div>' +
                    '</div>' +
                    '</td>' +
                    '</tr>';
            });
            $body.html(html);

            ERP.buildPagination('#hp-pagination', heroPageState.page, res.data.pages, loadHeroPageList);
        });
    }

    /* ====================================================================
       STOCK MANAGEMENT (v2 — inline editing)
       ==================================================================== */
    let stockState = { page: 1, perPage: 20, search: '', category: 0, stockStatus: '', orderby: 'product_name', order: 'ASC' };
    let debounceTimer;

    function initStockManagement() {
        // Check URL for pre-filter (e.g. from dashboard low stock click).
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('stock_status') === 'low') {
            stockState.stockStatus = 'low';
            $('#erp-stock-status').val('low');
        }

        loadStockTable();

        // Debounced search.
        $('#erp-stock-search').on('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => { stockState.search = $(this).val(); stockState.page = 1; loadStockTable(); }, 350);
        });

        // Filters.
        $('#erp-stock-category').on('change', function () { stockState.category = $(this).val(); stockState.page = 1; loadStockTable(); });
        $('#erp-stock-status').on('change', function () { stockState.stockStatus = $(this).val(); stockState.page = 1; loadStockTable(); });
        $('#erp-stock-per-page').on('change', function () { stockState.perPage = parseInt($(this).val()); stockState.page = 1; loadStockTable(); });

        // Sorting.
        $(document).on('click', '.jesp-sortable', function () {
            const col = $(this).data('sort');
            if (stockState.orderby === col) {
                stockState.order = stockState.order === 'ASC' ? 'DESC' : 'ASC';
            } else {
                stockState.orderby = col;
                stockState.order = 'ASC';
            }
            $('.jesp-sortable').removeClass('asc desc');
            $(this).addClass(stockState.order.toLowerCase());
            stockState.page = 1;
            loadStockTable();
        });

        // ---- Inline Edit Trigger ---- //
        $(document).on('click', '.jesp-inline-edit-btn', function (e) {
            e.stopPropagation();
            const $cell = $(this).closest('.jesp-editable-cell');
            const field = $cell.data('field');
            const pid = $cell.closest('tr').data('product-id');
            const currentVal = $cell.data('value');

            // Don't open if already editing.
            if ($cell.find('.jesp-inline-input').length) return;

            const inputType = (field === 'sku') ? 'text' : 'number';
            const step = (field === 'regular_price' || field === 'sale_price' || field === 'buying_price') ? '0.01' : '1';
            const min = (field === 'sku') ? '' : '0';

            const $displaySpan = $cell.find('.jesp-editable-value');
            $displaySpan.hide();
            $(this).hide();

            const $input = $(`<input type="${inputType}" class="jesp-inline-input" value="${ERP.esc(String(currentVal || ''))}" min="${min}" step="${step}">`);
            const $save = $('<button class="jesp-inline-save" title="Save"><span class="dashicons dashicons-yes"></span></button>');
            const $cancel = $('<button class="jesp-inline-cancel" title="Cancel"><span class="dashicons dashicons-no-alt"></span></button>');

            const $wrap = $('<span class="jesp-inline-wrap"></span>').append($input, $save, $cancel);
            $cell.append($wrap);
            $input.focus().select();

            function cancelEdit() {
                $wrap.remove();
                $displaySpan.show();
                $cell.find('.jesp-inline-edit-btn').show();
            }

            function saveEdit() {
                const newVal = $input.val().trim();
                if (newVal === String(currentVal || '')) { cancelEdit(); return; }

                // Validate client-side.
                if (field !== 'sku' && field !== 'sale_price' && field !== 'buying_price') {
                    if (newVal === '' || isNaN(newVal) || parseFloat(newVal) < 0) {
                        ERP.toast('Value must be a non-negative number.', 'error');
                        return;
                    }
                }
                if ((field === 'sale_price' || field === 'buying_price') && newVal !== '' && (isNaN(newVal) || parseFloat(newVal) < 0)) {
                    ERP.toast('Price must be non-negative or empty.', 'error');
                    return;
                }

                $save.prop('disabled', true);
                ERP.ajax('erp_inline_update_product', { product_id: pid, field: field, value: newVal }).done(function (res) {
                    if (res.success) {
                        const returnedVal = res.data.value;
                        // Update display.
                        let displayText;
                        if (field === 'regular_price' || field === 'sale_price' || field === 'buying_price') {
                            displayText = returnedVal ? ERP.formatMoney(returnedVal) : '—';
                        } else if (field === 'warehouse_stock' || field === 'sales_center_stock' || field === 'min_stock') {
                            displayText = returnedVal;
                        } else {
                            displayText = ERP.esc(returnedVal) || '—';
                        }

                        $displaySpan.text(displayText);
                        $cell.data('value', returnedVal);
                        cancelEdit();
                        ERP.flashCell($cell);
                        ERP.toast(res.data.message);

                        // If stock changed, update total column.
                        if (field === 'warehouse_stock' || field === 'sales_center_stock') {
                            const $row = $cell.closest('tr');
                            const wh = parseInt($row.find('[data-field="warehouse_stock"]').data('value')) || 0;
                            const sc = parseInt($row.find('[data-field="sales_center_stock"]').data('value')) || 0;
                            $row.find('.jesp-total-qty').text(wh + sc);
                        }
                    } else {
                        ERP.toast(res.data?.message || 'Error', 'error');
                    }
                }).fail(function () {
                    ERP.toast('Network error.', 'error');
                }).always(function () {
                    $save.prop('disabled', false);
                });
            }

            $cancel.on('click', cancelEdit);
            $save.on('click', saveEdit);
            $input.on('keydown', function (e) {
                if (e.key === 'Enter') saveEdit();
                if (e.key === 'Escape') cancelEdit();
            });
        });

        // ---- Image lightbox ---- //
        $(document).on('click', '.jesp-thumb-zoomable', function () {
            const src = $(this).data('full');
            const name = $(this).data('name');
            if (!src) return;

            if (!$('#jesp-img-lightbox').length) {
                $('body').append(
                    '<div id="jesp-img-lightbox" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.82);display:flex;align-items:center;justify-content:center;cursor:zoom-out;">' +
                    '<div style="position:relative;max-width:90vw;max-height:90vh;text-align:center;">' +
                    '<img id="jesp-lb-img" src="" alt="" style="max-width:90vw;max-height:80vh;border-radius:8px;box-shadow:0 8px 40px rgba(0,0,0,.6);">' +
                    '<p id="jesp-lb-name" style="color:#fff;margin-top:12px;font-size:15px;font-weight:600;text-shadow:0 1px 4px rgba(0,0,0,.8);"></p>' +
                    '<button id="jesp-lb-close" style="position:absolute;top:-14px;right:-14px;width:30px;height:30px;border-radius:50%;border:none;background:#fff;cursor:pointer;font-size:18px;line-height:30px;">&times;</button>' +
                    '</div></div>'
                );
                $(document).on('click', '#jesp-img-lightbox', function (e) {
                    if (e.target === this || $(e.target).is('#jesp-lb-close')) $(this).hide();
                });
                $(document).on('keydown.jespLightbox', function (e) {
                    if (e.key === 'Escape') $('#jesp-img-lightbox').hide();
                });
            }

            $('#jesp-lb-img').attr('src', src);
            $('#jesp-lb-name').text(name);
            $('#jesp-img-lightbox').css('display', 'flex');
        });

        // ---- Modal open (advanced adjustment) ---- //
        $(document).on('click', '.jesp-edit-stock', function () {
            const $row = $(this).closest('tr');
            $('#modal-product-id').val($row.data('product-id'));
            $('#modal-product-name').text($row.data('product-name'));
            $('#modal-quantity').val(0);
            $('#modal-reason').val('');
            $('#erp-stock-modal').show();
        });

        // Modal close.
        $(document).on('click', '.jesp-modal-close, .jesp-modal-overlay', function () {
            $(this).closest('.jesp-modal').hide();
        });

        // Save stock from modal.
        $('#erp-save-stock').on('click', function () {
            const $btn = $(this).prop('disabled', true).text(ERP.strings.saving || 'Saving...');
            const pid = $('#modal-product-id').val();
            const qty = $('#modal-quantity').val();
            const loc = $('#modal-location').val();
            const mode = $('#modal-mode').val();
            const reason = $('#modal-reason').val();
            const minStock = $('#modal-min-stock').val();

            ERP.ajax('erp_update_stock', { product_id: pid, quantity: qty, location: loc, mode: mode, reason: reason }).done(function (res) {
                if (res.success) {
                    if (parseInt(minStock) > 0) {
                        ERP.ajax('erp_update_min_stock', { product_id: pid, location: loc, min_stock: minStock });
                    }
                    ERP.toast(ERP.strings.saved || 'Saved!');
                    $('#erp-stock-modal').hide();
                    loadStockTable();
                } else {
                    ERP.toast(res.data?.message || ERP.strings.error || 'Error', 'error');
                }
            }).fail(function () {
                ERP.toast(ERP.strings.error || 'Error', 'error');
            }).always(function () {
                $btn.prop('disabled', false).text('Save Changes');
            });
        });

        // ---- Product Active/Inactive Toggle ---- //
        $(document).on('change', '.jesp-toggle-input', function () {
            const $toggle = $(this);
            const pid = $toggle.data('pid');
            $toggle.prop('disabled', true);

            ERP.ajax('erp_toggle_product_status', { product_id: pid }).done(function (res) {
                if (res.success) {
                    ERP.toast(res.data.message);
                    $toggle.closest('.jesp-toggle').attr('title', res.data.label);
                } else {
                    // Revert toggle on error.
                    $toggle.prop('checked', !$toggle.prop('checked'));
                    ERP.toast(res.data?.message || 'Error', 'error');
                }
            }).fail(function () {
                $toggle.prop('checked', !$toggle.prop('checked'));
                ERP.toast('Network error.', 'error');
            }).always(function () {
                $toggle.prop('disabled', false);
            });
        });

        // ---- Quick Edit Product ---- //
        let qeMediaFrame = null;

        $(document).on('click', '.jesp-quick-edit-btn', function () {
            const pid = $(this).data('pid');
            const $row = $(this).closest('tr');
            const name = $row.data('product-name');
            const thumbSrc = $row.find('.jesp-product-thumb').attr('src') || '';

            $('#qe-product-id').val(pid);
            $('#qe-title').val(name);
            $('#qe-description').val(''); // load async if needed - for now empty

            // Image preview.
            if (thumbSrc) {
                $('#qe-image-preview').attr('src', thumbSrc).show();
                $('#qe-remove-image').show();
            } else {
                $('#qe-image-preview').hide();
                $('#qe-remove-image').hide();
            }
            $('#qe-image-id').val(0);

            // Populate categories.
            const $catSelect = $('#qe-category');
            $catSelect.find('option:not(:first)').remove();
            if (ERP.categories) {
                $.each(ERP.categories, function (id, name) {
                    $catSelect.append(`<option value="${id}">${ERP.esc(name)}</option>`);
                });
            }

            $('#erp-quick-edit-modal').show();
        });

        // Image select via WP Media.
        $('#qe-select-image').on('click', function (e) {
            e.preventDefault();
            if (qeMediaFrame) {
                qeMediaFrame.open();
                return;
            }
            qeMediaFrame = wp.media({
                title: 'Select Product Image',
                button: { text: 'Use this image' },
                multiple: false,
            });
            qeMediaFrame.on('select', function () {
                const attachment = qeMediaFrame.state().get('selection').first().toJSON();
                $('#qe-image-id').val(attachment.id);
                $('#qe-image-preview').attr('src', attachment.sizes?.thumbnail?.url || attachment.url).show();
                $('#qe-remove-image').show();
            });
            qeMediaFrame.open();
        });

        $('#qe-remove-image').on('click', function () {
            $('#qe-image-id').val(0);
            $('#qe-image-preview').hide();
            $(this).hide();
        });

        // Save Quick Edit.
        $('#qe-save').on('click', function () {
            const $btn = $(this).prop('disabled', true);
            const data = {
                product_id: $('#qe-product-id').val(),
                title: $('#qe-title').val(),
                description: $('#qe-description').val(),
            };

            const imageId = parseInt($('#qe-image-id').val());
            if (imageId > 0) data.image_id = imageId;

            const catId = $('#qe-category').val();
            if (catId) data.category_id = catId;

            ERP.ajax('erp_quick_edit_product', data).done(function (res) {
                if (res.success) {
                    ERP.toast(res.data.message);
                    $('#erp-quick-edit-modal').hide();
                    loadStockTable();
                } else {
                    ERP.toast(res.data?.message || 'Error', 'error');
                }
            }).fail(function () {
                ERP.toast('Network error.', 'error');
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });

        // ---- Order Detail Modal ---- //
        $(document).on('click', '.jesp-order-detail-btn', function () {
            const oid = $(this).data('order-id');
            const $modal = $('#erp-order-detail-modal');
            $modal.show();
            $('#od-order-id').text('#' + oid);
            $('#od-body').html('<p class="jesp-loading">' + (ERP.strings.loading || 'Loading...') + '</p>');

            ERP.ajax('erp_get_order_detail', { order_id: oid }).done(function (res) {
                if (!res.success) {
                    $('#od-body').html('<p style="color:#ef4444;">' + (res.data?.message || 'Error') + '</p>');
                    return;
                }
                const o = res.data;
                let itemsHtml = '';
                (o.items || []).forEach(i => {
                    itemsHtml += `<tr>
                        <td>${ERP.esc(i.name)}</td>
                        <td style="color:#64748b;">${ERP.esc(i.sku || '\u2014')}</td>
                        <td>${i.qty}</td>
                        <td>${ERP.formatMoney(i.subtotal)}</td>
                        <td><strong>${ERP.formatMoney(i.total)}</strong></td>
                    </tr>`;
                });

                const html = `
                    <div class="jesp-od-section">
                        <h4><span class="dashicons dashicons-admin-users" style="color:#6366f1;"></span> Customer</h4>
                        <div class="jesp-od-grid">
                            <div><span class="jesp-od-label">Name</span><span>${ERP.esc(o.customer.name)}</span></div>
                            <div><span class="jesp-od-label">Email</span><span>${ERP.esc(o.customer.email || '\u2014')}</span></div>
                            <div><span class="jesp-od-label">Phone</span><span>${ERP.esc(o.customer.phone || '\u2014')}</span></div>
                            <div><span class="jesp-od-label">Address</span><span>${ERP.esc(o.customer.address || '\u2014')}</span></div>
                        </div>
                    </div>
                    <div class="jesp-od-section">
                        <h4><span class="dashicons dashicons-products" style="color:#6366f1;"></span> Order Items</h4>
                        <table class="jesp-table jesp-table-sm">
                            <thead><tr><th>Product</th><th>SKU</th><th>Qty</th><th>Subtotal</th><th>Total</th></tr></thead>
                            <tbody>${itemsHtml}</tbody>
                        </table>
                    </div>
                    <div class="jesp-od-section">
                        <h4><span class="dashicons dashicons-money-alt" style="color:#6366f1;"></span> Totals</h4>
                        <div class="jesp-od-grid">
                            <div><span class="jesp-od-label">Subtotal</span><span>${ERP.formatMoney(o.subtotal)}</span></div>
                            <div><span class="jesp-od-label">Tax</span><span>${ERP.formatMoney(o.tax_total)}</span></div>
                            <div><span class="jesp-od-label">Shipping</span><span>${ERP.formatMoney(o.shipping)}</span></div>
                            <div><span class="jesp-od-label">Discount</span><span>-${ERP.formatMoney(o.discount)}</span></div>
                            <div><span class="jesp-od-label"><strong>Total</strong></span><span><strong>${ERP.formatMoney(o.total)}</strong></span></div>
                        </div>
                    </div>
                    <div class="jesp-od-section">
                        <div class="jesp-od-grid">
                            <div><span class="jesp-od-label">Status</span><span class="jesp-badge jesp-badge-blue">${ERP.esc(o.status_label)}</span></div>
                            <div><span class="jesp-od-label">Date</span><span>${ERP.formatDate(o.order_date)}</span></div>
                            <div><span class="jesp-od-label">Payment</span><span>${ERP.esc(o.payment || '\u2014')}</span></div>
                        </div>
                    </div>`;

                $('#od-body').html(html);
            });
        });
    }

    function loadStockTable() {
        const $body = $('#erp-stock-body');
        $body.html('<tr><td colspan="13" class="jesp-loading">' + (ERP.strings.loading || 'Loading...') + '</td></tr>');

        ERP.ajax('erp_get_stock_list', {
            per_page: stockState.perPage,
            page: stockState.page,
            search: stockState.search,
            category: stockState.category,
            stock_status: stockState.stockStatus,
            orderby: stockState.orderby,
            order: stockState.order,
        }).done(function (res) {
            if (!res.success) { $body.html('<tr><td colspan="13" class="jesp-loading">' + (ERP.strings.error || 'Error') + '</td></tr>'); return; }

            const items = res.data.items || [];
            if (!items.length) { $body.html('<tr><td colspan="13" class="jesp-loading">' + (ERP.strings.no_results || 'No results.') + '</td></tr>'); return; }

            let html = '';
            items.forEach(item => {
                const isLow = parseInt(item.min_level) > 0 && parseInt(item.total_qty) <= parseInt(item.min_level);
                const rowClass = isLow ? 'jesp-row-low-stock' : '';
                const statusBadge = isLow
                    ? '<span class="jesp-badge jesp-badge-red">Low Stock</span>'
                    : '<span class="jesp-badge jesp-badge-green">Sufficient</span>';
                const thumb = item.thumbnail_url
                    ? `<img src="${item.thumbnail_url}" class="jesp-product-thumb jesp-thumb-zoomable" data-full="${item.full_image_url || item.thumbnail_url}" data-name="${ERP.esc(item.product_name)}" alt="" title="Click to enlarge" style="cursor:zoom-in;">`
                    : '<span class="dashicons dashicons-format-image" style="color:#cbd5e1;font-size:32px;width:40px;height:40px;"></span>';

                const isActive = item.product_status === 'publish';
                const toggleChecked = isActive ? 'checked' : '';

                const editableCell = (field, value, displayValue) => `
                    <td class="jesp-editable-cell" data-field="${field}" data-value="${ERP.esc(String(value || ''))}">
                        <span class="jesp-editable-value">${displayValue}</span>
                        <button class="jesp-inline-edit-btn" title="Edit"><span class="dashicons dashicons-edit"></span></button>
                    </td>`;

                html += `<tr class="${rowClass}" data-product-id="${item.product_id}" data-product-name="${ERP.esc(item.product_name)}">
                    <td>${thumb}</td>
                    <td><strong>${ERP.esc(item.product_name)}</strong></td>
                    ${editableCell('sku', item.sku, ERP.esc(item.sku || '—'))}
                    ${editableCell('regular_price', item.regular_price, item.regular_price ? ERP.formatMoney(item.regular_price) : '—')}
                    ${editableCell('sale_price', item.sale_price, item.sale_price ? ERP.formatMoney(item.sale_price) : '—')}
                    ${editableCell('warehouse_stock', item.warehouse_qty, item.warehouse_qty)}
                    ${editableCell('sales_center_stock', item.sales_center_qty, item.sales_center_qty)}
                    <td><strong class="jesp-total-qty">${item.total_qty}</strong></td>
                    ${editableCell('buying_price', item.buying_price, item.buying_price ? ERP.formatMoney(item.buying_price) : '—')}
                    ${editableCell('min_stock', item.min_level, item.min_level)}
                    <td>${statusBadge}</td>
                    <td>
                        <label class="jesp-toggle" title="${isActive ? 'Active' : 'Inactive'}">
                            <input type="checkbox" class="jesp-toggle-input" data-pid="${item.product_id}" ${toggleChecked}>
                            <span class="jesp-toggle-slider"></span>
                        </label>
                    </td>
                    <td>
                        <button class="jesp-action-btn-sm jesp-quick-edit-btn" data-pid="${item.product_id}" title="Quick Edit">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                    </td>
                </tr>`;
            });
            $body.html(html);
            ERP.buildPagination('#erp-stock-pagination', stockState.page, res.data.pages, (p) => { stockState.page = p; loadStockTable(); });
        });
    }

    /* ====================================================================
       IMPORT
       ==================================================================== */
    function initImport() {
        const $zone = $('#erp-upload-zone');
        const $fileInput = $('#erp-csv-file');
        let selectedFile = null;

        $('#erp-browse-btn').on('click', function (e) { e.stopPropagation(); $fileInput[0].click(); });
        $zone.on('click', function () { $fileInput[0].click(); });

        $fileInput.on('change', function () {
            if (this.files.length) selectFile(this.files[0]);
        });

        $zone.on('dragover', function (e) { e.preventDefault(); $(this).addClass('drag-over'); });
        $zone.on('dragleave drop', function (e) { e.preventDefault(); $(this).removeClass('drag-over'); });
        $zone.on('drop', function (e) { if (e.originalEvent.dataTransfer.files.length) selectFile(e.originalEvent.dataTransfer.files[0]); });

        function selectFile(file) {
            selectedFile = file;
            $zone.hide();
            $('#erp-file-name').text(file.name);
            $('#erp-file-info').show();
            $('#erp-start-import').prop('disabled', false);
        }

        $('#erp-remove-file').on('click', function () {
            selectedFile = null;
            $zone.show();
            $('#erp-file-info').hide();
            $('#erp-start-import').prop('disabled', true);
            $fileInput.val('');
        });

        $('#erp-start-import').on('click', function () {
            if (!selectedFile) return;
            const $btn = $(this).prop('disabled', true);

            $('#erp-import-progress').show();
            $('#erp-progress-fill').css('width', '60%');
            $('#erp-import-results').hide();

            const fd = new FormData();
            fd.append('action', 'erp_import_csv');
            fd.append('nonce', ERP.nonce);
            fd.append('csv_file', selectedFile);

            $.ajax({
                url: ERP.ajaxUrl,
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
            }).done(function (res) {
                $('#erp-progress-fill').css('width', '100%');
                setTimeout(() => { $('#erp-import-progress').hide(); }, 500);

                if (res.success) {
                    const d = res.data;
                    $('#result-created').text(d.created);
                    $('#result-updated').text(d.updated);
                    $('#result-skipped').text(d.skipped);
                    $('#result-errors').text(d.errors.length);
                    $('#erp-import-results').show();

                    if (d.errors.length) {
                        const $list = $('#erp-error-messages').empty();
                        d.errors.forEach(e => $list.append(`<li>${e}</li>`));
                        $('#erp-import-error-list').show();
                    } else {
                        $('#erp-import-error-list').hide();
                    }

                    ERP.toast(`Imported ${d.created} new, updated ${d.updated} products.`);
                } else {
                    ERP.toast(res.data?.message || 'Error', 'error');
                }
            }).fail(function () {
                ERP.toast('Error', 'error');
                $('#erp-import-progress').hide();
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });
    }

    /* ====================================================================
       EXPORT
       ==================================================================== */
    function initExport() {
        // Reflect checked state on the label styling.
        function syncFieldStyle($cb) {
            $cb.closest('.jesp-export-field-item').toggleClass('is-checked', $cb.is(':checked'));
        }

        // Sync all on load.
        $('.erp-export-field').each(function () { syncFieldStyle($(this)); });

        // Individual field toggle.
        $(document).on('change', '.erp-export-field', function () {
            syncFieldStyle($(this));
            const total = $('.erp-export-field').length;
            const checked = $('.erp-export-field:checked').length;
            $('#erp-export-select-all').prop('indeterminate', checked > 0 && checked < total)
                .prop('checked', checked === total);
        });

        // Select All toggle.
        $('#erp-export-select-all').on('change', function () {
            const on = $(this).is(':checked');
            $('.erp-export-field').prop('checked', on).each(function () { syncFieldStyle($(this)); });
        });

        // Initialise Select All state.
        (function () {
            const total = $('.erp-export-field').length;
            const checked = $('.erp-export-field:checked').length;
            $('#erp-export-select-all').prop('checked', checked === total)
                .prop('indeterminate', checked > 0 && checked < total);
        })();

        // Form submit — collect fields and redirect via GET.
        $('#erp-export-form').on('submit', function (e) {
            e.preventDefault();

            const selected = [];
            $('.erp-export-field:checked').each(function () { selected.push($(this).val()); });

            if (!selected.length) {
                ERP.toast('Please select at least one field.', 'error');
                return;
            }

            $('#erp-export-cat-hidden').val($('#erp-export-category').val());
            $('#erp-export-status-hidden').val($('input[name="export_stock_status"]:checked').val());
            $('#erp-export-fields-hidden').val(selected.join(','));

            const params = new URLSearchParams({
                action: 'erp_export_csv',
                nonce: ERP.nonce,
                category: $('#erp-export-cat-hidden').val(),
                stock_status: $('#erp-export-status-hidden').val(),
                fields: selected.join(','),
            });
            window.location.href = ERP.ajaxUrl + '?' + params.toString();
        });
    }

    /* ====================================================================
       BULK DISCOUNTS
       ==================================================================== */
    function initDiscounts() {
        loadDiscounts();

        $('#erp-apply-discount').on('click', function () {
            const $btn = $(this).prop('disabled', true);

            ERP.ajax('erp_apply_discount', {
                name: $('#discount-name').val(),
                discount_type: $('#discount-type').val(),
                discount_value: $('#discount-value').val(),
                category: $('#discount-category').val(),
                min_stock: $('#discount-min-stock').val() || '',
                max_stock: $('#discount-max-stock').val() || '',
                start_date: $('#discount-start-date').val(),
                end_date: $('#discount-end-date').val(),
            }).done(function (res) {
                if (res.success) {
                    ERP.toast(`Discount applied to ${res.data.affected_count} products.`);
                    $('#discount-name, #discount-value, #discount-min-stock, #discount-max-stock, #discount-start-date, #discount-end-date').val('');
                    loadDiscounts();
                } else {
                    ERP.toast(res.data?.message || 'Error', 'error');
                }
            }).fail(function () {
                ERP.toast('Error', 'error');
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });

        $(document).on('click', '.jesp-revert-discount', function () {
            if (!confirm('Revert this discount campaign?')) return;
            const id = $(this).data('id');
            ERP.ajax('erp_revert_discount', { discount_id: id }).done(function (res) {
                if (res.success) {
                    ERP.toast(`Reverted ${res.data.reverted_count} products.`);
                    loadDiscounts();
                } else {
                    ERP.toast(res.data?.message || 'Error', 'error');
                }
            });
        });
    }

    function loadDiscounts() {
        const $body = $('#erp-discounts-body');
        $body.html('<tr><td colspan="6" class="jesp-loading">' + (ERP.strings.loading || 'Loading...') + '</td></tr>');

        ERP.ajax('erp_get_discounts').done(function (res) {
            if (!res.success || !res.data.items.length) {
                $body.html('<tr><td colspan="6" class="jesp-loading">No discount campaigns yet.</td></tr>');
                return;
            }

            let html = '';
            res.data.items.forEach(d => {
                const affected = JSON.parse(d.affected_products || '[]');
                const statusBadge = d.status === 'active'
                    ? '<span class="jesp-badge jesp-badge-green">Active</span>'
                    : '<span class="jesp-badge jesp-badge-gray">Reverted</span>';
                const action = d.status === 'active'
                    ? `<button class="button button-secondary jesp-revert-discount" data-id="${d.id}">Revert</button>`
                    : '—';
                const typeLabel = d.discount_type === 'percentage' ? d.discount_value + '%' : ERP.formatMoney(d.discount_value);

                html += `<tr>
                    <td>${d.name}</td>
                    <td>${d.discount_type}</td>
                    <td>${typeLabel}</td>
                    <td>${affected.length}</td>
                    <td>${statusBadge}</td>
                    <td>${action}</td>
                </tr>`;
            });
            $body.html(html);
        });
    }

    /* ====================================================================
       ORDERS & ANALYTICS
       ==================================================================== */
    let ordersChart = null;
    let latestOrderId = 0;
    let orderPollTimer = null;

    function showNewOrderBanner(count) {
        const label = count === 1 ? '🛒 New order received!' : `🛒 ${count} new orders received!`;

        // Toast — immediate, impossible to miss.
        ERP.toast(label, 'success');

        // Banner above the table.
        $('#jesp-new-order-banner').remove();
        const $banner = $(
            '<div id="jesp-new-order-banner">' +
            '<span class="dashicons dashicons-bell"></span> ' +
            '<strong>' + label + '</strong> &mdash; <a href="#" id="jesp-refresh-orders">Refresh now</a>' +
            '<button id="jesp-banner-dismiss">&times;</button>' +
            '</div>'
        );
        const $table = $('#erp-all-orders-table');
        if ($table.length) {
            $table.closest('.jesp-table-responsive').before($banner);
        }

        // Red count badge on the tab.
        const $tab = $('#erp-orders-tabs .jesp-tab-btn[data-tab="all-orders"]');
        $tab.find('.jesp-order-badge').remove();
        $tab.append('<span class="jesp-order-badge">' + count + '</span>');
    }

    function startOrderPolling() {
        if (orderPollTimer) return;

        function poll(sinceId) {
            $.post(ERP.ajaxUrl, {
                action: 'erp_check_new_orders',
                nonce: ERP.nonce,
                since_id: sinceId
            }, function (res) {
                if (!res || !res.success) return;
                if (res.data.latest_id) latestOrderId = res.data.latest_id;
                if (res.data.new_count > 0) showNewOrderBanner(res.data.new_count);
            }, 'json');
        }

        // Seed latestOrderId immediately, then poll every 10 seconds.
        poll(0);
        orderPollTimer = setInterval(function () {
            if (latestOrderId) poll(latestOrderId);
        }, 10000);
    }

    function initOrders() {
        const todayStr = new Date().toISOString().slice(0, 10);
        $('#erp-orders-from').val(todayStr);
        $('#erp-orders-to').val(todayStr);

        // Tab switching.
        $('#erp-orders-tabs').on('click', '.jesp-tab-btn', function () {
            const tab = $(this).data('tab');
            $('#erp-orders-tabs .jesp-tab-btn').removeClass('active');
            $(this).addClass('active');
            $('#erp-orders-tabs .jesp-tab-panel').removeClass('active');
            $(`#erp-orders-tabs .jesp-tab-panel[data-panel="${tab}"]`).addClass('active');

            if (tab === 'all-orders') {
                loadAllOrders();
            } else if (tab === 'product-performance') {
                loadOrders();
                loadOrderChart();
            }
        });

        // Load default tab (All Orders).
        loadAllOrders();

        // Quick range buttons.
        $(document).on('click', '.jesp-range-btn', function () {
            const range = $(this).data('range');
            const todayStr = new Date().toISOString().slice(0, 10);
            const yesterdayStr = new Date(Date.now() - 86400000).toISOString().slice(0, 10);

            $('.jesp-range-btn').removeClass('active');
            $(this).addClass('active');

            if (range === 'today') {
                $('#erp-orders-from').val(todayStr);
                $('#erp-orders-to').val(todayStr);
            } else if (range === 'yesterday') {
                $('#erp-orders-from').val(yesterdayStr);
                $('#erp-orders-to').val(yesterdayStr);
            } else {
                // Custom — user picks dates manually via From/To inputs, then hits Apply.
                return;
            }

            // Refresh the active tab immediately for Today/Yesterday.
            const activeTab = $('#erp-orders-tabs .jesp-tab-btn.active').data('tab');
            if (activeTab === 'all-orders') {
                loadAllOrders();
            } else {
                loadOrders();
                loadOrderChart();
            }
        });

        // Apply button.
        $('#erp-orders-filter').on('click', function () {
            const activeTab = $('#erp-orders-tabs .jesp-tab-btn.active').data('tab');
            if (activeTab === 'all-orders') {
                loadAllOrders();
            } else {
                loadOrders();
                loadOrderChart();
            }
        });

        // All Orders: status / per-page filter.
        $('#erp-ao-status, #erp-ao-per-page').on('change', function () { loadAllOrders(); });

        // All Orders: search (debounced).
        let aoSearchTimer;
        $('#erp-ao-search').on('input', function () {
            clearTimeout(aoSearchTimer);
            aoSearchTimer = setTimeout(() => loadAllOrders(), 400);
        });

        // New order banner: refresh and dismiss.
        $(document).on('click', '#jesp-refresh-orders', function (e) {
            e.preventDefault();
            $('#jesp-new-order-banner').remove();
            $('#erp-orders-tabs .jesp-tab-btn[data-tab="all-orders"] .jesp-order-badge').remove();
            loadAllOrders();
        });
        $(document).on('click', '#jesp-banner-dismiss', function () {
            $('#jesp-new-order-banner').remove();
        });

        startOrderPolling();

        // Order Detail modal (delegated).
        $(document).on('click', '.jesp-order-detail-btn', function () {
            const oid = $(this).data('order-id');
            const $modal = $('#erp-order-detail-modal');
            $modal.show();
            $('#od-order-id').text('#' + oid);
            $('#od-body').html('<p class="jesp-loading">' + (ERP.strings.loading || 'Loading...') + '</p>');

            ERP.ajax('erp_get_order_detail', { order_id: oid }).done(function (res) {
                if (!res.success) {
                    $('#od-body').html('<p style="color:#ef4444;">' + (res.data?.message || 'Error') + '</p>');
                    return;
                }
                const o = res.data;
                let itemsHtml = '';
                (o.items || []).forEach(i => {
                    itemsHtml += `<tr>
                        <td>${ERP.esc(i.name)}</td>
                        <td style="color:#64748b;">${ERP.esc(i.sku || '\u2014')}</td>
                        <td>${i.qty}</td>
                        <td>${ERP.formatMoney(i.subtotal)}</td>
                        <td><strong>${ERP.formatMoney(i.total)}</strong></td>
                    </tr>`;
                });

                const html = `
                    <div class="jesp-od-section">
                        <h4><span class="dashicons dashicons-admin-users" style="color:#6366f1;"></span> Customer</h4>
                        <div class="jesp-od-grid">
                            <div><span class="jesp-od-label">Name</span><span>${ERP.esc(o.customer.name)}</span></div>
                            <div><span class="jesp-od-label">Email</span><span>${ERP.esc(o.customer.email || '\u2014')}</span></div>
                            <div><span class="jesp-od-label">Phone</span><span>${ERP.esc(o.customer.phone || '\u2014')}</span></div>
                            <div><span class="jesp-od-label">Address</span><span>${ERP.esc(o.customer.address || '\u2014')}</span></div>
                        </div>
                    </div>
                    <div class="jesp-od-section">
                        <h4><span class="dashicons dashicons-products" style="color:#6366f1;"></span> Order Items</h4>
                        <table class="jesp-table jesp-table-sm">
                            <thead><tr><th>Product</th><th>SKU</th><th>Qty</th><th>Subtotal</th><th>Total</th></tr></thead>
                            <tbody>${itemsHtml}</tbody>
                        </table>
                    </div>
                    <div class="jesp-od-section">
                        <h4><span class="dashicons dashicons-money-alt" style="color:#6366f1;"></span> Totals</h4>
                        <div class="jesp-od-grid">
                            <div><span class="jesp-od-label">Subtotal</span><span>${ERP.formatMoney(o.subtotal)}</span></div>
                            <div><span class="jesp-od-label">Tax</span><span>${ERP.formatMoney(o.tax_total)}</span></div>
                            <div><span class="jesp-od-label">Shipping</span><span>${ERP.formatMoney(o.shipping)}</span></div>
                            <div><span class="jesp-od-label">Discount</span><span>-${ERP.formatMoney(o.discount)}</span></div>
                            <div><span class="jesp-od-label"><strong>Total</strong></span><span><strong>${ERP.formatMoney(o.total)}</strong></span></div>
                        </div>
                    </div>
                    <div class="jesp-od-section">
                        <div class="jesp-od-grid">
                            <div><span class="jesp-od-label">Status</span><span class="jesp-badge jesp-badge-blue">${ERP.esc(o.status_label)}</span></div>
                            <div><span class="jesp-od-label">Date</span><span>${ERP.formatDate(o.order_date)}</span></div>
                            <div><span class="jesp-od-label">Payment</span><span class="jesp-badge jesp-badge-purple">${ERP.esc(o.payment || '\u2014')}</span></div>
                        </div>
                    </div>`;

                $('#od-body').html(html);
            });
        });

        // Modal close.
        $(document).on('click', '#erp-order-detail-modal .jesp-modal-close, #erp-order-detail-modal .jesp-modal-overlay', function () {
            $('#erp-order-detail-modal').hide();
        });

        // Export CSV — use a hidden form POST so browser triggers download directly.
        $(document).on('click', '#erp-ao-export', function () {
            const $btn = $(this);
            $btn.prop('disabled', true).text('Exporting...');

            const $form = $('<form>', {
                method: 'POST',
                action: ERP.ajaxUrl,
                target: '_blank',
            });

            const fields = {
                action: 'erp_export_orders',
                nonce: ERP.nonce,
                date_from: $('#erp-orders-from').val() || '',
                date_to: $('#erp-orders-to').val() || '',
                status: $('#erp-ao-status').val() || 'all',
                search: $('#erp-ao-search').val() || '',
            };

            Object.keys(fields).forEach(key => {
                $form.append($('<input>', { type: 'hidden', name: key, value: fields[key] }));
            });

            $('body').append($form);
            $form.submit();
            $form.remove();

            setTimeout(() => $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="vertical-align:middle;margin-right:4px;font-size:16px;width:16px;height:16px;"></span> Export HTML'), 2000);
        });

        // Export PDF — opens a print-ready page in a new tab; browser print dialog appears automatically.
        $(document).on('click', '#erp-ao-export-pdf', function () {
            const $btn = $(this);
            $btn.prop('disabled', true).text('Preparing...');

            const $form = $('<form>', {
                method: 'POST',
                action: ERP.ajaxUrl,
                target: '_blank',
            });

            const fields = {
                action: 'erp_export_orders_pdf',
                nonce: ERP.nonce,
                date_from: $('#erp-orders-from').val() || '',
                date_to: $('#erp-orders-to').val() || '',
                status: $('#erp-ao-status').val() || 'all',
                search: $('#erp-ao-search').val() || '',
            };

            Object.keys(fields).forEach(key => {
                $form.append($('<input>', { type: 'hidden', name: key, value: fields[key] }));
            });

            $('body').append($form);
            $form.submit();
            $form.remove();

            setTimeout(() => $btn.prop('disabled', false).html('<span class="dashicons dashicons-pdf" style="vertical-align:middle;margin-right:4px;font-size:16px;width:16px;height:16px;"></span> Export PDF'), 2000);
        });

        // Export CSV — SKU, Product Name, Order Number, Quantity (no image).
        $(document).on('click', '#erp-ao-export-csv', function () {
            const $btn = $(this);
            $btn.prop('disabled', true).text('Exporting...');

            const $form = $('<form>', {
                method: 'POST',
                action: ERP.ajaxUrl,
                target: '_blank',
            });

            const fields = {
                action: 'erp_export_orders_csv',
                nonce: ERP.nonce,
                date_from: $('#erp-orders-from').val() || '',
                date_to: $('#erp-orders-to').val() || '',
                status: $('#erp-ao-status').val() || 'all',
                search: $('#erp-ao-search').val() || '',
            };

            Object.keys(fields).forEach(key => {
                $form.append($('<input>', { type: 'hidden', name: key, value: fields[key] }));
            });

            $('body').append($form);
            $form.submit();
            $form.remove();

            setTimeout(() => $btn.prop('disabled', false).html('<span class="dashicons dashicons-spreadsheet" style="vertical-align:middle;margin-right:4px;font-size:16px;width:16px;height:16px;"></span> Export CSV'), 2000);
        });
    }

    function loadOrders(page = 1) {
        const $body = $('#erp-orders-body');
        $body.html('<tr><td colspan="5" class="jesp-loading">' + (ERP.strings.loading || 'Loading...') + '</td></tr>');

        ERP.ajax('erp_get_orders', {
            date_from: $('#erp-orders-from').val(),
            date_to: $('#erp-orders-to').val(),
            per_page: 20,
            page: page,
        }).done(function (res) {
            if (!res.success || !res.data.items.length) {
                $body.html('<tr><td colspan="5" class="jesp-loading">' + (ERP.strings.no_results || 'No results.') + '</td></tr>');
                $('#orders-total-count, #orders-total-revenue, #orders-unique-products').text('0');
                return;
            }

            const items = res.data.items;
            let totalOrders = 0, totalRev = 0;
            let html = '';

            items.forEach(item => {
                totalOrders += parseInt(item.order_count);
                totalRev += parseFloat(item.total_revenue);
                // Collect order IDs if available for detail button
                const orderIds = item.order_ids ? item.order_ids.split(',') : [];
                const detailBtn = orderIds.length
                    ? `<button class="jesp-action-btn-sm jesp-order-detail-btn" data-order-id="${orderIds[0]}" title="View Details"><span class="dashicons dashicons-visibility"></span></button>`
                    : '—';
                html += `<tr>
                    <td><strong>${item.product_name || 'Product #' + item.product_id}</strong></td>
                    <td>${item.order_count}</td>
                    <td>${item.total_qty_sold}</td>
                    <td>${ERP.formatMoney(item.total_revenue)}</td>
                    <td>${detailBtn}</td>
                </tr>`;
            });

            $body.html(html);
            $('#orders-total-count').text(totalOrders);
            $('#orders-total-revenue').text(ERP.formatMoney(totalRev));
            $('#orders-unique-products').text(items.length);

            ERP.buildPagination('#erp-orders-pagination', page, res.data.pages, (p) => loadOrders(p));
        });
    }

    function loadOrderChart() {
        ERP.ajax('erp_get_order_chart', {
            date_from: $('#erp-orders-from').val(),
            date_to: $('#erp-orders-to').val(),
        }).done(function (res) {
            if (!res.success) return;
            const data = res.data || [];
            const ctx = document.getElementById('erp-orders-revenue-chart');
            if (!ctx) return;

            if (ordersChart) ordersChart.destroy();

            ordersChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.order_date),
                    datasets: [{
                        type: 'bar',
                        label: 'Revenue',
                        data: data.map(d => parseFloat(d.revenue)),
                        backgroundColor: 'rgba(99,102,241,.6)',
                        borderRadius: 6,
                        borderSkipped: false,
                    }, {
                        type: 'line',
                        label: 'Orders',
                        data: data.map(d => parseInt(d.order_count)),
                        borderColor: '#f97316',
                        backgroundColor: 'rgba(249,115,22,.1)',
                        tension: 0.4,
                        borderWidth: 2.5,
                        pointRadius: 3,
                        yAxisID: 'y1',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: { legend: { position: 'bottom' } },
                    scales: {
                        y: { beginAtZero: true, title: { display: true, text: 'Revenue' } },
                        y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Orders' } },
                    }
                }
            });
        });
    }

    /* ---- All Orders (individual WC orders) ---- */
    function loadAllOrders(page = 1) {
        const $body = $('#erp-all-orders-body');
        $body.html('<tr><td colspan="8" class="jesp-loading">' + (ERP.strings.loading || 'Loading...') + '</td></tr>');

        const statusBadgeMap = {
            'completed': 'jesp-badge-green',
            'processing': 'jesp-badge-blue',
            'on-hold': 'jesp-badge-orange',
            'pending': 'jesp-badge-orange',
            'cancelled': 'jesp-badge-gray',
            'refunded': 'jesp-badge-purple',
            'failed': 'jesp-badge-red',
        };

        ERP.ajax('erp_get_all_orders', {
            date_from: $('#erp-orders-from').val(),
            date_to: $('#erp-orders-to').val(),
            search: $('#erp-ao-search').val() || '',
            status: $('#erp-ao-status').val() || 'all',
            per_page: parseInt($('#erp-ao-per-page').val()) || 20,
            page: page,
        }).done(function (res) {
            if (!res.success || !res.data.items.length) {
                $body.html('<tr><td colspan="8" class="jesp-loading">' + (ERP.strings.no_results || 'No orders found.') + '</td></tr>');
                return;
            }


            let html = '';
            res.data.items.forEach(o => {
                const badgeCls = statusBadgeMap[o.status] || 'jesp-badge-gray';
                html += `<tr>
                    <td><strong>#${ERP.esc(o.order_number)}</strong></td>
                    <td>${ERP.formatDate(o.order_date)}</td>
                    <td>
                        <strong>${ERP.esc(o.customer_name)}</strong>
                        <br><small style="color:#64748b;">${ERP.esc(o.customer_email || '')}</small>
                    </td>
                    <td>
                        <span title="${ERP.esc(o.items_summary)}">${o.items_count} item${o.items_count !== 1 ? 's' : ''}</span>
                        <br><small style="color:#64748b;">${ERP.esc(o.items_summary)}</small>
                    </td>
                    <td><strong>${ERP.formatMoney(o.total)}</strong></td>
                    <td><span class="jesp-badge jesp-badge-purple">${ERP.esc(o.payment_method || '\u2014')}</span></td>
                    <td><span class="jesp-badge ${badgeCls}">${ERP.esc(o.status_label)}</span></td>
                    <td><button class="jesp-action-btn-sm jesp-order-detail-btn" data-order-id="${o.order_id}" title="View Details"><span class="dashicons dashicons-visibility"></span></button></td>
                </tr>`;
            });
            $body.html(html);

            ERP.buildPagination('#erp-all-orders-pagination', page, res.data.pages, (p) => loadAllOrders(p));
        });
    }

    function loadBrandRevenue() {
        const $body = $('#erp-brand-body');
        $body.html('<tr><td colspan="5" class="jesp-loading">' + (ERP.strings.loading || 'Loading...') + '</td></tr>');

        ERP.ajax('erp_get_brand_revenue', ERP.getDashRange()).done(function (res) {
            if (!res.success) {
                $body.html('<tr><td colspan="5" class="jesp-loading">Error loading data.</td></tr>');
                return;
            }

            const items = res.data.items || [];
            const total = res.data.total_revenue || 0;

            // Update summary cards.
            $('#br-total-revenue').text(ERP.formatMoney(total));
            const branded = items.filter(i => i.brand !== 'Unbranded');
            $('#br-brand-count').text(branded.length);
            $('#br-top-brand').text(branded.length ? branded[0].brand : '—');

            if (!items.length) {
                $body.html('<tr><td colspan="5" class="jesp-loading">No data for this period.</td></tr>');
                return;
            }

            const maxRev = Math.max(...items.map(i => i.revenue));
            let html = '';
            items.forEach(item => {
                const pct = total > 0 ? ((item.revenue / total) * 100).toFixed(1) : '0.0';
                const barPct = maxRev > 0 ? ((item.revenue / maxRev) * 100).toFixed(1) : '0';
                const isUnbranded = item.brand === 'Unbranded';
                const barColor = isUnbranded ? '#94a3b8' : '#3b82f6';
                const nameBadge = isUnbranded
                    ? `<span style="color:#64748b;font-style:italic;">${ERP.esc(item.brand)}</span>`
                    : `<strong>${ERP.esc(item.brand)}</strong>`;

                html += `<tr>
                    <td>${nameBadge}</td>
                    <td style="text-align:center;">${item.order_count}</td>
                    <td><strong>${ERP.formatMoney(item.revenue)}</strong></td>
                    <td style="text-align:center;">${pct}%</td>
                    <td style="min-width:140px;">
                        <div style="background:#e2e8f0;border-radius:4px;height:10px;overflow:hidden;">
                            <div style="width:${barPct}%;height:100%;background:${barColor};border-radius:4px;transition:width .4s;"></div>
                        </div>
                    </td>
                </tr>`;
            });
            $body.html(html);
        });
    }

    /* ====================================================================
       CUSTOMERS (standalone page — unchanged)
       ==================================================================== */
    let customerDebounce;

    function initCustomers() {
        loadCustomers();

        $('#erp-customer-search').on('input', function () {
            clearTimeout(customerDebounce);
            customerDebounce = setTimeout(() => loadCustomers(), 350);
        });

        $('#erp-customer-filter').on('click', function () { loadCustomers(); });

        $('#erp-sync-customers').on('click', function () {
            const $btn = $(this);
            const $status = $('#erp-sync-customers-status');
            $btn.prop('disabled', true).find('.dashicons').addClass('dashicons-update-spin');
            $status.text('Syncing...');
            ERP.ajax('erp_sync_customers').done(function (res) {
                if (res.success) {
                    $status.text(res.data.message);
                    loadCustomers();
                } else {
                    $status.text(res.data?.message || 'Sync failed.');
                }
            }).fail(function () {
                $status.text('Sync failed.');
            }).always(function () {
                $btn.prop('disabled', false).find('.dashicons').removeClass('dashicons-update-spin');
            });
        });

        $(document).on('click', '.jesp-view-customer', function () {
            const id = $(this).data('id');
            loadCustomerDetail(id);
        });

        $('#erp-back-to-customers').on('click', function () {
            $('#erp-customer-detail-view').hide();
            $('#erp-customers-list-view').show();
        });
    }

    function loadCustomers(page = 1) {
        const $body = $('#erp-customers-body');
        $body.html('<tr><td colspan="8" class="jesp-loading">' + (ERP.strings.loading || 'Loading...') + '</td></tr>');

        ERP.ajax('erp_get_customers', {
            per_page: 20,
            page: page,
            search: $('#erp-customer-search').val() || '',
            min_spent: $('#erp-customer-min-spent').val() || 0,
        }).done(function (res) {
            if (!res.success || !res.data.items.length) {
                $body.html('<tr><td colspan="8" class="jesp-loading">' + (ERP.strings.no_results || 'No results.') + '</td></tr>');
                return;
            }

            let html = '';
            res.data.items.forEach(c => {
                const aov = c.aov ? ERP.formatMoney(c.aov) : '—';
                html += `<tr>
                    <td><strong>${ERP.esc(c.customer_name)}</strong></td>
                    <td>${ERP.esc(c.email || '—')}</td>
                    <td>${ERP.esc(c.phone || '—')}</td>
                    <td>${c.order_count}</td>
                    <td>${ERP.formatMoney(c.total_spent)}</td>
                    <td>${aov}</td>
                    <td>${ERP.formatDate(c.last_order_date)}</td>
                    <td><button class="jesp-action-btn-sm jesp-view-customer" data-id="${c.id}" title="View"><span class="dashicons dashicons-visibility"></span></button></td>
                </tr>`;
            });
            $body.html(html);

            ERP.buildPagination('#erp-customers-pagination', page, res.data.pages, (p) => loadCustomers(p));
        });
    }

    function loadCustomerDetail(customerId, page = 1) {
        ERP.ajax('erp_get_customer_orders', { customer_id: customerId, per_page: 20, page: page }).done(function (res) {
            if (!res.success) { ERP.toast(res.data?.message || 'Error', 'error'); return; }

            const c = res.data.customer;
            const orders = res.data.orders;

            $('#profile-name').text(c.customer_name);
            $('#profile-email').text(c.email || '—');
            $('#profile-phone').text(c.phone || '—');
            $('#profile-address').text(c.address || '—');
            $('#profile-total-spent').text(ERP.formatMoney(c.total_spent));
            $('#profile-order-count').text(c.order_count);
            $('#profile-aov').text(c.aov ? ERP.formatMoney(c.aov) : '—');

            const $orderBody = $('#erp-customer-orders-body');
            if (!orders.items.length) {
                $orderBody.html('<tr><td colspan="5" class="jesp-loading">No orders found.</td></tr>');
            } else {
                let html = '';
                orders.items.forEach(o => {
                    const itemsList = (o.items || []).map(i => `${i.name} × ${i.qty}`).join(', ') || '—';
                    html += `<tr>
                        <td>#${o.order_number || o.order_id}</td>
                        <td>${ERP.formatDate(o.order_date)}</td>
                        <td>${itemsList}</td>
                        <td><span class="jesp-badge jesp-badge-blue">${o.status_label || o.status}</span></td>
                        <td>${ERP.formatMoney(o.order_total)}</td>
                    </tr>`;
                });
                $orderBody.html(html);
            }

            ERP.buildPagination('#erp-customer-orders-pagination', page, orders.pages, (p) => loadCustomerDetail(customerId, p));

            $('#erp-customers-list-view').hide();
            $('#erp-customer-detail-view').show();
        });
    }

    /* ====================================================================
       SETTINGS PAGE — Custom CSS editor
       ==================================================================== */
    function initSettings() {
        let cmEditor = null;

        // Upgrade the textarea to CodeMirror if WordPress code editor is available.
        if (
            typeof wp !== 'undefined' &&
            wp.codeEditor &&
            typeof jespErpCodeEditor !== 'undefined' &&
            jespErpCodeEditor !== false
        ) {
            const editorConfig = $.extend(true, {}, jespErpCodeEditor, {
                codemirror: {
                    mode: 'css',
                    lineNumbers: true,
                    lineWrapping: true,
                    indentUnit: 2,
                    tabSize: 2,
                    theme: 'default',
                }
            });
            const instance = wp.codeEditor.initialize($('#jesp-custom-css-editor'), editorConfig);
            cmEditor = instance.codemirror;
        }

        function getCss() {
            return cmEditor ? cmEditor.getValue() : $('#jesp-custom-css-editor').val();
        }

        // Save button — saves CSS + tab visibility, then reloads to apply menu changes.
        $('#jesp-settings-save').on('click', function () {
            const $btn = $(this).prop('disabled', true).text('Saving...');

            const hiddenTabs = [];
            $('.jesp-tab-toggle').each(function () {
                if (!$(this).is(':checked')) hiddenTabs.push($(this).val());
            });

            ERP.ajax('erp_save_settings', { custom_css: getCss(), hidden_tabs: hiddenTabs })
                .done(function (res) {
                    if (res.success) {
                        ERP.toast(res.data.message || 'Saved!');
                        setTimeout(() => window.location.reload(), 900);
                    } else {
                        ERP.toast(res.data?.message || ERP.strings.error || 'Error', 'error');
                        $btn.prop('disabled', false).text('Save Settings');
                    }
                })
                .fail(function () {
                    ERP.toast('Network error.', 'error');
                    $btn.prop('disabled', false).text('Save Settings');
                });
        });

        // Clear button.
        $('#jesp-settings-clear').on('click', function () {
            if (!confirm('Clear all custom CSS?')) return;
            if (cmEditor) {
                cmEditor.setValue('');
            } else {
                $('#jesp-custom-css-editor').val('');
            }
        });
    }

    /* ====================================================================
       INVOICE MAKER
       ==================================================================== */
    function initInvoiceMaker() {
        let invPage = 1;

        loadInvoiceList();

        $('#inv-new-btn').on('click', function () { openEditor(null); });
        $('#inv-filter-btn').on('click', function () { invPage = 1; loadInvoiceList(); });
        $('#inv-search').on('keydown', function (e) { if (e.key === 'Enter') { invPage = 1; loadInvoiceList(); } });
        $('#inv-back-btn').on('click', function () { showListView(); });

        $('#inv-add-row').on('click', function () { addItemRow(); recalcTotals(); });

        $(document).on('input', '.inv-qty, .inv-price', function () { recalcRow($(this).closest('tr')); recalcTotals(); });
        $(document).on('click', '.inv-del-row', function () { $(this).closest('tr').remove(); recalcTotals(); });

        $('#inv-discount-type, #inv-discount-value, #inv-tax-rate').on('input change', recalcTotals);

        $('#inv-save-btn').on('click', saveInvoice);
        $('#inv-print-btn').on('click', printInvoice);

        $(document).on('click', '.inv-edit-btn', function () {
            openEditor($(this).data('id'));
        });

        $(document).on('click', '.inv-delete-btn', function () {
            if (!confirm('Delete this invoice?')) return;
            ERP.ajax('erp_delete_invoice', { id: $(this).data('id') }).done(function (res) {
                if (res.success) { ERP.toast('Invoice deleted.'); loadInvoiceList(); }
                else ERP.toast(res.data?.message || 'Error', 'error');
            });
        });

        $(document).on('click', '.inv-print-list-btn', function () {
            const id = $(this).data('id');
            const url = ERP.ajaxUrl + '?action=erp_print_invoice&nonce=' + ERP.nonce + '&id=' + id;
            window.open(url, '_blank');
        });

        function showListView() {
            $('#jesp-inv-editor-view').hide();
            $('#jesp-inv-list-view').show();
            loadInvoiceList();
        }

        function showEditorView() {
            $('#jesp-inv-list-view').hide();
            $('#jesp-inv-editor-view').show();
        }

        function loadInvoiceList(page) {
            page = page || invPage;
            const $body = $('#inv-list-body');
            $body.html('<tr><td colspan="6" class="jesp-loading">Loading…</td></tr>');

            ERP.ajax('erp_get_invoices', {
                per_page: 20,
                page: page,
                search: $('#inv-search').val() || '',
                status: $('#inv-status-filter').val() || '',
            }).done(function (res) {
                if (!res.success) { $body.html('<tr><td colspan="6" class="jesp-loading">Error loading invoices.</td></tr>'); return; }
                const { items, pages } = res.data;
                if (!items.length) { $body.html('<tr><td colspan="6" class="jesp-loading">No invoices found.</td></tr>'); return; }

                let html = '';
                items.forEach(inv => {
                    const statusBadge = inv.status === 'paid'
                        ? '<span class="jesp-badge jesp-badge-green">Paid</span>'
                        : '<span class="jesp-badge jesp-badge-gray">Draft</span>';
                    html += `<tr>
                        <td><strong>${ERP.esc(inv.invoice_number)}</strong></td>
                        <td>${ERP.esc(inv.customer_name)}</td>
                        <td>${ERP.formatDate(inv.invoice_date)}</td>
                        <td><strong>${ERP.formatMoney(inv.total)}</strong></td>
                        <td>${statusBadge}</td>
                        <td style="white-space:nowrap;">
                            <button class="jesp-action-btn-sm inv-edit-btn" data-id="${inv.id}" title="Edit"><span class="dashicons dashicons-edit"></span></button>
                            <button class="jesp-action-btn-sm inv-print-list-btn" data-id="${inv.id}" title="Print"><span class="dashicons dashicons-printer"></span></button>
                            <button class="jesp-action-btn-sm inv-delete-btn" style="color:#dc2626;" data-id="${inv.id}" title="Delete"><span class="dashicons dashicons-trash"></span></button>
                        </td>
                    </tr>`;
                });
                $body.html(html);
                ERP.buildPagination('#inv-list-pagination', page, pages, function (p) { invPage = p; loadInvoiceList(p); });
            });
        }

        function openEditor(id) {
            resetEditor();
            if (id) {
                ERP.ajax('erp_get_invoice', { id: id }).done(function (res) {
                    if (!res.success) { ERP.toast('Could not load invoice.', 'error'); return; }
                    populateEditor(res.data);
                    showEditorView();
                });
            } else {
                addItemRow();
                recalcTotals();
                showEditorView();
            }
        }

        function resetEditor() {
            $('#inv-id').val(0);
            $('#inv-number').val('');
            $('#inv-date').val(new Date().toISOString().slice(0, 10));
            $('#inv-status').val('draft');
            $('#inv-customer-name, #inv-customer-phone, #inv-customer-email, #inv-customer-address, #inv-notes, #inv-salesperson').val('');
            $('#inv-discount-type').val('none');
            $('#inv-discount-value').val(0);
            $('#inv-tax-rate').val(0);
            $('#inv-items-body').empty();
            $('#inv-display-subtotal, #inv-display-total').text('—');
        }

        function populateEditor(inv) {
            $('#inv-id').val(inv.id);
            $('#inv-number').val(inv.invoice_number);
            $('#inv-date').val(inv.invoice_date);
            $('#inv-status').val(inv.status);
            $('#inv-customer-name').val(inv.customer_name);
            $('#inv-customer-phone').val(inv.customer_phone);
            $('#inv-customer-email').val(inv.customer_email);
            $('#inv-customer-address').val(inv.customer_address);
            $('#inv-notes').val(inv.notes);
            $('#inv-salesperson').val(inv.salesperson_name || '');
            $('#inv-discount-type').val(inv.discount_type);
            $('#inv-discount-value').val(inv.discount_value);
            $('#inv-tax-rate').val(inv.tax_rate);

            $('#inv-items-body').empty();
            (inv.items || []).forEach(item => addItemRow(item));
            recalcTotals();
        }

        function addItemRow(item) {
            item = item || {};
            const row = `<tr>
                <td><input type="text" class="inv-name" value="${ERP.esc(item.product_name || '')}" placeholder="Product / Description"></td>
                <td><input type="text" class="inv-sku" value="${ERP.esc(item.sku || '')}" placeholder="SKU"></td>
                <td><input type="number" class="inv-qty" value="${parseFloat(item.qty || 1)}" min="0" step="0.01"></td>
                <td><input type="number" class="inv-price" value="${parseFloat(item.unit_price || 0)}" min="0" step="0.01"></td>
                <td class="r inv-line-total" style="text-align:right;font-weight:600;">${ERP.formatMoney((item.qty || 1) * (item.unit_price || 0))}</td>
                <td style="text-align:center;"><button class="inv-del-row" title="Remove row"><span class="dashicons dashicons-trash"></span></button></td>
            </tr>`;
            $('#inv-items-body').append(row);
        }

        function recalcRow($row) {
            const qty = parseFloat($row.find('.inv-qty').val()) || 0;
            const price = parseFloat($row.find('.inv-price').val()) || 0;
            $row.find('.inv-line-total').text(ERP.formatMoney(qty * price));
        }

        function recalcTotals() {
            let subtotal = 0;
            $('#inv-items-body tr').each(function () {
                const qty = parseFloat($(this).find('.inv-qty').val()) || 0;
                const price = parseFloat($(this).find('.inv-price').val()) || 0;
                subtotal += qty * price;
            });

            const discType = $('#inv-discount-type').val();
            const discVal = parseFloat($('#inv-discount-value').val()) || 0;
            const taxRate = parseFloat($('#inv-tax-rate').val()) || 0;

            let discAmt = 0;
            if (discType === 'percentage') discAmt = subtotal * (discVal / 100);
            else if (discType === 'fixed') discAmt = Math.min(discVal, subtotal);

            const afterDisc = subtotal - discAmt;
            const taxAmt = afterDisc * (taxRate / 100);
            const total = afterDisc + taxAmt;

            $('#inv-display-subtotal').text(ERP.formatMoney(subtotal));
            $('#inv-display-total').text(ERP.formatMoney(total));
        }

        function collectItems() {
            const items = [];
            $('#inv-items-body tr').each(function () {
                const name = $(this).find('.inv-name').val().trim();
                if (!name) return;
                items.push({
                    product_name: name,
                    sku: $(this).find('.inv-sku').val().trim(),
                    qty: parseFloat($(this).find('.inv-qty').val()) || 1,
                    unit_price: parseFloat($(this).find('.inv-price').val()) || 0,
                });
            });
            return items;
        }

        function getSubtotal() {
            let s = 0;
            $('#inv-items-body tr').each(function () {
                s += (parseFloat($(this).find('.inv-qty').val()) || 0) * (parseFloat($(this).find('.inv-price').val()) || 0);
            });
            return s;
        }

        function getTotal(subtotal) {
            const discType = $('#inv-discount-type').val();
            const discVal = parseFloat($('#inv-discount-value').val()) || 0;
            const taxRate = parseFloat($('#inv-tax-rate').val()) || 0;
            let discAmt = 0;
            if (discType === 'percentage') discAmt = subtotal * (discVal / 100);
            else if (discType === 'fixed') discAmt = Math.min(discVal, subtotal);
            const after = subtotal - discAmt;
            return after + after * (taxRate / 100);
        }

        function saveInvoice() {
            const name = $('#inv-customer-name').val().trim();
            if (!name) { ERP.toast('Customer name is required.', 'error'); return; }

            const items = collectItems();
            if (!items.length) { ERP.toast('Add at least one line item.', 'error'); return; }

            const sub = getSubtotal();
            const total = getTotal(sub);
            const $btn = $('#inv-save-btn').prop('disabled', true).text('Saving…');

            ERP.ajax('erp_save_invoice', {
                id: $('#inv-id').val(),
                invoice_number: $('#inv-number').val().trim(),
                customer_name: name,
                customer_phone: $('#inv-customer-phone').val().trim(),
                customer_email: $('#inv-customer-email').val().trim(),
                customer_address: $('#inv-customer-address').val().trim(),
                invoice_date: $('#inv-date').val(),
                subtotal: sub,
                discount_type: $('#inv-discount-type').val(),
                discount_value: $('#inv-discount-value').val(),
                tax_rate: $('#inv-tax-rate').val(),
                total: total,
                notes: $('#inv-notes').val().trim(),
                salesperson_name: $('#inv-salesperson').val().trim(),
                status: $('#inv-status').val(),
                items: JSON.stringify(items),
            }).done(function (res) {
                if (res.success) {
                    ERP.toast(res.data.message || 'Saved!');
                    if (!$('#inv-id').val() || $('#inv-id').val() === '0') {
                        $('#inv-id').val(res.data.id);
                    }
                } else {
                    ERP.toast(res.data?.message || 'Error', 'error');
                }
            }).fail(function () {
                ERP.toast('Network error.', 'error');
            }).always(function () {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved" style="margin-top:3px;"></span> Save Invoice');
            });
        }

        function printInvoice() {
            const id = $('#inv-id').val();
            if (!id || id === '0') {
                ERP.toast('Save the invoice first before printing.', 'error');
                return;
            }
            const url = ERP.ajaxUrl + '?action=erp_print_invoice&nonce=' + ERP.nonce + '&id=' + id;
            window.open(url, '_blank');
        }
    }

    /* ====================================================================
       FINANCE PAGE
       ==================================================================== */
    let finState = { dateFrom: '', dateTo: '' };
    let finRevenueChart = null;
    let finPaymentChart = null;

    function initFinancePage() {
        const to = new Date().toISOString().slice(0, 10);
        const from = new Date(Date.now() - 30 * 86400000).toISOString().slice(0, 10);
        $('#fin-date-from').val(from);
        $('#fin-date-to').val(to);
        finState.dateFrom = from;
        finState.dateTo = to;

        loadFinanceSummary();
        loadExpenses();

        // Quick range buttons.
        $(document).on('click', '.jesp-fin-range', function () {
            const days = parseInt($(this).data('days'));
            $('.jesp-fin-range').removeClass('active');
            $(this).addClass('active');

            if (days === 0) {
                $('.jesp-fin-custom-range').show();
                return;
            }
            $('.jesp-fin-custom-range').hide();
            const t = new Date().toISOString().slice(0, 10);
            const f = new Date(Date.now() - days * 86400000).toISOString().slice(0, 10);
            $('#fin-date-from').val(f);
            $('#fin-date-to').val(t);
            finState.dateFrom = f;
            finState.dateTo = t;
            loadFinanceSummary();
            loadExpenses();
        });

        // Custom date apply.
        $('#fin-apply-custom').on('click', function () {
            finState.dateFrom = $('#fin-date-from').val();
            finState.dateTo = $('#fin-date-to').val();
            loadFinanceSummary();
            loadExpenses();
        });

        // Add Expense button.
        $('#fin-add-expense').on('click', function () {
            $('#fin-expense-id').val(0);
            $('#fin-expense-title').val('');
            $('#fin-expense-amount').val('');
            $('#fin-expense-date').val(new Date().toISOString().slice(0, 10));
            $('#fin-expense-category').val('');
            $('#fin-expense-notes').val('');
            $('#fin-expense-modal-title').text('Add Expense');
            $('#fin-expense-modal').show();
        });

        // Modal close.
        $(document).on('click', '#fin-expense-modal .jesp-modal-close, #fin-expense-modal .jesp-modal-overlay', function () {
            $('#fin-expense-modal').hide();
        });

        // Save expense.
        $('#fin-save-expense').on('click', function () {
            const $btn = $(this).prop('disabled', true).text('Saving...');
            ERP.ajax('erp_save_expense', {
                id: $('#fin-expense-id').val(),
                title: $('#fin-expense-title').val(),
                amount: $('#fin-expense-amount').val(),
                category: $('#fin-expense-category').val(),
                expense_date: $('#fin-expense-date').val(),
                notes: $('#fin-expense-notes').val(),
            }).done(function (res) {
                if (res.success) {
                    ERP.toast(res.data.message);
                    $('#fin-expense-modal').hide();
                    loadExpenses();
                    loadFinanceSummary();
                } else {
                    ERP.toast(res.data?.message || 'Error', 'error');
                }
            }).fail(function () {
                ERP.toast('Network error.', 'error');
            }).always(function () {
                $btn.prop('disabled', false).text('Save Expense');
            });
        });

        // Delete expense.
        $(document).on('click', '.fin-delete-expense', function () {
            if (!confirm('Are you sure you want to delete this expense?')) return;
            const id = $(this).data('id');
            ERP.ajax('erp_delete_expense', { id: id }).done(function (res) {
                if (res.success) {
                    ERP.toast(res.data.message);
                    loadExpenses();
                    loadFinanceSummary();
                } else {
                    ERP.toast(res.data?.message || 'Error', 'error');
                }
            });
        });

        // Edit expense.
        $(document).on('click', '.fin-edit-expense', function () {
            const $row = $(this).closest('tr');
            $('#fin-expense-id').val($row.data('id'));
            $('#fin-expense-title').val($row.data('title'));
            $('#fin-expense-amount').val($row.data('amount'));
            $('#fin-expense-date').val($row.data('date'));
            $('#fin-expense-category').val($row.data('category'));
            $('#fin-expense-notes').val($row.data('notes'));
            $('#fin-expense-modal-title').text('Edit Expense');
            $('#fin-expense-modal').show();
        });
    }

    function loadFinanceSummary() {
        ERP.ajax('erp_get_finance_summary', {
            date_from: finState.dateFrom,
            date_to: finState.dateTo,
        }).done(function (res) {
            if (!res.success) return;
            const s = res.data.summary;
            const pm = res.data.payment_methods;
            const daily = res.data.daily_revenue;

            // Update summary cards.
            $('#fin-total-revenue').text(ERP.formatMoney(s.total_revenue));
            $('#fin-total-refunds').text(ERP.formatMoney(s.total_refunds));
            $('#fin-net-profit').text(ERP.formatMoney(s.net_profit));
            $('#fin-total-tax').text(ERP.formatMoney(s.total_tax));
            $('#fin-total-shipping').text(ERP.formatMoney(s.total_shipping));
            $('#fin-order-count').text(s.order_count);
            $('#fin-total-expenses').text(ERP.formatMoney(s.total_expenses));
            $('#fin-total-discount').text(ERP.formatMoney(s.total_discount));

            // Net profit color.
            $('#fin-net-profit').css('color', s.net_profit >= 0 ? '#16a34a' : '#dc2626');

            // Revenue chart.
            const ctx = document.getElementById('fin-revenue-chart');
            if (ctx) {
                if (finRevenueChart) finRevenueChart.destroy();
                finRevenueChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: daily.map(d => d.day),
                        datasets: [{
                            label: 'Revenue',
                            data: daily.map(d => d.revenue),
                            borderColor: '#16a34a',
                            backgroundColor: 'rgba(22,163,106,.08)',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 2.5,
                            pointRadius: 3,
                            pointBackgroundColor: '#16a34a',
                        }, {
                            label: 'Refunds',
                            data: daily.map(d => d.refunds),
                            borderColor: '#dc2626',
                            backgroundColor: 'rgba(220,38,38,.06)',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 2,
                            pointRadius: 2,
                            pointBackgroundColor: '#dc2626',
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { intersect: false, mode: 'index' },
                        plugins: { legend: { position: 'bottom' } },
                        scales: {
                            y: { beginAtZero: true },
                        }
                    }
                });
            }

            // Payment methods chart.
            const pmCtx = document.getElementById('fin-payment-chart');
            if (pmCtx && pm.length) {
                if (finPaymentChart) finPaymentChart.destroy();
                const pmColors = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#14b8a6', '#f97316', '#ec4899'];
                finPaymentChart = new Chart(pmCtx, {
                    type: 'doughnut',
                    data: {
                        labels: pm.map(m => m.label),
                        datasets: [{
                            data: pm.map(m => m.total),
                            backgroundColor: pmColors.slice(0, pm.length).map(c => c + 'CC'),
                            borderColor: pmColors.slice(0, pm.length),
                            borderWidth: 2,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'right', labels: { padding: 12, font: { size: 12 } } },
                        }
                    }
                });
            }

            // Payment methods table.
            const grandTotal = pm.reduce((sum, m) => sum + m.total, 0);
            const $pmBody = $('#fin-payment-body');
            if (!pm.length) {
                $pmBody.html('<tr><td colspan="4" class="jesp-loading">No payment data.</td></tr>');
            } else {
                let pmHtml = '';
                pm.forEach(m => {
                    const pct = grandTotal > 0 ? ((m.total / grandTotal) * 100).toFixed(1) : 0;
                    pmHtml += `<tr>
                        <td><strong>${ERP.esc(m.label)}</strong></td>
                        <td style="text-align:center;">${m.order_count}</td>
                        <td>${ERP.formatMoney(m.total)}</td>
                        <td>
                            <div style="display:flex;align-items:center;gap:6px;">
                                <div style="background:#e2e8f0;border-radius:4px;height:6px;flex:1;overflow:hidden;">
                                    <div style="width:${pct}%;height:100%;background:#6366f1;border-radius:4px;"></div>
                                </div>
                                <span style="font-size:12px;color:#64748b;min-width:40px;">${pct}%</span>
                            </div>
                        </td>
                    </tr>`;
                });
                $pmBody.html(pmHtml);
            }
        });
    }

    function loadExpenses(page) {
        page = page || 1;
        const $body = $('#fin-expenses-body');
        $body.html('<tr><td colspan="6" class="jesp-loading">Loading...</td></tr>');

        ERP.ajax('erp_get_expenses', {
            date_from: finState.dateFrom,
            date_to: finState.dateTo,
            per_page: 20,
            page: page,
        }).done(function (res) {
            if (!res.success) return;

            const items = res.data.items || [];
            const catTotals = res.data.cat_totals || [];

            if (!items.length) {
                $body.html('<tr><td colspan="6" class="jesp-loading">No expenses found. Click "Add Expense" to get started.</td></tr>');
            } else {
                let html = '';
                items.forEach(function (item) {
                    const catBadge = item.category
                        ? `<span class="jesp-badge jesp-badge-blue">${ERP.esc(item.category)}</span>`
                        : '<span style="color:#94a3b8;">—</span>';
                    html += `<tr data-id="${item.id}" data-title="${ERP.esc(item.title)}" data-amount="${item.amount}" data-date="${item.expense_date}" data-category="${ERP.esc(item.category)}" data-notes="${ERP.esc(item.notes || '')}">
                        <td>${ERP.formatDate(item.expense_date)}</td>
                        <td><strong>${ERP.esc(item.title)}</strong></td>
                        <td>${catBadge}</td>
                        <td><strong style="color:#dc2626;">${ERP.formatMoney(item.amount)}</strong></td>
                        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${ERP.esc(item.notes || '—')}</td>
                        <td>
                            <button class="jesp-action-btn-sm fin-edit-expense" title="Edit"><span class="dashicons dashicons-edit"></span></button>
                            <button class="jesp-action-btn-sm fin-delete-expense" data-id="${item.id}" title="Delete" style="color:#dc2626;"><span class="dashicons dashicons-trash"></span></button>
                        </td>
                    </tr>`;
                });
                $body.html(html);
            }

            ERP.buildPagination('#fin-expenses-pagination', page, res.data.pages, loadExpenses);

            // Expense categories summary.
            const $cats = $('#fin-expense-cats');
            if (!catTotals.length) {
                $cats.html('<p style="color:#94a3b8;padding:8px 0;">No expense categories for this period.</p>');
            } else {
                const maxCat = Math.max(...catTotals.map(c => parseFloat(c.total)));
                let catHtml = '';
                catTotals.forEach(c => {
                    const pct = maxCat > 0 ? ((parseFloat(c.total) / maxCat) * 100).toFixed(0) : 0;
                    const catName = c.category || 'Uncategorized';
                    catHtml += `<div style="margin-bottom:10px;">
                        <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                            <span style="font-size:13px;font-weight:600;color:#374151;">${ERP.esc(catName)}</span>
                            <span style="font-size:13px;color:#64748b;">${ERP.formatMoney(c.total)} (${c.cnt} items)</span>
                        </div>
                        <div style="background:#e2e8f0;border-radius:4px;height:8px;overflow:hidden;">
                            <div style="width:${pct}%;height:100%;background:#ef4444;border-radius:4px;transition:width .4s;"></div>
                        </div>
                    </div>`;
                });
                $cats.html(catHtml);
            }
        });
    }

})(jQuery);
