<?php
// Start session and generate CSRF token if not exists
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// Create a unique nonce for this page
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

    <!-- We no longer include a client-side QR library because QR decoding is done on the backend -->
    <!-- Include face-api.js and faceValidation module -->
    <script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    <script defer src="faceValidation.js"></script>

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

        @media only screen and (max-width: 768px) {
            body {
                flex-direction: column;
            }

            .left-pane,
            .right-pane {
                flex: unset;
                width: 100%;
            }

            .right-pane {
                height: 200px;
            }
        }
    </style>
</head>

<body>
    <div class="left-pane">
        <img src="assets/logo.png" alt="Company Logo" width="100">
        <h1 class="mt-3">Login</h1>
        <p>Enter your email or login via Virtual ID.</p>
        <!-- Email Login Form (fallback) -->
        <form id="loginForm" class="w-75">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" name="email" class="form-control" id="email" placeholder="Enter your email">
            </div>
            <button type="button" id="loginBtn" class="btn btn-success">Send OTP</button>
            <p id="error" class="text-danger mt-3"></p>
        </form>
        <p class="mt-3">Or</p>
        <!-- Virtual ID Login Button -->
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#virtualIdModal">
            Login via Virtual ID
        </button>
    </div>
    <div class="right-pane"></div>

    <!-- Loading Screen -->
    <div id="loadingScreen"
        class="d-none position-fixed w-100 h-100 top-0 start-0 bg-white bg-opacity-75 d-flex justify-content-center align-items-center">
        <div class="spinner-border text-success" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- 1) Virtual ID Modal -->
    <div class="modal fade" id="virtualIdModal" tabindex="-1" aria-labelledby="virtualIdModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="virtualIdModalLabel">Login via Virtual ID</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- The user uploads their Virtual ID image -->
                    <form id="virtualIdForm" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <label for="virtualIdImage" class="form-label">Upload Virtual ID Image</label>
                        <input type="file" name="virtualIdImage" id="virtualIdImage" class="form-control"
                            accept="image/*">
                    </form>
                    <p id="qrError" class="text-danger mt-2"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="processQrBtn">Next</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 2) Face Validation Modal -->
    <div class="modal fade" id="faceValidationModal" tabindex="-1" aria-labelledby="faceValidationModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="faceValidationModalLabel">Face Validation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Stored face reference (using face_image as in otp.php) -->
                    <h4>Stored Face Reference</h4>
                    <!-- The stored face image is decrypted and loaded via the decryption endpoint -->
                    <img id="storedFacePreview" src="" alt="Stored Face Reference"
                        style="max-width:320px; border:1px solid #ccc; margin-bottom:10px; display:block;">
                    <!-- Live face capture -->
                    <h4>Capture Your Face</h4>
                    <video id="videoInput" width="320" height="240" autoplay muted
                        style="border:1px solid #ccc;"></video>
                    <br />
                    <button type="button" class="btn btn-primary mt-2" id="validateFaceBtn">Validate Face</button>
                    <!-- Hidden canvas for capturing user face -->
                    <canvas id="userFaceCanvas" style="display: none;"></canvas>
                    <p id="faceValidationResult" style="margin-top:10px; font-weight:bold;"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Response Modal (General Notifications) -->
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

    <!-- Update Details Modal -->
    <div class="modal fade" id="updateDetailsModal" tabindex="-1" aria-labelledby="updateDetailsModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateDetailsModalLabel">Update Profile Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Input fields for user details -->
                    <div class="mb-3">
                        <label for="update_first_name_modal" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="update_first_name_modal"
                            placeholder="Enter your first name">
                    </div>
                    <div class="mb-3">
                        <label for="update_last_name_modal" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="update_last_name_modal"
                            placeholder="Enter your last name">
                    </div>
                    <!-- Face capture section -->
                    <div id="updateFaceCaptureSectionModal" class="mb-3">
                        <h4>Capture Your Face</h4>
                        <video id="updateFaceVideoModal" width="320" height="240" autoplay muted
                            style="border:1px solid #ccc;"></video>
                        <br>
                        <button type="button" id="updateCaptureFaceBtnModal" class="btn btn-custom mt-2">Capture
                            Face</button>
                        <canvas id="updateFaceCanvasModal" style="display:none;"></canvas>
                        <img id="updateCapturedFacePreviewModal" src="" alt="Captured Face"
                            style="display:none; max-width:320px; margin-top:10px; border:1px solid #ccc;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="updateDetailsBtnModal" class="btn btn-success">Update Details</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
    </script>

    <script nonce="<?php echo $scriptNonce; ?>">
        document.addEventListener('DOMContentLoaded', () => {
            // -----------------------
            // 1) Email OTP Login (Fallback)
            // -----------------------
            document.getElementById('loginBtn').addEventListener('click', async () => {
                const email = document.getElementById('email').value;
                if (!email) {
                    showModal('Error', 'Please enter your email.');
                    return;
                }
                showLoading();
                try {
                    const response = await fetch('backend/routes/login.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            email,
                            csrf_token: '<?php echo $_SESSION["csrf_token"]; ?>'
                        })
                    });
                    const result = await response.json();
                    hideLoading();
                    if (result.status) {
                        showModal('Success', result.message, `otp.php?action=login&email=${email}`);
                        const spamModal = new bootstrap.Modal(document.getElementById(
                            'checkSpamModal'));
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

            // Global variables to hold user data and reference descriptors
            let globalUserData = null;
            let globalVirtualId = null;
            let referenceDescriptor = null;

            // -----------------------
            // 2) Virtual ID: Upload Image & Retrieve User Data
            // -----------------------
            document.getElementById('processQrBtn').addEventListener('click', async () => {
                const fileInput = document.getElementById('virtualIdImage');
                if (!fileInput.files.length) {
                    showModal('Error', 'Please upload an image.');
                    return;
                }
                // Create FormData with the uploaded file and set action to "fetch"
                const formData = new FormData();
                formData.append('virtualIdImage', fileInput.files[0]);
                formData.append('csrf_token', '<?php echo $_SESSION["csrf_token"]; ?>');
                formData.append('action', 'fetch');
                try {
                    showLoading();
                    // Call the same login.php endpoint with action=fetch
                    const response = await fetch('backend/routes/login.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    hideLoading();
                    if (result.status) {
                        globalUserData = result.user;
                        // Save the extracted virtual ID returned from backend
                        globalVirtualId = result.user.virtual_id;
                        // Check for incomplete profile
                        if (!globalUserData.first_name || !globalUserData.last_name || !globalUserData
                            .face_image) {
                            showModal('Info',
                                'Your profile is incomplete. Please update your details.');
                            showUpdateDetailsModal();
                        } else {
                            // Hide the Virtual ID modal and proceed to face validation
                            const vidModal = bootstrap.Modal.getInstance(document.getElementById(
                                'virtualIdModal'));
                            vidModal.hide();
                            startWebcam();
                            await faceValidation.loadModels('backend/models/weights');
                            // Use decryption to load the clear stored face image.
                            await loadReferenceDescriptor(globalUserData.face_image);
                            showFaceValidationModal();
                        }
                    } else {
                        showModal('Error', result.message);
                    }
                } catch (error) {
                    hideLoading();
                    console.error('Error fetching user data:', error);
                    showModal('Error', 'An error occurred fetching user data.');
                }
            });

            // -----------------------
            // 3) Face Validation Flow
            // -----------------------
            function showFaceValidationModal() {
                // The decrypted image is already loaded in loadReferenceDescriptor()
                const faceValModal = new bootstrap.Modal(document.getElementById('faceValidationModal'));
                faceValModal.show();
            }

            async function startWebcam() {
                const video = document.getElementById('videoInput');
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({
                        video: {}
                    });
                    video.srcObject = stream;
                } catch (error) {
                    console.error('Error accessing webcam:', error);
                    showModal('Error', 'Could not access webcam. Please allow camera access.');
                }
            }

            // Updated loadReferenceDescriptor() to use the decryption endpoint.
            async function loadReferenceDescriptor(faceImageUrl) {
                if (!faceImageUrl) {
                    console.warn("No stored face image URL for login.");
                    return;
                }
                // Build the URL for decryption.
                const decryptUrl =
                    `backend/routes/decrypt_image.php?face_url=${encodeURIComponent(faceImageUrl)}`;
                const img = new Image();
                img.crossOrigin = "anonymous";
                img.src = decryptUrl;
                await new Promise((resolve, reject) => {
                    img.onload = resolve;
                    img.onerror = reject;
                });
                // Set the decrypted image as the stored face preview.
                document.getElementById('storedFacePreview').src = img.src;
                try {
                    const detection = await faceapi.detectSingleFace(img, new faceapi.TinyFaceDetectorOptions({
                            inputSize: 416,
                            scoreThreshold: 0.5
                        }))
                        .withFaceLandmarks()
                        .withFaceDescriptor();
                    if (!detection) {
                        console.error('No face detected in the decrypted reference image.');
                        return;
                    }
                    referenceDescriptor = detection.descriptor;
                    console.log("Reference descriptor loaded for login.");
                } catch (error) {
                    console.error("Error in face detection on decrypted image:", error);
                }
            }

            async function startFaceVideoForLogin() {
                const video = document.getElementById('videoInput');
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({
                        video: {}
                    });
                    video.srcObject = stream;
                } catch (error) {
                    console.error("Error accessing webcam for login face validation:", error);
                    showModal('Error', 'Could not access webcam for login face capture.');
                }
            }

            // For update details flow (if profile is incomplete)
            async function startFaceVideoForUpdate() {
                const video = document.getElementById('updateFaceVideo');
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({
                        video: {}
                    });
                    video.srcObject = stream;
                } catch (error) {
                    console.error("Error accessing webcam for update face capture:", error);
                    showModal('Error', 'Could not access webcam for update face capture.');
                }
            }

            // New function to show the Update Details Modal and start webcam for face capture
            function showUpdateDetailsModal() {
                const updateModal = new bootstrap.Modal(document.getElementById('updateDetailsModal'));
                updateModal.show();
                startFaceVideoForUpdateModal();
            }

            async function startFaceVideoForUpdateModal() {
                const video = document.getElementById('updateFaceVideoModal');
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({
                        video: {}
                    });
                    video.srcObject = stream;
                } catch (error) {
                    console.error("Error accessing webcam for update face capture:", error);
                    showModal('Error', 'Could not access webcam for updating face capture.');
                }
            }

            let updateCapturedFaceDataModal = "";
            document.getElementById('updateCaptureFaceBtnModal').addEventListener('click', async () => {
                await faceValidation.loadModels('backend/models/weights');
                const video = document.getElementById('updateFaceVideoModal');
                const canvas = document.getElementById('updateFaceCanvasModal');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                const context = canvas.getContext('2d', {
                    willReadFrequently: true
                });
                context.drawImage(video, 0, 0, canvas.width, canvas.height);
                const detection = await faceapi.detectSingleFace(canvas, new faceapi
                    .TinyFaceDetectorOptions({
                        inputSize: 416,
                        scoreThreshold: 0.5
                    }));
                if (!detection) {
                    showModal('Error', 'No face detected. Please recapture your face.');
                    return;
                }
                updateCapturedFaceDataModal = canvas.toDataURL('image/png');
                const preview = document.getElementById('updateCapturedFacePreviewModal');
                preview.src = updateCapturedFaceDataModal;
                preview.style.display = 'block';
            });

            document.getElementById('updateDetailsBtnModal').addEventListener('click', async () => {
                const first_name = document.getElementById('update_first_name_modal').value;
                const last_name = document.getElementById('update_last_name_modal').value;
                if (!first_name || !last_name) {
                    showModal('Error', 'Please enter your first and last name.');
                    return;
                }
                if (!updateCapturedFaceDataModal) {
                    showModal('Error', 'Please capture your face before submitting your details.');
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
                            email: globalUserData.email,
                            first_name,
                            last_name,
                            faceData: updateCapturedFaceDataModal
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

            document.getElementById('validateFaceBtn').addEventListener('click', async () => {
                const video = document.getElementById('videoInput');
                const canvas = document.getElementById('userFaceCanvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                const detection = await faceapi
                    .detectSingleFace(canvas, new faceapi.TinyFaceDetectorOptions({
                        inputSize: 416,
                        scoreThreshold: 0.5
                    }))
                    .withFaceLandmarks()
                    .withFaceDescriptor();
                const resultParagraph = document.getElementById('faceValidationResult');
                if (!detection) {
                    resultParagraph.innerText = 'No face detected. Please try again.';
                    return;
                }
                if (!referenceDescriptor) {
                    resultParagraph.innerText = 'No reference face found. Please contact support.';
                    return;
                }
                const distance = faceapi.euclideanDistance(detection.descriptor, referenceDescriptor);
                console.log('Distance:', distance);
                const threshold = 0.6;
                if (distance < threshold) {
                    resultParagraph.innerText = 'Face matched successfully!';
                    try {
                        showLoading();
                        const formData = new FormData();
                        formData.append('virtual_id', globalVirtualId);
                        formData.append('csrf_token', '<?php echo $_SESSION["csrf_token"]; ?>');
                        formData.append('action', 'finalize');
                        const response = await fetch('backend/routes/login.php', {
                            method: 'POST',
                            body: formData
                        });
                        const finalResult = await response.json();
                        hideLoading();
                        if (finalResult.status) {
                            showModal('Success', 'Login successful!', 'index.php');
                        } else {
                            resultParagraph.innerText = 'Error finalizing login: ' + finalResult
                                .message;
                        }
                    } catch (error) {
                        hideLoading();
                        resultParagraph.innerText = 'Error finalizing login: ' + error.message;
                    }
                } else {
                    resultParagraph.innerText = 'Face did not match. Please try again.';
                }
            });

            document.getElementById('updateCaptureFaceBtn').addEventListener('click', async () => {
                await loadFaceApiModels();
                const video = document.getElementById('updateFaceVideo');
                const canvas = document.getElementById('updateFaceCanvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                const context = canvas.getContext('2d', {
                    willReadFrequently: true
                });
                context.drawImage(video, 0, 0, canvas.width, canvas.height);
                const detection = await faceapi.detectSingleFace(canvas, new faceapi
                    .TinyFaceDetectorOptions({
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
        });
    </script>
</body>

</html>