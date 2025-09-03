<?php
require_once 'backend/db/db_connect.php';
require_once 'backend/utils/access_control.php';

// Configure session security based on environment
configureSessionSecurity();
session_start();

// Check authentication status (but don't require it)
$isLoggedIn = isset($_SESSION['user_id']);

// Default visually impaired flag is 0.
$isVisuallyImpaired = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT visually_impaired FROM users WHERE user_id = ?");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        exit('An internal error occurred');
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($visually_impaired);
    if ($stmt->fetch()) {
        $isVisuallyImpaired = $visually_impaired;
    }
    $stmt->close();
}

// Load editable content from settings
$aboutSettings = [
    'about_hero_title' => null,
    'about_hero_subtitle' => null,
    'about_purpose_text' => null,
    'about_mission_text' => null,
    'about_vision_text' => null,
    'about_objectives_html' => null,
    'about_hero_image_url' => null
];
$aKeys = array_keys($aboutSettings);
$placeholders = implode(',', array_fill(0, count($aKeys), '?'));
$types = str_repeat('s', count($aKeys));
$stmtS = $conn->prepare("SELECT `key`, value FROM settings WHERE `key` IN ($placeholders)");
if ($stmtS) {
    $stmtS->bind_param($types, ...$aKeys);
    $stmtS->execute();
    $resS = $stmtS->get_result();
    while ($row = $resS->fetch_assoc()) {
        $aboutSettings[$row['key']] = $row['value'];
    }
    $stmtS->close();
}

// Admin inline edit mode support (used by Admin > Pages tab via iframe)
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$editMode = $isAdmin && isset($_GET['edit']) && $_GET['edit'] == '1';

// Member directory data (list all users with role 'member')
$memberDirectory = [];
if ($stmtM = $conn->prepare("SELECT first_name, last_name FROM users WHERE role = 'member' ORDER BY last_name, first_name")) {
    $stmtM->execute();
    $resM = $stmtM->get_result();
    while ($row = $resM->fetch_assoc()) {
        $memberDirectory[] = $row;
    }
    $stmtM->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About - ADOHRE | Empowering Health Retirees</title>
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

    <!-- Inline Styles for Page-Specific Tweaks -->
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            line-height: 1.6;
            color: #333;
        }

        /* Hero Section for About Page */
        .about-hero {
            position: relative;
            background: url('assets/pexels-fauxels-3184434.jpg') no-repeat center center/cover;
            padding: 120px 0;
            color: #fff;
            text-align: center;
        }

        .about-hero::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1;
        }

        .about-hero .container {
            position: relative;
            z-index: 2;
        }

        .about-hero h1 {
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .about-hero p {
            font-size: 1.2rem;
        }

        /* Section Headings */
        h2 {
            color: var(--accent-color, #28A745);
            margin-bottom: 20px;
        }

        /* Carousel (Horizontal Slider) for PMV */
        .carousel-item .card {
            border: none;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin: auto;
            max-width: 600px;
        }

        .carousel-item .card-body {
            padding: 2rem;
        }

        .section-padding {
            padding: 60px 0;
        }

        /* Improve arrow visibility on the Core Pillars carousel */
        #pmvCarousel .carousel-control-prev,
        #pmvCarousel .carousel-control-next {
            width: 48px;
            height: 48px;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.95);
            border-radius: 50%;
            opacity: 1;
        }

        #pmvCarousel .carousel-control-prev-icon,
        #pmvCarousel .carousel-control-next-icon {
            background-size: 24px 24px;
        }

        #pmvCarousel .carousel-control-prev-icon {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%2328A745' viewBox='0 0 16 16'%3E%3Cpath d='M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z'/%3E%3C/svg%3E");
        }

        #pmvCarousel .carousel-control-next-icon {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%2328A745' viewBox='0 0 16 16'%3E%3Cpath d='M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
        }

        /* Core Values Card Styling */
        .core-value-card {
            border: none;
            text-align: center;
            margin-bottom: 20px;
        }

        .core-value-card h4 {
            font-size: 2rem;
            color: var(--accent-color, #28A745);
        }

        .core-value-card p {
            font-size: 1rem;
        }

        .core-value-card p span {
            color: var(--accent-color, #28A745);
            font-weight: bold;
        }

        /* Expertise and Interests Section */
        .expertise-section {
            background: url('assets/expertise-bg.jpg') no-repeat center center/cover;
            color: #fff;
            padding: 60px 0;
            position: relative;
        }

        .expertise-section::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(41, 41, 48, 0.7);
            /* Dark blue overlay */
            z-index: 1;
        }

        .expertise-section .container {
            position: relative;
            z-index: 2;
        }

        .expertise-section h2 {
            color: #fff;
            /* White heading */
            text-align: center;
            margin-bottom: 40px;
        }

        .expertise-card {
            background: rgba(255, 255, 255, 0.1);
            /* Semi-transparent white background */
            border: none;
            border-radius: 8px;
            margin-bottom: 20px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .expertise-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }

        .expertise-card .card-header {
            background-color: var(--accent-color, #28A745) !important;
            color: #fff !important;
            text-align: center;
            font-weight: 700;
            border-radius: 8px 8px 0 0;
        }

        .expertise-card .card-body {
            padding: 1.5rem;
            color: #fff;
            /* White text */
        }

        .expertise-card ul {
            list-style-type: disc;
            padding-left: 20px;
            margin: 0;
        }

        .expertise-card ul li {
            margin-bottom: 10px;
        }

        /* Organizational Objectives Section */
        .objectives-section {
            background: url('assets/objectives-bg.jpg') no-repeat center center/cover;
            color: #fff;
            /* White text */
            padding: 60px 0;
            position: relative;
        }

        .objectives-section::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 51, 0.7);
            /* Dark blue overlay */
            z-index: 1;
        }

        .objectives-section .container {
            position: relative;
            z-index: 2;
        }

        .objectives-section h2 {
            color: #fff;
            /* White heading */
            cursor: pointer;
        }

        .objectives-section h2 .arrow {
            color: #FFD700;
            /* Gold arrow */
            font-size: 1.5rem;
            transition: transform 0.3s ease;
        }

        .objectives-section h2 .arrow.rotate {
            transform: rotate(180deg);
        }

        .objectives-section ul {
            list-style-type: disc;
            padding-left: 20px;
            color: #F0F0F0;
            /* Light gray text */
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

        <?php if ($editMode): ?>

        /* Inline edit helpers (admin-only) */
        .edit-outline {
            outline: 2px dashed rgba(40, 167, 69, 0.6);
            outline-offset: 4px;
        }

        .edit-toolbar {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 10;
        }

        .edit-toolbar .btn {
            padding: 4px 8px;
        }

        .edit-hint {
            position: fixed;
            bottom: 10px;
            left: 10px;
            background: rgba(33, 37, 41, 0.9);
            color: #fff;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 12px;
            z-index: 1050;
        }

        <?php endif; ?>
    </style>
    <!-- Pass the visually impaired flag to JavaScript -->
    <script>
        var isVisuallyImpaired = <?php echo json_encode($isVisuallyImpaired); ?>;
    </script>
    <script src="tts.js"></script>
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
        <!-- Hero Section -->
        <?php
        $aboutHeroBg = $aboutSettings['about_hero_image_url'] ?? '';
        if ($aboutHeroBg && strpos($aboutHeroBg, '/s3proxy/') !== false) {
            $aboutHeroBg = 'backend/routes/decrypt_image.php?image_url=' . urlencode($aboutHeroBg);
        }
        ?>
        <section class="about-hero position-relative" <?php if (!empty($aboutHeroBg)): ?>style="background-image: url('<?= htmlspecialchars($aboutHeroBg, ENT_QUOTES) ?>'); background-size: cover; background-position: center;" <?php endif; ?>>
            <?php if ($editMode): ?>
                <div class="edit-toolbar">
                    <button class="btn btn-light btn-sm" id="btnAboutHeroImg"><i class="fa fa-image me-1"></i>Change Hero Image</button>
                    <input type="file" id="aboutHeroFileInline" accept="image/*" class="d-none">
                </div>
            <?php endif; ?>
            <div class="container">
                <h1 <?php if ($editMode): ?>contenteditable="true" data-edit-key="about_hero_title" class="edit-outline" <?php endif; ?>><?= htmlspecialchars($aboutSettings['about_hero_title'] ?? 'About ADOHRE', ENT_QUOTES) ?></h1>
                <p <?php if ($editMode): ?>contenteditable="true" data-edit-key="about_hero_subtitle" class="edit-outline" <?php endif; ?>><?= htmlspecialchars($aboutSettings['about_hero_subtitle'] ?? 'Discover ADOHRE: Your Best Chapter is Here!', ENT_QUOTES) ?></p>
            </div>
        </section>

        <!-- Horizontal Slider for Core Pillars -->
        <section class="section-padding bg-light">
            <div class="container">
                <h2 class="text-center mb-4">Our Core Pillars</h2>
                <div id="pmvCarousel" class="carousel slide" data-bs-ride="carousel">
                    <!-- Carousel Indicators -->
                    <div class="carousel-indicators">
                        <button type="button" data-bs-target="#pmvCarousel" data-bs-slide-to="0" class="active"
                            aria-current="true" aria-label="Purpose"></button>
                        <button type="button" data-bs-target="#pmvCarousel" data-bs-slide-to="1"
                            aria-label="Mission"></button>
                        <button type="button" data-bs-target="#pmvCarousel" data-bs-slide-to="2"
                            aria-label="Vision"></button>
                    </div>

                    <!-- Carousel Inner -->
                    <div class="carousel-inner" style="height:350px;">
                        <!-- Purpose Slide -->
                        <div class="carousel-item active" style="height:350px;">
                            <div class="d-flex justify-content-center align-items-center" style="height:100%;">
                                <div class="card text-center" style="max-width:600px;">
                                    <div class="card-body">
                                        <h3 class="card-title text-success">Purpose</h3>
                                        <p class="card-text">
                                            <span <?php if ($editMode): ?>contenteditable="true" data-edit-key="about_purpose_text" class="edit-outline" <?php endif; ?>><?= nl2br(htmlspecialchars($aboutSettings['about_purpose_text'] ?? 'Our goals: develop and strengthen partnerships; improve member and team capabilities; provide relevant and quality programs and services; enhance systems for effective and efficient performance; and ensure better communication and awareness toward policy enhancement and development.', ENT_QUOTES)) ?></span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Mission Slide -->
                        <div class="carousel-item" style="height:350px;">
                            <div class="d-flex justify-content-center align-items-center" style="height:100%;">
                                <div class="card text-center" style="max-width:600px;">
                                    <div class="card-body">
                                        <h3 class="card-title text-success">Mission</h3>
                                        <p class="card-text">
                                            <span <?php if ($editMode): ?>contenteditable="true" data-edit-key="about_mission_text" class="edit-outline" <?php endif; ?>><?= nl2br(htmlspecialchars($aboutSettings['about_mission_text'] ?? 'We serve the health sector by developing and improving capabilities of members and partnership, providing relevant and responsive programs and services, continuous systems development and ensuring better communication processes and promote awareness on health.', ENT_QUOTES)) ?></span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Vision Slide -->
                        <div class="carousel-item" style="height:350px;">
                            <div class="d-flex justify-content-center align-items-center" style="height:100%;">
                                <div class="card text-center" style="max-width:600px;">
                                    <div class="card-body">
                                        <h3 class="card-title text-success">Vision</h3>
                                        <p class="card-text"><span <?php if ($editMode): ?>contenteditable="true" data-edit-key="about_vision_text" class="edit-outline" <?php endif; ?>><?= nl2br(htmlspecialchars($aboutSettings['about_vision_text'] ?? '“Responsive and relevant partner for better health outcomes for Filipinos.“', ENT_QUOTES)) ?></span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Carousel Controls with Custom Arrow Colors -->
                    <button class="carousel-control-prev" type="button" data-bs-target="#pmvCarousel"
                        data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#pmvCarousel"
                        data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                </div>
            </div>
        </section>

        <!-- Organizational Objectives Section -->
        <section class="objectives-section">
            <div class="container">
                <h2 class="text-center mb-4" id="objectivesHeading">
                    Our Organizational Objectives
                    <span class="arrow">▼</span>
                </h2>
                <div class="collapse" id="objectivesCollapse">
                    <div <?php if ($editMode): ?>contenteditable="true" data-edit-key="about_objectives_html" class="edit-outline" <?php endif; ?>><?= $aboutSettings['about_objectives_html'] ?? '<ul><li>Provide services and products in the form of technical assistance, expert advice, consulting services, learning and development services, and related activities to the DOH, other partners and stakeholders.</li><li>Develop, promote, and implement concerted action for the empowerment, protection, well-being and welfare of its members.</li><li>Foster cooperation, camaraderie and solidarity among the members of the Association.</li><li>Improve ADOHRE’s organizational systems that foster integrity, good governance, efficiency and effectiveness.</li><li>Advocate and raise awareness on health and relevant issues while communicating and promoting ADOHRE’s programs and support it can offer.</li><li>Develop and market ADOHRE brand of technical expertise and assistance.</li><li>Participate and network with other organizations and government agencies towards the attainment of the goals of the Association most specially in promoting health as a right for every Filipino.</li></ul>' ?></div>
                </div>
            </div>
        </section>

        <!-- Core Values Section -->
        <section class="section-padding bg-light text-center">
            <div class="container">
                <h2 class="mb-4">Our Core Values</h2>
                <div class="row justify-content-center">
                    <!-- Each core value presented in its own card -->
                    <div class="col-sm-4 col-md-2">
                        <div class="card core-value-card">
                            <div class="card-body">
                                <h4>A</h4>
                                <p><span>A</span>ccountability and Answerability</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4 col-md-2">
                        <div class="card core-value-card">
                            <div class="card-body">
                                <h4>D</h4>
                                <p><span>D</span>edication and Devotion</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4 col-md-2">
                        <div class="card core-value-card">
                            <div class="card-body">
                                <h4>O</h4>
                                <p><span>O</span>penness or Overtness</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4 col-md-2">
                        <div class="card core-value-card">
                            <div class="card-body">
                                <h4>H</h4>
                                <p><span>H</span>armony and Honesty</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4 col-md-2">
                        <div class="card core-value-card">
                            <div class="card-body">
                                <h4>R</h4>
                                <p><span>R</span>espect and Responsibility</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4 col-md-2">
                        <div class="card core-value-card">
                            <div class="card-body">
                                <h4>E</h4>
                                <p><span>E</span>quity and Equality</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Expertise and Interests Section -->
        <section class="expertise-section">
            <div class="container">
                <h2>Our Expertise and Interests</h2>
                <div class="row">
                    <!-- Organizational Strategies -->
                    <div class="col-md-6 mb-4">
                        <div class="card expertise-card">
                            <div class="card-header">
                                Our Organizational Strategies
                            </div>
                            <div class="card-body">
                                <ul>
                                    <li>Resource mobilization and management</li>
                                    <li>Partnership development and networking strengthening</li>
                                    <li>Systems development</li>
                                    <li>Capability-building</li>
                                    <li>Promotion, advocacy, and communication</li>
                                    <li>Program and Service Delivery</li>
                                    <li>Data management</li>
                                    <li>Policy and Procedure Development</li>
                                </ul>
                            </div>
                        </div>
                        <!-- Areas of Expertise (moved here, below strategies) -->
                        <div class="card expertise-card mt-4">
                            <div class="card-header">
                                Areas of Expertise
                            </div>
                            <div class="card-body">
                                <ul>
                                    <li>Program Development &amp; Project Management</li>
                                    <li>Fund Management and Administration</li>
                                    <li>Learning and Development/Training</li>
                                    <li>Event Organizing</li>
                                    <li>Community Organizing</li>
                                    <li>Strategic and Operational Planning</li>
                                    <li>Research &amp; Development</li>
                                    <li>Policy and Standards Development</li>
                                    <li>Manual of Operation and SOP Development</li>
                                    <li>Monitoring and Evaluation</li>
                                    <li>Health Management Support Administration</li>
                                    <li>Health Promotion and Advocacy</li>
                                    <li>Facilitation Skills</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <!-- Fields of Interest -->
                    <div class="col-md-6 mb-4">
                        <div class="card expertise-card">
                            <div class="card-header">
                                Fields of Interest
                            </div>
                            <div class="card-body">
                                <ul>
                                    <li>Universal health care</li>
                                    <li>Medical Certification and Cause of Death and Civil Registration and Vital
                                        Statistics</li>
                                    <li>Active Ageing/Elderly Health and Parenting</li>
                                    <li>Community-based Drug Prevention and Control</li>
                                    <li>Maternal, Child Health and Nutrition</li>
                                    <li>HIV/AIDS Education</li>
                                    <li>Hospital Operations and Management</li>
                                    <li>Adolescent Health</li>
                                    <li>Healthy Lifestyle</li>
                                    <li>Mental Health</li>
                                    <li>First-Aid Management</li>
                                    <li>Stress Management</li>
                                    <li>Health Promotion and Advocacy</li>
                                    <li>Leadership and Governance for Health</li>
                                    <li>Wellness Program</li>
                                    <li>Nutrition</li>
                                    <li>Climate Change</li>
                                    <li>Health Systems Development</li>
                                    <li>Communicable Diseases</li>
                                    <li>Non-Communicable Diseases</li>
                                    <li>Disease Prevention and Control</li>
                                    <li>Health Data Management</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Organizational Structure Section -->
        <?php
        // Image path for the uploaded organizational structure diagram
        $orgImg = 'assets/org-structure-2025.png';
        $orgImgExists = file_exists($orgImg);
        ?>
        <section class="section-padding bg-white">
            <div class="container">
                <h2 class="text-center mb-3">Organizational Structure, 2025</h2>
                <p class="text-center text-muted mb-4">Explore the 2025 ADOHRE organizational chart. View full-screen,
                    zoom in, or download. A text outline is available for accessibility.</p>

                <figure class="text-center">
                    <?php if ($orgImgExists): ?>
                        <img src="<?php echo $orgImg; ?>"
                            alt="ADOHRE Organizational Structure chart for 2025 showing the General Assembly at the top; the Board of Trustees; Officers; External Auditor; Management Committee; Administrative Staff; Project Implementation/Management Teams; Finance, Membership & Training, Advocacy, Project, and Ad-hoc/Special Committees; and Project Development Teams."
                            class="img-fluid shadow-sm rounded" style="cursor: zoom-in; max-height: 70vh;"
                            id="orgStructureImg">
                        <figcaption class="mt-2 text-muted small">Click the image to open full-screen. You can also download
                            it or read the text outline below.</figcaption>
                    <?php else: ?>
                        <div class="alert alert-warning text-start" role="alert">
                            Organizational structure image not found. Please copy your file to
                            <code>assets/org-structure-2025.png</code>.
                        </div>
                    <?php endif; ?>
                </figure>

                <div class="d-flex flex-wrap justify-content-center gap-2 mt-2">
                    <?php if ($orgImgExists): ?>
                        <a href="<?php echo $orgImg; ?>" download class="btn btn-outline-secondary">
                            <i class="fa fa-download me-1"></i> Download PNG
                        </a>
                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#orgStructureModal">
                            <i class="fa fa-up-right-and-down-left-from-center me-1"></i> View full-screen
                        </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-dark" data-bs-toggle="collapse"
                        data-bs-target="#orgTextOutline" aria-expanded="false" aria-controls="orgTextOutline">
                        <i class="fa fa-list me-1"></i> Text outline
                    </button>
                </div>

                <div class="collapse mt-3" id="orgTextOutline">
                    <div class="card card-body">
                        <h5 class="mb-3">Text Outline</h5>
                        <ul class="mb-2">
                            <li><strong>General Assembly</strong></li>
                            <li><strong>Board of Trustees (7)</strong>
                                <ul>
                                    <li>Chairperson</li>
                                    <li>Vice-Chairperson</li>
                                    <li>Board Secretary</li>
                                    <li>Trustees</li>
                                </ul>
                            </li>
                            <li><strong>Officers (5)</strong>
                                <ul>
                                    <li>President/CEO</li>
                                    <li>Vice-President/COO</li>
                                    <li>Secretary</li>
                                    <li>Treasurer</li>
                                    <li>Internal Auditor</li>
                                </ul>
                            </li>
                            <li><strong>External Auditor</strong></li>
                            <li><strong>Management Committee</strong>
                                <ul>
                                    <li>BOT members</li>
                                    <li>Officers</li>
                                    <li>Committee Chairpersons/Vice</li>
                                </ul>
                            </li>
                            <li><strong>Administrative Staff</strong>
                                <ul>
                                    <li>Administrative Assistant</li>
                                    <li>Finance Assistant</li>
                                    <li>Project Staff</li>
                                </ul>
                            </li>
                            <li><strong>Project Implementation Teams / Project Management Teams (PIT/PMT)</strong>
                                <ul>
                                    <li>PIT/PMT 1</li>
                                    <li>PIT/PMT 2</li>
                                    <li>PIT/PMT n</li>
                                </ul>
                            </li>
                            <li><strong>Committees</strong>
                                <ul>
                                    <li><em>Finance Committee</em>: Chairperson, Vice-Chairperson, 3 or 5 Members, 1
                                        Adviser</li>
                                    <li><em>Membership &amp; Training Committee</em>: Chairperson, Vice-Chairperson, 3
                                        or 5 Members, 1 Adviser</li>
                                    <li><em>Advocacy Committee</em>: Chairperson, Vice-Chairperson, 3 or 5 Members, 1
                                        Adviser</li>
                                    <li><em>Project Committee</em>: Chairperson, Vice-Chairperson, 3 or 5 Members, 1
                                        Adviser</li>
                                    <li><em>Ad-hoc/Special Committees</em> (e.g., Election Committee, Disciplinary
                                        Committee)</li>
                                </ul>
                            </li>
                            <li><strong>Project Development Teams</strong></li>
                        </ul>
                        <p class="mb-0 small text-muted">This outline mirrors the content of the organizational chart
                            for screen readers and low-vision accessibility.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Board of Trustees & Officers -->
        <section class="section-padding bg-light">
            <div class="container">
                <h2 class="text-center mb-4">Board of Trustees &amp; Officers</h2>
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card h-100 shadow-sm">
                            <div class="card-header bg-success text-white fw-bold">Board of Trustees, 2025–2026</div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item">DR. PAULYN JEAN B. ROSELL UBIAL — <em>Chairperson</em>
                                    </li>
                                    <li class="list-group-item">DR. JUANITO D. TALEON — <em>Vice-Chairperson</em></li>
                                    <li class="list-group-item">MS. LOURDES RIZA S. YAPCIONGCO — <em>Board
                                            Secretary</em></li>
                                    <li class="list-group-item">MS. AGNETTE PERALTA — <em>Trustee</em></li>
                                    <li class="list-group-item">DR. ASUNCION M. ANDEN — <em>Trustee</em></li>
                                    <li class="list-group-item">MS. ANTONINA U. CUETO — <em>Trustee</em></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100 shadow-sm">
                            <div class="card-header bg-success text-white fw-bold">Officers, 2025–2026</div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item">DR. THEODORA CECILE G. MAGTURO — <em>President</em></li>
                                    <li class="list-group-item">MS. VICENTA E. BORJA — <em>Vice President</em></li>
                                    <li class="list-group-item">MS. PRUDENCIA L. CSAQUEJO — <em>Treasurer</em></li>
                                    <li class="list-group-item">MS. MARIA IMELDA L. LIM — <em>Auditor</em></li>
                                    <li class="list-group-item">MS. JACKELINE B. ACOSTA — <em>Secretary</em></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Member Directory -->
        <section class="section-padding bg-white">
            <div class="container">
                <h2 class="text-center mb-4">Member Directory</h2>
                <?php if (!empty($memberDirectory)): ?>
                    <div class="row g-2 g-md-3">
                        <?php foreach ($memberDirectory as $m): ?>
                            <?php
                            $fn = isset($m['first_name']) ? $m['first_name'] : '';
                            $ln = isset($m['last_name']) ? $m['last_name'] : '';
                            $name = trim($fn . ' ' . $ln);
                            if ($name === '') {
                                $name = 'Unnamed Member';
                            }
                            ?>
                            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                                <div class="border rounded px-3 py-2 bg-light h-100">
                                    <span class="fw-semibold">
                                        <?= htmlspecialchars($name, ENT_QUOTES) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info" role="alert">
                        No members found yet.
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Call to ction Band -->
        <section class="section-padding bg-light">
            <div class="container">
                <div class="p-4 p-md-5 d-flex flex-column flex-md-row align-items-center justify-content-between gap-3 shadow-sm"
                    style="background: linear-gradient(90deg, #28A745, #2ecc71); color: #fff; border-radius: 12px;">
                    <div>
                        <h3 class="mb-1">Be part of ADOHRE</h3>
                        <p class="mb-0">Join our community of retired health professionals and stay engaged.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="membership_form.php" class="btn btn-light">Apply for Membership</a>
                        <a href="mailto:adohre366@gmail.com" class="btn btn-outline-light">Contact Us</a>
                    </div>
                </div>
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

    <!-- ***** TEXT TO SPEECH FUNCTION ADDED BELOW ***** -->
    <!-- Read Page Button (only visible if you choose to make it available; it's always visible in this example) -->
    <button id="readPageBtn" title="Read Page">
        <i class="fas fa-volume-up"></i>
    </button>
    <!-- ***** END TEXT TO SPEECH FUNCTION ***** -->

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
    </script>

    <!-- JavaScript for Back to Top Button and Objectives Toggle -->
    <script>
        // Back to Top Button
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

        // On window load, show the Read Page button if visually impaired.
        window.addEventListener('load', function() {
            console.log("Window loaded. isVisuallyImpaired =", isVisuallyImpaired);
            if (isVisuallyImpaired == 1) {
                document.getElementById("readPageBtn").style.display = "block";
            }
        });


        // Objectives Toggle
        const objectivesHeading = document.getElementById("objectivesHeading");
        const arrow = objectivesHeading.querySelector(".arrow");
        const objectivesCollapse = document.getElementById("objectivesCollapse");

        objectivesHeading.addEventListener("click", function() {
            objectivesCollapse.classList.toggle("show");
            arrow.classList.toggle("rotate");
        });

        // Read Page button: read text from <main> only using innerText
        document.getElementById("readPageBtn").addEventListener("click", function() {
            console.log("Read Page button clicked in about.php");
            const mainElement = document.querySelector('main');
            let textToRead = "";
            if (mainElement) {
                textToRead = mainElement.innerText.trim();
                console.log("Reading from main element, length:", textToRead.length);
            } else {
                textToRead = document.body.innerText.trim();
                console.log("No main found, reading entire body, length:", textToRead.length);
            }
            TTS.speakTextInChunks(textToRead);
        });
        <?php if ($editMode): ?>
                // --- Inline edit mode logic (About) ---
                (function() {
                    const page = 'about';
                    let dirty = false;
                    let latestHeroUrl = <?= json_encode($aboutSettings['about_hero_image_url'] ?? '') ?>;

                    const markDirty = () => {
                        if (!dirty) {
                            dirty = true;
                            try {
                                parent.postMessage({
                                    type: 'pageEditChange',
                                    page
                                }, '*');
                            } catch (e) {}
                        }
                    };

                    document.addEventListener('click', (e) => {
                        const a = e.target.closest('a');
                        if (a) {
                            e.preventDefault();
                            e.stopPropagation();
                        }
                    }, true);

                    document.querySelectorAll('[data-edit-key]').forEach(el => {
                        el.addEventListener('input', markDirty);
                        el.addEventListener('blur', markDirty);
                    });

                    const btn = document.getElementById('btnAboutHeroImg');
                    const file = document.getElementById('aboutHeroFileInline');
                    if (btn && file) {
                        btn.addEventListener('click', () => file.click());
                        file.addEventListener('change', async (e) => {
                            const f = e.target.files && e.target.files[0];
                            if (!f) return;
                            const fd = new FormData();
                            fd.append('page', 'about');
                            fd.append('field', 'hero_image_url');
                            fd.append('image', f);
                            try {
                                const res = await fetch('backend/routes/settings_api.php?action=upload_page_image', {
                                    method: 'POST',
                                    body: fd
                                });
                                const j = await res.json();
                                if (j.status) {
                                    latestHeroUrl = j.url;
                                    const url = j.url.includes('/s3proxy/') ? ('backend/routes/decrypt_image.php?image_url=' + encodeURIComponent(j.url)) : j.url;
                                    const hero = document.querySelector('.about-hero');
                                    if (hero) hero.style.backgroundImage = `url('${url}')`;
                                    markDirty();
                                    alert('Image uploaded');
                                } else {
                                    alert(j.message || 'Upload failed');
                                }
                            } catch (err) {
                                alert('Upload error');
                            }
                        });
                    }

                    window.addEventListener('message', async (event) => {
                        const data = event.data || {};
                        if (data.type === 'pageSave') {
                            try {
                                const payload = {};
                                document.querySelectorAll('[data-edit-key]').forEach(el => {
                                    const key = el.getAttribute('data-edit-key');
                                    if (!key) return;
                                    if (key.endsWith('_html')) payload[key] = el.innerHTML;
                                    else payload[key] = el.innerText.trim();
                                });
                                if (latestHeroUrl) payload['about_hero_image_url'] = latestHeroUrl;
                                const fd = new FormData();
                                fd.append('page', page);
                                fd.append('data', JSON.stringify(payload));
                                const res = await fetch('backend/routes/settings_api.php?action=update_page_content', {
                                    method: 'POST',
                                    body: fd
                                });
                                const j = await res.json();
                                if (j.status) {
                                    dirty = false;
                                    try {
                                        parent.postMessage({
                                            type: 'pageEditSaved',
                                            page
                                        }, '*');
                                    } catch (e) {}
                                } else {
                                    try {
                                        parent.postMessage({
                                            type: 'pageEditError',
                                            page,
                                            message: j.message || 'Failed'
                                        }, '*');
                                    } catch (e) {}
                                }
                            } catch (err) {
                                try {
                                    parent.postMessage({
                                        type: 'pageEditError',
                                        page,
                                        message: 'Save error'
                                    }, '*');
                                } catch (e) {}
                            }
                        }
                    });

                    const hint = document.createElement('div');
                    hint.className = 'edit-hint';
                    hint.textContent = 'Editing mode: click text to modify. Use the toolbar to change hero image. Save from the admin tab.';
                    document.body.appendChild(hint);
                })();
        <?php endif; ?>
    </script>

    <!-- Org Structure Modal (full-screen with zoom controls) -->
    <div class="modal fade" id="orgStructureModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content bg-dark">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-white">Organizational Structure, 2025</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body d-flex justify-content-center align-items-center p-0">
                    <?php if ($orgImgExists): ?>
                        <div class="w-100 text-center" style="overflow:auto;">
                            <img src="<?php echo $orgImg; ?>" alt="Enlarged organizational structure image" id="orgZoomImg"
                                class="img-fluid" style="transform-origin: center center; cursor: grab;" />
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer border-0 justify-content-between">
                    <div class="btn-group" role="group" aria-label="Zoom controls">
                        <button type="button" class="btn btn-light" id="zoomOutBtn">-</button>
                        <button type="button" class="btn btn-light" id="zoomResetBtn">100%</button>
                        <button type="button" class="btn btn-light" id="zoomInBtn">+</button>
                    </div>
                    <?php if ($orgImgExists): ?>
                        <a href="<?php echo $orgImg; ?>" download class="btn btn-success">
                            <i class="fa fa-download me-1"></i> Download
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Open modal when clicking the inline image
        const orgInlineImg = document.getElementById('orgStructureImg');
        if (orgInlineImg) {
            orgInlineImg.addEventListener('click', function() {
                const modal = new bootstrap.Modal(document.getElementById('orgStructureModal'));
                modal.show();
            });
        }

        // Simple zoom controls for the modal image
        (function() {
            const img = document.getElementById('orgZoomImg');
            if (!img) return;
            let scale = 1;
            const apply = () => img.style.transform = `scale(${scale})`;
            document.getElementById('zoomInBtn').addEventListener('click', () => {
                scale = Math.min(5, scale * 1.2);
                apply();
            });
            document.getElementById('zoomOutBtn').addEventListener('click', () => {
                scale = Math.max(0.5, scale / 1.2);
                apply();
            });
            document.getElementById('zoomResetBtn').addEventListener('click', () => {
                scale = 1;
                apply();
            });
        })();
    </script>
    <script>
        // Robust toggle for the Text outline to ensure it collapses on second click
        (function() {
            const outlineEl = document.getElementById('orgTextOutline');
            const outlineBtn = document.querySelector('[data-bs-target="#orgTextOutline"]');
            if (!outlineEl || !outlineBtn || typeof bootstrap === 'undefined' || !bootstrap.Collapse) return;
            const collapse = new bootstrap.Collapse(outlineEl, {
                toggle: false
            });
            outlineBtn.addEventListener('click', function(e) {
                e.preventDefault();
                collapse.toggle();
            });
            outlineEl.addEventListener('shown.bs.collapse', () => outlineBtn.setAttribute('aria-expanded', 'true'));
            outlineEl.addEventListener('hidden.bs.collapse', () => outlineBtn.setAttribute('aria-expanded', 'false'));
        })();
    </script>
</body>

</html>