<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$userId = current_user_id();

$analyticsStmt = $pdo->prepare(
    'SELECT
        SUM(CASE WHEN status = "Win" THEN potential_profit ELSE 0 END) AS gross_profit,
        SUM(CASE WHEN status = "Loss" THEN potential_loss ELSE 0 END) AS gross_loss,
        SUM(CASE WHEN status = "Win" THEN 1 ELSE 0 END) AS wins,
        SUM(CASE WHEN status = "Loss" THEN 1 ELSE 0 END) AS losses,
        AVG(risk_percent) AS avg_risk_percent
    FROM trades
    WHERE user_id = :user_id
      AND status IN ("Win", "Loss")'
);
$analyticsStmt->execute(['user_id' => $userId]);
$analytics = $analyticsStmt->fetch() ?: [];

$equityStmt = $pdo->prepare(
    'SELECT status, potential_profit, potential_loss
     FROM trades
     WHERE user_id = :user_id
       AND status IN ("Win", "Loss")
     ORDER BY trade_date ASC, id ASC'
);
$equityStmt->execute(['user_id' => $userId]);
$equityRows = $equityStmt->fetchAll();

$equity = 0.0;
$peak = 0.0;
$maxDrawdown = 0.0;
foreach ($equityRows as $row) {
    if ($row['status'] === 'Win') {
        $equity += (float) $row['potential_profit'];
    } else {
        $equity -= (float) $row['potential_loss'];
    }
    if ($equity > $peak) {
        $peak = $equity;
    }
    if ($peak > 0) {
        $drawdown = (($peak - $equity) / $peak) * 100;
        if ($drawdown > $maxDrawdown) {
            $maxDrawdown = $drawdown;
        }
    }
}

$recentLossStmt = $pdo->prepare(
    'SELECT status
     FROM trades
     WHERE user_id = :user_id
     ORDER BY trade_date DESC, id DESC
     LIMIT 3'
);
$recentLossStmt->execute(['user_id' => $userId]);
$recentStatuses = $recentLossStmt->fetchAll(PDO::FETCH_COLUMN);
$consecutiveLosses = count($recentStatuses) === 3 && count(array_filter($recentStatuses, static fn($s) => $s === 'Loss')) === 3;

$grossProfit = (float) ($analytics['gross_profit'] ?? 0);
$grossLoss = (float) ($analytics['gross_loss'] ?? 0);
$profitFactor = $grossLoss > 0 ? ($grossProfit / $grossLoss) : 0;
$avgRiskPercent = (float) ($analytics['avg_risk_percent'] ?? 0);

$suggestions = [];
if ($maxDrawdown > 10) {
    $suggestions[] = [
        'level' => 'warning',
        'message' => 'Drawdown is above 10%. Reduce risk per trade and tighten stop placement.'
    ];
}
if ($profitFactor > 0 && $profitFactor < 1.2) {
    $suggestions[] = [
        'level' => 'warning',
        'message' => 'Profit factor is below 1.2. Review your entry quality and position sizing.'
    ];
}
if ($consecutiveLosses) {
    $suggestions[] = [
        'level' => 'caution',
        'message' => '3+ consecutive losses detected. Pause trading and reassess market conditions.'
    ];
}
if (!$suggestions) {
    $suggestions[] = [
        'level' => 'healthy',
        'message' => 'Risk profile is healthy. Maintain discipline and current position sizing rules.'
    ];
}

echo json_encode([
    'drawdown_percent' => round($maxDrawdown, 2),
    'profit_factor' => round($profitFactor, 2),
    'average_risk_percent' => round($avgRiskPercent, 2),
    'consecutive_loss_alert' => $consecutiveLosses,
    'suggestions' => $suggestions,
], JSON_UNESCAPED_UNICODE);
