import Chart from 'chart.js/auto';

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('dashboard-app');
    if (!root) return;

    const initialData = JSON.parse(root.dataset.initial || '{}');
    const metricsUrl = root.dataset.metricsUrl;

    const revCanvas = document.getElementById('revenue-chart');
    const topCanvas = document.getElementById('top-products-chart');
    const rangeRevenueEl = document.getElementById('dash-range-revenue');
    const rangeOrdersEl = document.getElementById('dash-range-orders');
    const rangeUnitsEl = document.getElementById('dash-range-units');
    const statusBox = document.getElementById('dash-chart-status');
    const revenueTableBody = document.getElementById('dash-revenue-table-body');
    const topTableBody = document.getElementById('dash-top-table-body');

    let revenueChart = null;
    let topChart = null;

    Chart.defaults.color = '#BDB5AA';
    Chart.defaults.font.family = "'Plus Jakarta Sans', system-ui, sans-serif";

    function renderCharts(data) {
        const series = data.revenue_series || [];
        const top = data.top_products || [];

        if (rangeRevenueEl && data.selected_range) {
            rangeRevenueEl.textContent = data.selected_range.revenue_formatted;
        }
        if (rangeOrdersEl && data.selected_range) {
            rangeOrdersEl.textContent = String(data.selected_range.orders_count);
        }
        if (rangeUnitsEl && data.selected_range) {
            rangeUnitsEl.textContent = String(data.selected_range.units_sold);
        }

        if (revenueTableBody) {
            revenueTableBody.innerHTML = '';
            series.forEach((row) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td></td><td class="tabular-nums"></td><td class="tabular-nums text-right"></td>`;
                const cells = tr.querySelectorAll('td');
                cells[0].textContent = row.date;
                cells[1].textContent = String(row.orders);
                cells[2].textContent = row.revenue_formatted;
                revenueTableBody.appendChild(tr);
            });
        }

        if (topTableBody) {
            topTableBody.innerHTML = '';
            top.forEach((row) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td></td><td class="tabular-nums"></td><td class="tabular-nums text-right"></td>`;
                const cells = tr.querySelectorAll('td');
                cells[0].textContent = row.name;
                cells[1].textContent = String(row.units_sold);
                cells[2].textContent = row.revenue_formatted;
                topTableBody.appendChild(tr);
            });
        }

        if (revCanvas) {
            revenueChart?.destroy();
            revenueChart = new Chart(revCanvas, {
                type: 'line',
                data: {
                    labels: series.map((s) => s.label),
                    datasets: [
                        {
                            label: 'Revenue (PHP)',
                            data: series.map((s) => s.revenue),
                            borderColor: '#C6A66B',
                            backgroundColor: 'rgba(198, 166, 107, 0.14)',
                            borderWidth: 2,
                            pointRadius: 3,
                            pointBackgroundColor: '#C6A66B',
                            tension: 0.25,
                            fill: true,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#24211D',
                            titleColor: '#F5F0E8',
                            bodyColor: '#C6A66B',
                            borderColor: 'rgba(198,166,107,0.35)',
                            borderWidth: 1,
                        },
                    },
                    scales: {
                        x: {
                            grid: { color: 'rgba(245, 240, 232, 0.05)' },
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(245, 240, 232, 0.06)' },
                            ticks: {
                                callback: (v) => `₱${Number(v).toLocaleString()}`,
                            },
                        },
                    },
                },
            });
        }

        if (topCanvas) {
            topChart?.destroy();
            topChart = new Chart(topCanvas, {
                type: 'bar',
                data: {
                    labels: top.map((p) => p.name),
                    datasets: [
                        {
                            label: 'Units Sold',
                            data: top.map((p) => p.units_sold),
                            backgroundColor: '#C6A66B',
                            borderRadius: 6,
                        },
                    ],
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#24211D',
                            titleColor: '#F5F0E8',
                            bodyColor: '#C6A66B',
                        },
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: { precision: 0 },
                            grid: { color: 'rgba(245, 240, 232, 0.06)' },
                        },
                        y: {
                            grid: { display: false },
                        },
                    },
                },
            });
        }
    }

    document.querySelectorAll('[data-dash-preset]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            if (!metricsUrl) return;
            const preset = btn.dataset.dashPreset;

            document.querySelectorAll('[data-dash-preset]').forEach((other) => {
                const active = other === btn;
                other.classList.toggle('abc-btn-primary', active);
                other.classList.toggle('abc-btn-secondary', !active);
                other.setAttribute('aria-pressed', active ? 'true' : 'false');
            });

            if (statusBox) {
                statusBox.textContent = 'Refreshing metrics...';
                statusBox.classList.remove('hidden', 'text-[#E6777D]');
            }

            try {
                const res = await fetch(`${metricsUrl}?preset=${encodeURIComponent(preset)}`, {
                    headers: { Accept: 'application/json' },
                });
                if (!res.ok) throw new Error('Request failed');
                const fresh = await res.json();
                renderCharts(fresh);
                statusBox?.classList.add('hidden');
            } catch {
                if (statusBox) {
                    statusBox.textContent = 'Could not load dashboard metrics. Please try again.';
                    statusBox.classList.remove('hidden');
                    statusBox.classList.add('text-[#E6777D]');
                }
            }
        });
    });

    renderCharts(initialData);
});
