<?php

/**
 * Database Fix Script for Users Table and User Settings
 * Run this script on your AWS Lightsail instance to fix database issues
 * Execute via command line: php fix_database_complete.php
 */

require_once 'backend/db/db_connect.php';

echo "Checking and fixing database structure...\n";

try {
    // Fix users table
    echo "\n=== Fixing users table ===\n";

    // Check if password_hash column exists
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'password_hash'");
    if ($result->num_rows == 0) {
        echo "Adding password_hash column...\n";
        $conn->query("ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL");
        echo "✓ password_hash column added\n";
    } else {
        echo "✓ password_hash column already exists\n";
    }

    // Check if is_profile_complete column exists
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'is_profile_complete'");
    if ($result->num_rows == 0) {
        echo "Adding is_profile_complete column...\n";
        $conn->query("ALTER TABLE users ADD COLUMN is_profile_complete TINYINT(1) NOT NULL DEFAULT 0");
        echo "✓ is_profile_complete column added\n";
    } else {
        echo "✓ is_profile_complete column already exists\n";
    }

    // Check if otp_code column exists
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'otp_code'");
    if ($result->num_rows == 0) {
        echo "Adding otp_code column...\n";
        $conn->query("ALTER TABLE users ADD COLUMN otp_code VARCHAR(10) DEFAULT NULL");
        echo "✓ otp_code column added\n";
    } else {
        echo "✓ otp_code column already exists\n";
    }

    // Check if otp_expiry column exists
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'otp_expiry'");
    if ($result->num_rows == 0) {
        echo "Adding otp_expiry column...\n";
        $conn->query("ALTER TABLE users ADD COLUMN otp_expiry DATETIME DEFAULT NULL");
        echo "✓ otp_expiry column added\n";
    } else {
        echo "✓ otp_expiry column already exists\n";
    }

    // Check if updated_at column exists
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'updated_at'");
    if ($result->num_rows == 0) {
        echo "Adding updated_at column...\n";
        $conn->query("ALTER TABLE users ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        echo "✓ updated_at column added\n";
    } else {
        echo "✓ updated_at column already exists\n";
    }

    // Check if email has unique constraint
    $result = $conn->query("SHOW INDEX FROM users WHERE Column_name = 'email' AND Non_unique = 0");
    if ($result->num_rows == 0) {
        echo "Adding unique constraint on email...\n";
        $conn->query("ALTER TABLE users ADD CONSTRAINT unique_email UNIQUE (email)");
        echo "✓ Unique constraint on email added\n";
    } else {
        echo "✓ Unique constraint on email already exists\n";
    }

    // Create user_settings table if it doesn't exist
    echo "\n=== Checking user_settings table ===\n";
    $result = $conn->query("SHOW TABLES LIKE 'user_settings'");
    if ($result->num_rows == 0) {
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

        if ($conn->query($sql)) {
            echo "✓ user_settings table created successfully\n";
        } else {
            echo "✗ Error creating user_settings table: " . $conn->error . "\n";
        }
    } else {
        echo "✓ user_settings table already exists\n";

        // Check if otp_enabled column exists
        $result = $conn->query("SHOW COLUMNS FROM user_settings LIKE 'otp_enabled'");
        if ($result->num_rows == 0) {
            echo "Adding otp_enabled column to user_settings...\n";
            $conn->query("ALTER TABLE user_settings ADD COLUMN otp_enabled TINYINT(1) NOT NULL DEFAULT 0");
            echo "✓ otp_enabled column added\n";
        } else {
            echo "✓ otp_enabled column already exists\n";
        }
    }

    // Create membership_applications table if it doesn't exist
    echo "\n=== Checking membership_applications table ===\n";
    $result = $conn->query("SHOW TABLES LIKE 'membership_applications'");
    if ($result->num_rows == 0) {
        echo "Creating membership_applications table...\n";
        $sql = "CREATE TABLE membership_applications (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(20),
            address TEXT,
            birth_date DATE,
            occupation VARCHAR(100),
            emergency_contact_name VARCHAR(100),
            emergency_contact_phone VARCHAR(20),
            medical_conditions TEXT,
            medications TEXT,
            membership_type VARCHAR(50),
            payment_method VARCHAR(50),
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            reviewed_at TIMESTAMP NULL,
            reviewed_by INT(11),
            PRIMARY KEY (id),
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if ($conn->query($sql)) {
            echo "✓ membership_applications table created successfully\n";
        } else {
            echo "✗ Error creating membership_applications table: " . $conn->error . "\n";
        }
    } else {
        echo "✓ membership_applications table already exists\n";
    }

    echo "\n=== Database fix completed successfully! ===\n";
    echo "The login and signup processes should now work correctly.\n";
    echo "If you still have issues, please check:\n";
    echo "1. Your SMTP settings in .env file\n";
    echo "2. That user accounts have password_hash set\n";
    echo "3. PHP error logs for any additional issues\n";
} catch (Exception $e) {
    echo "Error fixing database: " . $e->getMessage() . "\n";
    echo "Please run the SQL commands manually or contact support.\n";
}

$conn->close();
