<?php
session_start();
include '../db.php';

echo "<pre>Running paternal/maternal family migration...\n";

$cols = [
    'paternal_family_id' => "ALTER TABLE family_members ADD COLUMN paternal_family_id INT DEFAULT NULL",
    'maternal_family_id' => "ALTER TABLE family_members ADD COLUMN maternal_family_id INT DEFAULT NULL",
];

foreach ($cols as $col => $sql) {
    $res = $conn->query("SHOW COLUMNS FROM family_members LIKE '" . $conn->real_escape_string($col) . "'");
    if ($res && $res->num_rows === 0) {
        if ($conn->query($sql) === TRUE) {
            echo "Added column: $col\n";
        } else {
            echo "Failed to add $col: " . $conn->error . "\n";
        }
    } else {
        echo "Column $col already exists\n";
    }
}

// Backfill paternal/maternal family ids from parents
echo "\nBackfilling paternal_family_id from father relationships...\n";
$res = $conn->query("SELECT c.id AS child_id, f.family_id AS father_family FROM family_members c JOIN family_members f ON c.father_id = f.id WHERE c.father_id IS NOT NULL");
$count = 0;
while ($r = $res->fetch_assoc()) {
    $stmt = $conn->prepare("UPDATE family_members SET paternal_family_id = ? WHERE id = ?");
    $stmt->bind_param('ii', $r['father_family'], $r['child_id']);
    if ($stmt->execute()) $count++;
}
echo "Updated $count rows for paternal_family_id\n";

echo "\nBackfilling maternal_family_id from mother relationships...\n";
$res = $conn->query("SELECT c.id AS child_id, m.family_id AS mother_family FROM family_members c JOIN family_members m ON c.mother_id = m.id WHERE c.mother_id IS NOT NULL");
$count = 0;
while ($r = $res->fetch_assoc()) {
    $stmt = $conn->prepare("UPDATE family_members SET maternal_family_id = ? WHERE id = ?");
    $stmt->bind_param('ii', $r['mother_family'], $r['child_id']);
    if ($stmt->execute()) $count++;
}
echo "Updated $count rows for maternal_family_id\n";

// Optionally set family_id to paternal if family_id is NULL
echo "\nSetting family_id to paternal_family_id where missing...\n";
$res = $conn->query("UPDATE family_members SET family_id = paternal_family_id WHERE family_id IS NULL AND paternal_family_id IS NOT NULL");
echo "Affected rows: " . $conn->affected_rows . "\n";

echo "\nMigration complete.\n</pre>";
echo "<a href=\"dashboard.php\">Back to admin</a>";
