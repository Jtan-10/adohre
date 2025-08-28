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
    <title>Sign Up - Member Link</title>
    <link rel="icon" href="assets/logo.png" type="image/jpg" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            min-height: 100vh;
            margin: 0;
            flex-direction: row;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }

        .left-pane {
            flex: 1;
            padding: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .right-pane {
            flex: 1;
            background-size: cover;
            background-position: center;
            display: none;
        }

        .signup-card {
            width: 100%;
            max-width: 400px;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            background-color: white;
        }

        .signup-options {
            display: flex;
            gap: 10px;
            margin-bottom: 1rem;
        }

        .signup-option {
            flex: 1;
            text-align: center;
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .signup-option.active {
            background-color: #e9ecef;
            border-color: #6c757d;
        }

        .form-section {
            display: none;
        }

        .form-section.active {
            display: block;
        }

        .password-requirements {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.5rem;
        }

        .requirement {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.25rem;
        }

        .requirement i {
            font-size: 0.75rem;
        }

        .requirement.valid {
            color: #28a745;
        }

        .requirement.invalid {
            color: #dc3545;
        }

        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .strength-weak {
            background-color: #dc3545;
            width: 33%;
        }

        .strength-medium {
            background-color: #ffc107;
            width: 66%;
        }

        .strength-strong {
            background-color: #28a745;
            width: 100%;
        }

        @media (min-width: 768px) {
            .right-pane {
                display: block;
            }
        }
    </style>
</head>

<body>
    <?php include 'visually_impaired_modal.php'; ?>

    <div class="left-pane">
        <div class="signup-card">
            <h2 class="text-center mb-4">Create Account</h2>

            <div class="signup-options mb-4">
                <div class="signup-option active" data-option="password">
                    <i class="bi bi-key"></i>
                    <div>Password</div>
                </div>
                <div class="signup-option" data-option="otp">
                    <i class="bi bi-shield-lock"></i>
                    <div>OTP</div>
                </div>
            </div>

            <!-- Password Signup Form -->
            <form id="passwordSignupForm" class="form-section active needs-validation" novalidate>
                <div class="mb-3">
                    <label for="email-password" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email-password" name="email" required>
                    <div class="invalid-feedback">Please enter a valid email address.</div>
                </div>
                <div class="mb-3">
                    <label for="firstName-password" class="form-label">First Name</label>
                    <input type="text" class="form-control" id="firstName-password" name="firstName" required>
                    <div class="invalid-feedback">Please enter your first name.</div>
                </div>
                <div class="mb-3">
                    <label for="lastName-password" class="form-label">Last Name</label>
                    <input type="text" class="form-control" id="lastName-password" name="lastName" required>
                    <div class="invalid-feedback">Please enter your last name.</div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" required minlength="8">
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength"></div>
                    <div class="password-requirements">
                        <div class="requirement" data-requirement="length">
                            <i class="bi bi-x-circle"></i> At least 8 characters
                        </div>
                        <div class="requirement" data-requirement="uppercase">
                            <i class="bi bi-x-circle"></i> One uppercase letter
                        </div>
                        <div class="requirement" data-requirement="lowercase">
                            <i class="bi bi-x-circle"></i> One lowercase letter
                        </div>
                        <div class="requirement" data-requirement="number">
                            <i class="bi bi-x-circle"></i> One number
                        </div>
                        <div class="requirement" data-requirement="special">
                            <i class="bi bi-x-circle"></i> One special character
                        </div>
                    </div>
                </div>
                <div class="mb-4">
                    <label for="confirmPassword" class="form-label">Confirm Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
                        <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback">Passwords do not match.</div>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Sign Up</button>
                </div>
            </form>

            <!-- OTP Signup Form -->
            <form id="otpSignupForm" class="form-section needs-validation" novalidate>
                <div class="mb-3">
                    <label for="email-otp" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email-otp" name="email" required>
                    <div class="invalid-feedback">Please enter a valid email address.</div>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Send OTP</button>
                </div>
            </form>

            <hr class="my-4">

            <p class="text-center">
                Already have an account?
                <a href="login.php" class="text-decoration-none">Log in</a>
            </p>
        </div>
    </div>

    <div class="right-pane" style="background-image: url('assets/expertise-background.png');"></div>

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
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirmPassword');
            const strengthIndicator = document.querySelector('.password-strength');
            const requirements = document.querySelectorAll('.requirement');

            function validatePassword(password) {
                const checks = {
                    length: password.length >= 8,
                    uppercase: /[A-Z]/.test(password),
                    lowercase: /[a-z]/.test(password),
                    number: /[0-9]/.test(password),
                    special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
                };

                requirements.forEach(req => {
                    const type = req.dataset.requirement;
                    const icon = req.querySelector('i');
                    if (checks[type]) {
                        req.classList.add('valid');
                        req.classList.remove('invalid');
                        icon.classList.replace('bi-x-circle', 'bi-check-circle');
                    } else {
                        req.classList.add('invalid');
                        req.classList.remove('valid');
                        icon.classList.replace('bi-check-circle', 'bi-x-circle');
                    }
                });

                return Object.values(checks).filter(Boolean).length;
            }

            function updateStrengthIndicator(strength) {
                strengthIndicator.className = 'password-strength';
                if (strength < 3) {
                    strengthIndicator.classList.add('strength-weak');
                } else if (strength < 5) {
                    strengthIndicator.classList.add('strength-medium');
                } else {
                    strengthIndicator.classList.add('strength-strong');
                }
            }

            // Toggle between signup options
            const signupOptions = document.querySelectorAll('.signup-option');
            signupOptions.forEach(option => {
                option.addEventListener('click', function() {
                    signupOptions.forEach(opt => opt.classList.remove('active'));
                    this.classList.add('active');

                    const formType = this.dataset.option;
                    document.querySelectorAll('.form-section').forEach(form => {
                        form.classList.remove('active');
                    });
                    document.getElementById(formType + 'SignupForm').classList.add('active');
                });
            });

            // Password validation
            passwordInput.addEventListener('input', function() {
                const strength = validatePassword(this.value);
                updateStrengthIndicator(strength);

                if (confirmPasswordInput.value) {
                    confirmPasswordInput.setCustomValidity(
                        confirmPasswordInput.value === this.value ? '' : 'Passwords do not match.'
                    );
                }
            });

            confirmPasswordInput.addEventListener('input', function() {
                this.setCustomValidity(
                    this.value === passwordInput.value ? '' : 'Passwords do not match.'
                );
            });

            // Password visibility toggles
            ['password', 'confirmPassword'].forEach(id => {
                const input = document.getElementById(id);
                const toggle = document.getElementById('toggle' + id.charAt(0).toUpperCase() + id.slice(1));

                toggle.addEventListener('click', () => {
                    const icon = toggle.querySelector('i');
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.classList.replace('bi-eye', 'bi-eye-slash');
                    } else {
                        input.type = 'password';
                        icon.classList.replace('bi-eye-slash', 'bi-eye');
                    }
                });
            });

            // Password Signup Form Handler
            document.getElementById('passwordSignupForm').addEventListener('submit', async function(e) {
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
                    formData.append('firstName', document.getElementById('firstName-password').value);
                    formData.append('lastName', document.getElementById('lastName-password').value);
                    formData.append('password', passwordInput.value);

                    const response = await fetch('backend/routes/complete_signup.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    hideLoading();

                    if (result.status) {
                        showModal('Success', result.message, 'index.php');
                    } else {
                        showModal('Error', result.message);
                    }
                } catch (error) {
                    hideLoading();
                    showModal('Error', 'An error occurred during registration.');
                }
            });

            // OTP Signup Form Handler
            document.getElementById('otpSignupForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                if (!this.checkValidity()) {
                    e.stopPropagation();
                    this.classList.add('was-validated');
                    return;
                }

                showLoading();
                try {
                    const response = await fetch('backend/routes/send_otp.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            email: document.getElementById('email-otp').value,
                            action: 'signup'
                        })
                    });

                    const result = await response.json();
                    hideLoading();

                    if (result.status) {
                        sessionStorage.setItem('email', document.getElementById('email-otp').value);
                        sessionStorage.setItem('action', 'signup');
                        showModal('Success', 'OTP sent successfully!', 'otp.php');
                    } else {
                        showModal('Error', result.message);
                    }
                } catch (error) {
                    hideLoading();
                    showModal('Error', 'An error occurred while sending OTP.');
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