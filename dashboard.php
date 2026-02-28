<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$userId = current_user_id();
$search = trim((string) ($_GET['search'] ?? ''));
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
$consecutiveLossAlert = count($recentStatuses) === 3 && count(array_filter($recentStatuses, static fn($s) => $s === 'Loss')) === 3;

$pageTitle = 'Dashboard - ' . APP_NAME;
$extraHeadScripts = ['https://cdn.jsdelivr.net/npm/chart.js'];
$toastKey = (string) ($_GET['toast'] ?? '');
$toastMap = [
    'login_success' => ['type' => 'success', 'message' => 'Welcome back! Login successful.'],
    'trade_saved' => ['type' => 'success', 'message' => 'Trade saved successfully.'],
    'trade_updated' => ['type' => 'success', 'message' => 'Trade updated successfully.'],
    'trade_deleted' => ['type' => 'warning', 'message' => 'Trade deleted.'],
    'trade_delete_error' => ['type' => 'error', 'message' => 'Unable to delete trade.'],
];
require __DIR__ . '/includes/header.php';
?>
<?php if (isset($toastMap[$toastKey])): ?><div class="toast-bootstrap" data-toast-type="<?php echo e($toastMap[$toastKey]['type']); ?>" data-toast-message="<?php echo e($toastMap[$toastKey]['message']); ?>"></div><?php endif; ?>
<section class="tabs-nav">
    <a class="active" href="#overview">Dashboard</a>
    <a href="#trades">Journal</a>
    <a href="#analytics">Analytics</a>
    <a href="#psychology">Psychology</a>
    <a href="#market">Market</a>
</section>

<section id="overview">
    <h2>Dashboard</h2>
    <p class="muted">Welcome to your spot trading risk control center.</p>

    <?php if ($consecutiveLossAlert): ?>
        <div class="alert warning">Risk Alert: You have 3 consecutive losses. Consider reducing risk exposure.</div>
    <?php endif; ?>

    <div class="stats-grid">
        <article class="card stat-card"><h3>Account Balance</h3><p><?php echo e(number_format($totalRisked, 2)); ?></p></article>
        <article class="card stat-card"><h3>Total Trades</h3><p><?php echo e((string) $totalTrades); ?></p></article>
        <article class="card stat-card"><h3>Win Rate</h3><p><?php echo e(number_format($winRate, 2)); ?>%</p></article>
        <article class="card stat-card"><h3>Current Drawdown</h3><p id="headerCurrentDrawdown">--</p></article>
        <article class="card stat-card"><h3>Current Equity</h3><p id="headerCurrentEquity"><?php echo e(number_format($totalPnL, 2)); ?></p></article>
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
        <article class="metric-card"><h4>Current Drawdown %</h4><p id="metricCurrentDrawdownPercent">--</p></article>
        <article class="metric-card"><h4>Current Equity</h4><p id="metricCurrentEquity">--</p></article>
        <article class="metric-card"><h4>Peak Equity</h4><p id="metricPeakEquity">--</p></article>
        <article class="metric-card"><h4>Recovery Factor</h4><p id="metricRecoveryFactor">--</p></article>
        <article class="metric-card"><h4>Profit Factor</h4><p id="metricProfitFactor">--</p></article>
        <article class="metric-card"><h4>Average Win</h4><p id="metricAvgWin">--</p></article>
        <article class="metric-card"><h4>Average Loss</h4><p id="metricAvgLoss">--</p></article>
        <article class="metric-card"><h4>Win/Loss Ratio</h4><p id="metricWinLossRatio">--</p></article>
        <article class="metric-card"><h4>Total Net Profit</h4><p id="metricNetProfit">--</p></article>
        <article class="metric-card"><h4>Total Closed Trades</h4><p id="metricClosedTrades">--</p></article>
        <article class="metric-card"><h4>Expectancy / Trade</h4><p id="metricExpectancyPerTrade">--</p></article>
        <article class="metric-card"><h4>Expectancy % (Risk)</h4><p id="metricExpectancyPercent">--</p></article>
    </div>

    <div class="analytics-split-grid">
        <article class="metric-card">
            <h4>Strategy Performance</h4>
            <p class="muted tiny">Best Strategy: <span id="bestStrategyLabel">--</span></p>
            <div class="table-wrap compact-table-wrap">
                <table id="strategyPerformanceTable">
                    <thead><tr><th>Strategy</th><th>Trades</th><th>Win Rate</th><th>Net Profit</th></tr></thead>
                    <tbody><tr><td colspan="4">No strategy data yet.</td></tr></tbody>
                </table>
            </div>
        </article>

        <article class="metric-card">
            <h4>Session Performance</h4>
            <p class="muted tiny">Best Session: <span id="bestSessionLabel">--</span></p>
            <div class="table-wrap compact-table-wrap">
                <table id="sessionPerformanceTable">
                    <thead><tr><th>Session</th><th>Trades</th><th>Win Rate</th><th>Net Profit</th></tr></thead>
                    <tbody><tr><td colspan="4">No session data yet.</td></tr></tbody>
                </table>
            </div>
        </article>
    </div>

    <div class="risk-ruin-box">
        <h4>Risk of Ruin</h4>
        <p id="metricRiskOfRuin">--%</p>
        <div class="ruin-gauge"><span id="ruinGaugeBar"></span></div>
    </div>
    <p id="advancedMetricsEmpty" class="muted" hidden>Advanced analytics will appear after you close some trades.</p>
</section>


<section class="card panel" id="psychology">
    <h3>Psychology Insights</h3>
    <div class="analytics-split-grid">
        <article class="metric-card">
            <h4>Emotion Performance Breakdown</h4>
            <div class="table-wrap compact-table-wrap">
                <table id="emotionPerformanceTable">
                    <thead><tr><th>Emotion</th><th>Trades</th><th>Win Rate</th><th>Net Profit</th></tr></thead>
                    <tbody><tr><td colspan="4">No emotion data yet.</td></tr></tbody>
                </table>
            </div>
        </article>
        <article class="metric-card">
            <h4>Emotional Bias Detection</h4>
            <p class="tiny muted">Most common pre-trade emotion before loss: <span id="emotionBeforeLoss">--</span></p>
            <p class="tiny muted">Primary weakness pattern: <span id="emotionBiasPattern">--</span></p>
            <p class="tiny muted">Best performing emotion: <span id="bestEmotionLabel">--</span></p>
        </article>
    </div>
</section>

<section class="card panel">
    <h3>Performance Distribution Charts</h3>
    <div class="analytics-split-grid">
        <article class="metric-card"><h4>RR Distribution Histogram</h4><canvas id="rrDistributionChart"></canvas></article>
        <article class="metric-card"><h4>Profit Distribution</h4><canvas id="profitDistributionChart"></canvas></article>
        <article class="metric-card"><h4>Win/Loss Streaks</h4><canvas id="streakChart"></canvas></article>
    </div>
</section>

<section class="card panel" id="riskIntelligencePanel">
    <h3>Risk Intelligence Panel</h3>
    <div id="riskIntelligenceList" class="risk-intel-list">
        <p class="muted">Loading smart suggestions...</p>
    </div>
</section>

<section id="market" class="card panel market-panel">
    <div class="panel-head">
        <h3>Live Market Panel</h3>
    </div>
    <form id="marketSearchForm" class="search-form market-search-form">
        <input type="text" id="marketCoinSymbol" maxlength="10" placeholder="Enter coin symbol (e.g. BTC)" required>
        <button type="submit">Live Update</button>
    </form>

    <div class="market-grid" id="marketDataGrid">
        <article class="metric-card"><h4>Coin Name</h4><p id="marketCoinName">--</p></article>
        <article class="metric-card"><h4>Live Price</h4><p id="marketLivePrice">--</p></article>
        <article class="metric-card"><h4>24h %</h4><p id="marketChange24h">--</p></article>
        <article class="metric-card"><h4>Market Cap</h4><p id="marketCap">--</p></article>
        <article class="metric-card"><h4>24h Volume</h4><p id="marketVolume">--</p></article>
    </div>
    <div class="market-actions">
        <button type="button" id="useLivePriceBtn">Use Live Price</button>
        <p class="muted" id="marketStatusText">Search a coin to load live data.</p>
    </div>
</section>

<section id="calculator" class="card panel">
    <h3>Risk Calculator (Spot)</h3>
    <form id="calculatorForm" method="POST" action="calculate.php">
        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
        <div class="grid-2">
            <div><label>Account Balance</label><input type="number" step="0.01" name="balance" required></div>
            <div><label>Risk % per Trade</label><input type="number" step="0.01" name="risk_percent" required></div>
            <div><label>Entry Price</label><input id="calculatorEntryPrice" type="number" step="0.00000001" name="entry_price" required></div>
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
                <th>Date</th><th>Coin</th><th>Entry</th><th>SL</th><th>TP1</th><th>TP2</th><th>%@TP1</th><th>RR</th><th>Pre Emotion</th><th>Status</th><th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$trades): ?>
                <tr><td colspan="11">No trades found.</td></tr>
            <?php else: ?>
                <?php foreach ($trades as $trade): ?>
                    <tr>
                        <td><?php echo e($trade['trade_date']); ?></td>
                        <td><?php echo e($trade['coin_name']); ?></td>
                        <td><?php echo e($trade['entry_price']); ?></td>
                        <td><?php echo e($trade['stop_loss_price']); ?></td>
                        <td><?php echo e((string) ($trade['tp1_price'] ?: $trade['take_profit_price'])); ?></td>
                        <td><?php echo e((string) ($trade['tp2_price'] ?: $trade['take_profit_price'])); ?></td>
                        <td><?php echo e(number_format((float) ($trade['partial_close_percent'] ?? 0), 2)); ?>%</td>
                        <td><?php echo e(number_format((float) $trade['rr_ratio'], 2)); ?></td>
                        <td><?php echo e((string) ($trade['pre_trade_emotion'] ?: '-')); ?></td>
                        <td><span class="badge <?php echo strtolower(str_replace(' ', '-', $trade['status'])); ?>"><?php echo e($trade['status']); ?></span></td>
                        <td>
                            <a href="edit_trade.php?id=<?php echo e((string) $trade['id']); ?>">Edit</a>
                            |
                            <a href="delete_trade.php?id=<?php echo e((string) $trade['id']); ?>&csrf_token=<?php echo e(csrf_token()); ?>" class="action-delete" data-confirm="Delete this trade?" data-confirm-title="Delete Trade">Delete</a>
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
