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
        background: url('assets/green-bg.jpg') no-repeat center center/cover;
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
    /// Modified speakMessage that accepts a callback to run after the utterance finishes.
    function speakMessage(message, callback) {
        const utterance = new SpeechSynthesisUtterance(message);
        utterance.lang = 'en-GB';

        // When the speech ends, call the callback if provided.
        utterance.onend = function() {
            if (callback) {
                callback();
            }
        };

        // Function to select an English (GB) female voice if available.
        function setVoice() {
            let voices = window.speechSynthesis.getVoices();
            let selectedVoice = voices.find(voice => voice.lang === 'en-GB' && voice.name.toLowerCase().includes(
                'female'));
            if (!selectedVoice) {
                selectedVoice = voices.find(voice => voice.lang === 'en-GB') || voices[0];
            }
            utterance.voice = selectedVoice;
            window.speechSynthesis.speak(utterance);
        }

        if (window.speechSynthesis.getVoices().length === 0) {
            window.speechSynthesis.onvoiceschanged = setVoice;
        } else {
            setVoice();
        }
    }

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
                speakMessage("You said yes.", function() {
                    setVisuallyImpaired(true, visuallyImpairedModal);
                });
            } else if (transcript.includes("no")) {
                validResponseReceived = true;
                speakMessage("You said no.", function() {
                    setVisuallyImpaired(false, visuallyImpairedModal);
                });
            } else {
                // Invalid response: speak "try again" and listen again.
                speakMessage("Try again.", startListening);
            }
        };

        recognition.onerror = function(event) {
            console.error("Speech recognition error:", event.error);
            speakMessage("There was an error with speech recognition. Please click a button instead.", function() {
                // Optionally, you might decide to start listening again here.
            });
        };

        recognition.onend = function() {
            // If recognition ended without a valid response, prompt to try again.
            if (!validResponseReceived) {
                speakMessage("Try again.", startListening);
            }
        };
    } else {
        console.warn("Speech Recognition not supported. Please use the buttons.");
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

    // DOMContentLoaded: Initialize modal, event listeners, and the speech sequence.
    document.addEventListener('DOMContentLoaded', function() {
        // Play a welcome message.
        speakMessage("Welcome to Member Link! Please sign up with your email to get started.");

        // Initialize the visually impaired modal so that clicking outside or pressing Escape won't close it.
        var visuallyImpairedModalElement = document.getElementById('visuallyImpairedModal');
        window.visuallyImpairedModal = new bootstrap.Modal(visuallyImpairedModalElement, {
            backdrop: 'static',
            keyboard: false
        });

        // When the modal is shown, read its content.
        visuallyImpairedModalElement.addEventListener('shown.bs.modal', function() {
            const modalText = document.querySelector('#visuallyImpairedModal .modal-body').textContent
                .trim();
            speakMessage(modalText, function() {
                // After reading the modal, finish with "or say yes or no" then start listening.
                speakMessage("or say yes or no", startListening);
            });
        });

        // Show the modal.
        visuallyImpairedModal.show();

        // Manual button listeners.
        document.getElementById('btnYes').addEventListener('click', function() {
            console.log("User clicked Yes.");
            window.speechSynthesis.cancel(); // Stop any ongoing text-to-speech
            speakMessage("You said yes.", function() {
                setVisuallyImpaired(true, visuallyImpairedModal);
            });
        });


        document.getElementById('btnNo').addEventListener('click', function() {
            console.log("User clicked No.");
            window.speechSynthesis.cancel(); // Stop any ongoing text-to-speech
            speakMessage("You said no.", function() {
                setVisuallyImpaired(false, visuallyImpairedModal);
            });
        });

        // Example: 'Send OTP' button handler.
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

        // Example implementations for showLoading, hideLoading, and showModal.
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
            var modal = new bootstrap.Modal(document.getElementById('responseModal'));
            modal.show();
            if (redirectUrl) {
                document.getElementById('responseModal').addEventListener('hidden.bs.modal', () => {
                    window.location.href = redirectUrl;
                });
            }
        }
    });

    document.getElementById('signupBtn').addEventListener('click', async () => {
        const email = document.getElementById('email').value;

        if (!email) {
            showModal('Error', 'Please enter your email.');
            return;
        }

        showLoading(); // Show loading screen
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
            hideLoading(); // Hide loading screen

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

    // Show Loading Screen
    function showLoading() {
        document.getElementById('loadingScreen').classList.remove('d-none');
        document.getElementById('loadingScreen').style.display = 'flex';
    }

    // Hide Loading Screen
    function hideLoading() {
        document.getElementById('loadingScreen').classList.add('d-none');
        document.getElementById('loadingScreen').style.display = 'none';
    }

    // Show Bootstrap Modal
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
    </script>
</body>

</html>