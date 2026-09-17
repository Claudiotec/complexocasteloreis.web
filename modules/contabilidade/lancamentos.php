<?php
// ============================================
// LANÇAMENTOS CONTÁBEIS
// ============================================

require_once '../../config/app_modes.php';
require_once '../../config/database.php';
require_once 'config.php';

session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pdo = conectarBanco();
$usuario_id = $_SESSION['usuario_id'];
$usuario_perfil = $_SESSION['usuario_perfil'] ?? 'usuario';
$isAdmin = ($usuario_perfil == 'admin');

$acao = $_GET['acao'] ?? 'listar';
$id = $_GET['id'] ?? 0;

// ===== PROCESSAR FORMULÁRIO =====
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    $data_lancamento = $_POST['data_lancamento'] ?? date('Y-m-d');
    $historico = $_POST['historico'] ?? '';
    $tipo_documento = $_POST['tipo_documento'] ?? 'OUTRO';
    $observacoes = $_POST['observacoes'] ?? '';
    $status = $_POST['status'] ?? 'rascunho';
    
    $itens_debito = $_POST['item_debito'] ?? [];
    $valores_debito = $_POST['valor_debito'] ?? [];
    $itens_credito = $_POST['item_credito'] ?? [];
    $valores_credito = $_POST['valor_credito'] ?? [];
    
    if ($_POST['acao'] == 'salvar') {
        try {
            $pdo->beginTransaction();
            
            // Gerar número do lançamento
            $numero = 'LC-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            
            // Inserir lançamento
            $stmt = $pdo->prepare("
                INSERT INTO lancamentos_contabeis 
                (numero_lancamento, data_lancamento, data_competencia, historico, tipo_documento, observacoes, status, usuario_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $numero,
                $data_lancamento,
                $data_lancamento,
                $historico,
                $tipo_documento,
                $observacoes,
                $status,
                $usuario_id
            ]);
            $lancamento_id = $pdo->lastInsertId();
            
            // Inserir itens (débitos)
            foreach ($itens_debito as $i => $conta_id) {
                if (!empty($conta_id) && isset($valores_debito[$i]) && $valores_debito[$i] > 0) {
                    $stmt = $pdo->prepare("
                        INSERT INTO lancamentos_itens (lancamento_id, conta_id, tipo_movimento, valor)
                        VALUES (?, ?, 'DEBITO', ?)
                    ");
                    $stmt->execute([$lancamento_id, $conta_id, $valores_debito[$i]]);
                }
            }
            
            // Inserir itens (créditos)
            foreach ($itens_credito as $i => $conta_id) {
                if (!empty($conta_id) && isset($valores_credito[$i]) && $valores_credito[$i] > 0) {
                    $stmt = $pdo->prepare("
                        INSERT INTO lancamentos_itens (lancamento_id, conta_id, tipo_movimento, valor)
                        VALUES (?, ?, 'CREDITO', ?)
                    ");
                    $stmt->execute([$lancamento_id, $conta_id, $valores_credito[$i]]);
                }
            }
            
            $pdo->commit();
            
            // Atualizar saldos
            atualizarSaldos($pdo, $data_lancamento);
            
            $_SESSION['mensagem'] = 'Lançamento criado com sucesso!';
            $_SESSION['mensagem_tipo'] = 'success';
            header('Location: lancamentos.php?acao=view&id=' . $lancamento_id);
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $erro = 'Erro ao salvar: ' . $e->getMessage();
        }
    }
}

// ===== FUNÇÃO PARA ATUALIZAR SALDOS =====
function atualizarSaldos($pdo, $data) {
    $ano = date('Y', strtotime($data));
    $mes = date('m', strtotime($data));
    
    // Buscar todos os saldos do mês
    $stmt = $pdo->prepare("
        SELECT 
            pc.id as conta_id,
            SUM(CASE WHEN li.tipo_movimento = 'DEBITO' THEN li.valor ELSE 0 END) as total_debito,
            SUM(CASE WHEN li.tipo_movimento = 'CREDITO' THEN li.valor ELSE 0 END) as total_credito
        FROM lancamentos_contabeis l
        JOIN lancamentos_itens li ON l.id = li.lancamento_id
        JOIN plano_contas pc ON li.conta_id = pc.id
        WHERE YEAR(l.data_lancamento) = ? AND MONTH(l.data_lancamento) = ?
        AND l.status = 'confirmado'
        GROUP BY pc.id
    ");
    $stmt->execute([$ano, $mes]);
    $saldos = $stmt->fetchAll();
    
    // Atualizar ou inserir saldos
    foreach ($saldos as $saldo) {
        $saldo_final = $saldo['total_debito'] - $saldo['total_credito'];
        
        // Verificar natureza da conta
        $stmt = $pdo->prepare("SELECT natureza FROM plano_contas WHERE id = ?");
        $stmt->execute([$saldo['conta_id']]);
        $conta = $stmt->fetch();
        
        if ($conta && $conta['natureza'] == 'CREDORA') {
            $saldo_final = $saldo['total_credito'] - $saldo['total_debito'];
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO saldo_contas (conta_id, ano, mes, saldo_debito, saldo_credito, saldo_final)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            saldo_debito = VALUES(saldo_debito),
            saldo_credito = VALUES(saldo_credito),
            saldo_final = VALUES(saldo_final)
        ");
        $stmt->execute([
            $saldo['conta_id'],
            $ano,
            $mes,
            $saldo['total_debito'],
            $saldo['total_credito'],
            $saldo_final
        ]);
    }
}

// ===== BUSCAR DADOS PARA VIEW =====
$lancamento = null;
$itens = [];
if ($acao == 'view' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM lancamentos_contabeis WHERE id = ?");
    $stmt->execute([$id]);
    $lancamento = $stmt->fetch();
    
    if ($lancamento) {
        $stmt = $pdo->prepare("
            SELECT li.*, pc.codigo, pc.nome as conta_nome
            FROM lancamentos_itens li
            JOIN plano_contas pc ON li.conta_id = pc.id
            WHERE li.lancamento_id = ?
        ");
        $stmt->execute([$id]);
        $itens = $stmt->fetchAll();
    }
}

// ===== BUSCAR CONTAS PARA FORMULÁRIO =====
$contas = $pdo->query("
    SELECT id, codigo, nome, tipo, natureza 
    FROM plano_contas 
    WHERE status = 'ativo' AND permite_lancamento = 1
    ORDER BY codigo
")->fetchAll();

include_once '../../includes/header.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lançamentos Contábeis - SoftGest</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; }
        
        .module-container { padding: 20px 30px; max-width: 1400px; margin: 0 auto; }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .header h1 { font-size: 24px; color: #1a2332; }
        .header h1 span { color: #c9a84c; }
        .header .sub { color: #94a3b8; font-size: 14px; }
        
        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .actions a {
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            background: #f8fafc;
            color: #1a2332;
            border: 1px solid #eef2f7;
        }
        .actions a:hover { background: #f5d76e20; border-color: #f5d76e; }
        .actions a.primary { background: #f5d76e; color: #1a2332; border: none; }
        .actions a.primary:hover { background: #e6c753; }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #eef2f7;
            margin-bottom: 20px;
        }
        .card h3 { color: #1a2332; margin-bottom: 20px; font-size: 16px; }
        
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-weight: 600; color: #1a2332; margin-bottom: 5px; font-size: 14px; }
        .form-group .help { font-size: 12px; color: #94a3b8; }
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: border 0.3s;
        }
        .form-control:focus { outline: none; border-color: #f5d76e; box-shadow: 0 0 0 3px rgba(245, 215, 110, 0.2); }
        textarea.form-control { min-height: 80px; resize: vertical; }
        select.form-control { appearance: auto; }
        
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { .row { grid-template-columns: 1fr; } }
        
        .item-linha {
            display: grid;
            grid-template-columns: 1fr 1fr 80px;
            gap: 10px;
            align-items: center;
            padding: 10px;
            background: #f8fafc;
            border-radius: 8px;
            margin-bottom: 8px;
        }
        .item-linha .remove-btn {
            background: #fee2e2;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            color: #991b1b;
            cursor: pointer;
            font-size: 16px;
        }
        .item-linha .remove-btn:hover { background: #fecaca; }
        
        .btn-add {
            padding: 8px 16px;
            background: #f1f5f9;
            border: 1px dashed #94a3b8;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            color: #1a2332;
            transition: all 0.3s;
        }
        .btn-add:hover { background: #f5d76e20; border-color: #f5d76e; }
        
        .btn-submit {
            padding: 12px 30px;
            background: linear-gradient(135deg, #c9a84c, #f5d76e);
            color: #1a2332;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(197, 165, 50, 0.3); }
        
        .status-badge {
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .status-badge.confirmado { background: #d1fae5; color: #065f46; }
        .status-badge.rascunho { background: #fef3c7; color: #92400e; }
        .status-badge.cancelado { background: #fee2e2; color: #991b1b; }
        
        .total-geral {
            font-size: 18px;
            font-weight: 700;
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
            text-align: center;
        }
        .total-geral .diferenca { font-size: 14px; font-weight: 400; color: #94a3b8; }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #2ecc71; }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #e74c3c; }
        .alert-info { background: #dbeafe; color: #1e40af; border-left: 4px solid #3b82f6; }
        
        .view-info { margin-bottom: 15px; }
        .view-info .label { color: #94a3b8; font-size: 12px; text-transform: uppercase; }
        .view-info .value { font-size: 16px; font-weight: 600; color: #1a2332; }
        
        .tabela-itens th { background: #f8fafc; }
        .tabela-itens .debito { color: #2ecc71; }
        .tabela-itens .credito { color: #e74c3c; }
        
        .footer-info {
            text-align: center;
            padding: 20px 0 10px;
            color: #94a3b8;
            font-size: 14px;
            border-top: 1px solid #eef2f7;
            margin-top: 20px;
        }
    </style>
</head>
<body>

<div class="module-container">
    <!-- Header -->
    <div class="header">
        <div>
            <h1>📝 <span>Lançamentos Contábeis</span></h1>
            <div class="sub">Registro de débitos e créditos</div>
        </div>
        <div class="actions">
            <a href="index.php">📊 Dashboard</a>
            <?php if ($acao != 'novo'): ?>
            <a href="lancamentos.php?acao=novo" class="primary">➕ Novo Lançamento</a>
            <?php endif; ?>
            <a href="diario.php">📖 Diário</a>
            <a href="razao.php">📚 Razão</a>
        </div>
    </div>

    <?php if (isset($_SESSION['mensagem'])): ?>
    <div class="alert alert-<?= $_SESSION['mensagem_tipo'] ?? 'info' ?>">
        <?= $_SESSION['mensagem'] ?>
        <?php unset($_SESSION['mensagem'], $_SESSION['mensagem_tipo']); ?>
    </div>
    <?php endif; ?>

    <?php if (isset($erro)): ?>
    <div class="alert alert-error"><?= $erro ?></div>
    <?php endif; ?>

    <?php if ($acao == 'novo' || $acao == 'editar'): ?>
    <!-- FORMULÁRIO DE LANÇAMENTO -->
    <div class="card">
        <h3><?= $acao == 'editar' ? '✏️ Editar Lançamento' : '➕ Novo Lançamento' ?></h3>
        <form method="POST" action="">
            <input type="hidden" name="acao" value="salvar">
            
            <div class="row">
                <div class="form-group">
                    <label>Data do Lançamento</label>
                    <input type="date" name="data_lancamento" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label>Tipo de Documento</label>
                    <select name="tipo_documento" class="form-control">
                        <option value="RECIBO">Recibo</option>
                        <option value="FATURA">Fatura</option>
                        <option value="PAGAMENTO">Pagamento</option>
                        <option value="RECEITA">Receita</option>
                        <option value="DESPESA">Despesa</option>
                        <option value="TRANSFERENCIA">Transferência</option>
                        <option value="AJUSTE">Ajuste</option>
                        <option value="OUTRO">Outro</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Histórico <span style="color: #e74c3c;">*</span></label>
                <input type="text" name="historico" class="form-control" placeholder="Descrição do lançamento" required>
            </div>
            
            <div class="form-group">
                <label>Observações</label>
                <textarea name="observacoes" class="form-control" placeholder="Informações adicionais..."></textarea>
            </div>

            <hr style="margin: 20px 0; border-color: #eef2f7;">

            <div class="row">
                <div>
                    <h4 style="color: #2ecc71; margin-bottom: 10px;">📥 DÉBITOS</h4>
                    <div id="debitos-container">
                        <div class="item-linha">
                            <select name="item_debito[]" class="form-control conta-select">
                                <option value="">Selecione uma conta</option>
                                <?php foreach ($contas as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= $c['codigo'] ?> - <?= $c['nome'] ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" name="valor_debito[]" class="form-control" placeholder="Valor" step="0.01" min="0">
                            <button type="button" class="remove-btn" onclick="this.parentElement.remove()">✕</button>
                        </div>
                    </div>
                    <button type="button" class="btn-add" onclick="adicionarLinha('debitos-container', 'DEBITO')">+ Adicionar Débito</button>
                </div>
                <div>
                    <h4 style="color: #e74c3c; margin-bottom: 10px;">📤 CRÉDITOS</h4>
                    <div id="creditos-container">
                        <div class="item-linha">
                            <select name="item_credito[]" class="form-control conta-select">
                                <option value="">Selecione uma conta</option>
                                <?php foreach ($contas as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= $c['codigo'] ?> - <?= $c['nome'] ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" name="valor_credito[]" class="form-control" placeholder="Valor" step="0.01" min="0">
                            <button type="button" class="remove-btn" onclick="this.parentElement.remove()">✕</button>
                        </div>
                    </div>
                    <button type="button" class="btn-add" onclick="adicionarLinha('creditos-container', 'CREDITO')">+ Adicionar Crédito</button>
                </div>
            </div>

            <div class="total-geral" id="totalGeral">
                Total Débitos: <span id="totalDebitos" style="color: #2ecc71;">0,00</span> Kz
                | Total Créditos: <span id="totalCreditos" style="color: #e74c3c;">0,00</span> Kz
                <span class="diferenca" id="diferenca">(Diferença: 0,00 Kz)</span>
            </div>

            <div class="form-group" style="margin-top: 20px;">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="rascunho">📝 Rascunho</option>
                    <option value="confirmado">✅ Confirmado</option>
                </select>
            </div>

            <div style="text-align: right; margin-top: 20px;">
                <button type="submit" class="btn-submit">💾 Salvar Lançamento</button>
            </div>
        </form>
    </div>

    <script>
        function adicionarLinha(containerId, tipo) {
            const container = document.getElementById(containerId);
            const linhas = container.querySelectorAll('.item-linha');
            const options = <?= json_encode($contas) ?>;
            
            let selectOptions = '<option value="">Selecione uma conta</option>';
            options.forEach(c => {
                selectOptions += `<option value="${c.id}">${c.codigo} - ${c.nome}</option>`;
            });
            
            const div = document.createElement('div');
            div.className = 'item-linha';
            div.innerHTML = `
                <select name="${tipo == 'DEBITO' ? 'item_debito[]' : 'item_credito[]'}" class="form-control conta-select">
                    ${selectOptions}
                </select>
                <input type="number" name="${tipo == 'DEBITO' ? 'valor_debito[]' : 'valor_credito[]'}" class="form-control" placeholder="Valor" step="0.01" min="0" oninput="atualizarTotais()">
                <button type="button" class="remove-btn" onclick="this.parentElement.remove(); atualizarTotais();">✕</button>
            `;
            container.appendChild(div);
            atualizarTotais();
        }
        
        function atualizarTotais() {
            let totalDeb = 0, totalCred = 0;
            
            document.querySelectorAll('[name^="valor_debito"]').forEach(el => {
                totalDeb += parseFloat(el.value) || 0;
            });
            document.querySelectorAll('[name^="valor_credito"]').forEach(el => {
                totalCred += parseFloat(el.value) || 0;
            });
            
            document.getElementById('totalDebitos').textContent = totalDeb.toFixed(2);
            document.getElementById('totalCreditos').textContent = totalCred.toFixed(2);
            
            const diff = totalDeb - totalCred;
            const diffEl = document.getElementById('diferenca');
            diffEl.textContent = `(Diferença: ${diff.toFixed(2)} Kz)`;
            diffEl.style.color = Math.abs(diff) < 0.01 ? '#2ecc71' : '#e74c3c';
        }
        
        // Atualizar ao carregar
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('[name^="valor_debito"], [name^="valor_credito"]').forEach(el => {
                el.addEventListener('input', atualizarTotais);
            });
            atualizarTotais();
        });
    </script>

    <?php elseif ($acao == 'view' && $lancamento): ?>
    <!-- VISUALIZAR LANÇAMENTO -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <h3 style="margin: 0;">📄 Lançamento #<?= htmlspecialchars($lancamento['numero_lancamento']) ?></h3>
            <span class="status-badge <?= $lancamento['status'] ?>">
                <?= ucfirst($lancamento['status']) ?>
            </span>
        </div>
        
        <div class="row" style="margin-top: 15px;">
            <div>
                <div class="view-info">
                    <div class="label">Data</div>
                    <div class="value"><?= date('d/m/Y', strtotime($lancamento['data_lancamento'])) ?></div>
                </div>
                <div class="view-info">
                    <div class="label">Tipo de Documento</div>
                    <div class="value"><?= $lancamento['tipo_documento'] ?></div>
                </div>
            </div>
            <div>
                <div class="view-info">
                    <div class="label">Usuário</div>
                    <div class="value">#<?= $lancamento['usuario_id'] ?></div>
                </div>
                <div class="view-info">
                    <div class="label">Criado em</div>
                    <div class="value"><?= date('d/m/Y H:i', strtotime($lancamento['created_at'])) ?></div>
                </div>
            </div>
        </div>
        
        <div class="view-info">
            <div class="label">Histórico</div>
            <div class="value"><?= htmlspecialchars($lancamento['historico']) ?></div>
        </div>
        
        <?php if ($lancamento['observacoes']): ?>
        <div class="view-info">
            <div class="label">Observações</div>
            <div class="value"><?= nl2br(htmlspecialchars($lancamento['observacoes'])) ?></div>
        </div>
        <?php endif; ?>
        
        <hr style="margin: 20px 0; border-color: #eef2f7;">
        
        <h4 style="margin-bottom: 15px;">📋 Itens do Lançamento</h4>
        <div class="table-responsive">
            <table class="tabela-itens" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="padding: 10px 15px; text-align: left;">Conta</th>
                        <th style="padding: 10px 15px; text-align: left;">Código</th>
                        <th style="padding: 10px 15px; text-align: right;">Débito</th>
                        <th style="padding: 10px 15px; text-align: right;">Crédito</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $totalDeb = 0;
                    $totalCred = 0;
                    foreach ($itens as $item): 
                        if ($item['tipo_movimento'] == 'DEBITO') $totalDeb += $item['valor'];
                        else $totalCred += $item['valor'];
                    ?>
                    <tr>
                        <td style="padding: 10px 15px;"><?= htmlspecialchars($item['conta_nome']) ?></td>
                        <td style="padding: 10px 15px;"><?= $item['codigo'] ?></td>
                        <td style="padding: 10px 15px; text-align: right; color: #2ecc71;">
                            <?= $item['tipo_movimento'] == 'DEBITO' ? number_format($item['valor'], 2, ',', '.') : '-' ?>
                        </td>
                        <td style="padding: 10px 15px; text-align: right; color: #e74c3c;">
                            <?= $item['tipo_movimento'] == 'CREDITO' ? number_format($item['valor'], 2, ',', '.') : '-' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="font-weight: 700; background: #f8fafc;">
                        <td colspan="2" style="padding: 10px 15px; text-align: right;">TOTAIS</td>
                        <td style="padding: 10px 15px; text-align: right; color: #2ecc71;"><?= number_format($totalDeb, 2, ',', '.') ?></td>
                        <td style="padding: 10px 15px; text-align: right; color: #e74c3c;"><?= number_format($totalCred, 2, ',', '.') ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <?php if (abs($totalDeb - $totalCred) > 0.01): ?>
        <div class="alert alert-error" style="margin-top: 15px;">
            ⚠️ Lançamento desbalanceado! Débitos: <?= number_format($totalDeb, 2, ',', '.') ?> | Créditos: <?= number_format($totalCred, 2, ',', '.') ?>
        </div>
        <?php else: ?>
        <div class="alert alert-success" style="margin-top: 15px;">
            ✅ Lançamento balanceado (Débitos = Créditos)
        </div>
        <?php endif; ?>
        
        <div style="margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="lancamentos.php?acao=editar&id=<?= $lancamento['id'] ?>" class="btn-add" style="border-color: #f5d76e; background: #f5d76e20;">✏️ Editar</a>
            <a href="lancamentos.php?acao=imprimir&id=<?= $lancamento['id'] ?>" class="btn-add" target="_blank">🖨️ Imprimir</a>
            <a href="lancamentos.php" class="btn-add">← Voltar</a>
        </div>
    </div>

    <?php else: ?>
    <!-- LISTAGEM DE LANÇAMENTOS -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;">
            <h3 style="margin: 0;">📋 Todos os Lançamentos</h3>
            <a href="lancamentos.php?acao=novo" class="btn-add" style="border-color: #f5d76e; background: #f5d76e20;">➕ Novo</a>
        </div>
        
        <div class="table-responsive">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th>Nº</th>
                        <th>Data</th>
                        <th>Histórico</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query("
                        SELECT l.*, COUNT(li.id) as total_itens
                        FROM lancamentos_contabeis l
                        LEFT JOIN lancamentos_itens li ON l.id = li.lancamento_id
                        GROUP BY l.id
                        ORDER BY l.data_lancamento DESC
                    ");
                    $lancamentos = $stmt->fetchAll();
                    foreach ($lancamentos as $l):
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($l['numero_lancamento']) ?></strong></td>
                        <td><?= date('d/m/Y', strtotime($l['data_lancamento'])) ?></td>
                        <td><?= htmlspecialchars(substr($l['historico'], 0, 40)) ?></td>
                        <td><?= $l['tipo_documento'] ?></td>
                        <td>
                            <span class="status-badge <?= $l['status'] ?>">
                                <?= ucfirst($l['status']) ?>
                            </span>
                        </td>
                        <td>
                            <a href="lancamentos.php?acao=view&id=<?= $l['id'] ?>" style="color: #3498db; text-decoration: none;">Ver</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($lancamentos)): ?>
                    <tr><td colspan="6" style="text-align: center; color: #94a3b8; padding: 30px;">Nenhum lançamento registrado</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="footer-info">
        <p>© <?= date('Y') ?> <strong>SoftGest</strong> - Módulo de Contabilidade</p>
    </div>
</div>

</body>
</html>
<?php include_once '../../includes/footer.php'; ?>