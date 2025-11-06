<?php
include '../db.php';

// Create admin users table
$sql = "CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql)) {
    echo "Admin users table created successfully\n";
    
    // Create default admin user (username: admin, password: admin123)
    $username = 'admin';
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    
    $sql = "INSERT IGNORE INTO admin_users (username, password) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $password);
    
    if ($stmt->execute()) {
        echo "Default admin user created successfully\n";
    } else {
        echo "Error creating default admin user: " . $conn->error . "\n";
    }
} else {
    echo "Error creating admin table: " . $conn->error . "\n";
}
?>