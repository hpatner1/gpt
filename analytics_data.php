<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$userId = current_user_id();
if ($userId === null) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tradesStmt = $pdo->prepare(
    'SELECT status, risk_amount, potential_profit
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

foreach ($trades as $trade) {
    $status = $trade['status'];
    $riskAmount = (float) $trade['risk_amount'];
    $rewardAmount = (float) $trade['potential_profit'];

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

$maxDrawdownPercent = $peakEquity > 0 ? ($maxDrawdownAmount / $peakEquity) * 100 : 0.0;
$profitFactor = $grossLoss > 0 ? ($grossProfit / $grossLoss) : ($grossProfit > 0 ? INF : 0.0);
$avgWin = $wins > 0 ? ($grossProfit / $wins) : 0.0;
$avgLoss = $losses > 0 ? ($grossLoss / $losses) : 0.0;
$winLossRatio = $avgLoss > 0 ? ($avgWin / $avgLoss) : ($avgWin > 0 ? INF : 0.0);

$result = [
    'max_drawdown_percent' => round($maxDrawdownPercent, 2),
    'max_drawdown_amount' => round($maxDrawdownAmount, 2),
    'profit_factor' => is_finite($profitFactor) ? round($profitFactor, 2) : null,
    'avg_win' => round($avgWin, 2),
    'avg_loss' => round($avgLoss, 2),
    'win_loss_ratio' => is_finite($winLossRatio) ? round($winLossRatio, 2) : null,
    'net_profit' => round($netProfit, 2),
    'closed_trades' => $wins + $losses,
];

echo json_encode($result, JSON_UNESCAPED_UNICODE);
