<?php
/**
 * Hassan Spot Trading Risk Manager
 * Global configuration, database connection, and common helper functions.
 */

declare(strict_types=1);

// Production safe defaults (adjust for hosting).
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Session hardening for shared hosting.
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Database credentials (change for your hosting account).
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'hassan_risk_manager');
define('DB_USER', 'root');
define('DB_PASS', '');
define('APP_NAME', 'Hassan Spot Trading Risk Manager');

/** @var PDO|null $pdo */
$pdo = null;

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    // Generic error for security.
    http_response_code(500);
    die('Database connection failed.');
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    return !empty($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function calculate_risk_metrics(float $balance, float $riskPercent, float $entry, float $stopLoss, float $takeProfit): array
{
    $riskAmount = $balance * ($riskPercent / 100);
    $riskPerUnit = abs($entry - $stopLoss);
    $rewardPerUnit = abs($takeProfit - $entry);

    $positionSize = $riskPerUnit > 0 ? $riskAmount / $riskPerUnit : 0.0;
    $potentialLoss = $riskAmount;
    $potentialProfit = $positionSize * $rewardPerUnit;
    $rrRatio = $potentialLoss > 0 ? ($potentialProfit / $potentialLoss) : 0.0;

    return [
        'risk_amount' => round($riskAmount, 2),
        'position_size' => round($positionSize, 8),
        'rr_ratio' => round($rrRatio, 2),
        'potential_profit' => round($potentialProfit, 2),
        'potential_loss' => round($potentialLoss, 2),
    ];
}
