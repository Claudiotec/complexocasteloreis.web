<?php
// includes/mode_switcher.php
// Alternador de Modo (Público/Local)

require_once '../config/app_modes.php';

$modo_atual = getModoAtual();
$modos = getModosDisponiveis();
?>

<div class="mode-switcher" style="
    background: <?= APP_MODE_COLOR ?>15;
    border: 2px solid <?= APP_MODE_COLOR ?>;
    border-radius: 12px;
    padding: 8px 15px;
    display: flex;
    align-items: center;
    gap: 15px;
    margin: 10px 0;
">
    <div style="display: flex; align-items: center; gap: 10px;">
        <span style="font-size: 20px;"><?= APP_MODE_ICON ?></span>
        <div>
            <span style="font-weight: 600; color: <?= APP_MODE_COLOR ?>;">
                <?= APP_MODE_NAME ?>
            </span>
            <span style="
                background: <?= APP_MODE_COLOR ?>;
                color: white;
                padding: 2px 10px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 600;
                margin-left: 8px;
            ">
                <?= APP_MODE_BADGE ?>
            </span>
        </div>
    </div>
    
    <div style="display: flex; gap: 5px; border-left: 1px solid rgba(255,255,255,0.2); padding-left: 10px;">
        <?php foreach ($modos as $modo): ?>
            <a href="?modo=<?= $modo['key'] ?>" 
               style="
                   padding: 4px 12px;
                   border-radius: 6px;
                   text-decoration: none;
                   font-size: 13px;
                   font-weight: 500;
                   background: <?= $modo_atual['modo'] === $modo['key'] ? $modo['color'] : 'transparent' ?>;
                   color: <?= $modo_atual['modo'] === $modo['key'] ? 'white' : $modo['color'] ?>;
                   border: 1px solid <?= $modo['color'] ?>;
                   transition: all 0.3s;
               "
               onmouseover="this.style.transform='scale(1.05)'" 
               onmouseout="this.style.transform='scale(1)'">
                <?= $modo['icon'] ?> <?= $modo['badge'] ?>
            </a>
        <?php endforeach; ?>
    </div>
    
    <!-- Informações de conexão -->
    <div style="margin-left: auto; font-size: 12px; color: #94a3b8;">
        <span>🔄 <?= DB_HOST ?></span>
        <span style="margin-left: 10px;">📁 <?= DB_NAME ?></span>
    </div>
</div>

<style>
.mode-switcher {
    flex-wrap: wrap;
}
@media (max-width: 768px) {
    .mode-switcher {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }
    .mode-switcher > div:last-child {
        margin-left: 0 !important;
        justify-content: center;
    }
}
</style>