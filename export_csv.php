<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

if (!verify_csrf($_GET['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}

$userId = current_user_id();
$stmt = $pdo->prepare(
    'SELECT trade_date, coin_name, account_balance, risk_percent, entry_price, stop_loss_price, take_profit_price,
            risk_amount, position_size, rr_ratio, potential_profit, potential_loss, status, created_at
     FROM trades
     WHERE user_id = :user_id
     ORDER BY trade_date DESC, id DESC'
);
$stmt->execute(['user_id' => $userId]);
$rows = $stmt->fetchAll();

$filename = 'trade_history_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'wb');
fputcsv($output, [
    'Trade Date', 'Coin', 'Account Balance', 'Risk %', 'Entry', 'Stop Loss', 'Take Profit',
    'Risk Amount', 'Position Size', 'RR Ratio', 'Potential Profit', 'Potential Loss', 'Status', 'Created At'
]);

foreach ($rows as $row) {
    fputcsv($output, [
        $row['trade_date'],
        $row['coin_name'],
        $row['account_balance'],
        $row['risk_percent'],
        $row['entry_price'],
        $row['stop_loss_price'],
        $row['take_profit_price'],
        $row['risk_amount'],
        $row['position_size'],
        $row['rr_ratio'],
        $row['potential_profit'],
        $row['potential_loss'],
        $row['status'],
        $row['created_at'],
    ]);
}

fclose($output);
exit;
