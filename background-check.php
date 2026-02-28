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
$tradesStmt = $pdo->prepare('SELECT id, coin_name, stop_loss_price, tp1_price, tp2_price, take_profit_price FROM trades WHERE user_id = :user_id AND status = "Running" ORDER BY id DESC LIMIT 30');
$tradesStmt->execute(['user_id' => $userId]);
$trades = $tradesStmt->fetchAll();

$updated = [];
foreach ($trades as $trade) {
    $symbol = strtolower(preg_replace('/[^a-z0-9]/', '', (string) $trade['coin_name']));
    if ($symbol === '') {
        continue;
    }

    $context = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 10, 'header' => "User-Agent: TradingSystem/2.5\r\n"]]);
    $url = 'https://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&symbols=' . urlencode($symbol);
    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        continue;
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded) || !isset($decoded[0]['current_price'])) {
        continue;
    }

    $price = (float) $decoded[0]['current_price'];
    $sl = (float) $trade['stop_loss_price'];
    $tp1 = (float) ($trade['tp1_price'] ?: $trade['take_profit_price']);
    $tp2 = (float) ($trade['tp2_price'] ?: $trade['take_profit_price']);

    $nextStatus = 'Running';
    if ($price <= $sl) {
        $nextStatus = 'Loss';
    } elseif ($price >= $tp2) {
        $nextStatus = 'Win';
    } elseif ($price >= $tp1) {
        $nextStatus = 'Partially Closed';
    }

    if ($nextStatus !== 'Running') {
        $update = $pdo->prepare('UPDATE trades SET status = :status, updated_at = NOW() WHERE id = :id AND user_id = :user_id AND status = "Running"');
        $update->execute(['status' => $nextStatus, 'id' => (int) $trade['id'], 'user_id' => $userId]);
        if ($update->rowCount() > 0) {
            $updated[] = ['id' => (int) $trade['id'], 'status' => $nextStatus, 'price' => $price];
        }
    }
}

echo json_encode(['updated' => $updated], JSON_UNESCAPED_UNICODE);
