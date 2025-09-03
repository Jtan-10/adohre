<?php
// Define constant for visually_impaired_modal.php
define('IN_CAPSTONE', true);

require_once 'backend/db/db_connect.php';

// Configure session security based on environment
configureSessionSecurity();
session_start();

// Generate a unique nonce for this page
$scriptNonce = bin2hex(random_bytes(16));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login - Member Link</title>
    <link rel="icon" href="assets/logo.png" type="image/jpg" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        :root {
            --form-bg: rgba(255, 255, 255, 0.95);
        }

        body {
            background-image: url('assets/green_bg.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            font-family: 'Montserrat', sans-serif;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            background-color: var(--form-bg);
        }

        .login-options {
            display: flex;
            gap: 10px;
            margin-bottom: 1rem;
        }

        .login-option {
            flex: 1;
            text-align: center;
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: white;
        }

        .login-option.active {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            color: white;
        }

        .form-section {
            display: none;
        }

        .form-section.active {
            display: block;
        }

        .btn-primary {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }

        .btn-primary:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }

        .login-card h2 {
            color: var(--accent-color);
            font-weight: 600;
        }

        .form-label {
            font-weight: 500;
        }

        a {
            color: var(--accent-color);
            text-decoration: none;
        }

        a:hover {
            color: #218838;
        }

        /* Toast styles */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }
    </style>
</head>

<body>
    <?php include 'visually_impaired_modal.php'; ?>

    <div class="login-card">
        <h2 class="text-center mb-4">Welcome Back</h2>

        <!-- Login Form (Email-first) -->
        <form id="loginForm" class="form-section active needs-validation" novalidate>
            <div id="emailStep">
                <div class="mb-3">
                    <label for="email-password" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email-password" name="email" required>
                    <div class="invalid-feedback">Please enter a valid email address.</div>
                </div>
                <div class="d-grid mb-3">
                    <button type="button" id="continueBtn" class="btn btn-primary">Continue</button>
                </div>
                <p class="small text-muted">If your account doesn’t have a password yet, we’ll send a one-time code to set it.</p>
            </div>
            <div id="passwordStep" style="display:none;">
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password">
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="rememberMe" name="rememberMe">
                    <label class="form-check-label" for="rememberMe">Remember me</label>
                </div>
                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary">Log In</button>
                </div>
                <div class="text-center mb-3">
                    <a href="#" id="forgotPasswordLink">Forgot Password?</a>
                </div>
            </div>
        </form>

        <hr class="my-4">

        <p class="text-center">
            Don't have an account?
            <a href="signup.php">Sign up</a>
        </p>
    </div>

    <!-- Response Modal -->
    <div class="modal fade" id="responseModal" tabindex="-1" aria-labelledby="responseModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="responseModalLabel">Response</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="responseModalBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Spinner -->
    <div id="loadingSpinner" class="position-fixed top-0 start-0 w-100 h-100 d-none"
        style="background: rgba(0,0,0,0.5); z-index: 9999;">
        <div class="d-flex justify-content-center align-items-center h-100">
            <div class="spinner-border text-light" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script nonce="<?php echo $scriptNonce; ?>">
        document.addEventListener('DOMContentLoaded', function() {
            // Password visibility toggle
            const passwordInput = document.getElementById('password');
            const togglePassword = document.getElementById('togglePassword');

            togglePassword.addEventListener('click', () => {
                const icon = togglePassword.querySelector('i');
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    icon.classList.replace('bi-eye', 'bi-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    icon.classList.replace('bi-eye-slash', 'bi-eye');
                }
            });

            // Forgot Password Handler
            document.getElementById('forgotPasswordLink').addEventListener('click', function(e) {
                e.preventDefault();
                const email = document.getElementById('email-password').value;
                if (!email) {
                    showModal('Error', 'Please enter your email address first.');
                    return;
                }

                fetch('backend/routes/reset_password.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            email: email
                        })
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.status) {
                            sessionStorage.setItem('email', email);
                            sessionStorage.setItem('action', 'reset');
                            showModal('Success', 'Password reset OTP sent to your email.', 'otp.php');
                        } else {
                            showModal('Error', result.message);
                        }
                    })
                    .catch(error => {
                        showModal('Error', 'An error occurred while requesting password reset.');
                    });
            });

            // Email-first flow: Continue -> precheck -> either show password or send OTP for setup
            document.getElementById('continueBtn').addEventListener('click', async function() {
                const emailEl = document.getElementById('email-password');
                if (!emailEl.checkValidity()) {
                    emailEl.reportValidity();
                    return;
                }
                showLoading();
                try {
                    const res = await fetch('backend/routes/account_precheck.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            email: emailEl.value
                        })
                    });
                    const j = await res.json();
                    hideLoading();
                    if (!j.status) {
                        showModal('Error', j.message || 'Unable to continue');
                        return;
                    }
                    if (!j.exists) {
                        showModal('Error', 'Email not registered.');
                        return;
                    }
                    if (j.hasPassword) {
                        // Show password step
                        document.getElementById('emailStep').style.display = 'none';
                        document.getElementById('passwordStep').style.display = 'block';
                    } else {
                        // Initiate password setup via OTP, redirect to OTP page
                        const fd = new FormData();
                        fd.append('email', emailEl.value);
                        const resp = await fetch('backend/routes/initiate_password_setup.php', {
                            method: 'POST',
                            body: fd
                        });
                        const jr = await resp.json();
                        if (jr.status && jr.requiresOTP) {
                            sessionStorage.setItem('email', emailEl.value);
                            sessionStorage.setItem('action', 'reset');
                            window.location.replace(jr.redirect || 'otp.php');
                        } else {
                            showModal('Error', jr.message || 'Failed to start password setup.');
                        }
                    }
                } catch (err) {
                    hideLoading();
                    showModal('Error', 'An error occurred.');
                }
            });

            // Login Form Handler (password step)
            document.getElementById('loginForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                if (!this.checkValidity()) {
                    e.stopPropagation();
                    this.classList.add('was-validated');
                    return;
                }

                showLoading();
                try {
                    const formData = new FormData();
                    formData.append('email', document.getElementById('email-password').value);
                    formData.append('password', passwordInput.value);
                    formData.append('remember', document.getElementById('rememberMe').checked);

                    const response = await fetch('backend/routes/password_login.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    hideLoading();

                    console.log('Login response:', result); // Debug log

                    if (result.status) {
                        console.log('Login successful, redirecting...', result); // Debug log with result
                        if (result.needsPasswordSetup) {
                            // First-time login: password not set. Go to OTP, then reset_password
                            sessionStorage.setItem('email', document.getElementById('email-password').value);
                            sessionStorage.setItem('action', 'reset');
                            window.location.replace(result.redirect || 'otp.php');
                            setTimeout(function() {
                                window.location.href = result.redirect || 'otp.php';
                            }, 300);
                        } else if (result.requiresOTP) {
                            console.log('OTP required, redirecting to OTP page...');
                            sessionStorage.setItem('email', document.getElementById('email-password').value);
                            sessionStorage.setItem('action', 'login');

                            // Force hard redirect to OTP page
                            window.location.replace(result.redirect);

                            // Fallback redirect after a small delay
                            setTimeout(function() {
                                console.log('Fallback redirect to OTP page...');
                                window.location.href = result.redirect;
                            }, 500);
                        } else {
                            // Force redirect to index.php - use direct method
                            console.log('Redirecting to index.php...');
                            window.location.href = 'index.php';
                            // If that doesn't work, try after a small delay
                            setTimeout(function() {
                                window.location = 'index.php';
                            }, 100);
                        }
                    } else {
                        console.log('Login failed:', result.message); // Debug log
                        showModal('Error', result.message || 'Login failed');
                    }
                } catch (error) {
                    hideLoading();
                    showModal('Error', 'An error occurred during login.');
                }
            });

            // Helper functions
            function showLoading() {
                document.getElementById('loadingSpinner').classList.remove('d-none');
            }

            function hideLoading() {
                document.getElementById('loadingSpinner').classList.add('d-none');
            }

            function showModal(title, message, redirect = null) {
                const modal = new bootstrap.Modal(document.getElementById('responseModal'));
                document.getElementById('responseModalLabel').textContent = title;
                document.getElementById('responseModalBody').textContent = message;

                if (redirect) {
                    modal._element.addEventListener('hidden.bs.modal', () => {
                        window.location.href = redirect;
                    }, {
                        once: true
                    });
                }

                modal.show();
            }
        });
    </script>
</body>

</html>