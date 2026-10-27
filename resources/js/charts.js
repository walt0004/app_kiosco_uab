import Chart from 'chart.js/auto';

const PALETTE = ['#1f3a9c', '#3a68e6', '#f59e0b', '#10b981', '#8eb4ff', '#ef4444', '#6366f1', '#64748b'];

function money(value) {
    return (
        'Bs ' +
        Number(value).toLocaleString('es-BO', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        })
    );
}

/**
 * Dibuja cada <canvas data-chart='{"type":"bar","labels":[...],"datasets":[{"label":"...","data":[...]}],"money":true}'>
 */
export function initCharts() {
    document.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
        let config;

        try {
            config = JSON.parse(canvas.dataset.chart);
        } catch {
            return;
        }

        const isPie = config.type === 'doughnut' || config.type === 'pie';
        const horizontal = config.horizontal === true;

        const datasets = config.datasets.map((dataset, index) => ({
            ...dataset,
            backgroundColor: isPie ? PALETTE : PALETTE[index % PALETTE.length],
            borderColor: isPie ? '#ffffff' : PALETTE[index % PALETTE.length],
            borderRadius: isPie ? 0 : 4,
        }));

        const formatValue = (value) => (config.money ? money(value) : Number(value).toLocaleString('es-BO'));

        new Chart(canvas, {
            type: config.type,
            data: { labels: config.labels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: horizontal ? 'y' : 'x',
                plugins: {
                    legend: {
                        display: isPie || datasets.length > 1,
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const value = isPie ? context.parsed : horizontal ? context.parsed.x : context.parsed.y;

                                return ` ${context.dataset.label ? context.dataset.label + ': ' : context.label + ': '}${formatValue(value)}`;
                            },
                        },
                    },
                },
                scales: isPie
                    ? {}
                    : {
                          [horizontal ? 'x' : 'y']: {
                              beginAtZero: true,
                              ticks: { callback: (value) => formatValue(value) },
                          },
                      },
            },
        });
    });
}
