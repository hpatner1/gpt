<?php
require_once __DIR__ . '/includes/auth.php';
require_guest();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request token.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($name === '' || strlen($name) > 80) {
            $error = 'Please enter a valid name.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Password confirmation does not match.';
        } else {
            $check = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $check->execute(['email' => $email]);

            if ($check->fetch()) {
                $error = 'Email already exists.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, created_at) VALUES (:name, :email, :password_hash, NOW())');
                $stmt->execute([
                    'name' => $name,
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ]);
                $success = 'Account created successfully. You can now login.';
            }
        }
    }
}

$pageTitle = 'Register - ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>
<section class="auth-card">
    <h2>Register</h2>
    <p class="muted">Create your account to manage spot trading risk.</p>

    <?php if ($error): ?>
        <div class="alert error"><?php echo e($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert success"><?php echo e($success); ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
        <label>Full Name</label>
        <input type="text" name="name" required>

        <label>Email</label>
        <input type="email" name="email" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <label>Confirm Password</label>
        <input type="password" name="confirm_password" required>

        <button type="submit">Create Account</button>
    </form>

    <p class="muted">Already registered? <a href="index.php">Login</a></p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
