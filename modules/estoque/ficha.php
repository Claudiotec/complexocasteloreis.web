<?php
require_once '../../config/database.php';

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ?");
$stmt->execute([$id]);
$produto = $stmt->fetch();

if (!$produto) {
    die("Produto não encontrado");
}

$empresa = getEmpresa();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha do Produto - <?= htmlspecialchars($produto['nome']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            padding: 40px;
        }
        .ficha-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .ficha-header {
            background: linear-gradient(135deg, #1a2332, #2c3e50);
            color: white;
            padding: 30px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .ficha-header h1 {
            font-size: 24px;
            font-weight: 700;
        }
        .ficha-header h1 span {
            color: #f5d76e;
        }
        .ficha-header .empresa-info {
            text-align: right;
            font-size: 14px;
            opacity: 0.8;
        }
        .ficha-body {
            padding: 40px;
        }
        .ficha-title {
            text-align: center;
            font-size: 22px;
            font-weight: 700;
            color: #1a2332;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 3px solid #f5d76e;
        }
        .ficha-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 5px 30px;
        }
        .ficha-item {
            padding: 12px 0;
            border-bottom: 1px solid #f0f2f5;
        }
        .ficha-item .label {
            font-size: 12px;
            text-transform: uppercase;
            color: #94a3b8;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .ficha-item .value {
            font-size: 16px;
            color: #1e293b;
            font-weight: 500;
            margin-top: 2px;
        }
        .ficha-item.full {
            grid-column: 1 / -1;
        }
        .ficha-item .value.empty {
            color: #94a3b8;
            font-style: italic;
            font-weight: 400;
        }
        .ficha-status {
            display: inline-block;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        .ficha-status.normal {
            background: #d1fae5;
            color: #065f46;
        }
        .ficha-status.baixo {
            background: #fee2e2;
            color: #991b1b;
        }
        .ficha-footer {
            background: #f8fafc;
            padding: 20px 40px;
            text-align: center;
            font-size: 13px;
            color: #94a3b8;
            border-top: 1px solid #eef2f7;
        }
        .ficha-footer strong {
            color: #c9a84c;
        }
        .btn-actions {
            text-align: center;
            margin-top: 20px;
        }
        .btn-print {
            background: #1a2332;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-print:hover {
            background: #2c3e50;
            transform: scale(1.02);
        }
        .btn-voltar {
            background: #e2e8f0;
            color: #475569;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-voltar:hover {
            background: #cbd5e1;
        }
        
        @media print {
            body { background: white; padding: 0; }
            .ficha-container { box-shadow: none; border-radius: 0; }
            .btn-actions { display: none !important; }
            .ficha-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .ficha-status { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
        
        @media (max-width: 600px) {
            .ficha-grid { grid-template-columns: 1fr; }
            .ficha-header { flex-direction: column; text-align: center; gap: 10px; }
            .ficha-header .empresa-info { text-align: center; }
            .ficha-body { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="ficha-container">
        <!-- Header -->
        <div class="ficha-header">
            <div>
                <h1><?= htmlspecialchars($empresa['nome_fantasia'] ?? 'SoftGest') ?> <span>Web</span></h1>
                <div style="font-size: 13px; opacity: 0.7;">Sistema de Gestão Empresarial</div>
            </div>
            <div class="empresa-info">
                <div><?= htmlspecialchars($empresa['razao_social'] ?? '') ?></div>
                <div>CNPJ: <?= htmlspecialchars($empresa['cnpj'] ?? '') ?></div>
                <div><?= htmlspecialchars($empresa['telefone'] ?? '') ?></div>
            </div>
        </div>
        
        <!-- Body -->
        <div class="ficha-body">
            <div class="ficha-title">📦 FICHA DO PRODUTO</div>
            
            <div class="ficha-grid">
                <div class="ficha-item">
                    <div class="label">Código</div>
                    <div class="value"><?= htmlspecialchars($produto['codigo']) ?></div>
                </div>
                <div class="ficha-item">
                    <div class="label">Categoria</div>
                    <div class="value <?= empty($produto['categoria']) ? 'empty' : '' ?>">
                        <?= htmlspecialchars($produto['categoria'] ?? 'Não informado') ?>
                    </div>
                </div>
                
                <div class="ficha-item full">
                    <div class="label">Nome do Produto</div>
                    <div class="value" style="font-size: 20px; font-weight: 700; color: #1a2332;">
                        <?= htmlspecialchars($produto['nome']) ?>
                    </div>
                </div>
                
                <div class="ficha-item full">
                    <div class="label">Descrição</div>
                    <div class="value <?= empty($produto['descricao']) ? 'empty' : '' ?>">
                        <?= nl2br(htmlspecialchars($produto['descricao'] ?? 'Não informado')) ?>
                    </div>
                </div>
                
                <div class="ficha-item">
                    <div class="label">Quantidade em Estoque</div>
                    <div class="value" style="font-size: 20px; font-weight: 700; color: #2c3e50;">
                        <?= $produto['quantidade'] ?>
                    </div>
                </div>
                <div class="ficha-item">
                    <div class="label">Estoque Mínimo</div>
                    <div class="value"><?= $produto['estoque_minimo'] ?></div>
                </div>
                
                <div class="ficha-item">
                    <div class="label">Status do Estoque</div>
                    <div class="value">
                        <?php if ($produto['quantidade'] <= $produto['estoque_minimo']): ?>
                            <span class="ficha-status baixo">⚠️ Estoque Baixo</span>
                        <?php else: ?>
                            <span class="ficha-status normal">✅ Estoque Normal</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="ficha-item">
                    <div class="label">Fornecedor</div>
                    <div class="value <?= empty($produto['fornecedor']) ? 'empty' : '' ?>">
                        <?= htmlspecialchars($produto['fornecedor'] ?? 'Não informado') ?>
                    </div>
                </div>
                
                <div class="ficha-item">
                    <div class="label">Preço de Compra</div>
                    <div class="value">R$ <?= number_format($produto['preco_compra'], 2, ',', '.') ?></div>
                </div>
                <div class="ficha-item">
                    <div class="label">Preço de Venda</div>
                    <div class="value" style="font-size: 20px; font-weight: 700; color: #c9a84c;">
                        R$ <?= number_format($produto['preco_venda'], 2, ',', '.') ?>
                    </div>
                </div>
                
                <div class="ficha-item">
                    <div class="label">Margem de Lucro</div>
                    <div class="value">
                        <?php 
                        $margem = $produto['preco_compra'] > 0 ? (($produto['preco_venda'] - $produto['preco_compra']) / $produto['preco_compra']) * 100 : 0;
                        echo number_format($margem, 1) . '%';
                        ?>
                    </div>
                </div>
                <div class="ficha-item">
                    <div class="label">Localização</div>
                    <div class="value <?= empty($produto['localizacao']) ? 'empty' : '' ?>">
                        <?= htmlspecialchars($produto['localizacao'] ?? 'Não informado') ?>
                    </div>
                </div>
                
                <?php if ($produto['peso'] > 0): ?>
                <div class="ficha-item">
                    <div class="label">Peso (kg)</div>
                    <div class="value"><?= number_format($produto['peso'], 3, ',', '.') ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($produto['dimensoes']): ?>
                <div class="ficha-item">
                    <div class="label">Dimensões</div>
                    <div class="value"><?= htmlspecialchars($produto['dimensoes']) ?></div>
                </div>
                <?php endif; ?>
                
                <div class="ficha-item full">
                    <div class="label">Data de Cadastro</div>
                    <div class="value"><?= date('d/m/Y H:i', strtotime($produto['created_at'])) ?></div>
                </div>
                
                <?php if ($produto['observacoes']): ?>
                <div class="ficha-item full">
                    <div class="label">Observações</div>
                    <div class="value"><?= nl2br(htmlspecialchars($produto['observacoes'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="ficha-footer">
            <p>
                Documento gerado em <?= date('d/m/Y H:i:s') ?> - 
                <strong><?= htmlspecialchars($empresa['nome_fantasia'] ?? 'SoftGest Web') ?></strong>
            </p>
            <p style="font-size: 11px; color: #cbd5e1; margin-top: 4px;">
                Ficha técnica do produto - SoftGest Web v2.0
            </p>
        </div>
    </div>
    
    <div class="btn-actions">
        <button onclick="window.print()" class="btn-print">🖨️ Imprimir Ficha</button>
        <a href="index.php" class="btn-voltar">← Voltar</a>
    </div>
    
    <script>
        // Carregar automaticamente a impressão (opcional)
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>