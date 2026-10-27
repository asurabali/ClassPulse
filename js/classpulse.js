/**
 * ClassPulse — Core JavaScript Utilities v2.0
 * Handles: Toast notifications, Modal management, AJAX helpers,
 *          Chart initialization, CSV/PDF export, Sidebar toggle
 */

/* ============================================================
   TOAST NOTIFICATION SYSTEM
   ============================================================ */
window.CP = window.CP || {};

CP.toast = (function() {
    let container = null;

    function getContainer() {
        if (!container) {
            container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                document.body.appendChild(container);
            }
        }
        return container;
    }

    const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };

    function show(msg, type = 'success', duration = 3500) {
        const wrap = getContainer();
        const t = document.createElement('div');
        t.className = `toast ${type}`;
        t.innerHTML = `<span class="toast-icon">${icons[type] || icons.info}</span><span>${msg}</span>`;
        wrap.appendChild(t);
        setTimeout(() => {
            t.style.opacity = '0';
            t.style.transform = 'translateX(40px)';
            t.style.transition = 'all 0.3s ease';
            setTimeout(() => t.remove(), 350);
        }, duration);
    }

    return { show };
})();

/* ============================================================
   MODAL SYSTEM
   ============================================================ */
CP.modal = (function() {
    let activeModal = null;

    function open(selector) {
        const el = typeof selector === 'string' ? document.querySelector(selector) : selector;
        if (!el) return;
        el.classList.add('active');
        activeModal = el;
        document.body.style.overflow = 'hidden';
    }

    function close(selector) {
        const el = selector
            ? (typeof selector === 'string' ? document.querySelector(selector) : selector)
            : activeModal;
        if (!el) return;
        el.classList.remove('active');
        document.body.style.overflow = '';
        activeModal = null;
    }

    function closeOnOverlay(e) {
        if (e.target.classList.contains('modal-overlay')) close(e.target);
    }

    // Bootstrap-compat shim for legacy code using uni_modal()
    function uni_modal(title, url, size = '') {
        const modal = document.getElementById('uni_modal');
        if (!modal) return;
        const titleEl = modal.querySelector('.modal-title');
        const bodyEl  = modal.querySelector('.modal-body');
        if (titleEl) titleEl.textContent = title;
        if (bodyEl) {
            bodyEl.innerHTML = `<div class="text-center p-4"><span class="spinner"></span></div>`;
            fetch(url)
                .then(r => r.text())
                .then(html => { bodyEl.innerHTML = html; })
                .catch(() => { bodyEl.innerHTML = '<p class="text-danger">Failed to load content.</p>'; });
        }
        if (size === 'large' || size === 'mid-large') {
            modal.querySelector('.modal-dialog')?.classList.add('modal-lg');
        }
        // Bootstrap modal show
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    }

    // Expose globally for legacy compatibility
    window.uni_modal = uni_modal;

    return { open, close, closeOnOverlay };
})();

/* ============================================================
   AJAX HELPER
   ============================================================ */
CP.ajax = function({ url, method = 'POST', data = {}, onSuccess, onError, btn = null }) {
    if (btn) {
        btn.disabled = true;
        btn._origHtml = btn.innerHTML;
        btn.innerHTML = `<span class="spinner"></span> Processing...`;
    }
    const formData = new FormData();
    if (typeof data === 'object' && !(data instanceof FormData)) {
        Object.entries(data).forEach(([k, v]) => formData.append(k, v));
    }
    const opts = {
        method,
        body: method !== 'GET' ? (data instanceof FormData ? data : formData) : undefined
    };
    fetch(url, opts)
        .then(r => r.json())
        .then(resp => {
            if (resp.status === 'success') {
                CP.toast.show(resp.msg || 'Done!', 'success');
                onSuccess && onSuccess(resp);
            } else {
                CP.toast.show(resp.msg || 'An error occurred.', 'error');
                onError && onError(resp);
            }
        })
        .catch(err => {
            console.error(err);
            CP.toast.show('Network error. Please try again.', 'error');
            onError && onError({ msg: 'Network error' });
        })
        .finally(() => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = btn._origHtml;
            }
        });
};

/* ============================================================
   CONFIRMATION DIALOG (replaces legacy _conf)
   ============================================================ */
window._conf = function(message, callback_fn, args = []) {
    const modal = document.getElementById('confirm_modal');
    if (!modal) return;
    const content = modal.querySelector('#delete_content');
    const btn     = modal.querySelector('#confirm');
    if (content) content.innerHTML = message;
    if (btn) {
        btn.onclick = function() {
            if (typeof window[callback_fn] === 'function') {
                window[callback_fn](...args);
            }
        };
    }
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
};

/* ============================================================
   SIDEBAR TOGGLE (responsive)
   ============================================================ */
CP.sidebar = (function() {
    const sidebar  = document.getElementById('sidebar');
    const overlay  = document.getElementById('sidebar-overlay');

    function open() {
        sidebar && sidebar.classList.add('open');
        overlay && overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function close() {
        sidebar && sidebar.classList.remove('open');
        overlay && overlay.classList.remove('active');
        document.body.style.overflow = '';
    }
    function toggle() {
        sidebar && sidebar.classList.contains('open') ? close() : open();
    }

    if (overlay) overlay.addEventListener('click', close);

    return { open, close, toggle };
})();

/* ============================================================
   CHARTS — ClassPulse Chart Factory
   ============================================================ */
CP.charts = (function() {
    const defaults = {
        font:        "'Inter', sans-serif",
        gridColor:   'rgba(255,255,255,0.05)',
        tickColor:   '#5E6487',
        tooltipBg:   '#1A1D2E',
        tooltipBorder:'rgba(255,255,255,0.10)',
    };

    Chart.defaults.font.family = defaults.font;
    Chart.defaults.color       = defaults.tickColor;

    function applyDefaultOptions(opts) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        color: '#9BA3C7',
                        usePointStyle: true,
                        pointStyleWidth: 8,
                        font: { size: 12 }
                    }
                },
                tooltip: {
                    backgroundColor: defaults.tooltipBg,
                    borderColor: defaults.tooltipBorder,
                    borderWidth: 1,
                    padding: 12,
                    titleColor: '#F0F2FF',
                    bodyColor: '#9BA3C7',
                    cornerRadius: 8
                }
            },
            scales: {
                x: {
                    grid: { color: defaults.gridColor },
                    ticks: { color: defaults.tickColor }
                },
                y: {
                    grid: { color: defaults.gridColor },
                    ticks: { color: defaults.tickColor },
                    beginAtZero: true
                }
            },
            ...opts
        };
    }

    function createTrendChart(canvasId, labels, datasets) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return null;
        return new Chart(ctx, {
            type: 'line',
            data: { labels, datasets },
            options: applyDefaultOptions({
                scales: {
                    x: { grid: { color: defaults.gridColor }, ticks: { color: defaults.tickColor } },
                    y: {
                        grid: { color: defaults.gridColor },
                        ticks: { color: defaults.tickColor, callback: v => v + '%' },
                        min: 0, max: 100
                    }
                }
            })
        });
    }

    function createBarChart(canvasId, labels, datasets) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return null;
        return new Chart(ctx, {
            type: 'bar',
            data: { labels, datasets },
            options: applyDefaultOptions({
                plugins: {
                    legend: {
                        display: datasets.length > 1,
                        labels: { color: '#9BA3C7', usePointStyle: true }
                    },
                    tooltip: {
                        backgroundColor: defaults.tooltipBg,
                        borderColor: defaults.tooltipBorder,
                        borderWidth: 1,
                        padding: 12,
                        titleColor: '#F0F2FF',
                        bodyColor: '#9BA3C7',
                        cornerRadius: 8
                    }
                }
            })
        });
    }

    function createDoughnutChart(canvasId, labels, data, colors) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return null;
        return new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data,
                    backgroundColor: colors,
                    borderColor: '#1A1D2E',
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#9BA3C7', usePointStyle: true, padding: 16 }
                    },
                    tooltip: {
                        backgroundColor: defaults.tooltipBg,
                        borderColor: defaults.tooltipBorder,
                        borderWidth: 1,
                        padding: 12,
                        titleColor: '#F0F2FF',
                        bodyColor: '#9BA3C7',
                        cornerRadius: 8
                    }
                }
            }
        });
    }

    return { createTrendChart, createBarChart, createDoughnutChart };
})();

/* ============================================================
   EXPORT UTILITIES
   ============================================================ */
CP.export = (function() {

    /**
     * Export table to CSV
     * @param {string} tableId - ID of the HTML table
     * @param {string} filename - Output filename (without .csv)
     */
    function toCSV(tableId, filename = 'ClassPulse_Export') {
        const table = document.getElementById(tableId);
        if (!table) return CP.toast.show('No data to export', 'warning');

        const rows = [...table.querySelectorAll('tr')];
        const csv  = rows.map(row =>
            [...row.querySelectorAll('th, td')]
                .map(cell => `"${cell.innerText.trim().replace(/"/g, '""')}"`)
                .join(',')
        ).join('\n');

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url  = URL.createObjectURL(blob);
        const a    = Object.assign(document.createElement('a'), { href: url, download: `${filename}.csv` });
        a.click();
        URL.revokeObjectURL(url);
        CP.toast.show('CSV exported successfully!', 'success');
    }

    /**
     * Export page / specific element to PDF via print dialog
     * @param {string} elementId - ID of element to print
     * @param {string} title     - Report title
     */
    function toPDF(elementId, title = 'ClassPulse Report') {
        const el = document.getElementById(elementId);
        if (!el) return CP.toast.show('No content to export', 'warning');

        const styles = [...document.querySelectorAll('link[rel="stylesheet"], style')]
            .map(s => s.outerHTML).join('');

        const win = window.open('', '_blank', 'width=1200,height=900');
        win.document.write(`<!DOCTYPE html><html><head><title>${title}</title>
            <meta charset="UTF-8">
            ${styles}
            <style>
                body { background:#fff!important; color:#222!important; font-family:Inter,sans-serif; }
                .cp-card,.stat-card { border:1px solid #ddd!important; background:#fff!important; }
                .cp-table th,.cp-table td { border:1px solid #ddd!important; color:#222!important; }
                @page { size: A4 landscape; margin: 1cm; }
            </style>
        </head><body>
        <div style="text-align:center;margin-bottom:20px;">
            <h2 style="color:#6C63FF">${title}</h2>
            <p style="color:#555">Generated: ${new Date().toLocaleString()}</p>
        </div>
        ${el.innerHTML}
        <script>window.onload=function(){window.print();setTimeout(()=>window.close(),500)}<\/script>
        </body></html>`);
        win.document.close();
        CP.toast.show('PDF export initiated!', 'success');
    }

    return { toCSV, toPDF };
})();

/* ============================================================
   PROGRESS BAR COLOR HELPER
   ============================================================ */
CP.getAttColor = function(pct) {
    if (pct >= 90) return '#22c55e';       // excellent
    if (pct >= 75) return '#6C63FF';       // good  (meets threshold)
    if (pct >= 60) return '#FFB347';       // at-risk
    return '#ef4444';                       // critical
};

/* ============================================================
   DOM READY INIT
   ============================================================ */
document.addEventListener('DOMContentLoaded', function() {
    // Close modals on overlay click
    document.querySelectorAll('.modal-overlay').forEach(el => {
        el.addEventListener('click', CP.modal.closeOnOverlay);
    });

    // Close modal buttons
    document.querySelectorAll('[data-modal-close]').forEach(btn => {
        btn.addEventListener('click', function() {
            const target = this.dataset.modalClose;
            CP.modal.close(target ? `#${target}` : null);
        });
    });

    // Sidebar hamburger
    const hamburger = document.getElementById('sidebar-toggle');
    if (hamburger) hamburger.addEventListener('click', () => CP.sidebar.toggle());

    // Animate stat values on load
    document.querySelectorAll('.stat-value[data-target]').forEach(el => {
        animateCount(el, parseInt(el.dataset.target, 10), parseFloat(el.dataset.suffix || 0));
    });

    // Animate progress bars
    setTimeout(() => {
        document.querySelectorAll('.att-progress-fill[data-width]').forEach(el => {
            el.style.width = el.dataset.width + '%';
        });
    }, 200);
});

/* ============================================================
   ANIMATED COUNTER
   ============================================================ */
function animateCount(el, target, isFloat = false) {
    const suffix = el.dataset.suffix || '';
    const prefix = el.dataset.prefix || '';
    let start = 0;
    const dur  = 1200;
    const step = dur / 60;
    const inc  = target / (dur / step);
    const timer = setInterval(() => {
        start = Math.min(start + inc, target);
        el.textContent = prefix + (isFloat ? start.toFixed(1) : Math.floor(start)) + suffix;
        if (start >= target) clearInterval(timer);
    }, step);
}

/* ============================================================
   LEGACY script.js shim (for uni_modal / _conf already using jQuery)
   ============================================================ */
if (typeof $ !== 'undefined') {
    $(function() {
        // jQuery-based modal helper (legacy support)
        window.uni_modal_jq = function(title, url, size = '') {
            $('#uni_modal .modal-title').text(title);
            $('#uni_modal .modal-body').html('<div class="text-center p-4"><span class="spinner-border spinner-border-sm"></span></div>');
            $.get(url, function(data) {
                $('#uni_modal .modal-body').html(data);
            }).fail(function() {
                $('#uni_modal .modal-body').html('<p class="text-danger">Failed to load.</p>');
            });
            if (size) {
                $('#uni_modal .modal-dialog').addClass('modal-' + (size === 'large' ? 'xl' : 'lg'));
            }
            $('#uni_modal').modal('show');
        };
    });
}
