<?php
require_once 'backend/db/db_connect.php';
session_start();

echo "<pre>";
echo "SESSION CONTENTS:\n";
print_r($_SESSION);

echo "\nCHECKING IF LOGGED IN:\n";
if (isset($_SESSION['user_id'])) {
    echo "User is logged in with ID: " . $_SESSION['user_id'] . "\n";
} else {
    echo "User is NOT logged in\n";
}

echo "\nSESSION COOKIE PARAMETERS:\n";
print_r(session_get_cookie_params());

echo "\nSESSION ID: " . session_id() . "\n";

echo "</pre>";
