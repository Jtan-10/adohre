<?php
// Start session
session_start();

// Include database connection
require_once 'backend/db/db_connect.php';

// Include header
include 'header.php';

// Get action and other values from session storage
$action = '';
$email = '';
$incomplete = false;

if (isset($_SESSION['action'])) {
    $action = $_SESSION['action'];
}
if (isset($_SESSION['email'])) {
    $email = $_SESSION['email'];
}

// Check if user profile is incomplete (for existing users)
if ($action === 'login' && !empty($email)) {
    $stmt = $conn->prepare("SELECT is_profile_complete FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($isComplete);
    $stmt->fetch();
    $stmt->close();
    $incomplete = !$isComplete;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>

<body>
    <?php include 'visually_impaired_modal.php'; ?>
    <!-- OTP Section -->
    <div class="container-fluid min-vh-100 d-flex align-items-center justify-content-center" style="background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);">
        <div class="card p-4" style="width: 100%; max-width: 400px;">
            <div class="card-body" id="otp-section">
                <h2 class="text-center mb-4">OTP Verification</h2>
                <p class="text-center">Please enter the OTP sent to your email.</p>
                <form id="otpForm" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="otp" class="form-label">OTP</label>
                        <input type="text" class="form-control" id="otp" name="otp" required maxlength="6"
                            pattern="[0-9]{6}" inputmode="numeric">
                        <div class="invalid-feedback">
                            Please enter a valid 6-digit OTP.
                        </div>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Verify OTP</button>
                        <button type="button" class="btn btn-outline-primary" id="resendOtpBtn">Resend OTP</button>
                    </div>
                </form>
            </div>
            <div class="card-body" id="update-details-section" style="display: none;">
                <h2 class="text-center mb-4">Update Profile</h2>
                <form id="updateDetailsForm" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="update-first-name" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="update-first-name" name="firstName" required>
                        <div class="invalid-feedback">
                            Please provide your first name.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="update-last-name" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="update-last-name" name="lastName" required>
                        <div class="invalid-feedback">
                            Please provide your last name.
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </div>
                </form>
            </div>
            <div class="card-body" id="signup-section" style="display: none;">
                <h2 class="text-center mb-4">Sign Up</h2>
                <form id="signupForm" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="signup-first-name" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="signup-first-name" name="firstName" required>
                        <div class="invalid-feedback">
                            Please provide your first name.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="signup-last-name" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="signup-last-name" name="lastName" required>
                        <div class="invalid-feedback">
                            Please provide your last name.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="signup-password" class="form-label">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="signup-password" name="password" required
                                minlength="8">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">
                            Password must be at least 8 characters long.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="signup-confirm-password" class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="signup-confirm-password"
                                name="confirmPassword" required>
                            <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">
                            Passwords do not match.
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Complete Sign Up</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Response Modal -->
    <div class="modal fade" id="responseModal" tabindex="-1" aria-labelledby="responseModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="responseModalLabel">Response</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="responseModalBody">
                </div>
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
    <script>
        const action = <?php echo json_encode($action); ?>;
        const email = <?php echo json_encode($email); ?>;
        const incomplete = <?php echo json_encode($incomplete); ?>;

        document.addEventListener('DOMContentLoaded', () => {
            // Function to show loading spinner
            function showLoading() {
                document.getElementById('loadingSpinner').classList.remove('d-none');
            }

            // Function to hide loading spinner
            function hideLoading() {
                document.getElementById('loadingSpinner').classList.add('d-none');
            }

            // Function to show modal
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

            // OTP FORM SUBMISSION
            document.getElementById('otpForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                showLoading();

                const otp = document.getElementById('otp').value;

                try {
                    const response = await fetch('backend/routes/verify_otp.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            email: email,
                            otp: otp
                        })
                    });

                    if (!response.ok) {
                        const errorText = await response.text();
                        hideLoading();
                        showModal('Error', errorText);
                        return;
                    }

                    const result = await response.json();
                    hideLoading();

                    if (result.status) {
                        if (action === 'signup') {
                            showModal('Success', 'OTP Verified. Proceed to enter details.');
                            document.getElementById('otp-section').style.display = 'none';
                            document.getElementById('signup-section').style.display = 'block';
                        } else if (action === 'login') {
                            if (incomplete) {
                                showModal('Info', 'Your profile is incomplete. Please update your details.');
                                document.getElementById('otp-section').style.display = 'none';
                                document.getElementById('update-details-section').style.display = 'block';
                            } else {
                                showModal('Success', 'Login successful!', 'index.php');
                            }
                        }
                    } else {
                        showModal('Error', result.message);
                    }
                } catch (error) {
                    hideLoading();
                    console.error('Error:', error);
                    showModal('Error', 'An error occurred. Please try again.');
                }
            });

            // RESEND OTP BUTTON HANDLER
            document.getElementById('resendOtpBtn').addEventListener('click', async () => {
                const emailInput = document.getElementById('email') ? document.getElementById('email').value : email;
                if (!emailInput) {
                    showModal('Error', 'Email is required.');
                    return;
                }

                try {
                    showLoading();
                    const response = await fetch('backend/routes/resend_otp.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            email: emailInput
                        })
                    });

                    if (!response.ok) {
                        throw new Error('Failed to resend OTP');
                    }

                    const result = await response.json();
                    hideLoading();

                    if (result.status) {
                        showModal('Success', 'A new OTP has been sent to your email.');
                    } else {
                        showModal('Error', result.message);
                    }
                } catch (error) {
                    hideLoading();
                    showModal('Error', 'An error occurred while resending the OTP.');
                }
            });

            // SIGNUP FORM SUBMISSION
            document.getElementById('signupForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                if (!e.target.checkValidity()) {
                    e.stopPropagation();
                    e.target.classList.add('was-validated');
                    return;
                }

                const password = document.getElementById('signup-password').value;
                const confirmPassword = document.getElementById('signup-confirm-password').value;

                if (password !== confirmPassword) {
                    document.getElementById('signup-confirm-password').setCustomValidity('Passwords do not match');
                    e.target.classList.add('was-validated');
                    return;
                }

                showLoading();
                try {
                    const formData = new FormData();
                    formData.append('email', email);
                    formData.append('firstName', document.getElementById('signup-first-name').value);
                    formData.append('lastName', document.getElementById('signup-last-name').value);
                    formData.append('password', password);

                    const response = await fetch('backend/routes/complete_signup.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    hideLoading();

                    if (result.status) {
                        showModal('Success', 'Registration completed successfully!', 'index.php');
                    } else {
                        showModal('Error', result.message);
                    }
                } catch (error) {
                    hideLoading();
                    console.error('Error:', error);
                    showModal('Error', 'An error occurred during registration.');
                }
            });

            // UPDATE DETAILS FORM SUBMISSION
            document.getElementById('updateDetailsForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                if (!e.target.checkValidity()) {
                    e.stopPropagation();
                    e.target.classList.add('was-validated');
                    return;
                }

                showLoading();
                try {
                    const formData = new FormData();
                    formData.append('email', email);
                    formData.append('firstName', document.getElementById('update-first-name').value);
                    formData.append('lastName', document.getElementById('update-last-name').value);

                    const response = await fetch('backend/routes/update_profile.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    hideLoading();

                    if (result.status) {
                        showModal('Success', 'Profile updated successfully!', 'index.php');
                    } else {
                        showModal('Error', result.message);
                    }
                } catch (error) {
                    hideLoading();
                    console.error('Error:', error);
                    showModal('Error', 'An error occurred while updating your profile.');
                }
            });

            // Password toggle functionality
            const togglePassword = document.getElementById('togglePassword');
            const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');

            if (togglePassword) {
                togglePassword.addEventListener('click', () => {
                    const password = document.getElementById('signup-password');
                    const icon = togglePassword.querySelector('i');
                    if (password.type === 'password') {
                        password.type = 'text';
                        icon.classList.replace('bi-eye', 'bi-eye-slash');
                    } else {
                        password.type = 'password';
                        icon.classList.replace('bi-eye-slash', 'bi-eye');
                    }
                });
            }

            if (toggleConfirmPassword) {
                toggleConfirmPassword.addEventListener('click', () => {
                    const confirmPassword = document.getElementById('signup-confirm-password');
                    const icon = toggleConfirmPassword.querySelector('i');
                    if (confirmPassword.type === 'password') {
                        confirmPassword.type = 'text';
                        icon.classList.replace('bi-eye', 'bi-eye-slash');
                    } else {
                        confirmPassword.type = 'password';
                        icon.classList.replace('bi-eye-slash', 'bi-eye');
                    }
                });
            }
        });
    </script>
</body>

</html>
