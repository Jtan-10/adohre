<?php
require_once 'backend/db/db_connect.php';

// Configure session security based on environment
configureSessionSecurity();
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Record HTTP request method and data
echo "<h2>Debug Login Info</h2>";
echo "<pre>";
echo "HTTP Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "</pre>";

// Process login if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<pre>";
    echo "POST Data:\n";
    print_r($_POST);
    echo "</pre>";

    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Validate email and password
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<p>Error: Invalid email format</p>";
    } else if (empty($password)) {
        echo "<p>Error: Password is required</p>";
    } else {
        // Check if user exists
        $stmt = $conn->prepare("SELECT user_id, password_hash, first_name, last_name, profile_image, role, is_profile_complete FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        echo "<pre>";
        echo "User query results:\n";
        echo "User found: " . ($user ? "Yes" : "No") . "\n";
        if ($user) {
            echo "User ID: " . $user['user_id'] . "\n";
            echo "Password hash exists: " . (!empty($user['password_hash']) ? "Yes" : "No") . "\n";

            // Verify password if user exists
            if (!empty($user['password_hash'])) {
                $passwordVerified = password_verify($password, $user['password_hash']);
                echo "Password verification: " . ($passwordVerified ? "Success" : "Failed") . "\n";

                // Set session variables if password verified
                if ($passwordVerified) {
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['profile_image'] = $user['profile_image'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['is_profile_complete'] = $user['is_profile_complete'];

                    echo "Session variables set successfully.\n";
                    echo "SESSION after login:\n";
                    print_r($_SESSION);
                }
            }
        }
        echo "</pre>";
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Debug Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        form {
            margin: 20px 0;
            padding: 20px;
            border: 1px solid #ccc;
        }

        input {
            margin: 5px 0;
            padding: 8px;
            width: 300px;
        }

        button {
            padding: 10px 15px;
            background: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <h1>Debug Login Form</h1>

    <form method="post" action="debug_login.php">
        <div>
            <label for="email">Email:</label><br>
            <input type="email" id="email" name="email" required>
        </div>
        <div>
            <label for="password">Password:</label><br>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit">Login</button>
    </form>

    <h2>Current Session</h2>
    <pre><?php print_r($_SESSION); ?></pre>

    <p><a href="debug_session.php">View Session Details</a></p>
    <p><a href="index.php">Go to Homepage</a></p>
    <p><a href="logout.php">Logout</a></p>
</body>

</html>