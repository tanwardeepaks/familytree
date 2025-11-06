<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (empty($email) || empty($password)) {
        $error = 'Email and password required';
    } else {
        $stmt = $conn->prepare("SELECT id, password, is_active, family_id FROM family_members WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $m = $stmt->get_result()->fetch_assoc();
        if (!$m) {
            $error = 'Invalid credentials';
        } else {
            if (!password_verify($password, $m['password'])) {
                $error = 'Invalid credentials';
            } elseif (!$m['is_active']) {
                $error = 'Account not activated. Please complete payment.';
            } else {
                // Login
                $_SESSION['member_id'] = $m['id'];
                $_SESSION['member_family_id'] = $m['family_id'];
                header('Location: member_dashboard.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Member Login</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
<div class="container">
    <h2>Member Login</h2>
    <?php if (isset($_GET['activated'])): ?><div class="info">Account activated — please login.</div><?php endif; ?>
    <?php if (isset($error)): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <form method="POST">
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required>
        </div>
        <div class="actions">
            <button class="btn" type="submit">Login</button>
            <a class="btn btn-secondary" href="member_register.php">Register</a>
        </div>
    </form>
</div>
</body>
</html>
