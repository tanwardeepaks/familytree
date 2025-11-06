<?php
// Idempotent migration to add authentication fields to family_members
session_start();
include '../db.php';

echo "<pre>Running auth fields migration...\n";

// Add columns if not exist
$cols = [
    'email' => "ALTER TABLE family_members ADD COLUMN email VARCHAR(255) DEFAULT NULL",
    'password' => "ALTER TABLE family_members ADD COLUMN password VARCHAR(255) DEFAULT NULL",
    'is_active' => "ALTER TABLE family_members ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 0",
    'payment_ref' => "ALTER TABLE family_members ADD COLUMN payment_ref VARCHAR(255) DEFAULT NULL",
    'phone' => "ALTER TABLE family_members ADD COLUMN phone VARCHAR(50) DEFAULT NULL"
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

// Ensure families table exists fallback (reuse existing migration logic if needed)
$res = $conn->query("SHOW TABLES LIKE 'families'");
if ($res && $res->num_rows === 0) {
    $create = "CREATE TABLE families (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        slug VARCHAR(255) DEFAULT NULL,
        owner_id INT DEFAULT NULL,
        visibility ENUM('private','public') NOT NULL DEFAULT 'private',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if ($conn->query($create) === TRUE) echo "Created families table\n";
}

echo "\nMigration complete.\n</pre>";
echo "<a href=\"../index.php\">Back to site</a>";
