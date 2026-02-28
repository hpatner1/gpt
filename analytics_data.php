<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$userId = current_user_id();

$totalsStmt = $pdo->prepare(
    'SELECT
        COUNT(*) AS closed_trades,
        SUM(CASE WHEN status = "Win" THEN 1 ELSE 0 END) AS wins,
        SUM(CASE WHEN status = "Loss" THEN 1 ELSE 0 END) AS losses,
        SUM(CASE WHEN status = "Win" THEN potential_profit ELSE 0 END) AS gross_profit,
        SUM(CASE WHEN status = "Loss" THEN risk_amount ELSE 0 END) AS gross_loss,
        SUM(CASE WHEN status = "Win" THEN potential_profit WHEN status = "Loss" THEN -risk_amount ELSE 0 END) AS net_profit,
        AVG(CASE WHEN status IN ("Win", "Loss") THEN risk_percent END) AS avg_risk_percent
     FROM trades
     WHERE user_id = :user_id
       AND status IN ("Win", "Loss")'
);
$totalsStmt->execute(['user_id' => $userId]);
$totals = $totalsStmt->fetch() ?: [];

$equityStmt = $pdo->prepare(
    'SELECT account_balance, status, risk_amount, potential_profit
     FROM trades
     WHERE user_id = :user_id
       AND status IN ("Win", "Loss")
     ORDER BY created_at ASC, id ASC'
);
$equityStmt->execute(['user_id' => $userId]);
$equityRows = $equityStmt->fetchAll();

$baselineBalance = isset($equityRows[0]['account_balance']) ? (float) $equityRows[0]['account_balance'] : 0.0;
$currentEquity = $baselineBalance;
$peakEquity = $baselineBalance;
$maxDrawdownAmount = 0.0;

foreach ($equityRows as $row) {
    if ($row['status'] === 'Win') {
        $currentEquity += (float) $row['potential_profit'];
    } else {
        $currentEquity -= (float) $row['risk_amount'];
    }

    if ($currentEquity > $peakEquity) {
        $peakEquity = $currentEquity;
    }

    $drawdownAmount = max(0.0, $peakEquity - $currentEquity);
    if ($drawdownAmount > $maxDrawdownAmount) {
        $maxDrawdownAmount = $drawdownAmount;
    }
}

$currentDrawdownPercent = $peakEquity > 0 ? (($peakEquity - $currentEquity) / $peakEquity) * 100 : 0.0;
$maxDrawdownPercent = $peakEquity > 0 ? ($maxDrawdownAmount / $peakEquity) * 100 : 0.0;

$closedTrades = (int) ($totals['closed_trades'] ?? 0);
$wins = (int) ($totals['wins'] ?? 0);
$losses = (int) ($totals['losses'] ?? 0);
$grossProfit = (float) ($totals['gross_profit'] ?? 0);
$grossLoss = (float) ($totals['gross_loss'] ?? 0);
$netProfit = (float) ($totals['net_profit'] ?? 0);
$avgRiskPercent = (float) ($totals['avg_risk_percent'] ?? 0);

$profitFactor = $grossLoss > 0 ? ($grossProfit / $grossLoss) : ($grossProfit > 0 ? INF : 0.0);
$avgWin = $wins > 0 ? ($grossProfit / $wins) : 0.0;
$avgLoss = $losses > 0 ? ($grossLoss / $losses) : 0.0;
$winLossRatio = $avgLoss > 0 ? ($avgWin / $avgLoss) : ($avgWin > 0 ? INF : 0.0);
$winRate = $closedTrades > 0 ? $wins / $closedTrades : 0.0;
$lossRate = 1 - $winRate;
$expectancyPerTrade = ($winRate * $avgWin) - ($lossRate * $avgLoss);
$expectancyPercentRisk = $avgLoss > 0 ? ($expectancyPerTrade / $avgLoss) * 100 : 0.0;
$recoveryFactor = $maxDrawdownAmount > 0 ? ($netProfit / $maxDrawdownAmount) : null;

$riskOfRuin = 100.0;
if ($closedTrades > 0 && $avgRiskPercent > 0 && $profitFactor > 0 && $baselineBalance > 0) {
    $riskFraction = $avgRiskPercent / 100;
    $riskAmountPerTrade = max(1.0, $baselineBalance * $riskFraction);
    $capitalUnits = max(1.0, $baselineBalance / $riskAmountPerTrade);
    $ratio = ($winRate * $profitFactor) > 0 ? ($lossRate / ($winRate * $profitFactor)) : 1.0;

    if ($ratio < 1) {
        $riskOfRuin = pow($ratio, $capitalUnits) * 100;
    }
}

$strategyStmt = $pdo->prepare(
    'SELECT
        COALESCE(NULLIF(strategy, ""), "Unspecified") AS strategy_name,
        COUNT(*) AS trade_count,
        SUM(CASE WHEN status = "Win" THEN 1 ELSE 0 END) AS wins,
        SUM(CASE WHEN status = "Win" THEN potential_profit WHEN status = "Loss" THEN -risk_amount ELSE 0 END) AS net_profit
     FROM trades
     WHERE user_id = :user_id
       AND status IN ("Win", "Loss")
     GROUP BY COALESCE(NULLIF(strategy, ""), "Unspecified")
     ORDER BY net_profit DESC, trade_count DESC'
);
$strategyStmt->execute(['user_id' => $userId]);
$strategyRows = $strategyStmt->fetchAll();

$strategyPerformance = array_map(static function (array $row): array {
    $count = (int) $row['trade_count'];
    $winsCount = (int) $row['wins'];

    return [
        'strategy' => $row['strategy_name'],
        'trade_count' => $count,
        'win_rate_percent' => $count > 0 ? round(($winsCount / $count) * 100, 2) : 0.0,
        'net_profit' => round((float) $row['net_profit'], 2),
    ];
}, $strategyRows);

$sessionStmt = $pdo->prepare(
    'SELECT
        session,
        COUNT(*) AS trade_count,
        SUM(CASE WHEN status = "Win" THEN 1 ELSE 0 END) AS wins,
        SUM(CASE WHEN status = "Win" THEN potential_profit WHEN status = "Loss" THEN -risk_amount ELSE 0 END) AS net_profit
     FROM trades
     WHERE user_id = :user_id
       AND status IN ("Win", "Loss")
     GROUP BY session
     ORDER BY net_profit DESC, trade_count DESC'
);
$sessionStmt->execute(['user_id' => $userId]);
$sessionRows = $sessionStmt->fetchAll();

$sessionPerformance = array_map(static function (array $row): array {
    $count = (int) $row['trade_count'];
    $winsCount = (int) $row['wins'];

    return [
        'session' => $row['session'],
        'trade_count' => $count,
        'win_rate_percent' => $count > 0 ? round(($winsCount / $count) * 100, 2) : 0.0,
        'net_profit' => round((float) $row['net_profit'], 2),
    ];
}, $sessionRows);

$result = [
    'max_drawdown_percent' => round($maxDrawdownPercent, 2),
    'max_drawdown_amount' => round($maxDrawdownAmount, 2),
    'current_drawdown_percent' => round(max(0, $currentDrawdownPercent), 2),
    'current_equity' => round($currentEquity, 2),
    'peak_equity' => round($peakEquity, 2),
    'recovery_factor' => $recoveryFactor !== null ? round($recoveryFactor, 2) : null,
    'profit_factor' => is_finite($profitFactor) ? round($profitFactor, 2) : null,
    'avg_win' => round($avgWin, 2),
    'avg_loss' => round($avgLoss, 2),
    'win_loss_ratio' => is_finite($winLossRatio) ? round($winLossRatio, 2) : null,
    'net_profit' => round($netProfit, 2),
    'closed_trades' => $closedTrades,
    'expectancy_per_trade' => round($expectancyPerTrade, 2),
    'expectancy_percent_risk' => round($expectancyPercentRisk, 2),
    'risk_of_ruin_percent' => round(max(0, min(100, $riskOfRuin)), 2),
    'win_rate_percent' => round($winRate * 100, 2),
    'average_risk_percent' => round($avgRiskPercent, 2),
    'strategy_performance' => $strategyPerformance,
    'best_strategy' => $strategyPerformance[0]['strategy'] ?? null,
    'session_performance' => $sessionPerformance,
    'best_session' => $sessionPerformance[0]['session'] ?? null,
];

echo json_encode($result, JSON_UNESCAPED_UNICODE);
