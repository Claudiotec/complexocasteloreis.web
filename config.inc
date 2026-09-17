<?php
/*
 * phpMyAdmin - Sem Senha (Desenvolvimento Local)
 */

$i = 0;
$i++;

/* ========== CONFIGURAÇÃO PRINCIPAL ========== */
$cfg['Servers'][$i]['auth_type'] = 'config';
$cfg['Servers'][$i]['user'] = 'root';
$cfg['Servers'][$i]['password'] = '';
$cfg['Servers'][$i]['AllowNoPassword'] = true;

/* ========== CONEXÃO COM O SERVIDOR ========== */
$cfg['Servers'][$i]['host'] = 'localhost';
$cfg['Servers'][$i]['port'] = '3306';
$cfg['Servers'][$i]['socket'] = '';
$cfg['Servers'][$i]['connect_type'] = 'tcp';
$cfg['Servers'][$i]['extension'] = 'mysqli';
$cfg['Servers'][$i]['compress'] = false;

/* ========== CHAVE DE CRIPTOGRAFIA ========== */
$cfg['blowfish_secret'] = 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6';

/* ========== IDIOMA ========== */
$cfg['DefaultLang'] = 'pt_BR';
$cfg['ServerDefault'] = 1;

/* ========== SEGURANÇA ========== */
$cfg['LoginCookieValidity'] = 14400;
$cfg['LoginCookieStore'] = 0;
$cfg['LoginCookieDeleteAll'] = true;
$cfg['ShowPhpInfo'] = false;
$cfg['ShowChgPassword'] = true;
$cfg['ShowCreateDb'] = true;
$cfg['UploadDir'] = '';
$cfg['SaveDir'] = '';
$cfg['PmaNoRelation_DisableWarning'] = true;

/* ========== FINAL ========== */
?>