<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$userId = current_user_id();

$equityStmt = $pdo->prepare(
    'SELECT DATE(created_at) AS trade_date, account_balance, status, risk_amount, potential_profit
     FROM trades
     WHERE user_id = :user_id
       AND status IN ("Win", "Loss")
     ORDER BY created_at ASC, id ASC'
);
$equityStmt->execute(['user_id' => $userId]);

$rows = $equityStmt->fetchAll();
$baselineBalance = isset($rows[0]['account_balance']) ? (float) $rows[0]['account_balance'] : 0.0;
$equity = $baselineBalance;
$peak = $baselineBalance;
$data = [];

foreach ($rows as $row) {
    if ($row['status'] === 'Win') {
        $equity += (float) $row['potential_profit'];
    } elseif ($row['status'] === 'Loss') {
        $equity -= (float) $row['risk_amount'];
    }

    if ($equity > $peak) {
        $peak = $equity;
    }

    $data[] = [
        'date' => $row['trade_date'],
        'equity' => round($equity, 2),
        'balance' => round($baselineBalance, 2),
        'peak' => round($peak, 2),
    ];
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);
