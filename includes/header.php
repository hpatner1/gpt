<?php
/** Shared app header layout. */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle ?? APP_NAME); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <?php if (!empty($extraHeadScripts) && is_array($extraHeadScripts)): ?>
        <?php foreach ($extraHeadScripts as $scriptSrc): ?>
            <script src="<?php echo e($scriptSrc); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
<div class="app-shell">
    <?php if (current_user_id() !== null): ?>
        <aside class="sidebar">
            <div>
                <h1><?php echo e(APP_NAME); ?></h1>
                <p class="muted">Spot Trading Dashboard</p>
            </div>
            <nav>
                <a href="dashboard.php">Dashboard</a>
                <a href="dashboard.php#calculator">Risk Calculator</a>
                <a href="dashboard.php#trades">Trade Journal</a>
                <a href="logout.php">Logout</a>
            </nav>
        </aside>
    <?php endif; ?>
    <main class="main-content">
