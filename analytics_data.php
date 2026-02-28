<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

header('Content-Type: application/json; charset=utf-8');
$userId = current_user_id();

$tradeStmt = $pdo->prepare(
    'SELECT status, account_balance, risk_amount, potential_profit, rr_ratio, strategy, session,
            pre_trade_emotion, tp1_profit, tp2_profit, risk_percent
     FROM trades
     WHERE user_id = :user_id
     ORDER BY created_at ASC, id ASC'
);
$tradeStmt->execute(['user_id' => $userId]);
$rows = $tradeStmt->fetchAll();

$baselineBalance = isset($rows[0]['account_balance']) ? (float) $rows[0]['account_balance'] : 0.0;
$currentEquity = $baselineBalance;
$peakEquity = $baselineBalance;
$maxDrawdownAmount = 0.0;

$closedTrades = 0;
$wins = 0;
$losses = 0;
$grossProfit = 0.0;
$grossLoss = 0.0;
$netProfit = 0.0;
$totalRiskPercent = 0.0;
$rrBuckets = ['0-1' => 0, '1-2' => 0, '2-3' => 0, '3+' => 0];
$profitDistribution = [];
$streaks = [];
$currentStreakType = null;
$currentStreakLength = 0;
$emotionStats = [];
$lossEmotions = [];

foreach ($rows as $row) {
    $status = (string) $row['status'];
    $risk = (float) $row['risk_amount'];
    $rr = (float) $row['rr_ratio'];
    $realized = 0.0;

    if ($status === 'Win') {
        $realized = (float) $row['potential_profit'];
        $wins++;
        $closedTrades++;
        $grossProfit += $realized;
        $netProfit += $realized;
        $streakType = 'Win';
    } elseif ($status === 'Loss') {
        $realized = -$risk;
        $losses++;
        $closedTrades++;
        $grossLoss += $risk;
        $netProfit -= $risk;
        $streakType = 'Loss';
    } elseif ($status === 'Partially Closed') {
        $realized = (float) $row['tp1_profit'];
        $netProfit += $realized;
        $streakType = null;
    } else {
        $streakType = null;
    }

    if ($status !== 'Running') {
        $currentEquity += $realized;
        if ($currentEquity > $peakEquity) {
            $peakEquity = $currentEquity;
        }
        $maxDrawdownAmount = max($maxDrawdownAmount, $peakEquity - $currentEquity);
    }

    if (in_array($status, ['Win', 'Loss'], true)) {
        if ($rr < 1) {
            $rrBuckets['0-1']++;
        } elseif ($rr < 2) {
            $rrBuckets['1-2']++;
        } elseif ($rr < 3) {
            $rrBuckets['2-3']++;
        } else {
            $rrBuckets['3+']++;
        }

        $profitDistribution[] = $realized;

        if ($currentStreakType === $streakType) {
            $currentStreakLength++;
        } else {
            if ($currentStreakType !== null) {
                $streaks[] = ['type' => $currentStreakType, 'length' => $currentStreakLength];
            }
            $currentStreakType = $streakType;
            $currentStreakLength = 1;
        }

        $totalRiskPercent += (float) ($row['risk_percent'] ?? 0);

        $emotion = (string) ($row['pre_trade_emotion'] ?? 'Unspecified');
        if ($emotion === '') {
            $emotion = 'Unspecified';
        }
        if (!isset($emotionStats[$emotion])) {
            $emotionStats[$emotion] = ['trades' => 0, 'wins' => 0, 'net_profit' => 0.0];
        }

        $emotionStats[$emotion]['trades']++;
        if ($status === 'Win') {
            $emotionStats[$emotion]['wins']++;
        }
        $emotionStats[$emotion]['net_profit'] += $realized;

        if ($status === 'Loss') {
            $lossEmotions[$emotion] = ($lossEmotions[$emotion] ?? 0) + 1;
        }
    }
}
if ($currentStreakType !== null) {
    $streaks[] = ['type' => $currentStreakType, 'length' => $currentStreakLength];
}

$profitFactor = $grossLoss > 0 ? ($grossProfit / $grossLoss) : ($grossProfit > 0 ? INF : 0.0);
$avgWin = $wins > 0 ? ($grossProfit / $wins) : 0.0;
$avgLoss = $losses > 0 ? ($grossLoss / $losses) : 0.0;
$winLossRatio = $avgLoss > 0 ? ($avgWin / $avgLoss) : ($avgWin > 0 ? INF : 0.0);
$winRate = $closedTrades > 0 ? $wins / $closedTrades : 0.0;
$lossRate = 1 - $winRate;
$expectancyPerTrade = ($winRate * $avgWin) - ($lossRate * $avgLoss);
$expectancyPercentRisk = $avgLoss > 0 ? ($expectancyPerTrade / $avgLoss) * 100 : 0.0;
$recoveryFactor = $maxDrawdownAmount > 0 ? ($netProfit / $maxDrawdownAmount) : null;
$currentDrawdownPercent = $peakEquity > 0 ? (($peakEquity - $currentEquity) / $peakEquity) * 100 : 0.0;
$maxDrawdownPercent = $peakEquity > 0 ? ($maxDrawdownAmount / $peakEquity) * 100 : 0.0;

$emotionPerformance = [];
foreach ($emotionStats as $emotion => $stat) {
    $emotionPerformance[] = [
        'emotion' => $emotion,
        'trade_count' => $stat['trades'],
        'win_rate_percent' => $stat['trades'] > 0 ? round(($stat['wins'] / $stat['trades']) * 100, 2) : 0,
        'net_profit' => round($stat['net_profit'], 2),
    ];
}
usort($emotionPerformance, static fn($a, $b) => $b['net_profit'] <=> $a['net_profit']);
arsort($lossEmotions);
$lossEmotion = $lossEmotions ? array_key_first($lossEmotions) : null;
$biasPattern = $lossEmotion ? ('Losses frequently follow ' . $lossEmotion . ' entries.') : 'Not enough data.';

$strategyStmt = $pdo->prepare('SELECT COALESCE(NULLIF(strategy, ""), "Unspecified") AS strategy_name, COUNT(*) AS trade_count, SUM(CASE WHEN status="Win" THEN 1 ELSE 0 END) AS wins, SUM(CASE WHEN status="Win" THEN potential_profit WHEN status="Loss" THEN -risk_amount ELSE 0 END) AS net_profit FROM trades WHERE user_id=:user_id AND status IN ("Win","Loss") GROUP BY COALESCE(NULLIF(strategy, ""), "Unspecified") ORDER BY net_profit DESC');
$strategyStmt->execute(['user_id' => $userId]);
$strategyPerformance = array_map(static function (array $row): array {
    $count = (int) $row['trade_count'];
    return ['strategy' => $row['strategy_name'], 'trade_count' => $count, 'win_rate_percent' => $count > 0 ? round(((int) $row['wins'] / $count) * 100, 2) : 0, 'net_profit' => round((float) $row['net_profit'], 2)];
}, $strategyStmt->fetchAll());

$sessionStmt = $pdo->prepare('SELECT session, COUNT(*) AS trade_count, SUM(CASE WHEN status="Win" THEN 1 ELSE 0 END) AS wins, SUM(CASE WHEN status="Win" THEN potential_profit WHEN status="Loss" THEN -risk_amount ELSE 0 END) AS net_profit FROM trades WHERE user_id=:user_id AND status IN ("Win","Loss") GROUP BY session ORDER BY net_profit DESC');
$sessionStmt->execute(['user_id' => $userId]);
$sessionPerformance = array_map(static function (array $row): array {
    $count = (int) $row['trade_count'];
    return ['session' => $row['session'], 'trade_count' => $count, 'win_rate_percent' => $count > 0 ? round(((int) $row['wins'] / $count) * 100, 2) : 0, 'net_profit' => round((float) $row['net_profit'], 2)];
}, $sessionStmt->fetchAll());


$avgRiskPercent = $closedTrades > 0 ? ($totalRiskPercent / $closedTrades) : 0.0;
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

echo json_encode([
    'max_drawdown_percent' => round($maxDrawdownPercent, 2),
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
    'strategy_performance' => $strategyPerformance,
    'best_strategy' => $strategyPerformance[0]['strategy'] ?? null,
    'session_performance' => $sessionPerformance,
    'best_session' => $sessionPerformance[0]['session'] ?? null,
    'rr_distribution' => $rrBuckets,
    'profit_distribution' => $profitDistribution,
    'streak_data' => $streaks,
    'emotion_performance' => $emotionPerformance,
    'best_emotion' => $emotionPerformance[0]['emotion'] ?? null,
    'emotion_before_loss' => $lossEmotion,
    'emotion_bias' => $biasPattern,
], JSON_UNESCAPED_UNICODE);
