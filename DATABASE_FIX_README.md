# Database Fix for Signup Registration

## Problem

The signup process is failing because the `users` table is missing required columns that the registration script expects.

## Solution

You need to add the missing columns to your AWS Lightsail database.

## Option 1: Run PHP Script (Recommended)

1. Upload the `fix_database.php` file to your server
2. Run it via command line:
   ```bash
   php /path/to/fix_database.php
   ```

## Option 2: Run SQL Script Manually

1. Connect to your AWS Lightsail MySQL database
2. Run the SQL commands from `fix_users_table.sql`:
   ```sql
   ALTER TABLE users ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) DEFAULT NULL;
   ALTER TABLE users ADD COLUMN IF NOT EXISTS is_profile_complete TINYINT(1) NOT NULL DEFAULT 0;
   ALTER TABLE users ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
   ALTER TABLE users ADD CONSTRAINT IF NOT EXISTS unique_email UNIQUE (email);
   ALTER TABLE users MODIFY COLUMN user_id INT(11) NOT NULL AUTO_INCREMENT;
   ```

## What This Fixes

- Adds `password_hash` column for storing encrypted passwords
- Adds `is_profile_complete` column to track registration status
- Adds `updated_at` column for tracking user updates
- Ensures email uniqueness constraint
- Sets up auto-increment for user IDs

## After Fix

Once the database is updated, the signup process should work correctly:

1. User enters email → verification code sent
2. User enters code → email verified
3. User fills registration form → account created successfully

## Testing

Test the signup flow after applying the fix. If you encounter any other errors, check the PHP error logs for detailed error messages.
