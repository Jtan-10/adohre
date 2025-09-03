<?php
require_once 'backend/db/db_connect.php';
require_once 'backend/utils/access_control.php';

// Secure session
configureSessionSecurity();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require login (same as events.php behavior)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects - ADOHRE</title>
    <link rel="icon" href="assets/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #28a745;
            --secondary-color: #2c3e50;
            --accent-color: #f8f9fa;
        }

        body {
            background: #fff;
        }

        .projects-container {
            background: var(--accent-color);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, .05);
        }

        .project-card {
            border: none;
            border-radius: 12px;
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            overflow: hidden;
            transition: transform .3s ease, box-shadow .3s ease;
        }

        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(40, 167, 69, .15);
        }

        .project-card img {
            height: 220px;
            object-fit: cover;
        }

        .section-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 1.5rem;
            position: relative;
            padding-left: 1.5rem;
        }

        .section-title::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            height: 70%;
            width: 4px;
            background: var(--primary-color);
            border-radius: 2px;
        }

        .badge-date {
            background: var(--primary-color);
            color: #fff;
            border-radius: 20px;
            padding: .35rem .75rem;
            font-size: .85rem;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>
    <?php include 'sidebar.php'; ?>

    <main class="container mt-4 mb-5">
        <div class="projects-container">
            <h2 class="section-title">Projects</h2>
            <div id="projectsList" class="row g-4">
                <!-- Projects injected here -->
            </div>
        </div>
    </main>

    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const list = document.getElementById('projectsList');
            fetch('backend/routes/content_manager.php?action=fetch_projects')
                .then(r => r.json())
                .then(data => {
                    if (!data.status) {
                        list.innerHTML = '<p class="text-danger">Failed to load projects.</p>';
                        return;
                    }
                    if (!Array.isArray(data.projects) || data.projects.length === 0) {
                        list.innerHTML = '<p class="text-muted">No projects available.</p>';
                        return;
                    }
                    const cards = data.projects.map(p => {
                        const dateStr = p.date ? new Date(p.date).toLocaleString('en-US', {
                            month: 'long',
                            year: 'numeric'
                        }) : '';
                        const statusStr = p.status ? p.status.charAt(0).toUpperCase() + p.status.slice(1) : '';
                        const endStr = p.end_date ? new Date(p.end_date).toLocaleString('en-US', {
                            month: 'long',
                            year: 'numeric'
                        }) : '';
                        const imgSrc = p.image ? ('backend/routes/decrypt_image.php?image_url=' + encodeURIComponent(p.image)) : 'assets/default-image.jpg';
                        return `
                        <div class="col-12 col-md-6 col-lg-4">
                          <div class="card project-card h-100">
                            <img src="${imgSrc}" class="card-img-top" alt="${p.title||'Project'}">
                            <div class="card-body d-flex flex-column">
                                                            ${ dateStr ? `<span class=\"badge-date mb-2\"><i class=\"fas fa-calendar-alt me-1\"></i>${dateStr}</span>` : '' }
                              <h5 class="card-title">${p.title || ''}</h5>
                                                            ${ p.partner ? `<div class=\"text-muted mb-1\"><i class=\"fas fa-handshake me-1\"></i>${p.partner}</div>` : '' }
                                                            ${ p.description ? `<p class=\"mb-2 small\">${(p.description||'').replace(/</g,'&lt;').replace(/>/g,'&gt;')}</p>` : '' }
                                                            <div class="text-muted">
                                                                ${ statusStr ? `<span class=\"me-2\">${statusStr}</span>` : '' }
                                                                ${ endStr ? `<span class=\"text-nowrap\">End: ${endStr}</span>` : '' }
                                                            </div>
                            </div>
                          </div>
                        </div>`;
                    }).join('');
                    list.innerHTML = cards;
                })
                .catch(() => list.innerHTML = '<p class="text-danger">Failed to load projects.</p>');
        });
    </script>
</body>

</html>