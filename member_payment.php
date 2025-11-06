<?php
session_start();
include 'db.php';

$member_id = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
if (!$member_id) {
    header('Location: member_register.php');
    exit;
}

// Load member
$stmt = $conn->prepare("SELECT id, name, email, phone, is_active FROM family_members WHERE id = ?");
$stmt->bind_param('i', $member_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
if (!$member) {
    die('Member not found');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simulate mobile payment by accepting a transaction id input
    $tx = trim($_POST['tx'] ?? '');
    if (empty($tx)) {
        $error = 'Please enter the mobile payment transaction id received on your phone';
    } else {
        $stmt = $conn->prepare("UPDATE family_members SET payment_ref = ?, is_active = 1 WHERE id = ?");
        $stmt->bind_param('si', $tx, $member_id);
        if ($stmt->execute()) {
            header('Location: member_login.php?activated=1');
            exit;
        } else {
            $error = 'Failed to record payment: ' . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
<div class="container">
    <h2>Mobile Payment</h2>
    <p>Hello <?php echo htmlspecialchars($member['name']); ?> — to activate your account please complete the mobile payment using your phone. After payment, enter the transaction id below to finish activation.</p>
    <?php if (isset($error)): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <form method="POST">
        <div class="form-group">
            <label>Mobile payment transaction id</label>
            <input type="text" name="tx" required>
        </div>
        <div class="actions">
            <button class="btn" type="submit">Confirm Payment</button>
            <a class="btn btn-secondary" href="index.php">Cancel</a>
        </div>
    </form>
</div>
</body>
</html>
