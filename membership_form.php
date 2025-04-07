<?php
session_start();
header("X-Frame-Options: DENY"); // Send header instead of using <meta> tag.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csp_nonce = base64_encode(random_bytes(16));

// ***** NEW: Check if user already has a membership application record *****
if (isset($_SESSION['user_id'])) {
    require_once 'backend/db/db_connect.php';
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

        <!-- New Face Validation Modal -->
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

    </div>
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
    });
    </script>
    <script nonce="<?php echo $csp_nonce; ?>">
    // Face Validation and Form Submission logic
    // Global variable to track whether face validation has been completed
    let faceValidated = false;
    const membershipForm = document.getElementById('membership-form');
    const validateFaceBtn = document.getElementById('validateFaceBtn');
    const videoInput = document.getElementById('videoInput');
    const userFaceCanvas = document.getElementById('userFaceCanvas');
    const faceValidationResult = document.getElementById('faceValidationResult');
    let referenceDescriptor = null; // Will hold the descriptor from the stored face image

    // Load face-api.js models
    async function loadFaceModels() {
        await faceapi.nets.tinyFaceDetector.loadFromUri('backend/models/weights');
        await faceapi.nets.faceLandmark68Net.loadFromUri('backend/models/weights');
        await faceapi.nets.faceRecognitionNet.loadFromUri('backend/models/weights');
    }
    loadFaceModels();

    // Load the stored face descriptor from the stored face image URL (from session)
    async function loadReferenceDescriptor() {
        const storedFaceImg = document.getElementById('storedFacePreview');
        if (!storedFaceImg.src) {
            console.warn("No stored face reference.");
            return;
        }
        await new Promise((resolve, reject) => {
            storedFaceImg.onload = resolve;
            storedFaceImg.onerror = reject;
        });
        const detection = await faceapi
            .detectSingleFace(storedFaceImg, new faceapi.TinyFaceDetectorOptions({
                inputSize: 416,
                scoreThreshold: 0.5
            }))
            .withFaceLandmarks()
            .withFaceDescriptor();
        if (detection) {
            referenceDescriptor = detection.descriptor;
            console.log("Reference descriptor loaded in membership form.");
        } else {
            console.error("No face detected in stored reference image.");
        }
    }
    loadReferenceDescriptor();

    // Handler for face validation
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
            // Hide the Face Validation modal
            const faceModal = bootstrap.Modal.getInstance(document.getElementById('faceValidationModal'));
            if (faceModal) faceModal.hide();
            faceValidated = true;
            // Programmatically submit the form now that face is validated
            membershipForm.submit();
        } else {
            faceValidationResult.innerText = 'Face did not match. Please try again.';
        }
    });

    // Intercept form submission to ensure face validation is done
    membershipForm.addEventListener('submit', function(e) {
        // Check if signature is provided
        if (signaturePad.isEmpty()) {
            alert("Please provide your signature.");
            e.preventDefault();
            return;
        }
        // If face validation hasn't yet been completed, prevent final submission and show face validation modal
        if (!faceValidated) {
            e.preventDefault();
            const faceValidationModal = new bootstrap.Modal(document.getElementById('faceValidationModal'));
            faceValidationModal.show();
            return;
        }
        // Otherwise, the form will submit as usual.
    });
    </script>
    <script nonce="<?php echo $csp_nonce; ?>">
    // Form submission for membership application
    document.querySelector('#membership-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        const submitButton = document.querySelector('#submit-btn');
        submitButton.disabled = true;
        submitButton.textContent = 'Submitting...';

        // Save signature if not empty
        if (!signaturePad.isEmpty()) {
            const signatureData = signaturePad.toDataURL();
            document.getElementById('signature').value = signatureData;
            signaturePad.clear();
        } else {
            alert("Please provide your signature.");
            e.preventDefault();
            submitButton.disabled = false;
            submitButton.textContent = 'Submit Application';
            return;
        }

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