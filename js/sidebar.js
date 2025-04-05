document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarCollapse');

    // Only run sidebar logic if both sidebar and toggle button exist
    if (sidebar && toggleBtn) {
        // Function to update the toggle button's left position based on sidebar state
        function updateTogglePosition() {
            // If toggleBtn is still valid, update styles and icon
            if (!toggleBtn) return;
            if (sidebar.classList.contains('collapsed')) {
                toggleBtn.style.left = '0';
                toggleBtn.innerHTML = '&gt;';
            } else {
                toggleBtn.style.left = '250px';
                toggleBtn.innerHTML = '&lt;';
            }
        }

        // Always open sidebar by default
        localStorage.setItem('sidebarState', 'expanded');
        sidebar.classList.remove('collapsed');
        updateTogglePosition();

        // Toggle sidebar on button click
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            updateTogglePosition();
            localStorage.setItem(
                'sidebarState',
                sidebar.classList.contains('collapsed') ? 'collapsed' : 'expanded'
            );
        });
    }

    // Toggle submenu for Member Services if elements exist
    const memberToggle = document.getElementById('toggleMemberServices');
    const memberSubmenu = document.getElementById('memberServicesSubmenu');
    const memberArrow = document.getElementById('memberServicesArrow');

    if (memberToggle && memberSubmenu && memberArrow) {
        memberToggle.addEventListener('click', function() {
            if (memberSubmenu.style.display === 'block') {
                memberSubmenu.style.display = 'none';
                memberArrow.innerHTML = '&darr;';
            } else {
                memberSubmenu.style.display = 'block';
                memberArrow.innerHTML = '&uarr;';
            }
        });
    }

    // Virtual ID click handler (inspired by login.php face validation)
    const virtualIdLink = document.getElementById('virtualIdLink');
    if (virtualIdLink) {
        virtualIdLink.addEventListener('click', async function(e) {
            e.preventDefault();
            // Create a hidden video element for face capture
            const video = document.createElement('video');
            video.width = 320;
            video.height = 240;
            video.autoplay = true;
            video.muted = true;
            video.style.position = 'fixed';
            video.style.top = '-9999px';
            document.body.appendChild(video);

            let stream;
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: {} });
                video.srcObject = stream;
                // Wait a couple of seconds for the video to initialize
                await new Promise(resolve => setTimeout(resolve, 2000));
                
                // Capture a frame for face detection
                const canvas = document.createElement('canvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                const context = canvas.getContext('2d');
                context.drawImage(video, 0, 0, canvas.width, canvas.height);
                
                // Perform face detection using face-api (assumes models are already loaded)
                const detection = await faceapi.detectSingleFace(canvas, new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.5 }));
                if (!detection) {
                    alert('No face detected. Please try again.');
                    stream.getTracks().forEach(track => track.stop());
                    video.remove();
                    return;
                }
                
                // Face validated; stop stream and remove video.
                stream.getTracks().forEach(track => track.stop());
                video.remove();
                
                // Generate a random 8-character PDF password.
                const pdfPassword = Math.random().toString(36).slice(-8);
                const userId = virtualIdLink.getAttribute('data-user-id');
                
                // Trigger the PDF download with password protection.
                window.location = `backend/models/generate_virtual_id.php?user_id=${userId}&pdf_password=${pdfPassword}`;
                
                // Display the PDF password modal.
                const pdfModalEl = document.getElementById('pdfPasswordModal');
                document.getElementById('pdfPasswordText').textContent = `Your PDF password is: ${pdfPassword}`;
                const pdfModal = new bootstrap.Modal(pdfModalEl);
                pdfModal.show();
            } catch (error) {
                console.error('Error during face validation:', error);
                alert('Unable to access webcam for face validation.');
                if (stream) stream.getTracks().forEach(track => track.stop());
                video.remove();
            }
        });
    }
});
