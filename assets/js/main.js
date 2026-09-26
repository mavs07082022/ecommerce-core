document.addEventListener('DOMContentLoaded', () => {
    // Auto-hide alerts
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(a => {
            a.style.transition = 'opacity 0.4s, transform 0.4s';
            a.style.opacity = '0';
            a.style.transform = 'translateY(-6px)';
            setTimeout(() => a.remove(), 400);
        });
    }, 4500);

    // Mobile sidebar toggle
    const menuBtn = document.getElementById('menuToggle');
    const sidebar = document.querySelector('.sidebar');
    if (menuBtn && sidebar) {
        menuBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
    }
});