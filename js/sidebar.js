document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarCollapse');

    // Function to update the toggle button's left position based on sidebar state
    function updateTogglePosition() {
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

    // Toggle submenu for Member Services
    const memberToggle = document.getElementById('toggleMemberServices');
    const memberSubmenu = document.getElementById('memberServicesSubmenu');
    const memberArrow = document.getElementById('memberServicesArrow');
    memberToggle.addEventListener('click', function() {
        if (memberSubmenu.style.display === 'block') {
            memberSubmenu.style.display = 'none';
            memberArrow.innerHTML = '&darr;';
        } else {
            memberSubmenu.style.display = 'block';
            memberArrow.innerHTML = '&uarr;';
        }
    });
});
