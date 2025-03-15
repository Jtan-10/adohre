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
});
