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
            localStorage.setItem('sidebarState', sidebar.classList.contains('collapsed') ? 'collapsed' : 'expanded');
        });
    }

    // Toggle submenu for Member Services
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

    // Virtual ID click handler: show the face validation modal
    const virtualIdLink = document.getElementById('virtualIdLink');
    if (virtualIdLink) {
        virtualIdLink.addEventListener('click', async function(e) {
            e.preventDefault();
            const faceValidationModalEl = document.getElementById('faceValidationModal');
            if (!faceValidationModalEl) {
                console.error("Face validation modal element not found!");
                return;
            }
            const videoInput = document.getElementById('videoInput');
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: {} });
                videoInput.srcObject = stream;
            } catch (error) {
                console.error("Webcam access error:", error);
                alert('Unable to access webcam for face validation. Please check permissions.');
                // Optionally, you can still show the modal so users see an error message.
            }
            const faceValidationModal = new bootstrap.Modal(faceValidationModalEl);
            faceValidationModal.show();
        });
    }

    // Face validation and PDF generation logic after clicking Validate Face
    // Ensure that the face-api models are already loaded and that a reference descriptor is available
    const validateFaceBtn = document.getElementById('validateFaceBtn');
    if (validateFaceBtn) {
        validateFaceBtn.addEventListener('click', async function() {
            const video = document.getElementById('videoInput');
            const canvas = document.getElementById('userFaceCanvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            // Detect face with landmarks and descriptor
            const detection = await faceapi
                .detectSingleFace(canvas, new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.5 }))
                .withFaceLandmarks()
                .withFaceDescriptor();
            
            const resultParagraph = document.getElementById('faceValidationResult');
            if (!detection) {
                resultParagraph.innerText = 'No face detected. Please try again.';
                return;
            }
            // Ensure that a stored reference descriptor is available.
            if (typeof referenceDescriptor === 'undefined') {
                resultParagraph.innerText = 'Reference face not available. Please contact support.';
                return;
            }
            const distance = faceapi.euclideanDistance(detection.descriptor, referenceDescriptor);
            console.log('Distance:', distance);
            const threshold = 0.6;
            if (distance < threshold) {
                resultParagraph.innerText = 'Face matched successfully!';
                // Stop the webcam stream
                const stream = video.srcObject;
                if (stream) {
                    stream.getTracks().forEach(track => track.stop());
                }
                // Generate a random 8-character PDF password
                const pdfPassword = Math.random().toString(36).slice(-8);
                const userId = virtualIdLink.getAttribute('data-user-id');
                // Redirect to generate_virtual_id.php with the user_id and pdf_password parameters
                window.location.href = `backend/models/generate_virtual_id.php?user_id=${userId}&pdf_password=${pdfPassword}`;
            } else {
                resultParagraph.innerText = 'Face did not match. Please try again.';
            }
        });
    }
});
