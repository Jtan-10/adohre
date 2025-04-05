<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Generate a unique nonce for inline scripts
$scriptNonce = bin2hex(random_bytes(16));

$current_page = basename($_SERVER['PHP_SELF']);
$submenuActive = ($current_page == 'chat_assistance.php' || $current_page == 'appointments.php' || $current_page == 'medical_assistance.php');
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
    <div id="sidebar">
        <ul class="components">
            <li <?php if ($current_page == 'index.php') echo 'class="active"'; ?>><a href="index.php">Home</a></li>
            <li <?php if ($current_page == 'about.php') echo 'class="active"'; ?>><a href="about.php">About Us</a></li>
            <li <?php if ($current_page == 'news.php') echo 'class="active"'; ?>><a href="news.php">News</a></li>
            <li <?php if ($current_page == 'membership_form.php') echo 'class="active"'; ?>><a
                    href="membership_form.php">Member Application</a></li>
            <?php if (isset($_SESSION['user_id'])): ?>
            <li>
                <a href="#" id="virtualIdLink" data-user-id="<?php echo $_SESSION['user_id']; ?>">Virtual ID</a>
            </li>
            <?php endif; ?>
            <li <?php if ($current_page == 'health.php') echo 'class="active"'; ?>>
                <a data-bs-toggle="offcanvas" href="#offcanvasHealth" role="button"
                    aria-controls="offcanvasHealth">Health Tips</a>
            </li>
            <?php if (isset($_SESSION['user_id']) && (isset($_SESSION['role']) && $_SESSION['role'] !== 'user')): ?>
            <li <?php if ($current_page == 'member_services.php' || $submenuActive) echo 'class="active"'; ?>>
                <!-- Toggle the submenu using javascript -->
                <a href="javascript:void(0)" id="toggleMemberServices">
                    Member Services <span
                        id="memberServicesArrow"><?php echo $submenuActive ? '&uarr;' : '&darr;'; ?></span>
                </a>
                <ul class="submenu" id="memberServicesSubmenu"
                    <?php if ($submenuActive) echo 'style="display: block;"'; ?>>
                    <li <?php if ($current_page == 'consultation.php') echo 'class="active"'; ?>><a
                            href="consultation.php">Chat Assistance</a></li>
                    <li <?php if ($current_page == 'appointments.php') echo 'class="active"'; ?>><a
                            href="appointments.php">Appointments</a></li>
                    <li <?php if ($current_page == 'medical_assistance.php') echo 'class="active"'; ?>><a
                            href="medical_assistance.php">Medical Assistance</a></li>
                </ul>
            </li>
            <li <?php if ($current_page == 'events.php') echo 'class="active"'; ?>><a href="events.php">Events</a></li>
            <?php endif; ?>
            <?php if (isset($_SESSION['user_id'])): ?>
            <li <?php if ($current_page == 'trainings.php') echo 'class="active"'; ?>><a
                    href="trainings.php">Trainings</a></li>
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

    <!-- PDF Password Modal -->
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
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="faceValidationModalLabel">Face Validation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Full-width video for face capture -->
                    <video id="videoInput" width="100%" autoplay muted style="border:1px solid #ccc;"></video>
                    <canvas id="userFaceCanvas" style="display:none;"></canvas>
                    <p id="faceValidationResult"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" id="validateFaceBtn" class="btn btn-primary">Validate Face</button>
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

    <!-- Include Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Load face-api.js from an allowed source -->
    <script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>

    <!-- Inline Face Validation Module (faceValidation.js) -->
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
    document.addEventListener('DOMContentLoaded', function() {
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

        const memberToggle = document.getElementById('toggleMemberServices');
        const memberSubmenu = document.getElementById('memberServicesSubmenu');
        const memberArrow = document.getElementById('memberServicesArrow');

        if (memberToggle && memberSubmenu && memberArrow) {
            memberToggle.addEventListener('click', function() {
                if (memberSubmenu.style.display === 'block') {
                    memberSubmenu.style.display = 'none';
                    memberArrow.innerHTML = '&darr;';
                } else {
                    memberSubmenu.style.display = 'block';
                    memberArrow.innerHTML = '&uarr;';
                }
            });
        }

        const virtualIdLink = document.getElementById('virtualIdLink');
        if (virtualIdLink) {
            virtualIdLink.addEventListener('click', async function(e) {
                e.preventDefault();
                const faceValidationModalEl = document.getElementById('faceValidationModal');
                if (!faceValidationModalEl) {
                    console.error("Face validation modal element not found!");
                    return;
                }
                const videoInput = document.getElementById('videoInput');
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({
                        video: {}
                    });
                    videoInput.srcObject = stream;
                } catch (error) {
                    console.error("Webcam access error:", error);
                    alert('Unable to access webcam for face validation. Please check permissions.');
                }
                const faceValidationModal = new bootstrap.Modal(faceValidationModalEl);
                faceValidationModal.show();
            });
        }

        const validateFaceBtn = document.getElementById('validateFaceBtn');
        if (validateFaceBtn) {
            validateFaceBtn.addEventListener('click', async function() {
                const video = document.getElementById('videoInput');
                const canvas = document.getElementById('userFaceCanvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                // Use the faceValidation module to detect the face
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
                if (typeof referenceDescriptor === 'undefined') {
                    resultParagraph.innerText =
                        'Reference face not available. Please contact support.';
                    return;
                }
                const distance = faceapi.euclideanDistance(detection.descriptor,
                    referenceDescriptor);
                console.log('Distance:', distance);
                const threshold = 0.6;
                if (distance < threshold) {
                    resultParagraph.innerText = 'Face matched successfully!';
                    const stream = video.srcObject;
                    if (stream) {
                        stream.getTracks().forEach(track => track.stop());
                    }
                    const pdfPassword = Math.random().toString(36).slice(-8);
                    const userId = virtualIdLink.getAttribute('data-user-id');
                    window.location.href =
                        `backend/models/generate_virtual_id.php?user_id=${userId}&pdf_password=${pdfPassword}`;
                } else {
                    resultParagraph.innerText = 'Face did not match. Please try again.';
                }
            });
        }
    });
    </script>
</body>

</html>