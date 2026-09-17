            </div>
        </main>
    </div>
    
    <script>
        // Toggle sidebar no mobile
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            overlay.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:999;';
            document.body.appendChild(overlay);
            
            // Fechar sidebar ao clicar fora
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('open');
                overlay.style.display = 'none';
            });
            
            // Abrir/fechar sidebar no mobile
            document.querySelector('.sidebar-toggle')?.addEventListener('click', function() {
                sidebar.classList.toggle('open');
                overlay.style.display = sidebar.classList.contains('open') ? 'block' : 'none';
            });
        });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>