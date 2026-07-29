/**
 * Main JS for Prototype Navigation and Role Management
 */

document.addEventListener('DOMContentLoaded', () => {
    initNavigation();
    initModals();
});

function initNavigation() {
    // Check if there's a stored role
    const userRole = localStorage.getItem('userRole');

    // Logic to highlight active menu based on URL
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-link');

    navLinks.forEach(link => {
        if (link.getAttribute('href') && currentPath.includes(link.getAttribute('href').replace('../', '').replace('./', ''))) {
            // Remove active from others
            navLinks.forEach(l => l.classList.remove('active'));
            link.classList.add('active');
        }
    });

    // Sidebar Toggle
    const toggleBtn = document.getElementById('toggleSidebar');
    const sidebar = document.getElementById('sidebar');

    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            const isCollapsed = sidebar.classList.contains('collapsed');
            sidebar.style.width = isCollapsed ? '80px' : '280px';

            // Toggle icon
            const icon = toggleBtn.querySelector('i');
            if (isCollapsed) {
                icon.setAttribute('data-lucide', 'chevron-right');
            } else {
                icon.setAttribute('data-lucide', 'chevron-left');
            }
            lucide.createIcons();
        });
    }

    // Handle logout
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', (e) => {
            e.preventDefault();
            localStorage.removeItem('userRole');
            localStorage.removeItem('userName');
            window.location.href = '../index.html';
        });
    }
}

function initModals() {
    // Add Skill Functionality
    const addSkillBtn = document.getElementById('addSkillBtn');
    const skillsContainer = document.getElementById('skillsContainer');

    if (addSkillBtn && skillsContainer) {
        addSkillBtn.addEventListener('click', () => {
            const skillName = prompt('Digite a nova habilidade:');
            if (skillName && skillName.trim() !== '') {
                const badge = document.createElement('span');
                badge.className = 'badge bg-primary bg-opacity-10 text-primary p-2 animate-fade-in';
                badge.innerHTML = `${skillName} <i data-lucide="x" class="ms-1" style="width: 12px; cursor: pointer;" onclick="this.parentElement.remove()"></i>`;
                skillsContainer.insertBefore(badge, addSkillBtn);
                lucide.createIcons();
            }
        });
    }
}

// Global functions for prototype simulation
window.simulateLogin = (role, name) => {
    localStorage.setItem('userRole', role);
    localStorage.setItem('userName', name);

    let redirectUrl = '';
    switch (role) {
        case 'aluno': redirectUrl = 'pages/aluno-perfil.html'; break;
        case 'empresa': redirectUrl = 'pages/empresa-busca.html'; break;
        case 'admin': redirectUrl = 'pages/admin-dashboard.html'; break;
        default: redirectUrl = 'index.html';
    }

    window.location.href = redirectUrl;
};

// Helper to load templates if needed (simple version)
window.includeSidebar = (role) => {
    // For a real SPA we'd use components, for this prototype we can inject HTML via JS 
    // or keep it static in each file for high-fidelity performance.
    // Given the "multiple files" requirement, static or partially-static is better.
};
