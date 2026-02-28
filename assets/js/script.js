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
            var peakValues = data.map(function (point) { return point.peak; });
            var drawdownValues = data.map(function (point) {
                return Number(point.equity) < Number(point.peak) ? point.equity : null;
            });

            new Chart(chartCanvas, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Peak Equity',
                            data: peakValues,
                            borderColor: '#f3c969',
                            borderWidth: 1,
                            pointRadius: 0,
                            tension: 0.25,
                            fill: false
                        },
                        {
                            label: 'Drawdown Zone',
                            data: drawdownValues,
                            borderColor: 'rgba(255, 92, 124, 0.2)',
                            backgroundColor: 'rgba(255, 92, 124, 0.16)',
                            borderWidth: 1,
                            pointRadius: 0,
                            tension: 0.25,
                            fill: '-1',
                            spanGaps: true
                        },
                        {
                            label: 'Equity',
                            data: equityValues,
                            borderColor: '#36d399',
                            backgroundColor: 'rgba(54, 211, 153, 0.1)',
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
                        }
                    },
                    scales: {
                        x: { ticks: { color: '#c9d4ee' }, grid: { color: 'rgba(152, 166, 192, 0.12)' } },
                        y: { ticks: { color: '#c9d4ee' }, grid: { color: 'rgba(152, 166, 192, 0.12)' } }
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
    var ruinGaugeBar = document.getElementById('ruinGaugeBar');
    var metricRiskOfRuin = document.getElementById('metricRiskOfRuin');
    var strategyTableBody = document.querySelector('#strategyPerformanceTable tbody');
    var sessionTableBody = document.querySelector('#sessionPerformanceTable tbody');
    var bestStrategyLabel = document.getElementById('bestStrategyLabel');
    var bestSessionLabel = document.getElementById('bestSessionLabel');

    if (!grid) {
        return;
    }

    var metricEls = {
        maxDrawdownPercent: document.getElementById('metricMaxDrawdownPercent'),
        currentDrawdownPercent: document.getElementById('metricCurrentDrawdownPercent'),
        currentEquity: document.getElementById('metricCurrentEquity'),
        peakEquity: document.getElementById('metricPeakEquity'),
        recoveryFactor: document.getElementById('metricRecoveryFactor'),
        profitFactor: document.getElementById('metricProfitFactor'),
        avgWin: document.getElementById('metricAvgWin'),
        avgLoss: document.getElementById('metricAvgLoss'),
        winLossRatio: document.getElementById('metricWinLossRatio'),
        netProfit: document.getElementById('metricNetProfit'),
        closedTrades: document.getElementById('metricClosedTrades'),
        expectancyPerTrade: document.getElementById('metricExpectancyPerTrade'),
        expectancyPercent: document.getElementById('metricExpectancyPercent')
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

    function applyPositiveNegative(el, value) {
        if (!el) {
            return;
        }
        if (Number(value) >= 0) {
            el.classList.add('positive');
            el.classList.remove('negative');
        } else {
            el.classList.add('negative');
            el.classList.remove('positive');
        }
    }

    function fillPerformanceTable(body, rows, labelKey) {
        if (!body) {
            return;
        }

        if (!Array.isArray(rows) || rows.length === 0) {
            body.innerHTML = '<tr><td colspan="4">No data available yet.</td></tr>';
            return;
        }

        body.innerHTML = rows.map(function (row) {
            var netClass = Number(row.net_profit) >= 0 ? 'positive' : 'negative';
            return '<tr>' +
                '<td>' + row[labelKey] + '</td>' +
                '<td>' + row.trade_count + '</td>' +
                '<td>' + Number(row.win_rate_percent).toFixed(2) + '%</td>' +
                '<td class="' + netClass + '">' + formatMoney(row.net_profit) + '</td>' +
                '</tr>';
        }).join('');
    }

    fetch('analytics_data.php', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
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
            metricEls.currentDrawdownPercent.textContent = Number(data.current_drawdown_percent || 0).toFixed(2) + '%';
            metricEls.currentEquity.textContent = formatMoney(data.current_equity || 0);
            metricEls.peakEquity.textContent = formatMoney(data.peak_equity || 0);
            metricEls.recoveryFactor.textContent = data.recovery_factor === null ? '--' : formatRatio(data.recovery_factor);
            metricEls.profitFactor.textContent = formatRatio(data.profit_factor);
            metricEls.avgWin.textContent = formatMoney(data.avg_win || 0);
            metricEls.avgLoss.textContent = formatMoney(data.avg_loss || 0);
            metricEls.winLossRatio.textContent = formatRatio(data.win_loss_ratio);
            metricEls.netProfit.textContent = formatMoney(data.net_profit || 0);
            metricEls.closedTrades.textContent = String(data.closed_trades || 0);
            metricEls.expectancyPerTrade.textContent = formatMoney(data.expectancy_per_trade || 0);
            metricEls.expectancyPercent.textContent = Number(data.expectancy_percent_risk || 0).toFixed(2) + '%';
            metricRiskOfRuin.textContent = Number(data.risk_of_ruin_percent || 0).toFixed(2) + '%';

            fillPerformanceTable(strategyTableBody, data.strategy_performance, 'strategy');
            fillPerformanceTable(sessionTableBody, data.session_performance, 'session');
            if (bestStrategyLabel) {
                bestStrategyLabel.textContent = data.best_strategy || '--';
            }
            if (bestSessionLabel) {
                bestSessionLabel.textContent = data.best_session || '--';
            }

            applyPositiveNegative(metricEls.netProfit, data.net_profit || 0);
            applyPositiveNegative(metricEls.expectancyPerTrade, data.expectancy_per_trade || 0);
            applyPositiveNegative(metricEls.expectancyPercent, data.expectancy_percent_risk || 0);
            applyPositiveNegative(metricEls.recoveryFactor, data.recovery_factor || 0);

            if (ruinGaugeBar) {
                var ruin = Math.min(100, Math.max(0, Number(data.risk_of_ruin_percent || 0)));
                ruinGaugeBar.style.width = ruin + '%';
                ruinGaugeBar.classList.toggle('danger', ruin > 50);
            }
        })
        .catch(function () {
            if (emptyState) {
                emptyState.hidden = false;
                emptyState.textContent = 'Unable to load advanced analytics right now.';
            }
        });
})();

(function () {
    var container = document.getElementById('riskIntelligenceList');
    if (!container) {
        return;
    }

    fetch('risk_intelligence.php', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('Unable to load risk suggestions.');
            }
            return response.json();
        })
        .then(function (data) {
            if (!data || !Array.isArray(data.suggestions)) {
                container.innerHTML = '<p class="muted">No intelligence suggestions available.</p>';
                return;
            }

            container.innerHTML = data.suggestions.map(function (item) {
                return '<div class="risk-note ' + item.level + '">' + item.message + '</div>';
            }).join('');
        })
        .catch(function () {
            container.innerHTML = '<p class="muted">Unable to load suggestions right now.</p>';
        });
})();

(function () {
    var marketForm = document.getElementById('marketSearchForm');
    var symbolInput = document.getElementById('marketCoinSymbol');
    var statusText = document.getElementById('marketStatusText');
    var useLivePriceBtn = document.getElementById('useLivePriceBtn');
    var calculatorEntryInput = document.getElementById('calculatorEntryPrice');

    if (!marketForm || !symbolInput) {
        return;
    }

    var marketEls = {
        name: document.getElementById('marketCoinName'),
        price: document.getElementById('marketLivePrice'),
        change: document.getElementById('marketChange24h'),
        marketCap: document.getElementById('marketCap'),
        volume: document.getElementById('marketVolume')
    };

    var livePrice = null;

    function formatLarge(value) {
        return Number(value).toLocaleString(undefined, { maximumFractionDigits: 2 });
    }

    function setStatus(message, className) {
        if (!statusText) {
            return;
        }
        statusText.textContent = message;
        statusText.classList.remove('positive', 'negative');
        if (className) {
            statusText.classList.add(className);
        }
    }

    marketForm.addEventListener('submit', function (event) {
        event.preventDefault();
        var symbol = symbolInput.value.trim().toLowerCase();
        if (!symbol) {
            return;
        }

        setStatus('Loading live market data...');

        fetch('market_data.php?symbol=' + encodeURIComponent(symbol), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(function (response) {
                return response.json().then(function (payload) {
                    if (!response.ok) {
                        throw new Error(payload.error || 'Unable to load market data.');
                    }
                    return payload;
                });
            })
            .then(function (payload) {
                marketEls.name.textContent = payload.name + ' (' + payload.symbol + ')';
                marketEls.price.textContent = '$' + formatLarge(payload.price);
                marketEls.change.textContent = Number(payload.change_24h).toFixed(2) + '%';
                marketEls.marketCap.textContent = '$' + formatLarge(payload.market_cap);
                marketEls.volume.textContent = '$' + formatLarge(payload.volume_24h);

                if (Number(payload.change_24h) >= 0) {
                    marketEls.change.classList.add('positive');
                    marketEls.change.classList.remove('negative');
                } else {
                    marketEls.change.classList.add('negative');
                    marketEls.change.classList.remove('positive');
                }

                livePrice = Number(payload.price);
                setStatus('Live data updated successfully.', 'positive');
            })
            .catch(function (error) {
                setStatus(error.message || 'Unable to load market data.', 'negative');
            });
    });

    if (useLivePriceBtn) {
        useLivePriceBtn.addEventListener('click', function () {
            if (!calculatorEntryInput || !livePrice) {
                setStatus('Load a valid live price first.', 'negative');
                return;
            }
            calculatorEntryInput.value = livePrice.toFixed(8);
            setStatus('Entry price was filled from live market data.', 'positive');
            window.location.hash = '#calculator';
        });
    }
})();
