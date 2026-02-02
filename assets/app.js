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
    }

    // Entry Form Logic - New unified input design
    const entryForm = document.querySelector('form[method="POST"]');
    if (entryForm) {
        const modeButtons = document.querySelectorAll('.mode-btn');
        const inputModeHidden = document.getElementById('input_mode');
        const gainInput = document.getElementById('gain_input');
        const gainLabel = document.getElementById('gain_label');
        const ytdHiddenInput = document.getElementById('ytd_gain');
        const calcResult = document.getElementById('ytd-calc-result');
        const prevYtdValue = document.getElementById('prev-ytd-value');
        const calcMonthlyValue = document.getElementById('calc-monthly-value');
        const userIdSelect = document.getElementById('user_id');
        const monthSelect = document.getElementById('month');
        const yearInput = document.getElementById('year');
        const typeSelect = document.getElementById('entry_type');
        
        let isCalculating = false;
        let currentMode = '1m';
        let storedMonthlyValue = gainInput ? gainInput.value : '';
        let storedYtdValue = '';

        const updateMode = (mode) => {
            currentMode = mode;
            inputModeHidden.value = mode;
            
            modeButtons.forEach(btn => {
                btn.classList.toggle('active', btn.dataset.mode === mode);
            });

            if (mode === 'ytd') {
                // Switching to YTD mode
                storedMonthlyValue = gainInput.value; // Save current monthly value
                gainLabel.textContent = 'Target YTD';
                gainInput.placeholder = '0.00';
                gainInput.value = storedYtdValue; // Restore YTD value if any
                calcResult.style.display = 'block';
                
                // Trigger calculation if we have a value
                if (storedYtdValue) {
                    calculateMonthlyFromYTD();
                } else {
                    prevYtdValue.textContent = '—';
                    calcMonthlyValue.textContent = '—';
                }
            } else {
                // Switching to Monthly mode
                storedYtdValue = gainInput.value; // Save current YTD value
                gainLabel.textContent = 'Monthly Gain';
                gainInput.placeholder = '0.00';
                gainInput.value = storedMonthlyValue; // Restore monthly value
                calcResult.style.display = 'none';
                ytdHiddenInput.value = '';
            }
        };

        modeButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                updateMode(btn.dataset.mode);
            });
        });

        const calculateMonthlyFromYTD = async () => {
            const userId = userIdSelect?.value;
            const year = yearInput?.value;
            const month = monthSelect?.value;
            const type = typeSelect?.value;
            const targetYTD = parseFloat(gainInput.value);

            if (!userId || !year || !month || isNaN(targetYTD)) {
                prevYtdValue.textContent = '—';
                calcMonthlyValue.textContent = '—';
                return;
            }

            isCalculating = true;
            calcMonthlyValue.textContent = '...';

            try {
                const response = await fetch(`api/get-previous-gains.php?user_id=${userId}&year=${year}&month=${month}&entry_type=${type}`);
                const data = await response.json();
                
                if (data.entries !== undefined) {
                    let cumulativeMultiplier = 1.0;
                    data.entries.forEach(entry => {
                        cumulativeMultiplier *= (1 + (parseFloat(entry.gain_percent) / 100));
                    });

                    // Monthly = ((1 + YTD/100) / P_{n-1} - 1) * 100
                    const monthlyGain = (((1 + (targetYTD / 100)) / cumulativeMultiplier) - 1) * 100;
                    const previousYTD = (cumulativeMultiplier - 1) * 100;
                    
                    // Update display
                    prevYtdValue.textContent = previousYTD.toFixed(2) + '%';
                    calcMonthlyValue.textContent = (monthlyGain >= 0 ? '+' : '') + monthlyGain.toFixed(2) + '%';
                    
                    // Store calculated monthly for form submission
                    storedMonthlyValue = monthlyGain.toFixed(2);
                    ytdHiddenInput.value = targetYTD;
                }
            } catch (error) {
                console.error('Error fetching previous gains:', error);
                prevYtdValue.textContent = 'Error';
                calcMonthlyValue.textContent = 'Error';
            } finally {
                isCalculating = false;
            }
        };

        // Handle input changes based on mode
        if (gainInput) {
            gainInput.addEventListener('input', () => {
                if (currentMode === 'ytd') {
                    calculateMonthlyFromYTD();
                }
            });
        }

        // Recalculate when changing user/month/year/type in YTD mode
        [userIdSelect, monthSelect, yearInput, typeSelect].forEach(el => {
            if (el) {
                el.addEventListener('change', () => {
                    if (currentMode === 'ytd') {
                        calculateMonthlyFromYTD();
                    }
                });
            }
        });
        
        // Handle form submission
        entryForm.addEventListener('submit', (e) => {
            if (isCalculating) {
                e.preventDefault();
                alert('Please wait for the calculation to complete.');
                return;
            }
            
            // In YTD mode, swap the value to the calculated monthly
            if (currentMode === 'ytd' && storedMonthlyValue) {
                gainInput.value = storedMonthlyValue;
            }
        });
    }
});

