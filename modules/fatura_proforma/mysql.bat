@echo off
title SoftGest Web - Importar Banco Atualizado
color 0A

echo ========================================
echo   IMPORTAR BANCO DE DADOS ATUALIZADO
echo ========================================
echo.

echo ATENÇÃO: Esta ação irá recriar todo o banco de dados!
echo Todos os dados existentes serão perdidos!
echo.
echo Deseja continuar?
echo.
echo [1] Sim, recriar banco
echo [2] Não, sair
echo.
set /p opcao="Opção: "

if "%opcao%"=="1" goto continuar
if "%opcao%"=="2" exit
goto menu

:continuar
cls
echo ========================================
echo   RECRIANDO BANCO DE DADOS
echo ========================================
echo.

echo [1] Removendo banco antigo...
mysql -u root -p -e "DROP DATABASE IF EXISTS softgest_db;"
echo.

echo [2] Criando novo banco...
mysql -u root -p -e "CREATE DATABASE softgest_db CHARACTER SET utf8 COLLATE utf8_general_ci;"
echo.

echo [3] Importando dados...
mysql -u root -p softgest_db < sql\database.sql
echo.

echo [4] Verificando estrutura...
mysql -u root -p -e "USE softgest_db; SHOW TABLES;"
echo.

echo ========================================
echo   ✅ BANCO DE DADOS ATUALIZADO!
echo ========================================
echo.
echo Tabelas criadas:
echo - usuarios
echo - empresa
echo - clientes
echo - produtos
echo - movimentacoes_estoque
echo - faturas_proforma (com data_envio)
echo - fatura_proforma_itens
echo - faturas_recibo
echo - funcionarios
echo - folha_pagamento
echo - plano_marketing
echo - correspondencias
echo - modelos_mensagem
echo.
echo Acesse: http://localhost/softgest_web/
pause