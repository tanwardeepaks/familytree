<?php
session_start();
include 'db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: members_list.php');
    exit;
}
$id = (int)$_GET['id'];

// load member
$stmt = $conn->prepare("SELECT * FROM family_members WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
if (!$member) {
    die('Member not found');
}

// check permission: admin or member of same family
$isAdmin = isset($_SESSION['admin_id']);
$isMember = isset($_SESSION['member_id']) && isset($_SESSION['member_family_id']) && $_SESSION['member_family_id'] == $member['family_id'];
if (!($isAdmin || $isMember)) {
    die('Permission denied');
}

// Load family members for relationship selects (limit to same family for members, all for admin)
if ($isAdmin) {
    $res = $conn->query("SELECT id, name, gender FROM family_members ORDER BY name");
} else {
    $fid = (int)$_SESSION['member_family_id'];
    $stmt2 = $conn->prepare("SELECT id, name, gender FROM family_members WHERE family_id = ? ORDER BY name");
    $stmt2->bind_param('i', $fid);
    $stmt2->execute();
    $res = $stmt2->get_result();
}
$members = $res->fetch_all(MYSQLI_ASSOC);

// families for possible override (admins may change)
$families = [];
$fres = $conn->query("SELECT id, name, gotra, caste FROM families ORDER BY name");
while ($fr = $fres->fetch_assoc()) $families[] = $fr;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Only allow changing family if admin or owner override
    $name = trim($_POST['name'] ?? $member['name']);
    $gender = $_POST['gender'] ?? $member['gender'];
    $father_id = !empty($_POST['father_id']) ? (int)$_POST['father_id'] : null;
    $mother_id = !empty($_POST['mother_id']) ? (int)$_POST['mother_id'] : null;
    $spouse_id = !empty($_POST['spouse_id']) ? (int)$_POST['spouse_id'] : null;
    $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : $member['date_of_birth'];
    $birth_time = !empty($_POST['birth_time']) ? $_POST['birth_time'] : $member['birth_time'];
    $birth_place = trim($_POST['birth_place'] ?? $member['birth_place']);
    $education = trim($_POST['education'] ?? $member['education']);
    $occupation = trim($_POST['occupation'] ?? $member['occupation']);
    $mobile_no = trim($_POST['mobile_no'] ?? $member['mobile_no']);
    $address = trim($_POST['address'] ?? $member['address']);
    $gotra = trim($_POST['gotra'] ?? $member['gotra']);
    $caste = trim($_POST['caste'] ?? $member['caste']);
    $status = trim($_POST['status'] ?? $member['status']);

    $photo = $member['photo'];
    if (!empty($_FILES['photo']['name'])) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024;
        if (!in_array($_FILES['photo']['type'], $allowed_types)) {
            $error = 'Invalid photo type';
        } elseif ($_FILES['photo']['size'] > $max_size) {
            $error = 'Photo too large';
        } else {
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '_' . time() . '.' . $ext;
            if (!is_dir(__DIR__ . '/uploads')) mkdir(__DIR__ . '/uploads', 0755, true);
            if (move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/uploads/' . $filename)) {
                $photo = $filename;
            } else {
                $error = 'Failed to upload photo';
            }
        }
    }

    if (empty($name) || ($gender !== 'male' && $gender !== 'female')) {
        $error = 'Name and valid gender required';
    }

    if (!isset($error)) {
        // family change allowed only for admin or if same as owner's family
        $family_id = $member['family_id'];
        if ($isAdmin && isset($_POST['family_id'])) {
            $family_id = (int)$_POST['family_id'];
        }

        $stmtu = $conn->prepare("UPDATE family_members SET name=?, gender=?, family_id=?, father_id=?, mother_id=?, spouse_id=?, photo=?, date_of_birth=?, birth_time=?, birth_place=?, education=?, occupation=?, mobile_no=?, address=?, gotra=?, caste=?, status=? WHERE id=?");
        $stmtu->bind_param('ssiiiiisssssssssssi', $name, $gender, $family_id, $father_id, $mother_id, $spouse_id, $photo, $date_of_birth, $birth_time, $birth_place, $education, $occupation, $mobile_no, $address, $gotra, $caste, $status, $id);
        if (!$stmtu->execute()) {
            $error = 'Update failed: ' . $conn->error;
        } else {
            header('Location: view_member.php?id=' . $id);
            exit;
        }
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Edit Member</title>
    <link rel="stylesheet" href="css/styles.css">
    <style>.form-group{margin-bottom:10px}</style>
</head>
<body>
<div class="container">
    <h2>Edit Member: <?php echo htmlspecialchars($member['name']); ?></h2>
    <?php if (isset($error)): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
        <div class="form-group"><label>Name</label><input type="text" name="name" value="<?php echo htmlspecialchars($member['name']); ?>" required></div>
        <div class="form-group"><label>Gender</label>
            <select name="gender" required><option value="male" <?php echo $member['gender']==='male'?'selected':''; ?>>Male</option><option value="female" <?php echo $member['gender']==='female'?'selected':''; ?>>Female</option></select>
        </div>
        <div class="form-group"><label>Photo</label><input type="file" name="photo" accept="image/*"><br><?php if ($member['photo']): ?><img src="uploads/<?php echo htmlspecialchars($member['photo']); ?>" style="width:80px;border-radius:6px;margin-top:6px"><?php endif; ?></div>

        <?php if ($isAdmin): ?>
            <div class="form-group"><label>Family</label><select name="family_id">
                <?php foreach ($families as $f): ?><option value="<?php echo $f['id']; ?>" <?php echo ($f['id']==$member['family_id'])?'selected':''; ?>><?php echo htmlspecialchars($f['name']); ?></option><?php endforeach; ?>
            </select></div>
        <?php endif; ?>

        <div class="form-group"><label>Father</label><select name="father_id"><option value="">None</option><?php foreach ($members as $m): if ($m['gender']==='male'): ?><option value="<?php echo $m['id']; ?>" <?php echo ($m['id']==$member['father_id'])?'selected':''; ?>><?php echo htmlspecialchars($m['name']); ?></option><?php endif; endforeach; ?></select></div>
        <div class="form-group"><label>Mother</label><select name="mother_id"><option value="">None</option><?php foreach ($members as $m): if ($m['gender']==='female'): ?><option value="<?php echo $m['id']; ?>" <?php echo ($m['id']==$member['mother_id'])?'selected':''; ?>><?php echo htmlspecialchars($m['name']); ?></option><?php endif; endforeach; ?></select></div>
        <div class="form-group"><label>Spouse</label><select name="spouse_id"><option value="">None</option><?php foreach ($members as $m): ?><option value="<?php echo $m['id']; ?>" <?php echo ($m['id']==$member['spouse_id'])?'selected':''; ?>><?php echo htmlspecialchars($m['name']); ?> (<?php echo ucfirst($m['gender']); ?>)</option><?php endforeach; ?></select></div>

        <div class="form-group"><label>Date of Birth</label><input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($member['date_of_birth']); ?>"></div>
        <div class="form-group"><label>Education</label><textarea name="education"><?php echo htmlspecialchars($member['education']); ?></textarea></div>
        <div class="form-group"><label>Address</label><textarea name="address"><?php echo htmlspecialchars($member['address']); ?></textarea></div>
        <div class="form-group"><label>Gotra</label><input type="text" name="gotra" value="<?php echo htmlspecialchars($member['gotra']); ?>"></div>
        <div class="form-group"><label>Caste</label><input type="text" name="caste" value="<?php echo htmlspecialchars($member['caste']); ?>"></div>
        <div class="form-group"><label>Status</label><select name="status"><option value="unmarried" <?php echo ($member['status']==='unmarried')?'selected':''; ?>>Unmarried</option><option value="married" <?php echo ($member['status']==='married')?'selected':''; ?>>Married</option><option value="divorced" <?php echo ($member['status']==='divorced')?'selected':''; ?>>Divorced</option><option value="widow" <?php echo ($member['status']==='widow')?'selected':''; ?>>Widow</option></select></div>

        <div class="actions"><button class="btn" type="submit">Save</button> <a class="btn btn-secondary" href="view_member.php?id=<?php echo $id; ?>">Cancel</a></div>
    </form>
</div>
</body>
</html>
