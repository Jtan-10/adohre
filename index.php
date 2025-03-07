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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ADOHRE | Empowering Health Retirees</title>
    <link rel="icon" href="assets/logo.png" type="image/jpg">

    <!-- Google Fonts for Modern Typography -->
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/style.css">

    <!-- Inline Styles for Quick Tweaks -->
    <style>
    /* Base Typography & Colors */
    body {
        font-family: 'Montserrat', sans-serif;
        line-height: 1.6;
        color: #333;
        background-color: #fff;
    }

    /* Hero Section with Overlay */
    .hero-section {
        position: relative;
        background: url('assets/pexels-fauxels-3184429.jpg') no-repeat center center/cover;
        color: #fff;
        padding: 120px 0;
        overflow: hidden;
    }

    .hero-section::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1;
    }

    .hero-section .container {
        position: relative;
        z-index: 2;
    }

    .hero-section h1 {
        font-size: 2.8rem;
        font-weight: 700;
        margin-bottom: 20px;
    }

    .hero-section p {
        font-size: 1.2rem;
        margin-bottom: 30px;
    }

    /* Section Headings */
    section h2 {
        color: var(--accent-color, #28A745);
        margin-bottom: 20px;
    }

    /* Custom Button Styling */
    .btn-custom {
        background-color: var(--accent-color, #28A745);
        border: none;
        color: #fff;
        padding: 12px 30px;
        font-size: 1rem;
        border-radius: 4px;
        transition: background-color 0.3s ease, transform 0.3s ease;
    }

    .btn-custom:hover {
        background-color: #218838;
        transform: translateY(-2px);
    }

    /* Section Padding */
    .section-padding {
        padding: 60px 0;
    }

    /* About Section Text */
    .about-text p {
        font-size: 1rem;
        line-height: 1.8;
        margin-bottom: 1rem;
    }

    /* Empowerment Section Overlay */
    .empower-section {
        position: relative;
        background: url('assets/background-image.png') no-repeat center center/cover;
    }

    .empower-section::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1;
    }

    .empower-section .container {
        position: relative;
        z-index: 2;
    }

    /* Back to Top Button */
    #backToTopBtn {
        display: none;
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 99;
        border: none;
        outline: none;
        background-color: var(--accent-color, #28A745);
        color: white;
        cursor: pointer;
        padding: 12px 20px;
        border-radius: 50%;
        font-size: 1.2rem;
        transition: background-color 0.3s ease;
    }

    #backToTopBtn:hover {
        background-color: #218838;
    }

    /* Read Page Button - Always visible in top right for visually impaired users */
    #readPageBtn {
        display: none;
        /* Shown only if isVisuallyImpaired == 1 */
        position: fixed;
        top: 70px;
        right: 20px;
        z-index: 99;
        border: none;
        outline: none;
        background-color: var(--accent-color, #28A745);
        color: white;
        cursor: pointer;
        padding: 12px 20px;
        border-radius: 30%;
        font-size: 1.2rem;
        transition: background-color 0.3s ease;
    }

    #readPageBtn:hover {
        background-color: #218838;
    }
    </style>

    <!-- Pass the visually impaired flag to JavaScript -->
    <script>
    var isVisuallyImpaired = <?php echo json_encode($isVisuallyImpaired); ?>;
    </script>
</head>

<body>
    <!-- Header -->
    <header role="banner">
        <?php include('header.php'); ?>
    </header>

    <!-- Include the Sidebar -->
    <?php include('sidebar.php'); ?>

    <!-- Main Content -->
    <main role="main">
        <!-- Carousel (if any) -->
        <?php include('carousel.php'); ?>

        <!-- Hero Section -->
        <section class="hero-section text-center">
            <div class="container">
                <h1>
                    THE ASSOCIATION OF DEPARTMENT OF HEALTH RETIRED EMPLOYEES, INC. PHILIPPINES (ADOHRE)
                </h1>
                <p class="lead">
                    Discover ADOHRE: Your Best Chapter is Here!
                </p>
                <a href="membership_form.php" class="btn btn-custom btn-lg">
                    Join Us
                </a>
            </div>
        </section>

        <!-- About Section -->
        <section class="section-padding">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-6 mb-4 mb-md-0">
                        <img src="assets/about-image.jpg" class="img-fluid rounded" alt="Group Photo">
                    </div>
                    <div class="col-md-6 about-text">
                        <h2>About ADOHRE</h2>
                        <p>
                            The Association of Department of Health Retired Employees, or ADOHRE, is a non-stock,
                            non-profit organization established in 2014 through the Securities Exchange Commission.
                        </p>
                        <p>
                            Our organization is composed of up to 100 active members—retired employees and former
                            officials of the Department of Health—with extensive experience in public health, nutrition,
                            hospital management, regulatory oversight, facility development, health promotion, policy
                            development, and management support.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Empowerment Section -->
        <section class="section-padding text-center text-white empower-section">
            <div class="container">
                <h2>Empower Yourself with ADOHRE</h2>
                <p>
                    Are you ready to elevate your skills and expand your knowledge? We offer a range of engaging
                    webinars and training sessions designed to empower you in both your personal and professional
                    journey. Our expert-led programs cover the latest trends and best practices, providing you with
                    valuable insights and actionable strategies.
                </p>
            </div>
        </section>

        <!-- Contact Section -->
        <section class="section-padding text-center bg-light">
            <div class="container">
                <h2>Where We Are</h2>
                <p>
                    5th Floor, Philippine Blood Center, 6512 Quezon Avenue, Diliman, Quezon City 1101
                </p>
                <a href="membership_form.php" class="btn btn-custom">
                    Join Us
                </a>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer>
        <?php include('footer.php'); ?>
    </footer>

    <!-- Back to Top Button -->
    <button id="backToTopBtn" title="Go to top">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- Read Page Button (only visible for visually impaired users) -->
    <button id="readPageBtn" title="Read Page" style="display: none;">
        <i class="fas fa-volume-up"></i>
    </button>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
    </script>

    <!-- JavaScript for Back to Top Button -->
    <script>
    // Get the back-to-top button
    const backToTopBtn = document.getElementById("backToTopBtn");

    // Show the button when the user scrolls down 20px
    window.onscroll = function() {
        if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
            backToTopBtn.style.display = "block";
        } else {
            backToTopBtn.style.display = "none";
        }
    };

    // Scroll to the top when the button is clicked
    backToTopBtn.addEventListener("click", function() {
        window.scrollTo({
            top: 0,
            behavior: "smooth"
        });
    });
    </script>
    <!-- JavaScript for the Read Page Button -->
    <script>
    // Function to split text into chunks and speak them sequentially.
    function speakTextInChunks(text, maxChunkLength = 200) {
        // Split text into chunks
        let chunks = [];
        while (text.length > 0) {
            let chunk = text.substring(0, maxChunkLength);
            // Try to avoid cutting off in the middle of a word:
            let lastSpace = chunk.lastIndexOf(" ");
            if (lastSpace > 0 && text.length > maxChunkLength) {
                chunk = text.substring(0, lastSpace);
            }
            chunks.push(chunk);
            text = text.substring(chunk.length);
        }

        // Function to speak a single chunk and then call the next one.
        function speakChunk(index) {
            if (index >= chunks.length) return; // All chunks spoken
            const utterance = new SpeechSynthesisUtterance(chunks[index]);
            utterance.lang = 'en-GB';
            // Set up the voice
            function setVoice() {
                let voices = window.speechSynthesis.getVoices();
                let selectedVoice = voices.find(voice =>
                    voice.lang === 'en-GB' && voice.name.toLowerCase().includes('female')
                );
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
            // When this chunk finishes, speak the next chunk.
            utterance.onend = function() {
                speakChunk(index + 1);
            };
        }
        speakChunk(0);
    }

    // Updated speakMessage function for shorter text (if needed)
    function speakMessage(message) {
        const utterance = new SpeechSynthesisUtterance(message);
        utterance.lang = 'en-GB';

        function setVoice() {
            let voices = window.speechSynthesis.getVoices();
            let selectedVoice = voices.find(voice =>
                voice.lang === 'en-GB' && voice.name.toLowerCase().includes('female')
            );
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

    // When the window loads, check if the user is visually impaired.
    window.onload = function() {
        console.log("Window loaded. isVisuallyImpaired =", isVisuallyImpaired);
        if (isVisuallyImpaired == 1) {
            // Show the Read Page button if visually impaired.
            document.getElementById("readPageBtn").style.display = "block";
        }
    };

    // Add click event listener to the Read Page button.
    document.getElementById("readPageBtn").addEventListener("click", function() {
        console.log("Read Page button clicked.");
        // Grab all text content from the body
        const fullText = document.body.textContent.trim();
        console.log("Full text length:", fullText.length);

        // Use the chunking function to speak the full text
        speakTextInChunks(fullText);
    });
    </script>

</body>

</html>