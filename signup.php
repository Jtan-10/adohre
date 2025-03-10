<?php
// Start the session and include your database connection.
session_start();
require_once 'backend/db/db_connect.php';

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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Member Link</title>
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
        /* Ensure it appears above everything else */
        display: none;
    }
    </style>
    <!-- Include the global TTS module (create tts.js with your TTS functions) -->
    <script src="tts.js"></script>
    <!-- Pass the visually impaired flag to JavaScript -->
    <script>
    var isVisuallyImpaired = <?php echo json_encode($isVisuallyImpaired); ?>;
    </script>
</head>

<body>
    <div class="left-pane">
        <img src="assets/logo.png" alt="ADOHRE Logo" width="100">
        <h1 class="mt-3">Sign Up</h1>
        <p>Enter your email to start the signup process.</p>
        <form id="signupForm" class="w-75">
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" name="email" class="form-control" id="email" placeholder="Enter your email">
            </div>
            <button type="button" id="signupBtn" class="btn btn-success">Send OTP</button>
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

    <!-- Include Visually Impaired Modal -->
    <?php include 'visually_impaired_modal.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
    </script>

    <script>
    // Set up Speech Recognition.
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    let recognition;
    let validResponseReceived = false; // Flag to check if valid response received.
    if (SpeechRecognition) {
        recognition = new SpeechRecognition();
        recognition.lang = 'en-US';
        recognition.interimResults = false;
        recognition.maxAlternatives = 1;

        // Function to start listening.
        function startListening() {
            validResponseReceived = false;
            recognition.start();
        }

        recognition.onresult = function(event) {
            const transcript = event.results[0][0].transcript.toLowerCase();
            console.log("Recognized speech:", transcript);
            if (transcript.includes("yes")) {
                validResponseReceived = true;
                TTS.speakMessage("You said yes.", function() {
                    setVisuallyImpaired(true, visuallyImpairedModal);
                });
            } else if (transcript.includes("no")) {
                validResponseReceived = true;
                TTS.speakMessage("You said no.", function() {
                    setVisuallyImpaired(false, visuallyImpairedModal);
                });
            } else {
                TTS.speakMessage("Try again.", startListening);
            }
        };

        recognition.onerror = function(event) {
            console.error("Speech recognition error:", event.error);
            TTS.speakMessage("There was an error with speech recognition. Please click a button instead.",
                function() {});
        };

        recognition.onend = function() {
            if (!validResponseReceived) {
                TTS.speakMessage("Try again.", startListening);
            }
        };
    } else {
        console.warn("Speech Recognition not supported. Please use the buttons.");
    }

    // Function to send the visually impaired flag to the server and save it in the session.
    function setVisuallyImpaired(isImpaired, modalInstance) {
        const payload = {
            visually_impaired: isImpaired
        };
        console.log("Sending visually impaired flag payload:", payload);
        fetch('backend/routes/set_visually_impaired.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(response => {
                console.log("Response headers:", response.headers);
                return response.json();
            })
            .then(data => {
                console.log("Visually impaired update response:", data);
                modalInstance.hide();
            })
            .catch(error => {
                console.error("Error updating visually impaired:", error);
                modalInstance.hide();
            });
    }

    // Globalize the initialization using event listeners.
    document.addEventListener('DOMContentLoaded', function() {
        // Use the global TTS function for a welcome message.
        TTS.speakMessage("Welcome to ADOHRE! Please sign up with your email to get started.");

        // Initialize the visually impaired modal so that clicking outside or pressing Escape won't close it.
        var visuallyImpairedModalElement = document.getElementById('visuallyImpairedModal');
        window.visuallyImpairedModal = new bootstrap.Modal(visuallyImpairedModalElement, {
            backdrop: 'static',
            keyboard: false
        });

        // When the modal is shown, read its content (text only) and then prompt "or say yes or no".
        visuallyImpairedModalElement.addEventListener('shown.bs.modal', function() {
            const modalText = document.querySelector('#visuallyImpairedModal .modal-body').innerText
                .trim();
            TTS.speakMessage(modalText, function() {
                TTS.speakMessage("or say yes or no", startListening);
            });
        });

        // Show the modal.
        visuallyImpairedModal.show();

        // Manual button listeners.
        document.getElementById('btnYes').addEventListener('click', function() {
            console.log("User clicked Yes.");
            window.speechSynthesis.cancel();
            TTS.speakMessage("You said yes.", function() {
                setVisuallyImpaired(true, visuallyImpairedModal);
            });
        });

        document.getElementById('btnNo').addEventListener('click', function() {
            console.log("User clicked No.");
            window.speechSynthesis.cancel();
            TTS.speakMessage("You said no.", function() {
                setVisuallyImpaired(false, visuallyImpairedModal);
            });
        });

        // Show/hide loading screen functions.
        function showLoading() {
            const ls = document.getElementById('loadingScreen');
            ls.classList.remove('d-none');
            ls.style.display = 'flex';
        }

        // 'Send OTP' button handler.
        document.getElementById('signupBtn').addEventListener('click', async () => {
            const email = document.getElementById('email').value;
            if (!email) {
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
                        email: email
                    })
                });
                const result = await response.json();
                hideLoading();
                if (result.status) {
                    showModal('Success', result.message, `otp.php?action=signup&email=${email}`);
                } else {
                    showModal('Error', result.message);
                }
            } catch (error) {
                hideLoading();
                console.error('Error:', error);
                showModal('Error', 'An error occurred. Please try again.');
            }
        });


        function hideLoading() {
            const ls = document.getElementById('loadingScreen');
            ls.classList.add('d-none');
            ls.style.display = 'none';
        }

        // Show Bootstrap modal function.
        function showModal(title, message, redirectUrl = null) {
            document.getElementById('responseModalLabel').textContent = title;
            document.getElementById('responseModalBody').textContent = message;
            var modal = new bootstrap.Modal(document.getElementById('responseModal'));
            modal.show();
            if (redirectUrl) {
                modal._element.addEventListener('hidden.bs.modal', () => {
                    window.location.href = redirectUrl;
                });
            }
        }
    });

    // Add duplicate listener for signupBtn outside DOMContentLoaded block? (Optional: Remove if already inside.)
    document.getElementById('signupBtn').addEventListener('click', async () => {
        const email = document.getElementById('email').value;
        if (!email) {
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
                    email
                })
            });
            const result = await response.json();
            hideLoading();
            if (result.status) {
                showModal('Success', result.message, `otp.php?action=signup&email=${email}`);
            } else {
                showModal('Error', result.message);
            }
        } catch (error) {
            hideLoading();
            console.error('Error:', error);
            showModal('Error', 'An error occurred. Please try again.');
        }
    });
    </script>
</body>

</html>