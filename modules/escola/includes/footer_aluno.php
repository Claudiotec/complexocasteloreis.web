<?php
// modules/escola/includes/footer_aluno.php
// Template Footer para o Painel do Aluno
?>
        </div>
        <!-- Fim do .content-aluno -->
    </main>
</div>

<!-- ============================================
     SCRIPTS GLOBAIS
     ============================================ -->
<script>
    // Toggle Sidebar Mobile
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebarAluno');
        const overlay = document.getElementById('overlayAluno');
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
    }

    // Fechar sidebar ao clicar fora
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebarAluno');
        const toggle = document.querySelector('.menu-toggle');
        if (sidebar && toggle) {
            if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                sidebar.classList.remove('open');
                const overlay = document.getElementById('overlayAluno');
                if (overlay) overlay.classList.remove('active');
            }
        }
    });

    // Relógio em tempo real
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
</script>
</body>
</html>