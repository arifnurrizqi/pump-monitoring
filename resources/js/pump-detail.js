import Chart from 'chart.js/auto';

const sources = window.__PUMP_SOURCE_CHARTS__ ?? {};
const sourceSelect = document.getElementById('trend-source');
const parameterSelect = document.getElementById('trend-parameter');
const canvas = document.getElementById('source-trend-chart');
const empty = document.getElementById('trend-empty');
const livePoints = new Map();
let chart;

const timeLabel = time => new Date(time).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

function selectedSource() {
    return sources[sourceSelect?.value];
}

function renderChart() {
    if (!canvas || !sourceSelect || !parameterSelect) return;
    const source = selectedSource();
    const parameter = source?.parameters.find(item => item.key === parameterSelect.value);
    chart?.destroy();
    chart = null;
    if (!parameter) {
        empty?.classList.remove('hidden');
        return;
    }

    const history = source.points
        .filter(point => point.values[parameter.key] !== null && point.values[parameter.key] !== undefined)
        .map(point => ({ time: point.time, value: point.values[parameter.key] }));
    const live = livePoints.get(`${sourceSelect.value}:${parameter.key}`) ?? [];
    const points = [...history, ...live].slice(-120);
    empty?.classList.toggle('hidden', points.length > 0);

    chart = new Chart(canvas, {
        type: 'line',
        data: {
            labels: points.map(point => timeLabel(point.time)),
            datasets: [{
                label: `${parameter.label}${parameter.unit ? ` (${parameter.unit})` : ''}`,
                data: points.map(point => point.value),
                borderColor: '#2563eb', backgroundColor: 'rgba(37, 99, 235, 0.08)',
                fill: true, tension: 0.25, pointRadius: points.length > 40 ? 0 : 3,
            }],
        },
        options: {
            responsive: true, maintainAspectRatio: false, animation: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { maxTicksLimit: 8, color: '#94a3b8' } },
                y: { grid: { color: '#f1f5f9' }, ticks: { color: '#94a3b8' } },
            },
        },
    });
}

function changeSource() {
    if (!parameterSelect) return;
    const previous = parameterSelect.value;
    parameterSelect.replaceChildren();
    for (const parameter of selectedSource()?.parameters ?? []) {
        const option = new Option(parameter.label, parameter.key);
        parameterSelect.add(option);
    }
    if (selectedSource()?.parameters.some(parameter => parameter.key === previous)) {
        parameterSelect.value = previous;
    }
    renderChart();
}

sourceSelect?.addEventListener('change', changeSource);
parameterSelect?.addEventListener('change', renderChart);
changeSource();

window.addEventListener('pump:parameters-updated', ({ detail }) => {
    for (const source of detail.sources ?? []) {
        for (const [key, reading] of Object.entries(source.readings ?? {})) {
            if (reading.quality !== 'good' || reading.value == null) continue;
            const seriesKey = `${source.source_id}:${key}`;
            const series = livePoints.get(seriesKey) ?? [];
            series.push({ time: source.recorded_at, value: Number(reading.value) });
            livePoints.set(seriesKey, series.slice(-120));
            if (String(source.source_id) === sourceSelect?.value && key === parameterSelect?.value) renderChart();
        }
    }
});
