document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('performanceChart');
    if (ctx) {
        fetch('api/chart-data.php')
            .then(response => response.json())
            .then(data => {
                new Chart(ctx, {
                    type: 'line',
                    data: data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        scales: {
                            y: {
                                ticks: {
                                    callback: function(value) {
                                        return value + '%';
                                    },
                                    color: '#ccc'
                                },
                                grid: {
                                    color: '#333'
                                }
                            },
                            x: {
                                ticks: {
                                    color: '#ccc'
                                },
                                grid: {
                                    color: '#333'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                labels: {
                                    color: '#ccc'
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.parsed.y !== null) {
                                            label += context.parsed.y + '%';
                                            
                                            // Add monthly gain if available
                                            const monthlyGain = context.dataset.monthlyGains[context.dataIndex];
                                            if (monthlyGain !== undefined && monthlyGain !== null) {
                                                const gainValue = parseFloat(monthlyGain);
                                                const sign = gainValue >= 0 ? '+' : '';
                                                label += ` (${sign}${gainValue.toFixed(2)}% ${context.dataset.isPrediction ? 'predicted' : 'actual'})`;
                                            }
                                        }
                                        return label;
                                    }
                                }
                            }
                        }
                    }
                });
            });
    // Entry Form Logic
    const entryForm = document.querySelector('form[method="POST"]');
    if (entryForm) {
        const inputModes = document.querySelectorAll('input[name="input_mode"]');
        const monthlyGroup = document.getElementById('monthly_input_group');
        const ytdGroup = document.getElementById('ytd_input_group');
        const gainInput = document.getElementById('gain_percent');
        const ytdInput = document.getElementById('ytd_gain');
        const userIdSelect = document.getElementById('user_id');
        const monthSelect = document.getElementById('month');
        const yearInput = document.getElementById('year');
        const typeSelect = document.getElementById('entry_type');

        const updateVisibility = () => {
            const mode = document.querySelector('input[name="input_mode"]:checked').value;
            if (mode === 'ytd') {
                ytdGroup.style.display = 'block';
                gainInput.readOnly = true;
                gainInput.classList.add('readonly-input');
                ytdInput.required = true;
            } else {
                ytdGroup.style.display = 'none';
                gainInput.readOnly = false;
                gainInput.classList.remove('readonly-input');
                ytdInput.required = false;
            }
        };

        inputModes.forEach(radio => {
            radio.addEventListener('change', updateVisibility);
        });

        const calculateMonthlyFromYTD = async () => {
            const userId = userIdSelect.value;
            const year = yearInput.value;
            const month = monthSelect.value;
            const type = typeSelect.value;
            const targetYTD = parseFloat(ytdInput.value);

            if (!userId || !year || !month || isNaN(targetYTD)) return;

            try {
                const response = await fetch(`api/get-previous-gains.php?user_id=${userId}&year=${year}&month=${month}&entry_type=${type}`);
                const data = await response.json();
                
                if (data.entries) {
                    let cumulativeMultiplier = 1.0;
                    data.entries.forEach(entry => {
                        cumulativeMultiplier *= (1 + (parseFloat(entry.gain_percent) / 100));
                    });

                    // Monthly = ((1 + YTD/100) / P_{n-1} - 1) * 100
                    const monthlyGain = (((1 + (targetYTD / 100)) / cumulativeMultiplier) - 1) * 100;
                    gainInput.value = monthlyGain.toFixed(2);
                }
            } catch (error) {
                console.error('Error fetching previous gains:', error);
            }
        };

        ytdInput.addEventListener('input', calculateMonthlyFromYTD);
        [userIdSelect, monthSelect, yearInput, typeSelect].forEach(el => {
            el.addEventListener('change', () => {
                if (document.querySelector('input[name="input_mode"]:checked').value === 'ytd') {
                    calculateMonthlyFromYTD();
                }
            });
        });
    }
});

