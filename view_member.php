<?php
session_start();
include 'db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}
$id = (int)$_GET['id'];

// load member with family info
$stmt = $conn->prepare("SELECT fm.*, f.visibility AS family_visibility, f.name AS family_name FROM family_members fm LEFT JOIN families f ON fm.family_id = f.id WHERE fm.id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
if (!$member) die('Member not found');

// enforce family visibility: if family is private, only admin or family member can view
if ($member['family_visibility'] === 'private') {
    $isAdmin = isset($_SESSION['admin_id']);
    $isMember = isset($_SESSION['member_id']) && isset($_SESSION['member_family_id']) && $_SESSION['member_family_id'] == $member['family_id'];
    if (!($isAdmin || $isMember)) {
        die('This member belongs to a private family. Please login with a member account to view.');
    }
}

// prepare avatar data URIs
$svgMale = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">' .
    '<rect width="100%" height="100%" fill="#e6f0ff"/>' .
    '<text x="50%" y="50%" font-size="72" text-anchor="middle" dy=".35em" fill="#2c7be5">♂</text>' .
    '</svg>';
$svgFemale = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">' .
    '<rect width="100%" height="100%" fill="#fff0f6"/>' .
    '<text x="50%" y="50%" font-size="72" text-anchor="middle" dy=".35em" fill="#e83e8c">♀</text>' .
    '</svg>';
$maleDataUri = 'data:image/svg+xml;utf8,' . rawurlencode($svgMale);
$femaleDataUri = 'data:image/svg+xml;utf8,' . rawurlencode($svgFemale);

$img = !empty($member['photo']) ? 'uploads/' . $member['photo'] : ($member['gender'] === 'female' ? $femaleDataUri : $maleDataUri);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>View Member - <?php echo htmlspecialchars($member['name']); ?></title>
    <link rel="stylesheet" href="css/styles.css">
    <style> .member-card{max-width:800px;margin:18px auto;background:#fff;padding:12px;border-radius:8px} .member-photo{float:right;width:140px;height:140px;object-fit:cover;border-radius:8px} table{width:100%;border-collapse:collapse} th{width:30%;text-align:left;padding:8px;background:#f7f7f7} td{padding:8px;border-bottom:1px solid #eee} </style>
</head>
<body>
    <div class="member-card">
        <h2><?php echo htmlspecialchars($member['name']); ?></h2>
        <img class="member-photo" src="<?php echo htmlspecialchars($img); ?>" alt="">
        <table>
            <tr><th>Gender</th><td><?php echo htmlspecialchars($member['gender']); ?></td></tr>
            <tr><th>Date of Birth</th><td><?php echo htmlspecialchars($member['date_of_birth']); ?></td></tr>
            <tr><th>Birth Time</th><td><?php echo htmlspecialchars($member['birth_time']); ?></td></tr>
            <tr><th>Birth Place</th><td><?php echo htmlspecialchars($member['birth_place']); ?></td></tr>
            <tr><th>Education</th><td><?php echo htmlspecialchars($member['education']); ?></td></tr>
            <tr><th>Occupation</th><td><?php echo htmlspecialchars($member['occupation']); ?></td></tr>
            <tr><th>Mobile</th><td><?php echo htmlspecialchars($member['mobile_no']); ?></td></tr>
            <tr><th>Address</th><td><?php echo nl2br(htmlspecialchars($member['address'])); ?></td></tr>
            <tr><th>Gotra</th><td><?php echo htmlspecialchars($member['gotra']); ?></td></tr>
            <tr><th>Caste</th><td><?php echo htmlspecialchars($member['caste']); ?></td></tr>
            <tr><th>Family</th><td><?php echo htmlspecialchars($member['family_name'] ?? ''); ?></td></tr>
        </table>
        <div style="margin-top:12px">
            <?php if (isset($_SESSION['admin_id']) || (isset($_SESSION['member_family_id']) && $_SESSION['member_family_id'] == $member['family_id'])): ?>
                <a class="add-member-btn" href="edit_member_front.php?id=<?php echo $id; ?>">Edit</a>
            <?php endif; ?>
            <a class="add-member-btn" href="index.php?focus=<?php echo $id; ?>">View in Tree</a>
            <a class="add-member-btn" href="members_list.php">Back to List</a>
        </div>
    </div>
</body>
</html>
