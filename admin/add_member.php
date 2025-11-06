<?php
session_start();
include '../db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $family_id = !empty($_POST['family_id']) ? (int)$_POST['family_id'] : null;
    $father_id = !empty($_POST['father_id']) ? (int)$_POST['father_id'] : null;
    $mother_id = !empty($_POST['mother_id']) ? (int)$_POST['mother_id'] : null;
    $spouse_id = !empty($_POST['spouse_id']) ? (int)$_POST['spouse_id'] : null;
    $gotra = trim($_POST['gotra'] ?? '');
    $caste = trim($_POST['caste'] ?? '');
    $status = trim($_POST['status'] ?? 'unmarried');
    $spouse_id = !empty($_POST['spouse_id']) ? (int)$_POST['spouse_id'] : null;
    
    if (empty($name) || empty($gender)) {
        $error = "Name and gender are required";
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Determine paternal/maternal family ids from selected parents
            $paternal_family_id = null;
            $maternal_family_id = null;
            if ($father_id) {
                $s = $conn->prepare('SELECT family_id FROM family_members WHERE id = ?');
                $s->bind_param('i', $father_id); $s->execute();
                $fr = $s->get_result()->fetch_assoc();
                if ($fr) $paternal_family_id = $fr['family_id'] ? (int)$fr['family_id'] : null;
                // default family to father's family
                if ($paternal_family_id) $family_id = $paternal_family_id;
            }
            if ($mother_id) {
                $s = $conn->prepare('SELECT family_id FROM family_members WHERE id = ?');
                $s->bind_param('i', $mother_id); $s->execute();
                $mr = $s->get_result()->fetch_assoc();
                if ($mr) $maternal_family_id = $mr['family_id'] ? (int)$mr['family_id'] : null;
                // if no father family and mother family exists, default to mother
                if (empty($family_id) && $maternal_family_id) $family_id = $maternal_family_id;
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

            $sql = "INSERT INTO family_members (name, gender, family_id, paternal_family_id, maternal_family_id, father_id, mother_id, spouse_id, gotra, caste, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiiiiiiiss", $name, $gender, $family_id, $paternal_family_id, $maternal_family_id, $father_id, $mother_id, $spouse_id, $gotra, $caste, $status);
            
            if ($stmt->execute()) {
                $new_member_id = $conn->insert_id;
                
                // Update spouse's spouse_id if set
                if ($spouse_id) {
                    // Remove any existing spouse
                    $conn->query("UPDATE family_members SET spouse_id = NULL WHERE spouse_id = $spouse_id");
                    // Set new spouse relationship
                    $conn->query("UPDATE family_members SET spouse_id = $new_member_id WHERE id = $spouse_id");
                }
                
                $conn->commit();
                header('Location: dashboard.php');
                exit;
            } else {
                throw new Exception($conn->error);
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error adding member: " . $e->getMessage();
        }
    }
}

// Get all potential family members
$sql = "SELECT id, name, gender, family_id FROM family_members ORDER BY name";
$result = $conn->query($sql);
$members = [];
while ($row = $result->fetch_assoc()) {
    $members[] = $row;
}

// Fetch families for selection (include gotra/caste metadata)
$families = [];
$res = $conn->query("SELECT id, name, gotra, caste FROM families ORDER BY name");
while ($r = $res->fetch_assoc()) $families[] = $r;

// default family from GET
$defaultFamily = isset($_GET['family_id']) ? (int)$_GET['family_id'] : null;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Family Member</title>
    <meta charset="utf-8">
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
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 16px;
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
        .error {
            color: red;
            margin-bottom: 15px;
            padding: 10px;
            background: #fee;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Add New Family Member</h2>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" id="name" name="name" required>
            </div>
            
            <div class="form-group">
                <label for="family_id">Family:</label>
                <select id="family_id" name="family_id" required>
                    <option value="">Select Family</option>
                    <?php foreach ($families as $f): ?>
                        <option value="<?php echo $f['id']; ?>" <?php echo ($defaultFamily && $defaultFamily == $f['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($f['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <small style="display:block;margin-top:6px;color:#666;">Note: If a father is selected below, the child's family will default to the father's family. To override, pick a different family above.</small>
            </div>
            
            <div class="form-group">
                <label for="gender">Gender:</label>
                <select id="gender" name="gender" required>
                    <option value="">Select Gender</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                </select>
            </div>

            <div class="form-group">
                <label for="gotra">Gotra</label>
                <input type="text" id="gotra" name="gotra">
            </div>
            <div class="form-group">
                <label for="caste">Caste</label>
                <input type="text" id="caste" name="caste">
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="unmarried">Unmarried</option>
                    <option value="married">Married</option>
                    <option value="divorced">Divorced</option>
                    <option value="widow">Widow</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="father_id">Father:</label>
                <select id="father_id" name="father_id">
                    <option value="">Select Father</option>
                    <?php foreach ($members as $m): ?>
                        <?php if ($m['gender'] === 'male'): ?>
                            <option value="<?php echo $m['id']; ?>">
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
                        <?php if ($m['gender'] === 'female'): ?>
                            <option value="<?php echo $m['id']; ?>">
                                <?php echo htmlspecialchars($m['name']); ?> (ID: <?php echo $m['id']; ?>)
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="spouse_id">Spouse:</label>
                <select id="spouse_id" name="spouse_id">
                    <option value="">Select Spouse</option>
                    <?php foreach ($members as $m): ?>
                        <option value="<?php echo $m['id']; ?>">
                            <?php echo htmlspecialchars($m['name']); ?> (ID: <?php echo $m['id']; ?>) - <?php echo ucfirst($m['gender']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="actions">
                <button type="submit" class="btn">Add Member</button>
                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    <script>
        // Family metadata map for autofill
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
                    // Autofill only if empty to avoid overwriting manual edits
                    if (!gotra.value) gotra.value = familyMeta[v].gotra || '';
                    if (!caste.value) caste.value = familyMeta[v].caste || '';
                }
            });
            // trigger once on load if a default family is preselected
            if (fam.value) fam.dispatchEvent(new Event('change'));
        })();
    </script>
</body>
</html>