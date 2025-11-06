<?php
session_start();
include 'db.php';

// Simple frontend registration: creates member entry with is_active=0 and redirects to payment step
$families = [];
$res = $conn->query("SELECT id, name FROM families ORDER BY name");
while ($r = $res->fetch_assoc()) $families[] = $r;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $family_id = !empty($_POST['family_id']) ? (int)$_POST['family_id'] : null;
    $new_family = trim($_POST['new_family'] ?? '');

    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Name, email and password are required';
    } else {
        // If user provided a new family name, create it
        if (!empty($new_family)) {
            $slug = preg_replace('/[^a-z0-9]+/','-',strtolower($new_family));
            $stmt = $conn->prepare("INSERT INTO families (name, slug, visibility) VALUES (?, ?, 'private')");
            $stmt->bind_param('ss', $new_family, $slug);
            if ($stmt->execute()) {
                $family_id = $conn->insert_id;
            }
        }

        // Ensure email not already used
        $stmt = $conn->prepare("SELECT id FROM family_members WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $error = 'Email already registered';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO family_members (name, gender, family_id, email, password, phone, is_active) VALUES (?, 'male', ?, ?, ?, ?, 0)");
            // gender required by DB; default to male if not provided; frontend can update later
            $stmt->bind_param('sisss', $name, $family_id, $email, $hash, $phone);
            if ($stmt->execute()) {
                $member_id = $conn->insert_id;
                header('Location: member_payment.php?member_id=' . $member_id);
                exit;
            } else {
                $error = 'Failed to register: ' . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Register</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
<div class="container">
    <h2>Register</h2>
    <?php if (isset($error)): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <form method="POST">
        <div class="form-group">
            <label>Name</label>
            <input type="text" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required>
        </div>
        <div class="form-group">
            <label>Phone (for mobile pay)</label>
            <input type="tel" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Choose an existing family (or create a new one below)</label>
            <select name="family_id">
                <option value="">-- Select Family --</option>
                <?php foreach ($families as $f): ?>
                    <option value="<?php echo $f['id'] ?>" <?php echo (isset($_POST['family_id']) && $_POST['family_id']==$f['id'])? 'selected':''; ?>><?php echo htmlspecialchars($f['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Create new family (optional)</label>
            <input type="text" name="new_family" value="<?php echo htmlspecialchars($_POST['new_family'] ?? '') ?>">
        </div>
        <div class="actions">
            <button class="btn" type="submit">Register & Pay</button>
            <a class="btn btn-secondary" href="index.php">Cancel</a>
        </div>
    </form>
</div>
</body>
</html>
