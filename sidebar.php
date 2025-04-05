<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Generate a unique nonce for inline scripts
$scriptNonce = bin2hex(random_bytes(16));

$current_page = basename($_SERVER['PHP_SELF']);
$submenuActive = ($current_page == 'chat_assistance.php' || $current_page == 'appointments.php' || $current_page == 'medical_assistance.php');

// Optional: If you already know the logged-in user's face_image URL, you can store it in a variable.
$userFaceImageUrl = isset($_SESSION['face_image']) ? $_SESSION['face_image'] : null;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Sidebar</title>
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
    /* Modern Sidebar Styles */
    #sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100%;
        width: 250px;
        background-color: #198754;
        color: #fff;
        transition: transform 0.3s ease;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    /* When collapsed, slide completely off-screen */
    #sidebar.collapsed {
        transform: translateX(-100%);
    }

    /* Remove default list styles and center links */
    #sidebar ul.components {
        list-style: none;
        margin: 0;
        padding: 0;
        text-align: center;
        width: 100%;
    }

    #sidebar ul li {
        margin: 10px 0;
    }

    /* Sidebar links: centered text, no underline */
    #sidebar ul li a {
        display: block;
        padding: 10px;
        font-size: 1.1em;
        color: #fff;
        text-decoration: none;
        transition: background 0.3s ease;
    }

    /* Hover and active state */
    #sidebar ul li a:hover,
    #sidebar ul li.active>a {
        background: #157347;
        border-radius: 4px;
    }

    /* Submenu styling */
    #sidebar ul li ul.submenu {
        list-style: none;
        margin: 0;
        padding: 0;
        text-align: center;
    }

    #sidebar ul li ul.submenu li {
        margin: 5px 0;
    }

    #sidebar ul li ul.submenu li a {
        padding: 8px;
        font-size: 1em;
    }

    /* Toggle Button styling */
    #sidebarCollapse {
        position: fixed;
        top: 50%;
        transform: translateY(-50%);
        background: #198754;
        border: none;
        border-radius: 0 50% 50% 0;
        padding: 10px;
        cursor: pointer;
        z-index: 1001;
        color: #fff;
        font-size: 1.2em;
        transition: left 0.3s ease;
    }
    </style>
</head>

<body>
    <!-- Sidebar Navigation -->
    <div id="sidebar">
        <ul class="components">
            <li <?php if ($current_page == 'index.php') echo 'class="active"'; ?>><a href="index.php">Home</a></li>
            <li <?php if ($current_page == 'about.php') echo 'class="active"'; ?>><a href="about.php">About Us</a></li>
            <li <?php if ($current_page == 'news.php') echo 'class="active"'; ?>><a href="news.php">News</a></li>
            <li <?php if ($current_page == 'membership_form.php') echo 'class="active"'; ?>><a
                    href="membership_form.php">Member Application</a></li>

            <?php if (isset($_SESSION['user_id'])): ?>
            <li>
                <!-- The "Virtual ID" link triggers the face validation modal -->
                <a href="#" id="virtualIdLink" data-user-id="<?php echo $_SESSION['user_id']; ?>">Virtual ID</a>
            </li>
            <?php endif; ?>

            <li <?php if ($current_page == 'health.php') echo 'class="active"'; ?>>
                <a data-bs-toggle="offcanvas" href="#offcanvasHealth" role="button" aria-controls="offcanvasHealth">
                    Health Tips
                </a>
            </li>

            <?php if (isset($_SESSION['user_id']) && (isset($_SESSION['role']) && $_SESSION['role'] !== 'user')): ?>
            <li <?php if ($current_page == 'member_services.php' || $submenuActive) echo 'class="active"'; ?>>
                <!-- Simplified Member Services toggler using Bootstrap's data attributes -->
                <a href="#memberServicesSubmenu" data-bs-toggle="collapse" role="button"
                    aria-expanded="<?php echo $submenuActive ? 'true' : 'false'; ?>"
                    aria-controls="memberServicesSubmenu">
                    Member Services <span
                        id="memberServicesArrow"><?php echo $submenuActive ? '&uarr;' : '&darr;'; ?></span>
                </a>
                <ul class="collapse submenu <?php echo $submenuActive ? 'show' : ''; ?>" id="memberServicesSubmenu">
                    <li <?php if ($current_page == 'consultation.php') echo 'class="active"'; ?>>
                        <a href="consultation.php">Chat Assistance</a>
                    </li>
                    <li <?php if ($current_page == 'appointments.php') echo 'class="active"'; ?>>
                        <a href="appointments.php">Appointments</a>
                    </li>
                    <li <?php if ($current_page == 'medical_assistance.php') echo 'class="active"'; ?>>
                        <a href="medical_assistance.php">Medical Assistance</a>
                    </li>
                </ul>
            </li>
            <li <?php if ($current_page == 'events.php') echo 'class="active"'; ?>>
                <a href="events.php">Events</a>
            </li>
            <?php endif; ?>

            <?php if (isset($_SESSION['user_id'])): ?>
            <li <?php if ($current_page == 'trainings.php') echo 'class="active"'; ?>>
                <a href="trainings.php">Trainings</a>
            </li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Off-Canvas Panel for Health and Wellness -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasHealth" aria-labelledby="offcanvasHealthLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="offcanvasHealthLabel">Health and Wellness</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <?php include('health.php'); ?>
        </div>
    </div>

    <!-- PDF Password Modal (if needed) -->
    <div class="modal fade" id="pdfPasswordModal" tabindex="-1" aria-labelledby="pdfPasswordModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pdfPasswordModalLabel">Your PDF Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="pdfPasswordText"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Face Validation Modal -->
    <div class="modal fade" id="faceValidationModal" tabindex="-1" aria-labelledby="faceValidationModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="faceValidationModalLabel">Face Validation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Show stored face reference -->
                    <div class="mb-3">
                        <h5>Stored Face Reference</h5>
                        <img id="storedFacePreview" src="" alt="Stored Face Reference"
                            style="width:100%; max-width:320px; border:1px solid #ccc; display:block;">
                    </div>
                    <!-- Live face capture -->
                    <div class="mb-3">
                        <h5>Capture Your Face</h5>
                        <video id="videoInput" width="320" height="240" autoplay muted
                            style="border:1px solid #ccc;"></video>
                    </div>
                    <!-- Validate Face button + hidden canvas for capture -->
                    <button type="button" class="btn btn-primary" id="validateFaceBtn">Validate Face</button>
                    <canvas id="userFaceCanvas" style="display:none;"></canvas>

                    <p id="faceValidationResult" class="mt-3" style="font-weight:bold;"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Response Modal for Notifications -->
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

    <!-- Toggle Button for Sidebar -->
    <button id="sidebarCollapse">&gt;</button>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Load face-api.js from an allowed source -->
    <script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>

    <!-- Inline Face Validation Module -->
    <script nonce="<?php echo $scriptNonce; ?>" defer>
    (function(global) {
        "use strict";
        async function loadModels(modelUrl = 'backend/models/weights') {
            if (typeof modelUrl !== 'string') {
                throw new Error("Invalid modelUrl");
            }
            try {
                await faceapi.nets.tinyFaceDetector.loadFromUri(modelUrl);
                await faceapi.nets.faceLandmark68Net.loadFromUri(modelUrl);
                await faceapi.nets.faceRecognitionNet.loadFromUri(modelUrl);
            } catch (error) {
                console.error("FaceValidation: Model load failed", error);
            }
        }

        async function detectFaceFromCanvas(canvas, options = new faceapi.TinyFaceDetectorOptions()) {
            if (!(canvas instanceof HTMLCanvasElement)) {
                throw new Error("Invalid canvas element");
            }
            try {
                const detection = await faceapi
                    .detectSingleFace(canvas, options)
                    .withFaceLandmarks()
                    .withFaceDescriptor();
                return detection;
            } catch (error) {
                console.error("FaceValidation: Face detection failed", error);
                return null;
            }
        }

        function compareFaces(descriptor1, descriptor2) {
            if (!(descriptor1 instanceof Float32Array) || !(descriptor2 instanceof Float32Array)) {
                throw new Error("Invalid descriptor(s)");
            }
            return faceapi.euclideanDistance(descriptor1, descriptor2);
        }

        const api = Object.freeze({
            loadModels: loadModels,
            detectFace: detectFaceFromCanvas,
            compareFaces: compareFaces
        });
        global.faceValidation = api;
    })(window);
    </script>

    <!-- Main Sidebar and Face Validation Logic -->
    <script nonce="<?php echo $scriptNonce; ?>" defer>
    document.addEventListener('DOMContentLoaded', async function() {
        // SIDEBAR TOGGLE
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.getElementById('sidebarCollapse');
        if (sidebar && toggleBtn) {
            function updateTogglePosition() {
                if (sidebar.classList.contains('collapsed')) {
                    toggleBtn.style.left = '0';
                    toggleBtn.innerHTML = '&gt;';
                } else {
                    toggleBtn.style.left = '250px';
                    toggleBtn.innerHTML = '&lt;';
                }
            }
            localStorage.setItem('sidebarState', 'expanded');
            sidebar.classList.remove('collapsed');
            updateTogglePosition();

            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                updateTogglePosition();
                localStorage.setItem('sidebarState', sidebar.classList.contains('collapsed') ?
                    'collapsed' : 'expanded');
            });
        }

        // FACE VALIDATION
        const virtualIdLink = document.getElementById('virtualIdLink');
        const faceValidationModalEl = document.getElementById('faceValidationModal');
        const storedFacePreview = document.getElementById('storedFacePreview');
        const videoInput = document.getElementById('videoInput');
        const validateFaceBtn = document.getElementById('validateFaceBtn');
        const faceValidationResult = document.getElementById('faceValidationResult');
        const userFaceCanvas = document.getElementById('userFaceCanvas');

        let referenceDescriptor = null;
        const userFaceImageUrl = "<?php echo $userFaceImageUrl; ?>";

        await faceValidation.loadModels('backend/models/weights');

        async function loadReferenceDescriptor() {
            if (!userFaceImageUrl) {
                console.warn("No stored face image URL for this user.");
                return;
            }
            const decryptUrl =
                `backend/routes/decrypt_image.php?face_url=${encodeURIComponent(userFaceImageUrl)}`;
            const img = new Image();
            img.crossOrigin = "anonymous";
            img.src = decryptUrl;
            storedFacePreview.src = decryptUrl;

            await new Promise((resolve, reject) => {
                img.onload = resolve;
                img.onerror = reject;
            });

            try {
                const detection = await faceapi
                    .detectSingleFace(img, new faceapi.TinyFaceDetectorOptions({
                        inputSize: 416,
                        scoreThreshold: 0.5
                    }))
                    .withFaceLandmarks()
                    .withFaceDescriptor();
                if (!detection) {
                    console.error('No face detected in the reference image.');
                    return;
                }
                referenceDescriptor = detection.descriptor;
                console.log("Reference descriptor loaded.");
            } catch (error) {
                console.error("Error detecting face in reference image:", error);
            }
        }

        if (userFaceImageUrl) {
            await loadReferenceDescriptor();
        }

        if (virtualIdLink && faceValidationModalEl) {
            virtualIdLink.addEventListener('click', async function(e) {
                e.preventDefault();
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({
                        video: {}
                    });
                    videoInput.srcObject = stream;
                } catch (error) {
                    console.error("Webcam access error:", error);
                    alert(
                        'Unable to access webcam for face validation. Please check permissions.');
                    return;
                }
                const faceValidationModal = new bootstrap.Modal(faceValidationModalEl);
                faceValidationModal.show();
            });
        }

        if (validateFaceBtn) {
            validateFaceBtn.addEventListener('click', async function() {
                faceValidationResult.innerText = '';
                userFaceCanvas.width = videoInput.videoWidth;
                userFaceCanvas.height = videoInput.videoHeight;
                const ctx = userFaceCanvas.getContext('2d');
                ctx.drawImage(videoInput, 0, 0, userFaceCanvas.width, userFaceCanvas.height);

                const detection = await faceapi
                    .detectSingleFace(userFaceCanvas, new faceapi.TinyFaceDetectorOptions({
                        inputSize: 416,
                        scoreThreshold: 0.5
                    }))
                    .withFaceLandmarks()
                    .withFaceDescriptor();
                if (!detection) {
                    faceValidationResult.innerText = 'No face detected. Please try again.';
                    return;
                }
                if (!referenceDescriptor) {
                    faceValidationResult.innerText =
                        'No stored reference face found. Please contact support.';
                    return;
                }
                const distance = faceapi.euclideanDistance(detection.descriptor,
                    referenceDescriptor);
                console.log('Distance:', distance);

                const threshold = 0.6;
                if (distance < threshold) {
                    faceValidationResult.innerText = 'Face matched successfully!';
                    const stream = videoInput.srcObject;
                    if (stream) {
                        stream.getTracks().forEach(track => track.stop());
                    }
                    const pdfPassword = Math.random().toString(36).slice(-8);
                    const userId = virtualIdLink.getAttribute('data-user-id');
                    // Optionally redirect or show a success modal
                    // window.location.href = `backend/models/generate_virtual_id.php?user_id=${userId}&pdf_password=${pdfPassword}`;
                } else {
                    faceValidationResult.innerText = 'Face did not match. Please try again.';
                }
            });
        }
    });
    </script>
</body>

</html>