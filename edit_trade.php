<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$tradeId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($tradeId <= 0) {
    redirect('dashboard.php#trades');
}

$stmt = $pdo->prepare('SELECT * FROM trades WHERE id = :id AND user_id = :user_id LIMIT 1');
$stmt->execute(['id' => $tradeId, 'user_id' => current_user_id()]);
$trade = $stmt->fetch();

if (!$trade) {
    redirect('dashboard.php#trades');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request token.';
    } else {
        $coin = strtoupper(trim($_POST['coin_name'] ?? ''));
        $balance = (float) ($_POST['balance'] ?? 0);
        $riskPercent = (float) ($_POST['risk_percent'] ?? 0);
        $entry = (float) ($_POST['entry_price'] ?? 0);
        $stopLoss = (float) ($_POST['stop_loss_price'] ?? 0);
        $takeProfit = (float) ($_POST['take_profit_price'] ?? 0);
        $status = $_POST['status'] ?? 'Running';
        $tradeDate = $_POST['trade_date'] ?? date('Y-m-d');

        $allowedStatus = ['Win', 'Loss', 'Running', 'Partially Closed'];
        if ($coin === '' || strlen($coin) > 25) {
            $error = 'Coin name is required and must be less than 25 characters.';
        } elseif ($balance <= 0 || $riskPercent <= 0 || $entry <= 0 || $stopLoss <= 0 || $takeProfit <= 0) {
            $error = 'Numeric fields must be greater than zero.';
        } elseif (!in_array($status, $allowedStatus, true)) {
            $error = 'Invalid trade status.';
        } else {
            $calculated = calculate_risk_metrics($balance, $riskPercent, $entry, $stopLoss, $takeProfit);

            $update = $pdo->prepare(
                'UPDATE trades SET
                    coin_name = :coin_name,
                    account_balance = :account_balance,
                    risk_percent = :risk_percent,
                    entry_price = :entry_price,
                    stop_loss_price = :stop_loss_price,
                    take_profit_price = :take_profit_price,
                    risk_amount = :risk_amount,
                    position_size = :position_size,
                    rr_ratio = :rr_ratio,
                    potential_profit = :potential_profit,
                    potential_loss = :potential_loss,
                    status = :status,
                    trade_date = :trade_date,
                    updated_at = NOW()
                 WHERE id = :id AND user_id = :user_id'
            );
            $update->execute([
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
                'trade_date' => $tradeDate,
                'id' => $tradeId,
                'user_id' => current_user_id(),
            ]);

            redirect('dashboard.php#trades');
        }
    }
}

$pageTitle = 'Edit Trade - ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>
<section class="card panel">
    <h2>Edit Trade</h2>
    <?php if ($error): ?><div class="alert error"><?php echo e($error); ?></div><?php endif; ?>

    <form method="POST">
        <input type="hidden" name="id" value="<?php echo e((string) $tradeId); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
        <div class="grid-2">
            <div><label>Coin Name</label><input name="coin_name" maxlength="25" value="<?php echo e($trade['coin_name']); ?>" required></div>
            <div><label>Account Balance</label><input type="number" step="0.01" name="balance" value="<?php echo e($trade['account_balance']); ?>" required></div>
            <div><label>Risk %</label><input type="number" step="0.01" name="risk_percent" value="<?php echo e($trade['risk_percent']); ?>" required></div>
            <div><label>Entry Price</label><input type="number" step="0.00000001" name="entry_price" value="<?php echo e($trade['entry_price']); ?>" required></div>
            <div><label>Stop Loss Price</label><input type="number" step="0.00000001" name="stop_loss_price" value="<?php echo e($trade['stop_loss_price']); ?>" required></div>
            <div><label>Take Profit Price</label><input type="number" step="0.00000001" name="take_profit_price" value="<?php echo e($trade['take_profit_price']); ?>" required></div>
            <div><label>Status</label>
                <select name="status">
                    <?php foreach (['Running', 'Partially Closed', 'Win', 'Loss'] as $status): ?>
                        <option value="<?php echo e($status); ?>" <?php echo $trade['status'] === $status ? 'selected' : ''; ?>><?php echo e($status); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Trade Date</label><input type="date" name="trade_date" value="<?php echo e($trade['trade_date']); ?>" required></div>
        </div>
        <button type="submit">Update Trade</button>
        <a class="btn-link" href="dashboard.php#trades">Cancel</a>
    </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
