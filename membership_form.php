<?php
session_start();
header("X-Frame-Options: DENY"); // Send header instead of using <meta> tag.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csp_nonce = base64_encode(random_bytes(16));
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

        <form id="membership-form" action="backend/routes/membership_handler.php" method="POST">
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

            <div class="text-center">
                <button type="submit" id="submit-btn" class="btn btn-success">Submit Application</button>
            </div>
        </form>
        <!-- Modal Structure -->
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

    document.getElementById("membership-form").addEventListener("submit", function(e) {
        if (!signaturePad.isEmpty()) {
            const signatureData = signaturePad.toDataURL();
            document.getElementById("signature").value = signatureData;
            signaturePad.clear(); // Added line: Clear the signature pad after capturing
        } else {
            alert("Please provide your signature.");
            e.preventDefault();
        }
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
                        input.disabled = false; // Enable the text box if "Others" is selected
                    } else {
                        input.disabled = true; // Disable the text box otherwise
                        input.value = ""; // Clear the text box when disabled
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




    document.querySelector('#membership-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        const submitButton = document.querySelector('#submit-btn');
        submitButton.disabled = true;
        submitButton.textContent = 'Submitting...';

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
</body>

</html>