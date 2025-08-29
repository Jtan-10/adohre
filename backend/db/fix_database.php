<?php

/**
 * Database Fix Script for Users Table
 * Run this script to add missing columns to the users table
 * Execute via command line: php fix_database.php
 */

require_once 'db/db_connect.php';

echo "Checking and fixing users table structure...\n";

try {
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

    echo "\nDatabase fix completed successfully!\n";
    echo "The signup process should now work correctly.\n";
} catch (Exception $e) {
    echo "Error fixing database: " . $e->getMessage() . "\n";
    echo "Please run the SQL script manually or contact support.\n";
}

$conn->close();
