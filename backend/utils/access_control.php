<?php

/**
 * Access Control Functions
 * Include this file in pages that require authentication and OTP verification
 */

// Define constant for visually_impaired_modal.php
if (!defined('IN_CAPSTONE')) {
    define('IN_CAPSTONE', true);
}

/**
 * Check if user is authenticated and has completed OTP if required
 */
function requireAuthentication()
{
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }

    // Check if OTP is pending and redirect to OTP page
    if (isset($_SESSION['otp_pending']) && $_SESSION['otp_pending'] === true) {
        // Only redirect if not already on OTP page or logout page
        $currentPage = basename($_SERVER['PHP_SELF']);
        if ($currentPage !== 'otp.php' && $currentPage !== 'logout.php') {
            header('Location: otp.php');
            exit();
        }
    }
}

/**
 * Check if user has admin role
 */
function requireAdmin()
{
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header('Location: index.php');
        exit();
    }
}

/**
 * Check if face validation is enabled
 * This can be configured via environment variables or database settings
 */
function isFaceValidationEnabled()
{
    // Check environment variable first
    if (isset($_ENV['FACE_VALIDATION_ENABLED'])) {
        return filter_var($_ENV['FACE_VALIDATION_ENABLED'], FILTER_VALIDATE_BOOLEAN);
    }

    // Default to disabled for security
    return false;
}
