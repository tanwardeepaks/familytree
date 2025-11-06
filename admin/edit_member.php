<?php
session_start();
error_reporting(1);
include '../db.php';

// NOTE: error reporting was temporarily enabled during debugging and has been removed.

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header('Location: dashboard.php');
    exit;
}

// Get member details
$stmt = $conn->prepare("SELECT *, 
    DATE_FORMAT(date_of_birth, '%Y-%m-%d') as dob_formatted,
    TIME_FORMAT(birth_time, '%H:%i') as birth_time_formatted
    FROM family_members WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
if (!$member) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $family_id = !empty($_POST['family_id']) ? (int)$_POST['family_id'] : null;
    $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $birth_time = !empty($_POST['birth_time']) ? $_POST['birth_time'] : null;
    $birth_place = trim($_POST['birth_place'] ?? '');
    $education = trim($_POST['education'] ?? '');
    $occupation = trim($_POST['occupation'] ?? '');
    $mobile_no = trim($_POST['mobile_no'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $father_id = !empty($_POST['father_id']) ? (int)$_POST['father_id'] : null;
    $mother_id = !empty($_POST['mother_id']) ? (int)$_POST['mother_id'] : null;
    $spouse_id = !empty($_POST['spouse_id']) ? (int)$_POST['spouse_id'] : null;
    $gotra = trim($_POST['gotra'] ?? '');
    $caste = trim($_POST['caste'] ?? '');
    $status = trim($_POST['status'] ?? 'unmarried');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Handle photo upload
    $photo = $member['photo']; // Keep existing photo by default
    
    // Check if photo should be removed
    if (isset($_POST['remove_photo']) && $_POST['remove_photo'] == '1') {
        if (!empty($member['photo']) && file_exists("../uploads/" . $member['photo'])) {
            unlink("../uploads/" . $member['photo']);
        }
        $photo = null;
    }
    
    // Handle new photo upload
    if (!empty($_FILES['photo']['name'])) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['photo']['type'], $allowed_types)) {
            $error = "Invalid file type. Please upload a JPG, PNG or GIF image.";
        } elseif ($_FILES['photo']['size'] > $max_size) {
            $error = "File is too large. Maximum size is 5MB.";
        } else {
            // Generate unique filename
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '_' . time() . '.' . $ext;
            
            // Remove old photo if exists
            if (!empty($member['photo']) && file_exists("../uploads/" . $member['photo'])) {
                unlink("../uploads/" . $member['photo']);
            }
            
            // Upload new photo
            if (move_uploaded_file($_FILES['photo']['tmp_name'], "../uploads/" . $filename)) {
                $photo = $filename;
            } else {
                $error = "Failed to upload photo. Please try again.";
            }
        }
    }
    
    if (empty($name) || empty($gender)) {
        $error = "Name and gender are required";
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Validate relationships
            if ($father_id) {
                $check = $conn->prepare("SELECT id FROM family_members WHERE id = ? AND gender = 'male'");
                $check->bind_param("i", $father_id);
                $check->execute();
                if (!$check->get_result()->fetch_assoc()) {
                    throw new Exception("Invalid father selection");
                }
            }
            
            if ($mother_id) {
                $check = $conn->prepare("SELECT id FROM family_members WHERE id = ? AND gender = 'female'");
                $check->bind_param("i", $mother_id);
                $check->execute();
                if (!$check->get_result()->fetch_assoc()) {
                    throw new Exception("Invalid mother selection");
                }
            }

            if ($spouse_id) {
                $check = $conn->prepare("SELECT id FROM family_members WHERE id = ?");
                $check->bind_param("i", $spouse_id);
                $check->execute();
                if (!$check->get_result()->fetch_assoc()) {
                    throw new Exception("Invalid spouse selection");
                }
            }

            // Determine paternal/maternal family ids from selected parents
            $paternal_family_id = null;
            $maternal_family_id = null;
            if ($father_id) {
                $s = $conn->prepare('SELECT family_id FROM family_members WHERE id = ?');
                $s->bind_param('i', $father_id); $s->execute();
                $fr = $s->get_result()->fetch_assoc();
                if ($fr) $paternal_family_id = $fr['family_id'] ? (int)$fr['family_id'] : null;
            }
            if ($mother_id) {
                $s = $conn->prepare('SELECT family_id FROM family_members WHERE id = ?');
                $s->bind_param('i', $mother_id); $s->execute();
                $mr = $s->get_result()->fetch_assoc();
                if ($mr) $maternal_family_id = $mr['family_id'] ? (int)$mr['family_id'] : null;
            }

            // Default family assignment: if no explicit family_id provided, prefer paternal then maternal
            if (empty($family_id)) {
                if (!empty($paternal_family_id)) $family_id = $paternal_family_id;
                elseif (!empty($maternal_family_id)) $family_id = $maternal_family_id;
            }

            // Update member (include password and is_active)
            $update_sql = "UPDATE family_members SET 
                name = ?, email = ?, gender = ?, father_id = ?, mother_id = ?, spouse_id = ?, family_id = ?, paternal_family_id = ?, maternal_family_id = ?,
                photo = ?, date_of_birth = ?, birth_time = ?, birth_place = ?,
                education = ?, occupation = ?, mobile_no = ?, address = ?, gotra = ?, caste = ?, status = ?, is_active = ?";
            $params = [
                $name, $email, $gender, $father_id, $mother_id, $spouse_id, $family_id, $paternal_family_id, $maternal_family_id, $photo,
                $date_of_birth, $birth_time, $birth_place,
                $education, $occupation, $mobile_no, $address, $gotra, $caste, $status, $is_active
            ];
            $types = "sssiiiiiissssssssssii";
            if (!empty($password)) {
                $update_sql .= ", password = ?";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
                $types .= "s";
            }
            $update_sql .= " WHERE id = ?";
            $params[] = $id;
            $types .= "i";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param($types, ...$params);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update member details");
            }
            
            // Handle spouse relationships
            // First, clear the old spouse's reference if it exists
            if ($member['spouse_id'] !== null) {
                $stmt = $conn->prepare("UPDATE family_members SET spouse_id = NULL WHERE id = ?");
                $stmt->bind_param("i", $member['spouse_id']);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update old spouse reference");
                }
            }
            
            // Then handle the new spouse relationship
            if ($spouse_id !== null) {
                // Clear any existing spouse of the new spouse
                $stmt = $conn->prepare("UPDATE family_members SET spouse_id = NULL WHERE id = ? OR spouse_id = ?");
                $stmt->bind_param("ii", $spouse_id, $spouse_id);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to clear existing spouse references");
                }

                // Set the new bi-directional spouse relationship
                $stmt = $conn->prepare("UPDATE family_members SET spouse_id = ? WHERE id = ?");
                
                // Set spouse_id for the current member
                $stmt->bind_param("ii", $spouse_id, $id);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update member's spouse reference");
                }
                
                // Set spouse_id for the spouse
                $stmt->bind_param("ii", $id, $spouse_id);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update spouse's reference");
                }
            }
            
            $conn->commit();
            header('Location: dashboard.php');
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error updating member: " . $e->getMessage();
        }
    }
}

// Get all potential parents and spouses
$stmt = $conn->prepare("SELECT id, name, gender, spouse_id FROM family_members WHERE id != ? ORDER BY name");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$members = [];
while ($row = $result->fetch_assoc()) {
    $members[] = $row;
}

// Get current relationships
$relationships = [
    'father' => $member['father_id'] ? $conn->query("SELECT name FROM family_members WHERE id = " . (int)$member['father_id'])->fetch_assoc() : null,
    'mother' => $member['mother_id'] ? $conn->query("SELECT name FROM family_members WHERE id = " . (int)$member['mother_id'])->fetch_assoc() : null,
    'spouse' => $member['spouse_id'] ? $conn->query("SELECT name FROM family_members WHERE id = " . (int)$member['spouse_id'])->fetch_assoc() : null
];

// Fetch families for selection
$families = [];
$res = $conn->query("SELECT id, name, gotra, caste FROM families ORDER BY name");
while ($r = $res->fetch_assoc()) $families[] = $r;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Family Member</title>
    <meta charset="utf-8">
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f7f7f7;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h2 {
            color: #4CAF50;
            margin-top: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
                font-size: 0.8em;
                color: #666;
        }
            font-size: 0.8em;
            color: #666;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: bold;
        }
            input[type="text"],
            input[type="date"],
            input[type="time"],
            input[type="tel"],
            textarea,
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 16px;
        }
            textarea {
                resize: vertical;
                min-height: 60px;
            }
            .form-group small {
                display: block;
                color: #666;
                margin-top: 4px;
                font-size: 0.9em;
            }
        .current-value {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 16px;
            margin-right: 10px;
        }
        .btn:hover {
            background: #45a049;
        }
        .btn-secondary {
            background: #666;
        }
        .btn-secondary:hover {
            background: #555;
        }
        .btn-delete {
            background: #f44336;
            float: right;
        }
        .btn-delete:hover {
            background: #da190b;
        }
        .error {
            color: red;
            margin-bottom: 15px;
            padding: 10px;
            background: #fee;
            border-radius: 4px;
        }
        .actions {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .relationship-info {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .relationship-info h3 {
            margin: 0 0 10px 0;
            color: #333;
        }
        .relationship-item {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>
            Edit Family Member 
            <span class="member-id">ID: <?php echo $id; ?></span>
        </h2>
        
        <div class="relationship-info">
            <h3>Current Relationships</h3>
            <div class="relationship-item">
                <strong>Father:</strong> 
                <?php echo $relationships['father'] ? htmlspecialchars($relationships['father']['name']) : 'None'; ?>
            </div>
            <div class="relationship-item">
                <strong>Mother:</strong> 
                <?php echo $relationships['mother'] ? htmlspecialchars($relationships['mother']['name']) : 'None'; ?>
            </div>
            <div class="relationship-item">
                <strong>Spouse:</strong> 
                <?php echo $relationships['spouse'] ? htmlspecialchars($relationships['spouse']['name']) : 'None'; ?>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="email">Email Address (for login):</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($member['email'] ?? ''); ?>" required>
                <small>This email will be used for member login from the frontend.</small>
            </div>
            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($member['name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Set/Change Password:</label>
                <input type="text" id="password" name="password" autocomplete="new-password" placeholder="Leave blank to keep unchanged">
                <small>If you set a password, this member can log in from the frontend. Passwords are stored securely.</small>
            </div>
            <div class="form-group">
                <label for="is_active">
                    <input type="checkbox" id="is_active" name="is_active" value="1" <?php echo ($member['is_active'] ? 'checked' : ''); ?>>
                    Active (Allow login from frontend)
                </label>
            </div>
            
            <div class="form-group">
                <label for="family_id">Family:</label>
                <select id="family_id" name="family_id" required>
                    <option value="">Select Family</option>
                    <?php foreach ($families as $f): ?>
                        <option value="<?php echo $f['id']; ?>" <?php echo ($member['family_id']==$f['id'])? 'selected':''; ?>><?php echo htmlspecialchars($f['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <small style="display:block;margin-top:6px;color:#666;">Note: If you select a father below, the member's family will default to the father's family unless you explicitly choose a different family here.</small>
            </div>
            
            <div class="form-group">
                <label for="photo">Photo:</label>
                <input type="file" id="photo" name="photo" accept="image/*">
                <?php if (!empty($member['photo'])): ?>
                    <div class="current-photo">
                        <img src="../uploads/<?php echo htmlspecialchars($member['photo']); ?>" alt="Current photo" style="max-width: 100px; margin-top: 10px;">
                        <label>
                            <input type="checkbox" name="remove_photo" value="1">
                            Remove current photo
                        </label>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="gender">Gender:</label>
                <select id="gender" name="gender" required>
                    <option value="">Select Gender</option>
                    <option value="male" <?php echo $member['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                    <option value="female" <?php echo $member['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                </select>
            </div>
            
                <div class="form-group">
                    <label for="date_of_birth">Date of Birth:</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" 
                        value="<?php echo $member['dob_formatted']; ?>">
                </div>
            
                <div class="form-group">
                    <label for="birth_time">Birth Time:</label>
                    <input type="time" id="birth_time" name="birth_time" 
                        value="<?php echo $member['birth_time_formatted']; ?>">
                </div>
            
                <div class="form-group">
                    <label for="birth_place">Birth Place:</label>
                    <input type="text" id="birth_place" name="birth_place" 
                        value="<?php echo htmlspecialchars($member['birth_place'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="gotra">Gotra</label>
                    <input type="text" id="gotra" name="gotra" value="<?php echo htmlspecialchars($member['gotra'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="caste">Caste</label>
                    <input type="text" id="caste" name="caste" value="<?php echo htmlspecialchars($member['caste'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="unmarried" <?php echo ($member['status']==='unmarried')? 'selected':''; ?>>Unmarried</option>
                        <option value="married" <?php echo ($member['status']==='married')? 'selected':''; ?>>Married</option>
                        <option value="divorced" <?php echo ($member['status']==='divorced')? 'selected':''; ?>>Divorced</option>
                        <option value="widow" <?php echo ($member['status']==='widow')? 'selected':''; ?>>Widow</option>
                    </select>
                </div>
            
                <div class="form-group">
                    <label for="education">Education:</label>
                    <textarea id="education" name="education" rows="2"><?php echo htmlspecialchars($member['education'] ?? ''); ?></textarea>
                </div>
            
                <div class="form-group">
                    <label for="occupation">Occupation:</label>
                    <input type="text" id="occupation" name="occupation" 
                        value="<?php echo htmlspecialchars($member['occupation'] ?? ''); ?>">
                </div>
            
                <div class="form-group">
                    <label for="mobile_no">Mobile Number:</label>
                    <input type="tel" id="mobile_no" name="mobile_no" 
                        value="<?php echo htmlspecialchars($member['mobile_no'] ?? ''); ?>">
                </div>
            
                <div class="form-group">
                    <label for="address">Address:</label>
                    <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($member['address'] ?? ''); ?></textarea>
                </div>
            
            <div class="form-group">
                <label for="father_id">Father:</label>
                <select id="father_id" name="father_id">
                    <option value="">Select Father</option>
                    <?php foreach ($members as $m): ?>
                        <?php if ($m['gender'] === 'male' && $m['id'] != $member['spouse_id']): ?>
                            <option value="<?php echo $m['id']; ?>" <?php echo $member['father_id'] == $m['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m['name']); ?> (ID: <?php echo $m['id']; ?>)
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="mother_id">Mother:</label>
                <select id="mother_id" name="mother_id">
                    <option value="">Select Mother</option>
                    <?php foreach ($members as $m): ?>
                        <?php if ($m['gender'] === 'female' && $m['id'] != $member['spouse_id']): ?>
                            <option value="<?php echo $m['id']; ?>" <?php echo $member['mother_id'] == $m['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m['name']); ?> (ID: <?php echo $m['id']; ?>)
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="spouse_id">Spouse:</label>
                <select id="spouse_id" name="spouse_id">
                    <option value="">No Spouse</option>
                    <?php 
                    // Filter out inappropriate spouse options (parents, children)
                    foreach ($members as $m): 
                        // Skip if this person is a parent
                        if ($m['id'] == $member['father_id'] || $m['id'] == $member['mother_id']) {
                            continue;
                        }
                        
                        // Skip if this person already has a different spouse
                        if ($m['spouse_id'] !== null && $m['spouse_id'] != $id) {
                            continue;
                        }
                    ?>
                        <option value="<?php echo $m['id']; ?>" 
                                <?php echo ($member['spouse_id'] == $m['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($m['name']); ?> 
                            (ID: <?php echo $m['id']; ?>) - 
                            <?php echo ucfirst($m['gender']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($member['spouse_id']): ?>
                    <div class="current-value">
                        Current Spouse: <?php echo htmlspecialchars($relationships['spouse']['name']); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="actions">
                <div>
                    <button type="submit" class="btn">Update Member</button>
                    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                </div>
                <a href="delete_member.php?id=<?php echo $id; ?>" class="btn btn-delete" 
                   onclick="return confirm('Are you sure you want to delete this member? This will remove all relationships with this person.')">
                    Delete Member
                </a>
            </div>
        </form>
    </div>
    <script>
        var familyMeta = {};
        <?php foreach ($families as $f): ?>
            familyMeta[<?php echo (int)$f['id']; ?>] = { gotra: <?php echo json_encode($f['gotra']); ?>, caste: <?php echo json_encode($f['caste']); ?> };
        <?php endforeach; ?>

        (function(){
            var fam = document.getElementById('family_id');
            var gotra = document.getElementById('gotra');
            var caste = document.getElementById('caste');
            if (!fam || !gotra || !caste) return;
            fam.addEventListener('change', function(){
                var v = fam.value ? parseInt(fam.value,10) : null;
                if (v && familyMeta[v]) {
                    // Autofill when changing family
                    gotra.value = familyMeta[v].gotra || '';
                    caste.value = familyMeta[v].caste || '';
                }
            });
        })();
    </script>
</body>
</html>