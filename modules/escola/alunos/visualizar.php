<?php
// ============================================
// modules/escola/alunos/visualizar.php - Ficha do Aluno
// ============================================

// Carregar configurações
require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

// Verificar permissão
if (!temPermissao('Escola', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ===== FUNÇÕES =====
function formatarMoeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function formatarData($data) {
    if ($data && $data != '0000-00-00' && $data != 'NULL' && !empty($data)) {
        return date('d/m/Y', strtotime($data));
    }
    return '-';
}

function obterNomeMes($mes) {
    $meses = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 
              'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    return $meses[$mes - 1] ?? $mes;
}

function obterStatus($status) {
    $status = strtolower($status);
    switch ($status) {
        case 'pago': return 'PAGO';
        case 'pendente': return 'PENDENTE';
        case 'atrasado': return 'ATRASADO';
        default: return strtoupper($status);
    }
}

function obterGenero($genero) {
    if ($genero == 'M') return 'Masculino';
    if ($genero == 'F') return 'Feminino';
    return 'Não informado';
}

// ===== BUSCAR ALUNO =====
$aluno_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$aluno = null;
$erro = '';
$mensalidades = [];
$pagamentos = [];
$total_pago = 0;
$total_debito = 0;
$total_mensalidades = 0;
$pendentes = 0;
$atrasados = 0;

if ($aluno_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM alunos WHERE id = ?");
        $stmt->execute([$aluno_id]);
        $aluno = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$aluno) {
            $erro = 'Aluno não encontrado.';
        } else {
            $stmt = $pdo->prepare("
                SELECT * FROM mensalidades 
                WHERE aluno_id = ? 
                ORDER BY ano DESC, mes DESC
            ");
            $stmt->execute([$aluno_id]);
            $mensalidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total_mensalidades = count($mensalidades);
            
            $stmt = $pdo->prepare("
                SELECT p.*, e.nome as emolumento_nome 
                FROM pagamentos p
                LEFT JOIN emolumentos e ON p.emolumento_id = e.id
                WHERE p.aluno_id = ? 
                ORDER BY p.id DESC 
                LIMIT 50
            ");
            $stmt->execute([$aluno_id]);
            $pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($mensalidades as $m) {
                if ($m['status'] == 'pago' || $m['status'] == 'Pago') {
                    $total_pago += $m['valor'];
                } else {
                    $total_debito += $m['valor'];
                    if ($m['status'] == 'pendente' || $m['status'] == 'Pendente') {
                        $pendentes++;
                    } elseif ($m['status'] == 'atrasado' || $m['status'] == 'Atrasado') {
                        $atrasados++;
                    }
                }
            }
        }
    } catch (Exception $e) {
        $erro = 'Erro ao buscar aluno: ' . $e->getMessage();
    }
} else {
    $erro = 'ID do aluno não informado.';
}

// ===== CONFIGURAÇÕES DA ESCOLA =====
$nome_escola = 'Sistema Escolar';
$endereco = '';
$contacto = '';
$nif = '';

$config_file = '../../../../config_escola.json';
if (file_exists($config_file)) {
    $config = json_decode(file_get_contents($config_file), true);
    $nome_escola = $config['nome'] ?? 'Sistema Escolar';
    $endereco = $config['endereco'] ?? '';
    $contacto = $config['contacto'] ?? '';
    $nif = $config['nif'] ?? '';
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha do Aluno - <?= htmlspecialchars($aluno['nome'] ?? '') ?></title>
    <style>
        /* ===== ESTILOS DA FICHA ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Times New Roman', Times, serif;
            background: #f0f0f0;
            padding: 20px;
            display: flex;
            justify-content: center;
        }
        
        .ficha-container {
            max-width: 210mm;
            width: 100%;
            background: white;
            padding: 20px 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            border: 1px solid #ccc;
        }
        
        /* ===== CABEÇALHO ===== */
        .header {
            text-align: center;
            border-bottom: 3px double #1a2332;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .header h1 {
            font-size: 22px;
            color: #1a2332;
            letter-spacing: 2px;
            margin: 0;
        }
        
        .header .subtitulo {
            font-size: 14px;
            color: #555;
            margin: 3px 0;
            letter-spacing: 1px;
        }
        
        .header .info-escola {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .header .nif {
            border: 1px solid #1a2332;
            padding: 2px 15px;
            display: inline-block;
            font-size: 12px;
            margin-top: 5px;
        }
        
        /* ===== DADOS DO ALUNO ===== */
        .dados-aluno {
            border: 1px solid #1a2332;
            padding: 15px;
            margin-bottom: 20px;
            background: #fafafa;
        }
        
        .dados-aluno .titulo {
            font-weight: bold;
            font-size: 14px;
            text-align: center;
            border-bottom: 1px solid #1a2332;
            padding-bottom: 5px;
            margin-bottom: 10px;
            letter-spacing: 2px;
        }
        
        .dados-aluno .grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 5px 30px;
        }
        
        .dados-aluno .item {
            display: flex;
            flex-direction: column;
            padding: 3px 0;
        }
        
        .dados-aluno .label {
            font-size: 10px;
            color: #888;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .dados-aluno .value {
            font-size: 13px;
            color: #1a2332;
            font-weight: 500;
        }
        
        /* ===== RESUMO ===== */
        .resumo-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .resumo-item {
            border: 1px solid #1a2332;
            padding: 10px;
            text-align: center;
            background: #fafafa;
        }
        
        .resumo-item .numero {
            font-size: 20px;
            font-weight: 700;
            color: #1a2332;
        }
        
        .resumo-item .numero.verde { color: #27ae60; }
        .resumo-item .numero.vermelho { color: #e74c3c; }
        .resumo-item .numero.laranja { color: #f39c12; }
        
        .resumo-item .label {
            font-size: 10px;
            color: #888;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        /* ===== ALERTA ===== */
        .alerta {
            padding: 12px 16px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .alerta-vermelho {
            background: #fee2e2;
            border: 2px solid #e74c3c;
            color: #991b1b;
        }
        
        .alerta-laranja {
            background: #fef3c7;
            border: 2px solid #f39c12;
            color: #92400e;
        }
        
        .alerta-verde {
            background: #d1fae5;
            border: 2px solid #27ae60;
            color: #065f46;
        }
        
        .alerta .destaque {
            font-size: 18px;
            font-weight: 700;
        }
        
        .alerta .sub {
            font-weight: 400;
            font-size: 12px;
        }
        
        /* ===== TABELAS ===== */
        .tabela {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-bottom: 15px;
            border: 1px solid #1a2332;
        }
        
        .tabela th {
            background: #1a2332;
            color: white;
            padding: 6px 10px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid #1a2332;
        }
        
        .tabela td {
            padding: 5px 10px;
            border: 1px solid #1a2332;
            color: #333;
        }
        
        .tabela .text-right { text-align: right; }
        .tabela .text-center { text-align: center; }
        
        .tabela .status-pago { color: #27ae60; font-weight: 600; }
        .tabela .status-pendente { color: #f39c12; font-weight: 600; }
        .tabela .status-atrasado { color: #e74c3c; font-weight: 600; }
        
        .tabela tr:nth-child(even) { background: #f9f9f9; }
        
        .tabela .destaque-atrasado { background: #fee2e2 !important; }
        .tabela .destaque-pendente { background: #fef3c7 !important; }
        
        /* ===== TÍTULO SEÇÃO ===== */
        .secao-titulo {
            font-size: 14px;
            font-weight: 700;
            color: #1a2332;
            margin: 15px 0 8px;
            padding-bottom: 5px;
            border-bottom: 2px solid #1a2332;
            display: flex;
            justify-content: space-between;
        }
        
        .secao-titulo .contador {
            font-size: 11px;
            color: #888;
            font-weight: 400;
        }
        
        /* ===== RESULTADO ===== */
        .resultado {
            border: 2px solid #1a2332;
            padding: 15px;
            margin-top: 15px;
            background: #fafafa;
        }
        
        .resultado .linha {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        
        .resultado .linha:last-child { border-bottom: none; }
        
        .resultado .label { font-weight: 600; color: #555; }
        .resultado .valor { font-weight: 700; }
        .resultado .valor.verde { color: #27ae60; }
        .resultado .valor.vermelho { color: #e74c3c; }
        
        /* ===== BARRA DE PROGRESSO ===== */
        .progresso {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }
        
        .progresso .barra {
            width: 100%;
            height: 8px;
            background: #eee;
            border-radius: 10px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .progresso .barra .fill {
            height: 100%;
            background: #27ae60;
            border-radius: 10px;
            transition: width 0.5s;
        }
        
        .progresso .info {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
        }
        
        /* ===== RODAPÉ ===== */
        .footer {
            border-top: 1px solid #1a2332;
            padding-top: 10px;
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: #999;
        }
        
        /* ===== IMPRESSÃO ===== */
        @media print {
            body { background: white; padding: 0; }
            .ficha-container { box-shadow: none; border: none; padding: 15px 20px; }
            .no-print { display: none !important; }
            .tabela th { background: #1a2332 !important; color: white !important; }
            .tabela tr:nth-child(even) { background: #f9f9f9 !important; }
            .tabela .destaque-atrasado { background: #fee2e2 !important; }
            .tabela .destaque-pendente { background: #fef3c7 !important; }
            .alerta { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
        
        @media (max-width: 600px) {
            .dados-aluno .grid { grid-template-columns: 1fr; }
            .resumo-grid { grid-template-columns: 1fr 1fr; }
            .ficha-container { padding: 10px; }
            .tabela { font-size: 10px; }
            .tabela th, .tabela td { padding: 3px 5px; }
        }
    </style>
</head>
<body>

<div class="ficha-container">

    <!-- ===== CABEÇALHO ===== -->
    <div class="header">
        <h1><?= htmlspecialchars($nome_escola) ?></h1>
        <div class="subtitulo">FICHA INDIVIDUAL DO ALUNO</div>
        <div class="info-escola">
            <?= htmlspecialchars($endereco) ?>
            <?php if ($contacto): ?> | 📞 <?= htmlspecialchars($contacto) ?><?php endif; ?>
        </div>
        <?php if ($nif): ?>
        <div class="nif">NIF: <?= htmlspecialchars($nif) ?></div>
        <?php endif; ?>
    </div>

    <?php if ($erro): ?>
    <div style="color:#e74c3c;text-align:center;padding:20px;border:1px solid #e74c3c;">
        ❌ <?= $erro ?>
    </div>
    <?php endif; ?>

    <?php if ($aluno): ?>
    
    <!-- ===== DADOS DO ALUNO ===== -->
    <div class="dados-aluno">
        <div class="titulo">DADOS PESSOAIS</div>
        <div class="grid">
            <div class="item">
                <span class="label">Nome Completo</span>
                <span class="value"><?= htmlspecialchars($aluno['nome'] ?? 'N/A') ?></span>
            </div>
            <div class="item">
                <span class="label">ID / Processo</span>
                <span class="value">#<?= $aluno['id'] ?></span>
            </div>
            <div class="item">
                <span class="label">Status</span>
                <span class="value"><?= ucfirst($aluno['status'] ?? 'N/A') ?></span>
            </div>
            <div class="item">
                <span class="label">Gênero</span>
                <span class="value"><?= obterGenero($aluno['genero'] ?? '') ?></span>
            </div>
            <div class="item">
                <span class="label">Data de Nascimento</span>
                <span class="value"><?= formatarData($aluno['data_nascimento'] ?? '') ?></span>
            </div>
            <div class="item">
                <span class="label">Idade</span>
                <span class="value">
                    <?php 
                    if (!empty($aluno['data_nascimento'])) {
                        $idade = date('Y') - date('Y', strtotime($aluno['data_nascimento']));
                        echo $idade . ' anos';
                    } else {
                        echo '-';
                    }
                    ?>
                </span>
            </div>
            <div class="item">
                <span class="label">Classe</span>
                <span class="value"><?= htmlspecialchars($aluno['classe'] ?? $aluno['Classe'] ?? 'N/A') ?></span>
            </div>
            <div class="item">
                <span class="label">Turma / Sala</span>
                <span class="value"><?= htmlspecialchars($aluno['turma'] ?? $aluno['TURMA'] ?? $aluno['SALA'] ?? 'N/A') ?></span>
            </div>
            <div class="item">
                <span class="label">Curso</span>
                <span class="value"><?= htmlspecialchars($aluno['curso'] ?? $aluno['Curso'] ?? 'N/A') ?></span>
            </div>
            <div class="item">
                <span class="label">Contacto</span>
                <span class="value"><?= htmlspecialchars($aluno['telefone'] ?? $aluno['Contacto_do_Aluno'] ?? 'N/A') ?></span>
            </div>
            <div class="item">
                <span class="label">Email</span>
                <span class="value"><?= htmlspecialchars($aluno['email'] ?? 'N/A') ?></span>
            </div>
            <div class="item">
                <span class="label">Endereço</span>
                <span class="value"><?= htmlspecialchars($aluno['endereco'] ?? $aluno['Morada'] ?? 'N/A') ?></span>
            </div>
            <?php if (!empty($aluno['nome_pai']) || !empty($aluno['Nome_do_Pai'])): ?>
            <div class="item">
                <span class="label">Nome do Pai</span>
                <span class="value"><?= htmlspecialchars($aluno['nome_pai'] ?? $aluno['Nome_do_Pai'] ?? 'N/A') ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($aluno['nome_mae']) || !empty($aluno['Nome_da_mae'])): ?>
            <div class="item">
                <span class="label">Nome da Mãe</span>
                <span class="value"><?= htmlspecialchars($aluno['nome_mae'] ?? $aluno['Nome_da_mae'] ?? 'N/A') ?></span>
            </div>
            <?php endif; ?>
            <div class="item">
                <span class="label">Data de Matrícula</span>
                <span class="value"><?= formatarData($aluno['data_matricula'] ?? '') ?></span>
            </div>
        </div>
    </div>

    <!-- ===== ALERTA ===== -->
    <?php if ($atrasados > 0): ?>
    <div class="alerta alerta-vermelho">
        <span class="destaque">⚠️ ATENÇÃO!</span><br>
        Este aluno possui <strong><?= $atrasados ?></strong> mensalidade(s) em <strong>ATRASO</strong>.<br>
        <span class="sub">Valor total em atraso: <?= formatarMoeda($total_debito) ?></span><br>
        <span class="sub"><strong>URGENTE:</strong> Regularizar situação financeira o mais breve possível.</span>
    </div>
    <?php elseif ($pendentes > 0): ?>
    <div class="alerta alerta-laranja">
        <span class="destaque">📌 AVISO</span><br>
        Este aluno possui <strong><?= $pendentes ?></strong> mensalidade(s) <strong>PENDENTE(S)</strong>.<br>
        <span class="sub">Valor pendente: <?= formatarMoeda($total_debito) ?></span><br>
        <span class="sub">Solicitamos a regularização dos pagamentos pendentes.</span>
    </div>
    <?php else: ?>
    <div class="alerta alerta-verde">
        <span class="destaque">✅ SITUAÇÃO REGULAR</span><br>
        Este aluno não possui débitos pendentes.<br>
        <span class="sub">Todas as mensalidades estão em dia.</span>
    </div>
    <?php endif; ?>

    <!-- ===== RESUMO FINANCEIRO ===== -->
    <div class="resumo-grid">
        <div class="resumo-item">
            <div class="numero"><?= $total_mensalidades ?></div>
            <div class="label">Mensalidades</div>
        </div>
        <div class="resumo-item">
            <div class="numero verde"><?= formatarMoeda($total_pago) ?></div>
            <div class="label">Total Pago</div>
        </div>
        <div class="resumo-item">
            <div class="numero vermelho"><?= formatarMoeda($total_debito) ?></div>
            <div class="label">Débito Total</div>
        </div>
        <div class="resumo-item">
            <div class="numero"><?= count($pagamentos) ?></div>
            <div class="label">Pagamentos</div>
        </div>
    </div>

    <!-- ===== MENSALIDADES ===== -->
    <div class="secao-titulo">
        📅 Mensalidades
        <span class="contador"><?= $total_mensalidades ?> registro(s)</span>
    </div>
    
    <table class="tabela">
        <thead>
            <tr>
                <th>Mês/Ano</th>
                <th>Descrição</th>
                <th class="text-right">Valor</th>
                <th>Vencimento</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($mensalidades)): ?>
                <?php foreach ($mensalidades as $m): 
                    $status = strtolower($m['status'] ?? 'pendente');
                    $class_status = 'status-' . $status;
                    $destaque = '';
                    if ($status == 'atrasado') $destaque = 'destaque-atrasado';
                    elseif ($status == 'pendente') $destaque = 'destaque-pendente';
                ?>
                <tr class="<?= $destaque ?>">
                    <td><strong><?= obterNomeMes($m['mes']) ?>/<?= $m['ano'] ?></strong></td>
                    <td><?= htmlspecialchars($m['descricao'] ?? 'Mensalidade') ?></td>
                    <td class="text-right"><?= formatarMoeda($m['valor'] ?? 0) ?></td>
                    <td><?= formatarData($m['data_vencimento'] ?? '') ?></td>
                    <td class="<?= $class_status ?>"><?= obterStatus($status) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" class="text-center" style="color:#999;padding:15px;">Nenhuma mensalidade encontrada</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- ===== PAGAMENTOS ===== -->
    <div class="secao-titulo">
        💳 Histórico de Pagamentos
        <span class="contador"><?= count($pagamentos) ?> registro(s)</span>
    </div>
    
    <table class="tabela">
        <thead>
            <tr>
                <th>Data</th>
                <th>Descrição</th>
                <th class="text-right">Valor</th>
                <th>Forma</th>
                <th>Referência</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($pagamentos)): ?>
                <?php foreach ($pagamentos as $p): ?>
                <tr>
                    <td><?= formatarData($p['data_pagamento'] ?? '') ?></td>
                    <td><?= htmlspecialchars($p['emolumento_nome'] ?? $p['descricao'] ?? 'Pagamento') ?></td>
                    <td class="text-right"><?= formatarMoeda($p['valor'] ?? 0) ?></td>
                    <td><?= ucfirst($p['forma_pagamento'] ?? 'Dinheiro') ?></td>
                    <td><?= htmlspecialchars($p['referencia'] ?? '-') ?></td>
                    <td class="status-pago">PAGO</td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" class="text-center" style="color:#999;padding:15px;">Nenhum pagamento encontrado</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- ===== RESUMO FINANCEIRO ===== -->
    <div class="resultado">
        <div class="linha">
            <span class="label">Total de Mensalidades</span>
            <span class="valor"><?= formatarMoeda($total_pago + $total_debito) ?></span>
        </div>
        <div class="linha">
            <span class="label">Total Pago</span>
            <span class="valor verde"><?= formatarMoeda($total_pago) ?></span>
        </div>
        <div class="linha">
            <span class="label">Débito Pendente</span>
            <span class="valor vermelho"><?= formatarMoeda($total_debito) ?></span>
        </div>
        
        <?php 
        $total_geral = $total_pago + $total_debito;
        $percentual = $total_geral > 0 ? round(($total_pago / $total_geral) * 100, 1) : 0;
        ?>
        <div class="progresso">
            <div class="info">
                <span>Progresso de Pagamento</span>
                <span><?= $percentual ?>%</span>
            </div>
            <div class="barra">
                <div class="fill" style="width:<?= $percentual ?>%;"></div>
            </div>
        </div>
    </div>

    <!-- ===== OBSERVAÇÃO FINAL ===== -->
    <?php if ($atrasados > 0 || $pendentes > 0): ?>
    <div style="margin-top:15px;padding:10px;border:1px solid #e74c3c;background:#fee2e2;text-align:center;font-size:13px;">
        <strong>📌 RECOMENDAÇÃO:</strong> 
        <?php if ($atrasados > 0): ?>
            Regularizar URGENTEMENTE as <?= $atrasados ?> mensalidade(s) em atraso.
        <?php else: ?>
            Efetuar o pagamento das <?= $pendentes ?> mensalidade(s) pendentes.
        <?php endif; ?>
        <br>
        <span style="font-size:11px;color:#666;">Total em débito: <?= formatarMoeda($total_debito) ?></span>
    </div>
    <?php endif; ?>

    <!-- ===== RODAPÉ ===== -->
    <div class="footer">
        <div>
            <strong><?= htmlspecialchars($nome_escola) ?></strong>
            <?php if ($nif): ?> | NIF: <?= htmlspecialchars($nif) ?><?php endif; ?>
        </div>
        <div>
            Documento gerado em <?= date('d/m/Y H:i:s') ?>
        </div>
    </div>

    <?php endif; ?>

</div>

<!-- ===== BOTÃO IMPRIMIR ===== -->
<div class="no-print" style="text-align:center;margin-top:20px;">
    <button onclick="window.print()" style="padding:12px 40px;background:#1a2332;color:white;border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;">
        🖨️ IMPRIMIR FICHA
    </button>
    <button onclick="window.close()" style="padding:12px 40px;background:#e74c3c;color:white;border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;margin-left:10px;">
        ✕ FECHAR
    </button>
</div>

</body>
</html>