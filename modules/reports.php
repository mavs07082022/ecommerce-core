<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireProductManager();

$pageTitle = 'Reports & Analytics — E-Commerce Core';

$range = $_GET['range'] ?? '30';
$ranges = ['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days', '365' => 'Last year', 'all' => 'All time'];
if (!array_key_exists($range, $ranges)) $range = '30';

$dateFilter = $range === 'all' ? '' : 'AND created_at >= DATE_SUB(NOW(), INTERVAL ' . (int)$range . ' DAY)';

$revenue = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status NOT IN ('cancelled','returned') AND payment_status = 'paid' $dateFilter")->fetchColumn();
$ordersCount = $pdo->query("SELECT COUNT(*) FROM orders WHERE 1=1 $dateFilter")->fetchColumn();
$avgOrder = $ordersCount > 0 ? $revenue / $ordersCount : 0;
$productsSold = $pdo->query("SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi JOIN orders o ON oi.order_id=o.id WHERE o.status NOT IN ('cancelled','returned') $dateFilter")->fetchColumn();
$pendingReturns = $pdo->query("SELECT COUNT(*) FROM returns WHERE status='pending'")->fetchColumn();
$lowStock = $pdo->query("SELECT COUNT(*) FROM products WHERE stock < 15")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>
<!-- Chart.js + html2pdf + html2canvas + SheetJS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<style>
.reports-header-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }

.live-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 12px; background: #ecfdf5; color: #065f46;
    border-radius: 999px; font-size: 0.72rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.05em;
}
.live-dot {
    width: 8px; height: 8px; background: #10b981; border-radius: 50%;
    animation: pulse-dot 2s infinite;
}
@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.3); }
}

.range-tabs { display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap; }
.range-tab {
    padding: 9px 18px; border-radius: 8px; text-decoration: none;
    color: #6b7280; font-size: 0.83rem; font-weight: 600;
    border: 1.5px solid #e5e7eb; background: #fff;
    transition: all 0.15s;
}
.range-tab:hover { background: #f4f7fb; border-color: #d1d5db; }
.range-tab.active {
    background: #2563eb; color: #fff; border-color: #2563eb;
    box-shadow: 0 4px 12px rgba(37,99,235,0.3);
}

.kpi-grid {
    display: grid; grid-template-columns: repeat(4, 1fr);
    gap: 20px; margin-bottom: 28px;
}
@media (max-width: 1000px) { .kpi-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 560px) { .kpi-grid { grid-template-columns: 1fr; } }

.kpi-card {
    background: #fff; border: 1px solid #e5e7eb;
    border-radius: 12px; padding: 22px;
    transition: all 0.2s;
    box-shadow: 0 1px 2px rgba(16,24,40,0.04);
    position: relative; overflow: hidden;
}
.kpi-card::after {
    content: ''; position: absolute; top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, #2563eb, #1d4ed8);
    transform: scaleX(0); transform-origin: left;
    transition: transform 0.4s;
}
.kpi-card:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(16,24,40,0.08); }
.kpi-card:hover::after { transform: scaleX(1); }

.kpi-label {
    color: #6b7280; font-size: 0.72rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 8px;
}
.kpi-value {
    font-size: 1.75rem; font-weight: 700; color: #111827;
    letter-spacing: -0.02em; line-height: 1.1;
}
.kpi-sub { color: #9ca3af; font-size: 0.75rem; margin-top: 6px; }

.chart-grid {
    display: grid; grid-template-columns: 2fr 1fr;
    gap: 20px; margin-bottom: 24px;
}
@media (max-width: 1000px) { .chart-grid { grid-template-columns: 1fr; } }

.chart-card {
    background: #fff; border: 1px solid #e5e7eb;
    border-radius: 12px; padding: 22px;
    box-shadow: 0 1px 2px rgba(16,24,40,0.04);
}
.chart-card h3 {
    font-size: 1rem; font-weight: 700; color: #111827;
    margin: 0 0 18px; display: flex; align-items: center; justify-content: space-between; gap: 12px;
}
.chart-wrap { position: relative; height: 300px; }
.chart-wrap-sm { position: relative; height: 260px; }

.export-menu { position: relative; }
.export-btn { display: inline-flex; align-items: center; gap: 6px; }
.export-dropdown {
    position: absolute; top: 100%; right: 0; margin-top: 6px;
    background: #fff; border: 1px solid #e5e7eb;
    border-radius: 10px; box-shadow: 0 12px 28px rgba(16,24,40,0.12);
    min-width: 200px; z-index: 100;
    display: none; overflow: hidden;
}
.export-dropdown.show { display: block; }
.export-dropdown button {
    display: flex; width: 100%; align-items: center; gap: 10px;
    padding: 12px 16px; background: transparent;
    border: none; font-family: inherit; font-size: 0.85rem;
    color: #374151; cursor: pointer; text-align: left;
    transition: background 0.15s;
}
.export-dropdown button:hover { background: #f4f7fb; color: #2563eb; }
.export-dropdown .divider { height: 1px; background: #f3f4f6; }

/* Toast */
.toast {
    position: fixed; top: 24px; right: 24px;
    background: #111827; color: #fff;
    padding: 14px 20px; border-radius: 12px;
    box-shadow: 0 12px 32px rgba(0,0,0,0.32);
    font-size: 0.88rem; font-weight: 600;
    z-index: 9999;
    display: flex; align-items: center; gap: 10px;
    transform: translateX(400px); transition: transform 0.3s;
}
.toast.show { transform: translateX(0); }
.toast.success { background: #059669; }
.toast.error { background: #dc2626; }

/* PDF-specific class applied during export */
.pdf-mode { background: #ffffff !important; padding: 20px !important; }
</style>

<div class="app-layout">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-wrapper">
        <header class="topbar">
            <div class="topbar-left">
                <button class="menu-toggle" onclick="openSidebar()" aria-label="Toggle menu">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div>
                    <h2>Reports & Analytics</h2>
                    <p>Real-time business insights</p>
                </div>
            </div>
            <div class="reports-header-actions">
                <span class="live-badge">
                    <span class="live-dot"></span>
                    <span id="liveTimestamp">Live</span>
                </span>
                <div class="export-menu">
                    <button class="btn btn-secondary export-btn" onclick="toggleExportMenu(event)">
                        <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                        Export
                    </button>
                    <div class="export-dropdown" id="exportMenu">
                        <button onclick="exportPDF()">
                            <svg style="width:16px;height:16px;color:#dc2626;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            Export as PDF (branded)
                        </button>
                        <div class="divider"></div>
                        <button onclick="exportExcel()">
                            <svg style="width:16px;height:16px;color:#059669;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            Export as Excel (branded)
                        </button>
                        <button onclick="exportCSV()">
                            <svg style="width:16px;height:16px;color:#0891b2;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Export as CSV
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <div class="page-body" id="reportContent">
            <div class="range-tabs">
                <?php foreach ($ranges as $key => $label): ?>
                    <a href="?range=<?= $key ?>" class="range-tab <?= $range === $key ? 'active' : '' ?>"><?= $label ?></a>
                <?php endforeach; ?>
            </div>

            <div class="kpi-grid" id="kpiGrid">
                <div class="kpi-card">
                    <div class="kpi-label">Total Revenue</div>
                    <div class="kpi-value" id="kpiRevenue">₱<?= number_format($revenue, 2) ?></div>
                    <div class="kpi-sub">Paid orders only</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Orders</div>
                    <div class="kpi-value" id="kpiOrders"><?= number_format($ordersCount) ?></div>
                    <div class="kpi-sub">All statuses</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Avg Order Value</div>
                    <div class="kpi-value" id="kpiAvgOrder">₱<?= number_format($avgOrder, 2) ?></div>
                    <div class="kpi-sub">Revenue ÷ orders</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Products Sold</div>
                    <div class="kpi-value" id="kpiProductsSold"><?= number_format($productsSold) ?></div>
                    <div class="kpi-sub">Total units</div>
                </div>
            </div>

            <div class="chart-grid">
                <div class="chart-card">
                    <h3>📈 Revenue Trend</h3>
                    <div class="chart-wrap"><canvas id="revenueChart"></canvas></div>
                </div>
                <div class="chart-card">
                    <h3>🍩 Revenue by Category</h3>
                    <div class="chart-wrap-sm"><canvas id="categoryChart"></canvas></div>
                </div>
            </div>

            <div class="chart-grid">
                <div class="chart-card">
                    <h3>📊 Orders by Status</h3>
                    <div class="chart-wrap-sm"><canvas id="statusChart"></canvas></div>
                </div>
                <div class="chart-card">
                    <h3>🏆 Top Selling Products</h3>
                    <div id="topProductsList" style="max-height:260px;overflow-y:auto;"></div>
                </div>
            </div>

            <div class="kpi-grid" style="grid-template-columns: repeat(3, 1fr);">
                <div class="kpi-card" style="border-left:4px solid #f59e0b;">
                    <div class="kpi-label">Pending Returns</div>
                    <div class="kpi-value" id="kpiPendingReturns"><?= $pendingReturns ?></div>
                    <div class="kpi-sub">Awaiting review</div>
                </div>
                <div class="kpi-card" style="border-left:4px solid #dc2626;">
                    <div class="kpi-label">Low Stock</div>
                    <div class="kpi-value" id="kpiLowStock"><?= $lowStock ?></div>
                    <div class="kpi-sub">Under 15 units</div>
                </div>
                <div class="kpi-card" style="border-left:4px solid #2563eb;">
                    <div class="kpi-label">Unpaid Orders</div>
                    <div class="kpi-value" id="kpiUnpaidOrders">0</div>
                    <div class="kpi-sub">Awaiting payment</div>
                </div>
            </div>
        </div>

        <?php require __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>

<script>
const API_URL = '<?= baseUrl('api/reports_data.php') ?>';
const CURRENT_RANGE = '<?= $range ?>';
const RANGE_LABEL = '<?= $ranges[$range] ?>';
const REFRESH_MS = 30000;
const BRAND = {
    navy: '#111827',
    slate: '#1e293b',
    blue: '#2563eb',
    darkBlue: '#1d4ed8',
    lightBlue: '#eff6ff',
    grayText: '#6b7280',
    border: '#e5e7eb',
    bg: '#f4f7fb'
};

let revenueChart, categoryChart, statusChart;
let chartData = null;

Chart.defaults.font.family = "'Inter', -apple-system, sans-serif";
Chart.defaults.font.size = 12;
Chart.defaults.color = '#6b7280';

// ============ INIT CHARTS ============
function initCharts(data) {
    chartData = data;

    const ctxRev = document.getElementById('revenueChart').getContext('2d');
    const gradient = ctxRev.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(37, 99, 235, 0.25)');
    gradient.addColorStop(1, 'rgba(37, 99, 235, 0.01)');

    if (revenueChart) revenueChart.destroy();
    revenueChart = new Chart(ctxRev, {
        type: 'line',
        data: {
            labels: data.daily.map(d => new Date(d.day + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })),
            datasets: [{
                label: 'Revenue (₱)',
                data: data.daily.map(d => d.revenue),
                borderColor: '#2563eb',
                backgroundColor: gradient,
                borderWidth: 2.5,
                fill: true,
                tension: 0.4,
                pointRadius: 0,
                pointHoverRadius: 6,
                pointHoverBackgroundColor: '#2563eb',
                pointHoverBorderColor: '#fff',
                pointHoverBorderWidth: 2
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            animation: { duration: 0 },
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#111827', padding: 12, cornerRadius: 8,
                    callbacks: { label: ctx => '₱' + parseFloat(ctx.parsed.y).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2}) }
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { maxTicksLimit: 8, color: '#9ca3af' } },
                y: {
                    beginAtZero: true, grid: { color: '#f3f4f6' },
                    ticks: { color: '#9ca3af', callback: v => '₱' + (v >= 1000 ? (v/1000) + 'k' : v) }
                }
            }
        }
    });

    const ctxCat = document.getElementById('categoryChart').getContext('2d');
    const catColors = ['#2563eb', '#1d4ed8', '#3b82f6', '#60a5fa', '#93c5fd', '#dbeafe', '#bfdbfe', '#e0e7ff'];
    if (categoryChart) categoryChart.destroy();
    categoryChart = new Chart(ctxCat, {
        type: 'doughnut',
        data: {
            labels: data.categories.map(c => c.category),
            datasets: [{
                data: data.categories.map(c => parseFloat(c.revenue)),
                backgroundColor: catColors,
                borderWidth: 3, borderColor: '#fff', hoverOffset: 8
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            animation: { duration: 0 },
            cutout: '62%',
            plugins: {
                legend: { position: 'bottom', labels: { padding: 12, boxWidth: 12, boxHeight: 12, usePointStyle: true, pointStyle: 'circle', font: { size: 11 } } },
                tooltip: {
                    backgroundColor: '#111827', padding: 12, cornerRadius: 8,
                    callbacks: { label: ctx => ctx.label + ': ₱' + parseFloat(ctx.parsed).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2}) }
                }
            }
        }
    });

    const ctxStat = document.getElementById('statusChart').getContext('2d');
    const statusColors = { 'pending': '#f59e0b', 'processing': '#2563eb', 'shipped': '#6366f1', 'delivered': '#10b981', 'cancelled': '#ef4444', 'returned': '#6b7280' };
    if (statusChart) statusChart.destroy();
    statusChart = new Chart(ctxStat, {
        type: 'bar',
        data: {
            labels: data.statuses.map(s => s.status.charAt(0).toUpperCase() + s.status.slice(1)),
            datasets: [{ label: 'Orders', data: data.statuses.map(s => s.cnt), backgroundColor: data.statuses.map(s => statusColors[s.status] || '#94a3b8'), borderRadius: 8, borderSkipped: false }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            animation: { duration: 0 },
            plugins: { legend: { display: false }, tooltip: { backgroundColor: '#111827', padding: 12, cornerRadius: 8 } },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#6b7280' } },
                y: { beginAtZero: true, grid: { color: '#f3f4f6' }, ticks: { color: '#9ca3af', precision: 0 } }
            }
        }
    });
}

function updateKPIs(kpis, timestamp) {
    document.getElementById('kpiRevenue').textContent = '₱' + parseFloat(kpis.revenue).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
    document.getElementById('kpiOrders').textContent = kpis.orders.toLocaleString();
    document.getElementById('kpiAvgOrder').textContent = '₱' + parseFloat(kpis.avg_order).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
    document.getElementById('kpiProductsSold').textContent = kpis.products_sold.toLocaleString();
    document.getElementById('kpiPendingReturns').textContent = kpis.pending_returns;
    document.getElementById('kpiLowStock').textContent = kpis.low_stock;
    document.getElementById('kpiUnpaidOrders').textContent = kpis.unpaid_orders;

    const time = new Date(timestamp).toLocaleTimeString('en-PH', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
    document.getElementById('liveTimestamp').textContent = 'Updated ' + time;
}

function renderTopProducts(products) {
    const list = document.getElementById('topProductsList');
    if (!products.length) {
        list.innerHTML = '<p style="color:#6b7280;text-align:center;padding:20px;">No sales data yet.</p>';
        return;
    }
    const max = Math.max(...products.map(p => parseFloat(p.total_revenue)));
    let html = '';
    products.forEach((p, i) => {
        const pct = max > 0 ? (parseFloat(p.total_revenue) / max * 100) : 0;
        html += `
            <div style="padding:10px 0;border-bottom:1px solid #f3f4f6;${i === products.length - 1 ? 'border-bottom:none;' : ''}">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;gap:10px;">
                    <div style="min-width:0;flex:1;">
                        <div style="font-weight:600;color:#111827;font-size:0.85rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escapeHtml(p.name)}</div>
                        <div style="color:#9ca3af;font-size:0.72rem;">${escapeHtml(p.category)} · ${p.total_qty} sold</div>
                    </div>
                    <div style="font-weight:700;color:#1d4ed8;font-size:0.83rem;white-space:nowrap;">₱${parseFloat(p.total_revenue).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2})}</div>
                </div>
                <div style="background:#f3f4f6;border-radius:4px;height:6px;overflow:hidden;">
                    <div style="background:linear-gradient(90deg,#2563eb,#1d4ed8);height:100%;width:${pct}%;transition:width 0.6s;"></div>
                </div>
            </div>`;
    });
    list.innerHTML = html;
}

function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

async function refreshData() {
    try {
        const res = await fetch(API_URL + '?range=' + CURRENT_RANGE, { cache: 'no-store' });
        const data = await res.json();
        if (!data.success) { console.error(data.error); return; }
        updateKPIs(data.kpis, data.timestamp);
        renderTopProducts(data.top_products);

        if (!chartData) {
            initCharts(data);
        } else {
            revenueChart.data.labels = data.daily.map(d => new Date(d.day + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric' }));
            revenueChart.data.datasets[0].data = data.daily.map(d => d.revenue);
            revenueChart.update('none');
            categoryChart.data.labels = data.categories.map(c => c.category);
            categoryChart.data.datasets[0].data = data.categories.map(c => parseFloat(c.revenue));
            categoryChart.update('none');
            statusChart.data.labels = data.statuses.map(s => s.status.charAt(0).toUpperCase() + s.status.slice(1));
            statusChart.data.datasets[0].data = data.statuses.map(s => s.cnt);
            statusChart.update('none');
        }
    } catch (err) { console.error(err); }
}

// ============ TOAST ============
function toast(msg, type = 'success') {
    const t = document.createElement('div');
    t.className = 'toast ' + type;
    t.innerHTML = (type === 'success' ? '✅ ' : '⚠️ ') + msg;
    document.body.appendChild(t);
    setTimeout(() => t.classList.add('show'), 10);
    setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 400); }, 3000);
}

// ============ EXPORT MENU ============
function toggleExportMenu(e) {
    e.stopPropagation();
    document.getElementById('exportMenu').classList.toggle('show');
}
document.addEventListener('click', () => document.getElementById('exportMenu')?.classList.remove('show'));

// ============================================================
// BRANDED PDF EXPORT — builds a dedicated printable layout
// ============================================================
async function exportPDF() {
    document.getElementById('exportMenu').classList.remove('show');
    if (!chartData) { toast('Data not loaded yet', 'error'); return; }

    toast('Generating PDF...', 'success');

    try {
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF({ unit: 'mm', format: 'a4', orientation: 'portrait' });
        const pageW = 210, pageH = 297, margin = 12;

        // ---------- Build branded HTML block off-screen ----------
        const wrapper = document.createElement('div');
        wrapper.style.cssText = `
            position: fixed; left: -10000px; top: 0;
            width: 800px; background: #fff;
            font-family: 'Inter', -apple-system, sans-serif; color: #111827;
        `;

        const revenueChartImg = document.getElementById('revenueChart').toDataURL('image/png', 1.0);
        const categoryChartImg = document.getElementById('categoryChart').toDataURL('image/png', 1.0);
        const statusChartImg = document.getElementById('statusChart').toDataURL('image/png', 1.0);

        const kpis = chartData.kpis;
        const fmtP = v => '₱' + parseFloat(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        const topRows = chartData.top_products.map((p, i) => `
            <tr style="background:${i % 2 === 0 ? '#f9fafb' : '#ffffff'};">
                <td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;font-size:12px;">${escapeHtml(p.name)}</td>
                <td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;font-size:12px;color:#6b7280;">${escapeHtml(p.category)}</td>
                <td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;font-size:12px;text-align:center;font-weight:600;">${p.total_qty}</td>
                <td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;font-size:12px;text-align:right;color:#1d4ed8;font-weight:600;">${fmtP(p.total_revenue)}</td>
            </tr>
        `).join('');

        const catRows = chartData.categories.map((c, i) => `
            <tr style="background:${i % 2 === 0 ? '#f9fafb' : '#ffffff'};">
                <td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;font-size:12px;">${escapeHtml(c.category)}</td>
                <td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;font-size:12px;text-align:right;color:#1d4ed8;font-weight:600;">${fmtP(c.revenue)}</td>
            </tr>
        `).join('');

        const statusRows = chartData.statuses.map((s, i) => `
            <tr style="background:${i % 2 === 0 ? '#f9fafb' : '#ffffff'};">
                <td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;font-size:12px;text-transform:capitalize;">${escapeHtml(s.status)}</td>
                <td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;font-size:12px;text-align:center;font-weight:600;">${s.cnt}</td>
            </tr>
        `).join('');

        wrapper.innerHTML = `
            <!-- Header Banner -->
            <div style="background: linear-gradient(135deg, #1e293b 0%, #111827 100%); padding: 32px 40px; color: #fff; border-radius: 0 0 24px 24px;">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 14px;">
                        <div style="width: 52px; height: 52px; background: #2563eb; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 22px; color: #fff;">E</div>
                        <div>
                            <div style="font-size: 22px; font-weight: 800; letter-spacing: -0.02em;">E-Commerce Core</div>
                            <div style="font-size: 11px; color: #9ca3af; text-transform: uppercase; letter-spacing: 2px; margin-top: 2px;">Analytics Report</div>
                        </div>
                    </div>
                    <div style="text-align: right; font-size: 11px; color: #dbeafe; line-height: 1.6;">
                        <div><strong style="color: #fff;">Generated:</strong> ${new Date().toLocaleString('en-PH')}</div>
                        <div><strong style="color: #fff;">Range:</strong> ${RANGE_LABEL}</div>
                    </div>
                </div>
            </div>

            <!-- KPI Row -->
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; padding: 24px 40px 0;">
                <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 16px;">
                    <div style="font-size: 10px; font-weight: 700; color: #1d4ed8; text-transform: uppercase; letter-spacing: 1px;">Revenue</div>
                    <div style="font-size: 18px; font-weight: 800; color: #111827; margin-top: 6px;">${fmtP(kpis.revenue)}</div>
                </div>
                <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 16px;">
                    <div style="font-size: 10px; font-weight: 700; color: #1d4ed8; text-transform: uppercase; letter-spacing: 1px;">Orders</div>
                    <div style="font-size: 18px; font-weight: 800; color: #111827; margin-top: 6px;">${kpis.orders}</div>
                </div>
                <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 16px;">
                    <div style="font-size: 10px; font-weight: 700; color: #1d4ed8; text-transform: uppercase; letter-spacing: 1px;">Avg Order</div>
                    <div style="font-size: 18px; font-weight: 800; color: #111827; margin-top: 6px;">${fmtP(kpis.avg_order)}</div>
                </div>
                <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 16px;">
                    <div style="font-size: 10px; font-weight: 700; color: #1d4ed8; text-transform: uppercase; letter-spacing: 1px;">Units Sold</div>
                    <div style="font-size: 18px; font-weight: 800; color: #111827; margin-top: 6px;">${kpis.products_sold}</div>
                </div>
            </div>

            <!-- Charts -->
            <div style="padding: 24px 40px 0;">
                <div style="font-size: 14px; font-weight: 800; color: #111827; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #2563eb; display: inline-block;">Revenue Trend</div>
                <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px;">
                    <img src="${revenueChartImg}" style="width: 100%; display: block;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; padding: 20px 40px 0;">
                <div>
                    <div style="font-size: 14px; font-weight: 800; color: #111827; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #2563eb; display: inline-block;">Revenue by Category</div>
                    <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px;">
                        <img src="${categoryChartImg}" style="width: 100%; display: block;">
                    </div>
                </div>
                <div>
                    <div style="font-size: 14px; font-weight: 800; color: #111827; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #2563eb; display: inline-block;">Orders by Status</div>
                    <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px;">
                        <img src="${statusChartImg}" style="width: 100%; display: block;">
                    </div>
                </div>
            </div>

            <!-- Top Products Table -->
            <div style="padding: 24px 40px 0;">
                <div style="font-size: 14px; font-weight: 800; color: #111827; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #2563eb; display: inline-block;">Top Selling Products</div>
                <table style="width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden;">
                    <thead>
                        <tr style="background: #1e293b; color: #fff;">
                            <th style="padding: 12px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Product</th>
                            <th style="padding: 12px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Category</th>
                            <th style="padding: 12px; text-align: center; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Qty</th>
                            <th style="padding: 12px; text-align: right; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>${topRows || '<tr><td colspan="4" style="padding:20px;text-align:center;color:#6b7280;">No data</td></tr>'}</tbody>
                </table>
            </div>

            <!-- Two Tables side by side -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; padding: 20px 40px 40px;">
                <div>
                    <div style="font-size: 14px; font-weight: 800; color: #111827; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #2563eb; display: inline-block;">Revenue by Category</div>
                    <table style="width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden;">
                        <thead>
                            <tr style="background: #1e293b; color: #fff;">
                                <th style="padding: 10px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 1px;">Category</th>
                                <th style="padding: 10px; text-align: right; font-size: 10px; text-transform: uppercase; letter-spacing: 1px;">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>${catRows}</tbody>
                    </table>
                </div>
                <div>
                    <div style="font-size: 14px; font-weight: 800; color: #111827; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #2563eb; display: inline-block;">Orders by Status</div>
                    <table style="width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden;">
                        <thead>
                            <tr style="background: #1e293b; color: #fff;">
                                <th style="padding: 10px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 1px;">Status</th>
                                <th style="padding: 10px; text-align: center; font-size: 10px; text-transform: uppercase; letter-spacing: 1px;">Count</th>
                            </tr>
                        </thead>
                        <tbody>${statusRows}</tbody>
                    </table>
                </div>
            </div>

            <!-- Footer -->
            <div style="background: #f4f7fb; padding: 16px 40px; border-top: 1px solid #e5e7eb; text-align: center; font-size: 10px; color: #9ca3af;">
                © ${new Date().getFullYear()} E-Commerce Core. All rights reserved. Report auto-generated.
            </div>
        `;

        document.body.appendChild(wrapper);

        // Wait for fonts to render
        await new Promise(r => setTimeout(r, 300));

        // Capture as image
        const canvas = await html2canvas(wrapper, {
            scale: 2,
            useCORS: true,
            backgroundColor: '#ffffff',
            logging: false,
            windowWidth: 800
        });

        document.body.removeChild(wrapper);

        // Add to PDF with pagination
        const imgData = canvas.toDataURL('image/jpeg', 0.95);
        const imgW = pageW - margin * 2;
        const imgH = (canvas.height * imgW) / canvas.width;

        let heightLeft = imgH;
        let position = margin;

        pdf.addImage(imgData, 'JPEG', margin, position, imgW, imgH);
        heightLeft -= (pageH - margin * 2);

        while (heightLeft > 0) {
            position = heightLeft - imgH + margin;
            pdf.addPage();
            pdf.addImage(imgData, 'JPEG', margin, position, imgW, imgH);
            heightLeft -= (pageH - margin * 2);
        }

        pdf.save('ECommerce-Report-' + new Date().toISOString().slice(0, 10) + '.pdf');
        toast('PDF downloaded!');
    } catch (err) {
        console.error(err);
        toast('PDF export failed: ' + err.message, 'error');
    }
}

// ============================================================
// BRANDED EXCEL EXPORT
// ============================================================
function exportExcel() {
    document.getElementById('exportMenu').classList.remove('show');
    if (!chartData) { toast('Data not loaded yet', 'error'); return; }

    const wb = XLSX.utils.book_new();

    // ============ SUMMARY SHEET with brand colors ============
    const summaryData = [
        ['E-COMMERCE CORE — ANALYTICS REPORT'],
        [''],
        ['Generated', new Date().toLocaleString('en-PH')],
        ['Date Range', RANGE_LABEL],
        [''],
        ['KPI METRICS', 'VALUE'],
        ['Total Revenue', parseFloat(chartData.kpis.revenue)],
        ['Orders', chartData.kpis.orders],
        ['Avg Order Value', parseFloat(chartData.kpis.avg_order)],
        ['Products Sold', chartData.kpis.products_sold],
        [''],
        ['ALERTS', 'COUNT'],
        ['Pending Returns', chartData.kpis.pending_returns],
        ['Low Stock Products', chartData.kpis.low_stock],
        ['Unpaid Orders', chartData.kpis.unpaid_orders]
    ];
    const wsSummary = XLSX.utils.aoa_to_sheet(summaryData);
    wsSummary['!cols'] = [{ wch: 25 }, { wch: 22 }];
    wsSummary['!merges'] = [
        { s: { r: 0, c: 0 }, e: { r: 0, c: 1 } },
        { s: { r: 5, c: 0 }, e: { r: 5, c: 1 } },
        { s: { r: 11, c: 0 }, e: { r: 11, c: 1 } }
    ];
    // Apply brand styling to header cells
    if (!wsSummary['!style']) wsSummary['!style'] = {};
    applyCellStyle(wsSummary, 'A1', { bg: '1e293b', color: 'FFFFFF', bold: true, size: 14, align: 'center' });
    applyCellStyle(wsSummary, 'A6', { bg: '2563eb', color: 'FFFFFF', bold: true, size: 11 });
    applyCellStyle(wsSummary, 'B6', { bg: '2563eb', color: 'FFFFFF', bold: true, size: 11, align: 'right' });
    applyCellStyle(wsSummary, 'A12', { bg: 'f59e0b', color: 'FFFFFF', bold: true, size: 11 });
    applyCellStyle(wsSummary, 'B12', { bg: 'f59e0b', color: 'FFFFFF', bold: true, size: 11, align: 'right' });
    applyCellStyle(wsSummary, 'B7', { color: '1d4ed8', bold: true });
    XLSX.utils.book_append_sheet(wb, wsSummary, 'Summary');

    // ============ DAILY REVENUE ============
    const dailyData = [['DATE', 'REVENUE (₱)', 'ORDERS']];
    chartData.daily.forEach(d => dailyData.push([d.day, parseFloat(d.revenue), d.orders]));
    const wsDaily = XLSX.utils.aoa_to_sheet(dailyData);
    wsDaily['!cols'] = [{ wch: 15 }, { wch: 18 }, { wch: 12 }];
    ['A1', 'B1', 'C1'].forEach(c => applyCellStyle(wsDaily, c, { bg: '1e293b', color: 'FFFFFF', bold: true, size: 11 }));
    for (let r = 2; r <= dailyData.length; r++) {
        applyCellStyle(wsDaily, 'B' + r, { color: '1d4ed8', numFmt: '"₱"#,##0.00' });
    }
    XLSX.utils.book_append_sheet(wb, wsDaily, 'Daily Revenue');

    // ============ BY CATEGORY ============
    const catData = [['CATEGORY', 'REVENUE (₱)']];
    chartData.categories.forEach(c => catData.push([c.category, parseFloat(c.revenue)]));
    const wsCat = XLSX.utils.aoa_to_sheet(catData);
    wsCat['!cols'] = [{ wch: 25 }, { wch: 18 }];
    ['A1', 'B1'].forEach(c => applyCellStyle(wsCat, c, { bg: '1e293b', color: 'FFFFFF', bold: true, size: 11 }));
    for (let r = 2; r <= catData.length; r++) {
        applyCellStyle(wsCat, 'B' + r, { color: '1d4ed8', numFmt: '"₱"#,##0.00' });
    }
    XLSX.utils.book_append_sheet(wb, wsCat, 'By Category');

    // ============ BY STATUS ============
    const statData = [['STATUS', 'COUNT']];
    chartData.statuses.forEach(s => statData.push([s.status, s.cnt]));
    const wsStat = XLSX.utils.aoa_to_sheet(statData);
    wsStat['!cols'] = [{ wch: 20 }, { wch: 12 }];
    ['A1', 'B1'].forEach(c => applyCellStyle(wsStat, c, { bg: '1e293b', color: 'FFFFFF', bold: true, size: 11 }));
    XLSX.utils.book_append_sheet(wb, wsStat, 'By Status');

    // ============ TOP PRODUCTS ============
    const topData = [['PRODUCT', 'CATEGORY', 'QTY SOLD', 'REVENUE (₱)']];
    chartData.top_products.forEach(p => topData.push([p.name, p.category, p.total_qty, parseFloat(p.total_revenue)]));
    const wsTop = XLSX.utils.aoa_to_sheet(topData);
    wsTop['!cols'] = [{ wch: 32 }, { wch: 18 }, { wch: 12 }, { wch: 18 }];
    ['A1', 'B1', 'C1', 'D1'].forEach(c => applyCellStyle(wsTop, c, { bg: '1e293b', color: 'FFFFFF', bold: true, size: 11 }));
    for (let r = 2; r <= topData.length; r++) {
        applyCellStyle(wsTop, 'D' + r, { color: '1d4ed8', numFmt: '"₱"#,##0.00' });
    }
    XLSX.utils.book_append_sheet(wb, wsTop, 'Top Products');

    XLSX.writeFile(wb, 'ECommerce-Report-' + new Date().toISOString().slice(0, 10) + '.xlsx');
    toast('Excel downloaded!');
}

function applyCellStyle(ws, cell, opts) {
    if (!ws[cell]) ws[cell] = { v: '', t: 's' };
    ws[cell].s = {
        font: {
            name: 'Calibri',
            sz: opts.size || 11,
            bold: !!opts.bold,
            color: { rgb: opts.color || '111827' }
        },
        fill: opts.bg ? { fgColor: { rgb: opts.bg } } : undefined,
        alignment: {
            horizontal: opts.align || 'left',
            vertical: 'center'
        },
        numFmt: opts.numFmt
    };
}

// ============================================================
// CSV EXPORT
// ============================================================
function exportCSV() {
    document.getElementById('exportMenu').classList.remove('show');
    if (!chartData) { toast('Data not loaded yet', 'error'); return; }

    const fmtP = v => parseFloat(v).toFixed(2);
    let csv = 'E-Commerce Core — Analytics Report\n';
    csv += 'Generated,' + new Date().toLocaleString('en-PH') + '\n';
    csv += 'Date Range,' + RANGE_LABEL + '\n\n';

    csv += 'KPI,Value\n';
    csv += 'Total Revenue (₱),' + fmtP(chartData.kpis.revenue) + '\n';
    csv += 'Orders,' + chartData.kpis.orders + '\n';
    csv += 'Avg Order Value (₱),' + fmtP(chartData.kpis.avg_order) + '\n';
    csv += 'Products Sold,' + chartData.kpis.products_sold + '\n\n';

    csv += 'Date,Revenue (₱),Orders\n';
    chartData.daily.forEach(d => csv += `${d.day},${fmtP(d.revenue)},${d.orders}\n`);

    csv += '\nCategory,Revenue (₱)\n';
    chartData.categories.forEach(c => csv += `"${c.category}",${fmtP(c.revenue)}\n`);

    csv += '\nStatus,Count\n';
    chartData.statuses.forEach(s => csv += `"${s.status}",${s.cnt}\n`);

    csv += '\nProduct,Category,Quantity Sold,Revenue (₱)\n';
    chartData.top_products.forEach(p => csv += `"${p.name}","${p.category}",${p.total_qty},${fmtP(p.total_revenue)}\n`);

    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'ECommerce-Report-' + new Date().toISOString().slice(0, 10) + '.csv';
    link.click();
    URL.revokeObjectURL(url);
    toast('CSV downloaded!');
}

// ============ BOOT ============
document.addEventListener('DOMContentLoaded', () => {
    refreshData();
    setInterval(refreshData, REFRESH_MS);
});
</script>