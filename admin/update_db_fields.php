<?php
include '../db.php';

// Add new columns if they don't exist
$columns = [
    'date_of_birth' => 'DATE',
    'birth_time' => 'TIME',
    'birth_place' => 'VARCHAR(255)',
    'education' => 'TEXT',
    'occupation' => 'VARCHAR(255)',
    'mobile_no' => 'VARCHAR(20)',
    'address' => 'TEXT'
];

foreach ($columns as $column => $type) {
    $result = $conn->query("SHOW COLUMNS FROM family_members LIKE '$column'");
    if ($result->num_rows === 0) {
        $conn->query("ALTER TABLE family_members ADD COLUMN $column $type DEFAULT NULL");
        echo "Added $column column.<br>";
    } else {
        echo "$column column already exists.<br>";
    }
}

echo "Database update completed.";
?>