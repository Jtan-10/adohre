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

    /* Organizational Structure Styles */
    .org-structure {
        padding: 60px 0;
        background-color: #f8f9fa;
    }

    .org-chart {
        display: flex;
        flex-direction: column;
        align-items: center;
        margin-top: 30px;
    }

    .org-level {
        display: flex;
        justify-content: center;
        margin-bottom: 40px;
        width: 100%;
    }

    .org-unit {
        background-color: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 15px;
        margin: 0 15px;
        text-align: center;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        min-width: 200px;
        position: relative;
    }

    .org-unit.central {
        background-color: #e8f5e9;
        border-color: #28a745;
    }

    .org-unit h4 {
        color: #28a745;
        font-size: 16px;
        margin-bottom: 10px;
    }

    .org-unit ul {
        list-style-type: none;
        padding: 0;
        margin: 0;
        text-align: left;
    }

    .org-unit li {
        padding: 5px 0;
        border-bottom: 1px solid #eee;
    }

    .org-unit li:last-child {
        border-bottom: none;
    }

    .connector {
        position: absolute;
        background-color: #28a745;
        width: 2px;
        height: 20px;
        bottom: -20px;
        left: 50%;
        transform: translateX(-50%);
    }

    .horizontal-connector {
        position: absolute;
        background-color: #28a745;
        height: 2px;
        top: 50%;
        transform: translateY(-50%);
    }

    .org-sublevel {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        margin-top: 40px;
        position: relative;
    }

    .org-sublevel::before {
        content: "";
        position: absolute;
        background-color: #28a745;
        width: 2px;
        height: 20px;
        top: -20px;
        left: 50%;
        transform: translateX(-50%);
    }

    .org-subunit {
        background-color: white;
        border: 1px solid #ddd;
        border-radius: 6px;
        padding: 10px;
        margin: 10px;
        text-align: center;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        min-width: 180px;
        position: relative;
    }

    .org-subunit h5 {
        color: #28a745;
        font-size: 14px;
        margin-bottom: 8px;
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
    </style>
    <!-- Pass the visually impaired flag to JavaScript -->
    <script>
    var isVisuallyImpaired = 0; // Default value
    </script>
    <script src="tts.js"></script>
</head>

<body>
    <!-- Header -->
    <header role="banner">
        <nav class="navbar navbar-expand-lg navbar-dark bg-success">
            <div class="container">
                <a class="navbar-brand" href="index.php">
                    <img src="assets/logo.png" alt="ADOHRE Logo" width="40" height="40"
                        class="d-inline-block align-text-top me-2">
                    ADOHRE
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="about.php">About</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="projects.php">Projects</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="members.php">Members</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="contact.php">Contact</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link btn btn-outline-light ms-2" href="login.php">Login</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <!-- Include the Sidebar -->
    <div class="sidebar">
        <!-- Sidebar content would go here -->
    </div>

    <!-- Main Content -->
    <main role="main">
        <!-- Hero Section -->
        <section class="about-hero">
            <div class="container">
                <h1>About ADOHRE</h1>
                <p>Discover ADOHRE: Your Best Chapter is Here!</p>
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
                                            Foster cooperation and unity among members. Promote and implement actions
                                            for
                                            member empowerment and welfare.
                                            Network with organizations and government agencies. Provide technical
                                            assistance and expertise to the DOH and partners.
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
                                            We serve the health sector by enhancing the capabilities of our members,
                                            forging strategic partnerships,
                                            and delivering responsive programs that foster better communication and
                                            awareness.
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
                                        <p class="card-text">
                                            To be a responsive and relevant partner for better health outcomes for
                                            Filipinos.
                                        </p>
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
                    <ul>
                        <li>Provide technical assistance, expert advice, consulting, and training sessions.</li>
                        <li>Develop and implement actions for member empowerment, protection, and welfare.</li>
                        <li>Foster unity and cooperation among association members.</li>
                        <li>Enhance organizational sustainability, efficiency, and effectiveness.</li>
                        <li>Advocate for health and social issues while bridging our initiatives with DOH units.</li>
                        <li>Strengthen our initiatives through strategic partnerships and collaborations.</li>
                        <li>Network with organizations and government agencies to achieve our goals.</li>
                    </ul>
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
                                <p><span>A</span>ccountability &amp; Integrity</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4 col-md-2">
                        <div class="card core-value-card">
                            <div class="card-body">
                                <h4>D</h4>
                                <p><span>D</span>edication</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4 col-md-2">
                        <div class="card core-value-card">
                            <div class="card-body">
                                <h4>O</h4>
                                <p><span>O</span>penness &amp; Freedom</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4 col-md-2">
                        <div class="card core-value-card">
                            <div class="card-body">
                                <h4>H</h4>
                                <p><span>H</span>armony</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4 col-md-2">
                        <div class="card core-value-card">
                            <div class="card-body">
                                <h4>R</h4>
                                <p><span>R</span>espect</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4 col-md-2">
                        <div class="card core-value-card">
                            <div class="card-body">
                                <h4>E</h4>
                                <p><span>E</span>quity</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Organizational Structure Section -->
        <section class="org-structure">
            <div class="container">
                <h2 class="text-center mb-5">Organizational Structure 2025</h2>

                <div class="org-chart">
                    <!-- Level 1: General Assembly -->
                    <div class="org-level">
                        <div class="org-unit central">
                            <h4>GENERAL ASSEMBLY</h4>
                            <div class="connector"></div>
                        </div>
                    </div>

                    <!-- Level 2: Board of Trustees and External Auditor -->
                    <div class="org-level">
                        <div class="org-unit">
                            <h4>BOARD OF TRUSTEES (7)</h4>
                            <ul>
                                <li>Chairperson</li>
                                <li>Vice-Chairperson</li>
                                <li>Board Secretary</li>
                                <li>Trustees</li>
                            </ul>
                            <div class="connector"></div>
                        </div>

                        <div class="org-unit">
                            <h4>EXTERNAL AUDITOR</h4>
                            <div class="connector"></div>
                        </div>
                    </div>

                    <!-- Level 3: Management Committee -->
                    <div class="org-level">
                        <div class="org-unit central">
                            <h4>MANAGEMENT COMMITTEE</h4>
                            <ul>
                                <li>BOT members</li>
                                <li>Officers</li>
                                <li>Committee Chairpersons/Vice</li>
                            </ul>
                            <div class="connector"></div>
                        </div>
                    </div>

                    <!-- Level 4: Officers and Committees -->
                    <div class="org-level">
                        <div class="org-unit">
                            <h4>OFFICERS (5)</h4>
                            <ul>
                                <li>President/CEO</li>
                                <li>Vice-President/COO</li>
                                <li>Secretary</li>
                                <li>Treasurer</li>
                                <li>Internal Auditor</li>
                            </ul>
                            <div class="connector"></div>
                        </div>

                        <!-- Committees -->
                        <div class="org-sublevel">
                            <div class="org-subunit">
                                <h5>FINANCE COMMITTEE</h5>
                                <ul>
                                    <li>Chairperson</li>
                                    <li>Vice-Chairperson</li>
                                    <li>3 or 5 Members</li>
                                    <li>1 Adviser</li>
                                </ul>
                            </div>

                            <div class="org-subunit">
                                <h5>MEMBERSHIP & TRAINING COMMITTEE</h5>
                                <ul>
                                    <li>Chairperson</li>
                                    <li>Vice Chairperson</li>
                                    <li>3 or 5 Members</li>
                                    <li>1 Adviser</li>
                                </ul>
                            </div>

                            <div class="org-subunit">
                                <h5>ADVOCACY COMMITTEE</h5>
                                <ul>
                                    <li>Chairperson</li>
                                    <li>Vice-Chairperson</li>
                                    <li>3 or 5 Members</li>
                                    <li>1 Adviser</li>
                                </ul>
                            </div>

                            <div class="org-subunit">
                                <h5>PROJECT COMMITTEE</h5>
                                <ul>
                                    <li>Chairperson</li>
                                    <li>Vice-Chairperson</li>
                                    <li>3 or 5 Members</li>
                                    <li>1 Adviser</li>
                                </ul>
                            </div>

                            <div class="org-subunit">
                                <h5>AD-HOC/SPECIAL COMMITTEE</h5>
                                <ul>
                                    <li>Election Committee</li>
                                    <li>Disciplinary Committee</li>
                                    <li>...</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Level 5: Administrative Staff and Project Teams -->
                    <div class="org-level">
                        <div class="org-unit">
                            <h4>ADMINISTRATIVE STAFF</h4>
                            <ul>
                                <li>Administrative Assistant</li>
                                <li>Finance Assistant</li>
                                <li>Project Staff</li>
                            </ul>
                        </div>

                        <div class="org-unit">
                            <h4>PROJECT IMPLEMENTATION TEAMS / PROJECT MANAGEMENT TEAMS (PIT/PMT)</h4>
                            <ul>
                                <li>PIT/PMT *</li>
                                <li>PIT/PMT *</li>
                                <li>PIT/PMT *</li>
                            </ul>
                        </div>

                        <div class="org-unit">
                            <h4>PROJECT DEVELOPMENT TEAMS *</h4>
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
                                    <li>Resource mobilization</li>
                                    <li>Partnership development &amp; strengthening</li>
                                    <li>Systems development</li>
                                    <li>Capability building</li>
                                    <li>Promotion, advocacy &amp; communication</li>
                                    <li>Program &amp; service delivery</li>
                                    <li>Data management</li>
                                    <li>Policy, standards &amp; procedures development</li>
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
                                    <li>Program Development</li>
                                    <li>Project Management</li>
                                    <li>Fund Management &amp; Administration</li>
                                    <li>Learning &amp; Development/Training</li>
                                    <li>Event Organizing</li>
                                    <li>Community Organizing</li>
                                    <li>Coalition &amp; Alliance Building</li>
                                    <li>Policy Development</li>
                                    <li>Monitoring &amp; Evaluation</li>
                                    <li>Health Management Support Administration</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Call to Action Band -->
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
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <h5>ADOHRE</h5>
                    <p>Association of Department of Health Retirees</p>
                    <p>Empowering Health Retirees</p>
                </div>
                <div class="col-md-4 mb-3">
                    <h5>Contact Info</h5>
                    <p><i class="fas fa-envelope me-2"></i> adohre366@gmail.com</p>
                    <p><i class="fas fa-phone me-2"></i> +63 XXX-XXXX-XXX</p>
                </div>
                <div class="col-md-4 mb-3">
                    <h5>Follow Us</h5>
                    <div class="d-flex gap-3">
                        <a href="#" class="text-white"><i class="fab fa-facebook-f fa-lg"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-linkedin-in fa-lg"></i></a>
                    </div>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p class="mb-0">&copy; 2025 ADOHRE. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Back to Top Button -->
    <button id="backToTopBtn" title="Go to top">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- Read Page Button -->
    <button id="readPageBtn" title="Read Page">
        <i class="fas fa-volume-up"></i>
    </button>

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
        // TTS.speakTextInChunks(textToRead); // Uncomment if TTS is available
    });
    </script>
</body>

</html>