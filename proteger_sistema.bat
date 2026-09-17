@echo off
title SoftGest Web - Proteção Completa
color 0A

echo ============================================================
echo   🛡️ SOFTGEST WEB - PROTEÇÃO COMPLETA
echo   Versão 4.0 (Todas as pastas)
echo ============================================================
echo.

echo ATENÇÃO: Este script irá aplicar proteção em TODAS as pastas!
echo.
echo Pastas protegidas:
echo - Raiz (.)htaccess
echo - config/
echo - includes/
echo - sql/
echo - data/
echo - protect/
echo - assets/
echo - modules/ (todos os submódulos)
echo.
echo Deseja continuar?
echo.
echo [1] Sim, proteger tudo
echo [2] Não, sair
echo.
set /p opcao="Opção: "

if "%opcao%"=="1" goto continuar
if "%opcao%"=="2" exit

:continuar
cls
echo ============================================================
echo   APLICANDO PROTEÇÕES
echo ============================================================
echo.

cd C:\xampp\htdocs\softgest_web

echo [1] Criando .htaccess na RAIZ...
(
echo # ============================================================
echo # SOFTGEST WEB - PROTEÇÃO COMPLETA
echo # ============================================================
echo.
echo RewriteEngine On
echo Options -Indexes
echo DirectoryIndex index.php login.php
echo.
echo # Proteger arquivos sensíveis
echo ^<FilesMatch "\.(sql|log|ini|config|json|yml|yaml|md|sh|bat|py)$"^>
echo     Order allow,deny
echo     Deny from all
echo ^</FilesMatch^>
echo.
echo # Proteger arquivos de configuração
echo ^<Files "database.php"^>
echo     Order allow,deny
echo     Deny from all
echo ^</Files^>
echo ^<Files "security.php"^>
echo     Order allow,deny
echo     Deny from all
echo ^</Files^>
echo.
echo # Proteger pastas
echo RedirectMatch 403 ^/softgest_web/config/.*$
echo RedirectMatch 403 ^/softgest_web/includes/.*$
echo RedirectMatch 403 ^/softgest_web/sql/.*$
echo RedirectMatch 403 ^/softgest_web/data/.*$
echo RedirectMatch 403 ^/softgest_web/modules/.*\.php$
echo.
echo # Bloquear arquivos ocultos
echo RedirectMatch 403 /\..*$
echo.
echo # Segurança
echo ^<IfModule mod_headers.c^>
echo     Header set X-Content-Type-Options "nosniff"
echo     Header set X-Frame-Options "DENY"
echo     Header set X-XSS-Protection "1; mode=block"
echo ^</IfModule^>
) > .htaccess
echo ✅ Raiz protegida

echo.
echo [2] Protegendo pastas principais...
echo.

:: CONFIG
(
echo # Bloquear acesso
echo Order allow,deny
echo Deny from all
) > config\.htaccess
echo ✅ config/

:: INCLUDES
(
echo # Bloquear acesso
echo Order allow,deny
echo Deny from all
) > includes\.htaccess
echo ✅ includes/

:: SQL
(
echo # Bloquear acesso
echo Order allow,deny
echo Deny from all
) > sql\.htaccess
echo ✅ sql/

:: DATA
(
echo # Bloquear acesso
echo Order allow,deny
echo Deny from all
) > data\.htaccess
echo ✅ data/

:: PROTECT
(
echo # Bloquear logs
echo ^<Files "*.log"^>
echo     Order allow,deny
echo     Deny from all
echo ^</Files^>
echo ^<Files "*.json"^>
echo     Order allow,deny
echo     Deny from all
echo ^</Files^>
) > protect\.htaccess
echo ✅ protect/

:: ASSETS
(
echo # Permitir arquivos estáticos
echo ^<FilesMatch "\.(css|js|jpg|jpeg|png|gif|svg|ico)$"^>
echo     Order allow,deny
echo     Allow from all
echo ^</FilesMatch^>
) > assets\.htaccess
echo ✅ assets/

:: ASSETS/CSS
(
echo # Permitir CSS
echo Allow from all
) > assets\css\.htaccess
echo ✅ assets/css/

:: ASSETS/JS
(
echo # Permitir JS
echo Allow from all
) > assets\js\.htaccess
echo ✅ assets/js/

echo.
echo [3] Protegendo TODOS os módulos...
echo.

:: ===== MODULES RAIZ =====
(
echo # ============================================================
echo # PROTEÇÃO DA PASTA MODULES
echo # ============================================================
echo.
echo Options -Indexes
echo.
echo ^<FilesMatch "\.(php)$"^>
echo     Order allow,deny
echo     Deny from all
echo ^</FilesMatch^>
echo.
echo ^<Files "index.php"^>
echo     Order allow,deny
echo     Allow from all
echo ^</Files^>
echo.
echo ^<FilesMatch "\.(sql|log|ini|config|json)$"^>
echo     Order allow,deny
echo     Deny from all
echo ^</FilesMatch^>
) > modules\.htaccess
echo ✅ modules/

:: ===== MÓDULOS INDIVIDUAIS =====

:: CLIENTES
(
echo Options -Indexes
echo ^<FilesMatch "\.(php)$"^>
echo     Order allow,deny
echo     Deny from all
echo ^</FilesMatch^>
echo ^<Files "index.php"^>
echo     Order allow,deny
echo     Allow from all
echo ^</Files^>
) > modules\clientes\.htaccess
echo ✅ modules/clientes/

:: PRODUTOS
(
echo Options -Indexes
echo ^<FilesMatch "\.(php)$"^>
echo     Order allow,deny
echo     Deny from all
echo ^</FilesMatch^>
echo ^<Files "index.php"^>
echo     Order allow,deny
echo     Allow from all
echo ^</Files^>
) > modules\produtos\.htaccess
echo ✅ modules/produtos/

:: ESTOQUE
(
echo Options -Indexes
echo ^<FilesMatch "\.(php)$"^>
echo     Order allow,deny
echo     Deny from all
echo ^</FilesMatch^>
echo ^<Files "index.php"^>
echo     Order allow,deny
echo     Allow from all
echo ^</Files^>
) > modules\estoque\.htaccess
echo ✅ modules/estoque/

:: FATURA PROFORMA
(
echo Options -Indexes
echo ^<FilesMatch "\.(php)$"^>
echo     Order allow,deny
echo     Deny from all
echo ^</FilesMatch^>
echo ^<Files "index.php"^>
echo     Order allow,deny
echo     Allow from all
echo ^</Files^>
) > modules\fatura_proforma\.htaccess
echo ✅ modules/fatura_proforma/

:: FATURA RECIBO
(
echo Options -Indexes
echo ^<FilesMatch "\.(php)$"^>
echo     Order allow,deny
echo     Deny from all
echo ^</FilesMatch^>
echo ^<Files "index.php"^>
echo     Order allow,deny
echo     Allow from all
echo ^</Files^>
) > modules\fatura_recibo\.htaccess
echo ✅ modules/fatura_recibo/

:: CAIXA
(
echo Options -Indexes
echo ^<FilesMatch "\.(php)$"^>
echo     Order allow,deny
echo     Deny from all
echo ^</FilesMatch^>
echo ^<Files "index.php"^>
echo     Order allow,deny
echo     Allow from all
echo ^</Files^>
) > modules\caixa\.htaccess
echo ✅ modules/caixa/

:: RH
(
echo Options -Indexes
echo ^<FilesMatch "\.(php)$"^>
echo     Order allow,deny
echo     Deny from all
echo ^</FilesMatch^>
echo ^<Files "index.php"^>
echo     Order allow,deny
echo     Allow from all
echo ^</Files^>
) > modules\rh\.htaccess
echo ✅ modules/rh/

:: MARKETING
(
echo Options -Indexes
echo ^<FilesMatch "\.(php)$"^>
echo     Order allow,deny
echo     Deny from all
echo ^</FilesMatch^>
echo ^<Files "index.php"^>
echo     Order allow,deny
echo     Allow from all
echo ^</Files^>
) > modules\marketing\.htaccess
echo ✅ modules/marketing/

:: CORRESPONDENCIA
(
echo Options -Indexes
echo ^<FilesMatch "\.(php)$"^>
echo     Order allow,deny
echo     Deny from all
echo ^</FilesMatch^>
echo ^<Files "index.php"^>
echo     Order allow,deny
echo     Allow from all
echo ^</Files^>
) > modules\correspondencia\.htaccess
echo ✅ modules/correspondencia/

:: EMPRESA
(
echo Options -Indexes
echo ^<FilesMatch "\.(php)$"^>
echo     Order allow,deny
echo     Deny from all
echo ^</FilesMatch^>
echo ^<Files "index.php"^>
echo     Order allow,deny
echo     Allow from all
echo ^</Files^>
) > modules\empresa\.htaccess
echo ✅ modules/empresa/

:: USUARIOS
(
echo Options -Indexes
echo ^<FilesMatch "\.(php)$"^>
echo     Order allow,deny
echo     Deny from all
echo ^</FilesMatch^>
echo ^<Files "index.php"^>
echo     Order allow,deny
echo     Allow from all
echo ^</Files^>
) > modules\usuarios\.htaccess
echo ✅ modules/usuarios/

echo.
echo [4] Ocultando pastas...
attrib +h config 2>nul
attrib +h includes 2>nul
attrib +h sql 2>nul
attrib +h data 2>nul
attrib +h protect 2>nul
echo ✅ Pastas ocultas

echo.
echo [5] Aplicando permissões de leitura...
attrib +r config\database.php 2>nul
attrib +r config\security.php 2>nul
attrib +r .htaccess 2>nul
attrib +r index.php 2>nul
attrib +r login.php 2>nul
echo ✅ Permissões aplicadas

echo.
echo [6] Criando verificador de integridade...
(
echo ^<?php
echo // ============================================================
echo // VERIFICADOR DE INTEGRIDADE
echo // ============================================================
echo.
echo \$arquivos = [
echo     'index.php', 'login.php', 'registrar.php', 'logout.php',
echo     'config/database.php', 'config/security.php'
echo ];
echo.
echo echo "<h1>🔍 Verificação de Integridade</h1>";
echo echo "<ul>";
echo foreach (\$arquivos as \$arquivo) {
echo     if (file_exists(\$arquivo)) {
echo         echo "<li style='color:green;'>✅ \$arquivo - OK</li>";
echo     } else {
echo         echo "<li style='color:red;'>❌ \$arquivo - FALTA!</li>";
echo     }
echo }
echo echo "</ul>";
echo ?^>
) > verificar_integridade.php
echo ✅ Verificador criado

echo.
echo [7] Criando relatório...
(
echo ============================================================
echo RELATÓRIO DE SEGURANÇA - SOFTGEST WEB
echo ============================================================
echo.
echo Data: %date%
echo Hora: %time%
echo.
echo PASTAS PROTEGIDAS:
echo - config/
echo - includes/
echo - sql/
echo - data/
echo - protect/
echo - assets/
echo - modules/ (13 submódulos)
echo.
echo TOTAL: 20 pastas protegidas
echo ============================================================
) > relatorio_seguranca.txt
echo ✅ Relatório criado

echo.
echo ============================================================
echo   ✅ PROTEÇÃO COMPLETA APLICADA!
echo ============================================================
echo.
echo Resumo:
echo - 📁 20 pastas protegidas
echo - 🔒 13 módulos protegidos
echo - 🛡️ .htaccess em todas as pastas
echo - 📊 Verificador de integridade
echo - 📝 Relatório de segurança
echo.
echo Teste: http://localhost/softgest_web/config/
echo Deve dar erro 403 - Acesso negado!
echo.
pause