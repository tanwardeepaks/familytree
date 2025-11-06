<?php
session_start();
include 'db.php';

// Require login (member or admin). Redirect to member login and preserve current URL for return.
if (!isset($_SESSION['member_id']) && !isset($_SESSION['admin_id'])) {
    $current = $_SERVER['REQUEST_URI'] ?? '/members_list.php';
    header('Location: member_login.php?redirect=' . urlencode($current));
    exit;
}

// Load families for dropdown
$families = [];
$fres = $conn->query("SELECT id, name FROM families ORDER BY name");
while ($fr = $fres->fetch_assoc()) $families[] = $fr;

// default values from GET
$family_id = isset($_GET['family_id']) && $_GET['family_id'] !== '' ? (int)$_GET['family_id'] : null;
$gotra = trim($_GET['gotra'] ?? '');
$caste = trim($_GET['caste'] ?? '');
$education = trim($_GET['education'] ?? '');
$address = trim($_GET['address'] ?? '');
$dob_from = trim($_GET['dob_from'] ?? '');
$dob_to = trim($_GET['dob_to'] ?? '');

// If logged-in member, default to their family if not provided
if (isset($_SESSION['member_id']) && isset($_SESSION['member_family_id']) && !$family_id) {
    $family_id = (int)$_SESSION['member_family_id'];
}

// Build query
$where = [];
$params = [];
$types = '';

if ($family_id) {
    $where[] = 'fm.family_id = ?';
    $types .= 'i';
    $params[] = $family_id;
}

if ($gotra !== '') {
    $where[] = 'fm.gotra LIKE ?';
    $types .= 's';
    $params[] = "%" . $conn->real_escape_string($gotra) . "%";
}

if ($caste !== '') {
    $where[] = 'fm.caste LIKE ?';
    $types .= 's';
    $params[] = "%" . $conn->real_escape_string($caste) . "%";
}

if ($education !== '') {
    $where[] = 'fm.education LIKE ?';
    $types .= 's';
    $params[] = "%" . $conn->real_escape_string($education) . "%";
}

if ($address !== '') {
    $where[] = 'fm.address LIKE ?';
    $types .= 's';
    $params[] = "%" . $conn->real_escape_string($address) . "%";
}

if ($dob_from !== '') {
    // expect YYYY-MM-DD
    $where[] = 'fm.date_of_birth >= ?';
    $types .= 's';
    $params[] = $dob_from;
}

if ($dob_to !== '') {
    $where[] = 'fm.date_of_birth <= ?';
    $types .= 's';
    $params[] = $dob_to;
}

// We'll need total count for pagination
$count_sql = "SELECT COUNT(*) AS cnt FROM family_members fm";
if (!empty($where)) $count_sql .= ' WHERE ' . implode(' AND ', $where);

$stmt_count = $conn->prepare($count_sql);
if ($stmt_count === false) { die('Prepare failed: ' . htmlspecialchars($conn->error)); }

if (!empty($params)) {
    // bind params for count
    $bind_names = [];
    $bind_names[] = $types;
    for ($i = 0; $i < count($params); $i++) {
        $bind_name = 'cb' . $i;
        $$bind_name = $params[$i];
        $bind_names[] = &$$bind_name;
    }
    call_user_func_array([$stmt_count, 'bind_param'], $bind_names);
}

if (!$stmt_count->execute()) { die('Execute failed: ' . htmlspecialchars($stmt_count->error)); }
$count_res = $stmt_count->get_result()->fetch_assoc();
$total_rows = isset($count_res['cnt']) ? (int)$count_res['cnt'] : 0;

// pagination
$per_page = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

$sql = "SELECT fm.*, COALESCE(f.name, '') AS family_name FROM family_members fm LEFT JOIN families f ON fm.family_id = f.id";
if (!empty($where)) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY fm.name ASC';
$sql .= ' LIMIT ? OFFSET ?';

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die('Prepare failed: ' . htmlspecialchars($conn->error));
}

// bind params including limit/offset
$types_with_limit = $types . 'ii';
$bind_names = [];
$bind_names[] = $types_with_limit;
for ($i = 0; $i < count($params); $i++) {
    $bind_name = 'b' . $i;
    $$bind_name = $params[$i];
    $bind_names[] = &$$bind_name;
}
$bind_limit = 'lim'; $$bind_limit = $per_page; $bind_offset = 'off'; $$bind_offset = $offset;
$bind_names[] = &$$bind_limit;
$bind_names[] = &$$bind_offset;
call_user_func_array([$stmt, 'bind_param'], $bind_names);

if (!$stmt->execute()) { die('Execute failed: ' . htmlspecialchars($stmt->error)); }

$res = $stmt->get_result();

// default SVG avatars (data URIs) for male/female
$svgMale = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><rect width="100%" height="100%" fill="#e6f0ff"/><text x="50%" y="50%" font-size="72" text-anchor="middle" dy=".35em" fill="#2c7be5">♂</text></svg>';
$svgFemale = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><rect width="100%" height="100%" fill="#fff0f6"/><text x="50%" y="50%" font-size="72" text-anchor="middle" dy=".35em" fill="#e83e8c">♀</text></svg>';
$maleDataUri = 'data:image/svg+xml;utf8,' . rawurlencode($svgMale);
$femaleDataUri = 'data:image/svg+xml;utf8,' . rawurlencode($svgFemale);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Members List</title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        .filter-box { max-width:1000px; margin:16px auto; padding:12px; background:#fff; border-radius:8px; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:8px; border-bottom:1px solid #eee; text-align:left; }
        img.thumb { width:64px; height:64px; object-fit:cover; border-radius:6px; }
        .actions a { margin-right:8px; }
    </style>
</head>
<body>
    <h2 style="text-align:center">Members - List / Filter</h2>
    <div style="text-align:center; margin-bottom:16px;">
        <a href="index.php" class="button">View Family Tree</a>
    </div>
    <div class="filter-box">
        <form method="GET">
            <label>Family:
                <select name="family_id">
                    <option value="">-- Any --</option>
                    <?php foreach ($families as $f): ?>
                        <option value="<?php echo (int)$f['id']; ?>" <?php echo ($family_id == $f['id'])? 'selected':''; ?>><?php echo htmlspecialchars($f['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            &nbsp;&nbsp;
            <label>Gotra: <input type="text" name="gotra" value="<?php echo htmlspecialchars($gotra); ?>"></label>
            &nbsp;&nbsp;
            <label>Caste: <input type="text" name="caste" value="<?php echo htmlspecialchars($caste); ?>"></label>
            <br style="margin:6px 0;">
            <label>Education: <input type="text" name="education" value="<?php echo htmlspecialchars($education); ?>"></label>
            &nbsp;&nbsp;
            <label>Address: <input type="text" name="address" value="<?php echo htmlspecialchars($address); ?>"></label>
            <br style="margin:6px 0;">
            <label>DOB from: <input type="date" name="dob_from" value="<?php echo htmlspecialchars($dob_from); ?>"></label>
            &nbsp;&nbsp;
            <label>DOB to: <input type="date" name="dob_to" value="<?php echo htmlspecialchars($dob_to); ?>"></label>
            <br style="margin-top:8px;">
            <button type="submit">Filter</button>
            <a href="members_list.php" style="margin-left:8px">Reset</a>
        </form>
    </div>

    <div style="max-width:1100px;margin:12px auto;background:#fff;border-radius:8px;padding:12px;">
        <table>
            <thead>
                <tr>
                    <th>Photo</th>
                    <th>Name</th>
                    <th>Gender</th>
                    <th>DOB</th>
                    <th>Gotra</th>
                    <th>Caste</th>
                    <th>Education</th>
                    <th>Address</th>
                    <th>Family</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $res->fetch_assoc()): ?>
                    <?php
                        $img = !empty($row['photo']) ? 'uploads/' . $row['photo'] : ($row['gender'] === 'female' ? $femaleDataUri : $maleDataUri);
                    ?>
                    <tr>
                        <td><img class="thumb" src="<?php echo htmlspecialchars($img); ?>" alt=""></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['gender']); ?></td>
                        <td><?php echo htmlspecialchars($row['date_of_birth'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['gotra'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['caste'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['education'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['address'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['family_name'] ?? ''); ?></td>
                        <td class="actions">
                            <a href="view_member.php?id=<?php echo (int)$row['id']; ?>">View</a>
                            <?php if (isset($_SESSION['admin_id'])): ?>
                                <a href="admin/edit_member.php?id=<?php echo (int)$row['id']; ?>">Edit</a>
                            <?php elseif (isset($_SESSION['member_family_id']) && $_SESSION['member_family_id'] == $row['family_id']): ?>
                                <a href="edit_member_front.php?id=<?php echo (int)$row['id']; ?>">Edit</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

</body>
</html>
