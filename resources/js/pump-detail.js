import Chart from 'chart.js/auto';

const dashboard = document.querySelector('[data-chart-dashboard]');
const groupDefinitions = window.__PUMP_CHART_GROUPS__ ?? [];
const colors = ['#2563eb', '#f97316', '#10b981', '#a855f7', '#e11d48', '#0891b2', '#ca8a04'];
const ranges = { '1h': 3600, '12h': 43200, '1d': 86400, '7d': 604800, '1mo': 2592000 };
const charts = new Map();
const history = new Map();
const live = new Map();
let selectedRange = '1h';
let activeRequest;

function timeLabel(time) {
    const options = selectedRange === '1h' || selectedRange === '12h'
        ? { hour: '2-digit', minute: '2-digit' }
        : { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' };
    return new Date(time).toLocaleString('id-ID', options);
}

function renderGroup(group) {
    const card = dashboard?.querySelector(`[data-chart-group="${group.key}"]`);
    const canvas = card?.querySelector('canvas');
    if (!canvas) return;

    const since = Date.now() - ranges[selectedRange] * 1000;
    const merged = new Map();
    for (const point of [...(history.get(group.key) ?? []), ...(live.get(group.key) ?? [])]) {
        const timestamp = new Date(point.time).getTime();
        if (!Number.isFinite(timestamp) || timestamp < since) continue;
        const current = merged.get(timestamp) ?? {};
        merged.set(timestamp, { ...current, ...point.values });
    }
    const times = [...merged.keys()].sort((a, b) => a - b);
    card.querySelector('[data-chart-empty]')?.classList.toggle('hidden', times.length > 0);

    const labelCounts = group.series.reduce((counts, series) => {
        counts[series.label] = (counts[series.label] ?? 0) + 1;
        return counts;
    }, {});
    const datasets = group.series.map((series, index) => ({
        label: `${series.label}${labelCounts[series.label] > 1 ? ` · ${series.source_name}` : ''}${series.unit ? ` (${series.unit})` : ''}`,
        data: times.map(time => merged.get(time)[series.id] ?? null),
        borderColor: colors[index % colors.length],
        backgroundColor: colors[index % colors.length],
        borderWidth: 2,
        pointRadius: times.length > 40 ? 0 : 2,
        pointHoverRadius: 4,
        tension: 0.2,
        spanGaps: false,
    }));

    const existing = charts.get(group.key);
    if (existing) {
        existing.data.labels = times.map(timeLabel);
        existing.data.datasets = datasets;
        existing.options.plugins.tooltip.callbacks.title = items => new Date(times[items[0]?.dataIndex]).toLocaleString('id-ID');
        existing.update('none');
        return;
    }

    charts.set(group.key, new Chart(canvas, {
        type: 'line',
        data: { labels: times.map(timeLabel), datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } },
                tooltip: { callbacks: { title: items => new Date(times[items[0]?.dataIndex]).toLocaleString('id-ID') } },
            },
            scales: {
                x: { grid: { display: false }, ticks: { maxTicksLimit: 6, color: '#64748b' } },
                y: { grid: { color: '#f1f5f9' }, ticks: { color: '#64748b' } },
            },
        },
    }));
}

async function loadRange(range) {
    if (!dashboard || !groupDefinitions.length) return;
    selectedRange = range;
    dashboard.querySelectorAll('[data-chart-range]').forEach(button => {
        const selected = button.dataset.chartRange === range;
        button.setAttribute('aria-pressed', String(selected));
        button.classList.toggle('bg-blue-600', selected);
        button.classList.toggle('text-white', selected);
        button.classList.toggle('text-slate-600', !selected);
    });
    activeRequest?.abort();
    const request = new AbortController();
    activeRequest = request;
    dashboard.querySelector('[data-chart-error]')?.classList.add('hidden');
    try {
        const url = new URL(dashboard.dataset.chartUrl, window.location.href);
        url.searchParams.set('range', range);
        const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: request.signal });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const payload = await response.json();
        if (activeRequest !== request) return;
        for (const group of groupDefinitions) {
            history.set(group.key, payload.groups.find(item => item.key === group.key)?.points ?? []);
            renderGroup(group);
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            dashboard.querySelector('[data-chart-error]')?.classList.remove('hidden');
            console.error('Gagal memuat histori grafik', error);
        }
    }
}

dashboard?.querySelectorAll('[data-chart-range]').forEach(button => {
    button.addEventListener('click', () => loadRange(button.dataset.chartRange));
});
loadRange('1h');

window.addEventListener('pump:parameters-updated', ({ detail }) => {
    for (const source of detail.sources ?? []) {
        for (const group of groupDefinitions) {
            const values = {};
            for (const series of group.series) {
                if (Number(series.source_id) !== Number(source.source_id)) continue;
                const reading = source.readings?.[series.key];
                if (reading?.quality === 'good' && reading.value != null) {
                    values[series.id] = Number(reading.value);
                }
            }
            if (!Object.keys(values).length) continue;
            const points = live.get(group.key) ?? [];
            points.push({ time: source.recorded_at, values });
            live.set(group.key, points.slice(-500));
            renderGroup(group);
        }
    }
});
