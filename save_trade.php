<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request token.';
    } else {
        $coin = strtoupper(trim((string) ($_POST['coin_name'] ?? '')));
        $balance = (float) ($_POST['balance'] ?? 0);
        $riskPercent = (float) ($_POST['risk_percent'] ?? 0);
        $entry = (float) ($_POST['entry_price'] ?? 0);
        $stopLoss = (float) ($_POST['stop_loss_price'] ?? 0);
        $takeProfit = (float) ($_POST['take_profit_price'] ?? 0);
        $status = (string) ($_POST['status'] ?? 'Running');
        $tradeDate = (string) ($_POST['trade_date'] ?? date('Y-m-d'));
        $strategy = trim((string) ($_POST['strategy'] ?? ''));
        $setupType = trim((string) ($_POST['setup_type'] ?? ''));
        $session = trim((string) ($_POST['session'] ?? ''));

        $allowedStatus = ['Win', 'Loss', 'Running'];
        $allowedSetupTypes = ['Breakout', 'Pullback', 'Scalping', 'Swing', 'Trend Continuation'];
        $allowedSessions = ['Asia', 'London', 'New York'];

        if ($coin === '' || strlen($coin) > 25) {
            $error = 'Coin name is required and must be less than 25 characters.';
        } elseif ($balance <= 0 || $riskPercent <= 0 || $entry <= 0 || $stopLoss <= 0 || $takeProfit <= 0) {
            $error = 'Numeric fields must be greater than zero.';
        } elseif (!in_array($status, $allowedStatus, true)) {
            $error = 'Invalid trade status.';
        } elseif ($strategy !== '' && strlen($strategy) > 100) {
            $error = 'Strategy name must be 100 characters or less.';
        } elseif ($setupType !== '' && !in_array($setupType, $allowedSetupTypes, true)) {
            $error = 'Invalid setup type selected.';
        } elseif (!in_array($session, $allowedSessions, true)) {
            $error = 'Invalid session selected.';
        } else {
            $tradeDateObj = DateTime::createFromFormat('Y-m-d', $tradeDate);
            $validDate = $tradeDateObj && $tradeDateObj->format('Y-m-d') === $tradeDate;

            if (!$validDate) {
                $error = 'Invalid trade date format.';
            } else {
                $calculated = calculate_risk_metrics($balance, $riskPercent, $entry, $stopLoss, $takeProfit);

                $stmt = $pdo->prepare(
                    'INSERT INTO trades (
                        user_id, coin_name, account_balance, risk_percent, entry_price, stop_loss_price, take_profit_price,
                        risk_amount, position_size, rr_ratio, potential_profit, potential_loss, status, strategy, setup_type,
                        session, trade_date, created_at, updated_at
                    ) VALUES (
                        :user_id, :coin_name, :account_balance, :risk_percent, :entry_price, :stop_loss_price, :take_profit_price,
                        :risk_amount, :position_size, :rr_ratio, :potential_profit, :potential_loss, :status, :strategy, :setup_type,
                        :session, :trade_date, NOW(), NOW()
                    )'
                );
                $stmt->execute([
                    'user_id' => current_user_id(),
                    'coin_name' => $coin,
                    'account_balance' => $balance,
                    'risk_percent' => $riskPercent,
                    'entry_price' => $entry,
                    'stop_loss_price' => $stopLoss,
                    'take_profit_price' => $takeProfit,
                    'risk_amount' => $calculated['risk_amount'],
                    'position_size' => $calculated['position_size'],
                    'rr_ratio' => $calculated['rr_ratio'],
                    'potential_profit' => $calculated['potential_profit'],
                    'potential_loss' => $calculated['potential_loss'],
                    'status' => $status,
                    'strategy' => $strategy,
                    'setup_type' => $setupType,
                    'session' => $session,
                    'trade_date' => $tradeDate,
                ]);

                redirect('dashboard.php#trades');
            }
        }
    }
}

$pageTitle = 'Save Trade - ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>
<section class="card panel">
    <h2>Save New Trade</h2>
    <?php if ($error): ?><div class="alert error"><?php echo e($error); ?></div><?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
        <div class="grid-2">
            <div><label>Coin Name</label><input name="coin_name" maxlength="25" required></div>
            <div><label>Account Balance</label><input type="number" step="0.01" name="balance" required></div>
            <div><label>Risk %</label><input type="number" step="0.01" name="risk_percent" required></div>
            <div><label>Entry Price</label><input type="number" step="0.00000001" name="entry_price" required></div>
            <div><label>Stop Loss Price</label><input type="number" step="0.00000001" name="stop_loss_price" required></div>
            <div><label>Take Profit Price</label><input type="number" step="0.00000001" name="take_profit_price" required></div>
            <div><label>Strategy Name</label><input type="text" name="strategy" maxlength="100" placeholder="e.g. Momentum Continuation"></div>
            <div>
                <label>Setup Type</label>
                <select name="setup_type">
                    <option value="">Select setup type</option>
                    <option>Breakout</option>
                    <option>Pullback</option>
                    <option>Scalping</option>
                    <option>Swing</option>
                    <option>Trend Continuation</option>
                </select>
            </div>
            <div>
                <label>Session</label>
                <select name="session" required>
                    <option value="Asia">Asia</option>
                    <option value="London">London</option>
                    <option value="New York">New York</option>
                </select>
            </div>
            <div><label>Status</label><select name="status"><option>Running</option><option>Win</option><option>Loss</option></select></div>
            <div><label>Trade Date</label><input type="date" name="trade_date" value="<?php echo date('Y-m-d'); ?>" required></div>
        </div>
        <button type="submit">Save Trade</button>
        <a class="btn-link" href="dashboard.php#trades">Cancel</a>
    </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
