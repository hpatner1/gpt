<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

if (!verify_csrf($_POST['csrf_token'] ?? null)) {
    http_response_code(419);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$balance = (float) ($_POST['balance'] ?? 0);
$riskPercent = (float) ($_POST['risk_percent'] ?? 0);
$entry = (float) ($_POST['entry_price'] ?? 0);
$stopLoss = (float) ($_POST['stop_loss_price'] ?? 0);
$takeProfit = (float) ($_POST['take_profit_price'] ?? 0);

if ($balance <= 0 || $riskPercent <= 0 || $entry <= 0 || $stopLoss <= 0 || $takeProfit <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'All values must be greater than zero']);
    exit;
}

$results = calculate_risk_metrics($balance, $riskPercent, $entry, $stopLoss, $takeProfit);

header('Content-Type: application/json');
echo json_encode(['data' => $results]);
