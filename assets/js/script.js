(function () {
    var toastContainer = document.getElementById('toastContainer');

    window.showToast = function (type, message) {
        if (!toastContainer || !message) { return; }
        var toast = document.createElement('div');
        toast.className = 'toast ' + (type || 'info');
        toast.innerHTML = '<span>' + message + '</span><button type="button" aria-label="Close">✕</button>';
        toastContainer.appendChild(toast);

        var remove = function () {
            if (toast.parentNode) { toast.parentNode.removeChild(toast); }
        };
        toast.querySelector('button').addEventListener('click', remove);
        setTimeout(remove, 4000);
    };

    document.querySelectorAll('.toast-bootstrap').forEach(function (el) {
        showToast(el.getAttribute('data-toast-type') || 'info', el.getAttribute('data-toast-message') || '');
    });

    document.querySelectorAll('.alert').forEach(function (el) {
        var type = el.classList.contains('error') ? 'error' : (el.classList.contains('warning') ? 'warning' : 'success');
        showToast(type, el.textContent.trim());
    });
})();

(function () {
    var shell = document.querySelector('.app-shell');
    var toggle = document.getElementById('sidebarToggle');
    if (shell && toggle) {
        toggle.addEventListener('click', function () { shell.classList.toggle('sidebar-open'); });
    }

    var btcEl = document.getElementById('liveBtcPrice');
    if (!btcEl) { return; }
    function loadBtc() {
        fetch('market_data.php?symbol=btc', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (p) {
                if (p && p.price) { btcEl.textContent = '$' + Number(p.price).toLocaleString(undefined, { maximumFractionDigits: 2 }); }
            })
            .catch(function () { btcEl.textContent = 'N/A'; showToast('error', 'API error while loading live BTC price.'); });
    }
    loadBtc();
    setInterval(loadBtc, 30000);
})();

(function () {
    document.querySelectorAll('form').forEach(function (form) {
        form.querySelectorAll('input, select, textarea').forEach(function (input) {
            var wrap = input.parentElement;
            if (wrap && wrap.querySelector('label')) {
                wrap.classList.add('form-field');
            }

            var validate = function () {
                var err = input.parentElement.querySelector('.field-error');
                if (!input.checkValidity()) {
                    input.classList.add('is-invalid');
                    if (!err) {
                        err = document.createElement('div');
                        err.className = 'field-error';
                        input.parentElement.appendChild(err);
                    }
                    if (input.validity.valueMissing) { err.textContent = 'This field is required.'; }
                    else if (input.validity.typeMismatch) { err.textContent = 'Please provide a valid value.'; }
                    else { err.textContent = input.validationMessage || 'Invalid value.'; }
                } else {
                    input.classList.remove('is-invalid');
                    if (err) { err.remove(); }
                }
                if (wrap) { wrap.classList.toggle('is-floating', !!input.value); }
            };

            ['input', 'blur', 'change'].forEach(function (ev) { input.addEventListener(ev, validate); });
            validate();
        });

        form.addEventListener('submit', function () {
            var btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.dataset.original = btn.dataset.original || btn.innerHTML;
                btn.innerHTML = '<span class="spinner"></span> Processing...';
            }
        });
    });
})();

(function () {
    var modal = document.getElementById('confirmModal');
    var msg = document.getElementById('confirmModalMessage');
    var title = document.getElementById('confirmModalTitle');
    var accept = document.getElementById('confirmModalAccept');
    if (!modal || !accept) { return; }

    var pendingHref = '';

    document.addEventListener('click', function (event) {
        var link = event.target.closest('[data-confirm]');
        if (!link) { return; }
        event.preventDefault();
        pendingHref = link.getAttribute('href') || '';
        msg.textContent = link.getAttribute('data-confirm') || 'Are you sure?';
        title.textContent = link.getAttribute('data-confirm-title') || 'Please Confirm';
        modal.hidden = false;
    });

    modal.querySelectorAll('[data-modal-close="cancel"]').forEach(function (el) {
        el.addEventListener('click', function () { modal.hidden = true; pendingHref = ''; });
    });
    accept.addEventListener('click', function () {
        if (pendingHref) { window.location.href = pendingHref; }
    });
})();

(function () {
    var form = document.getElementById('calculatorForm');
    var resultBox = document.getElementById('calcResult');
    if (!form || !resultBox) { return; }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        fetch('calculate.php', { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) { return response.json(); })
            .then(function (payload) {
                resultBox.style.display = 'block';
                if (payload.error) { showToast('error', 'Validation error: ' + payload.error); resultBox.innerHTML = '<strong>Error:</strong> ' + payload.error; return; }
                var d = payload.data;
                resultBox.innerHTML = '<h4>Calculation Result</h4><p>Risk Amount: <strong>' + d.risk_amount + '</strong></p><p>Position Size: <strong>' + d.position_size + '</strong></p><p>Risk/Reward Ratio: <strong>' + d.rr_ratio + '</strong></p><p>Potential Profit: <strong>' + d.potential_profit + '</strong></p><p>Potential Loss: <strong>' + d.potential_loss + '</strong></p>';
                showToast('success', 'Calculation completed.');
            })
            .catch(function () { showToast('error', 'API error while calculating risk.'); });
    });
})();

(function () {
    var chartCanvas = document.getElementById('equityCurveChart');
    var emptyState = document.getElementById('equityChartEmpty');
    var wrap = chartCanvas ? chartCanvas.parentElement : null;
    if (!chartCanvas || typeof Chart === 'undefined') { return; }

    var skeleton = document.createElement('div');
    skeleton.className = 'chart-skeleton';
    wrap.appendChild(skeleton);

    fetch('equity_data.php', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (response) { if (!response.ok) { throw new Error('Unable to load equity data.'); } return response.json(); })
        .then(function (data) {
            skeleton.remove();
            if (!Array.isArray(data) || data.length === 0) { if (emptyState) { emptyState.hidden = false; } return; }
            new Chart(chartCanvas, { type: 'line', data: { labels: data.map(function (i) { return i.date; }), datasets: [{ label: 'Equity', data: data.map(function (i) { return i.equity; }), borderColor: '#31d49f', backgroundColor: 'rgba(49,212,159,.15)', fill: true, tension: .35 }] }, options: { responsive: true, maintainAspectRatio: false } });
        })
        .catch(function () { skeleton.remove(); if (emptyState) { emptyState.hidden = false; emptyState.textContent = 'Unable to load chart right now.'; } showToast('error', 'API error while loading equity chart.'); });
})();

(function () {
    var currentEquity = document.getElementById('metricCurrentEquity');
    var currentDrawdown = document.getElementById('metricCurrentDrawdownPercent');
    var headerEquity = document.getElementById('headerCurrentEquity');
    var headerDraw = document.getElementById('headerCurrentDrawdown');
    fetch('analytics_data.php', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { if (!r.ok) { throw new Error(); } return r.json(); })
        .then(function (data) {
            if (!data) { return; }
            if (currentEquity) { currentEquity.textContent = Number(data.current_equity || 0).toFixed(2); }
            if (currentDrawdown) { currentDrawdown.textContent = Number(data.current_drawdown_percent || 0).toFixed(2) + '%'; }
            if (headerEquity) { headerEquity.textContent = Number(data.current_equity || 0).toFixed(2); }
            if (headerDraw) { headerDraw.textContent = Number(data.current_drawdown_percent || 0).toFixed(2) + '%'; }
        })
        .catch(function () { showToast('warning', 'Unable to load full analytics right now.'); });
})();

(function () {
    var container = document.getElementById('riskIntelligenceList');
    if (!container) { return; }
    fetch('risk_intelligence.php', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (response) { if (!response.ok) { throw new Error(); } return response.json(); })
        .then(function (data) {
            if (!data || !Array.isArray(data.suggestions)) { return; }
            container.innerHTML = data.suggestions.map(function (item) { return '<div class="risk-note ' + item.level + '">' + item.message + '</div>'; }).join('');
        })
        .catch(function () { container.innerHTML = '<p class="muted">Unable to load suggestions right now.</p>'; });
})();

(function () {
    var marketForm = document.getElementById('marketSearchForm');
    var symbolInput = document.getElementById('marketCoinSymbol');
    var statusText = document.getElementById('marketStatusText');
    var useLivePriceBtn = document.getElementById('useLivePriceBtn');
    var calculatorEntryInput = document.getElementById('calculatorEntryPrice');
    if (!marketForm || !symbolInput) { return; }

    var marketEls = { name: document.getElementById('marketCoinName'), price: document.getElementById('marketLivePrice'), change: document.getElementById('marketChange24h'), marketCap: document.getElementById('marketCap'), volume: document.getElementById('marketVolume') };
    var livePrice = null;
    var currentSymbol = '';

    function setStatus(message, className) { if (!statusText) { return; } statusText.textContent = message; statusText.className = 'muted ' + (className || ''); }

    function fetchMarket(symbol, statusMsg) {
        currentSymbol = symbol;
        if (statusMsg) { setStatus(statusMsg); }
        return fetch('market_data.php?symbol=' + encodeURIComponent(symbol), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) { return response.json().then(function (payload) { if (!response.ok) { throw new Error(payload.error || 'Unable to load market data.'); } return payload; }); })
            .then(function (payload) {
                marketEls.name.textContent = payload.name + ' (' + payload.symbol + ')';
                marketEls.price.textContent = '$' + Number(payload.price).toLocaleString(undefined, { maximumFractionDigits: 8 });
                marketEls.change.textContent = Number(payload.change_24h).toFixed(2) + '%';
                marketEls.marketCap.textContent = '$' + Number(payload.market_cap).toLocaleString();
                marketEls.volume.textContent = '$' + Number(payload.volume_24h).toLocaleString();
                livePrice = Number(payload.price);
                setStatus('Live data updated successfully.', 'positive');
                showToast('success', 'Market data refreshed.');
            })
            .catch(function (error) { setStatus(error.message || 'Unable to load market data.', 'negative'); showToast('error', 'API error: ' + (error.message || 'Unable to fetch market data.')); });
    }

    marketForm.addEventListener('submit', function (event) { event.preventDefault(); var symbol = symbolInput.value.trim().toLowerCase(); if (!symbol) { showToast('warning', 'Validation error: symbol is required.'); return; } fetchMarket(symbol, 'Loading...'); });
    setInterval(function () { if (currentSymbol) { fetchMarket(currentSymbol, 'Auto-refreshing...'); } }, 30000);

    if (useLivePriceBtn) {
        useLivePriceBtn.addEventListener('click', function () {
            if (!calculatorEntryInput || !livePrice) { showToast('warning', 'Validation error: load a valid live price first.'); return; }
            calculatorEntryInput.value = livePrice.toFixed(8);
            setStatus('Entry price updated from live data.', 'positive');
            showToast('info', 'Live price applied to calculator.');
        });
    }
})();
