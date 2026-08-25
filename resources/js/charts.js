import Chart from 'chart.js/auto';

/**
 * Charts read their data from a JSON script tag beside the canvas, so no
 * markup has to inline a JS object literal.
 *
 * Palette is the seal's: maroon, gold, forest, plus a warm neutral.
 */
const PALETTE = ['#780000', '#f0dc00', '#145000', '#b3b0a1', '#a82c2c', '#6cb356'];

const BASE = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            labels: {
                boxWidth: 12,
                boxHeight: 12,
                usePointStyle: true,
                font: { family: 'Inter, sans-serif', size: 12 },
                color: '#514f43',
            },
        },
        tooltip: {
            backgroundColor: '#26251f',
            padding: 10,
            cornerRadius: 6,
            titleFont: { family: 'Inter, sans-serif', size: 12 },
            bodyFont: { family: 'Inter, sans-serif', size: 12 },
        },
    },
};

const GRID = {
    grid: { color: 'rgba(38,37,31,.07)', drawBorder: false },
    ticks: { font: { family: 'Inter, sans-serif', size: 11 }, color: '#8a8778' },
};

function build(canvas) {
    const config = JSON.parse(
        document.getElementById(canvas.dataset.chart).textContent
    );

    const type = canvas.dataset.type || 'bar';

    const datasets = config.datasets.map((set, i) => ({
        ...set,
        backgroundColor: type === 'line'
            ? 'rgba(120,0,0,.08)'
            : (set.backgroundColor || (type === 'pie' || type === 'doughnut' ? PALETTE : PALETTE[i])),
        borderColor: type === 'line' ? PALETTE[0] : (type === 'pie' || type === 'doughnut' ? '#fff' : 'transparent'),
        borderWidth: type === 'line' ? 2 : (type === 'pie' || type === 'doughnut' ? 2 : 0),
        borderRadius: type === 'bar' ? 4 : undefined,
        tension: type === 'line' ? 0.35 : undefined,
        fill: type === 'line',
        pointBackgroundColor: PALETTE[0],
        pointRadius: type === 'line' ? 3 : undefined,
    }));

    new Chart(canvas, {
        type,
        data: { labels: config.labels, datasets },
        options: {
            ...BASE,
            plugins: {
                ...BASE.plugins,
                legend: {
                    ...BASE.plugins.legend,
                    display: type === 'pie' || type === 'doughnut' || datasets.length > 1,
                    position: type === 'pie' || type === 'doughnut' ? 'bottom' : 'top',
                },
            },
            scales: (type === 'pie' || type === 'doughnut')
                ? {}
                : { x: GRID, y: { ...GRID, beginAtZero: true } },
        },
    });
}

document.querySelectorAll('canvas[data-chart]').forEach(build);
