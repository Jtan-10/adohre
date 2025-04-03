<?php
// Enforce HTTPS for production, skip enforcement if HTTP_HOST contains "localhost"
if (stripos($_SERVER['HTTP_HOST'], 'localhost') === false && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off')) {
    $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header("Location: $redirect");
    exit();
}

// Generate a nonce for inline scripts and styles.
$csp_nonce = base64_encode(random_bytes(16));

// Disable error display to users.
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Send secure HTTP headers with CSP nonce.
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("Referrer-Policy: no-referrer");

// Add secure session cookie settings.
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',  // adjust if needed
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();
require_once 'backend/db/db_connect.php';

// Generate CSRF token if not exists.
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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
    <style nonce="<?php echo $csp_nonce; ?>">
    /* Desktop layout: two panes side by side */
    body {
        display: flex;
        min-height: 100vh;
        margin: 0;
        flex-direction: row;
    }

    .left-pane {
        flex: 1;
        display: flex;
        justify-content: center;
        align-items: center;
        flex-direction: column;
        background: #ffffff;
        padding: 2rem;
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

    /* Mobile styles (max-width: 768px) */
    @media (max-width: 768px) {
        body {
            flex-direction: column;
            background: url('assets/green_bg.png') no-repeat center center/cover;
            background-size: cover;
        }

        /* Make left pane transparent and use a pseudo-element for the white card */
        .left-pane {
            position: relative;
            background: transparent;
            padding: 1rem;
        }

        .left-pane::before {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100%;
            max-width: 90%;
            background: #ffffff;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            z-index: -1;
        }

        /* Hide right pane on mobile */
        .right-pane {
            display: none;
        }
    }
    </style>
    <!-- Include the global TTS module (create tts.js with your TTS functions) -->
    <script src="tts.js"></script>
    <!-- Pass the CSRF token and visually impaired flag to JavaScript -->
    <script nonce="<?php echo $csp_nonce; ?>">
    var csrfToken = <?php echo json_encode($_SESSION['csrf_token']); ?>;
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
                <input type="email" name="email" class="form-control" id="email" placeholder="Enter your email"
                    required>
            </div>
            <!-- Include hidden CSRF token. -->
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
    </script>
    <script nonce="<?php echo $csp_nonce; ?>">
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

        // When the modal is shown, read its content and then prompt "or say yes or no".
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
                        email: email,
                        csrf_token: csrfToken
                    })
                });
                const result = await response.json();
                hideLoading();
                if (result.status) {
                    showModal('Success', result.message, `otp.php?action=signup&email=${email}`);
                    // Also show the "Check Your Spam Folder" modal.
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
    </script>
</body>

</html>