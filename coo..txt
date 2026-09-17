@echo off
echo ========================================
echo CORRIGINDO MÓDULOS DO SOFTGEST WEB
echo ========================================
echo.

cd C:\xampp\htdocs\softgest_web\modules

echo 1. Removendo arquivos .py...
del /s *.py 2>nul

echo 2. Criando arquivos index.php para cada módulo...

REM ======== ESTOQUE ========
echo Criando estoque/index.php...
(
echo ^<?php
echo require_once '../../config/database.php';
echo.
echo $stmt = $pdo->query("SELECT * FROM produtos ORDER BY nome");
echo $produtos = $stmt->fetchAll();
echo ?^>
echo.
echo ^<!DOCTYPE html^>
echo ^<html lang="pt-BR"^>
echo ^<head^>
echo     ^<meta charset="UTF-8"^>
echo     ^<meta name="viewport" content="width=device-width, initial-scale=1.0"^>
echo     ^<title^>Estoque - SoftGest Web^</title^>
echo     ^<link rel="stylesheet" href="../../assets/css/style.css"^>
echo ^</head^>
echo ^<body^>
echo     ^<?php include '../../includes/header.php'; ?^>
echo     ^<div class="container"^>
echo         ^<h2^>📦 Controle de Estoque^</h2^>
echo         ^<div class="actions"^>
echo             ^<a href="add.php" class="btn btn-primary"^>+ Novo Produto^</a^>
echo         ^</div^>
echo         ^<table class="table"^>
echo             ^<thead^>
echo                 ^<tr^>
echo                     ^<th^>Código^</th^>
echo                     ^<th^>Nome^</th^>
echo                     ^<th^>Qtd^</th^>
echo                     ^<th^>Preço Venda^</th^>
echo                     ^<th^>Estoque Mínimo^</th^>
echo                     ^<th^>Ações^</th^>
echo                 ^</tr^>
echo             ^</thead^>
echo             ^<tbody^>
echo                 ^<?php foreach($produtos as $produto): ?^>
echo                 ^<tr^>
echo                     ^<td^>^<?= $produto['codigo'] ?^>^</td^>
echo                     ^<td^>^<?= $produto['nome'] ?^>^</td^>
echo                     ^<td^>^<?= $produto['quantidade'] ?^>^</td^>
echo                     ^<td^>R$ ^<?= number_format($produto['preco_venda'], 2, ',', '.') ?^>^</td^>
echo                     ^<td^>^<?= $produto['estoque_minimo'] ?^>^</td^>
echo                     ^<td^>
echo                         ^<a href="edit.php?id=^<?= $produto['id'] ?^>" class="btn-small"^>Editar^</a^>
echo                         ^<a href="movimentar.php?id=^<?= $produto['id'] ?^>" class="btn-small"^>Movimentar^</a^>
echo                     ^</td^>
echo                 ^</tr^>
echo                 ^<?php endforeach; ?^>
echo             ^</tbody^>
echo         ^</table^>
echo     ^</div^>
echo     ^<?php include '../../includes/footer.php'; ?^>
echo ^</body^>
echo ^</html^>
) > estoque\index.php

REM ======== FATURA PROFORMA ========
echo Criando fatura_proforma/index.php...
(
echo ^<?php
echo require_once '../../config/database.php';
echo.
echo $stmt = $pdo->query("SELECT * FROM faturas_proforma ORDER BY created_at DESC");
echo $faturas = $stmt->fetchAll();
echo ?^>
echo.
echo ^<!DOCTYPE html^>
echo ^<html lang="pt-BR"^>
echo ^<head^>
echo     ^<meta charset="UTF-8"^>
echo     ^<meta name="viewport" content="width=device-width, initial-scale=1.0"^>
echo     ^<title^>Fatura Proforma - SoftGest Web^</title^>
echo     ^<link rel="stylesheet" href="../../assets/css/style.css"^>
echo ^</head^>
echo ^<body^>
echo     ^<?php include '../../includes/header.php'; ?^>
echo     ^<div class="container"^>
echo         ^<h2^>📄 Faturas Proforma^</h2^>
echo         ^<div class="actions"^>
echo             ^<a href="create.php" class="btn btn-primary"^>+ Nova Fatura^</a^>
echo         ^</div^>
echo         ^<table class="table"^>
echo             ^<thead^>
echo                 ^<tr^>
echo                     ^<th^>Número^</th^>
echo                     ^<th^>Cliente^</th^>
echo                     ^<th^>Total^</th^>
echo                     ^<th^>Status^</th^>
echo                     ^<th^>Data^</th^>
echo                     ^<th^>Ações^</th^>
echo                 ^</tr^>
echo             ^</thead^>
echo             ^<tbody^>
echo                 ^<?php foreach($faturas as $fatura): ?^>
echo                 ^<tr^>
echo                     ^<td^>^<?= $fatura['numero'] ?^>^</td^>
echo                     ^<td^>^<?php
echo                         $stmtCli = $pdo->prepare("SELECT nome FROM clientes WHERE id = ?");
echo                         $stmtCli->execute([$fatura['cliente_id']]);
echo                         $cliente = $stmtCli->fetch();
echo                         echo $cliente['nome'] ?? 'N/A';
echo                     ?^>^</td^>
echo                     ^<td^>R$ ^<?= number_format($fatura['total'], 2, ',', '.') ?^>^</td^>
echo                     ^<td^>^<?= $fatura['status'] ?^>^</td^>
echo                     ^<td^>^<?= date('d/m/Y', strtotime($fatura['created_at'])) ?^>^</td^>
echo                     ^<td^>
echo                         ^<a href="view.php?id=^<?= $fatura['id'] ?^>" class="btn-small"^>Visualizar^</a^>
echo                     ^</td^>
echo                 ^</tr^>
echo                 ^<?php endforeach; ?^>
echo             ^</tbody^>
echo         ^</table^>
echo     ^</div^>
echo     ^<?php include '../../includes/footer.php'; ?^>
echo ^</body^>
echo ^</html^>
) > fatura_proforma\index.php

REM ======== RH ========
echo Criando rh/index.php...
(
echo ^<?php
echo require_once '../../config/database.php';
echo.
echo $stmt = $pdo->query("SELECT * FROM funcionarios ORDER BY nome");
echo $funcionarios = $stmt->fetchAll();
echo ?^>
echo.
echo ^<!DOCTYPE html^>
echo ^<html lang="pt-BR"^>
echo ^<head^>
echo     ^<meta charset="UTF-8"^>
echo     ^<meta name="viewport" content="width=device-width, initial-scale=1.0"^>
echo     ^<title^>RH - SoftGest Web^</title^>
echo     ^<link rel="stylesheet" href="../../assets/css/style.css"^>
echo ^</head^>
echo ^<body^>
echo     ^<?php include '../../includes/header.php'; ?^>
echo     ^<div class="container"^>
echo         ^<h2^>👥 Recursos Humanos^</h2^>
echo         ^<div class="actions"^>
echo             ^<a href="add_funcionario.php" class="btn btn-primary"^>+ Novo Funcionário^</a^>
echo         ^</div^>
echo         ^<table class="table"^>
echo             ^<thead^>
echo                 ^<tr^>
echo                     ^<th^>Nome^</th^>
echo                     ^<th^>Cargo^</th^>
echo                     ^<th^>Departamento^</th^>
echo                     ^<th^>Salário^</th^>
echo                     ^<th^>Status^</th^>
echo                     ^<th^>Ações^</th^>
echo                 ^</tr^>
echo             ^</thead^>
echo             ^<tbody^>
echo                 ^<?php foreach($funcionarios as $func): ?^>
echo                 ^<tr^>
echo                     ^<td^>^<?= $func['nome'] ?^>^</td^>
echo                     ^<td^>^<?= $func['cargo'] ?^>^</td^>
echo                     ^<td^>^<?= $func['departamento'] ?^>^</td^>
echo                     ^<td^>R$ ^<?= number_format($func['salario'], 2, ',', '.') ?^>^</td^>
echo                     ^<td^>^<?= $func['status'] ?^>^</td^>
echo                     ^<td^>
echo                         ^<a href="edit_funcionario.php?id=^<?= $func['id'] ?^>" class="btn-small"^>Editar^</a^>
echo                         ^<a href="folha.php?funcionario=^<?= $func['id'] ?^>" class="btn-small"^>Folha^</a^>
echo                     ^</td^>
echo                 ^</tr^>
echo                 ^<?php endforeach; ?^>
echo             ^</tbody^>
echo         ^</table^>
echo     ^</div^>
echo     ^<?php include '../../includes/footer.php'; ?^>
echo ^</body^>
echo ^</html^>
) > rh\index.php

REM ======== MARKETING ========
echo Criando marketing/index.php...
(
echo ^<?php
echo require_once '../../config/database.php';
echo.
echo $stmt = $pdo->query("SELECT * FROM plano_marketing ORDER BY created_at DESC");
echo $planos = $stmt->fetchAll();
echo ?^>
echo.
echo ^<!DOCTYPE html^>
echo ^<html lang="pt-BR"^>
echo ^<head^>
echo     ^<meta charset="UTF-8"^>
echo     ^<meta name="viewport" content="width=device-width, initial-scale=1.0"^>
echo     ^<title^>Marketing - SoftGest Web^</title^>
echo     ^<link rel="stylesheet" href="../../assets/css/style.css"^>
echo ^</head^>
echo ^<body^>
echo     ^<?php include '../../includes/header.php'; ?^>
echo     ^<div class="container"^>
echo         ^<h2^>📊 Planos de Marketing^</h2^>
echo         ^<div class="actions"^>
echo             ^<a href="plano.php" class="btn btn-primary"^>+ Novo Plano^</a^>
echo         ^</div^>
echo         ^<table class="table"^>
echo             ^<thead^>
echo                 ^<tr^>
echo                     ^<th^>Título^</th^>
echo                     ^<th^>Objetivo^</th^>
echo                     ^<th^>Orçamento^</th^>
echo                     ^<th^>Status^</th^>
echo                     ^<th^>Período^</th^>
echo                 ^</tr^>
echo             ^</thead^>
echo             ^<tbody^>
echo                 ^<?php foreach($planos as $plano): ?^>
echo                 ^<tr^>
echo                     ^<td^>^<?= $plano['titulo'] ?^>^</td^>
echo                     ^<td^>^<?= substr($plano['objetivo'], 0, 50) ?^>...^</td^>
echo                     ^<td^>R$ ^<?= number_format($plano['orcamento'], 2, ',', '.') ?^>^</td^>
echo                     ^<td^>^<?= $plano['status'] ?^>^</td^>
echo                     ^<td^>^<?= date('d/m/Y', strtotime($plano['data_inicio'])) ?^>^</td^>
echo                 ^</tr^>
echo                 ^<?php endforeach; ?^>
echo             ^</tbody^>
echo         ^</table^>
echo     ^</div^>
echo     ^<?php include '../../includes/footer.php'; ?^>
echo ^</body^>
echo ^</html^>
) > marketing\index.php

REM ======== CORRESPONDENCIA ========
echo Criando correspondencia/index.php...
(
echo ^<?php
echo require_once '../../config/database.php';
echo.
echo $stmt = $pdo->query("SELECT * FROM correspondencias ORDER BY created_at DESC");
echo $correspondencias = $stmt->fetchAll();
echo ?^>
echo.
echo ^<!DOCTYPE html^>
echo ^<html lang="pt-BR"^>
echo ^<head^>
echo     ^<meta charset="UTF-8"^>
echo     ^<meta name="viewport" content="width=device-width, initial-scale=1.0"^>
echo     ^<title^>Correspondência - SoftGest Web^</title^>
echo     ^<link rel="stylesheet" href="../../assets/css/style.css"^>
echo ^</head^>
echo ^<body^>
echo     ^<?php include '../../includes/header.php'; ?^>
echo     ^<div class="container"^>
echo         ^<h2^>✉️ Correspondências^</h2^>
echo         ^<div class="actions"^>
echo             ^<a href="enviar.php" class="btn btn-primary"^>+ Nova Correspondência^</a^>
echo         ^</div^>
echo         ^<table class="table"^>
echo             ^<thead^>
echo                 ^<tr^>
echo                     ^<th^>Destinatário^</th^>
echo                     ^<th^>Assunto^</th^>
echo                     ^<th^>Tipo^</th^>
echo                     ^<th^>Status^</th^>
echo                     ^<th^>Data^</th^>
echo                 ^</tr^>
echo             ^</thead^>
echo             ^<tbody^>
echo                 ^<?php foreach($correspondencias as $corresp): ?^>
echo                 ^<tr^>
echo                     ^<td^>^<?= $corresp['destinatario'] ?^>^</td^>
echo                     ^<td^>^<?= $corresp['assunto'] ?^>^</td^>
echo                     ^<td^>^<?= $corresp['tipo'] ?^>^</td^>
echo                     ^<td^>^<?= $corresp['status'] ?^>^</td^>
echo                     ^<td^>^<?= date('d/m/Y', strtotime($corresp['created_at'])) ?^>^</td^>
echo                 ^</tr^>
echo                 ^<?php endforeach; ?^>
echo             ^</tbody^>
echo         ^</table^>
echo     ^</div^>
echo     ^<?php include '../../includes/footer.php'; ?^>
echo ^</body^>
echo ^</html^>
) > correspondencia\index.php

echo.
echo ========================================
echo ✅ MÓDULOS CORRIGIDOS COM SUCESSO!
echo ========================================
echo.
echo Acesse: http://localhost/softgest_web/
echo.
pause