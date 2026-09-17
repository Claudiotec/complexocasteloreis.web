@echo off
echo ========================================
echo CRIANDO MÓDULOS CLIENTES E PRODUTOS
echo ========================================
echo.

cd C:\xampp\htdocs\softgest_web\modules

echo Criando módulo Clientes...
mkdir clientes 2>nul

echo Criando módulo Produtos...
mkdir produtos 2>nul

echo.
echo ========================================
echo ✅ MÓDULOS CRIADOS!
echo ========================================
echo.
echo Acesse:
echo - Clientes: http://localhost/softgest_web/modules/clientes/
echo - Produtos: http://localhost/softgest_web/modules/produtos/
pause