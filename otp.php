
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification - Member Link</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    body {
        display: flex;
        min-height: 100vh;
        margin: 0;
    }

    .left-pane {
        flex: 1;
        display: flex;
        justify-content: center;
        align-items: center;
        flex-direction: column;
        background: #ffffff;
    }

    .right-pane {
        flex: 1;
        background: url('assets/green-bg.jpg') no-repeat center center/cover;
    }

    .form-control {
        border-radius: 0.5rem;
    }

    .btn-success {
        width: 100%;
        border-radius: 0.5rem;
    }

    #loadingScreen {
        z-index: 1055;
        /* Ensure it appears above everything else */
        display: none;
    }
    </style>
</head>

<body>
    <div class="left-pane">
        <img src="assets/logo.png" alt="Company Logo" width="100">
        <h1 id="form-title" class="mt-3">OTP Verification</h1>
        <p id="form-description">Enter the OTP sent to your email.</p>
        <form id="otpForm" class="w-75">
            <div id="otp-section">
                <div class="mb-3">
                    <label for="otp" class="form-label">OTP</label>
                    <input type="text" name="otp" class="form-control" id="otp" placeholder="Enter OTP">
                </div>
                <button type="button" id="verifyBtn" class="btn btn-success">Verify OTP</button>
            </div>

            <div id="details-section" style="display: none;">
                <div class="mb-3">
                    <label for="first_name" class="form-label">First Name</label>
                    <input type="text" name="first_name" class="form-control" id="first_name"
                        placeholder="Enter your first name">
                </div>
                <div class="mb-3">
                    <label for="last_name" class="form-label">Last Name</label>
                    <input type="text" name="last_name" class="form-control" id="last_name"
                        placeholder="Enter your last name">
                </div>
                <button type="button" id="submitDetailsBtn" class="btn btn-success">Submit Details</button>
            </div>
            <p id="error" class="text-danger mt-3"></p>
        </form>
    </div>
    <div class="right-pane"></div>
    <!-- Loading Screen -->
    <div id="loadingScreen"
        class="d-none position-fixed w-100 h-100 top-0 start-0 bg-white bg-opacity-75 d-flex justify-content-center align-items-center">
        <div class="spinner-border text-success" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- Bootstrap Modal -->
    <div class="modal fade" id="responseModal" tabindex="-1" aria-labelledby="responseModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="responseModalLabel">Notification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="responseModalBody">
                    <!-- Response message will be inserted here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
    </script>
    <script>
    // Retrieve the email and action from the query parameter
    const urlParams = new URLSearchParams(window.location.search);
    const email = urlParams.get('email') || sessionStorage.getItem('email');
    const action = urlParams.get('action'); // Either 'login' or 'signup'

    if (!email || !action) {
        alert("Invalid access. Please restart the process.");
        // Redirect back to the appropriate page
        window.location.href = action === 'signup' ? 'signup.php' : 'login.php';
    } else {
        // Store email and action in sessionStorage for further use
        sessionStorage.setItem('email', email);
        sessionStorage.setItem('action', action);
    }

    // OTP Verification
    document.getElementById('verifyBtn').addEventListener('click', async () => {
        const otp = document.getElementById('otp').value;
        const email = sessionStorage.getItem('email');
        const action = sessionStorage.getItem('action');

        if (!otp) {
            showModal('Error', 'Please enter the OTP.');
            return;
        }

        showLoading(); // Show loading screen
        try {
            const response = await fetch('backend/routes/verify_otp.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    email,
                    otp
                })
            });

            const result = await response.json();
            hideLoading(); // Hide loading screen

            if (result.status) {
                if (action === 'signup') {
                    showModal('Success', 'OTP Verified. Proceeding to enter details.');
                    document.getElementById('otp-section').style.display = 'none';
                    document.getElementById('details-section').style.display = 'block';
                } else {
                    showModal('Success', 'Login successful!', 'index.php');
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

    // Submit Signup Details
    document.getElementById('submitDetailsBtn').addEventListener('click', async () => {
        const first_name = document.getElementById('first_name').value;
        const last_name = document.getElementById('last_name').value;
        const email = sessionStorage.getItem('email');

        if (!first_name || !last_name) {
            showModal('Error', 'Please enter your first and last name.');
            return;
        }

        showLoading(); // Show loading screen
        try {
            const response = await fetch('backend/routes/signup.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    email,
                    first_name,
                    last_name
                })
            });

            const result = await response.json();
            hideLoading(); // Hide loading screen

            if (result.status) {
                showModal('Success', result.message, 'login.php');
            } else {
                showModal('Error', result.message);
            }
        } catch (error) {
            hideLoading();
            console.error('Error:', error);
            showModal('Error', 'An error occurred. Please try again.');
        }
    });

    // Show Loading Screen
    function showLoading() {
        document.getElementById('loadingScreen').classList.remove('d-none');
        document.getElementById('loadingScreen').style.display = 'flex';
    }

    // Hide Loading Screen
    function hideLoading() {
        document.getElementById('loadingScreen').classList.add('d-none');
        document.getElementById('loadingScreen').style.display = 'none';
    }

    // Show Bootstrap Modal
    function showModal(title, message, redirectUrl = null) {
        document.getElementById('responseModalLabel').textContent = title;
        document.getElementById('responseModalBody').textContent = message;
        const modal = new bootstrap.Modal(document.getElementById('responseModal'));
        modal.show();

        if (redirectUrl) {
            modal._element.addEventListener('hidden.bs.modal', () => {
                window.location.href = redirectUrl;
            });
        }
    }
    </script>
</body>

</html>