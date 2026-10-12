/**
 * VIROS IT Portal - Dashboard Real-Time Multi-Section Analytics & Charts
 * Powered by Chart.js
 */

document.addEventListener("DOMContentLoaded", function () {
    if (typeof Chart === "undefined" || !window.DASHBOARD_ANALYTICS) {
        return;
    }

    const analytics = window.DASHBOARD_ANALYTICS;
    const statusData = analytics.statusData || { inUse: 0, available: 0, maintenance: 0, retired: 0 };
    const deptData = analytics.deptData || [];
    const catData = analytics.categoriesData || [];
    const ticketData = analytics.ticketPriorityData || { urgent: 0, high: 0, medium: 0, low: 0, total: 0 };
    const swSpendData = analytics.softwareSpendData || [];

    // Global Typography & Styling
    Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';
    Chart.defaults.color = '#64748b';

    // =========================================================================
    // CHART 1: Asset Lifecycle & Status Doughnut Chart
    // =========================================================================
    const statusCanvas = document.getElementById("assetStatusChart");
    if (statusCanvas) {
        const total = (statusData.inUse + statusData.available + statusData.maintenance + statusData.retired);
        const chartData = total > 0 
            ? [statusData.inUse, statusData.available, statusData.maintenance, statusData.retired]
            : [0, 0, 0, 0];

        new Chart(statusCanvas, {
            type: "doughnut",
            data: {
                labels: ["In Use / Deployed", "Available in Stock", "Under Maintenance", "Retired"],
                datasets: [{
                    data: chartData,
                    backgroundColor: [
                        "#0891b2", // Cyan / In Use
                        "#10b981", // Emerald / Available
                        "#f59e0b", // Amber / Maintenance
                        "#94a3b8"  // Slate / Retired
                    ],
                    borderWidth: 3,
                    borderColor: "#ffffff",
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: "72%",
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: "#0f172a",
                        titleColor: "#ffffff",
                        bodyColor: "#94a3b8",
                        padding: 12,
                        cornerRadius: 8,
                        boxPadding: 6,
                        callbacks: {
                            label: function (context) {
                                const val = context.raw || 0;
                                const pct = total > 0 ? Math.round((val / total) * 100) : 0;
                                return ` ${context.label}: ${val} Units (${pct}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    // =========================================================================
    // CHART 2: Department Hardware Allocation Bar Chart
    // =========================================================================
    const deptCanvas = document.getElementById("deptAllocationChart");
    if (deptCanvas) {
        let labels = [];
        let values = [];

        if (deptData && deptData.length > 0) {
            deptData.forEach(item => {
                labels.push(item.name || "General");
                values.push(item.count || 0);
            });
        } else if (catData && catData.length > 0) {
            catData.forEach(item => {
                labels.push(item.name || "General");
                values.push(item.count || 0);
            });
        } else {
            labels = ["IT Dept", "HR Dept", "Operations", "Finance"];
            values = [0, 0, 0, 0];
        }

        new Chart(deptCanvas, {
            type: "bar",
            data: {
                labels: labels,
                datasets: [{
                    label: "Managed Assets",
                    data: values,
                    backgroundColor: "#0284c7",
                    hoverBackgroundColor: "#0369a1",
                    borderRadius: 6,
                    maxBarThickness: 42
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { size: 12, weight: "600" },
                            color: "#475569"
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            stepSize: 1,
                            color: "#64748b"
                        },
                        grid: { color: "#f1f5f9" }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: "#0f172a",
                        titleColor: "#ffffff",
                        bodyColor: "#94a3b8",
                        padding: 12,
                        cornerRadius: 8,
                        boxPadding: 6,
                        callbacks: {
                            label: function (context) {
                                return ` ${context.raw} Assets Assigned`;
                            }
                        }
                    }
                }
            }
        });
    }

    // =========================================================================
    // CHART 3: Helpdesk Incidents by Priority & Triage (Polar Area / Doughnut)
    // =========================================================================
    const ticketCanvas = document.getElementById("ticketPriorityChart");
    if (ticketCanvas) {
        const tTotal = (ticketData.urgent + ticketData.high + ticketData.medium + ticketData.low);
        const tData = tTotal > 0 
            ? [ticketData.urgent, ticketData.high, ticketData.medium, ticketData.low]
            : [0, 0, 1, 0]; // fallback clean demo if no tickets

        new Chart(ticketCanvas, {
            type: "doughnut",
            data: {
                labels: ["Urgent Priority", "High Priority", "Medium Priority", "Low Priority"],
                datasets: [{
                    data: tData,
                    backgroundColor: [
                        "#ef4444", // Red - Urgent
                        "#f97316", // Orange - High
                        "#eab308", // Yellow - Medium
                        "#10b981"  // Green - Low
                    ],
                    borderWidth: 3,
                    borderColor: "#ffffff",
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: "70%",
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: "#0f172a",
                        titleColor: "#ffffff",
                        bodyColor: "#94a3b8",
                        padding: 12,
                        cornerRadius: 8,
                        boxPadding: 6,
                        callbacks: {
                            label: function (context) {
                                return ` ${context.label}: ${context.raw} Tickets`;
                            }
                        }
                    }
                }
            }
        });
    }

    // =========================================================================
    // CHART 4: Software Investment & Spend by Category (Horizontal Bar Chart)
    // =========================================================================
    const swSpendCanvas = document.getElementById("softwareSpendChart");
    if (swSpendCanvas) {
        let swLabels = [];
        let swSpendValues = [];

        if (swSpendData && swSpendData.length > 0) {
            swSpendData.forEach(item => {
                swLabels.push(item.name || "Category");
                swSpendValues.push(item.spend || 0);
            });
        } else {
            swLabels = ["Productivity", "Security", "Dev Tools", "Cloud Services"];
            swSpendValues = [140000, 35000, 48000, 22000];
        }

        new Chart(swSpendCanvas, {
            type: "bar",
            data: {
                labels: swLabels,
                datasets: [{
                    label: "Annual Investment (₹)",
                    data: swSpendValues,
                    backgroundColor: [
                        "#7c3aed",
                        "#8b5cf6",
                        "#a78bfa",
                        "#c4b5fd"
                    ],
                    borderRadius: 6,
                    maxBarThickness: 34
                }]
            },
            options: {
                indexAxis: 'y', // Horizontal bars
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: "#f1f5f9" },
                        ticks: {
                            color: "#64748b",
                            callback: function (val) {
                                return "₹" + Number(val).toLocaleString('en-IN');
                            }
                        }
                    },
                    y: {
                        grid: { display: false },
                        ticks: {
                            font: { size: 12, weight: "600" },
                            color: "#334155"
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: "#0f172a",
                        titleColor: "#ffffff",
                        bodyColor: "#e2e8f0",
                        padding: 12,
                        cornerRadius: 8,
                        boxPadding: 6,
                        callbacks: {
                            label: function (context) {
                                return ` Investment: ₹${Number(context.raw).toLocaleString('en-IN')}`;
                            }
                        }
                    }
                }
            }
        });
    }
});
