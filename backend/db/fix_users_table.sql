-- Add missing columns to users table for signup functionality
-- Run this script on your AWS Lightsail database

-- Add password_hash column if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) DEFAULT NULL;

-- Add is_profile_complete column if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_profile_complete TINYINT(1) NOT NULL DEFAULT 0;

-- Add updated_at column if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Add unique constraint on email if it doesn't exist
ALTER TABLE users ADD CONSTRAINT IF NOT EXISTS unique_email UNIQUE (email);

-- Add primary key if it doesn't exist
ALTER TABLE users ADD PRIMARY KEY IF NOT EXISTS (user_id);

-- Add auto increment to user_id if not already set
ALTER TABLE users MODIFY COLUMN user_id INT(11) NOT NULL AUTO_INCREMENT;
