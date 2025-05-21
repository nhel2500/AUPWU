/**
 * AUPWU Management System
 * Charts JS file for data visualization
 */

/**
 * Creates a bar chart for committee membership
 * @param {string} canvasId - Canvas element ID
 * @param {Array} data - Array of objects with name and count properties
 */
function createCommitteeMembershipChart(canvasId, data) {
    const ctx = document.getElementById(canvasId).getContext('2d');
    
    // Extract labels and data values
    const labels = data.map(item => item.name);
    const values = data.map(item => item.member_count);
    
    // Create gradient background
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(128, 0, 0, 0.8)');
    gradient.addColorStop(1, 'rgba(128, 0, 0, 0.2)');
    
    // Create the chart
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Members',
                data: values,
                backgroundColor: gradient,
                borderColor: 'rgba(128, 0, 0, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                title: {
                    display: true,
                    text: 'Committee Membership Distribution',
                    font: {
                        size: 16
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `Members: ${context.formattedValue}`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Members'
                    },
                    ticks: {
                        precision: 0
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Committees'
                    },
                    ticks: {
                        autoSkip: false,
                        maxRotation: 90,
                        minRotation: 30
                    }
                }
            }
        }
    });
}

/**
 * Creates a doughnut chart for member status
 * @param {string} canvasId - Canvas element ID
 * @param {number} activeCount - Count of active members
 * @param {number} inactiveCount - Count of inactive members
 */
function createMemberStatusChart(canvasId, activeCount, inactiveCount) {
    const ctx = document.getElementById(canvasId).getContext('2d');
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Active', 'Inactive'],
            datasets: [{
                data: [activeCount, inactiveCount],
                backgroundColor: [
                    'rgba(40, 167, 69, 0.7)',   // Green for active
                    'rgba(220, 53, 69, 0.7)'    // Red for inactive
                ],
                borderColor: [
                    'rgba(40, 167, 69, 1)',
                    'rgba(220, 53, 69, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Member Status Distribution',
                    font: {
                        size: 16
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.formattedValue || '';
                            const total = context.dataset.data.reduce((acc, curr) => acc + curr, 0);
                            const percentage = Math.round((context.raw / total) * 100);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            },
            cutout: '60%'
        }
    });
}

/**
 * Creates a doughnut chart for UP status (in/out)
 * @param {string} canvasId - Canvas element ID
 * @param {number} inCount - Count of members in UP
 * @param {number} outCount - Count of members out of UP
 */
function createUPStatusChart(canvasId, inCount, outCount) {
    const ctx = document.getElementById(canvasId).getContext('2d');
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['In UP', 'Out of UP'],
            datasets: [{
                data: [inCount, outCount],
                backgroundColor: [
                    'rgba(23, 162, 184, 0.7)',   // Blue for in UP
                    'rgba(255, 193, 7, 0.7)'     // Yellow for out of UP
                ],
                borderColor: [
                    'rgba(23, 162, 184, 1)',
                    'rgba(255, 193, 7, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'UP Status Distribution',
                    font: {
                        size: 16
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.formattedValue || '';
                            const total = context.dataset.data.reduce((acc, curr) => acc + curr, 0);
                            const percentage = Math.round((context.raw / total) * 100);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            },
            cutout: '60%'
        }
    });
}

/**
 * Creates a horizontal bar chart for unit/college distribution
 * @param {string} canvasId - Canvas element ID
 * @param {Array} data - Array of objects with unit_college and count properties
 */
function createUnitDistributionChart(canvasId, data) {
    const ctx = document.getElementById(canvasId).getContext('2d');
    
    // Extract labels and data values
    const labels = data.map(item => item.unit_college);
    const values = data.map(item => item.count);
    
    // Create gradient background
    const gradient = ctx.createLinearGradient(0, 0, 400, 0);
    gradient.addColorStop(0, 'rgba(128, 0, 0, 0.8)');
    gradient.addColorStop(1, 'rgba(128, 0, 0, 0.2)');
    
    // Create the chart
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                axis: 'y',
                label: 'Members',
                data: values,
                backgroundColor: gradient,
                borderColor: 'rgba(128, 0, 0, 1)',
                borderWidth: 1
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                title: {
                    display: true,
                    text: 'Unit/College Distribution',
                    font: {
                        size: 16
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Members'
                    },
                    ticks: {
                        precision: 0
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Unit/College'
                    }
                }
            }
        }
    });
}

/**
 * Creates a line chart for member registrations over time
 * @param {string} canvasId - Canvas element ID
 * @param {Array} data - Array of objects with date and count properties
 */
function createMemberRegistrationChart(canvasId, data) {
    const ctx = document.getElementById(canvasId).getContext('2d');
    
    // Extract labels and data values
    const labels = data.map(item => item.date);
    const values = data.map(item => item.count);
    
    // Create the chart
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'New Registrations',
                data: values,
                backgroundColor: 'rgba(128, 0, 0, 0.1)',
                borderColor: 'rgba(128, 0, 0, 1)',
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Member Registrations Over Time',
                    font: {
                        size: 16
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Registrations'
                    },
                    ticks: {
                        precision: 0
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Date'
                    }
                }
            }
        }
    });
}

/**
 * Creates a bar chart for election results
 * @param {string} canvasId - Canvas element ID
 * @param {string} title - Chart title
 * @param {Array} data - Array of objects with name and vote_count properties
 */
function createElectionResultsChart(canvasId, title, data) {
    const ctx = document.getElementById(canvasId).getContext('2d');
    
    // Extract labels and data values
    const labels = data.map(item => item.name);
    const values = data.map(item => item.vote_count);
    
    // Generate background colors (shades of maroon)
    const backgroundColors = data.map((_, index) => {
        const opacity = 0.3 + (0.6 * (index / data.length));
        return `rgba(128, 0, 0, ${opacity})`;
    });
    
    // Create the chart
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Votes',
                data: values,
                backgroundColor: backgroundColors,
                borderColor: 'rgba(128, 0, 0, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                title: {
                    display: true,
                    text: title,
                    font: {
                        size: 16
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Votes'
                    },
                    ticks: {
                        precision: 0
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Candidates'
                    }
                }
            }
        }
    });
}

/**
 * Creates a chart based on the provided data and configuration
 * @param {string} canvasId - Canvas element ID
 * @param {string} chartType - Type of chart (bar, line, doughnut, etc.)
 * @param {Array} labels - Array of labels
 * @param {Array} data - Array of data values
 * @param {string} title - Chart title
 * @param {Object} options - Additional chart options
 */
function createCustomChart(canvasId, chartType, labels, data, title, options = {}) {
    const ctx = document.getElementById(canvasId).getContext('2d');
    
    // Default options
    const defaultOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: {
                display: true,
                text: title,
                font: {
                    size: 16
                }
            }
        }
    };
    
    // Merge default options with provided options
    const chartOptions = { ...defaultOptions, ...options };
    
    // Default dataset options
    const defaultDataset = {
        label: 'Data',
        data: data,
        backgroundColor: 'rgba(128, 0, 0, 0.7)',
        borderColor: 'rgba(128, 0, 0, 1)',
        borderWidth: 1
    };
    
    // Create the chart
    new Chart(ctx, {
        type: chartType,
        data: {
            labels: labels,
            datasets: [defaultDataset]
        },
        options: chartOptions
    });
}
