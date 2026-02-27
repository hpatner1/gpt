<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$tradeId = (int) ($_GET['id'] ?? 0);
$token = $_GET['csrf_token'] ?? '';

if ($tradeId > 0 && verify_csrf($token)) {
    $stmt = $pdo->prepare('DELETE FROM trades WHERE id = :id AND user_id = :user_id');
    $stmt->execute([
        'id' => $tradeId,
        'user_id' => current_user_id(),
    ]);
}

redirect('dashboard.php#trades');
