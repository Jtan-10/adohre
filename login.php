<!DOCTYPE html>
<html lang="en">


<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Member Link</title>
    <link rel="icon" href="assets/logo.png" type="image/jpg" />
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
        <h1 class="mt-3">Login</h1>
        <p>Enter your email or login via Virtual ID.</p>
        <form id="loginForm" class="w-75">
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" name="email" class="form-control" id="email" placeholder="Enter your email">
            </div>
            <button type="button" id="loginBtn" class="btn btn-success">Send OTP</button>
            <p id="error" class="text-danger mt-3"></p>
        </form>
        <p class="mt-3">Or</p>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#virtualIdModal">Login via
            Virtual ID</button>
    </div>
    <div class="right-pane"></div>
    <!-- Loading Screen -->
    <div id="loadingScreen"
        class="d-none position-fixed w-100 h-100 top-0 start-0 bg-white bg-opacity-75 d-flex justify-content-center align-items-center">
        <div class="spinner-border text-success" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- Virtual ID Modal -->
    <div class="modal fade" id="virtualIdModal" tabindex="-1" aria-labelledby="virtualIdModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="virtualIdModalLabel">Login via Virtual ID</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="virtualIdForm">
                        <label for="virtualIdImage" class="form-label">Upload Virtual ID Image</label>
                        <input type="file" name="virtualIdImage" id="virtualIdImage" class="form-control"
                            accept="image/*">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="uploadBtn">Login</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
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
    document.getElementById('loginBtn').addEventListener('click', async () => {
        const email = document.getElementById('email').value;

        if (!email) {
            showModal('Error', 'Please enter your email.');
            return;
        }

        showLoading(); // Show loading screen
        try {
            const response = await fetch('backend/routes/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    email
                })
            });

            const result = await response.json();
            hideLoading(); // Hide loading screen

            if (result.status) {
                showModal('Success', result.message, `otp.php?action=login&email=${email}`);
            } else {
                showModal('Error', result.message);
            }
        } catch (error) {
            hideLoading();
            console.error('Error:', error);
            showModal('Error', 'An error occurred. Please try again.');
        }
    });

    document.getElementById('uploadBtn').addEventListener('click', async () => {
        const formData = new FormData(document.getElementById('virtualIdForm'));
        const fileInput = document.getElementById('virtualIdImage');

        if (!fileInput.files.length) {
            alert('Please upload an image.');
            return;
        }

        try {
            const response = await fetch('backend/routes/login.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.status) {
                alert('Login successful.');
                window.location.href = 'index.php';
            } else {
                alert(result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
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