<?php
/*
 * This is needed for cookie based authentication to encrypt password in
 * cookie
 */
$cfg['blowfish_secret'] = 'xampp'; /* YOU SHOULD CHANGE THIS FOR A MORE SECURE COOKIE AUTH! */

/*
 * Servers configuration
 */
$i = 0;

/*
 * First server
 */
$i++;

/* Authentication type and info */
// MUDAR para 'cookie' para permitir login de qualquer lugar
$cfg['Servers'][$i]['auth_type'] = 'cookie';
$cfg['Servers'][$i]['user'] = 'root';
$cfg['Servers'][$i]['password'] = '';
$cfg['Servers'][$i]['extension'] = 'mysqli';
$cfg['Servers'][$i]['AllowNoPassword'] = true;
$cfg['Lang'] = '';

/* MUDAR host para 0.0.0.0 ou deixar em branco para aceitar todas conexões */
$cfg['Servers'][$i]['host'] = '0.0.0.0'; // OU DEIXE EM BRANCO: ''
// OU para IP específico do servidor: $cfg['Servers'][$i]['host'] = '192.168.1.100';
$cfg['Servers'][$i]['connect_type'] = 'tcp';

// LIBERAR ACESSO REMOTO - Adicionar estas linhas:
$cfg['Servers'][$i]['AllowDeny']['order'] = 'allow,deny';
$cfg['Servers'][$i]['AllowDeny']['rules'] = array(
    'allow %'  // Permite todos os IPs
);

// OU para permitir apenas rede local:
/*
$cfg['Servers'][$i]['AllowDeny']['rules'] = array(
    'allow 192.168.1.0/24',
    'allow 127.0.0.1',
    'deny %'
);
*/

/* User for advanced features */
$cfg['Servers'][$i]['controluser'] = 'pma';
$cfg['Servers'][$i]['controlpass'] = '';

/* Advanced phpMyAdmin features */
$cfg['Servers'][$i]['pmadb'] = 'phpmyadmin';
$cfg['Servers'][$i]['bookmarktable'] = 'pma__bookmark';
$cfg['Servers'][$i]['relation'] = 'pma__relation';
$cfg['Servers'][$i]['table_info'] = 'pma__table_info';
$cfg['Servers'][$i]['table_coords'] = 'pma__table_coords';
$cfg['Servers'][$i]['pdf_pages'] = 'pma__pdf_pages';
$cfg['Servers'][$i]['column_info'] = 'pma__column_info';
$cfg['Servers'][$i]['history'] = 'pma__history';
$cfg['Servers'][$i]['designer_coords'] = 'pma__designer_coords';
$cfg['Servers'][$i]['tracking'] = 'pma__tracking';
$cfg['Servers'][$i]['userconfig'] = 'pma__userconfig';
$cfg['Servers'][$i]['recent'] = 'pma__recent';
$cfg['Servers'][$i]['table_uiprefs'] = 'pma__table_uiprefs';
$cfg['Servers'][$i]['users'] = 'pma__users';
$cfg['Servers'][$i]['usergroups'] = 'pma__usergroups';
$cfg['Servers'][$i]['navigationhiding'] = 'pma__navigationhiding';
$cfg['Servers'][$i]['savedsearches'] = 'pma__savedsearches';
$cfg['Servers'][$i]['central_columns'] = 'pma__central_columns';
$cfg['Servers'][$i]['designer_settings'] = 'pma__designer_settings';
$cfg['Servers'][$i]['export_templates'] = 'pma__export_templates';
$cfg['Servers'][$i]['favorite'] = 'pma__favorite';

/*
 * End of servers configuration
 */

// CONFIGURAÇÃO EXTRA PARA PERMITIR ACESSO REMOTO
// Forçar o phpMyAdmin a aceitar requisições de qualquer origem
$cfg['ForceSSL'] = false; // Não forçar SSL (para testes)
$cfg['PmaAbsoluteUri'] = ''; // Deixar vazio para auto-detecção

?>