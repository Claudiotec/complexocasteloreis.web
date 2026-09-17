<?php
// ============================================
// NAVEGAÇÃO DO MÓDULO DE CONTABILIDADE
// ============================================
?>
<div style="background: white; border-radius: 12px; padding: 15px 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); border: 1px solid #eef2f7;">
    <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
        <a href="index.php" style="text-decoration: none; color: #1a2332; font-weight: 600; padding: 6px 14px; border-radius: 6px; <?= strpos($_SERVER['REQUEST_URI'], 'contabilidade/index.php') !== false ? 'background: #f5d76e20;' : '' ?>">
            📊 Dashboard
        </a>
        <a href="lancamentos.php" style="text-decoration: none; color: #1a2332; font-weight: 600; padding: 6px 14px; border-radius: 6px; <?= strpos($_SERVER['REQUEST_URI'], 'lancamentos.php') !== false ? 'background: #f5d76e20;' : '' ?>">
            📝 Lançamentos
        </a>
        <a href="diario.php" style="text-decoration: none; color: #1a2332; font-weight: 600; padding: 6px 14px; border-radius: 6px;">
            📖 Diário
        </a>
        <a href="razao.php" style="text-decoration: none; color: #1a2332; font-weight: 600; padding: 6px 14px; border-radius: 6px;">
            📚 Razão
        </a>
        <a href="balanco.php" style="text-decoration: none; color: #1a2332; font-weight: 600; padding: 6px 14px; border-radius: 6px; <?= strpos($_SERVER['REQUEST_URI'], 'balanco.php') !== false ? 'background: #f5d76e20;' : '' ?>">
            ⚖️ Balanço
        </a>
        <a href="dre.php" style="text-decoration: none; color: #1a2332; font-weight: 600; padding: 6px 14px; border-radius: 6px; <?= strpos($_SERVER['REQUEST_URI'], 'dre.php') !== false ? 'background: #f5d76e20;' : '' ?>">
            📈 DRE
        </a>
        <a href="plano_contas.php" style="text-decoration: none; color: #1a2332; font-weight: 600; padding: 6px 14px; border-radius: 6px;">
            📋 Plano de Contas
        </a>
        <?php if ($isAdmin ?? false): ?>
        <a href="periodos.php" style="text-decoration: none; color: #1a2332; font-weight: 600; padding: 6px 14px; border-radius: 6px;">
            📅 Períodos
        </a>
        <?php endif; ?>
    </div>
</div>