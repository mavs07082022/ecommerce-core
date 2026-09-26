        <footer class="app-footer">
            &copy; <?= date('Y') ?> E-Commerce Core Operations. All rights reserved.
        </footer>
    </div><!-- /.main-wrapper -->
</div><!-- /.app-layout -->

<script>
function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('sidebarBackdrop').classList.add('show');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarBackdrop').classList.remove('show');
}
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(a => {
        a.style.transition = 'opacity 0.4s, transform 0.4s';
        a.style.opacity = '0';
        a.style.transform = 'translateY(-6px)';
        setTimeout(() => a.remove(), 400);
    });
}, 4500);
</script>
</body>
</html>