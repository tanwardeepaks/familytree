<?php
include 'db.php';

$name = trim($_POST['name'] ?? '');
$gender = $_POST['gender'] ?? '';
$father_id = $_POST['father_id'] ?? null;
$mother_id = $_POST['mother_id'] ?? null;
$spouse_id = $_POST['spouse_id'] ?? null;

if ($father_id === '') $father_id = null;
if ($mother_id === '') $mother_id = null;
if ($spouse_id === '') $spouse_id = null;

if ($name === '' || ($gender !== 'male' && $gender !== 'female')) {
    die('Invalid input');
}

// Build dynamic SQL to allow nulls
$sql = "INSERT INTO family_members (name, gender";
$values = [];
$placeholders = [];

$values[] = $name;
$values[] = $gender;
$placeholders[] = '?';
$placeholders[] = '?';

if ($father_id !== null) { $sql .= ', father_id'; $placeholders[] = '?'; $values[] = (int)$father_id; }
if ($mother_id !== null) { $sql .= ', mother_id'; $placeholders[] = '?'; $values[] = (int)$mother_id; }
if ($spouse_id !== null) { $sql .= ', spouse_id'; $placeholders[] = '?'; $values[] = (int)$spouse_id; }

$sql .= ') VALUES (' . implode(', ', $placeholders) . ')';

$stmt = $conn->prepare($sql);
if ($stmt === false) { die('Prepare failed: ' . htmlspecialchars($conn->error)); }

// bind params dynamically
$types = '';
foreach ($values as $v) { $types .= is_int($v) ? 'i' : 's'; }
$bind_names = [];
$bind_names[] = $types;
for ($i=0;$i<count($values);$i++) {
    $bind_name = 'bind' . $i;
    $$bind_name = $values[$i];
    $bind_names[] = &$$bind_name;
}
call_user_func_array([$stmt, 'bind_param'], $bind_names);

if (!$stmt->execute()) {
    die('Execute failed: ' . htmlspecialchars($stmt->error));
}
$stmt->close();

header('Location: index.php');
exit;
?>