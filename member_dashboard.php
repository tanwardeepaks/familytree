<?php
session_start();
include 'db.php';

if (!isset($_SESSION['member_id'])) {
    header('Location: member_login.php');
    exit;
}
$member_id = (int)$_SESSION['member_id'];

// Load member
$stmt = $conn->prepare("SELECT id, name, family_id FROM family_members WHERE id = ?");
$stmt->bind_param('i', $member_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
if (!$member) {
    session_destroy();
    header('Location: member_login.php');
    exit;
}
$family_id = $member['family_id'];

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>My Account</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
<div class="container">
    <h2>Welcome, <?php echo htmlspecialchars($member['name']); ?></h2>
    <p>Your account is active. From here you can view and manage your family tree.</p>
    <div class="actions">
        <a class="btn" href="index.php?family_id=<?php echo $family_id; ?>">View My Family Tree</a>
        <a class="btn" href="add_member_front.php">Add Family Member</a>
        <a class="btn btn-secondary" href="member_logout.php">Logout</a>
    </div>
    <hr>
    <h3>Notes</h3>
    <ul>
        <li>Only families marked public can be viewed by non-members. Private families require login.</li>
        <li>You can add members to your family using the "Add Family Member" button.</li>
    </ul>
</div>
</body>
</html>
