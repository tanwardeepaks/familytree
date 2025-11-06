<?php
session_start();
include '../db.php';

echo "<pre>Running member extra fields migration...\n";

$cols = [
    'gotra' => "ALTER TABLE family_members ADD COLUMN gotra VARCHAR(255) DEFAULT NULL",
    'caste' => "ALTER TABLE family_members ADD COLUMN caste VARCHAR(255) DEFAULT NULL",
    'status' => "ALTER TABLE family_members ADD COLUMN status ENUM('married','unmarried','divorced','widow') NOT NULL DEFAULT 'unmarried'"
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

echo "\nMigration complete.\n</pre>";
echo "<a href=\"../index.php\">Back to site</a>";
