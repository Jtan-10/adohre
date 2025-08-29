<?php
// htaccess_test.php - Test if .htaccess is working properly

// Set appropriate content type for HTML output
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>.htaccess Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .test-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .code {
            background-color: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
            font-family: monospace;
            white-space: pre-wrap;
            overflow-x: auto;
        }

        h3 {
            margin-top: 0;
            color: #2c3e50;
        }

        .success {
            color: green;
        }

        .error {
            color: red;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>.htaccess Test</h1>

        <div class="test-section">
            <h3>1. File Existence Check</h3>
            <?php
            $htaccessPath = __DIR__ . '/.htaccess';
            echo '<p><strong>.htaccess File:</strong> ';
            if (file_exists($htaccessPath)) {
                echo '<span class="success">File exists</span>';
                echo '<p>File permissions: ' . substr(sprintf('%o', fileperms($htaccessPath)), -4) . '</p>';
            } else {
                echo '<span class="error">File not found</span>';
            }
            echo '</p>';
            ?>
        </div>

        <div class="test-section">
            <h3>2. Apache Configuration Test</h3>
            <?php
            echo '<p>';
            if (function_exists('apache_get_modules')) {
                echo '<strong>Apache Modules:</strong><br>';
                $modules = apache_get_modules();
                $relevantModules = ['mod_rewrite', 'mod_headers', 'mod_mime'];
                foreach ($relevantModules as $module) {
                    if (in_array($module, $modules)) {
                        echo "<span class=\"success\">$module is enabled</span><br>";
                    } else {
                        echo "<span class=\"error\">$module is not enabled</span><br>";
                    }
                }
            } else {
                echo '<span class="error">Cannot check Apache modules (apache_get_modules function does not exist)</span>';
            }
            echo '</p>';

            // Test if AllowOverride is enabled
            echo '<p><strong>AllowOverride Test:</strong> ';
            $testDir = __DIR__ . '/htaccess_test_dir';
            if (!is_dir($testDir)) {
                mkdir($testDir);
            }
            $testHtaccess = $testDir . '/.htaccess';
            $testPhp = $testDir . '/test.php';

            // Create test files
            file_put_contents($testHtaccess, 'Deny from all');
            file_put_contents($testPhp, '<?php echo "If you see this, AllowOverride is not enabled"; ?>');

            $url = 'http://' . $_SERVER['HTTP_HOST'] . '/capstone-php/htaccess_test_dir/test.php';
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode == 403) {
                echo '<span class="success">AllowOverride is enabled (Access denied, which is expected)</span>';
            } else {
                echo '<span class="error">AllowOverride might not be enabled (HTTP code: ' . $httpCode . ')</span>';
            }
            echo '</p>';

            // Clean up
            @unlink($testHtaccess);
            @unlink($testPhp);
            @rmdir($testDir);
            ?>
        </div>

        <div class="test-section">
            <h3>3. Rewrite Test</h3>
            <?php
            // Test s3proxy rewrite
            $s3proxyUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/capstone-php/s3proxy/test';
            $ch = curl_init($s3proxyUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            echo '<p><strong>S3 Proxy Rewrite Test:</strong> ';
            if ($httpCode == 404 && strpos($s3proxyUrl, '/s3proxy/') !== false) {
                echo '<span class="success">Rewrite rule appears to be working (404 is expected since "test" is not a valid S3 key)</span>';
            } else {
                echo '<span class="error">Rewrite rule may not be working correctly (HTTP code: ' . $httpCode . ')</span>';
            }
            echo '</p>';

            // Test decrypt_image rewrite
            $decryptUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/capstone-php/decrypt_image.php?test=1';
            $ch = curl_init($decryptUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            echo '<p><strong>Decrypt Image Rewrite Test:</strong> ';
            if ($httpCode == 400) {
                echo '<span class="success">Rewrite rule appears to be working (400 is expected for missing image URL)</span>';
            } else {
                echo '<span class="error">Rewrite rule may not be working correctly (HTTP code: ' . $httpCode . ')</span>';
            }
            echo '</p>';
            ?>
        </div>
    </div>
</body>

</html>