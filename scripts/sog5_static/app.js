// SOG5 Dashboard - simplified client

const API_BASE = '';
const UPDATE_INTERVAL = 5000;

let updateTimer = null;
let hourlyChart = null;
let dailyTrendChart = null;
let currentDaysFilter = 30;
let selectedDate = null;

document.addEventListener('DOMContentLoaded', () => {
    initializeHourlyChart();
    initializeDailyTrendChart();
    initializeStepsGrid();
    initializeDailyRecordsTable();
    startAutoUpdate();
});

async function fetchCurrentData() {
    try {
        const response = await fetch(`${API_BASE}/api/current`);
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return await response.json();
    } catch (error) {
        console.error('Error fetching current data:', error);
        updateStatus(false, error.message);
        return null;
    }
}

async function fetchDailyRecords(days = 30) {
    try {
        const response = await fetch(`${API_BASE}/api/daily-records?days=${days}`);
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return await response.json();
    } catch (error) {
        console.error('Error fetching daily records:', error);
        return [];
    }
}

async function fetchHourlyConsumption(date) {
    const query = date ? `?date=${encodeURIComponent(date)}` : '';
    try {
        const response = await fetch(`${API_BASE}/api/hourly-consumption${query}`);
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return await response.json();
    } catch (error) {
        console.error('Error fetching hourly consumption:', error);
        return [];
    }
}

async function fetchConsumptionSummary() {
    try {
        const response = await fetch(`${API_BASE}/api/consumption-summary`);
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return await response.json();
    } catch (error) {
        console.error('Error fetching consumption summary:', error);
        return null;
    }
}

function updateStatus(online, message = '') {
    const statusDot = document.getElementById('statusDot');
    const statusText = document.getElementById('statusText');
    if (!statusDot || !statusText) return;

    if (online) {
        statusDot.classList.add('online');
        statusText.textContent = 'Bağlı';
        statusText.style.color = 'var(--accent-primary)';
    } else {
        statusDot.classList.remove('online');
        statusText.textContent = message || 'Bağlantı Hatası';
        statusText.style.color = 'var(--accent-danger)';
    }
}

function updateLastUpdateTime(timestamp) {
    const element = document.getElementById('lastUpdate');
    if (!element) return;
    element.textContent = timestamp || new Date().toLocaleTimeString('tr-TR');
}

function updateMetricValue(elementId, value, decimals = 1) {
    const element = document.getElementById(elementId);
    if (!element) return;

    if (value === null || value === undefined) {
        element.textContent = 'N/A';
        element.classList.add('error');
        return;
    }

    element.textContent = value.toFixed(decimals);
    element.classList.remove('error');
}

function updateDashboard(data) {
    if (!data) return;

    updateLastUpdateTime(data.ts);
    updateStatus(true);

    updateMetricValue('v_l1', data.v_l1_v, 1);
    updateMetricValue('v_l2', data.v_l2_v, 1);
    updateMetricValue('v_l3', data.v_l3_v, 1);
    updateMetricValue('v_l12', data.v_l1_l2_v, 1);
    updateMetricValue('v_l23', data.v_l2_l3_v, 1);
    updateMetricValue('v_l31', data.v_l3_l1_v, 1);

    updateMetricValue('e_total', data.e_total_import_kwh, 1);
    updateMetricValue('e_reactive_ind_total', data.e_total_reactive_ind_kvarh, 1);
    updateMetricValue('e_reactive_cap_total', data.e_total_reactive_cap_kvarh, 1);

    updateStepsStatus(data.step_status_bits);
}

function setTextValue(elementId, value) {
    const element = document.getElementById(elementId);
    if (!element) return;
    element.textContent = value;
}

function updateConsumptionSummary(summary) {
    if (!summary) return;

    const monthToNow = summary.month_to_now || {};
    const dayToNow = summary.day_to_now || {};
    const limits = summary.limits || {};
    const indLimit = limits.ind_pct || 20;
    const capLimit = limits.cap_pct || 15;

    const mtdInd = monthToNow.ind_pct != null ? monthToNow.ind_pct.toFixed(2) : '-';
    const mtdCap = monthToNow.cap_pct != null ? monthToNow.cap_pct.toFixed(2) : '-';
    const dayInd = dayToNow.ind_pct != null ? dayToNow.ind_pct.toFixed(2) : '-';
    const dayCap = dayToNow.cap_pct != null ? dayToNow.cap_pct.toFixed(2) : '-';

    setTextValue('mtd_ind_ratio', mtdInd);
    setTextValue('mtd_cap_ratio', mtdCap);
    setTextValue('day_ind_ratio', dayInd);
    setTextValue('day_cap_ratio', dayCap);

    // Ceza eşiği: aylık oranları kullan (lifetime birikimli değil)
    const mtdIndPct = monthToNow.ind_pct;
    const mtdCapPct = monthToNow.cap_pct;
    const penaltyText = `End ${mtdIndPct != null ? mtdIndPct.toFixed(2) : '-'} | Kap ${mtdCapPct != null ? mtdCapPct.toFixed(2) : '-'}`;
    setTextValue('penalty_status', penaltyText);

    const penaltyEl = document.getElementById('penalty_status');
    if (penaltyEl) {
        penaltyEl.classList.remove('error', 'warning');
        const indOver = mtdIndPct != null && mtdIndPct >= indLimit;
        const capOver = mtdCapPct != null && mtdCapPct >= capLimit;
        if (indOver || capOver) {
            penaltyEl.classList.add('error');
        } else if ((mtdIndPct != null && mtdIndPct >= indLimit * 0.8) ||
                   (mtdCapPct != null && mtdCapPct >= capLimit * 0.8)) {
            penaltyEl.classList.add('warning');
        }
    }
}

function initializeStepsGrid() {
    const grid = document.getElementById('stepsGrid');
    if (!grid) return;

    for (let i = 1; i <= 32; i++) {
        const item = document.createElement('div');
        item.className = 'step-item';
        item.id = `step_${i}`;
        item.innerHTML = `
            <div class="step-num">${i}</div>
            <div class="step-label">KDM</div>
        `;
        grid.appendChild(item);
    }
}

function updateStepsStatus(statusBits) {
    if (statusBits === null || statusBits === undefined) return;

    for (let i = 0; i < 32; i++) {
        const element = document.getElementById(`step_${i + 1}`);
        if (!element) continue;
        element.classList.toggle('active', (statusBits & (1 << i)) !== 0);
    }
}

function initializeHourlyChart() {
    const canvas = document.getElementById('hourlyChart');
    if (!canvas) return;

    hourlyChart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: [],
            datasets: [
                {
                    type: 'bar',
                    label: 'Aktif (kWh)',
                    data: [],
                    backgroundColor: 'rgba(0, 230, 118, 0.35)',
                    borderColor: '#00e676',
                    borderWidth: 1,
                    yAxisID: 'y'
                },
                {
                    type: 'line',
                    label: 'End. Reaktif %',
                    data: [],
                    borderColor: '#40c4ff',
                    backgroundColor: 'rgba(64, 196, 255, 0.12)',
                    tension: 0.35,
                    yAxisID: 'y1'
                },
                {
                    type: 'line',
                    label: 'Kap. Reaktif %',
                    data: [],
                    borderColor: '#ffd740',
                    backgroundColor: 'rgba(255, 215, 64, 0.12)',
                    tension: 0.35,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    labels: { color: '#e8eaf6' }
                }
            },
            scales: {
                x: {
                    ticks: { color: '#5c6bc0' },
                    grid: { color: 'rgba(255,255,255,0.05)' }
                },
                y: {
                    position: 'left',
                    ticks: { color: '#5c6bc0' },
                    grid: { color: 'rgba(255,255,255,0.05)' },
                    title: { display: true, text: 'Aktif (kWh)', color: '#9fa8da' }
                },
                y1: {
                    position: 'right',
                    ticks: { color: '#9fa8da', callback: v => v.toFixed(1) + '%' },
                    grid: { drawOnChartArea: false },
                    title: { display: true, text: 'Reaktif Oran (%)', color: '#9fa8da' }
                }
            }
        }
    });
}

function initializeDailyTrendChart() {
    const canvas = document.getElementById('dailyTrendChart');
    if (!canvas) return;

    dailyTrendChart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: [],
            datasets: [
                {
                    type: 'bar',
                    label: 'Günlük Aktif (kWh)',
                    data: [],
                    backgroundColor: 'rgba(63, 129, 201, 0.5)',
                    borderColor: '#6fa8ff',
                    borderWidth: 1,
                    yAxisID: 'y'
                },
                {
                    type: 'line',
                    label: 'Reaktif Günlük %',
                    data: [],
                    borderColor: '#ff9f43',
                    backgroundColor: 'rgba(255, 159, 67, 0.2)',
                    tension: 0.25,
                    yAxisID: 'y1'
                },
                {
                    type: 'line',
                    label: 'Kapasitif Günlük %',
                    data: [],
                    borderColor: '#40c4ff',
                    backgroundColor: 'rgba(64, 196, 255, 0.2)',
                    tension: 0.25,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { labels: { color: '#e8eaf6' } }
            },
            scales: {
                x: {
                    ticks: { color: '#5c6bc0' },
                    grid: { color: 'rgba(255,255,255,0.05)' }
                },
                y: {
                    position: 'left',
                    ticks: { color: '#5c6bc0' },
                    grid: { color: 'rgba(255,255,255,0.05)' },
                    title: { display: true, text: 'kWh', color: '#9fa8da' }
                },
                y1: {
                    position: 'right',
                    ticks: { color: '#9fa8da' },
                    grid: { drawOnChartArea: false },
                    title: { display: true, text: '%', color: '#9fa8da' }
                }
            }
        }
    });
}

function renderHourlyChart(date, rows) {
    const meta = document.getElementById('hourlyChartMeta');
    if (meta) {
        if (!rows || rows.length === 0) {
            meta.textContent = date ? `${date} icin saatlik veri yok` : 'Bir gun secin';
        } else {
            const source = rows[0].source || 'db';
            meta.textContent = `${date} | kaynak: ${source}`;
        }
    }

    if (!hourlyChart) return;

    if (!rows || rows.length === 0) {
        hourlyChart.data.labels = [];
        hourlyChart.data.datasets.forEach(ds => ds.data = []);
        hourlyChart.update();
        return;
    }

    hourlyChart.data.labels = rows.map(row => row.hour_slot.slice(0, 5));
    hourlyChart.data.datasets[0].data = rows.map(row => row.active_kwh || 0);
    hourlyChart.data.datasets[1].data = rows.map(row => {
        const a = row.active_kwh || 0;
        return a > 0 ? parseFloat(((row.reactive_ind_kvarh || 0) / a * 100).toFixed(2)) : 0;
    });
    hourlyChart.data.datasets[2].data = rows.map(row => {
        const a = row.active_kwh || 0;
        return a > 0 ? parseFloat(((row.reactive_cap_kvarh || 0) / a * 100).toFixed(2)) : 0;
    });
    hourlyChart.update();
}

function formatNumber(num, decimals = 1) {
    if (num == null) return '-';
    return num.toLocaleString('tr-TR', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    });
}

function buildHourlyTable(rows) {
    if (!rows || rows.length === 0) {
        return '<div class="hourly-empty">Saatlik veri yok.</div>';
    }

    const body = rows.map(row => {
        const a = row.active_kwh || 0;
        const indPct = a > 0 ? (row.reactive_ind_kvarh || 0) / a * 100 : 0;
        const capPct = a > 0 ? (row.reactive_cap_kvarh || 0) / a * 100 : 0;
        return `
        <tr>
            <td>${row.hour_slot.slice(0, 5)}</td>
            <td class="number-cell positive">${formatNumber(row.active_kwh)}</td>
            <td class="number-cell">${formatNumber(row.reactive_ind_kvarh)}</td>
            <td class="ratio-cell">${indPct.toFixed(1)}%</td>
            <td class="number-cell">${formatNumber(row.reactive_cap_kvarh)}</td>
            <td class="ratio-cell">${capPct.toFixed(1)}%</td>
        </tr>
    `}).join('');

    return `
        <div class="hourly-inline-wrap">
            <table class="daily-records-table hourly-inline-table">
                <thead>
                    <tr>
                        <th>Saat</th>
                        <th>Aktif (kWh)</th>
                        <th>End. (kVARh)</th>
                        <th>End. %</th>
                        <th>Kap. (kVARh)</th>
                        <th>Kap. %</th>
                    </tr>
                </thead>
                <tbody>${body}</tbody>
            </table>
        </div>
    `;
}

function updateDailyTrendChart(records) {
    if (!dailyTrendChart) return;
    const meta = document.getElementById('dailyTrendMeta');

    if (!records || records.length === 0) {
        dailyTrendChart.data.labels = [];
        dailyTrendChart.data.datasets.forEach(ds => ds.data = []);
        dailyTrendChart.update();
        if (meta) meta.textContent = 'Günlük trend için yeterli veri yok.';
        return;
    }

    const ordered = records
        .filter(r => r.daily_consumption_kwh != null)
        .slice()
        .reverse();

    dailyTrendChart.data.labels = ordered.map(r => r.snapshot_date);
    dailyTrendChart.data.datasets[0].data = ordered.map(r => r.daily_consumption_kwh || 0);
    dailyTrendChart.data.datasets[1].data = ordered.map(r => r.reactive_ind_ratio_pct || 0);
    dailyTrendChart.data.datasets[2].data = ordered.map(r => r.reactive_cap_ratio_pct || 0);
    dailyTrendChart.update();

    if (meta) {
        meta.textContent = `Seçili dönem: son ${ordered.length} günlük kayıt`;
    }
}

async function toggleDailyRow(date) {
    const detailRows = document.querySelectorAll('.hourly-detail-row');
    const summaryRows = document.querySelectorAll('.daily-summary-row');

    for (const row of detailRows) {
        const isTarget = row.dataset.date === date;
        row.classList.toggle('open', isTarget && selectedDate !== date);
    }
    for (const row of summaryRows) {
        row.classList.toggle('selected', row.dataset.date === date && selectedDate !== date);
    }

    if (selectedDate === date) {
        selectedDate = null;
        renderHourlyChart(null, []);
        return;
    }

    selectedDate = date;
    const rows = await fetchHourlyConsumption(date);
    const container = document.getElementById(`hourly-detail-${date}`);
    if (container) {
        container.innerHTML = buildHourlyTable(rows);
    }
    renderHourlyChart(date, rows);
}

function renderDailyRecords(records) {
    const tbody = document.getElementById('dailyRecordsBody');
    if (!tbody) return;

    updateDailyTrendChart(records || []);

    if (!records || records.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10" class="no-data-message">Henüz günlük kayıt bulunmuyor.</td></tr>';
        return;
    }

    tbody.innerHTML = records.map(record => {
        const date = record.snapshot_date || '-';
        const toggle = (record.hourly_count || 0) > 0 ? '▸' : '•';

        // Anomali tespiti: negatif tüketim veya çok düşük değer (veri boşluğu)
        const dailyKwh = record.daily_consumption_kwh;
        const indPct = record.reactive_ind_ratio_pct;
        const capPct = record.reactive_cap_ratio_pct;
        const isAnomalous = (dailyKwh != null && dailyKwh < 500) ||
                            (indPct != null && indPct < 0) ||
                            (capPct != null && capPct < 0);
        const indOver = indPct != null && indPct >= 20;
        const capOver = capPct != null && capPct >= 15;
        const rowClass = isAnomalous ? 'daily-summary-row row-anomaly' :
                         (indOver || capOver) ? 'daily-summary-row row-penalty' :
                         'daily-summary-row';

        const indCell = `<td class="ratio-cell${indOver ? ' ratio-over' : ''}">${indPct != null ? indPct.toFixed(2) : '-'}</td>`;
        const capCell = `<td class="ratio-cell${capOver ? ' ratio-over' : ''}">${capPct != null ? capPct.toFixed(2) : '-'}</td>`;

        return `
            <tr class="${rowClass}" data-date="${date}">
                <td class="toggle-cell"><button class="toggle-btn" data-date="${date}">${toggle}</button></td>
                <td class="date-cell">${date}${isAnomalous ? ' ⚠' : ''}</td>
                <td class="number-cell">${formatNumber(record.e_total_kwh)}</td>
                <td class="number-cell positive">${formatNumber(dailyKwh)}</td>
                <td class="number-cell">${formatNumber(record.e_total_reactive_ind_kvarh)}</td>
                <td class="number-cell">${formatNumber(record.daily_reactive_ind_kvarh)}</td>
                ${indCell}
                <td class="number-cell">${formatNumber(record.e_total_reactive_cap_kvarh)}</td>
                <td class="number-cell">${formatNumber(record.daily_reactive_cap_kvarh)}</td>
                ${capCell}
            </tr>
            <tr class="hourly-detail-row" data-date="${date}">
                <td colspan="10">
                    <div class="hourly-detail-box" id="hourly-detail-${date}">Yukleniyor...</div>
                </td>
            </tr>
        `;
    }).join('');

    tbody.querySelectorAll('.toggle-btn').forEach(button => {
        button.addEventListener('click', () => toggleDailyRow(button.dataset.date));
    });

    const firstWithHourly = records.find(row => (row.hourly_count || 0) > 0);
    if (firstWithHourly) {
        toggleDailyRow(firstWithHourly.snapshot_date);
    }
}

function initializeDailyRecordsTable() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    filterButtons.forEach(btn => {
        btn.addEventListener('click', async () => {
            filterButtons.forEach(item => item.classList.remove('active'));
            btn.classList.add('active');
            currentDaysFilter = parseInt(btn.dataset.days, 10);
            selectedDate = null;
            const records = await fetchDailyRecords(currentDaysFilter);
            renderDailyRecords(records);
        });
    });

    fetchDailyRecords(currentDaysFilter).then(renderDailyRecords);
}

async function performUpdate() {
    try {
        const currentData = await fetchCurrentData();
        if (currentData) updateDashboard(currentData);

        const summary = await fetchConsumptionSummary();
        if (summary) updateConsumptionSummary(summary);
    } catch (error) {
        console.error('Update error:', error);
        updateStatus(false, 'Guncelleme hatasi');
    }
}

function startAutoUpdate() {
    performUpdate();
    updateTimer = setInterval(performUpdate, UPDATE_INTERVAL);
}

function stopAutoUpdate() {
    if (updateTimer) {
        clearInterval(updateTimer);
        updateTimer = null;
    }
}

window.addEventListener('beforeunload', stopAutoUpdate);

window.SOG5 = {
    fetchCurrentData,
    fetchDailyRecords,
    fetchHourlyConsumption,
    performUpdate,
    toggleDailyRow
};