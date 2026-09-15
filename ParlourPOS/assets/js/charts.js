// assets/js/charts.js

// Global Chart Instances to allow updating without destroying the canvas
let revChartInstance = null;
let custChartInstance = null;

// Thematic Colors matching CSS
const colors = {
    sidebar: '#1a1b41',
    coral: '#ff6b6b',
    nude: '#E8DCC4',
    gridLines: 'rgba(0,0,0,0.05)'
};

/**
 * Renders or updates the Area Chart for Revenue
 */
function updateRevenueChart(labels, dataPoints) {
    const ctx = document.getElementById('revenueChart');
    if (!ctx) return;

    if (revChartInstance) {
        revChartInstance.data.labels = labels;
        revChartInstance.data.datasets[0].data = dataPoints;
        revChartInstance.update();
        return;
    }

    // Create gradient fill
    const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(26, 27, 65, 0.2)'); // Brand Sidebar Color fading out
    gradient.addColorStop(1, 'rgba(26, 27, 65, 0)');

    revChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Revenue (₹)',
                data: dataPoints,
                borderColor: colors.sidebar,
                backgroundColor: gradient,
                borderWidth: 2,
                pointBackgroundColor: colors.coral,
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: colors.coral,
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.4 // Smooth curves[cite: 2]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: colors.sidebar,
                    padding: 10,
                    cornerRadius: 8,
                    displayColors: false
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#94a3b8', font: { family: "'Inter', sans-serif" } }
                },
                y: {
                    grid: { color: colors.gridLines },
                    ticks: { color: '#94a3b8', font: { family: "'Inter', sans-serif" } },
                    border: { display: false }
                }
            }
        }
    });
}

/**
 * Renders or updates the Donut Chart for Customers
 */
function updateCustomerChart(dataPoints) {
    const ctx = document.getElementById('customerChart');
    if (!ctx) return;

    if (custChartInstance) {
        custChartInstance.data.datasets[0].data = dataPoints;
        custChartInstance.update();
        return;
    }

    custChartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['New', 'Returning', 'Members'],
            datasets: [{
                data: dataPoints,
                backgroundColor: [colors.coral, colors.sidebar, colors.nude],
                borderWidth: 2,
                borderColor: '#ffffff',
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        color: '#64748b',
                        font: { family: "'Inter', sans-serif", size: 12 }
                    }
                },
                tooltip: {
                    backgroundColor: colors.sidebar,
                    padding: 10,
                    cornerRadius: 8
                }
            }
        }
    });
}