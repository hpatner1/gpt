<?php
/** Shared app header layout. */
$userName = null;
if (current_user_id() !== null && isset($pdo) && $pdo instanceof PDO) {
    $userStmt = $pdo->prepare('SELECT name FROM users WHERE id = :id LIMIT 1');
    $userStmt->execute(['id' => current_user_id()]);
    $userRow = $userStmt->fetch();
    $userName = $userRow['name'] ?? 'Trader';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle ?? APP_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <?php if (!empty($extraHeadScripts) && is_array($extraHeadScripts)): ?>
        <?php foreach ($extraHeadScripts as $scriptSrc): ?>
            <script src="<?php echo e($scriptSrc); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
<div class="app-shell <?php echo current_user_id() !== null ? 'is-auth' : 'is-guest'; ?>">
    <?php if (current_user_id() !== null): ?>
        <aside class="sidebar" id="appSidebar">
            <div class="sidebar-brand">
                <h1><?php echo e(APP_NAME); ?></h1>
                <p class="muted">Trading SaaS v2.6</p>
            </div>
            <nav>
                <a href="dashboard.php#overview">Dashboard</a>
                <a href="dashboard.php#trades">Journal</a>
                <a href="dashboard.php#analytics">Analytics</a>
                <a href="reports.php">Reports</a>
                <a href="dashboard.php#calculator">Risk Calculator</a>
            </nav>
        </aside>
    <?php endif; ?>
    <main class="main-content">
        <?php if (current_user_id() !== null): ?>
            <header class="top-header card">
                <button type="button" class="ghost-btn" id="sidebarToggle" aria-label="Toggle sidebar">☰</button>
                <div class="header-btc-pill">BTC: <strong id="liveBtcPrice">Loading...</strong></div>
                <div class="header-user-panel">
                    <span class="muted">Hi, <?php echo e((string) ($userName ?? 'Trader')); ?></span>
                    <a class="btn-danger" href="logout.php">Logout</a>
                </div>
            </header>
        <?php endif; ?>
