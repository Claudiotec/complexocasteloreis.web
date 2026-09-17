    <script src="<?= SITE_URL ?>assets/js/script.js"></script>
    <script>
        // ===== TOGGLE SIDEBAR =====
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (sidebar) {
                sidebar.classList.toggle('open');
                if (overlay) overlay.classList.toggle('active');
            }
        }

        function closeSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (sidebar) sidebar.classList.remove('open');
            if (overlay) overlay.classList.remove('active');
        }

        // ===== RELÓGIO =====
        function updateDateTime() {
            const now = new Date();
            const options = { 
                weekday: 'short', 
                day: '2-digit', 
                month: 'short', 
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            };
            const el = document.getElementById('currentDateTime');
            if (el) {
                el.textContent = now.toLocaleDateString('pt-BR', options);
            }
        }

        updateDateTime();
        setInterval(updateDateTime, 1000);

        // ===== FECHAR SIDEBAR FORA =====
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const toggle = document.querySelector('.menu-toggle');
            if (window.innerWidth <= 992 && sidebar && toggle) {
                if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                    closeSidebar();
                }
            }
        });

        // ===== REDIMENSIONAR =====
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992) {
                closeSidebar();
                const nav = document.querySelector('.top-bar-nav');
                if (nav) nav.classList.remove('open');
            }
        });
    </script>
</body>
</html>