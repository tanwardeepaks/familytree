<?php
include '../db.php';

// Safe idempotent migration to add families table and family_id column
// and backfill existing members to a default 'Main' family.

// Create families table if not exists
$sql = "CREATE TABLE IF NOT EXISTS families (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) DEFAULT NULL,
    owner_id INT DEFAULT NULL,
    visibility ENUM('private','public') DEFAULT 'private',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

// Add family_id column if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM family_members LIKE 'family_id'");
if ($result->num_rows === 0) {
    $conn->query("ALTER TABLE family_members ADD COLUMN family_id INT DEFAULT NULL");
    echo "Added family_id column to family_members.<br>";
} else {
    echo "family_id column already exists.<br>";
}

// Create default Main family if not exists
$check = $conn->query("SELECT id FROM families WHERE slug = 'main' LIMIT 1");
if ($check->num_rows === 0) {
    $conn->query($conn->prepare("INSERT INTO families (name, slug, visibility) VALUES (?, ?, ?)")->bind_param('sss', $name, $slug, $vis) ?? '');
    // fallback simple insert
    $conn->query("INSERT INTO families (name, slug, visibility) VALUES ('Main', 'main', 'public')");
    echo "Created default 'Main' family.<br>";
}

// Get main id
$res = $conn->query("SELECT id FROM families WHERE slug = 'main' LIMIT 1");
$main = $res->fetch_assoc();
$main_id = $main ? (int)$main['id'] : null;

if ($main_id) {
    // Backfill existing members where family_id is NULL
    $conn->query("UPDATE family_members SET family_id = $main_id WHERE family_id IS NULL");
    echo "Backfilled existing members to Main family (id=$main_id).<br>";
} else {
    echo "Failed to determine Main family id.<br>";
}

echo "Migration completed.";
?>