import Chart from 'chart.js/auto';

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('dashboard-app');
    if (!root) return;

    const initialData = JSON.parse(root.dataset.initial || '{}');
    const metricsUrl = root.dataset.metricsUrl;

    const presetSelect = document.getElementById('dash-preset-select');
    const revCanvas = document.getElementById('revenue-chart');
    const periodLabelEl = document.getElementById('dash-period-label');
    const chartHeadingEl = document.getElementById('dash-chart-heading');
    const chartTotalEl = document.getElementById('dash-chart-total');
    const recentHeadingEl = document.getElementById('dash-recent-heading');
    const topHeadingEl = document.getElementById('dash-top-heading');
    const rangeRevenueEl = document.getElementById('dash-range-revenue');
    const rangeOrdersEl = document.getElementById('dash-range-orders');
    const rangeUnitsEl = document.getElementById('dash-range-units');
    const rangeAvgEl = document.getElementById('dash-range-avg');
    const statusBox = document.getElementById('dash-chart-status');
    const revenueTableBody = document.getElementById('dash-revenue-table-body');
    const recentOrdersBody = document.getElementById('dash-recent-orders-body');
    const bestSellersList = document.getElementById('dash-best-sellers-list');

    let revenueChart = null;

    Chart.defaults.color = '#BFB4A5';
    Chart.defaults.font.family = "'Plus Jakarta Sans', system-ui, sans-serif";

    function renderDashboard(data) {
        const series = data.revenue_series || [];
        const top = data.top_products || [];
        const recent = data.recent_orders || [];
        const label = data.range?.label || 'Selected Period';

        if (periodLabelEl) periodLabelEl.textContent = label;
        if (chartHeadingEl) chartHeadingEl.textContent = label;
        if (recentHeadingEl) recentHeadingEl.textContent = label;
        if (topHeadingEl) topHeadingEl.textContent = label;

        if (data.selected_range) {
            if (rangeRevenueEl) rangeRevenueEl.textContent = data.selected_range.revenue_formatted;
            if (chartTotalEl) chartTotalEl.textContent = data.selected_range.revenue_formatted;
            if (rangeOrdersEl) rangeOrdersEl.textContent = String(data.selected_range.orders_count);
            if (rangeUnitsEl) rangeUnitsEl.textContent = String(data.selected_range.units_sold);
            if (rangeAvgEl) rangeAvgEl.textContent = data.selected_range.average_order_formatted;
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

        if (recentOrdersBody) {
            recentOrdersBody.innerHTML = '';
            if (recent.length === 0) {
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = 5;
                td.className = 'text-center text-sm text-[#BFB4A5] py-8';
                td.textContent = 'No completed orders in this period.';
                tr.appendChild(td);
                recentOrdersBody.appendChild(tr);
            } else {
                recent.forEach((ord) => {
                    const tr = document.createElement('tr');

                    const tdId = document.createElement('td');
                    tdId.className = 'tabular-nums whitespace-nowrap';
                    const link = document.createElement('a');
                    link.href = ord.show_url;
                    link.className = 'font-medium text-[#D2B077] hover:underline';
                    link.textContent = ord.order_number;
                    const dt = document.createElement('div');
                    dt.className = 'text-xs text-[#BFB4A5]';
                    dt.textContent = ord.created_at_manila;
                    tdId.appendChild(link);
                    tdId.appendChild(dt);

                    const tdItems = document.createElement('td');
                    tdItems.className = 'text-sm text-[#F4EEE5]';
                    tdItems.textContent = ord.items_summary;

                    const tdCustomer = document.createElement('td');
                    tdCustomer.className = 'text-sm text-[#BFB4A5]';
                    tdCustomer.textContent = ord.customer_name;

                    const tdAmt = document.createElement('td');
                    tdAmt.className = 'text-right font-semibold tabular-nums text-[#F4EEE5]';
                    tdAmt.textContent = ord.total_formatted;

                    const tdAction = document.createElement('td');
                    tdAction.className = 'text-right whitespace-nowrap';
                    const recLink = document.createElement('a');
                    recLink.href = ord.receipt_url;
                    recLink.className = 'abc-btn-secondary py-1 px-2.5 text-xs';
                    recLink.textContent = 'Receipt';
                    tdAction.appendChild(recLink);

                    tr.appendChild(tdId);
                    tr.appendChild(tdItems);
                    tr.appendChild(tdCustomer);
                    tr.appendChild(tdAmt);
                    tr.appendChild(tdAction);
                    recentOrdersBody.appendChild(tr);
                });
            }
        }

        if (bestSellersList) {
            bestSellersList.innerHTML = '';
            if (top.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'text-sm text-[#BFB4A5] py-6 text-center';
                empty.textContent = 'No product sales recorded in this period.';
                bestSellersList.appendChild(empty);
            } else {
                top.forEach((item) => {
                    const card = document.createElement('div');
                    card.className = 'abc-surface p-3 space-y-1.5';

                    const topRow = document.createElement('div');
                    topRow.className = 'flex items-center justify-between gap-2 text-sm';
                    const nameSpan = document.createElement('span');
                    nameSpan.className = 'font-medium text-[#F4EEE5] truncate';
                    nameSpan.textContent = item.name;
                    const revSpan = document.createElement('span');
                    revSpan.className = 'text-xs font-semibold text-[#D2B077] tabular-nums shrink-0';
                    revSpan.textContent = item.revenue_formatted;
                    topRow.appendChild(nameSpan);
                    topRow.appendChild(revSpan);

                    const metaRow = document.createElement('div');
                    metaRow.className = 'flex items-center justify-between text-xs text-[#BFB4A5] tabular-nums';
                    const unitsSpan = document.createElement('span');
                    unitsSpan.textContent = `${item.units_sold} units sold`;
                    const pctSpan = document.createElement('span');
                    pctSpan.textContent = `${item.share_percent}%`;
                    metaRow.appendChild(unitsSpan);
                    metaRow.appendChild(pctSpan);

                    const track = document.createElement('div');
                    track.className = 'h-1.5 rounded-full bg-[#161412] overflow-hidden';
                    const fill = document.createElement('div');
                    fill.className = 'h-full rounded-full bg-[#D2B077]';
                    fill.style.width = `${item.share_percent}%`;
                    track.appendChild(fill);

                    card.appendChild(topRow);
                    card.appendChild(metaRow);
                    card.appendChild(track);
                    bestSellersList.appendChild(card);
                });
            }
        }

        if (revCanvas) {
            revenueChart?.destroy();
            revenueChart = new Chart(revCanvas, {
                type: 'bar',
                data: {
                    labels: series.map((s) => s.label),
                    datasets: [
                        {
                            label: 'Revenue (PHP)',
                            data: series.map((s) => s.revenue),
                            backgroundColor: 'rgba(210, 176, 119, 0.86)',
                            hoverBackgroundColor: '#DFC08B',
                            borderRadius: 6,
                            maxBarThickness: 32,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#161412',
                            titleColor: '#F4EEE5',
                            bodyColor: '#D2B077',
                            borderColor: 'rgba(210,176,119,0.38)',
                            borderWidth: 1,
                        },
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(244, 238, 229, 0.06)' },
                            ticks: {
                                callback: (v) => `₱${Number(v).toLocaleString()}`,
                            },
                        },
                    },
                },
            });
        }
    }

    presetSelect?.addEventListener('change', async () => {
        if (!metricsUrl) return;
        const preset = presetSelect.value;

        if (statusBox) {
            statusBox.textContent = 'Refreshing sales overview...';
            statusBox.classList.remove('hidden', 'text-[#E07A7A]');
        }

        try {
            const res = await fetch(`${metricsUrl}?preset=${encodeURIComponent(preset)}`, {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) throw new Error('Request failed');
            const fresh = await res.json();
            renderDashboard(fresh);
            statusBox?.classList.add('hidden');
        } catch {
            if (statusBox) {
                statusBox.textContent = 'Could not load dashboard metrics. Please try again.';
                statusBox.classList.remove('hidden');
                statusBox.classList.add('text-[#E07A7A]');
            }
        }
    });

    renderDashboard(initialData);
});
