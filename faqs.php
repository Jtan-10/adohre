<?php
require_once 'backend/db/db_connect.php';
require_once 'backend/utils/access_control.php';

// Secure session and start
configureSessionSecurity();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine visually impaired setting for current user
$isVisuallyImpaired = 0;
if (isset($_SESSION['user_id'])) {
    $uid = (int) $_SESSION['user_id'];
    if ($stmt = $conn->prepare("SELECT visually_impaired FROM users WHERE user_id = ?")) {
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $stmt->bind_result($vi);
        if ($stmt->fetch()) {
            $isVisuallyImpaired = (int) $vi;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQs - ADOHRE</title>
    <link rel="icon" href="assets/logo.png" type="image/png">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/style.css">

    <style>
        .faq-hero {
            position: relative;
            background: url('assets/pexels-fauxels-3182835.jpg') no-repeat center center/cover;
            padding: 120px 0;
            color: #fff;
            text-align: center;
        }

        .faq-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            z-index: 1;
        }

        .faq-hero .container {
            position: relative;
            z-index: 2;
        }

        .faq-hero h1 {
            font-size: 2.6rem;
            font-weight: 700;
        }

        .faq-hero p {
            font-size: 1.1rem;
            opacity: 0.95;
        }

        /* Search input */
        .faq-search {
            max-width: 700px;
        }

        .faq-search .form-control {
            padding: 0.9rem 1.1rem;
        }

        /* Accordion tweaks */
        .accordion-button:not(.collapsed) {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--accent-color, #28A745);
            box-shadow: none;
        }

        .accordion-button:focus {
            box-shadow: none;
        }

        /* CTA band */
        .cta-band {
            background: linear-gradient(90deg, #28A745, #2ecc71);
            color: #fff;
            border-radius: 12px;
        }

        /* Back to Top & Read buttons */
        #backToTopBtn,
        #readPageBtn {
            display: none;
            /* toggled via JS */
            position: fixed;
            right: 20px;
            z-index: 99;
            border: none;
            outline: none;
            background-color: var(--accent-color, #28A745);
            color: #fff;
            cursor: pointer;
            padding: 12px 16px;
            border-radius: 50%;
            font-size: 1.1rem;
            transition: background-color 0.3s ease;
        }

        #backToTopBtn {
            bottom: 20px;
        }

        #readPageBtn {
            top: 80px;
        }

        #backToTopBtn:hover,
        #readPageBtn:hover {
            background-color: #218838;
        }
    </style>

    <script>
        var isVisuallyImpaired = <?php echo json_encode($isVisuallyImpaired); ?>;
    </script>
    <script src="tts.js"></script>
</head>

<body>
    <!-- Header -->
    <?php include('header.php'); ?>
    <!-- Sidebar -->
    <?php include('sidebar.php'); ?>

    <!-- Hero -->
    <section class="faq-hero" role="region" aria-label="FAQs Hero">
        <div class="container">
            <h1 class="mb-2">Frequently Asked Questions</h1>
            <p>Quick answers about membership, services, and events.</p>
        </div>
    </section>

    <!-- Search -->
    <section class="py-4 bg-light">
        <div class="container d-flex justify-content-center">
            <div class="input-group faq-search shadow-sm">
                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                <input id="faqSearch" type="text" class="form-control border-start-0" placeholder="Search FAQs (e.g., membership, fees, trainings)..." aria-label="Search FAQs">
            </div>
        </div>
    </section>

    <!-- FAQs -->
    <main class="container section-padding" role="main">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="accordion" id="faqAccordion">
                    <!-- Item 1 -->
                    <div class="accordion-item" data-faq>
                        <h2 class="accordion-header" id="q1-h">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#q1" aria-expanded="true" aria-controls="q1">
                                What is ADOHRE?
                            </button>
                        </h2>
                        <div id="q1" class="accordion-collapse collapse show" aria-labelledby="q1-h" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                The Association of Department of Health Retired Employees (ADOHRE) is a professional network of retired DOH employees offering support, continuing education, and a vibrant community through trainings, events, consultations, and assistance programs.
                            </div>
                        </div>
                    </div>

                    <!-- Item 2 -->
                    <div class="accordion-item" data-faq>
                        <h2 class="accordion-header" id="q2-h">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q2" aria-expanded="false" aria-controls="q2">
                                Who is eligible to become a member?
                            </button>
                        </h2>
                        <div id="q2" class="accordion-collapse collapse" aria-labelledby="q2-h" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Individuals who served the Department of Health for at least 15 years and are officially retired are eligible to apply for membership.
                            </div>
                        </div>
                    </div>

                    <!-- Item 3 -->
                    <div class="accordion-item" data-faq>
                        <h2 class="accordion-header" id="q3-h">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q3" aria-expanded="false" aria-controls="q3">
                                What services does the Association offer?
                            </button>
                        </h2>
                        <div id="q3" class="accordion-collapse collapse" aria-labelledby="q3-h" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                We offer professional trainings, social and health-related events, one-on-one consultations (legal, health, personal support), and assistance programs for members in need.
                            </div>
                        </div>
                    </div>

                    <!-- Item 4 -->
                    <div class="accordion-item" data-faq>
                        <h2 class="accordion-header" id="q4-h">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q4" aria-expanded="false" aria-controls="q4">
                                Are there regular events for members?
                            </button>
                        </h2>
                        <div id="q4" class="accordion-collapse collapse" aria-labelledby="q4-h" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Yes. We host wellness seminars, community outreach activities, and social gatherings to keep members engaged and connected.
                            </div>
                        </div>
                    </div>

                    <!-- Item 5 -->
                    <div class="accordion-item" data-faq>
                        <h2 class="accordion-header" id="q5-h">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q5" aria-expanded="false" aria-controls="q5">
                                How can I apply for membership?
                            </button>
                        </h2>
                        <div id="q5" class="accordion-collapse collapse" aria-labelledby="q5-h" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Fill out the membership application form and submit proof of your 15+ years of DOH service along with your retirement documentation. You can start here: <a href="membership_form.php">Membership Application</a>.
                            </div>
                        </div>
                    </div>

                    <!-- Item 6 -->
                    <div class="accordion-item" data-faq>
                        <h2 class="accordion-header" id="q6-h">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q6" aria-expanded="false" aria-controls="q6">
                                Is there a membership fee?
                            </button>
                        </h2>
                        <div id="q6" class="accordion-collapse collapse" aria-labelledby="q6-h" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Yes, we collect a small annual membership fee to support operations and services. Details are provided during application or upon inquiry.
                            </div>
                        </div>
                    </div>

                    <!-- Item 7 -->
                    <div class="accordion-item" data-faq>
                        <h2 class="accordion-header" id="q7-h">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q7" aria-expanded="false" aria-controls="q7">
                                Can members volunteer or contribute to activities?
                            </button>
                        </h2>
                        <div id="q7" class="accordion-collapse collapse" aria-labelledby="q7-h" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Absolutely. Members can help organize events, mentor others, or share expertise in trainings and consultations.
                            </div>
                        </div>
                    </div>

                    <!-- Item 8 -->
                    <div class="accordion-item" data-faq>
                        <h2 class="accordion-header" id="q8-h">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q8" aria-expanded="false" aria-controls="q8">
                                Where is the Association located?
                            </button>
                        </h2>
                        <div id="q8" class="accordion-collapse collapse" aria-labelledby="q8-h" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                6512 Quezon Ave, Diliman, Quezon City, Metro Manila, Philippine Blood Center 3rd Floor. We also operate in various regions and offer online consultations and events.
                            </div>
                        </div>
                    </div>

                    <!-- Item 9 -->
                    <div class="accordion-item" data-faq>
                        <h2 class="accordion-header" id="q9-h">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q9" aria-expanded="false" aria-controls="q9">
                                Can non-members access services?
                            </button>
                        </h2>
                        <div id="q9" class="accordion-collapse collapse" aria-labelledby="q9-h" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Most services are member-exclusive, but some trainings or events may be open to guests. Check event details for specifics.
                            </div>
                        </div>
                    </div>

                    <!-- Item 10 -->
                    <div class="accordion-item" data-faq>
                        <h2 class="accordion-header" id="q10-h">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q10" aria-expanded="false" aria-controls="q10">
                                Who can I contact for more information?
                            </button>
                        </h2>
                        <div id="q10" class="accordion-collapse collapse" aria-labelledby="q10-h" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Email us at <a href="mailto:adohre366@gmail.com">adohre366@gmail.com</a> or visit our official website at <a href="https://adohre.site" target="_blank" rel="noopener">adohre.site</a>.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CTA -->
        <div class="row justify-content-center mt-5">
            <div class="col-lg-10">
                <div class="p-4 p-md-5 cta-band d-flex flex-column flex-md-row align-items-center justify-content-between gap-3 shadow-sm">
                    <div>
                        <h3 class="mb-1">Still have questions?</h3>
                        <p class="mb-0">Join ADOHRE or contact us and we’ll be happy to help.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="membership_form.php" class="btn btn-light">Apply for Membership</a>
                        <a href="mailto:adohre366@gmail.com" class="btn btn-outline-light">Contact Support</a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php include('footer.php'); ?>

    <!-- Back to Top & Read Page -->
    <button id="backToTopBtn" title="Back to top" aria-label="Back to top"><i class="fas fa-arrow-up"></i></button>
    <button id="readPageBtn" title="Read page" aria-label="Read page"><i class="fas fa-volume-up"></i></button>

    <!-- Bootstrap Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Back to Top visibility
        const backToTopBtn = document.getElementById('backToTopBtn');
        window.addEventListener('scroll', () => {
            const show = document.documentElement.scrollTop > 50;
            backToTopBtn.style.display = show ? 'block' : 'none';
        });
        backToTopBtn.addEventListener('click', () => window.scrollTo({
            top: 0,
            behavior: 'smooth'
        }));

        // Read Page button
        window.addEventListener('load', () => {
            if (isVisuallyImpaired == 1) {
                document.getElementById('readPageBtn').style.display = 'block';
            }
        });
        document.getElementById('readPageBtn').addEventListener('click', () => {
            const mainEl = document.querySelector('main');
            const text = (mainEl ? mainEl.innerText : document.body.innerText).trim();
            if (window.TTS && typeof TTS.speakTextInChunks === 'function') {
                TTS.speakTextInChunks(text);
            }
        });

        // FAQ search filter
        const searchInput = document.getElementById('faqSearch');
        const items = Array.from(document.querySelectorAll('[data-faq]'));
        searchInput.addEventListener('input', function() {
            const q = this.value.toLowerCase();
            items.forEach(item => {
                const text = item.innerText.toLowerCase();
                item.style.display = text.includes(q) ? '' : 'none';
            });
        });
    </script>
</body>

</html>