<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Generate a unique nonce for inline scripts
$scriptNonce = bin2hex(random_bytes(16));

$current_page = basename($_SERVER['PHP_SELF']);
$submenuActive = ($current_page == 'chat_assistance.php' || $current_page == 'appointments.php' || $current_page == 'medical_assistance.php');

// Optional: If you already know the logged-in user’s face_image URL, you can store it in a variable.
// For example, if you store the face image in $_SESSION['face_image']:
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
        /* Center navigation vertically and horizontally */
        display: flex;
        justify-content: center;
        align-items: center;
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
        display: none;
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
                <a data-bs-toggle="collapse" href="#memberServicesSubmenu" role="button"
                    aria-expanded="<?php echo $submenuActive ? 'true' : 'false'; ?>"
                    aria-controls="memberServicesSubmenu" id="toggleMemberServices">
                    Member Services <span
                        id="memberServicesArrow"><?php echo $submenuActive ? '&uarr;' : '&darr;'; ?></span>
                </a>
                <ul class="submenu collapse <?php echo $submenuActive ? 'show' : ''; ?>" id="memberServicesSubmenu">
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

    <!-- Inline Face Validation Module (like faceValidation.js) -->
    <script nonce="<?php echo $scriptNonce; ?>" defer>
    (function(global) {
        "use strict";
        /**
         * Loads the face-api.js models from the specified URL.
         * @param {string} modelUrl - The URL or path to the models folder.
         * @returns {Promise} Resolves when all models are loaded.
         */
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

        /**
         * Detects a single face in a given canvas element.
         * @param {HTMLCanvasElement} canvas - The canvas containing the face image.
         * @param {object} [options] - Optional detection options.
         * @returns {Promise<object|null>} Returns detection result with landmarks and descriptor or null if no face is found.
         */
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

        /**
         * Compares two face descriptors using Euclidean distance.
         * @param {Float32Array} descriptor1 - The first face descriptor.
         * @param {Float32Array} descriptor2 - The second face descriptor.
         * @returns {number} The Euclidean distance between the descriptors.
         */
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
            // Always open sidebar by default
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

        // SUBMENU ARROWS
        const memberSubmenu = document.getElementById('memberServicesSubmenu');
        const memberArrow = document.getElementById('memberServicesArrow');
        if (memberSubmenu && memberArrow) {
            memberSubmenu.addEventListener('shown.bs.collapse', function() {
                memberArrow.innerHTML = '&uarr;';
            });
            memberSubmenu.addEventListener('hidden.bs.collapse', function() {
                memberArrow.innerHTML = '&darr;';
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

        // This will store the reference descriptor from the user's stored face
        let referenceDescriptor = null;

        // OPTIONAL: If you already have the user’s face image URL in $userFaceImageUrl from PHP:
        // If you store it in a data attribute, you can do this in the HTML:
        // <div id="faceValidationModal" data-face-url="<?php echo $userFaceImageUrl; ?>"></div>
        // For now, let's read it directly from a JS variable:
        const userFaceImageUrl = "<?php echo $userFaceImageUrl; ?>";

        // 1) Preload face-api models
        await faceValidation.loadModels('backend/models/weights');

        // 2) If we have a stored face URL, load it and compute descriptor
        async function loadReferenceDescriptor() {
            if (!userFaceImageUrl) {
                console.warn("No stored face image URL for this user.");
                return;
            }
            // Use the decrypt endpoint to retrieve the image
            const decryptUrl =
                `backend/routes/decrypt_image.php?face_url=${encodeURIComponent(userFaceImageUrl)}`;
            const img = new Image();
            img.crossOrigin = "anonymous";
            img.src = decryptUrl;
            // Display the decrypted image in the stored face preview
            storedFacePreview.src = decryptUrl;

            // Wait until the image loads
            await new Promise((resolve, reject) => {
                img.onload = resolve;
                img.onerror = reject;
            });

            // Detect face with descriptor from the decrypted image
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


        // Actually load the stored reference
        if (userFaceImageUrl) {
            await loadReferenceDescriptor();
        }

        // 3) Show face validation modal on "Virtual ID" click
        if (virtualIdLink && faceValidationModalEl) {
            virtualIdLink.addEventListener('click', async function(e) {
                e.preventDefault();
                // Start the webcam
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({
                        video: {}
                    });
                    videoInput.srcObject = stream;
                } catch (error) {
                    console.error("Webcam access error:", error);
                    alert(
                        'Unable to access webcam for face validation. Please check permissions.'
                    );
                    return;
                }
                // Show the modal
                const faceValidationModal = new bootstrap.Modal(faceValidationModalEl);
                faceValidationModal.show();
            });
        }

        // 4) Validate Face button
        if (validateFaceBtn) {
            validateFaceBtn.addEventListener('click', async function() {
                faceValidationResult.innerText = '';
                // Capture the video frame to canvas
                userFaceCanvas.width = videoInput.videoWidth;
                userFaceCanvas.height = videoInput.videoHeight;
                const ctx = userFaceCanvas.getContext('2d');
                ctx.drawImage(videoInput, 0, 0, userFaceCanvas.width, userFaceCanvas.height);

                // Detect face with descriptor
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
                // Compare descriptors
                const distance = faceapi.euclideanDistance(detection.descriptor,
                    referenceDescriptor);
                console.log('Distance:', distance);

                // For typical use, a threshold of ~0.6 is used
                const threshold = 0.6;
                if (distance < threshold) {
                    faceValidationResult.innerText = 'Face matched successfully!';
                    // Stop the webcam stream
                    const stream = videoInput.srcObject;
                    if (stream) {
                        stream.getTracks().forEach(track => track.stop());
                    }
                    // Example: Generate PDF password or do something else
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