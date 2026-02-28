<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$userId = current_user_id();
$selectedMonth = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}

$closedStmt = $pdo->prepare(
    'SELECT trade_date, status, potential_profit, potential_loss
     FROM trades
     WHERE user_id = :user_id
       AND status IN ("Win", "Loss")
     ORDER BY trade_date ASC, id ASC'
);
$closedStmt->execute(['user_id' => $userId]);
$closedTrades = $closedStmt->fetchAll();

$monthData = [];
$weekData = [];
foreach ($closedTrades as $trade) {
    $monthKey = date('Y-m', strtotime($trade['trade_date']));
    $weekKey = date('o-\\WW', strtotime($trade['trade_date']));
    $pnl = $trade['status'] === 'Win' ? (float) $trade['potential_profit'] : -(float) $trade['potential_loss'];
    $isWin = $trade['status'] === 'Win';

    foreach ([['key' => $monthKey, 'ref' => &$monthData], ['key' => $weekKey, 'ref' => &$weekData]] as $bucket) {
        $key = $bucket['key'];
        if (!isset($bucket['ref'][$key])) {
            $bucket['ref'][$key] = [
                'trades' => 0,
                'wins' => 0,
                'gross_profit' => 0.0,
                'gross_loss' => 0.0,
                'net_profit' => 0.0,
                'equity' => 0.0,
                'peak' => 0.0,
                'max_drawdown' => 0.0,
            ];
        }

        $bucket['ref'][$key]['trades']++;
        if ($isWin) {
            $bucket['ref'][$key]['wins']++;
            $bucket['ref'][$key]['gross_profit'] += (float) $trade['potential_profit'];
        } else {
            $bucket['ref'][$key]['gross_loss'] += (float) $trade['potential_loss'];
        }
        $bucket['ref'][$key]['net_profit'] += $pnl;
        $bucket['ref'][$key]['equity'] += $pnl;
        if ($bucket['ref'][$key]['equity'] > $bucket['ref'][$key]['peak']) {
            $bucket['ref'][$key]['peak'] = $bucket['ref'][$key]['equity'];
        }
        if ($bucket['ref'][$key]['peak'] > 0) {
            $drawdown = (($bucket['ref'][$key]['peak'] - $bucket['ref'][$key]['equity']) / $bucket['ref'][$key]['peak']) * 100;
            if ($drawdown > $bucket['ref'][$key]['max_drawdown']) {
                $bucket['ref'][$key]['max_drawdown'] = $drawdown;
            }
        }
    }
}

krsort($monthData);
krsort($weekData);

$calendarStmt = $pdo->prepare(
    'SELECT trade_date,
            SUM(CASE WHEN status = "Win" THEN potential_profit WHEN status = "Loss" THEN -potential_loss ELSE 0 END) AS pnl
     FROM trades
     WHERE user_id = :user_id
       AND DATE_FORMAT(trade_date, "%Y-%m") = :month
     GROUP BY trade_date'
);
$calendarStmt->execute(['user_id' => $userId, 'month' => $selectedMonth]);
$dailyPnLRows = $calendarStmt->fetchAll();
$dayPnL = [];
foreach ($dailyPnLRows as $row) {
    $dayPnL[$row['trade_date']] = (float) $row['pnl'];
}

$firstDayTimestamp = strtotime($selectedMonth . '-01');
$daysInMonth = (int) date('t', $firstDayTimestamp);
$startDayOfWeek = (int) date('N', $firstDayTimestamp); // 1 (Mon) - 7 (Sun)

$pageTitle = 'Reports - ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>
<section class="tabs-nav">
    <a href="dashboard.php">Dashboard</a>
    <a href="dashboard.php#trades">Journal</a>
    <a href="dashboard.php#analytics">Analytics</a>
    <a class="active" href="reports.php">Reports</a>
</section>

<section class="card panel">
    <h2>Equity Heatmap Calendar</h2>
    <form method="GET" class="search-form month-picker">
        <div>
            <label>Select Month</label>
            <input type="month" name="month" value="<?php echo e($selectedMonth); ?>">
        </div>
        <button type="submit">Load</button>
    </form>

    <div class="heatmap-grid" id="heatmapGrid">
        <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayLabel): ?>
            <div class="heatmap-head"><?php echo e($dayLabel); ?></div>
        <?php endforeach; ?>

        <?php for ($blank = 1; $blank < $startDayOfWeek; $blank++): ?>
            <div class="heatmap-day empty"></div>
        <?php endfor; ?>

        <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
            <?php $dateKey = date('Y-m-d', strtotime($selectedMonth . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT))); ?>
            <?php $pnl = $dayPnL[$dateKey] ?? null; ?>
            <?php $class = 'neutral';
            if ($pnl !== null && $pnl > 0) {
                $class = 'profit';
            } elseif ($pnl !== null && $pnl < 0) {
                $class = 'loss';
            }
            ?>
            <div class="heatmap-day <?php echo $class; ?>">
                <strong><?php echo e((string) $day); ?></strong>
                <span><?php echo $pnl === null ? 'No trade' : number_format($pnl, 2); ?></span>
            </div>
        <?php endfor; ?>
    </div>
</section>

<section class="card panel" id="analytics">
    <h2>Performance Breakdown</h2>

    <h3>Monthly Breakdown</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Month</th><th>Total Trades</th><th>Net Profit</th><th>Win Rate</th><th>Max Drawdown</th><th>Profit Factor</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$monthData): ?>
                <tr><td colspan="6">No closed trades yet.</td></tr>
            <?php else: ?>
                <?php foreach ($monthData as $month => $values): ?>
                    <?php $winRate = $values['trades'] > 0 ? ($values['wins'] / $values['trades']) * 100 : 0; ?>
                    <?php $profitFactor = $values['gross_loss'] > 0 ? ($values['gross_profit'] / $values['gross_loss']) : 0; ?>
                    <tr>
                        <td><?php echo e($month); ?></td>
                        <td><?php echo e((string) $values['trades']); ?></td>
                        <td class="<?php echo $values['net_profit'] >= 0 ? 'positive' : 'negative'; ?>"><?php echo e(number_format($values['net_profit'], 2)); ?></td>
                        <td><?php echo e(number_format($winRate, 2)); ?>%</td>
                        <td><?php echo e(number_format($values['max_drawdown'], 2)); ?>%</td>
                        <td><?php echo e(number_format($profitFactor, 2)); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <h3>Weekly Breakdown</h3>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Week</th><th>Total Trades</th><th>Net Profit</th><th>Win Rate</th><th>Max Drawdown</th><th>Profit Factor</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$weekData): ?>
                <tr><td colspan="6">No weekly history yet.</td></tr>
            <?php else: ?>
                <?php foreach ($weekData as $week => $values): ?>
                    <?php $winRate = $values['trades'] > 0 ? ($values['wins'] / $values['trades']) * 100 : 0; ?>
                    <?php $profitFactor = $values['gross_loss'] > 0 ? ($values['gross_profit'] / $values['gross_loss']) : 0; ?>
                    <tr>
                        <td><?php echo e($week); ?></td>
                        <td><?php echo e((string) $values['trades']); ?></td>
                        <td class="<?php echo $values['net_profit'] >= 0 ? 'positive' : 'negative'; ?>"><?php echo e(number_format($values['net_profit'], 2)); ?></td>
                        <td><?php echo e(number_format($winRate, 2)); ?>%</td>
                        <td><?php echo e(number_format($values['max_drawdown'], 2)); ?>%</td>
                        <td><?php echo e(number_format($profitFactor, 2)); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
