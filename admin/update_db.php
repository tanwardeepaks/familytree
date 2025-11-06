<?php
include '../db.php';

// Add photo column if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM family_members LIKE 'photo'");
if ($result->num_rows === 0) {
    $conn->query("ALTER TABLE family_members ADD COLUMN photo VARCHAR(255) DEFAULT NULL");
    echo "Photo column added successfully.";
} else {
    echo "Photo column already exists.";
}
?>