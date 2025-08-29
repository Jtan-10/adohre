<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>PHP Syntax Check</h1>";

$files = [
    '/opt/lampp/htdocs/capstone-php/admin/settings.php',
    '/opt/lampp/htdocs/capstone-php/backend/routes/settings_api.php',
    '/opt/lampp/htdocs/capstone-php/admin/admin_header.php'
];

foreach ($files as $file) {
    echo "<h2>Checking: $file</h2>";

    // Use PHP's built-in linter
    $output = [];
    $return_var = 0;
    exec("php -l $file", $output, $return_var);

    if ($return_var === 0) {
        echo "<p style='color: green;'>No syntax errors detected.</p>";
    } else {
        echo "<pre style='color: red;'>";
        echo implode("\n", $output);
        echo "</pre>";
    }
}
