<?php
// Add security headers before any output
header("X-Frame-Options: DENY");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
header("X-Content-Type-Options: nosniff");

session_start();
session_regenerate_id(true); // Prevent session fixation

require_once 'backend/db/db_connect.php';

// Check if face validation is enabled
$faceValidationEnabled = isFaceValidationEnabled();

// Securely handle GET parameters using filter_input
$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING);
$emailParam = filter_input(INPUT_GET, 'email', FILTER_VALIDATE_EMAIL);

// Default visually impaired flag is 0.
$isVisuallyImpaired = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT visually_impaired FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($visually_impaired);
    if ($stmt->fetch()) {
        $isVisuallyImpaired = $visually_impaired;
    }
    $stmt->close();
}

// For login: Fetch the user's stored face image, first_name, and last_name from the database.
$face_image_url = "";
$first_name = "";
$last_name = "";
$incomplete = false; // Flag to indicate if profile is incomplete.
if ($action === 'login' && $emailParam) {
    $login_email = trim($emailParam);
    $stmt = $conn->prepare("SELECT face_image, first_name, last_name FROM users WHERE email = ?");
    $stmt->bind_param("s", $login_email);
    $stmt->execute();
    $stmt->bind_result($face_image_url, $first_name, $last_name);
    $stmt->fetch();
    $stmt->close();
    if (empty($face_image_url) || empty($first_name) || empty($last_name)) {
        $incomplete = true;
    }
}
?>
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
            background: url('assets/green_bg.png') no-repeat center center/cover;
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
            display: none;
        }

        /* Hide details sections by default */
        #signup-section,
        #login-face-validation,
        #update-details-section {
            display: none;
        }

        /* Hide canvas to avoid large blank area */
        #faceCanvas,
        #loginCanvas {
            display: none;
        }

        /* Hide the preview image by default */
        #capturedFacePreview,
        #updateFaceCanvas,
        #updateCapturedFacePreview {
            display: none;
            max-width: 320px;
            margin-top: 10px;
            border: 1px solid #ccc;
        }

        /* Mobile mode: create individual white card layouts for each section with custom sizes */
        @media (max-width: 768px) {

            /* Use the green background for the entire body */
            body {
                flex-direction: column;
                background: url('assets/green_bg.png') no-repeat center center/cover;
                background-size: cover;
            }

            /* Hide the right pane on mobile */
            .right-pane {
                display: none;
            }

            /* Remove the left-pane pseudo-element styling */
            .left-pane {
                position: relative;
                background: transparent;
                padding: 15px;
                min-height: 100vh;
                align-items: center;
                justify-content: center;
            }

            /* Define individual card styles for each section */
            .card-otp,
            .card-signup,
            .card-update,
            .card-login {
                background: #ffffff;
                border-radius: 0.5rem;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
                margin: 10px auto;
                width: 100%;
                max-width: 600px;
                /* increased from 420px to 600px */
            }

            .card-otp {
                padding: 20px;
            }

            .card-signup {
                padding: 40px;
            }

            .card-update {
                padding: 30px;
            }

            .card-login {
                padding: 25px;
            }

            /* Override inline dimensions for canvas and video within card sections */
            .card-otp video,
            .card-otp canvas,
            .card-signup video,
            .card-signup canvas,
            .card-update video,
            .card-update canvas,
            .card-login video,
            .card-login canvas {
                width: 100% !important;
                height: auto !important;
                display: block;
                margin: 0 auto;
            }

            /* Ensure update captured face preview image fits the card */
            #updateCapturedFacePreview,
            #storedFacePreview {
                max-width: 100%;
                width: 100% !important;
                height: auto !important;
            }
        }
    </style>
    <!-- Include the global TTS module (create tts.js with your TTS functions) -->
    <script src="tts.js"></script>
    <!-- Pass the visually impaired flag, stored face image URL, and incomplete flag to JavaScript -->
    <script>
        var isVisuallyImpaired = <?php echo json_encode($isVisuallyImpaired); ?>;
        var storedFaceImageURL = <?php echo json_encode($face_image_url); ?>;
        var incompleteProfile = <?php echo json_encode($incomplete); ?>;
    </script>
</head>

<body>
    <div class="left-pane">
        <!-- Hide external header on mobile -->
        <img src="assets/logo.png" alt="Company Logo" width="100" class="d-none d-md-block">
        <h1 id="form-title" class="mt-3 d-none d-md-block">OTP Verification</h1>
        <p id="form-description" class="d-none d-md-block">Enter the OTP sent to your email.</p>
        <form id="otpForm" class="w-75">
            <!-- OTP Section -->
            <div id="otp-section" class="card-otp">
                <!-- Mobile Card Header for OTP Section -->
                <div class="card-header text-center d-md-none">
                    <img src="assets/logo.png" alt="Company Logo" width="100">
                    <h1>OTP Verification</h1>
                    <p>Enter the OTP sent to your email.</p>
                </div>
                <div class="mb-3">
                    <label for="otp" class="form-label">OTP</label>
                    <input type="text" name="otp" class="form-control" id="otp" placeholder="Enter OTP">
                </div>
                <button type="button" id="verifyBtn" class="btn btn-success">Verify OTP</button>
                <!-- Resend OTP Button -->
                <button type="button" id="resendOtpBtn" class="btn btn-secondary mt-2">Resend OTP</button>
            </div>

            <!-- SIGNUP SECTION (Only shown if action=signup AFTER OTP) -->
            <div id="signup-section" class="card-signup">
                <!-- Mobile Card Header for Signup Section -->
                <div class="card-header text-center d-md-none">
                    <img src="assets/logo.png" alt="Company Logo" width="100">
                    <h1>Sign Up</h1>
                    <p>Please enter your details to sign up.</p>
                </div>
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
                <div id="faceCaptureSection" style="margin-top:20px;<?php echo !$faceValidationEnabled ? ' display:none;' : ''; ?>">
                    <h4>Capture Your Face</h4>
                    <video id="faceVideo" width="320" height="240" autoplay muted
                        style="border:1px solid #ccc;"></video>
                    <br>
                    <button type="button" id="captureFaceBtn" class="btn btn-custom mt-2">Capture Face</button>
                    <canvas id="faceCanvas"></canvas>
                    <img id="capturedFacePreview" src="" alt="Captured Face">
                </div>
                <button type="button" id="submitDetailsBtn" class="btn btn-success mt-3">Submit Details</button>
            </div>

            <!-- UPDATE DETAILS SECTION (For login when profile is incomplete) -->
            <div id="update-details-section" class="card-update">
                <!-- Mobile Card Header for Update Details Section -->
                <div class="card-header text-center d-md-none">
                    <img src="assets/logo.png" alt="Company Logo" width="100">
                    <h1>Update Details</h1>
                    <p>Your profile is incomplete. Please update your details.</p>
                </div>
                <p>Your profile is incomplete. Please update your details.</p>
                <div class="mb-3">
                    <label for="update_first_name" class="form-label">First Name</label>
                    <input type="text" name="update_first_name" class="form-control" id="update_first_name"
                        placeholder="Enter your first name">
                </div>
                <div class="mb-3">
                    <label for="update_last_name" class="form-label">Last Name</label>
                    <input type="text" name="update_last_name" class="form-control" id="update_last_name"
                        placeholder="Enter your last name">
                </div>
                <div id="updateFaceCaptureSection" style="margin-top:20px;<?php echo !$faceValidationEnabled ? ' display:none;' : ''; ?>">
                    <h4>Capture Your Face</h4>
                    <video id="updateFaceVideo" width="320" height="240" autoplay muted
                        style="border:1px solid #ccc;"></video>
                    <br>
                    <button type="button" id="updateCaptureFaceBtn" class="btn btn-custom mt-2">Capture Face</button>
                    <canvas id="updateFaceCanvas"></canvas>
                    <img id="updateCapturedFacePreview" src="" alt="Captured Face">
                </div>
                <button type="button" id="updateDetailsBtn" class="btn btn-success mt-3">Update Details</button>
            </div>

            <!-- LOGIN FACE VALIDATION SECTION (Only shown if action=login and profile is complete) -->
            <div id="login-face-validation" class="card-login" style="<?php echo !$faceValidationEnabled ? 'display:none;' : ''; ?>">
                <!-- Mobile Card Header for Face Validation Section -->
                <div class="card-header text-center d-md-none">
                    <img src="assets/logo.png" alt="Company Logo" width="100">
                    <h1>Face Validation</h1>
                    <p>Proceed with face validation.</p>
                </div>
                <h4>Stored Face Reference</h4>
                <img id="storedFacePreview" src="" alt="Stored Face Reference"
                    style="max-width:320px; border:1px solid #ccc; margin-bottom:10px;">
                <h4>Capture Your Face</h4>
                <video id="videoInput" width="320" height="240" autoplay muted style="border:1px solid #ccc;"></video>
                <br>
                <button type="button" id="captureLoginBtn" class="btn btn-custom mt-2">Validate Face</button>
                <canvas id="loginCanvas"></canvas>
                <p id="faceValidationResult" style="margin-top:10px; font-weight:bold;"></p>
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
    <!-- Response Modal -->
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

    <!-- Check Spam Folder Modal -->
    <div class="modal fade" id="checkSpamModal" tabindex="-1" aria-labelledby="checkSpamModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="checkSpamModalLabel">Check Your Spam Folder</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    An OTP has been sent to your email address. If you do not see it in your inbox within a few minutes,
                    please check your spam or junk folder.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Visually Impaired Modal -->
    <?php
    define('IN_CAPSTONE', true);
    include 'visually_impaired_modal.php';
    ?>
    <!-- Load face-api.js BEFORE faceValidation.js only if face validation is enabled -->
    <?php if ($faceValidationEnabled): ?>
        <script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
        <script defer src="faceValidation.js"></script>
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
    </script>
    <script nonce="<?php echo $scriptNonce; ?>">
        const urlParams = new URLSearchParams(window.location.search);
        const email = urlParams.get('email') || sessionStorage.getItem('email');
        const action = urlParams.get('action'); // 'signup' or 'login'
        let capturedFaceData = "";

        // Face validation configuration
        const faceValidationEnabled = <?php echo json_encode($faceValidationEnabled); ?>;

        // For login face validation
        let referenceDescriptor = null;
        var storedFaceImageURL = "<?php echo $face_image_url; ?>";

        if (!email || !action) {
            alert("Invalid access. Please restart the process.");
            window.location.href = action === 'signup' ? 'signup.php' : 'login.php';
        } else {
            sessionStorage.setItem('email', email);
            sessionStorage.setItem('action', action);
        }

        // Initially, only the OTP section is visible.
        document.addEventListener('DOMContentLoaded', () => {
            // No extra section is revealed until OTP is verified.
        });

        // OTP Verification
        document.getElementById('verifyBtn').addEventListener('click', async () => {
            const otp = document.getElementById('otp').value;
            if (!otp) {
                showModal('Error', 'Please enter the OTP.');
                return;
            }
            showLoading();
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
                // Added check for non-OK HTTP responses
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
                        if (faceValidationEnabled) {
                            startFaceVideoForSignup();
                        }
                    } else if (action === 'login') {
                        if (<?php echo json_encode($incomplete); ?>) {
                            showModal('Info', 'Your profile is incomplete. Please update your details.');
                            document.getElementById('otp-section').style.display = 'none';
                            document.getElementById('update-details-section').style.display = 'block';
                            if (faceValidationEnabled) {
                                startFaceVideoForUpdate();
                            }
                        } else {
                            if (faceValidationEnabled) {
                                showModal('Success', 'OTP Verified. Proceed with face validation.');
                                document.getElementById('otp-section').style.display = 'none';
                                document.getElementById('login-face-validation').style.display = 'block';
                                await loadFaceApiModels();
                                await loadReferenceDescriptor();
                                startFaceVideoForLogin();
                            } else {
                                // Face validation is disabled - check if login was completed by verify_otp.php
                                if (result.face_validation_skipped) {
                                    showModal('Success', 'Login successful!', 'index.php');
                                } else {
                                    // Fallback: try to complete login manually (shouldn't be needed with new logic)
                                    showModal('Success', 'OTP Verified. Logging you in...');
                                    try {
                                        const completeResponse = await fetch('backend/routes/complete_login.php', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json'
                                            },
                                            body: JSON.stringify({
                                                email: email
                                            })
                                        });
                                        const completeResult = await completeResponse.json();
                                        if (completeResult.status) {
                                            showModal('Success', 'Login successful!', 'index.php');
                                        } else {
                                            showModal('Error', 'Error completing login: ' + completeResult.message);
                                        }
                                    } catch (error) {
                                        showModal('Error', 'Error completing login: ' + error.message);
                                    }
                                }
                            }
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

        // ARIA fix: Remove aria-hidden from the modal when it is shown.
        document.getElementById('responseModal').addEventListener('shown.bs.modal', (event) => {
            event.target.removeAttribute('aria-hidden');
        });

        // RESEND OTP BUTTON HANDLER
        document.getElementById('resendOtpBtn').addEventListener('click', async () => {
            const emailInput = document.getElementById('email') ? document.getElementById('email').value :
                sessionStorage.getItem('email');
            if (!emailInput) {
                showModal('Error', 'Please enter your email.');
                return;
            }
            showLoading();
            try {
                const response = await fetch('backend/routes/signup.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        email: emailInput
                    })
                });
                const result = await response.json();
                hideLoading();
                if (result.status) {
                    showModal('Success', result.message);
                    // Also show the spam folder instruction modal
                    const spamModal = new bootstrap.Modal(document.getElementById('checkSpamModal'));
                    spamModal.show();
                } else {
                    showModal('Error', result.message);
                }
            } catch (error) {
                hideLoading();
                console.error('Error:', error);
                showModal('Error', 'An error occurred. Please try again.');
            }
        });

        // Helper function to safely call face validation functions
        function safeFaceValidationCall(func, ...args) {
            if (!faceValidationEnabled) {
                console.log('Face validation is disabled, skipping function call');
                return Promise.resolve();
            }
            if (typeof func === 'function') {
                return func(...args);
            }
            return Promise.resolve();
        }

        // SIGNUP FLOW: Start face capture for signup
        async function startFaceVideoForSignup() {
            if (!faceValidationEnabled) return;
            const video = document.getElementById('faceVideo');
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: {}
                });
                video.srcObject = stream;
            } catch (error) {
                console.error("Error accessing webcam for signup face capture:", error);
                showModal('Webcam Error', 'Error accessing webcam for signup face capture: ' + error.message +
                    '. Please ensure your webcam is connected and allowed.');
            }
        }

        document.getElementById('captureFaceBtn').addEventListener('click', async () => {
            if (!faceValidationEnabled) return;
            await loadFaceApiModels();
            const video = document.getElementById('faceVideo');
            const canvas = document.getElementById('faceCanvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const context = canvas.getContext('2d', {
                willReadFrequently: true
            });
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            // Run face detection on the captured image.
            const detection = await faceapi.detectSingleFace(canvas, new faceapi.TinyFaceDetectorOptions({
                inputSize: 416,
                scoreThreshold: 0.5
            }));
            if (!detection) {
                showModal('Error', 'No face detected. Please recapture your face.');
                return;
            }
            capturedFaceData = canvas.toDataURL('image/png');
            console.log("Face captured for signup, length:", capturedFaceData.length);
            const preview = document.getElementById('capturedFacePreview');
            preview.src = capturedFaceData;
            preview.style.display = 'block';
        });

        document.getElementById('submitDetailsBtn').addEventListener('click', async () => {
            const first_name = document.getElementById('first_name').value;
            const last_name = document.getElementById('last_name').value;
            if (!first_name || !last_name) {
                showModal('Error', 'Please enter your first and last name.');
                return;
            }
            if (faceValidationEnabled && !capturedFaceData) {
                alert("Please capture your face before submitting your details.");
                return;
            }
            showLoading();
            try {
                const response = await fetch('backend/routes/signup.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        email,
                        first_name,
                        last_name,
                        faceData: faceValidationEnabled ? capturedFaceData : null
                    })
                });
                const result = await response.json();
                hideLoading();
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

        // LOGIN FLOW: Load models, load stored face descriptor, start face capture for login.
        async function loadFaceApiModels() {
            if (!faceValidationEnabled) return;
            if (typeof faceValidation !== 'undefined') {
                await faceValidation.loadModels('backend/models/weights');
            }
        }

        async function loadReferenceDescriptor() {
            if (!faceValidationEnabled) return;
            if (!storedFaceImageURL) {
                console.warn("No stored face image URL for login.");
                return;
            }
            try {
                const fullURL =
                    `backend/routes/decrypt_image.php?face_url=${encodeURIComponent(storedFaceImageURL)}`;
                const img = new Image();
                img.crossOrigin = "anonymous";
                img.src = fullURL;
                await new Promise((resolve, reject) => {
                    img.onload = resolve;
                    img.onerror = reject;
                });
                document.getElementById('storedFacePreview').src = img.src;
                const detection = await faceapi.detectSingleFace(img, new faceapi.TinyFaceDetectorOptions({
                        inputSize: 416,
                        scoreThreshold: 0.5
                    }))
                    .withFaceLandmarks()
                    .withFaceDescriptor();
                if (detection) {
                    referenceDescriptor = detection.descriptor;
                    console.log("Reference descriptor loaded for login.");
                } else {
                    console.warn("No face detected in the stored reference image.");
                }
            } catch (error) {
                console.error("Error loading reference descriptor:", error);
            }
        }

        async function startFaceVideoForLogin() {
            if (!faceValidationEnabled) return;
            const video = document.getElementById('videoInput');
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: {}
                });
                video.srcObject = stream;
            } catch (error) {
                console.error("Error accessing webcam for login face validation:", error);
                showModal('Webcam Error', 'Error accessing webcam for login face capture: ' + error.message +
                    '. Please ensure your webcam is connected and allowed.');
            }
        }

        // For update details flow (if profile is incomplete)
        async function startFaceVideoForUpdate() {
            if (!faceValidationEnabled) return;
            const video = document.getElementById('updateFaceVideo');
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: {}
                });
                video.srcObject = stream;
            } catch (error) {
                console.error("Error accessing webcam for update face capture:", error);
                showModal('Webcam Error', 'Error accessing webcam for update face capture: ' + error.message +
                    '. Please ensure your webcam is connected and allowed.');
            }
        }

        document.getElementById('updateCaptureFaceBtn').addEventListener('click', async () => {
            if (!faceValidationEnabled) return;
            await loadFaceApiModels();
            const video = document.getElementById('updateFaceVideo');
            const canvas = document.getElementById('updateFaceCanvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const context = canvas.getContext('2d', {
                willReadFrequently: true
            });
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            const detection = await faceapi.detectSingleFace(canvas, new faceapi.TinyFaceDetectorOptions({
                inputSize: 416,
                scoreThreshold: 0.5
            }));
            if (!detection) {
                showModal('Error', 'No face detected. Please recapture your face.');
                return;
            }
            updateCapturedFaceData = canvas.toDataURL('image/png');
            console.log("Face captured for update, length:", updateCapturedFaceData.length);
            const preview = document.getElementById('updateCapturedFacePreview');
            preview.src = updateCapturedFaceData;
            preview.style.display = 'block';
        });

        document.getElementById('updateDetailsBtn').addEventListener('click', async () => {
            const first_name = document.getElementById('update_first_name').value;
            const last_name = document.getElementById('update_last_name').value;
            if (!first_name || !last_name) {
                showModal('Error', 'Please enter your first and last name.');
                return;
            }
            if (faceValidationEnabled && !updateCapturedFaceData) {
                alert("Please capture your face before submitting your details.");
                return;
            }
            showLoading();
            try {
                const response = await fetch('backend/routes/update_user_details.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        email,
                        first_name,
                        last_name,
                        faceData: faceValidationEnabled ? updateCapturedFaceData : null
                    })
                });
                const result = await response.json();
                hideLoading();
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

        // LOGIN FLOW: Validate face
        document.getElementById('captureLoginBtn').addEventListener('click', async () => {
            if (!faceValidationEnabled) return;
            const video = document.getElementById('videoInput');
            const canvas = document.getElementById('loginCanvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const context = canvas.getContext('2d');
            context.drawImage(video, 0, 0, canvas.width, canvas.height);

            // Perform face detection using your faceValidation module
            const detection = await faceValidation.detectFace(canvas);
            const resultParagraph = document.getElementById('faceValidationResult');
            if (detection && referenceDescriptor) {
                const distance = faceapi.euclideanDistance(detection.descriptor, referenceDescriptor);
                console.log("Face match distance:", distance);
                const threshold = 0.6;
                if (distance < threshold) {
                    resultParagraph.innerText = "Face matched successfully!";
                    // Only now, call the backend endpoint to complete login
                    try {
                        const completeResponse = await fetch('backend/routes/complete_login.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                email: email
                            })
                        });
                        const completeResult = await completeResponse.json();
                        if (completeResult.status) {
                            showModal('Success', 'Login successful!', 'index.php');
                        } else {
                            resultParagraph.innerText = "Error finalizing login: " + completeResult.message;
                        }
                    } catch (error) {
                        resultParagraph.innerText = "Error finalizing login: " + error.message;
                    }
                } else {
                    resultParagraph.innerText = "Face did not match. Please try again.";
                }
            } else {
                resultParagraph.innerText = "No face detected or no reference available.";
            }
        });


        // Utility: Show/hide loading screen
        function showLoading() {
            const ls = document.getElementById('loadingScreen');
            ls.classList.remove('d-none');
            ls.style.display = 'flex';
        }

        function hideLoading() {
            const ls = document.getElementById('loadingScreen');
            ls.classList.add('d-none');
            ls.style.display = 'none';
        }

        // Utility: Show a Bootstrap modal
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

        // Function to send the visually impaired response to the server and close the modal.
        function setVisuallyImpaired(isImpaired, modalInstance) {
            fetch('backend/routes/set_visually_impaired.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        visually_impaired: isImpaired
                    })
                })
                .then(response => response.json())
                .then(data => {
                    console.log("Database updated:", data);
                    modalInstance.hide();
                })
                .catch(error => {
                    console.error("Error updating database:", error);
                    modalInstance.hide();
                });
        }
    </script>
</body>

</html>