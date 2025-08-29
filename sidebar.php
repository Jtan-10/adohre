<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if $conn exists and is alive. If not, re-establish the connection.
if (!isset($conn) || !$conn->ping()) {
    require 'backend/db/db_connect.php';
}

// Generate a unique nonce for inline scripts.
$scriptNonce = bin2hex(random_bytes(16));

// Get the current page name and determine submenu active state.
$current_page = basename($_SERVER['PHP_SELF']);
$submenuActive = ($current_page == 'consultation.php' || $current_page == 'appointments.php' || $current_page == 'medical_assistance.php');
?>

<!-- Sidebar Styles -->
<style>
    /* Modern Sidebar Styles - Centered */
    #sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100%;
        width: 250px;
        background-color: #198754;
        color: #fff;
        transition: all 0.3s ease;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 0;
        box-shadow: 3px 0 10px rgba(0, 0, 0, 0.1);
    }

    #sidebar.collapsed {
        transform: translateX(-100%);
    }

    .sidebar-header {
        text-align: center;
        padding: 0;
        margin-bottom: 0;
    }

    #sidebar ul.components {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 1rem;
    }

    #sidebar ul li {
        margin: 0;
        width: 100%;
    }

    #sidebar ul li a {
        display: block;
        width: 100%;
        padding: 10px;
        font-size: 1.1em;
        color: #fff;
        text-decoration: none;
        text-align: center;
        border-radius: 4px;
        transition: background 0.3s ease;
    }

    #sidebar ul li a:hover {
        background: rgba(255, 255, 255, 0.15);
    }

    #sidebar ul li.active>a {
        background: rgba(0, 0, 0, 0.2);
        color: #fff;
    }

    #sidebar ul li ul.submenu {
        list-style: none;
        margin: 0;
        padding: 0;
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
    }

    #sidebar ul li ul.submenu.show {
        max-height: 300px;
    }

    #sidebar ul li ul.submenu li {
        margin: 0;
    }

    #sidebar ul li ul.submenu li a {
        font-size: 0.95em;
    }

    #sidebarCollapse {
        position: fixed;
        top: 50%;
        transform: translateY(-50%);
        background: #198754;
        border: none;
        border-radius: 0 4px 4px 0;
        padding: 10px 12px;
        cursor: pointer;
        z-index: 1001;
        color: #fff;
        font-size: 1.2em;
        transition: left 0.3s ease;
        box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
    }

    #sidebarCollapse.expanded {
        left: 250px;
    }

    @media (max-width: 768px) {
        #sidebar {
            width: 200px;
        }

        #sidebarCollapse.expanded {
            left: 200px;
        }
    }
</style>
</head>

<body>
    <!-- Sidebar Navigation -->
    <div id="sidebar">
        <div class="sidebar-header"></div>
        <ul class="components">
            <li <?php if ($current_page == 'index.php') echo 'class="active"'; ?>><a href="index.php">Home</a></li>
            <li <?php if ($current_page == 'about.php') echo 'class="active"'; ?>><a href="about.php">About Us</a></li>
            <li <?php if ($current_page == 'news.php') echo 'class="active"'; ?>><a href="news.php">News</a></li>
            <li <?php if ($current_page == 'membership_form.php') echo 'class="active"'; ?>><a
                    href="membership_form.php">Member Application</a></li>
            <li <?php if ($current_page == 'faqs.php') echo 'class="active"'; ?>><a href="faqs.php">FAQs</a></li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <li>
                    <a href="#" id="virtualIdLink" data-user-id="<?php echo $_SESSION['user_id']; ?>">Virtual ID</a>
                </li>
            <?php endif; ?>
            <li <?php if ($current_page == 'health.php') echo 'class="active"'; ?>>
                <a data-bs-toggle="offcanvas" href="#offcanvasHealth" role="button"
                    aria-controls="offcanvasHealth">Health Tips</a>
            </li>
            <?php if (isset($_SESSION['user_id']) && (isset($_SESSION['role']) && $_SESSION['role'] !== 'user')): ?>
                <li <?php if ($current_page == 'member_services.php' || $submenuActive) echo 'class="active"'; ?>>
                    <a href="#" class="dropdown-toggle" id="memberServicesToggle">Member Services</a>
                    <ul class="submenu <?php echo $submenuActive ? 'show' : ''; ?>" id="memberServicesSubmenu">
                        <li <?php if ($current_page == 'consultation.php') echo 'class="active"'; ?>><a
                                href="consultation.php">Chat Assistance</a></li>
                        <li <?php if ($current_page == 'appointments.php') echo 'class="active"'; ?>><a
                                href="appointments.php">Appointments</a></li>
                        <li <?php if ($current_page == 'medical_assistance.php') echo 'class="active"'; ?>><a
                                href="medical_assistance.php">Medical Assistance</a></li>
                    </ul>
                </li>
                <li <?php if ($current_page == 'events.php') echo 'class="active"'; ?>><a href="events.php">Events</a></li>
            <?php endif; ?>
            <?php if (isset($_SESSION['user_id'])): ?>
                <li <?php if ($current_page == 'trainings.php') echo 'class="active"'; ?>><a
                        href="trainings.php">Trainings</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Off-Canvas Panel for Health and Wellness -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasHealth" aria-labelledby="offcanvasHealthLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="offcanvasHealthLabel">Health and Wellness</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <?php include('health.php'); ?>
        </div>
    </div>

    <!-- PDF Password Modal (if needed) -->
    <div class="modal fade" id="pdfPasswordModal" tabindex="-1" aria-labelledby="pdfPasswordModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pdfPasswordModalLabel">Your PDF Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="pdfPasswordText"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>



    <!-- Response Modal for Notifications -->
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

    <!-- Toggle Button for Sidebar -->
    <button id="sidebarCollapse">&gt;</button>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    </script>

    <!-- Main Sidebar and Face Validation Logic -->
    <script nonce="<?php echo $scriptNonce; ?>" defer>
        document.addEventListener('DOMContentLoaded', async function() {
            // SIDEBAR TOGGLE
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('sidebarCollapse');
            if (sidebar && toggleBtn) {
                function updateTogglePosition() {
                    if (sidebar.classList.contains('collapsed')) {
                        toggleBtn.style.left = '0';
                        toggleBtn.innerHTML = '&gt;';
                        toggleBtn.classList.remove('expanded');
                    } else {
                        if (window.innerWidth < 768) {
                            toggleBtn.style.left = '200px';
                        } else {
                            toggleBtn.style.left = '250px';
                        }
                        toggleBtn.innerHTML = '&lt;';
                        toggleBtn.classList.add('expanded');
                    }
                }
                const savedState = localStorage.getItem('sidebarState') || 'expanded';
                if (savedState === 'collapsed') {
                    sidebar.classList.add('collapsed');
                } else {
                    sidebar.classList.remove('collapsed');
                }
                updateTogglePosition();
                toggleBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    updateTogglePosition();
                    localStorage.setItem('sidebarState', sidebar.classList.contains('collapsed') ?
                        'collapsed' : 'expanded');
                });
            }
            // SUBMENU TOGGLE
            const memberServicesToggle = document.getElementById('memberServicesToggle');
            const memberServicesSubmenu = document.getElementById('memberServicesSubmenu');
            if (memberServicesToggle && memberServicesSubmenu) {
                memberServicesToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    memberServicesSubmenu.classList.toggle('show');
                });
            }
            // FACE VALIDATION
            const virtualIdLink = document.getElementById('virtualIdLink');
            const faceValidationModalEl = document.getElementById('faceValidationModal');
            const storedFacePreview = document.getElementById('storedFacePreview');
            const videoInput = document.getElementById('videoInput');
            const validateFaceBtn = document.getElementById('validateFaceBtn');
            const faceValidationResult = document.getElementById('faceValidationResult');
            const userFaceCanvas = document.getElementById('userFaceCanvas');

            if (virtualIdLink) {
                virtualIdLink.addEventListener('click', async function(e) {
                    e.preventDefault();

                    // Generate a stronger, random 12-character password
                    function generatePassword(length = 12) {
                        const charset =
                            "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+";
                        let retVal = "";
                        const randomValues = new Uint32Array(length);
                        window.crypto.getRandomValues(randomValues);
                        for (let i = 0; i < length; i++) {
                            retVal += charset[randomValues[i] % charset.length];
                        }
                        return retVal;
                    }

                    const pdfPassword = generatePassword();
                    const userId = virtualIdLink.getAttribute('data-user-id');
                    const downloadUrl =
                        `backend/models/generate_virtual_id.php?user_id=${userId}&pdf_password=${encodeURIComponent(pdfPassword)}`;

                    // Set the generated PDF password in the PDF Password Modal
                    document.getElementById('pdfPasswordText').textContent =
                        "Your PDF password is: " + pdfPassword;

                    // Show the PDF Password Modal
                    const pdfModalEl = document.getElementById('pdfPasswordModal');
                    const pdfModal = new bootstrap.Modal(pdfModalEl);
                    pdfModal.show();

                    // When the PDF Password Modal is closed, redirect to the download URL
                    pdfModalEl.addEventListener('hidden.bs.modal', () => {
                        window.location.href = downloadUrl;
                    }, {
                        once: true
                    });
                });
            }
        });
    </script>