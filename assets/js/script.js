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
