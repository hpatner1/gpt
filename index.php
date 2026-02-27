<?php
require_once __DIR__ . '/includes/auth.php';
require_guest();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request token.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $error = 'Please provide valid email and password.';
        } else {
            $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                redirect('dashboard.php');
            } else {
                $error = 'Invalid login credentials.';
            }
        }
    }
}

$pageTitle = 'Login - ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>
<section class="auth-card">
    <h2>Login</h2>
    <p class="muted">Access your trading risk dashboard.</p>

    <?php if ($error): ?>
        <div class="alert error"><?php echo e($error); ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
        <label>Email</label>
        <input type="email" name="email" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <button type="submit">Login</button>
    </form>

    <p class="muted">No account? <a href="register.php">Create one</a></p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
