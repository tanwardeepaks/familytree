<?php
session_start();
include 'db.php';

if (!isset($_SESSION['member_id'])) {
    header('Location: member_login.php');
    exit;
}

$owner_id = (int)$_SESSION['member_id'];
// load owner to get family
$stmt = $conn->prepare("SELECT id, name, family_id FROM family_members WHERE id = ?");
$stmt->bind_param('i', $owner_id);
$stmt->execute();
$owner = $stmt->get_result()->fetch_assoc();
if (!$owner) {
    session_destroy();
    header('Location: member_login.php');
    exit;
}
$family_id = $owner['family_id'];

// Get potential relationships (members in same family)
$stmt = $conn->prepare("SELECT id, name, gender FROM family_members WHERE family_id = ? AND id != ? ORDER BY name");
$stmt->bind_param('ii', $family_id, $owner_id);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch all families so owner can optionally override family assignment
$families = [];
// include gotra/caste metadata
$res = $conn->query("SELECT id, name, gotra, caste FROM families ORDER BY name");
while ($r = $res->fetch_assoc()) $families[] = $r;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $father_id = !empty($_POST['father_id']) ? (int)$_POST['father_id'] : null;
    $mother_id = !empty($_POST['mother_id']) ? (int)$_POST['mother_id'] : null;
    $spouse_id = !empty($_POST['spouse_id']) ? (int)$_POST['spouse_id'] : null;
    $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $birth_time = !empty($_POST['birth_time']) ? $_POST['birth_time'] : null;
    $birth_place = trim($_POST['birth_place'] ?? '');
    $education = trim($_POST['education'] ?? '');
    $occupation = trim($_POST['occupation'] ?? '');
    $mobile_no = trim($_POST['mobile_no'] ?? '');
    $address = trim($_POST['address'] ?? '');
        $gotra = trim($_POST['gotra'] ?? '');
        $caste = trim($_POST['caste'] ?? '');
        $status = trim($_POST['status'] ?? 'unmarried');

    // Handle photo upload
    $photo = null;
    if (!empty($_FILES['photo']['name'])) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        if (!in_array($_FILES['photo']['type'], $allowed_types)) {
            $error = 'Invalid photo type. Allowed: JPG, PNG, GIF';
        } elseif ($_FILES['photo']['size'] > $max_size) {
            $error = 'Photo too large (max 5MB)';
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

    if (empty($name) || empty($gender)) {
        $error = 'Name and gender are required';
    } else {
        $conn->begin_transaction();
        try {
            // Validate relationships
            if ($father_id) {
                $check = $conn->prepare("SELECT id, family_id FROM family_members WHERE id = ? AND gender = 'male'");
                $check->bind_param('i', $father_id);
                $check->execute();
                $frow = $check->get_result()->fetch_assoc();
                if (!$frow) throw new Exception('Invalid father selection');
                $paternal_family_id = $frow['family_id'] ? (int)$frow['family_id'] : null;
            } else {
                $paternal_family_id = null;
            }
            if ($mother_id) {
                $check = $conn->prepare("SELECT id, family_id FROM family_members WHERE id = ? AND gender = 'female'");
                $check->bind_param('i', $mother_id);
                $check->execute();
                $mrow = $check->get_result()->fetch_assoc();
                if (!$mrow) throw new Exception('Invalid mother selection');
                $maternal_family_id = $mrow['family_id'] ? (int)$mrow['family_id'] : null;
            } else {
                $maternal_family_id = null;
            }
            if ($spouse_id) {
                $check = $conn->prepare("SELECT id FROM family_members WHERE id = ?");
                $check->bind_param('i', $spouse_id);
                $check->execute();
                if (!$check->get_result()->fetch_assoc()) throw new Exception('Invalid spouse selection');
            }

            // Default child.family_id to father's family if available, otherwise mother's family if available
            // Unless owner explicitly requested an override via the form checkbox
            if (isset($_POST['override_family']) && $_POST['override_family'] == '1') {
                // Owner chose to set family manually
                $family_id = !empty($_POST['family_id_override']) ? (int)$_POST['family_id_override'] : $family_id;
            } else {
                if (!empty($paternal_family_id)) {
                    $family_id = $paternal_family_id;
                } elseif (!empty($maternal_family_id) && empty($family_id)) {
                    $family_id = $maternal_family_id;
                }
            }

                // If gotra/caste not provided, try to fill from selected family
                if (empty($gotra) && !empty($family_id)) {
                    foreach ($families as $f) {
                        if ($f['id'] == $family_id) {
                            if (!empty($f['gotra'])) $gotra = $f['gotra'];
                            if (!empty($f['caste'])) $caste = $f['caste'];
                            break;
                        }
                    }
                }

                $stmt = $conn->prepare("INSERT INTO family_members (name, gender, family_id, paternal_family_id, maternal_family_id, father_id, mother_id, spouse_id, photo, date_of_birth, birth_time, birth_place, education, occupation, mobile_no, address, gotra, caste, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            // types: name(s), gender(s), family_id(i), paternal(i), maternal(i), father(i), mother(i), spouse(i), then strings for remaining fields
                $stmt->bind_param('ssiiiiii' . 'sssssssssss', $name, $gender, $family_id, $paternal_family_id, $maternal_family_id, $father_id, $mother_id, $spouse_id, $photo, $date_of_birth, $birth_time, $birth_place, $education, $occupation, $mobile_no, $address, $gotra, $caste, $status);

            if (!$stmt->execute()) throw new Exception('Failed to insert member: ' . $conn->error);

            $new_id = $conn->insert_id;

            // Handle spouse reciprocal update
            if ($spouse_id) {
                // Clear existing spouse references for the chosen spouse
                $stmt = $conn->prepare("UPDATE family_members SET spouse_id = NULL WHERE id = ? OR spouse_id = ?");
                $stmt->bind_param('ii', $spouse_id, $spouse_id);
                if (!$stmt->execute()) throw new Exception('Failed clearing spouse references');

                // Set spouse_id for the spouse to this new member
                $stmt = $conn->prepare("UPDATE family_members SET spouse_id = ? WHERE id = ?");
                $stmt->bind_param('ii', $new_id, $spouse_id);
                if (!$stmt->execute()) throw new Exception('Failed setting spouse reference');
            }

            $conn->commit();
            header('Location: index.php?family_id=' . $family_id);
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Add Family Member</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
<div class="container">
    <h2>Add Family Member to "<?php echo htmlspecialchars($owner['name']); ?>'s" family</h2>
    <?php if (isset($error)): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label>Name</label>
            <input type="text" name="name" required>
        </div>
        <div class="form-group">
            <label>Gender</label>
            <select name="gender" required>
                <option value="">Select</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
            </select>
        </div>
        <div class="form-group">
            <label>Photo</label>
            <input type="file" name="photo" accept="image/*">
        </div>
        <div class="form-group">
            <label>Father</label>
            <select name="father_id">
                <option value="">None</option>
                <?php foreach ($members as $m): if ($m['gender']==='male'): ?>
                    <option value="<?php echo $m['id'] ?>"><?php echo htmlspecialchars($m['name']) ?></option>
                <?php endif; endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Mother</label>
            <select name="mother_id">
                <option value="">None</option>
                <?php foreach ($members as $m): if ($m['gender']==='female'): ?>
                    <option value="<?php echo $m['id'] ?>"><?php echo htmlspecialchars($m['name']) ?></option>
                <?php endif; endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Spouse</label>
            <select name="spouse_id">
                <option value="">None</option>
                <?php foreach ($members as $m): ?>
                    <option value="<?php echo $m['id'] ?>"><?php echo htmlspecialchars($m['name']) ?> (<?php echo ucfirst($m['gender']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" id="override_block" style="display:none;">
            <label>Family assignment</label>
            <div style="font-size:0.95em;color:#444;margin-bottom:6px;">By default a new child's record will be assigned to the father's family (if a father is selected). You can override and choose a different family below.</div>
            <label style="display:inline-block;margin-right:10px;">
                <input type="checkbox" name="override_family" id="override_family" value="1"> Override family assignment
            </label>
            <select name="family_id_override" id="family_id_override" style="display:none;margin-top:8px;">
                <option value="">Select Family (override)</option>
                <?php foreach ($families as $f): ?>
                    <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Date of Birth</label>
            <input type="date" name="date_of_birth">
        </div>
        <div class="form-group">
            <label>Birth Time</label>
            <input type="time" name="birth_time">
        </div>
        <div class="form-group">
            <label>Birth Place</label>
            <input type="text" name="birth_place">
        </div>
        <div class="form-group">
            <label>Education</label>
            <textarea name="education" rows="2"></textarea>
        </div>
        <div class="form-group">
            <label>Occupation</label>
            <input type="text" name="occupation">
        </div>
        <div class="form-group">
            <label>Mobile Number</label>
            <input type="tel" name="mobile_no">
        </div>
        <div class="form-group">
            <label>Address</label>
            <textarea name="address" rows="3"></textarea>
        </div>
        <div class="form-group">
            <label>Gotra</label>
            <input type="text" id="gotra" name="gotra" value="<?php echo htmlspecialchars($gotra ?? ''); ?>">
        </div>
        <div class="form-group">
            <label>Caste</label>
            <input type="text" id="caste" name="caste" value="<?php echo htmlspecialchars($caste ?? ''); ?>">
        </div>
        <div class="form-group">
            <label>Status</label>
            <select id="status" name="status">
                <option value="unmarried" <?php echo (isset($status) && $status==='unmarried')? 'selected':''; ?>>Unmarried</option>
                <option value="married" <?php echo (isset($status) && $status==='married')? 'selected':''; ?>>Married</option>
                <option value="divorced" <?php echo (isset($status) && $status==='divorced')? 'selected':''; ?>>Divorced</option>
                <option value="widow" <?php echo (isset($status) && $status==='widow')? 'selected':''; ?>>Widow</option>
            </select>
        </div>
        <div class="actions">
            <button class="btn" type="submit">Add Member</button>
            <a class="btn btn-secondary" href="member_dashboard.php">Cancel</a>
        </div>
    </form>
</div>
</div>
<script>
    // Toggle override family select visibility
    (function(){
        var cb = document.getElementById('override_family');
        var sel = document.getElementById('family_id_override');
        var block = document.getElementById('override_block');
        var father = document.getElementById('father_id');
        var mother = document.getElementById('mother_id');
        // family metadata map
        var familyMeta = {};
        <?php foreach ($families as $f): ?>
            familyMeta[<?php echo (int)$f['id']; ?>] = { gotra: <?php echo json_encode($f['gotra']); ?>, caste: <?php echo json_encode($f['caste']); ?> };
        <?php endforeach; ?>
        var gotraInput = document.getElementById('gotra');
        var casteInput = document.getElementById('caste');
        if (!cb || !sel || !block) return;

        function updateBlockVisibility() {
            var show = false;
            if (father && father.value) show = true;
            if (mother && mother.value) show = true;
            block.style.display = show ? 'block' : 'none';
            if (!show) {
                // reset override when no parent selected
                cb.checked = false;
                sel.style.display = 'none';
            }
        }

        cb.addEventListener('change', function(){
            sel.style.display = cb.checked ? 'block' : 'none';
        });

        if (father) father.addEventListener('change', updateBlockVisibility);
        if (mother) mother.addEventListener('change', updateBlockVisibility);

        // initial state
        updateBlockVisibility();
        // set gotra/caste when override family changes
        if (sel) sel.addEventListener('change', function(){
            var v = sel.value ? parseInt(sel.value,10) : null;
            if (v && familyMeta[v]) {
                if (!gotraInput.value) gotraInput.value = familyMeta[v].gotra || '';
                if (!casteInput.value) casteInput.value = familyMeta[v].caste || '';
            }
        });

        // If owner has a default family, prefill gotra/caste from it
        (function(){
            var ownerFamily = <?php echo json_encode($family_id); ?>;
            if (ownerFamily && familyMeta[ownerFamily]) {
                if (!gotraInput.value) gotraInput.value = familyMeta[ownerFamily].gotra || '';
                if (!casteInput.value) casteInput.value = familyMeta[ownerFamily].caste || '';
            }
        })();
    })();
</script>
</body>
</html>
