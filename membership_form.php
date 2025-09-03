<?php
require_once 'backend/db/db_connect.php';
require_once 'backend/utils/access_control.php';

// Configure session security based on environment
configureSessionSecurity();
session_start();

// Check authentication status (but don't require it for the form)
$isLoggedIn = isset($_SESSION['user_id']);

header("X-Frame-Options: DENY"); // Send header instead of using <meta> tag.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csp_nonce = base64_encode(random_bytes(16));

// Check if face validation is enabled
$faceValidationEnabled = isFaceValidationEnabled();

// Define $userFaceImageUrl from session
$userFaceImageUrl = isset($_SESSION['face_image']) ? $_SESSION['face_image'] : '';

// ***** NEW: Check if user already has a membership application record *****
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT application_id FROM membership_applications WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            // User already submitted a membership application – show message and stop further processing.
            echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Membership Application</title>
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css'>
</head>
<body>
    <div class='container mt-5'>
        <h1 class='text-center'>Membership Application</h1>
        <div class='alert alert-info text-center'>
            You have already submitted your membership application. Thank you.
        </div>
    </div>
</body>
</html>";
            exit;
        }
        $stmt->close();
    }
    $conn->close();
}
// ***** END OF NEW CHECK *****
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membership Form</title>

    <script nonce="<?php echo $csp_nonce; ?>"
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Added: Load face-api.js library only if face validation is enabled -->
    <?php if ($faceValidationEnabled): ?>
        <script nonce="<?php echo $csp_nonce; ?>"
            src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    <?php endif; ?>
    <style>
        body {
            background-color: #f8f9fa;
        }

        .form-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: #fff;
        }

        .form-title {
            font-size: 1.25rem;
            font-weight: bold;
            margin-bottom: 15px;
        }
    </style>
</head>

<body>
    <?php if (isset($_GET['warning']) && $_GET['warning'] == 1): ?>
        <div class="alert alert-warning text-center" role="alert">
            You must complete the membership form in order to activate your membership.
        </div>
    <?php endif; ?>

    <div class="container my-5">
        <h1 class="text-center text-success mb-4">Membership Application Form</h1>

        <form id="membership-form" action="backend/routes/membership_handler.php" method="POST"
            enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <!-- OCR Upload Section -->
            <div class="form-section">
                <div class="form-title">OCR Upload</div>
                <input type="file" id="membership-upload" accept="image/*" class="form-control mb-3 d-none">
                <textarea id="ocr-output" class="form-control" rows="10"
                    placeholder="Extracted text will appear here..." readonly></textarea>
                <div class="progress mb-3">
                    <div id="ocr-progress" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuemin="0"
                        aria-valuemax="100"></div>
                </div>
            </div>

            <!-- Section 1: Personal Information -->
            <?php include('section1_membership_form.php'); ?>

            <!-- Section 2: Employment Record -->
            <?php include('section2_membership_form.php'); ?>

            <!-- Section 3: Educational Background -->
            <?php include('section3_membership_form.php'); ?>

            <!-- Section 4: Current Engagement -->
            <?php include('section4_membership_form.php'); ?>

            <!-- Section 5: Key Expertise -->
            <?php include('section5_membership_form.php'); ?>

            <!-- Section 6: Other Skills -->
            <?php include('section6_membership_form.php'); ?>

            <!-- Section 7: Committees -->
            <?php include('section7_membership_form.php'); ?>

            <!-- New Section: Valid ID Upload -->
            <div class="form-section">
                <div class="form-title">Valid ID Upload</div>
                <div class="mb-3">
                    <label for="valid_id" class="form-label">Upload a Valid ID</label>
                    <input type="file" id="valid_id" name="valid_id" accept="image/*" class="form-control" required>
                    <div class="form-text">Please upload a clear image of your valid ID.</div>
                </div>
            </div>

            <!-- Section: Signature and Date -->
            <div class="form-section">
                <div class="form-title">Signature</div>

                <!-- Digital Signature Pad -->
                <div class="mb-3">
                    <label class="form-label">Signature of Prospective Member</label>
                    <div id="signature-pad"
                        style="border: 1px solid #ccc; border-radius: 5px; width: 100%; height: 200px; position: relative;">
                        <canvas id="signature-canvas" style="width: 100%; height: 100%;"></canvas>
                    </div>
                    <div class="mt-2">
                        <button type="button" id="clear-signature" class="btn btn-warning btn-sm">Clear</button>
                        <input type="hidden" id="signature" name="signature">
                    </div>
                </div>

            </div>

            <div class="d-flex justify-content-center gap-2">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </a>
                <button type="submit" id="submit-btn" class="btn btn-success">
                    Submit Application
                </button>
            </div>

        </form>
        <!-- Modal Structure for Input Method -->
        <div class="modal fade" id="inputModal" tabindex="-1" aria-labelledby="inputModalLabel">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="inputModalLabel">Choose Input Method</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Please choose how you want to fill the membership form:</p>
                        <button id="ocr-button" type="button" class="btn btn-success w-100 mb-2">Upload Image for
                            OCR</button>
                        <button id="manual-button" type="button" class="btn btn-secondary w-100"
                            data-bs-dismiss="modal">
                            Manual Input
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- New Face Validation Modal (only shown if face validation is enabled) -->
        <?php if ($faceValidationEnabled): ?>
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
                                <!-- Assuming update_user_details.php or similar has already stored the user's face image URL in a session variable -->
                                <img id="storedFacePreview"
                                    src="<?php echo isset($_SESSION['face_image']) ? 'backend/routes/decrypt_image.php?face_url=' . urlencode($_SESSION['face_image']) : ''; ?>"
                                    alt="Stored Face Reference"
                                    style="width:100%; max-width:320px; border:1px solid #ccc; display:block;">
                            </div>
                            <!-- Live face capture -->
                            <div class="mb-3">
                                <h5>Capture Your Face</h5>
                                <video id="videoInput" width="320" height="240" autoplay muted
                                    style="border:1px solid #ccc;"></video>
                            </div>
                            <!-- Validate Face button and hidden canvas -->
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
        <?php endif; ?>

    </div>
    <script>
        // Apply admin-configured membership form schema (labels/placeholders/required/options) if available
        (async function applyMembershipSchema() {
            try {
                const res = await fetch('backend/routes/settings_api.php?action=get_membership_form_schema', {
                    credentials: 'same-origin'
                });
                const j = await res.json();
                if (!j.status || !j.schema) return;
                const s = j.schema;

                // Helper to set label text for input/select by for/id
                function setLabel(id, text) {
                    const lbl = document.querySelector(`label[for="${id}"]`);
                    if (lbl && text) {
                        lbl.textContent = text;
                    }
                }

                function setRequired(id, required) {
                    const el = document.getElementById(id);
                    if (el) {
                        if (required) {
                            el.setAttribute('required', 'required');
                        } else {
                            el.removeAttribute('required');
                        }
                    }
                }

                function setPlaceholder(id, ph) {
                    const el = document.getElementById(id);
                    if (el && ph != null) {
                        el.setAttribute('placeholder', ph);
                    }
                }

                function setSelectOptions(id, options) {
                    const el = document.getElementById(id);
                    if (el && el.tagName === 'SELECT' && Array.isArray(options)) {
                        el.innerHTML = '';
                        options.forEach(v => {
                            const o = document.createElement('option');
                            o.value = v;
                            o.textContent = v;
                            el.appendChild(o);
                        });
                    }
                }
                // Section 1
                if (s.section1 && s.section1.fields) {
                    for (const [k, cfg] of Object.entries(s.section1.fields)) {
                        if (cfg.label) setLabel(k, cfg.label);
                        if ('required' in cfg) setRequired(k, !!cfg.required);
                        if (cfg.placeholder) setPlaceholder(k, cfg.placeholder);
                        if (cfg.type === 'select' && cfg.options) setSelectOptions(k, cfg.options);
                        if (cfg.maxlength) {
                            const el = document.getElementById(k);
                            if (el) el.maxLength = parseInt(cfg.maxlength, 10) || el.maxLength;
                        }
                        if (cfg.min && document.getElementById(k)) document.getElementById(k).min = cfg.min;
                        if (cfg.max && document.getElementById(k)) document.getElementById(k).max = cfg.max;
                    }
                }
                // Section 2
                if (s.section2 && s.section2.fields) {
                    for (const [k, cfg] of Object.entries(s.section2.fields)) {
                        if (cfg.label) setLabel(k, cfg.label);
                        if ('required' in cfg) setRequired(k, !!cfg.required);
                    }
                }
                // Section 3
                if (s.section3 && s.section3.fields) {
                    for (const [k, cfg] of Object.entries(s.section3.fields)) {
                        if (cfg.label) setLabel(k, cfg.label);
                        if ('required' in cfg) setRequired(k, !!cfg.required);
                        if (cfg.min && document.getElementById(k)) document.getElementById(k).min = cfg.min;
                        if (cfg.max && document.getElementById(k)) document.getElementById(k).max = cfg.max;
                    }
                }
                // Section 4 radio options (current_engagement)
                if (s.section4 && s.section4.group) {
                    const grp = s.section4.group;
                    const container = document.querySelector('div.form-section .form-title ~ .form-check input[name="current_engagement"]')?.closest('.form-section');
                    // Safer: find section4 by title text
                    const sec4 = Array.from(document.querySelectorAll('.form-section')).find(d => d.querySelector('.form-title')?.textContent?.trim().startsWith('4.'));
                    if (sec4) {
                        // Rebuild group
                        const area = sec4;
                        area.querySelectorAll('.form-check').forEach(e => e.remove());
                        if (Array.isArray(grp.options)) {
                            grp.options.forEach((opt, i) => {
                                const id = (opt.toLowerCase().replace(/[^a-z0-9]+/g, '_')) || ('opt_' + i);
                                const wrap = document.createElement('div');
                                wrap.className = 'form-check';
                                wrap.innerHTML = `<input class="form-check-input" type="radio" name="current_engagement" id="${id}" value="${opt}">` +
                                    `<label class="form-check-label" for="${id}">${opt}</label>`;
                                area.appendChild(wrap);
                            });
                        }
                        if (grp.includeOthers) {
                            const wrap = document.createElement('div');
                            wrap.className = 'form-check';
                            wrap.innerHTML = `<input class="form-check-input" type="radio" name="current_engagement" id="others_current_engagement" value="Others">` +
                                `<label class="form-check-label" for="others_current_engagement">${grp.othersLabel||'Others (Specify):'}</label>` +
                                `<input type="text" id="others_engagement_specify" name="others_engagement_specify" class="form-control mt-2" placeholder="Specify here..." disabled>`;
                            area.appendChild(wrap);
                        }
                    }
                }
                // Section 5 groups (key_expertise & specific_field)
                if (s.section5 && Array.isArray(s.section5.groups)) {
                    const sec5 = Array.from(document.querySelectorAll('.form-section')).find(d => d.querySelector('.form-title')?.textContent?.trim().startsWith('5.'));
                    if (sec5) {
                        // Remove old groups and rebuild
                        sec5.querySelectorAll('.form-check').forEach(e => e.remove());
                        s.section5.groups.forEach(g => {
                            if (Array.isArray(g.options)) {
                                const labelEl = document.createElement('div');
                                if (g.label) {
                                    const lbl = document.createElement('label');
                                    lbl.textContent = g.label;
                                    lbl.className = 'mt-4 d-block';
                                    labelEl.appendChild(lbl);
                                }
                                sec5.appendChild(labelEl);
                                g.options.forEach((opt, i) => {
                                    const id = (g.name + '_' + i);
                                    const wrap = document.createElement('div');
                                    wrap.className = 'form-check';
                                    wrap.innerHTML = `<input type="radio" id="${id}" name="${g.name}" value="${opt}" class="form-check-input">` +
                                        `<label for="${id}" class="form-check-label">${opt}</label>`;
                                    sec5.appendChild(wrap);
                                });
                                if (g.includeOthers) {
                                    const wrap = document.createElement('div');
                                    wrap.className = 'form-check';
                                    const specId = g.name === 'key_expertise' ? 'others_expertise_specify' : 'others_specific_field_specify';
                                    const othersId = g.name === 'key_expertise' ? 'others_key_expertise' : 'others_specific_field';
                                    wrap.innerHTML = `<input type="radio" id="${othersId}" name="${g.name}" value="Others" class="form-check-input">` +
                                        `<label for="${othersId}" class="form-check-label">${g.othersLabel||'Others (Specify):'}</label>` +
                                        `<input type="text" id="${specId}" name="${specId}" class="form-control mt-2" placeholder="Specify here..." disabled>`;
                                    sec5.appendChild(wrap);
                                }
                            }
                        });
                    }
                }
                // Section 6 simple fields
                if (s.section6 && s.section6.fields) {
                    for (const [k, cfg] of Object.entries(s.section6.fields)) {
                        if (cfg.label) setLabel(k, cfg.label);
                        if (cfg.placeholder) setPlaceholder(k, cfg.placeholder);
                        if ('required' in cfg) setRequired(k, !!cfg.required);
                    }
                }
                // Section 7 committees
                if (s.section7 && s.section7.group) {
                    const sec7 = Array.from(document.querySelectorAll('.form-section')).find(d => d.querySelector('.form-title')?.textContent?.trim().startsWith('7.'));
                    if (sec7) {
                        sec7.querySelectorAll('.form-check').forEach(e => e.remove());
                        const grp = s.section7.group;
                        if (Array.isArray(grp.options)) {
                            grp.options.forEach((opt, i) => {
                                const id = 'committee_' + i;
                                const wrap = document.createElement('div');
                                wrap.className = 'form-check';
                                wrap.innerHTML = `<input type="radio" id="${id}" name="${grp.name}" value="${opt}" class="form-check-input">` +
                                    `<label for="${id}" class="form-check-label">${opt}</label>`;
                                sec7.appendChild(wrap);
                            });
                        }
                        if (grp.includeOthers) {
                            const wrap = document.createElement('div');
                            wrap.className = 'form-check';
                            wrap.innerHTML = `<input type="radio" id="others_committees" name="${grp.name}" value="Others" class="form-check-input">` +
                                `<label for="others_committees" class="form-check-label">${grp.othersLabel||'Others (Specify):'}</label>` +
                                `<input type="text" id="others_committee_specify" name="others_committee_specify" class="form-control mt-2" placeholder="Specify here..." disabled>`;
                            sec7.appendChild(wrap);
                        }
                    }
                }
            } catch (e) {
                /* ignore */ }
        })();
    </script>
    <script nonce="<?php echo $csp_nonce; ?>"
        src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <!-- Load OCR Script externally -->
    <script nonce="<?php echo $csp_nonce; ?>" src="OCR_membership_form.php"></script>
    <script nonce="<?php echo $csp_nonce; ?>">
        const canvas = document.getElementById("signature-canvas");
        const signaturePad = new SignaturePad(canvas);

        function resizeCanvas() {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext("2d").scale(ratio, ratio);
            signaturePad.clear();
        }
        window.addEventListener("resize", resizeCanvas);
        resizeCanvas();

        document.getElementById("clear-signature").addEventListener("click", function() {
            signaturePad.clear();
            document.getElementById("signature").value = "";
        });
    </script>
    <script nonce="<?php echo $csp_nonce; ?>">
        // Enable/Disable "Others (Specify)" Textboxes Based on Selection
        document.addEventListener("DOMContentLoaded", function() {
            /**
             * Function to toggle the "Specify" input box based on the "Others" radio button selection.
             * @param {string} radioName - The name attribute of the radio group.
             * @param {string} inputId - The ID of the text input box to enable/disable.
             */
            function toggleSpecifyInput(radioName, inputId) {
                const radios = document.querySelectorAll(`input[name="${radioName}"]`);
                const input = document.getElementById(inputId);

                radios.forEach(radio => {
                    radio.addEventListener("change", function() {
                        const isOthersSelected = document.getElementById(`others_${radioName}`)
                            ?.checked;
                        if (isOthersSelected) {
                            input.disabled = false; // Enable if "Others" is selected
                        } else {
                            input.disabled = true; // Disable otherwise
                            input.value = ""; // Clear when disabled
                        }
                    });
                });
            }

            // Apply toggle logic for each section
            toggleSpecifyInput("current_engagement", "others_engagement_specify");
            toggleSpecifyInput("key_expertise", "others_expertise_specify");
            toggleSpecifyInput("specific_field", "others_specific_field_specify");
            toggleSpecifyInput("committees", "others_committee_specify");

            // Inside the DOMContentLoaded event handler, after obtaining videoInput and faceValidationModalEl
            const faceValidationModalEl = document.getElementById('faceValidationModal');
            faceValidationModalEl.addEventListener('shown.bs.modal', async () => {
                if (!videoInput.srcObject) {
                    try {
                        const stream = await navigator.mediaDevices.getUserMedia({
                            video: true
                        });
                        videoInput.srcObject = stream;
                    } catch (error) {
                        console.error("Webcam access error:", error);
                        alert('Unable to access webcam. Please allow camera permissions or use HTTPS.');
                    }
                }
            });
        });
    </script>
    <script nonce="<?php echo $csp_nonce; ?>">
        // Face Validation and Form Submission logic
        // Global variable to track whether face validation has been completed
        const faceValidationEnabled = <?php echo json_encode($faceValidationEnabled); ?>;
        let faceValidated = !faceValidationEnabled; // Skip validation if disabled
        const membershipForm = document.getElementById('membership-form');
        const validateFaceBtn = document.getElementById('validateFaceBtn');
        const videoInput = document.getElementById('videoInput');
        const userFaceCanvas = document.getElementById('userFaceCanvas');
        const faceValidationResult = document.getElementById('faceValidationResult');
        let referenceDescriptor = null; // Will hold the descriptor from the stored face image

        // Declare userFaceImageUrl before calling loadReferenceDescriptor
        const userFaceImageUrl = "<?php echo $userFaceImageUrl; ?>";

        // Load face-api.js models
        async function loadFaceModels() {
            if (!faceValidationEnabled) return;
            if (typeof faceapi === 'undefined') return;
            await faceapi.nets.tinyFaceDetector.loadFromUri('backend/models/weights');
            await faceapi.nets.faceLandmark68Net.loadFromUri('backend/models/weights');
            await faceapi.nets.faceRecognitionNet.loadFromUri('backend/models/weights');
        }

        // Modified loadReferenceDescriptor function
        async function loadReferenceDescriptor() {
            if (!faceValidationEnabled) return;
            if (!userFaceImageUrl) {
                console.warn("No stored face image URL for this user.");
                return;
            }
            const decryptUrl = `backend/routes/decrypt_image.php?face_url=${encodeURIComponent(userFaceImageUrl)}`;
            // Use the storedFacePreview element and wait for it to be fully loaded with valid dimensions
            storedFacePreview.src = decryptUrl;
            await new Promise((resolve, reject) => {
                if (storedFacePreview.complete && storedFacePreview.naturalWidth !== 0) {
                    resolve();
                } else {
                    storedFacePreview.onload = resolve;
                    storedFacePreview.onerror = reject;
                }
            });
            try {
                const detection = await faceapi
                    .detectSingleFace(storedFacePreview, new faceapi.TinyFaceDetectorOptions({
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

        // Ensure models are loaded before detection
        let modelsPromise = loadFaceModels();
        modelsPromise.then(loadReferenceDescriptor);

        // Updated Handler for face validation
        validateFaceBtn.addEventListener('click', async function() {
            faceValidationResult.innerText = '';
            // If video metadata is not loaded yet, wait for it
            if (videoInput.videoWidth === 0) {
                await new Promise(resolve => videoInput.addEventListener('loadedmetadata', resolve, {
                    once: true
                }));
            }
            userFaceCanvas.width = videoInput.videoWidth;
            userFaceCanvas.height = videoInput.videoHeight;
            const ctx = userFaceCanvas.getContext('2d');
            ctx.drawImage(videoInput, 0, 0, userFaceCanvas.width, userFaceCanvas.height);

            // Ensure models are loaded before detection
            await modelsPromise;

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
                faceValidationResult.innerText = 'No stored reference available.';
                return;
            }
            const distance = faceapi.euclideanDistance(detection.descriptor, referenceDescriptor);
            console.log('Face distance:', distance);
            const threshold = 0.6;
            if (distance < threshold) {
                faceValidationResult.innerText = 'Face matched successfully!';
                // Stop the webcam stream
                if (videoInput.srcObject) {
                    videoInput.srcObject.getTracks().forEach(track => track.stop());
                    videoInput.srcObject = null;
                }
                // Automatically close the Face Validation modal
                const faceModal = bootstrap.Modal.getInstance(document.getElementById('faceValidationModal'));
                if (faceModal) faceModal.hide();
                faceValidated = true;
                // Instead of membershipForm.submit(), dispatch a submit event so the fetch handler runs
                membershipForm.dispatchEvent(new Event('submit', {
                    bubbles: true,
                    cancelable: true
                }));
            } else {
                faceValidationResult.innerText = 'Face did not match. Please try again.';
            }
        });

        // Intercept form submission to ensure face validation is done
        document.querySelector('#membership-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            // Check if signature is provided
            if (signaturePad.isEmpty()) {
                alert("Please provide your signature.");
                return;
            }
            // If face validation hasn't been completed, show the modal and do not proceed
            if (!faceValidated) {
                const faceValidationModal = new bootstrap.Modal(document.getElementById('faceValidationModal'));
                faceValidationModal.show();
                return;
            }
            const submitButton = document.querySelector('#submit-btn');
            submitButton.disabled = true;
            submitButton.textContent = 'Submitting...';

            // Save signature data
            const signatureData = signaturePad.toDataURL();
            document.getElementById('signature').value = signatureData;
            signaturePad.clear();

            const formData = new FormData(this);
            try {
                const response = await fetch(this.action, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.status) {
                    alert(result.message);
                    this.reset();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('An unexpected error occurred. Please try again.');
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = 'Submit Application';
            }
        });
    </script>
    <script nonce="<?php echo $csp_nonce; ?>">
        // Enable/Disable "Others (Specify)" Textboxes Based on Selection (if any)
        document.addEventListener("DOMContentLoaded", function() {
            function toggleSpecifyInput(radioName, inputId) {
                const radios = document.querySelectorAll(`input[name="${radioName}"]`);
                const input = document.getElementById(inputId);

                radios.forEach(radio => {
                    radio.addEventListener("change", function() {
                        const isOthersSelected = document.getElementById(`others_${radioName}`)
                            ?.checked;
                        if (isOthersSelected) {
                            input.disabled = false;
                        } else {
                            input.disabled = true;
                            input.value = "";
                        }
                    });
                });
            }
            toggleSpecifyInput("current_engagement", "others_engagement_specify");
            toggleSpecifyInput("key_expertise", "others_expertise_specify");
            toggleSpecifyInput("specific_field", "others_specific_field_specify");
            toggleSpecifyInput("committees", "others_committee_specify");
        });
    </script>
</body>

</html>