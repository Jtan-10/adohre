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

        .signup-card {
            width: 100%;
            max-width: 400px;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            background-color: var(--form-bg);
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
            background-color: white;
        }

        .signup-option.active {
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
            color: var(--accent-color);
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
            background-color: var(--accent-color);
            width: 100%;
        }

        .btn-primary {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }

        .btn-primary:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }

        .signup-card h2 {
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

        /* Progress indicator styles */
        .step-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }

        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .step.active .step-circle {
            background-color: var(--accent-color);
            color: white;
        }

        .step.completed .step-circle {
            background-color: #28a745;
            color: white;
        }

        .step-label {
            font-size: 0.875rem;
            color: #6c757d;
            font-weight: 500;
        }

        .step.active .step-label {
            color: var(--accent-color);
        }

        .step.completed .step-label {
            color: #28a745;
        }

        .step-connector {
            width: 40px;
            height: 2px;
            background-color: #e9ecef;
            transition: all 0.3s ease;
        }

        .step.completed+.step-connector {
            background-color: #28a745;
        }
    </style>
</head>

<body>
    <?php include 'visually_impaired_modal.php'; ?>

    <div class="signup-card">
        <h2 class="text-center mb-4">Create Account</h2>

        <!-- Progress indicator -->
        <div class="mb-4">
            <div class="d-flex justify-content-center">
                <div class="step-indicator">
                    <div class="step active" id="step1">
                        <div class="step-circle">1</div>
                        <div class="step-label">Email</div>
                    </div>
                    <div class="step-connector"></div>
                    <div class="step" id="step2">
                        <div class="step-circle">2</div>
                        <div class="step-label">Verify</div>
                    </div>
                    <div class="step-connector"></div>
                    <div class="step" id="step3">
                        <div class="step-circle">3</div>
                        <div class="step-label">Details</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Email Validation Form -->
        <form id="emailValidationForm" class="form-section active needs-validation" novalidate>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
                <div class="invalid-feedback">Please enter a valid email address.</div>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">Send Verification Code</button>
            </div>
        </form>

        <!-- Email Verification Form -->
        <form id="emailVerificationForm" class="form-section needs-validation" novalidate style="display: none;">
            <div class="mb-3">
                <label for="verificationCode" class="form-label">Verification Code</label>
                <input type="text" class="form-control" id="verificationCode" name="verificationCode" required
                    maxlength="6" pattern="[0-9]{6}" placeholder="Enter 6-digit code">
                <div class="invalid-feedback">Please enter the 6-digit verification code.</div>
                <div class="form-text">Check your email for the verification code. It expires in 10 minutes.</div>
            </div>
            <div class="d-grid mb-3">
                <button type="submit" class="btn btn-primary">Verify Code</button>
            </div>
            <div class="text-center">
                <button type="button" class="btn btn-link" id="resendCodeBtn">Didn't receive the code? Resend</button>
            </div>
        </form>

        <!-- Complete Registration Form -->
        <form id="completeSignupForm" class="form-section needs-validation" novalidate style="display: none;">
            <div class="mb-3">
                <label for="firstName" class="form-label">First Name</label>
                <input type="text" class="form-control" id="firstName" name="firstName" required>
                <div class="invalid-feedback">Please enter your first name.</div>
            </div>
            <div class="mb-3">
                <label for="lastName" class="form-label">Last Name</label>
                <input type="text" class="form-control" id="lastName" name="lastName" required>
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

        <hr class="my-4">

        <!-- Debug Section (Remove in production) -->
        <div class="mt-4 p-3 bg-light rounded" id="debugSection" style="display: none;">
            <h6 class="text-muted">Debug: Test Email Verification</h6>
            <div class="input-group">
                <input type="email" class="form-control" id="debugEmail" placeholder="Enter test email">
                <button class="btn btn-outline-secondary" type="button" id="testEmailBtn">Test Email</button>
            </div>
            <div id="debugResult" class="mt-2 small text-muted"></div>
        </div>

        <p class="text-center">
            Already have an account?
            <a href="login.php">Log in</a>
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

            // Email validation form handler
            let validatedEmail = '';

            function updateStepIndicator(currentStep) {
                // Reset all steps
                document.querySelectorAll('.step').forEach(step => {
                    step.classList.remove('active', 'completed');
                });

                // Set completed steps
                for (let i = 1; i < currentStep; i++) {
                    document.getElementById(`step${i}`).classList.add('completed');
                }

                // Set active step
                document.getElementById(`step${currentStep}`).classList.add('active');
            }

            document.getElementById('emailValidationForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                if (!this.checkValidity()) {
                    e.stopPropagation();
                    this.classList.add('was-validated');
                    return;
                }

                const email = document.getElementById('email').value;
                showLoading();
                try {
                    const response = await fetch('backend/routes/validate_email.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            email
                        })
                    });

                    const result = await response.json();
                    hideLoading();

                    if (result.status) {
                        validatedEmail = email;
                        document.getElementById('emailValidationForm').style.display = 'none';
                        document.getElementById('emailVerificationForm').style.display = 'block';
                        updateStepIndicator(2);
                        document.getElementById('verificationCode').focus();
                    } else {
                        showModal('Error', result.message);
                    }
                } catch (error) {
                    hideLoading();
                    showModal('Error', 'An error occurred while sending verification code.');
                }
            });

            // Email verification form handler
            document.getElementById('emailVerificationForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                if (!this.checkValidity()) {
                    e.stopPropagation();
                    this.classList.add('was-validated');
                    return;
                }

                const verificationCode = document.getElementById('verificationCode').value;
                showLoading();
                try {
                    const response = await fetch('backend/routes/verify_email_code.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            verificationCode
                        })
                    });

                    const result = await response.json();
                    hideLoading();

                    if (result.status && result.verified) {
                        document.getElementById('emailVerificationForm').style.display = 'none';
                        document.getElementById('completeSignupForm').style.display = 'block';
                        updateStepIndicator(3);
                        document.getElementById('firstName').focus();
                    } else {
                        showModal('Error', result.message);
                    }
                } catch (error) {
                    hideLoading();
                    showModal('Error', 'An error occurred while verifying the code.');
                }
            });

            // Resend verification code handler
            document.getElementById('resendCodeBtn').addEventListener('click', async function() {
                if (!validatedEmail) {
                    showModal('Error', 'No email to resend code to. Please start over.');
                    return;
                }

                showLoading();
                try {
                    const response = await fetch('backend/routes/validate_email.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            email: validatedEmail
                        })
                    });

                    const result = await response.json();
                    hideLoading();

                    if (result.status) {
                        showModal('Success', 'New verification code sent to your email.');
                        document.getElementById('verificationCode').value = '';
                        document.getElementById('verificationCode').focus();
                    } else {
                        showModal('Error', result.message);
                    }
                } catch (error) {
                    hideLoading();
                    showModal('Error', 'An error occurred while resending the code.');
                }
            });

            // Add back button functionality
            function addBackButton(formId, targetStep) {
                const form = document.getElementById(formId);
                const backBtn = document.createElement('button');
                backBtn.type = 'button';
                backBtn.className = 'btn btn-outline-secondary mt-2';
                backBtn.innerHTML = '<i class="bi bi-arrow-left"></i> Back';
                backBtn.onclick = () => {
                    form.style.display = 'none';
                    updateStepIndicator(targetStep);
                    if (targetStep === 1) {
                        document.getElementById('emailValidationForm').style.display = 'block';
                        document.getElementById('email').focus();
                    } else if (targetStep === 2) {
                        document.getElementById('emailVerificationForm').style.display = 'block';
                        document.getElementById('verificationCode').focus();
                    }
                };
                form.appendChild(backBtn);
            }

            // Auto-format verification code input (only numbers)
            document.getElementById('verificationCode').addEventListener('input', function(e) {
                // Remove any non-numeric characters
                this.value = this.value.replace(/[^0-9]/g, '');

                // Auto-focus to next field when complete
                if (this.value.length === 6) {
                    // Trigger form submission or move to next step
                    this.blur();
                }
            });

            // Prevent paste of non-numeric content
            document.getElementById('verificationCode').addEventListener('paste', function(e) {
                e.preventDefault();
                const pastedText = (e.clipboardData || window.clipboardData).getData('text');
                const numericText = pastedText.replace(/[^0-9]/g, '');
                this.value = numericText.substring(0, 6);
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

            // Complete Registration Form Handler
            document.getElementById('completeSignupForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                if (!this.checkValidity()) {
                    e.stopPropagation();
                    this.classList.add('was-validated');
                    return;
                }

                showLoading();
                try {
                    const formData = new FormData();
                    formData.append('email', validatedEmail);
                    formData.append('firstName', document.getElementById('firstName').value);
                    formData.append('lastName', document.getElementById('lastName').value);
                    formData.append('password', passwordInput.value);

                    const response = await fetch('backend/routes/complete_signup.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    hideLoading();

                    if (result.status) {
                        showModal('Success', 'Registration successful! Please log in.', 'login.php');
                    } else {
                        showModal('Error', result.message);
                    }
                } catch (error) {
                    hideLoading();
                    showModal('Error', 'An error occurred during registration.');
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

            // Debug functionality (remove in production)
            document.getElementById('testEmailBtn').addEventListener('click', async function() {
                const debugEmail = document.getElementById('debugEmail').value;
                const debugResult = document.getElementById('debugResult');

                if (!debugEmail || !debugEmail.includes('@')) {
                    debugResult.innerHTML = '<span class="text-danger">Please enter a valid email address.</span>';
                    return;
                }

                debugResult.innerHTML = '<span class="text-info">Sending test email...</span>';

                try {
                    const response = await fetch('backend/routes/test_email_verification.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            testEmail: debugEmail
                        })
                    });

                    const result = await response.json();

                    if (result.status) {
                        debugResult.innerHTML = `
                            <span class="text-success">✅ Test email sent successfully!</span><br>
                            <small class="text-muted">Check ${debugEmail} for the verification code: ${result.debug.code_generated}</small>
                        `;
                    } else {
                        debugResult.innerHTML = `
                            <span class="text-danger">❌ Failed to send test email: ${result.message}</span>
                        `;
                    }
                } catch (error) {
                    debugResult.innerHTML = '<span class="text-danger">❌ Error: ' + error.message + '</span>';
                }
            });

            // Show debug section (uncomment to enable)
            // document.getElementById('debugSection').style.display = 'block';
        });
    </script>
</body>

</html>