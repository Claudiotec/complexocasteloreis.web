@echo off
title SoftGest Web - Remover Proteção Completa
color 0C

echo ============================================================
echo   🔓 SOFTGEST WEB - REMOVER PROTEÇÃO COMPLETA
echo   Versão 4.0
echo ============================================================
echo.

echo ATENÇÃO: Isso irá REMOVER TODAS as proteções!
echo.
echo Proteções que serão removidas:
echo - Todos os arquivos .htaccess
echo - Pastas ocultas (config, includes, sql, data, protect)
echo - Permissões de leitura
echo - Arquivos de proteção
echo.
echo Deseja continuar?
echo.
echo [1] Sim, remover todas as proteções
echo [2] Não, sair
echo.
set /p opcao="Opção: "

if "%opcao%"=="1" goto continuar
if "%opcao%"=="2" exit

:continuar
cls
echo ============================================================
echo   REMOVENDO PROTEÇÕES
echo ============================================================
echo.

cd C:\xampp\htdocs\softgest_web

echo [1] Removendo .htaccess da RAIZ...
if exist .htaccess (
    del .htaccess
    echo ✅ .htaccess raiz removido
) else (
    echo ⚠️ .htaccess raiz não encontrado
)

echo.
echo [2] Removendo .htaccess das pastas principais...
echo.

:: CONFIG
if exist config\.htaccess (
    del config\.htaccess
    echo ✅ config\.htaccess removido
) else (
    echo ⚠️ config\.htaccess não encontrado
)

:: INCLUDES
if exist includes\.htaccess (
    del includes\.htaccess
    echo ✅ includes\.htaccess removido
) else (
    echo ⚠️ includes\.htaccess não encontrado
)

:: SQL
if exist sql\.htaccess (
    del sql\.htaccess
    echo ✅ sql\.htaccess removido
) else (
    echo ⚠️ sql\.htaccess não encontrado
)

:: DATA
if exist data\.htaccess (
    del data\.htaccess
    echo ✅ data\.htaccess removido
) else (
    echo ⚠️ data\.htaccess não encontrado
)

:: PROTECT
if exist protect\.htaccess (
    del protect\.htaccess
    echo ✅ protect\.htaccess removido
) else (
    echo ⚠️ protect\.htaccess não encontrado
)

:: ASSETS
if exist assets\.htaccess (
    del assets\.htaccess
    echo ✅ assets\.htaccess removido
) else (
    echo ⚠️ assets\.htaccess não encontrado
)

:: ASSETS/CSS
if exist assets\css\.htaccess (
    del assets\css\.htaccess
    echo ✅ assets/css/.htaccess removido
) else (
    echo ⚠️ assets/css/.htaccess não encontrado
)

:: ASSETS/JS
if exist assets\js\.htaccess (
    del assets\js\.htaccess
    echo ✅ assets/js/.htaccess removido
) else (
    echo ⚠️ assets/js/.htaccess não encontrado
)

echo.
echo [3] Removendo .htaccess dos MÓDULOS...
echo.

:: MODULES RAIZ
if exist modules\.htaccess (
    del modules\.htaccess
    echo ✅ modules/.htaccess removido
) else (
    echo ⚠️ modules/.htaccess não encontrado
)

:: CLIENTES
if exist modules\clientes\.htaccess (
    del modules\clientes\.htaccess
    echo ✅ modules/clientes/.htaccess removido
) else (
    echo ⚠️ modules/clientes/.htaccess não encontrado
)

:: PRODUTOS
if exist modules\produtos\.htaccess (
    del modules\produtos\.htaccess
    echo ✅ modules/produtos/.htaccess removido
) else (
    echo ⚠️ modules/produtos/.htaccess não encontrado
)

:: ESTOQUE
if exist modules\estoque\.htaccess (
    del modules\estoque\.htaccess
    echo ✅ modules/estoque/.htaccess removido
) else (
    echo ⚠️ modules/estoque/.htaccess não encontrado
)

:: FATURA PROFORMA
if exist modules\fatura_proforma\.htaccess (
    del modules\fatura_proforma\.htaccess
    echo ✅ modules/fatura_proforma/.htaccess removido
) else (
    echo ⚠️ modules/fatura_proforma/.htaccess não encontrado
)

:: FATURA RECIBO
if exist modules\fatura_recibo\.htaccess (
    del modules\fatura_recibo\.htaccess
    echo ✅ modules/fatura_recibo/.htaccess removido
) else (
    echo ⚠️ modules/fatura_recibo/.htaccess não encontrado
)

:: CAIXA
if exist modules\caixa\.htaccess (
    del modules\caixa\.htaccess
    echo ✅ modules/caixa/.htaccess removido
) else (
    echo ⚠️ modules/caixa/.htaccess não encontrado
)

:: RH
if exist modules\rh\.htaccess (
    del modules\rh\.htaccess
    echo ✅ modules/rh/.htaccess removido
) else (
    echo ⚠️ modules/rh/.htaccess não encontrado
)

:: MARKETING
if exist modules\marketing\.htaccess (
    del modules\marketing\.htaccess
    echo ✅ modules/marketing/.htaccess removido
) else (
    echo ⚠️ modules/marketing/.htaccess não encontrado
)

:: CORRESPONDENCIA
if exist modules\correspondencia\.htaccess (
    del modules\correspondencia\.htaccess
    echo ✅ modules/correspondencia/.htaccess removido
) else (
    echo ⚠️ modules/correspondencia/.htaccess não encontrado
)

:: EMPRESA
if exist modules\empresa\.htaccess (
    del modules\empresa\.htaccess
    echo ✅ modules/empresa/.htaccess removido
) else (
    echo ⚠️ modules/empresa/.htaccess não encontrado
)

:: USUARIOS
if exist modules\usuarios\.htaccess (
    del modules\usuarios\.htaccess
    echo ✅ modules/usuarios/.htaccess removido
) else (
    echo ⚠️ modules/usuarios/.htaccess não encontrado
)

echo.
echo [4] Mostrando pastas ocultas...
attrib -h config 2>nul
attrib -h includes 2>nul
attrib -h sql 2>nul
attrib -h data 2>nul
attrib -h protect 2>nul
echo ✅ Pastas visíveis novamente

echo.
echo [5] Removendo permissões de leitura...
attrib -r config\database.php 2>nul
attrib -r config\security.php 2>nul
attrib -r .htaccess 2>nul
attrib -r index.php 2>nul
attrib -r login.php 2>nul
attrib -r registrar.php 2>nul
attrib -r logout.php 2>nul
echo ✅ Permissões removidas

echo.
echo [6] Removendo arquivos de proteção...
if exist verificar_integridade.php (
    del verificar_integridade.php
    echo ✅ verificar_integridade.php removido
) else (
    echo ⚠️ verificar_integridade.php não encontrado
)

if exist protect\protegido.php (
    del protect\protegido.php
    echo ✅ protect/protegido.php removido
) else (
    echo ⚠️ protect/protegido.php não encontrado
)

if exist relatorio_seguranca.txt (
    del relatorio_seguranca.txt
    echo ✅ relatorio_seguranca.txt removido
) else (
    echo ⚠️ relatorio_seguranca.txt não encontrado
)

echo.
echo [7] Verificando se todas as proteções foram removidas...
echo.

:: Verificar se ainda há .htaccess
set encontrou=0
for /r %%f in (.htaccess) do (
    echo ⚠️ Ainda existe: %%f
    set encontrou=1
)

if %encontrou%==0 (
    echo ✅ Nenhum .htaccess encontrado. Proteções removidas com sucesso!
) else (
    echo ⚠️ Ainda há arquivos .htaccess no sistema.
    echo    Remova manualmente se necessário.
)

echo.
echo ============================================================
echo   ✅ PROTEÇÕES REMOVIDAS COM SUCESSO!
echo ============================================================
echo.
echo Resumo da remoção:
echo - 🔓 .htaccess removidos de todas as pastas
echo - 📁 Pastas visíveis novamente
echo - 🔓 Permissões removidas
echo - 🗑️ Arquivos de proteção deletados
echo.
echo AGORA O SISTEMA ESTÁ DESPROTEGIDO!
echo.
echo Acesse: http://localhost/softgest_web/config/
echo Agora deve mostrar o conteúdo (ou erro, mas não 403)
echo.
pause