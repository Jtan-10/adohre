<?php
// (Optional) include any backend initialization if needed
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <!-- Responsive meta tag -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQs - Association of Department of Health Retired Employees</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* FAQ Hero Section */
        .faq-hero {
            position: relative;
            background: url('assets/faq-hero.jpg') no-repeat center center/cover;
            padding: 120px 0;
            color: #fff;
            text-align: center;
        }

        .faq-hero::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1;
        }

        .faq-hero .container {
            position: relative;
            z-index: 2;
        }

        .faq-hero h1 {
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 20px;
        }

        /* FAQ Main Content Styles */
        main.container {
            margin-top: 40px;
            margin-bottom: 40px;
        }

        .faq-item {
            margin-bottom: 2rem;
        }

        .faq-item h3 {
            font-size: 1.75rem;
            color: var(--accent-color, #28A745);
            margin-bottom: 0.5rem;
        }

        .faq-item p,
        .faq-item ul {
            font-size: 1rem;
            line-height: 1.6;
        }

        .faq-item ul {
            list-style: disc;
            padding-left: 20px;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <?php include('header.php'); ?>

    <!-- Sidebar -->
    <?php include('sidebar.php'); ?>

    <!-- FAQ Hero Section -->
    <section class="faq-hero">
        <div class="container">
            <h1>Frequently Asked Questions</h1>
            <p>Find answers to your questions about our services and membership.</p>
        </div>
    </section>

    <!-- Main FAQ Content -->
    <main class="container">
        <h1 class="mb-4">Frequently Asked Questions</h1>

        <section class="faq-item">
            <h3>What is the Association of Department of Health Retired Employees?</h3>
            <p>The Association is a professional network for retired employees of the Department of Health. It provides
                support, continuing education, and a vibrant community through trainings, events, consultations, and
                assistance programs.</p>
        </section>

        <section class="faq-item">
            <h3>Who is eligible to become a member?</h3>
            <p>To be eligible for membership, individuals must have worked for the Department of Health for at least 15
                years and must be officially retired.</p>
        </section>

        <section class="faq-item">
            <h3>What services does the Association offer to its members?</h3>
            <p>We offer a variety of services, including:</p>
            <ul>
                <li>Professional trainings and skill refreshers</li>
                <li>Social and health-related events</li>
                <li>One-on-one consultations (legal, health, and personal support)</li>
                <li>Assistance programs for members in need</li>
            </ul>
        </section>

        <section class="faq-item">
            <h3>Are there regular events for members?</h3>
            <p>Yes, the Association hosts regular events such as wellness seminars, community outreach activities, and
                social gatherings to keep members engaged and connected.</p>
        </section>

        <section class="faq-item">
            <h3>How can I apply for membership?</h3>
            <p>Interested applicants can fill out a membership application form (available at our office or on our
                website) and submit proof of at least 15 years of service with the Department of Health along with
                retirement documentation.</p>
        </section>

        <section class="faq-item">
            <h3>Is there a membership fee?</h3>
            <p>Yes, a small annual membership fee is required to support the operations and services of the Association.
                Fee details are available upon application or inquiry.</p>
        </section>

        <section class="faq-item">
            <h3>Can members volunteer or contribute to Association activities?</h3>
            <p>Absolutely! Members are encouraged to get involved in organizing events, mentoring younger retirees, or
                sharing their expertise in trainings and consultations.</p>
        </section>

        <section class="faq-item">
            <h3>Where is the Association located?</h3>
            <p>Our main office is located at 6512 Quezon Ave, Diliman, Quezon City, Metro Manila, Philippine Blood
                Center 3rd Floor. We also operate in various regions and offer online consultations and events.</p>
        </section>

        <section class="faq-item">
            <h3>Can non-members access the services?</h3>
            <p>Most services are exclusive to members, but some public events or training sessions may be open to
                non-members or guests. Please check specific event details.</p>
        </section>

        <section class="faq-item">
            <h3>Who can I contact for more information?</h3>
            <p>You can reach us via email at adohre366@gmail.com, or visit our
                official website at adohre.site for more details.</p>
        </section>
    </main>

    <!-- Footer -->
    <?php include('footer.php'); ?>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>