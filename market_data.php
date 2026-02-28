<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$symbolRaw = strtolower(trim((string) ($_GET['symbol'] ?? '')));
$symbol = preg_replace('/[^a-z0-9]/', '', $symbolRaw);

if ($symbol === '' || strlen($symbol) > 10) {
    http_response_code(422);
    echo json_encode(['error' => 'Please provide a valid coin symbol.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

$cacheFile = $cacheDir . '/market_' . $symbol . '.json';
$cacheLifetime = 300;

if (is_file($cacheFile) && (time() - filemtime($cacheFile) < $cacheLifetime)) {
    $cached = file_get_contents($cacheFile);
    if ($cached !== false) {
        echo $cached;
        exit;
    }
}

$url = 'https://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&symbols=' . urlencode($symbol) . '&sparkline=true&price_change_percentage=24h';

function fetch_market_payload(string $url): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_USERAGENT => 'TradingSystem/2.4',
        ]);
        $body = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body !== false && $statusCode >= 200 && $statusCode < 300) {
            return $body;
        }

        return null;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 15,
            'header' => "User-Agent: TradingSystem/2.4\r\n",
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    return $body === false ? null : $body;
}

$body = fetch_market_payload($url);
if ($body === null) {
    http_response_code(502);
    echo json_encode(['error' => 'Market provider is unavailable right now.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$decoded = json_decode($body, true);
if (!is_array($decoded) || empty($decoded[0])) {
    http_response_code(404);
    echo json_encode(['error' => 'No market data found for that symbol.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$coin = $decoded[0];
$response = [
    'symbol' => strtoupper((string) ($coin['symbol'] ?? $symbol)),
    'name' => (string) ($coin['name'] ?? strtoupper($symbol)),
    'price' => (float) ($coin['current_price'] ?? 0),
    'change_24h' => (float) ($coin['price_change_percentage_24h'] ?? 0),
    'volume_24h' => (float) ($coin['total_volume'] ?? 0),
    'market_cap' => (float) ($coin['market_cap'] ?? 0),
    'sparkline' => isset($coin['sparkline_in_7d']['price']) && is_array($coin['sparkline_in_7d']['price'])
        ? array_slice(array_map('floatval', $coin['sparkline_in_7d']['price']), -24)
        : [],
    'cached_at' => date('c'),
];

$json = json_encode($response, JSON_UNESCAPED_UNICODE);
if ($json === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to encode market data.'], JSON_UNESCAPED_UNICODE);
    exit;
}

@file_put_contents($cacheFile, $json, LOCK_EX);
echo $json;
