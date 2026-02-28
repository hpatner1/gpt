<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$userId = current_user_id();
$search = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = ' WHERE user_id = :user_id ';
$params = ['user_id' => $userId];
if ($search !== '') {
    $where .= ' AND coin_name LIKE :search ';
    $params['search'] = '%' . $search . '%';
}

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM trades' . $where);
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));

$listSql = 'SELECT * FROM trades' . $where . ' ORDER BY trade_date DESC, id DESC LIMIT :limit OFFSET :offset';
$listStmt = $pdo->prepare($listSql);
foreach ($params as $key => $value) {
    $listStmt->bindValue(':' . $key, $value);
}
$listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStmt->execute();
$trades = $listStmt->fetchAll();

$statsStmt = $pdo->prepare(
    'SELECT
        COUNT(*) AS total_trades,
        SUM(CASE WHEN status = "Win" THEN 1 ELSE 0 END) AS wins,
        AVG(rr_ratio) AS avg_rr,
        SUM(CASE WHEN status = "Win" THEN potential_profit WHEN status = "Loss" THEN -potential_loss ELSE 0 END) AS total_pnl,
        SUM(potential_loss) AS total_risked
     FROM trades
     WHERE user_id = :user_id'
);
$statsStmt->execute(['user_id' => $userId]);
$stats = $statsStmt->fetch() ?: [];

$totalTrades = (int) ($stats['total_trades'] ?? 0);
$wins = (int) ($stats['wins'] ?? 0);
$winRate = $totalTrades > 0 ? ($wins / $totalTrades) * 100 : 0;
$avgRR = (float) ($stats['avg_rr'] ?? 0);
$totalPnL = (float) ($stats['total_pnl'] ?? 0);
$totalRisked = (float) ($stats['total_risked'] ?? 0);
$accountGrowth = $totalRisked > 0 ? ($totalPnL / $totalRisked) * 100 : 0;

$lossAlertStmt = $pdo->prepare('SELECT status FROM trades WHERE user_id = :user_id ORDER BY trade_date DESC, id DESC LIMIT 3');
$lossAlertStmt->execute(['user_id' => $userId]);
$recentStatuses = $lossAlertStmt->fetchAll(PDO::FETCH_COLUMN);
$consecutiveLossAlert = count($recentStatuses) === 3 && count(array_filter($recentStatuses, fn($s) => $s === 'Loss')) === 3;

$pageTitle = 'Dashboard - ' . APP_NAME;
$extraHeadScripts = ['https://cdn.jsdelivr.net/npm/chart.js'];
require __DIR__ . '/includes/header.php';
?>
<section class="tabs-nav">
    <a class="active" href="#overview">Dashboard</a>
    <a href="#trades">Journal</a>
    <a href="#analytics">Analytics</a>
    <a href="reports.php">Reports</a>
</section>

<section id="overview">
    <h2>Dashboard</h2>
    <p class="muted">Welcome to your spot trading risk control center.</p>

    <?php if ($consecutiveLossAlert): ?>
        <div class="alert warning">Risk Alert: You have 3 consecutive losses. Consider reducing risk exposure.</div>
    <?php endif; ?>

    <div class="stats-grid">
        <article class="card"><h3>Total Trades</h3><p><?php echo e((string) $totalTrades); ?></p></article>
        <article class="card"><h3>Win Rate</h3><p><?php echo e(number_format($winRate, 2)); ?>%</p></article>
        <article class="card"><h3>Average RR</h3><p><?php echo e(number_format($avgRR, 2)); ?></p></article>
        <article class="card"><h3>Total P/L</h3><p class="<?php echo $totalPnL >= 0 ? 'positive' : 'negative'; ?>"><?php echo e(number_format($totalPnL, 2)); ?></p></article>
        <article class="card"><h3>Account Growth</h3><p><?php echo e(number_format($accountGrowth, 2)); ?>%</p></article>
    </div>
</section>

<section class="card panel equity-card">
    <h3>Equity Curve</h3>
    <div class="equity-chart-wrap">
        <canvas id="equityCurveChart" aria-label="Equity Curve Chart"></canvas>
    </div>
    <p id="equityChartEmpty" class="muted equity-empty" hidden>No closed trades available to plot yet.</p>
</section>

<section class="card panel advanced-metrics-panel" id="analytics">
    <div class="panel-head">
        <h3>Advanced Performance Metrics</h3>
    </div>
    <div class="advanced-grid" id="advancedMetricsGrid">
        <article class="metric-card"><h4>Max Drawdown %</h4><p id="metricMaxDrawdownPercent">--</p></article>
        <article class="metric-card"><h4>Profit Factor</h4><p id="metricProfitFactor">--</p></article>
        <article class="metric-card"><h4>Average Win</h4><p id="metricAvgWin">--</p></article>
        <article class="metric-card"><h4>Average Loss</h4><p id="metricAvgLoss">--</p></article>
        <article class="metric-card"><h4>Win/Loss Ratio</h4><p id="metricWinLossRatio">--</p></article>
        <article class="metric-card"><h4>Total Net Profit</h4><p id="metricNetProfit">--</p></article>
        <article class="metric-card"><h4>Total Closed Trades</h4><p id="metricClosedTrades">--</p></article>
        <article class="metric-card"><h4>Expectancy / Trade</h4><p id="metricExpectancyPerTrade">--</p></article>
        <article class="metric-card"><h4>Expectancy % (Risk)</h4><p id="metricExpectancyPercent">--</p></article>
    </div>

    <div class="risk-ruin-box">
        <h4>Risk of Ruin</h4>
        <p id="metricRiskOfRuin">--%</p>
        <div class="ruin-gauge"><span id="ruinGaugeBar"></span></div>
    </div>
    <p id="advancedMetricsEmpty" class="muted" hidden>Advanced analytics will appear after you close some trades.</p>
</section>

<section class="card panel" id="riskIntelligencePanel">
    <h3>Risk Intelligence Panel</h3>
    <div id="riskIntelligenceList" class="risk-intel-list">
        <p class="muted">Loading smart suggestions...</p>
    </div>
</section>

<section id="calculator" class="card panel">
    <h3>Risk Calculator (Spot)</h3>
    <form id="calculatorForm" method="POST" action="calculate.php">
        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
        <div class="grid-2">
            <div><label>Account Balance</label><input type="number" step="0.01" name="balance" required></div>
            <div><label>Risk % per Trade</label><input type="number" step="0.01" name="risk_percent" required></div>
            <div><label>Entry Price</label><input type="number" step="0.00000001" name="entry_price" required></div>
            <div><label>Stop Loss Price</label><input type="number" step="0.00000001" name="stop_loss_price" required></div>
            <div><label>Take Profit Price</label><input type="number" step="0.00000001" name="take_profit_price" required></div>
            <div><label>Coin Name (optional)</label><input type="text" name="coin_name" maxlength="25"></div>
        </div>
        <button type="submit">Calculate</button>
    </form>
    <div id="calcResult" class="calc-result"></div>
</section>

<section id="trades" class="card panel">
    <div class="panel-head">
        <h3>Trade Journal</h3>
        <div class="panel-actions">
            <a class="btn-link" href="save_trade.php">+ Save Trade</a>
            <a class="btn-link" href="export_csv.php?csrf_token=<?php echo e(csrf_token()); ?>">Export Trade History</a>
        </div>
    </div>

    <form method="GET" class="search-form">
        <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search by coin name">
        <button type="submit">Search</button>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Date</th><th>Coin</th><th>Entry</th><th>SL</th><th>TP</th><th>RR</th><th>Status</th><th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$trades): ?>
                <tr><td colspan="8">No trades found.</td></tr>
            <?php else: ?>
                <?php foreach ($trades as $trade): ?>
                    <tr>
                        <td><?php echo e($trade['trade_date']); ?></td>
                        <td><?php echo e($trade['coin_name']); ?></td>
                        <td><?php echo e($trade['entry_price']); ?></td>
                        <td><?php echo e($trade['stop_loss_price']); ?></td>
                        <td><?php echo e($trade['take_profit_price']); ?></td>
                        <td><?php echo e(number_format((float) $trade['rr_ratio'], 2)); ?></td>
                        <td><span class="badge <?php echo strtolower($trade['status']); ?>"><?php echo e($trade['status']); ?></span></td>
                        <td>
                            <a href="edit_trade.php?id=<?php echo e((string) $trade['id']); ?>">Edit</a>
                            |
                            <a href="delete_trade.php?id=<?php echo e((string) $trade['id']); ?>&csrf_token=<?php echo e(csrf_token()); ?>" onclick="return confirm('Delete this trade?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a class="<?php echo $i === $page ? 'active' : ''; ?>" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>#trades"><?php echo $i; ?></a>
        <?php endfor; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
