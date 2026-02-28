<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$userId = current_user_id();

$tradesStmt = $pdo->prepare(
    'SELECT status, risk_amount, potential_profit, account_balance, risk_percent
     FROM trades
     WHERE user_id = :user_id
       AND status IN ("Win", "Loss")
     ORDER BY created_at ASC, id ASC'
);
$tradesStmt->execute(['user_id' => $userId]);
$trades = $tradesStmt->fetchAll();

$grossProfit = 0.0;
$grossLoss = 0.0;
$wins = 0;
$losses = 0;
$netProfit = 0.0;
$equity = 0.0;
$peakEquity = 0.0;
$maxDrawdownAmount = 0.0;
$totalRiskPercent = 0.0;
$latestAccountBalance = 0.0;

foreach ($trades as $trade) {
    $status = $trade['status'];
    $riskAmount = (float) $trade['risk_amount'];
    $rewardAmount = (float) $trade['potential_profit'];
    $latestAccountBalance = (float) $trade['account_balance'];
    $totalRiskPercent += (float) $trade['risk_percent'];

    if ($status === 'Win') {
        $grossProfit += $rewardAmount;
        $wins++;
        $netProfit += $rewardAmount;
        $equity += $rewardAmount;
    } else {
        $grossLoss += $riskAmount;
        $losses++;
        $netProfit -= $riskAmount;
        $equity -= $riskAmount;
    }

    if ($equity > $peakEquity) {
        $peakEquity = $equity;
    }

    if ($peakEquity > 0) {
        $drawdownAmount = $peakEquity - $equity;
        if ($drawdownAmount > $maxDrawdownAmount) {
            $maxDrawdownAmount = $drawdownAmount;
        }
    }
}

$closedTrades = $wins + $losses;
$maxDrawdownPercent = $peakEquity > 0 ? ($maxDrawdownAmount / $peakEquity) * 100 : 0.0;
$profitFactor = $grossLoss > 0 ? ($grossProfit / $grossLoss) : ($grossProfit > 0 ? INF : 0.0);
$avgWin = $wins > 0 ? ($grossProfit / $wins) : 0.0;
$avgLoss = $losses > 0 ? ($grossLoss / $losses) : 0.0;
$winLossRatio = $avgLoss > 0 ? ($avgWin / $avgLoss) : ($avgWin > 0 ? INF : 0.0);
$winRate = $closedTrades > 0 ? $wins / $closedTrades : 0.0;
$lossRate = 1 - $winRate;
$expectancyPerTrade = ($winRate * $avgWin) - ($lossRate * $avgLoss);
$expectancyPercentRisk = $avgLoss > 0 ? ($expectancyPerTrade / $avgLoss) * 100 : 0.0;
$averageRiskPercent = $closedTrades > 0 ? $totalRiskPercent / $closedTrades : 0.0;

$riskOfRuin = 100.0;
if ($closedTrades > 0 && $averageRiskPercent > 0 && $profitFactor > 0) {
    $riskFraction = $averageRiskPercent / 100;
    $accountSize = max(1.0, $latestAccountBalance);
    $riskAmountPerTrade = max(1.0, $accountSize * $riskFraction);
    $capitalUnits = max(1.0, $accountSize / $riskAmountPerTrade);
    $ratio = ($winRate * $profitFactor) > 0 ? ($lossRate / ($winRate * $profitFactor)) : 1.0;

    if ($ratio < 1) {
        $riskOfRuin = pow($ratio, $capitalUnits) * 100;
    }
}

$result = [
    'max_drawdown_percent' => round($maxDrawdownPercent, 2),
    'max_drawdown_amount' => round($maxDrawdownAmount, 2),
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
    'average_risk_percent' => round($averageRiskPercent, 2),
];

echo json_encode($result, JSON_UNESCAPED_UNICODE);
