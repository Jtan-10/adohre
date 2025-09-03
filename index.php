<?php
// Enable error reporting for debugging (temporarily)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'backend/db/db_connect.php';

// Configure session security based on environment
configureSessionSecurity();

// Start the session
session_start();

// Log session state
error_log('INDEX.PHP - Session ID: ' . session_id());
error_log('INDEX.PHP - Session Data: ' . json_encode($_SESSION));

// Send security headers
header("X-Content-Type-Options: nosniff");

// Include access control utilities
require_once 'backend/utils/access_control.php';

// Pull editable page content from settings
$homeSettings = [
    'home_hero_title' => null,
    'home_hero_subtitle' => null,
    'home_about_html' => null,
    'home_contact_address' => null,
    'home_hero_image_url' => null
];
$keys = array_keys($homeSettings);
$placeholders = implode(',', array_fill(0, count($keys), '?'));
$types = str_repeat('s', count($keys));
$stmtSettings = $conn->prepare("SELECT `key`, value FROM settings WHERE `key` IN ($placeholders)");
if ($stmtSettings) {
    $stmtSettings->bind_param($types, ...$keys);
    $stmtSettings->execute();
    $res = $stmtSettings->get_result();
    while ($row = $res->fetch_assoc()) {
        $homeSettings[$row['key']] = $row['value'];
    }
    $stmtSettings->close();
}

// Check if user is authenticated (but don't require it for homepage)
$isLoggedIn = isset($_SESSION['user_id']);
$isOTPRequired = isset($_SESSION['otp_pending']) && $_SESSION['otp_pending'] === true;

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


// Check if the logged-in user is a member and does not have a membership application record.
if ($isLoggedIn && isset($_SESSION['role']) && $_SESSION['role'] === 'member') {
    $stmtApp = $conn->prepare("SELECT COUNT(*) as cnt FROM membership_applications WHERE user_id = ?");
    $stmtApp->bind_param("i", $_SESSION['user_id']);
    $stmtApp->execute();
    $stmtApp->bind_result($cnt);
    $stmtApp->fetch();
    $stmtApp->close();
    if (intval($cnt) === 0) {
        // Show warning message instead of redirecting
        $showMembershipWarning = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ADOHRE | Empowering Health Retirees</title>
    <link rel="icon" href="assets/logo.png" type="image/jpg">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#000000">

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

        :root {
            --accent-color: #28A745;
            --accent-dark: #1e7e34;
            --text-dark: #1f2937;
            --muted: #6b7280;
            --card-bg: #ffffff;
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
            background: linear-gradient(135deg, rgba(0, 0, 0, .6) 0%, rgba(0, 0, 0, .25) 100%);
            z-index: 1;
        }

        .hero-section .container {
            position: relative;
            z-index: 2;
        }

        .hero-section h1 {
            font-size: clamp(2rem, 3.2vw, 3rem);
            font-weight: 800;
            margin-bottom: 20px;
            letter-spacing: .2px;
        }

        .hero-section p {
            font-size: 1.125rem;
            margin-bottom: 30px;
        }

        /* Section Headings */
        section h2 {
            color: var(--accent-color, #28A745);
            margin-bottom: 20px;
        }

        /* Custom Button Styling */
        .btn-custom {
            background-color: var(--accent-color);
            border: none;
            color: #fff;
            padding: 12px 30px;
            font-size: 1rem;
            border-radius: 4px;
            transition: background-color 0.3s ease, transform 0.3s ease;
        }

        .btn-custom:hover {
            background-color: var(--accent-dark);
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

        /* Feature Cards */
        .feature-card {
            border: 0;
            border-radius: 16px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, .06);
            transition: transform .2s ease, box-shadow .2s ease;
            background: var(--card-bg);
        }

        .feature-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, .08);
        }

        .feature-icon {
            font-size: 2rem;
            color: var(--accent-color);
        }

        /* Section decorations */
        .shape-divider {
            line-height: 0;
        }

        .shape-divider svg {
            display: block;
            width: 100%;
            height: 60px;
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

        /* Cards for event/news preview */
        .preview-card img {
            height: 180px;
            object-fit: cover;
        }

        .preview-card {
            border: 0;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 6px 14px rgba(0, 0, 0, .06);
        }

        .preview-card .card-body {
            min-height: 150px;
        }

        .section-subtitle {
            color: var(--muted);
        }
    </style>

    <!-- Pass the visually impaired flag to JavaScript -->
    <script>
        var isVisuallyImpaired = <?php echo json_encode($isVisuallyImpaired); ?>;
    </script>
    <!-- Include the global TTS module (adjust the path if necessary) -->
    <script src="tts.js"></script>
</head>

<body>
    <!-- Header -->
    <header role="banner">
        <?php include('header.php'); ?>
    </header>

    <!-- Sidebar -->
    <?php include('sidebar.php'); ?>

    <?php include 'privacy_and_cookie_notice.php'; ?>

    <!-- Membership Warning -->
    <?php if (isset($showMembershipWarning) && $showMembershipWarning): ?>
        <div class="alert alert-warning alert-dismissible fade show text-center" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Membership Required:</strong> You must complete your membership application to access all features.
            <a href="membership_form.php" class="alert-link">Complete Application</a>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Main Content -->
    <main role="main">
        <!-- Carousel (if any) -->
        <?php include('carousel.php'); ?>

        <!-- Hero Section -->
        <?php
        $homeHeroBg = $homeSettings['home_hero_image_url'] ?? '';
        if ($homeHeroBg && strpos($homeHeroBg, '/s3proxy/') !== false) {
            $homeHeroBg = 'backend/routes/decrypt_image.php?image_url=' . urlencode($homeHeroBg);
        }
        ?>
        <section class="hero-section text-center" <?php if (!empty($homeHeroBg)): ?>style="background-image: url('<?= htmlspecialchars($homeHeroBg, ENT_QUOTES) ?>'); background-size: cover; background-position: center;" <?php endif; ?>>
            <div class="container">
                <h1>
                    <?= htmlspecialchars($homeSettings['home_hero_title'] ?? 'THE ASSOCIATION OF DEPARTMENT OF HEALTH (DOH) RETIRED EMPLOYEES, PHILIPPINES, INC. (ADOHRE)', ENT_QUOTES) ?>
                </h1>
                <p class="lead">
                    <?= htmlspecialchars($homeSettings['home_hero_subtitle'] ?? 'Discover ADOHRE: Your Best Chapter is Here!', ENT_QUOTES) ?>
                </p>
                <a href="membership_form.php" class="btn btn-custom btn-lg">Join Us</a>
            </div>
        </section>

        <!-- Wave Divider -->
        <div class="shape-divider">
            <svg preserveAspectRatio="none" viewBox="0 0 1200 120" xmlns="http://www.w3.org/2000/svg">
                <path d="M1200 0L0 0 0 16.48 1200 0z" opacity=".25" fill="#28A745"></path>
                <path d="M1200 0L0 0 0 6.48 1200 0z" opacity=".5" fill="#28A745"></path>
                <path d="M1200 0L0 0 0 0 1200 0z" fill="#28A745"></path>
            </svg>
        </div>

        <!-- About Section -->
        <section class="section-padding">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-6 mb-4 mb-md-0">
                        <img src="assets/about-image.jpg" class="img-fluid rounded" alt="Group Photo">
                    </div>
                    <div class="col-md-6 about-text">
                        <h2>About ADOHRE</h2>
                        <div>
                            <?= $homeSettings['home_about_html'] ?? '<p>The “Association of Department of Health (DOH) Retired Employees – Central Office,” an association of retired Department of Health personnel was established in 2014 with fifteen (15) founding members. It is a non-stock, non-profit organization registered with the Securities and Exchange Commission. In 2018, the Association amended its articles of incorporation, renaming the organization to the “Association of DOH Retired Employees, Philippines, Inc.” (ADOHRE) with the forethought of broadening its membership base, to include retirees not only from the central offices but also from DOH regional offices, hospitals, rehabilitation centers, and even attached agencies. It also filed for an amendment of its By-Laws defining anew its membership, meetings, functions of its Board of Trustees, officers, committees, roles, funds, logo, and other significant policies and processes.</p><p>After more than ten (10) years of existence, ADOHRE has more than seventy (70) members of good standing out of the more than one hundred (100) registered members. It is now expanding to hospitals and regional offices with the Centers for Health Development CALABARZON as pilot region.</p>' ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="section-padding bg-light">
            <div class="container">
                <div class="text-center mb-4">
                    <h2>Why Join ADOHRE</h2>
                    <p class="section-subtitle">Community, growth, and purpose for health retirees</p>
                </div>
                <div class="row g-4">
                    <div class="col-md-6 col-lg-3">
                        <div class="card feature-card h-100 p-3">
                            <div class="feature-icon mb-2"><i class="fa-solid fa-people-group"></i></div>
                            <h5>Supportive Community</h5>
                            <p class="mb-0">Connect with peers and mentors who share your passion for public health.</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="card feature-card h-100 p-3">
                            <div class="feature-icon mb-2"><i class="fa-solid fa-chalkboard-teacher"></i></div>
                            <h5>Training & Webinars</h5>
                            <p class="mb-0">Stay active with learning opportunities designed for real-world impact.</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="card feature-card h-100 p-3">
                            <div class="feature-icon mb-2"><i class="fa-solid fa-hand-holding-heart"></i></div>
                            <h5>Service & Advocacy</h5>
                            <p class="mb-0">Contribute to initiatives that uplift communities and colleagues.</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="card feature-card h-100 p-3">
                            <div class="feature-icon mb-2"><i class="fa-solid fa-id-card"></i></div>
                            <h5>Member Benefits</h5>
                            <p class="mb-0">Access events, assistance, and resources exclusive to members.</p>
                        </div>
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

        <!-- Upcoming Events Preview -->
        <section class="section-padding">
            <div class="container">
                <div class="d-flex align-items-end justify-content-between mb-3">
                    <div>
                        <h2 class="mb-0">Upcoming Events</h2>
                        <small class="section-subtitle">Don’t miss what’s next</small>
                    </div>
                    <a href="events.php" class="btn btn-outline-success btn-sm">View all</a>
                </div>
                <div class="row g-4" id="homeEvents"></div>
            </div>
        </section>

        <!-- Latest News Preview -->
        <section class="section-padding bg-light">
            <div class="container">
                <div class="d-flex align-items-end justify-content-between mb-3">
                    <div>
                        <h2 class="mb-0">Latest News</h2>
                        <small class="section-subtitle">Updates from the ADOHRE community</small>
                    </div>
                    <a href="news.php" class="btn btn-outline-success btn-sm">More news</a>
                </div>
                <div class="row g-4" id="homeNews"></div>
            </div>
        </section>


        <!-- Contact Section -->
        <section class="section-padding text-center bg-light">
            <div class="container">
                <h2>Where We Are</h2>
                <p><?= htmlspecialchars($homeSettings['home_contact_address'] ?? '5th Floor, Philippine Blood Center, 6512 Quezon Avenue, Diliman, Quezon City 1101', ENT_QUOTES) ?></p>
                <a href="membership_form.php" class="btn btn-custom">Join Us</a>
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
    <button id="readPageBtn" title="Read Page">
        <i class="fas fa-volume-up"></i>
    </button>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
    </script>

    <script>
        // Back-to-top button logic
        const backToTopBtn = document.getElementById("backToTopBtn");
        window.onscroll = function() {
            if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
                backToTopBtn.style.display = "block";
            } else {
                backToTopBtn.style.display = "none";
            }
        };
        backToTopBtn.addEventListener("click", function() {
            window.scrollTo({
                top: 0,
                behavior: "smooth"
            });
        });

        // When window loads, show the Read Page button if visually impaired
        window.addEventListener('load', function() {
            console.log("Window loaded. isVisuallyImpaired =", isVisuallyImpaired);
            if (isVisuallyImpaired == 1) {
                document.getElementById("readPageBtn").style.display = "block";
            }
        });

        // Read Page button: Read text from the main element only using innerText
        document.getElementById("readPageBtn").addEventListener("click", function() {
            console.log("Read Page button clicked.");
            const mainElement = document.querySelector('main');
            let textToRead = "";
            if (mainElement) {
                textToRead = mainElement.innerText.trim();
                console.log("Reading from main element, length:", textToRead.length);
            } else {
                textToRead = document.body.innerText.trim();
                console.log("No main element found, reading entire body, length:", textToRead.length);
            }
            TTS.speakTextInChunks(textToRead);
        });

        // Populate Upcoming Events
        (function loadEvents() {
            const target = document.getElementById('homeEvents');
            if (!target) return;
            fetch('backend/routes/content_manager.php?action=fetch')
                .then(r => r.json())
                .then(data => {
                    if (!data || !data.status || !Array.isArray(data.events)) throw new Error(
                        'Invalid events response');
                    const now = new Date();
                    const upcoming = data.events
                        .filter(e => new Date(e.date) >= now)
                        .sort((a, b) => new Date(a.date) - new Date(b.date))
                        .slice(0, 3);
                    if (upcoming.length === 0) {
                        target.innerHTML = '<p class="text-muted">No upcoming events.</p>';
                        return;
                    }
                    target.innerHTML = upcoming.map(e => `
                                        <div class="col-md-6 col-lg-4">
                                            <div class="card preview-card h-100">
                                                <img src="${'backend/routes/decrypt_image.php?image_url=' + encodeURIComponent(e.image || '/capstone-php/assets/default-image.jpg')}" class="card-img-top" alt="${(e.title||'Event')}">
                                                <div class="card-body">
                                                    <h5 class="card-title mb-1">${e.title || ''}</h5>
                                                    <small class="text-muted d-block mb-2"><i class="fa-regular fa-calendar me-1"></i>${new Date(e.date).toLocaleString()}</small>
                                                    <p class="card-text mb-2">${(e.description || '').toString().slice(0,120)}${(e.description||'').length>120?'…':''}</p>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="badge bg-success">${(e.fee && parseFloat(e.fee)>0)? ('₱'+e.fee):'Free'}</span>
                                                        <a href="events.php" class="btn btn-sm btn-outline-success">Details</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>`).join('');
                })
                .catch(err => {
                    console.error('Events load error', err);
                    target.innerHTML = '<p class="text-muted">Unable to load events right now.</p>';
                });
        })();

        // Populate Latest News
        (function loadNews() {
            const target = document.getElementById('homeNews');
            if (!target) return;
            fetch('backend/routes/news_manager.php?action=fetch')
                .then(r => r.json())
                .then(data => {
                    if (!data || !data.status || !Array.isArray(data.news)) throw new Error(
                        'Invalid news response');
                    const items = data.news.slice(0, 3);
                    if (items.length === 0) {
                        target.innerHTML = '<p class="text-muted">No news yet.</p>';
                        return;
                    }
                    target.innerHTML = items.map(n => `
                                        <div class="col-md-6 col-lg-4">
                                            <div class="card preview-card h-100">
                                                <img src="${ n.image ? ('backend/routes/decrypt_image.php?image_url=' + encodeURIComponent(n.image)) : 'assets/default-image.jpg' }" class="card-img-top" alt="${(n.title||'News')}">
                                                <div class="card-body">
                                                    <h5 class="card-title mb-1">${n.title || ''}</h5>
                                                    <small class="text-muted d-block mb-2"><i class="fa-regular fa-calendar me-1"></i>${new Date(n.published_date).toLocaleDateString()}</small>
                                                    <p class="card-text mb-2">${(n.excerpt||'').toString().slice(0,120)}${(n.excerpt||'').length>120?'…':''}</p>
                                                    <a href="news.php" class="btn btn-sm btn-outline-success">Read more</a>
                                                </div>
                                            </div>
                                        </div>`).join('');
                })
                .catch(err => {
                    console.error('News load error', err);
                    target.innerHTML = '<p class="text-muted">Unable to load news right now.</p>';
                });
        })();
    </script>
</body>

</html>