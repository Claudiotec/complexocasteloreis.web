<?php
// ============================================
// modules/escola/relatorios/frequencia_imprimir.php - Versão A4 para Impressão
// ============================================

// Os dados já foram carregados no arquivo principal
// $frequencias, $resumo, $nome_turma, $mes_filtro, $ano_filtro já estão disponíveis
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Frequência - Complexo Escolar Castelo Reis</title>
    
    <style>
        /* ============================================
           ESTILOS PARA IMPRESSÃO PROFISSIONAL A4
           ============================================ */
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Times New Roman', Times, serif;
            background: #ffffff;
            color: #1a1a1a;
            line-height: 1.4;
        }
        
        .pagina-a4 {
            max-width: 210mm;
            margin: 0 auto;
            padding: 15mm 20mm;
            background: #ffffff;
            min-height: 297mm;
        }
        
        /* ===== CABEÇALHO ===== */
        .cabecalho {
            border-bottom: 3px solid #1a3c6e;
            padding-bottom: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .cabecalho-esquerda {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo-empresa {
            width: 70px;
            height: 70px;
            background: #1a3c6e;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 28px;
            font-weight: bold;
            flex-shrink: 0;
        }
        
        .info-empresa h1 {
            font-size: 22px;
            color: #1a3c6e;
            font-weight: 700;
            letter-spacing: 1px;
            margin: 0;
        }
        
        .info-empresa .subtitulo {
            font-size: 12px;
            color: #4a5568;
            font-weight: normal;
            letter-spacing: 2px;
        }
        
        .info-empresa .endereco {
            font-size: 10px;
            color: #6c757d;
            margin-top: 2px;
        }
        
        .cabecalho-direita {
            text-align: right;
            border-left: 2px solid #1a3c6e;
            padding-left: 15px;
        }
        
        .cabecalho-direita .titulo-relatorio {
            font-size: 16px;
            font-weight: 700;
            color: #1a3c6e;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .cabecalho-direita .numero-relatorio {
            font-size: 11px;
            color: #4a5568;
        }
        
        .cabecalho-direita .data-relatorio {
            font-size: 11px;
            color: #6c757d;
        }
        
        /* ===== INFORMAÇÕES DO RELATÓRIO ===== */
        .info-relatorio {
            background: #f8f9fa;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px 18px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px 40px;
        }
        
        .info-relatorio .item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
        }
        
        .info-relatorio .item .label {
            font-weight: 600;
            color: #4a5568;
        }
        
        .info-relatorio .item .valor {
            color: #1a2332;
            font-weight: 500;
        }
        
        /* ===== RESUMO EM GRID ===== */
        .resumo-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 10px;
            margin-bottom: 25px;
        }
        
        .resumo-card {
            background: #f8f9fa;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px 10px;
            text-align: center;
        }
        
        .resumo-card .number {
            font-size: 24px;
            font-weight: 700;
            color: #1a3c6e;
        }
        
        .resumo-card .label {
            font-size: 10px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }
        
        .resumo-card.total .number { color: #1a3c6e; }
        .resumo-card.presentes .number { color: #27ae60; }
        .resumo-card.ausentes .number { color: #e74c3c; }
        .resumo-card.justificados .number { color: #f39c12; }
        .resumo-card.atrasados .number { color: #2980b9; }
        .resumo-card.percentual .number { color: #8e44ad; }
        
        /* ===== TABELA ===== */
        .tabela-container {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .tabela {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        
        .tabela thead {
            background: #1a3c6e;
            color: #ffffff;
        }
        
        .tabela thead th {
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #1a3c6e;
        }
        
        .tabela tbody tr {
            border-bottom: 1px solid #e2e8f0;
        }
        
        .tabela tbody tr:hover {
            background: #f8f9fa;
        }
        
        .tabela tbody td {
            padding: 8px 12px;
            vertical-align: middle;
        }
        
        .tabela tbody tr:last-child {
            border-bottom: none;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .status-presente {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-ausente {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .status-justificado {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-atrasado {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .aluno-nao-encontrado {
            color: #e74c3c;
            font-style: italic;
        }
        
        /* ===== RODAPÉ ===== */
        .rodape {
            border-top: 2px solid #1a3c6e;
            padding-top: 15px;
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 10px;
            color: #6c757d;
        }
        
        .rodape .assinaturas {
            display: flex;
            gap: 40px;
        }
        
        .rodape .assinatura {
            text-align: center;
        }
        
        .rodape .assinatura .linha {
            width: 150px;
            border-top: 1px solid #1a1a1a;
            margin: 20px auto 5px;
        }
        
        .rodape .assinatura .cargo {
            font-size: 9px;
            color: #4a5568;
        }
        
        .rodape .info-sistema {
            text-align: right;
        }
        
        /* ===== MENSAGEM VAZIA ===== */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
        }
        
        .empty-state .icon {
            font-size: 48px;
            display: block;
            margin-bottom: 10px;
        }
        
        .empty-state h3 {
            font-size: 16px;
            color: #4a5568;
        }
        
        /* ============================================
           ESTILOS DE IMPRESSÃO
           ============================================ */
        @media print {
            body {
                background: #ffffff;
                margin: 0;
                padding: 0;
            }
            
            .pagina-a4 {
                padding: 12mm 15mm;
                max-width: 100%;
                min-height: auto;
            }
            
            .resumo-grid {
                page-break-inside: avoid;
            }
            
            .tabela-container {
                page-break-inside: auto;
            }
            
            .tabela tbody tr {
                page-break-inside: avoid;
            }
            
            .cabecalho {
                border-bottom: 3px solid #1a3c6e !important;
            }
            
            .rodape {
                border-top: 2px solid #1a3c6e !important;
                position: fixed;
                bottom: 0;
                width: 100%;
                background: white;
                padding: 10px 15mm;
            }
        }
        
        @media (max-width: 768px) {
            .pagina-a4 {
                padding: 10px;
            }
            
            .resumo-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .cabecalho {
                flex-direction: column;
                text-align: center;
            }
            
            .cabecalho-esquerda {
                flex-direction: column;
            }
            
            .cabecalho-direita {
                border-left: none;
                padding-left: 0;
                text-align: center;
            }
            
            .info-relatorio {
                flex-direction: column;
                gap: 5px;
            }
            
            .rodape {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .rodape .assinaturas {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>

<div class="pagina-a4">
    
    <!-- ===== CABEÇALHO ===== -->
    <div class="cabecalho">
        <div class="cabecalho-esquerda">
            <div class="logo-empresa">🏫</div>
            <div class="info-empresa">
                <h1>COMPLEXO ESCOLAR CASTELO REIS</h1>
                <div class="subtitulo">ENSINO PRIMÁRIO E SECUNDÁRIO</div>
                <div class="endereco">
                    📍 Luanda, Angola | 📞 +244 923 294 402 | ✉ casteloreis@escola.ao
                </div>
            </div>
        </div>
        <div class="cabecalho-direita">
            <div class="titulo-relatorio">Relatório de Frequência</div>
            <div class="numero-relatorio">Nº: <?= date('Y') . '/' . str_pad($resumo['total'], 4, '0', STR_PAD_LEFT) ?></div>
            <div class="data-relatorio">Data: <?= date('d/m/Y H:i:s') ?></div>
        </div>
    </div>
    
    <!-- ===== INFORMAÇÕES ===== -->
    <div class="info-relatorio">
        <div class="item">
            <span class="label">📋 Turma:</span>
            <span class="valor"><?= $nome_turma ?></span>
        </div>
        <div class="item">
            <span class="label">📅 Período:</span>
            <span class="valor">
                <?php 
                if (!empty($mes_filtro) && !empty($ano_filtro)) {
                    echo date('F', mktime(0, 0, 0, $mes_filtro, 1, 2000)) . ' / ' . $ano_filtro;
                } else {
                    echo 'Todos os períodos';
                }
                ?>
            </span>
        </div>
        <div class="item">
            <span class="label">📊 Total de Registros:</span>
            <span class="valor"><?= $resumo['total'] ?></span>
        </div>
        <div class="item">
            <span class="label">📈 Taxa de Presença:</span>
            <span class="valor"><?= $resumo['percentual_presenca'] ?>%</span>
        </div>
    </div>
    
    <!-- ===== RESUMO ===== -->
    <div class="resumo-grid">
        <div class="resumo-card total">
            <div class="number"><?= $resumo['total'] ?></div>
            <div class="label">Total</div>
        </div>
        <div class="resumo-card presentes">
            <div class="number"><?= $resumo['presentes'] ?></div>
            <div class="label">✅ Presentes</div>
        </div>
        <div class="resumo-card ausentes">
            <div class="number"><?= $resumo['ausentes'] ?></div>
            <div class="label">❌ Ausentes</div>
        </div>
        <div class="resumo-card justificados">
            <div class="number"><?= $resumo['justificados'] ?></div>
            <div class="label">📋 Justificados</div>
        </div>
        <div class="resumo-card atrasados">
            <div class="number"><?= $resumo['atrasados'] ?></div>
            <div class="label">⏰ Atrasados</div>
        </div>
        <div class="resumo-card percentual">
            <div class="number"><?= $resumo['percentual_presenca'] ?>%</div>
            <div class="label">Taxa Presença</div>
        </div>
    </div>
    
    <!-- ===== TABELA ===== -->
    <div class="tabela-container">
        <table class="tabela">
            <thead>
                <tr>
                    <th style="width: 40px;">#</th>
                    <th style="width: 100px;">Data</th>
                    <th>Aluno</th>
                    <th>Turma</th>
                    <th style="width: 120px;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($frequencias) > 0): ?>
                    <?php $contador = 1; ?>
                    <?php foreach ($frequencias as $f): ?>
                        <tr>
                            <td><?= $contador++ ?></td>
                            <td><?= $f['data_formatada'] ?></td>
                            <td>
                                <?php if (empty($f['aluno_nome'])): ?>
                                    <span class="aluno-nao-encontrado">⚠️ Aluno #<?= $f['aluno_id'] ?></span>
                                <?php else: ?>
                                    <strong><?= htmlspecialchars($f['aluno_nome']) ?></strong>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($f['turma_nome'] ?? 'Turma #' . $f['turma_id']) ?></td>
                            <td>
                                <span class="status-badge status-<?= strtolower($f['status'] ?? 'presente') ?>">
                                    <?= $f['status_descricao'] ?? ucfirst($f['status'] ?? 'Presente') ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <span class="icon">📭</span>
                                <h3>Nenhum registro encontrado</h3>
                                <p>Não há dados para os filtros selecionados.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- ===== RODAPÉ ===== -->
    <div class="rodape">
        <div class="assinaturas">
            <div class="assinatura">
                <div class="linha"></div>
                <div class="cargo">Diretor Pedagógico</div>
            </div>
            <div class="assinatura">
                <div class="linha"></div>
                <div class="cargo">Coordenador Académico</div>
            </div>
        </div>
        <div class="info-sistema">
            <div>📄 Relatório gerado em <?= date('d/m/Y H:i:s') ?></div>
            <div>© <?= date('Y') ?> Complexo Escolar Castelo Reis</div>
            <div style="font-size: 8px; color: #94a3b8;">Sistema de Gestão Escolar v1.0</div>
        </div>
    </div>
</div>

<script>
// Abrir impressão automaticamente quando carregar
window.onload = function() {
    window.print();
};
</script>

</body>
</html>