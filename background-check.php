<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

if (!verify_csrf($_POST['csrf_token'] ?? null)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid CSRF token.']);
    exit;
}

$userId = current_user_id();
$tradesStmt = $pdo->prepare(
    'SELECT id, coin_name, stop_loss_price, tp1_price, tp2_price, take_profit_price, entry_price, status
     FROM trades
     WHERE user_id = :user_id
       AND status IN ("Running", "Partially Closed")
     ORDER BY id DESC
     LIMIT 30'
);
$tradesStmt->execute(['user_id' => $userId]);
$trades = $tradesStmt->fetchAll();

$symbolToTradeIds = [];
$tradeIndex = [];
foreach ($trades as $trade) {
    $symbol = strtolower(preg_replace('/[^a-z0-9]/', '', (string) $trade['coin_name']));
    if ($symbol === '') {
        continue;
    }
    $symbolToTradeIds[$symbol][] = (int) $trade['id'];
    $tradeIndex[(int) $trade['id']] = $trade;
}

if (!$symbolToTradeIds) {
    echo json_encode(['updated' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$symbols = array_keys($symbolToTradeIds);
$url = 'https://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&symbols=' . urlencode(implode(',', $symbols));
$context = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 10, 'header' => "User-Agent: TradingSystem/2.5\r\n"]]);
$body = @file_get_contents($url, false, $context);

if ($body === false) {
    http_response_code(502);
    echo json_encode(['updated' => [], 'error' => 'Market provider is unavailable right now.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$decoded = json_decode($body, true);
if (!is_array($decoded)) {
    http_response_code(502);
    echo json_encode(['updated' => [], 'error' => 'Invalid market response.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$priceBySymbol = [];
foreach ($decoded as $coin) {
    if (!is_array($coin) || !isset($coin['symbol'], $coin['current_price'])) {
        continue;
    }
    $priceBySymbol[strtolower((string) $coin['symbol'])] = (float) $coin['current_price'];
}

$allowedTransitions = [
    'Running' => ['Partially Closed', 'Win', 'Loss'],
    'Partially Closed' => ['Win', 'Loss'],
];

$updated = [];
$update = $pdo->prepare('UPDATE trades SET status = :status, updated_at = NOW() WHERE id = :id AND user_id = :user_id AND status = :current_status');

foreach ($symbolToTradeIds as $symbol => $tradeIds) {
    if (!isset($priceBySymbol[$symbol])) {
        continue;
    }

    $price = $priceBySymbol[$symbol];
    foreach ($tradeIds as $tradeId) {
        $trade = $tradeIndex[$tradeId];
        $currentStatus = normalize_trade_status((string) $trade['status']);
        $tp1 = (float) ($trade['tp1_price'] ?: $trade['take_profit_price']);
        $tp2 = (float) ($trade['tp2_price'] ?: $trade['take_profit_price']);

        $nextStatus = resolve_trade_status(
            (float) $trade['entry_price'],
            (float) $trade['stop_loss_price'],
            $tp1,
            $tp2,
            $price,
            $currentStatus
        );

        if ($nextStatus === $currentStatus || !in_array($nextStatus, $allowedTransitions[$currentStatus] ?? [], true)) {
            continue;
        }

        $update->execute([
            'status' => $nextStatus,
            'id' => $tradeId,
            'user_id' => $userId,
            'current_status' => $currentStatus,
        ]);

        if ($update->rowCount() > 0) {
            $updated[] = ['id' => $tradeId, 'status' => $nextStatus, 'price' => $price];
        }
    }
}

echo json_encode(['updated' => $updated], JSON_UNESCAPED_UNICODE);
