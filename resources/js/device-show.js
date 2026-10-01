/**
 * Device Show — Telemetry Charts
 * Requires: chart.js, window.__DEVICE_CHART_DATA__
 */
import Chart from 'chart.js/auto';

// ── Palette ────────────────────────────────────────────────────
const C = {
    indigo:  { border: '#6366f1', bg: 'rgba(99, 102, 241, 0.08)' },
    violet:  { border: '#8b5cf6', bg: 'rgba(139, 92, 246, 0.08)' },
    sky:     { border: '#0ea5e9', bg: 'rgba(14, 165, 233, 0.10)' },
    emerald: { border: '#10b981', bg: 'rgba(16, 185, 129, 0.12)' },
    red:     'rgba(239, 68, 68, 0.7)',
    grid:    '#f3f4f6',
    tick:    '#9ca3af',
};

// ── Registry ───────────────────────────────────────────────────
let charts = {};
let currentPeriod = '24h';

function destroyAll() {
    Object.values(charts).forEach(c => c?.destroy());
    charts = {};
}

// ── Helpers ────────────────────────────────────────────────────
const pluck = (rows, key) => rows.map(r => r[key] ?? null);

function thresholdSet(label, value, count) {
    return {
        label,
        data: Array(count).fill(value),
        borderColor: C.red,
        borderDash: [5, 4],
        borderWidth: 1.5,
        pointRadius: 0,
        fill: false,
        tension: 0,
        _isThreshold: true, // used to hide from tooltip
    };
}

// ── Shared config builders ─────────────────────────────────────
function makePlugins() {
    return {
        legend: { display: false },
        tooltip: {
            mode: 'index',
            intersect: false,
            filter: item => !item.dataset._isThreshold,
            backgroundColor: '#ffffff',
            titleColor: '#111827',
            bodyColor: '#6b7280',
            borderColor: '#e5e7eb',
            borderWidth: 1,
            padding: 10,
            cornerRadius: 8,
            boxPadding: 4,
        },
    };
}

function makeXScale() {
    return {
        grid: { display: false },
        border: { display: false },
        ticks: {
            color: C.tick,
            font: { size: 11 },
            maxTicksLimit: 8,
            maxRotation: 0,
        },
    };
}

function makeYScale(extra = {}) {
    return {
        grid: { color: C.grid },
        border: { display: false },
        ticks: {
            color: C.tick,
            font: { size: 11 },
            padding: 8,
        },
        ...extra,
    };
}

function makeElements() {
    return {
        line: { tension: 0.4, borderCapStyle: 'round' },
        point: {
            radius: 0,
            hoverRadius: 4,
            hoverBorderWidth: 2,
            hoverBackgroundColor: '#ffffff',
        },
    };
}

function lineChart(id, labels, datasets, yExtra = {}) {
    const canvas = document.getElementById(id);
    if (!canvas) return null;
    return new Chart(canvas, {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            animation: { duration: 500, easing: 'easeOutQuart' },
            plugins: makePlugins(),
            scales: { x: makeXScale(), y: makeYScale(yExtra) },
            elements: makeElements(),
        },
    });
}

function barChart(id, labels, datasets, yExtra = {}) {
    const canvas = document.getElementById(id);
    if (!canvas) return null;
    return new Chart(canvas, {
        type: 'bar',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            animation: { duration: 500, easing: 'easeOutQuart' },
            plugins: makePlugins(),
            scales: { x: makeXScale(), y: makeYScale({ beginAtZero: true, ...yExtra }) },
            elements: { bar: { borderRadius: 3, borderSkipped: false } },
        },
    });
}

// ── Main render ────────────────────────────────────────────────
function renderCharts(period) {
    currentPeriod = period;
    const DATA = window.__DEVICE_CHART_DATA__;
    if (!DATA) return;

    destroyAll();

    const rows = DATA[period] ?? [];
    const usageRows = DATA.usage?.[period] ?? [];
    const T = DATA.thresholds ?? {};
    const n = rows.length;

    // Toggle visibility
    const noDataEl  = document.getElementById('charts-no-data');
    const gridEl    = document.getElementById('charts-grid');

    if (n === 0) {
        noDataEl?.classList.remove('hidden');
        gridEl?.classList.add('hidden');
        return;
    }
    noDataEl?.classList.add('hidden');
    gridEl?.classList.remove('hidden');

    const labels = rows.map(r => r.label);

    // ── pH ─────────────────────────────────────────────────────
    const phSets = [{
        label: 'pH',
        data: pluck(rows, 'ph'),
        borderColor: C.indigo.border,
        backgroundColor: C.indigo.bg,
        borderWidth: 2,
        fill: true,
    }];
    if (T.ph_min != null) phSets.push(thresholdSet('pH Min', T.ph_min, n));
    if (T.ph_max != null) phSets.push(thresholdSet('pH Max', T.ph_max, n));
    charts.ph = lineChart('chart-ph', labels, phSets);

    // ── Turbidity ──────────────────────────────────────────────
    const turbSets = [{
        label: 'Turbidity (NTU)',
        data: pluck(rows, 'turbidity'),
        borderColor: C.violet.border,
        backgroundColor: C.violet.bg,
        borderWidth: 2,
        fill: true,
    }];
    if (T.turbidity_max != null) turbSets.push(thresholdSet('Max', T.turbidity_max, n));
    charts.turbidity = lineChart('chart-turbidity', labels, turbSets);

    // ── Water Level % ──────────────────────────────────────────
    const levelSets = [{
        label: 'Level (%)',
        data: pluck(rows, 'level'),
        borderColor: C.sky.border,
        backgroundColor: C.sky.bg,
        borderWidth: 2,
        fill: true,
    }];
    if (T.level_min != null) levelSets.push(thresholdSet('Min%', T.level_min, n));
    if (T.level_max != null) levelSets.push(thresholdSet('Max%', T.level_max, n));
    charts.level = lineChart('chart-level', labels, levelSets, {
        min: 0,
        max: 100,
        ticks: {
            color: C.tick,
            font: { size: 11 },
            padding: 8,
            callback: v => v + '%',
        },
    });

    // ── Water Usage ────────────────────────────────────────────
    charts.waterUsage = barChart('chart-water-usage', usageRows.map(row => row.label), [{
        label: 'Water Usage (L)',
        data: pluck(usageRows, 'usage'),
        backgroundColor: C.emerald.bg,
        borderColor: C.emerald.border,
        borderWidth: 1.5,
    }], {
        ticks: {
            color: C.tick,
            font: { size: 11 },
            padding: 8,
            callback: value => `${value} L`,
        },
    });
}

// ── Public API ─────────────────────────────────────────────────
window.updateCharts = renderCharts;

function replaceRealtimeChartData(chart, labels, values) {
    if (!chart) return;

    chart.data.labels = labels;
    chart.data.datasets.forEach((dataset, index) => {
        if (index === 0) {
            dataset.data = values;
            return;
        }

        const threshold = dataset.data.find(value => value !== null && value !== undefined);
        dataset.data = Array(labels.length).fill(threshold);
    });
    chart.update();
}

function updateRealtimeCharts(data) {
    const rows = data['1h'] ?? [];
    if (!charts.ph || !charts.turbidity || !charts.level || !charts.waterUsage) {
        renderCharts('1h');
        return;
    }

    const labels = rows.map(row => row.label);
    replaceRealtimeChartData(charts.ph, labels, pluck(rows, 'ph'));
    replaceRealtimeChartData(charts.turbidity, labels, pluck(rows, 'turbidity'));
    replaceRealtimeChartData(charts.level, labels, pluck(rows, 'level'));

    const usageRows = data.usage?.['1h'] ?? [];
    replaceRealtimeChartData(
        charts.waterUsage,
        usageRows.map(row => row.label),
        pluck(usageRows, 'usage'),
    );
}

window.appendRealtimeTelemetryPoint = point => {
    const data = window.__DEVICE_CHART_DATA__;
    if (!data || !point) return;

    data['1h'] ??= [];
    data['1h'].push({
        label: point.label,
        ph: point.ph,
        turbidity: point.turbidity,
        level: point.level,
    });
    data['1h'] = data['1h'].slice(-720);

    data.usage ??= {};
    data.usage['1h'] ??= [];
    const usageBucket = data.usage['1h'].find(row => row.label === point.usage_label);
    if (usageBucket) usageBucket.usage = Number(usageBucket.usage ?? 0) + Number(point.usage ?? 0);
    else data.usage['1h'].push({ label: point.usage_label, usage: Number(point.usage ?? 0) });
    data.usage['1h'] = data.usage['1h'].slice(-12);

    if (currentPeriod === '1h') updateRealtimeCharts(data);
};

// Auto-init (module scripts are deferred — DOM is ready at this point)
renderCharts('24h');
