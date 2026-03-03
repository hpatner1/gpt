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

$pageTitle = 'Trading Dashboard | ' . APP_NAME;
$extraHeadScripts = ['https://cdn.jsdelivr.net/npm/chart.js', 'https://unpkg.com/lucide@latest'];
$toastKey = (string) ($_GET['toast'] ?? '');
$toastMap = [
    'login_success' => ['type' => 'success', 'message' => 'Barka da dawowa!'],
    'trade_saved' => ['type' => 'success', 'message' => 'An adana trade cikin nasara.'],
    'trade_updated' => ['type' => 'success', 'message' => 'An sabunta trade cikin nasara.'],
    'trade_deleted' => ['type' => 'warning', 'message' => 'An goge trade.'],
    'trade_delete_error' => ['type' => 'error', 'message' => 'Ba a iya goge trade ba.'],
];
require __DIR__ . '/includes/header.php';
?>

<?php if (isset($toastMap[$toastKey])): ?>
<div class="toast-container" id="customToastContainer">
    <div class="toast toast-<?php echo $toastMap[$toastKey]['type']; ?>" data-toast>
        <i data-lucide="<?php echo $toastMap[$toastKey]['type'] === 'success' ? 'check-circle' : ($toastMap[$toastKey]['type'] === 'error' ? 'x-circle' : 'alert-circle'); ?>"></i>
        <span><?php echo e($toastMap[$toastKey]['message']); ?></span>
        <button class="toast-close" onclick="this.parentElement.remove()"><i data-lucide="x"></i></button>
    </div>
</div>
<?php endif; ?>

<?php if ($consecutiveLossAlert): ?>
<div class="risk-alert-banner">
    <div class="container">
        <i data-lucide="alert-triangle"></i>
        <span><strong>Hattara:</strong> Anyi asara sau uku a jere. Rage risk kafin sabon entry.</span>
        <button onclick="this.parentElement.parentElement.remove()"><i data-lucide="x"></i></button>
    </div>
</div>
<?php endif; ?>

<nav class="main-nav">
    <div class="nav-brand">
        <i data-lucide="trending-up"></i>
        <span><?php echo APP_NAME; ?></span>
    </div>
    <div class="nav-links">
        <a href="#overview" class="nav-link active">Dashboard</a>
        <a href="#trades" class="nav-link">Journal</a>
        <a href="#analytics" class="nav-link">Analytics</a>
        <a href="#psychology" class="nav-link">Psychology</a>
        <a href="#market" class="nav-link">Market</a>
        <a href="#calculator" class="nav-link">Calculator</a>
    </div>
    <div class="nav-actions">
        <a href="logout.php" class="btn-icon"><i data-lucide="log-out"></i></a>
    </div>
</nav>

<main class="dashboard-container">
    <section id="overview" class="section-active">
        <div class="section-header">
            <h1>Dashboard</h1>
            <p class="text-muted">Barka da zuwa cibiyar sarrafa hadari.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-icon"><i data-lucide="wallet"></i></div>
                <div class="stat-content">
                    <h3>Account Balance</h3>
                    <p class="stat-value">$<?php echo e(number_format($totalRisked, 2)); ?></p>
                    <span class="stat-change <?php echo $accountGrowth >= 0 ? 'positive' : 'negative'; ?>"><?php echo e(number_format($accountGrowth, 2)); ?>%</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon blue"><i data-lucide="bar-chart-2"></i></div>
                <div class="stat-content">
                    <h3>Total Trades</h3>
                    <p class="stat-value"><?php echo e($totalTrades); ?></p>
                    <span class="stat-label"><?php echo e($wins); ?> wins</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green"><i data-lucide="target"></i></div>
                <div class="stat-content">
                    <h3>Win Rate</h3>
                    <p class="stat-value"><?php echo e(number_format($winRate, 1)); ?>%</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon red"><i data-lucide="trending-down"></i></div>
                <div class="stat-content">
                    <h3>Current Drawdown</h3>
                    <p class="stat-value" id="headerCurrentDrawdown">--</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple"><i data-lucide="coins"></i></div>
                <div class="stat-content">
                    <h3>Current Equity</h3>
                    <p class="stat-value" id="headerCurrentEquity">$<?php echo e(number_format($totalPnL, 2)); ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon blue"><i data-lucide="scale"></i></div>
                <div class="stat-content">
                    <h3>Average RR</h3>
                    <p class="stat-value"><?php echo e(number_format($avgRR, 2)); ?></p>
                </div>
            </div>
        </div>

        <div class="card chart-card">
            <div class="card-header">
                <h3><i data-lucide="activity"></i> Equity Curve</h3>
            </div>
            <div class="chart-container">
                <canvas id="equityCurveChart"></canvas>
                <div id="equityChartEmpty" class="chart-empty" hidden>
                    <i data-lucide="bar-chart"></i>
                    <p>Babu closed trade da za a nuna yanzu.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="analytics" class="section-hidden">
        <div class="section-header">
            <h1>Advanced Analytics</h1>
            <p class="text-muted">Cikakken bayanin performance dinka.</p>
        </div>

        <div class="metrics-grid">
            <div class="metric-card"><h4>Max Drawdown %</h4><p class="metric-value" id="metricMaxDrawdownPercent">--</p></div>
            <div class="metric-card"><h4>Current Drawdown %</h4><p class="metric-value" id="metricCurrentDrawdownPercent">--</p></div>
            <div class="metric-card"><h4>Current Equity</h4><p class="metric-value" id="metricCurrentEquity">--</p></div>
            <div class="metric-card"><h4>Recovery Factor</h4><p class="metric-value" id="metricRecoveryFactor">--</p></div>
            <div class="metric-card"><h4>Profit Factor</h4><p class="metric-value" id="metricProfitFactor">--</p></div>
            <div class="metric-card"><h4>Average Win</h4><p class="metric-value positive" id="metricAvgWin">--</p></div>
            <div class="metric-card"><h4>Average Loss</h4><p class="metric-value negative" id="metricAvgLoss">--</p></div>
            <div class="metric-card"><h4>Win/Loss Ratio</h4><p class="metric-value" id="metricWinLossRatio">--</p></div>
        </div>

        <div class="card risk-card">
            <div class="risk-header">
                <h3><i data-lucide="shield-alert"></i> Risk of Ruin</h3>
                <span class="risk-value" id="metricRiskOfRuin">--%</span>
            </div>
            <div class="risk-gauge"><div class="risk-gauge-bar" id="ruinGaugeBar"></div></div>
            <p class="risk-description">Yi la'akari da rage risk idan wannan ya yi yawa.</p>
        </div>

        <div class="split-grid">
            <div class="card">
                <div class="card-header"><h4>Strategy Performance</h4><span class="badge" id="bestStrategyLabel">--</span></div>
                <div class="table-responsive">
                    <table class="data-table" id="strategyPerformanceTable">
                        <thead><tr><th>Strategy</th><th>Trades</th><th>Win Rate</th><th>P&amp;L</th></tr></thead>
                        <tbody><tr><td colspan="4" class="text-center text-muted">No data yet</td></tr></tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h4>Session Performance</h4><span class="badge" id="bestSessionLabel">--</span></div>
                <div class="table-responsive">
                    <table class="data-table" id="sessionPerformanceTable">
                        <thead><tr><th>Session</th><th>Trades</th><th>Win Rate</th><th>P&amp;L</th></tr></thead>
                        <tbody><tr><td colspan="4" class="text-center text-muted">No data yet</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="charts-grid">
            <div class="card chart-card"><h4>RR Distribution</h4><canvas id="rrDistributionChart"></canvas></div>
            <div class="card chart-card"><h4>Profit Distribution</h4><canvas id="profitDistributionChart"></canvas></div>
            <div class="card chart-card"><h4>Win/Loss Streaks</h4><canvas id="streakChart"></canvas></div>
        </div>
    </section>

    <section id="psychology" class="section-hidden">
        <div class="section-header">
            <h1>Psychology Insights</h1>
            <p class="text-muted">Fahimci yanayin tunaninka kafin da bayan trade.</p>
        </div>

        <div class="split-grid">
            <div class="card">
                <div class="card-header"><h4><i data-lucide="brain"></i> Emotion Performance</h4></div>
                <div class="table-responsive">
                    <table class="data-table" id="emotionPerformanceTable">
                        <thead><tr><th>Emotion</th><th>Trades</th><th>Win Rate</th><th>Net Profit</th></tr></thead>
                        <tbody><tr><td colspan="4" class="text-center text-muted">No emotion data yet</td></tr></tbody>
                    </table>
                </div>
            </div>

            <div class="card insight-card">
                <h4><i data-lucide="lightbulb"></i> Emotional Bias Detection</h4>
                <div class="insight-item"><span class="insight-label">Most common before loss:</span><span class="insight-value" id="emotionBeforeLoss">--</span></div>
                <div class="insight-item"><span class="insight-label">Primary weakness:</span><span class="insight-value" id="emotionBiasPattern">--</span></div>
                <div class="insight-item"><span class="insight-label">Best performing:</span><span class="insight-value positive" id="bestEmotionLabel">--</span></div>
            </div>
        </div>
    </section>

    <section id="market" class="section-hidden">
        <div class="section-header">
            <h1>Live Market</h1>
            <p class="text-muted">Duba kasuwa kai tsaye.</p>
        </div>

        <div class="card">
            <form id="marketSearchForm" class="market-search">
                <div class="search-input-group">
                    <i data-lucide="search"></i>
                    <input type="text" id="marketCoinSymbol" placeholder="Enter symbol (e.g. BTC)" maxlength="10" required>
                </div>
                <button type="submit" class="btn-primary"><i data-lucide="refresh-cw"></i> Update</button>
            </form>

            <div class="market-grid" id="marketDataGrid">
                <div class="market-stat"><span class="market-label">Coin</span><span class="market-value" id="marketCoinName">--</span></div>
                <div class="market-stat highlight"><span class="market-label">Price</span><span class="market-value" id="marketLivePrice">--</span></div>
                <div class="market-stat"><span class="market-label">24h Change</span><span class="market-value" id="marketChange24h">--</span></div>
                <div class="market-stat"><span class="market-label">Market Cap</span><span class="market-value" id="marketCap">--</span></div>
                <div class="market-stat"><span class="market-label">Volume (24h)</span><span class="market-value" id="marketVolume">--</span></div>
            </div>

            <div class="market-actions">
                <button type="button" id="useLivePriceBtn" class="btn-secondary"><i data-lucide="arrow-down-circle"></i> Use This Price</button>
                <span class="text-muted" id="marketStatusText">Search a coin to load data</span>
            </div>
        </div>
    </section>

    <section id="calculator" class="section-hidden">
        <div class="section-header">
            <h1>Risk Calculator</h1>
            <p class="text-muted">Lissafin risk na spot trading.</p>
        </div>

        <div class="card calculator-card">
            <form id="calculatorForm" method="POST" action="calculate.php">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">

                <div class="form-grid">
                    <div class="form-group"><label>Account Balance ($)</label><input type="number" step="0.01" name="balance" class="form-control" placeholder="10000" required></div>
                    <div class="form-group"><label>Risk % per Trade</label><input type="number" step="0.01" name="risk_percent" class="form-control" placeholder="1.0" required></div>
                    <div class="form-group"><label>Entry Price</label><input type="number" step="0.00000001" id="calculatorEntryPrice" name="entry_price" class="form-control" required></div>
                    <div class="form-group"><label>Stop Loss</label><input type="number" step="0.00000001" name="stop_loss_price" class="form-control" required></div>
                    <div class="form-group"><label>Take Profit</label><input type="number" step="0.00000001" name="take_profit_price" class="form-control" required></div>
                    <div class="form-group"><label>Coin (Optional)</label><input type="text" name="coin_name" class="form-control" placeholder="BTC" maxlength="25"></div>
                </div>

                <button type="submit" class="btn-primary btn-block"><i data-lucide="calculator"></i> Calculate Position</button>
            </form>

            <div id="calcResult" class="calc-result"></div>
        </div>
    </section>

    <section id="trades" class="section-hidden">
        <div class="section-header">
            <h1>Trade Journal</h1>
            <p class="text-muted">Tarihin duk tradenka.</p>
        </div>

        <div class="card">
            <div class="journal-header">
                <form method="GET" class="search-box">
                    <i data-lucide="search"></i>
                    <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search by coin...">
                    <button type="submit" class="btn-sm">Search</button>
                </form>
                <div class="journal-actions">
                    <a href="save_trade.php" class="btn-primary"><i data-lucide="plus"></i> New Trade</a>
                    <a href="export_csv.php?csrf_token=<?php echo e(csrf_token()); ?>" class="btn-secondary"><i data-lucide="download"></i> Export</a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="data-table journal-table">
                    <thead>
                        <tr><th>Date</th><th>Coin</th><th>Entry</th><th>SL</th><th>TP1</th><th>TP2</th><th>Close %</th><th>R:R</th><th>Emotion</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!$trades): ?>
                            <tr>
                                <td colspan="11" class="empty-state">
                                    <i data-lucide="inbox"></i>
                                    <p>No trades found</p>
                                    <a href="save_trade.php" class="btn-sm">Add your first trade</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($trades as $trade): ?>
                                <tr>
                                    <td class="date-cell"><?php echo e(date('M d, Y', strtotime($trade['trade_date']))); ?></td>
                                    <td class="coin-cell"><span class="coin-badge"><?php echo e(strtoupper($trade['coin_name'])); ?></span></td>
                                    <td><?php echo e(number_format((float) $trade['entry_price'], 4)); ?></td>
                                    <td class="sl-cell"><?php echo e(number_format((float) $trade['stop_loss_price'], 4)); ?></td>
                                    <td><?php echo e(number_format((float) ($trade['tp1_price'] ?: $trade['take_profit_price']), 4)); ?></td>
                                    <td><?php echo e(number_format((float) ($trade['tp2_price'] ?: $trade['take_profit_price']), 4)); ?></td>
                                    <td><?php echo e(number_format((float) ($trade['partial_close_percent'] ?? 0), 1)); ?>%</td>
                                    <td><span class="rr-badge"><?php echo e(number_format((float) $trade['rr_ratio'], 2)); ?></span></td>
                                    <td>
                                        <?php if ($trade['pre_trade_emotion']): ?>
                                            <span class="emotion-tag"><?php echo e($trade['pre_trade_emotion']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $trade['status'])); ?>"><?php echo e($trade['status']); ?></span></td>
                                    <td class="actions-cell">
                                        <a href="edit_trade.php?id=<?php echo e((string) $trade['id']); ?>" class="btn-icon-sm" title="Edit"><i data-lucide="edit-2"></i></a>
                                        <a href="delete_trade.php?id=<?php echo e((string) $trade['id']); ?>&csrf_token=<?php echo e(csrf_token()); ?>" class="btn-icon-sm danger" data-confirm="Delete this trade?" data-confirm-title="Delete Trade" title="Delete"><i data-lucide="trash-2"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>#trades" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<div class="risk-intel-panel" id="riskIntelligencePanel">
    <div class="risk-intel-header" onclick="toggleRiskIntel()">
        <i data-lucide="sparkles"></i>
        <span>AI Insights</span>
        <button class="btn-icon-sm" type="button"><i data-lucide="chevron-down"></i></button>
    </div>
    <div class="risk-intel-content" id="riskIntelligenceList">
        <p class="text-muted">Loading suggestions...</p>
    </div>
</div>

<style>
.main-content { overflow: visible; }
:root {
    --bg-primary: #0f172a;
    --bg-secondary: #1e293b;
    --bg-tertiary: #334155;
    --text-primary: #f8fafc;
    --text-secondary: #94a3b8;
    --text-muted: #64748b;
    --accent-primary: #3b82f6;
    --accent-success: #10b981;
    --accent-warning: #f59e0b;
    --accent-danger: #ef4444;
    --accent-purple: #8b5cf6;
    --border-color: #334155;
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
    --radius: 12px;
    --transition: all 0.3s ease;
}

.main-nav { position: sticky; top: 0; height: 70px; background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(10px); border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; padding: 0 2rem; z-index: 90; margin-bottom: 1rem; }
.nav-brand { display: flex; align-items: center; gap: 0.75rem; font-size: 1.1rem; font-weight: 700; color: var(--accent-primary); }
.nav-links { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.nav-link { padding: 0.5rem 1rem; color: var(--text-secondary); text-decoration: none; border-radius: 8px; transition: var(--transition); font-weight: 500; }
.nav-link:hover, .nav-link.active { color: var(--text-primary); background: var(--bg-secondary); }
.nav-actions { display: flex; gap: 0.5rem; }

.dashboard-container { max-width: 1400px; margin: 0 auto; padding: 1rem 1rem 2rem; }
.section-header { margin-bottom: 1.5rem; }
.section-header h1 { font-size: 2rem; margin-bottom: 0.5rem; }
.text-muted { color: var(--text-muted); }
.text-center { text-align: center; }

.risk-alert-banner { background: linear-gradient(90deg, #ef4444 0%, #f59e0b 100%); color: white; z-index: 99; padding: 0.75rem 1rem; border-radius: 10px; margin-bottom: 1rem; }
.risk-alert-banner .container { max-width: 1400px; margin: 0 auto; display: flex; align-items: center; gap: 1rem; }
.risk-alert-banner button { margin-left: auto; background: none; border: none; color: white; cursor: pointer; }

.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.stat-card { background: var(--bg-secondary); border-radius: var(--radius); padding: 1.25rem; display: flex; align-items: center; gap: 1rem; border: 1px solid var(--border-color); transition: var(--transition); }
.stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
.stat-card.primary { background: linear-gradient(135deg, var(--accent-primary) 0%, #1d4ed8 100%); border: none; }
.stat-icon { width: 46px; height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: var(--bg-tertiary); }
.stat-card.primary .stat-icon { background: rgba(255,255,255,0.2); }
.stat-icon.blue { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
.stat-icon.green { background: rgba(16, 185, 129, 0.2); color: #34d399; }
.stat-icon.red { background: rgba(239, 68, 68, 0.2); color: #f87171; }
.stat-icon.purple { background: rgba(139, 92, 246, 0.2); color: #a78bfa; }
.stat-content h3 { font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.25rem; }
.stat-value { font-size: 1.45rem; font-weight: 700; color: var(--text-primary); }
.stat-card.primary .stat-content h3, .stat-card.primary .stat-value { color: white; }
.stat-change.positive { color: var(--accent-success); }
.stat-change.negative { color: var(--accent-danger); }
.stat-label { font-size: 0.8rem; color: var(--text-muted); }

.card { background: var(--bg-secondary); border-radius: var(--radius); border: 1px solid var(--border-color); overflow: hidden; margin-bottom: 1.25rem; }
.card-header { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; }

.btn-sm, .btn-primary, .btn-secondary { padding: 0.5rem 1rem; border-radius: 8px; border: none; font-weight: 500; cursor: pointer; transition: var(--transition); display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; font-size: 0.875rem; }
.btn-sm { background: var(--bg-tertiary); color: var(--text-primary); }
.btn-primary { background: var(--accent-primary); color: white; }
.btn-primary:hover { background: #2563eb; }
.btn-secondary { background: var(--bg-tertiary); color: var(--text-primary); border: 1px solid var(--border-color); }

.btn-icon, .btn-icon-sm { width: 40px; height: 40px; border-radius: 8px; border: none; background: var(--bg-tertiary); color: var(--text-primary); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: var(--transition); }
.btn-icon-sm { width: 32px; height: 32px; text-decoration: none; }
.btn-icon-sm.danger { color: var(--accent-danger); }
.btn-block { width: 100%; justify-content: center; padding: 0.875rem; font-size: 1rem; }

.chart-container { padding: 1.5rem; height: 360px; position: relative; }
.chart-empty { position: absolute; inset: 0; display: grid; place-items: center; text-align: center; color: var(--text-muted); }

.metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.metric-card { background: var(--bg-secondary); border-radius: var(--radius); padding: 1.25rem; border: 1px solid var(--border-color); }
.metric-card h4 { font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 0.5rem; font-weight: 500; }
.metric-value { font-size: 1.5rem; font-weight: 700; }
.metric-value.positive { color: var(--accent-success); }
.metric-value.negative { color: var(--accent-danger); }

.risk-card { padding: 1.25rem; background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(245, 158, 11, 0.1)); border: 1px solid rgba(239, 68, 68, 0.3); }
.risk-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
.risk-gauge { height: 8px; background: var(--bg-tertiary); border-radius: 6px; overflow: hidden; margin-bottom: .75rem; }
.risk-gauge-bar { display: block; height: 100%; width: 0; background: linear-gradient(90deg, var(--accent-success), var(--accent-warning), var(--accent-danger)); }

.split-grid, .charts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
.table-responsive { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th, .data-table td { padding: 0.9rem; border-bottom: 1px solid var(--border-color); font-size: 0.875rem; }
.data-table th { text-transform: uppercase; letter-spacing: .05em; color: var(--text-muted); font-size: .75rem; }
.data-table tr:hover { background: rgba(255,255,255,0.02); }

.badge { display: inline-flex; align-items: center; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: var(--bg-tertiary); color: var(--text-secondary); }
.coin-badge { font-weight: 700; color: var(--accent-primary); font-family: monospace; }
.rr-badge, .emotion-tag, .status-badge { padding: 0.25rem 0.5rem; border-radius: 6px; font-weight: 600; font-size: .75rem; }
.rr-badge { background: rgba(59,130,246,.2); color: #60a5fa; }
.emotion-tag { background: rgba(139,92,246,.2); color: #a78bfa; }
.status-win { background: rgba(16,185,129,.2); color: #34d399; }
.status-loss { background: rgba(239,68,68,.2); color: #f87171; }
.status-open, .status-pending { background: rgba(245,158,11,.2); color: #fbbf24; }
.empty-state { text-align: center; padding: 2rem !important; color: var(--text-muted); }

.journal-header { padding: 1.25rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; border-bottom: 1px solid var(--border-color); }
.search-box { display: flex; align-items: center; gap: 0.5rem; flex: 1; max-width: 400px; }
.search-box input { flex: 1; background: var(--bg-primary); border: 1px solid var(--border-color); padding: 0.5rem 1rem; border-radius: 8px; color: var(--text-primary); }
.journal-actions { display: flex; gap: .75rem; }
.actions-cell { display: flex; gap: 0.5rem; }
.sl-cell { color: var(--accent-danger); }

.market-search { padding: 1.25rem; display: flex; gap: 1rem; border-bottom: 1px solid var(--border-color); }
.search-input-group { flex: 1; display: flex; align-items: center; gap: 0.75rem; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 8px; padding: 0 1rem; }
.search-input-group input { flex: 1; background: none; border: none; padding: 0.875rem 0; color: var(--text-primary); outline: none; }
.market-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; padding: 1.25rem; }
.market-stat.highlight { grid-column: span 2; }
.market-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; }
.market-value { font-size: 1.2rem; font-weight: 700; }
.market-stat.highlight .market-value { font-size: 1.9rem; color: var(--accent-primary); }
.market-actions { padding: 1.25rem; border-top: 1px solid var(--border-color); display: flex; align-items: center; gap: 1rem; }

.calculator-card { max-width: 860px; }
.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; padding: 1.25rem; }
.form-group { display: flex; flex-direction: column; gap: 0.5rem; }
.form-control { background: var(--bg-primary); border: 1px solid var(--border-color); padding: 0.875rem; border-radius: 8px; color: var(--text-primary); }
.calc-result { padding: 1.25rem; background: var(--bg-primary); border-top: 1px solid var(--border-color); }

.pagination { display: flex; gap: .5rem; padding: 1.25rem; justify-content: center; }
.pagination a { width: 40px; height: 40px; display: grid; place-items: center; border-radius: 8px; background: var(--bg-tertiary); color: var(--text-secondary); text-decoration: none; }
.pagination a.active { background: var(--accent-primary); color: white; }

.risk-intel-panel { position: fixed; bottom: 1rem; right: 1rem; width: 320px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 12px; z-index: 200; }
.risk-intel-header { padding: 0.9rem 1rem; border-bottom: 1px solid var(--border-color); display: flex; gap: .7rem; cursor: pointer; background: linear-gradient(90deg, var(--accent-purple), var(--accent-primary)); color: #fff; border-radius: 12px 12px 0 0; }
.risk-intel-header span { flex: 1; font-weight: 600; }
.risk-intel-content { padding: 1rem; max-height: 260px; overflow-y: auto; }
.risk-intel-panel.collapsed .risk-intel-content { display: none; }

.toast-container { position: fixed; top: 90px; right: 1rem; z-index: 400; }
.toast { display: flex; align-items: center; gap: .65rem; padding: .9rem 1rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 10px; }
.toast-success { border-left: 4px solid var(--accent-success); }
.toast-error { border-left: 4px solid var(--accent-danger); }
.toast-warning { border-left: 4px solid var(--accent-warning); }
.toast-close { margin-left: auto; background: none; border: none; color: var(--text-muted); cursor: pointer; }

.section-hidden { display: none; }
.section-active { display: block; }

.insight-card { padding: 1.25rem; }
.insight-item { display: flex; justify-content: space-between; align-items: center; padding: .9rem 0; border-bottom: 1px solid var(--border-color); }
.insight-item:last-child { border-bottom: none; }

@media (max-width: 900px) {
    .nav-links { display: none; }
    .market-stat.highlight { grid-column: span 1; }
    .risk-intel-panel { left: 1rem; right: 1rem; width: auto; }
}
</style>

<script>
lucide.createIcons();

document.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', (e) => {
        e.preventDefault();
        const target = link.getAttribute('href').substring(1);

        document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
        link.classList.add('active');

        document.querySelectorAll('.dashboard-container > section').forEach(section => {
            section.classList.add('section-hidden');
            section.classList.remove('section-active');
        });

        const targetSection = document.getElementById(target);
        if (targetSection) {
            targetSection.classList.remove('section-hidden');
            targetSection.classList.add('section-active');
        }

        window.location.hash = target;
    });
});

window.addEventListener('load', () => {
    const hash = window.location.hash.substring(1) || 'overview';
    const navLink = document.querySelector(`.nav-link[href="#${hash}"]`);
    if (navLink) { navLink.click(); }
    setTimeout(() => {
        document.querySelectorAll('#customToastContainer .toast').forEach(toast => toast.remove());
    }, 5000);
});

function toggleRiskIntel() {
    const panel = document.getElementById('riskIntelligencePanel');
    panel.classList.toggle('collapsed');
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
