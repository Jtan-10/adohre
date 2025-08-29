<?php
// Test script to verify email verification functionality
require_once '../db/db_connect.php';
require_once '../controllers/authController.php';

// Configure session security based on environment
configureSessionSecurity();
session_start();

header('Content-Type: text/plain');

// Test email address (replace with your actual email for testing)
$testEmail = 'your-email@example.com'; // Replace with your email

echo "Testing Email Verification System\n";
echo "==================================\n\n";

// Test 1: Generate verification code
echo "Test 1: Generating verification code...\n";
$verificationCode = generateVerificationCode(6);
echo "Generated code: $verificationCode\n\n";

// Test 2: Send verification email
echo "Test 2: Sending verification email...\n";
$result = sendEmailVerification($testEmail, $verificationCode);
echo "Email sent: " . ($result ? "SUCCESS" : "FAILED") . "\n\n";

// Test 3: Check if email exists (should be false for new signup)
echo "Test 3: Checking if email exists...\n";
$exists = emailExists($testEmail);
echo "Email exists: " . ($exists ? "YES" : "NO") . "\n\n";

echo "Test completed. Check your email for the verification code.\n";
echo "If you received the email with the code, the system is working correctly.\n";
