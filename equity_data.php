<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$userId = current_user_id();

$initialCapitalStmt = $pdo->prepare(
    'SELECT account_balance
     FROM trades
     WHERE user_id = :user_id
     ORDER BY created_at ASC, id ASC
     LIMIT 1'
);
$initialCapitalStmt->execute(['user_id' => $userId]);
$initialCapital = (float) ($initialCapitalStmt->fetchColumn() ?: 0);

$equityStmt = $pdo->prepare(
    'SELECT DATE(created_at) AS trade_date, status, risk_amount, potential_profit
     FROM trades
     WHERE user_id = :user_id
       AND status IN ("Win", "Loss")
     ORDER BY created_at ASC, id ASC'
);
$equityStmt->execute(['user_id' => $userId]);

$rows = $equityStmt->fetchAll();
$equity = $initialCapital;
$data = [];

foreach ($rows as $row) {
    if ($row['status'] === 'Win') {
        $equity += (float) $row['potential_profit'];
    } elseif ($row['status'] === 'Loss') {
        $equity -= (float) $row['risk_amount'];
    }

    $data[] = [
        'date' => $row['trade_date'],
        'equity' => round($equity, 2),
    ];
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);
