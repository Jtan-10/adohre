<?php
require_once 'backend/db/db_connect.php';

echo "Checking database tables...\n";

// Check if user_settings table exists
$result = $conn->query("SHOW TABLES LIKE 'user_settings'");
if ($result->num_rows > 0) {
    echo "✓ user_settings table exists\n";

    // Check table structure
    $result = $conn->query("DESCRIBE user_settings");
    echo "user_settings columns:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "✗ user_settings table does not exist\n";
    echo "Creating user_settings table...\n";

    $sql = "CREATE TABLE user_settings (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        otp_enabled TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_user (user_id),
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql) === TRUE) {
        echo "✓ user_settings table created successfully\n";
    } else {
        echo "✗ Error creating table: " . $conn->error . "\n";
    }
}

// Check users table structure
echo "\nChecking users table...\n";
$result = $conn->query("DESCRIBE users");
$usersColumns = [];
while ($row = $result->fetch_assoc()) {
    $usersColumns[] = $row['Field'];
    echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
}

// Check for missing columns
$requiredColumns = ['password_hash', 'is_profile_complete', 'otp_code', 'otp_expiry'];
$missingColumns = [];

foreach ($requiredColumns as $column) {
    if (!in_array($column, $usersColumns)) {
        $missingColumns[] = $column;
    }
}

if (!empty($missingColumns)) {
    echo "\nMissing columns in users table: " . implode(', ', $missingColumns) . "\n";
    echo "Adding missing columns...\n";

    foreach ($missingColumns as $column) {
        $sql = "";
        switch ($column) {
            case 'password_hash':
                $sql = "ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL";
                break;
            case 'is_profile_complete':
                $sql = "ALTER TABLE users ADD COLUMN is_profile_complete TINYINT(1) NOT NULL DEFAULT 0";
                break;
            case 'otp_code':
                $sql = "ALTER TABLE users ADD COLUMN otp_code VARCHAR(10) DEFAULT NULL";
                break;
            case 'otp_expiry':
                $sql = "ALTER TABLE users ADD COLUMN otp_expiry DATETIME DEFAULT NULL";
                break;
        }

        if (!empty($sql)) {
            if ($conn->query($sql) === TRUE) {
                echo "✓ Added column: $column\n";
            } else {
                echo "✗ Error adding column $column: " . $conn->error . "\n";
            }
        }
    }
} else {
    echo "✓ All required columns exist in users table\n";
}

echo "\nDatabase check completed.\n";
