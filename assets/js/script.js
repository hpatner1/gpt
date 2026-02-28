(function () {
    var form = document.getElementById('calculatorForm');
    var resultBox = document.getElementById('calcResult');

    if (!form || !resultBox) {
        return;
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        var formData = new FormData(form);

        fetch('calculate.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (payload) {
                if (payload.error) {
                    resultBox.style.display = 'block';
                    resultBox.innerHTML = '<strong>Error:</strong> ' + payload.error;
                    return;
                }

                var d = payload.data;
                resultBox.style.display = 'block';
                resultBox.innerHTML =
                    '<h4>Calculation Result</h4>' +
                    '<p>Risk Amount: <strong>' + d.risk_amount + '</strong></p>' +
                    '<p>Position Size: <strong>' + d.position_size + '</strong></p>' +
                    '<p>Risk/Reward Ratio: <strong>' + d.rr_ratio + '</strong></p>' +
                    '<p>Potential Profit: <strong>' + d.potential_profit + '</strong></p>' +
                    '<p>Potential Loss: <strong>' + d.potential_loss + '</strong></p>';
            })
            .catch(function () {
                resultBox.style.display = 'block';
                resultBox.innerHTML = '<strong>Error:</strong> Unable to calculate now.';
            });
    });
})();

(function () {
    var chartCanvas = document.getElementById('equityCurveChart');
    var emptyState = document.getElementById('equityChartEmpty');

    if (!chartCanvas || typeof Chart === 'undefined') {
        return;
    }

    fetch('equity_data.php', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('Unable to load equity data.');
            }

            return response.json();
        })
        .then(function (data) {
            if (!Array.isArray(data) || data.length === 0) {
                if (emptyState) {
                    emptyState.hidden = false;
                }
                return;
            }

            var labels = data.map(function (point) { return point.date; });
            var equityValues = data.map(function (point) { return point.equity; });
            var balanceValues = data.map(function (point) { return point.balance; });

            new Chart(chartCanvas, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Equity',
                            data: equityValues,
                            borderColor: '#36d399',
                            backgroundColor: 'rgba(54, 211, 153, 0.12)',
                            borderWidth: 2,
                            pointRadius: 2,
                            pointHoverRadius: 4,
                            tension: 0.35,
                            fill: true
                        },
                        {
                            label: 'Balance Baseline',
                            data: balanceValues,
                            borderColor: '#8f9bb5',
                            borderWidth: 2,
                            borderDash: [6, 6],
                            pointRadius: 0,
                            pointHoverRadius: 0,
                            tension: 0.35,
                            fill: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 850,
                        easing: 'easeOutQuart'
                    },
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            display: true,
                            labels: {
                                color: '#c9d4ee'
                            }
                        },
                        tooltip: {
                            enabled: true
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                color: 'rgba(152, 166, 192, 0.12)'
                            },
                            ticks: {
                                color: '#c9d4ee'
                            }
                        },
                        y: {
                            grid: {
                                color: 'rgba(152, 166, 192, 0.12)'
                            },
                            ticks: {
                                color: '#c9d4ee'
                            }
                        }
                    }
                }
            });
        })
        .catch(function () {
            if (emptyState) {
                emptyState.hidden = false;
                emptyState.textContent = 'Unable to load equity data right now.';
            }
        });
})();

(function () {
    var emptyState = document.getElementById('advancedMetricsEmpty');
    var grid = document.getElementById('advancedMetricsGrid');

    if (!grid) {
        return;
    }

    var metricEls = {
        maxDrawdownPercent: document.getElementById('metricMaxDrawdownPercent'),
        profitFactor: document.getElementById('metricProfitFactor'),
        avgWin: document.getElementById('metricAvgWin'),
        avgLoss: document.getElementById('metricAvgLoss'),
        winLossRatio: document.getElementById('metricWinLossRatio'),
        netProfit: document.getElementById('metricNetProfit'),
        closedTrades: document.getElementById('metricClosedTrades')
    };

    function formatMoney(value) {
        return Number(value).toFixed(2);
    }

    function formatRatio(value) {
        if (value === null || typeof value === 'undefined') {
            return '∞';
        }
        return Number(value).toFixed(2);
    }

    fetch('analytics_data.php', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('Unable to load analytics.');
            }
            return response.json();
        })
        .then(function (data) {
            if (!data || Number(data.closed_trades || 0) === 0) {
                if (emptyState) {
                    emptyState.hidden = false;
                }
                return;
            }

            metricEls.maxDrawdownPercent.textContent = Number(data.max_drawdown_percent || 0).toFixed(2) + '%';
            metricEls.profitFactor.textContent = formatRatio(data.profit_factor);
            metricEls.avgWin.textContent = formatMoney(data.avg_win || 0);
            metricEls.avgLoss.textContent = formatMoney(data.avg_loss || 0);
            metricEls.winLossRatio.textContent = formatRatio(data.win_loss_ratio);
            metricEls.netProfit.textContent = formatMoney(data.net_profit || 0);
            metricEls.closedTrades.textContent = String(data.closed_trades || 0);

            if (Number(data.net_profit || 0) >= 0) {
                metricEls.netProfit.classList.add('positive');
                metricEls.netProfit.classList.remove('negative');
            } else {
                metricEls.netProfit.classList.add('negative');
                metricEls.netProfit.classList.remove('positive');
            }
        })
        .catch(function () {
            if (emptyState) {
                emptyState.hidden = false;
                emptyState.textContent = 'Unable to load advanced analytics right now.';
            }
        });
})();
