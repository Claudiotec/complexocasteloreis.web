<?php
// ============================================
// modules/escola/financeiro/mensalidades/gerar.php - Gerar Mensalidades
// ============================================

// Carregar configurações
require_once '../../../../config/database.php';
require_once '../../../../config/app_modes.php';

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
if (!temPermissao('Escola', 'criar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ===== MESES DO ANO =====
$meses = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 
          'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

// ===== FUNÇÃO PARA REMOVER ACENTOS =====
function removerAcentos($string) {
    $mapa = array(
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c',
        'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'Ä' => 'A',
        'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ó' => 'O', 'Ò' => 'O', 'Õ' => 'O', 'Ô' => 'O', 'Ö' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'Ç' => 'C'
    );
    return strtr($string, $mapa);
}

// ===== FUNÇÃO PARA VERIFICAR SE É ITEM AVULSO =====
function isItemAvulso($descricao) {
    $itens_avulsos = ['Folha de Prova', 'Boletim', 'Certificado', 'Declaração', 
                      'Transferência', 'Reconfirmação', 'Matrícula', 'Cartão',
                      'Uniforme', 'Prova', 'Exame', 'Atestado'];
    foreach ($itens_avulsos as $item) {
        if (stripos(removerAcentos($descricao), removerAcentos($item)) !== false) {
            return true;
        }
    }
    return false;
}

// ===== BUSCAR DADOS PARA FILTROS =====
$classes = [];
$turmas = [];
$emolumentos = [];
$alunos = [];

try {
    // Buscar classes
    $stmt = $pdo->query("SELECT DISTINCT classe FROM turmas WHERE classe IS NOT NULL AND classe != '' ORDER BY classe");
    $classes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Buscar turmas
    $stmt = $pdo->query("SELECT id, nome, classe FROM turmas WHERE status = 'ativa' ORDER BY nome");
    $turmas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar emolumentos
    $stmt = $pdo->query("SELECT id, nome, classe, valor, tipo FROM emolumentos WHERE status = 'ativo' ORDER BY nome");
    $emolumentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar alunos
    $stmt = $pdo->query("SELECT id, nome, Classe, TURMA FROM alunos WHERE status = 'ativo' ORDER BY nome");
    $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Erro ao buscar dados: " . $e->getMessage());
}

// ===== PROCESSAR FORMULÁRIO =====
$erro = '';
$sucesso = '';
$mensalidades_geradas = 0;
$alunos_processados = 0;
$itens_adicionados = 0;
$itens_ignorados = 0;

// ===== PROCESSAR POST =====
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ano_inicio = (int) ($_POST['ano_inicio'] ?? date('Y'));
    $ano_fim = (int) ($_POST['ano_fim'] ?? date('Y') + 1);
    $mes_inicio = (int) ($_POST['mes_inicio'] ?? 9);
    $mes_fim = (int) ($_POST['mes_fim'] ?? 8);
    $classe = $_POST['classe'] ?? '';
    $turma_id = $_POST['turma_id'] ?? null;
    $emolumento_id = $_POST['emolumento_id'] ?? null;
    $valor_base = isset($_POST['valor_base']) ? floatval(str_replace(',', '.', $_POST['valor_base'])) : 0;
    $data_vencimento = (int) ($_POST['data_vencimento'] ?? date('d'));
    $descricao = $_POST['descricao'] ?? 'Mensalidade escolar';
    $mes_unico = (int) ($_POST['mes_unico'] ?? 9);
    $ano_unico = (int) ($_POST['ano_unico'] ?? date('Y'));
    $tipo_geracao = $_POST['tipo_geracao'] ?? 'periodo';
    
    // Validar
    $erros = [];
    if ($data_vencimento < 1 || $data_vencimento > 31) $erros[] = 'Dia de vencimento inválido.';
    
    if (empty($erros)) {
        try {
            $pdo->beginTransaction();
            
            // Buscar alunos com filtros
            $sql = "SELECT id, nome, Classe, TURMA FROM alunos WHERE status = 'ativo'";
            $params = [];
            
            if (!empty($classe)) {
                $sql .= " AND Classe = ?";
                $params[] = $classe;
            }
            
            if (!empty($turma_id)) {
                $sql .= " AND TURMA = (SELECT nome FROM turmas WHERE id = ?)";
                $params[] = $turma_id;
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $alunos_filtrados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($alunos_filtrados)) {
                throw new Exception('Nenhum aluno encontrado com os filtros selecionados.');
            }
            
            $alunos_processados = count($alunos_filtrados);
            
            // Buscar emolumento selecionado
            $emolumento_selecionado = null;
            if (!empty($emolumento_id)) {
                $stmt = $pdo->prepare("SELECT id, nome, valor FROM emolumentos WHERE id = ?");
                $stmt->execute([$emolumento_id]);
                $emolumento_selecionado = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            // ===== VERIFICAR SE É ITEM AVULSO =====
            $is_item_avulso = isItemAvulso($descricao);
            
            // ===== GERAR MENSALIDADES =====
            $meses_nomes = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 
                           'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
            
            if ($tipo_geracao == 'unico') {
                // Gerar apenas um mês específico
                foreach ($alunos_filtrados as $aluno) {
                    $valor = $valor_base;
                    $emolumento_usado = null;
                    
                    // Buscar emolumento para este aluno
                    if (!empty($emolumento_selecionado)) {
                        $emolumento_usado = $emolumento_selecionado;
                        if ($valor_base <= 0 && isset($emolumento_selecionado['valor'])) {
                            $valor = $emolumento_selecionado['valor'];
                        }
                    } else {
                        // Buscar emolumento por classe do aluno
                        $stmt_emol = $pdo->prepare("
                            SELECT id, nome, valor FROM emolumentos 
                            WHERE classe = ? AND status = 'ativo'
                            LIMIT 1
                        ");
                        $stmt_emol->execute([$aluno['Classe']]);
                        $emol = $stmt_emol->fetch(PDO::FETCH_ASSOC);
                        
                        if ($emol) {
                            $emolumento_usado = $emol;
                            if ($valor_base <= 0) {
                                $valor = $emol['valor'];
                            }
                        }
                    }
                    
                    // Se valor ainda for 0, usar valor base
                    if ($valor <= 0) {
                        $valor = $valor_base > 0 ? $valor_base : 1000;
                    }
                    
                    // Calcular data de vencimento
                    $ultimo_dia = cal_days_in_month(CAL_GREGORIAN, $mes_unico, $ano_unico);
                    $dia_venc = min($data_vencimento, $ultimo_dia);
                    $data_venc = date("Y-m-d", mktime(0, 0, 0, $mes_unico, $dia_venc, $ano_unico));
                    
                    // Gerar número do documento
                    $prefixo = $is_item_avulso ? 'ITEM' : 'MEN';
                    $num_documento = $prefixo . '-' . $ano_inicio . '/' . $ano_fim . '-' . 
                                    str_pad($aluno['id'], 4, '0', STR_PAD_LEFT) . '-' . 
                                    str_pad($mes_unico, 2, '0', STR_PAD_LEFT);
                    
                    // Verificar se já existe registro para este aluno + mês + ano + tipo
                    $sql_check = "SELECT id FROM mensalidades 
                                  WHERE aluno_id = ? AND mes = ? AND ano = ? 
                                  AND (emolumento_id = ? OR (emolumento_id IS NULL AND ? IS NULL))
                                  LIMIT 1";
                    $params_check = [
                        $aluno['id'],
                        $mes_unico,
                        $ano_unico,
                        $emolumento_usado['id'] ?? null,
                        $emolumento_usado['id'] ?? null
                    ];
                    
                    $stmt_check = $pdo->prepare($sql_check);
                    $stmt_check->execute($params_check);
                    $existe = $stmt_check->fetch();
                    
                    if ($existe) {
                        $itens_ignorados++;
                        continue;
                    }
                    
                    // Inserir
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO mensalidades (
                                aluno_id, turma_id, emolumento_id, mes, ano, 
                                valor, data_vencimento, status, descricao, 
                                num_documento, multa_tipo, multa_valor, 
                                prazo_dias, ano_letivo_inicio, ano_letivo_fim
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pendente', ?, ?, 'percentual', 10, 30, ?, ?)
                        ");
                        
                        $stmt->execute([
                            $aluno['id'],
                            $turma_id ?: null,
                            $emolumento_usado['id'] ?? null,
                            $mes_unico,
                            $ano_unico,
                            $valor,
                            $data_venc,
                            $descricao,
                            $num_documento,
                            $ano_inicio,
                            $ano_fim
                        ]);
                        
                        if ($stmt->rowCount() > 0) {
                            $mensalidades_geradas++;
                        }
                    } catch (Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                            $itens_ignorados++;
                        } else {
                            throw $e;
                        }
                    }
                }
            } else {
                // Gerar período completo
                foreach ($alunos_filtrados as $aluno) {
                    $ano_atual = $ano_inicio;
                    $mes_atual = $mes_inicio;
                    
                    while (true) {
                        $valor = $valor_base;
                        $emolumento_usado = null;
                        
                        // Buscar emolumento para este aluno e mês
                        if (!empty($emolumento_selecionado)) {
                            $emolumento_usado = $emolumento_selecionado;
                            if ($valor_base <= 0 && isset($emolumento_selecionado['valor'])) {
                                $valor = $emolumento_selecionado['valor'];
                            }
                        } else {
                            // Buscar emolumento por classe do aluno e mês
                            $stmt_emol = $pdo->prepare("
                                SELECT id, nome, valor FROM emolumentos 
                                WHERE classe = ? AND mes_referencia = ? AND status = 'ativo'
                                LIMIT 1
                            ");
                            $stmt_emol->execute([$aluno['Classe'], $mes_atual]);
                            $emol = $stmt_emol->fetch(PDO::FETCH_ASSOC);
                            
                            if ($emol) {
                                $emolumento_usado = $emol;
                                if ($valor_base <= 0) {
                                    $valor = $emol['valor'];
                                }
                            }
                        }
                        
                        // Se valor ainda for 0, usar valor base
                        if ($valor <= 0) {
                            $valor = $valor_base > 0 ? $valor_base : 1000;
                        }
                        
                        // Calcular data de vencimento
                        $ultimo_dia = cal_days_in_month(CAL_GREGORIAN, $mes_atual, $ano_atual);
                        $dia_venc = min($data_vencimento, $ultimo_dia);
                        $data_venc = date("Y-m-d", mktime(0, 0, 0, $mes_atual, $dia_venc, $ano_atual));
                        
                        // Gerar número do documento
                        $num_documento = 'MEN-' . $ano_inicio . '/' . $ano_fim . '-' . 
                                        str_pad($aluno['id'], 4, '0', STR_PAD_LEFT) . '-' . 
                                        str_pad($mes_atual, 2, '0', STR_PAD_LEFT);
                        
                        // Verificar se já existe registro para este aluno + mês + ano
                        $sql_check = "SELECT id FROM mensalidades 
                                      WHERE aluno_id = ? AND mes = ? AND ano = ? 
                                      AND (emolumento_id = ? OR (emolumento_id IS NULL AND ? IS NULL))
                                      LIMIT 1";
                        $params_check = [
                            $aluno['id'],
                            $mes_atual,
                            $ano_atual,
                            $emolumento_usado['id'] ?? null,
                            $emolumento_usado['id'] ?? null
                        ];
                        
                        $stmt_check = $pdo->prepare($sql_check);
                        $stmt_check->execute($params_check);
                        $existe = $stmt_check->fetch();
                        
                        if ($existe) {
                            $itens_ignorados++;
                        } else {
                            try {
                                $stmt = $pdo->prepare("
                                    INSERT INTO mensalidades (
                                        aluno_id, turma_id, emolumento_id, mes, ano, 
                                        valor, data_vencimento, status, descricao, 
                                        num_documento, multa_tipo, multa_valor, 
                                        prazo_dias, ano_letivo_inicio, ano_letivo_fim
                                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pendente', ?, ?, 'percentual', 10, 30, ?, ?)
                                ");
                                
                                $stmt->execute([
                                    $aluno['id'],
                                    $turma_id ?: null,
                                    $emolumento_usado['id'] ?? null,
                                    $mes_atual,
                                    $ano_atual,
                                    $valor,
                                    $data_venc,
                                    $descricao,
                                    $num_documento,
                                    $ano_inicio,
                                    $ano_fim
                                ]);
                                
                                if ($stmt->rowCount() > 0) {
                                    $mensalidades_geradas++;
                                }
                            } catch (Exception $e) {
                                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                                    $itens_ignorados++;
                                } else {
                                    throw $e;
                                }
                            }
                        }
                        
                        // Avançar
                        $mes_atual++;
                        if ($mes_atual > 12) {
                            $mes_atual = 1;
                            $ano_atual++;
                        }
                        
                        if ($ano_atual > $ano_fim || ($ano_atual == $ano_fim && $mes_atual > $mes_fim)) {
                            break;
                        }
                    }
                }
            }
            
            $pdo->commit();
            
            $sucesso = "✅ Operação concluída com sucesso!";
            $sucesso .= "<br>👨‍🎓 Alunos processados: <strong>$alunos_processados</strong>";
            $sucesso .= "<br>📄 Registros gerados: <strong>$mensalidades_geradas</strong>";
            
            if ($itens_ignorados > 0) {
                $sucesso .= "<br>⏭️ Registros ignorados (já existentes): <strong>$itens_ignorados</strong>";
            }
            
            if ($tipo_geracao == 'unico') {
                $mes_nome = $meses_nomes[$mes_unico - 1];
                $sucesso .= "<br>📅 Mês: <strong>$mes_nome/$ano_unico</strong>";
            } else {
                $mes_inicio_nome = $meses_nomes[$mes_inicio - 1];
                $mes_fim_nome = $meses_nomes[$mes_fim - 1];
                $sucesso .= "<br>📅 Período: <strong>$mes_inicio_nome/$ano_inicio a $mes_fim_nome/$ano_fim</strong>";
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $erro = '❌ Erro: ' . $e->getMessage();
        }
    } else {
        $erro = implode('<br>', $erros);
    }
}

// ===== INCLUIR HEADER =====
include '../../includes/header_escola.php';
?>

<style>
    .page-header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;margin-bottom:25px}
    .page-header h1{font-size:24px;font-weight:700;color:#1a2332;margin:0}
    .page-header .subtitle{color:#94a3b8;font-size:14px;margin:2px 0 0}
    .btn{padding:8px 20px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;transition:all .3s;display:inline-flex;align-items:center;gap:6px;border:none;cursor:pointer}
    .btn-primary{background:#c9a84c;color:#1a2332}
    .btn-primary:hover{background:#b8973a;transform:translateY(-2px);box-shadow:0 4px 15px rgba(201,168,76,0.3)}
    .btn-secondary{background:#f1f5f9;color:#4a5568}
    .btn-secondary:hover{background:#e2e8f0}
    .btn-success{background:#2ecc71;color:#fff}
    .btn-success:hover{background:#27ae60}
    .btn-warning{background:#f39c12;color:#fff}
    .btn-warning:hover{background:#e67e22}
    .btn-info{background:#3498db;color:#fff}
    .btn-info:hover{background:#2980b9}
    .btn-danger{background:#e74c3c;color:#fff}
    .btn-danger:hover{background:#c0392b}
    .menu-financeiro{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:25px;padding:15px 20px;background:#fff;border-radius:12px;border:1px solid #eef2f7}
    .menu-financeiro a{padding:8px 18px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:500;color:#4a5568;background:#f8fafc;border:1px solid #e2e8f0;display:inline-flex;align-items:center;gap:6px}
    .menu-financeiro a:hover{background:#c9a84c;color:#1a2332;border-color:#c9a84c;transform:translateY(-2px)}
    .menu-financeiro a.active{background:#c9a84c;color:#1a2332;border-color:#c9a84c}
    .form-container{background:#fff;border-radius:12px;padding:30px;border:1px solid #eef2f7;max-width:1000px}
    .form-group{margin-bottom:18px}
    .form-group label{display:block;font-weight:600;margin-bottom:5px;color:#1a2332;font-size:13px}
    .form-group label .required{color:#e74c3c}
    .form-group label .help{font-weight:400;color:#94a3b8;font-size:11px}
    .form-group label .valor-auto{color:#2ecc71;font-weight:400;font-size:12px}
    .form-group input,.form-group select,.form-group textarea{width:100%;padding:10px 14px;border:1px solid #d1d5db;border-radius:8px;font-size:14px}
    .form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:#c9a84c;box-shadow:0 0 0 3px rgba(201,168,76,0.1)}
    .form-group textarea{min-height:60px;resize:vertical}
    .form-group .valor-destaque{background:#fef9e8;border-color:#fde68a}
    .form-row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px}
    .form-row-2{display:grid;grid-template-columns:1fr 1fr;gap:20px}
    .form-row-4{display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:20px}
    .alert{padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:14px}
    .alert-success{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0}
    .alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
    .alert-warning{background:#fef3c7;color:#92400e;border:1px solid #fcd34d}
    .alert-info{background:#dbeafe;color:#1e40af;border:1px solid #93c5fd}
    .form-actions{display:flex;gap:10px;margin-top:20px;flex-wrap:wrap}
    .section-title{font-size:16px;font-weight:700;color:#1a2332;margin:20px 0 15px;padding-bottom:8px;border-bottom:2px solid #eef2f7}
    .section-title .icon{margin-right:8px}
    .resumo-box{background:linear-gradient(135deg,#f8fafc 0%,#eef2f7 100%);padding:20px;border-radius:12px;border:2px solid #c9a84c;margin:15px 0}
    .resumo-box .numero{font-size:28px;font-weight:700;color:#c9a84c}
    .resumo-box .label{font-size:13px;color:#4a5568}
    .tipo-geracao-box{background:#f8fafc;padding:15px;border-radius:8px;border:1px solid #e2e8f0;margin-bottom:15px}
    .tipo-geracao-box .radio-group{display:flex;gap:20px;margin-top:10px}
    .tipo-geracao-box .radio-group label{display:flex;align-items:center;gap:8px;font-weight:400;cursor:pointer}
    .emolumento-info{background:#f0fdf4;padding:8px 12px;border-radius:6px;border-left:3px solid #2ecc71;margin-top:5px;font-size:13px;color:#065f46}
    @media(max-width:768px){
        .page-header{flex-direction:column;align-items:stretch}
        .menu-financeiro{flex-direction:column;align-items:stretch}
        .menu-financeiro a{text-align:center;justify-content:center}
        .form-row{grid-template-columns:1fr;gap:0}
        .form-row-2{grid-template-columns:1fr;gap:0}
        .form-row-4{grid-template-columns:1fr 1fr;gap:10px}
        .form-container{padding:20px}
        .form-actions{flex-direction:column}
        .form-actions .btn{justify-content:center}
        .resumo-box .numero{font-size:20px}
        .tipo-geracao-box .radio-group{flex-direction:column;gap:10px}
    }
</style>

<div class="page-header">
    <div>
        <h1>📅 Gerar Mensalidades</h1>
        <p class="subtitle">Gerar mensalidades para o ano letivo bianual</p>
    </div>
    <a href="index.php" class="btn btn-secondary">← Voltar</a>
</div>

<!-- Menu Financeiro -->
<div class="menu-financeiro">
    <a href="../index.php">📊 Dashboard</a>
    <a href="../pagamentos/">💳 Pagamentos</a>
    <a href="../emolumentos/">📋 Emolumentos</a>
    <a href="index.php" class="active">📅 Mensalidades</a>
    <a href="../contas/">🏦 Contas</a>
    <a href="../fluxo_caixa/">💵 Fluxo de Caixa</a>
    <a href="../relatorios/">📈 Relatórios</a>
</div>

<?php if ($sucesso): ?>
<div class="alert alert-success"><?= $sucesso ?><br><br><a href="index.php" class="btn btn-primary" style="margin-top:5px;">📋 Ver Mensalidades</a></div>
<?php endif; ?>

<?php if ($erro): ?>
<div class="alert alert-error"><strong>❌ Ops! Encontramos alguns problemas:</strong><br><?= $erro ?></div>
<?php endif; ?>

<div class="alert alert-info">
    <strong>📌 Como funciona:</strong><br>
    • <strong>Período:</strong> Gera mensalidades para todos os meses do ano letivo<br>
    • <strong>Mês Único:</strong> Gera apenas para um mês específico<br>
    • <strong>Valor:</strong> Se não informado, busca automaticamente do emolumento<br>
    • <strong>Não duplica:</strong> Verifica se já existe registro para o aluno + mês + ano
</div>

<div class="form-container">
    <form method="POST" id="formGerarMensalidades" enctype="multipart/form-data">
        <!-- ===== TIPO DE GERAÇÃO ===== -->
        <div class="section-title"><span class="icon">⚙️</span> Tipo de Geração</div>
        
        <div class="tipo-geracao-box">
            <div class="radio-group">
                <label>
                    <input type="radio" name="tipo_geracao" value="periodo" <?= ($_POST['tipo_geracao'] ?? 'periodo') == 'periodo' ? 'checked' : '' ?> onchange="toggleTipoGeracao()">
                    📅 Período Completo
                </label>
                <label>
                    <input type="radio" name="tipo_geracao" value="unico" <?= ($_POST['tipo_geracao'] ?? '') == 'unico' ? 'checked' : '' ?> onchange="toggleTipoGeracao()">
                    📌 Mês Único / Item Avulso
                </label>
            </div>
        </div>
        
        <!-- ===== PARÂMETROS DE GERAÇÃO ===== -->
        <div class="section-title"><span class="icon">⚙️</span> Parâmetros de Geração</div>
        
        <!-- Período Completo -->
        <div id="camposPeriodo">
            <div class="form-row-4">
                <div class="form-group">
                    <label>Ano Início <span class="required">*</span></label>
                    <select name="ano_inicio">
                        <?php for($a = date('Y') - 2; $a <= date('Y') + 2; $a++): ?>
                        <option value="<?= $a ?>" <?= ($_POST['ano_inicio'] ?? date('Y')) == $a ? 'selected' : '' ?>><?= $a ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Ano Fim <span class="required">*</span></label>
                    <select name="ano_fim">
                        <?php for($a = date('Y') - 1; $a <= date('Y') + 3; $a++): ?>
                        <option value="<?= $a ?>" <?= ($_POST['ano_fim'] ?? date('Y') + 1) == $a ? 'selected' : '' ?>><?= $a ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Mês Início <span class="required">*</span></label>
                    <select name="mes_inicio">
                        <?php for($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= ($_POST['mes_inicio'] ?? 9) == $i ? 'selected' : '' ?>><?= $meses[$i-1] ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Mês Fim <span class="required">*</span></label>
                    <select name="mes_fim">
                        <?php for($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= ($_POST['mes_fim'] ?? 8) == $i ? 'selected' : '' ?>><?= $meses[$i-1] ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Mês Único -->
        <div id="camposUnico" style="display:none;">
            <div class="form-row-2">
                <div class="form-group">
                    <label>Mês <span class="required">*</span></label>
                    <select name="mes_unico" required>
                        <?php for($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= ($_POST['mes_unico'] ?? date('m')) == $i ? 'selected' : '' ?>><?= $meses[$i-1] ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Ano <span class="required">*</span></label>
                    <select name="ano_unico" required>
                        <?php for($a = date('Y') - 2; $a <= date('Y') + 2; $a++): ?>
                        <option value="<?= $a ?>" <?= ($_POST['ano_unico'] ?? date('Y')) == $a ? 'selected' : '' ?>><?= $a ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="form-row">
            <div class="form-group">
                <label>📚 Classe</label>
                <select name="classe" id="filtroClasse" onchange="carregarTurmasPorClasse()">
                    <option value="">Todas as classes</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>" <?= ($_POST['classe'] ?? '') == $c ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c) ?>ª Classe
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>🏫 Turma</label>
                <select name="turma_id" id="filtroTurma">
                    <option value="">Todas as turmas</option>
                    <?php foreach ($turmas as $turma): ?>
                    <option value="<?= $turma['id'] ?>" data-classe="<?= $turma['classe'] ?>" <?= ($_POST['turma_id'] ?? '') == $turma['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($turma['nome']) ?> (<?= $turma['classe'] ?>ª)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>📋 Emolumento / Categoria</label>
                <select name="emolumento_id" id="filtroEmolumento" onchange="carregarValorEmolumento()">
                    <option value="">Todos os emolumentos</option>
                    <?php foreach ($emolumentos as $emol): ?>
                    <option value="<?= $emol['id'] ?>" data-classe="<?= $emol['classe'] ?>" data-valor="<?= $emol['valor'] ?>" <?= ($_POST['emolumento_id'] ?? '') == $emol['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($emol['nome']) ?> (<?= $emol['classe'] ?>ª) - Kz <?= number_format($emol['valor'], 2, ',', '.') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-row-2">
            <div class="form-group">
                <label>Dia de Vencimento <span class="required">*</span></label>
                <input type="number" name="data_vencimento" value="<?= $_POST['data_vencimento'] ?? date('d') ?>" min="1" max="31" required>
                <span class="help">Dia do mês para vencimento (1-31)</span>
            </div>
            <div class="form-group">
                <label>Valor Base (Kz) <span class="help">(opcional)</span></label>
                <input type="number" step="0.01" name="valor_base" id="valorBase" value="<?= $_POST['valor_base'] ?? '' ?>" placeholder="Deixe em branco para usar o valor do emolumento" min="0">
                <span class="help" id="valorAutoHint">🟢 Se deixar em branco, será carregado automaticamente do emolumento</span>
                <div id="emolumentoInfo" class="emolumento-info" style="display:none;">
                    📌 Valor do emolumento: <strong id="emolumentoValorDisplay">-</strong>
                </div>
            </div>
        </div>
        
        <div class="form-group">
            <label>Descrição <span class="required">*</span></label>
            <input type="text" name="descricao" id="descricao" value="<?= htmlspecialchars($_POST['descricao'] ?? 'Mensalidade escolar') ?>" required>
            <span class="help" id="descricaoHint">
                💡 <strong>Mensalidade escolar</strong> - Gera mensalidades normais<br>
                💡 <strong>Folha de Prova</strong> - Adiciona como item avulso<br>
                💡 <strong>Boletim</strong> - Adiciona como item avulso
            </span>
        </div>
        
        <!-- ===== RESUMO ===== -->
        <div class="section-title"><span class="icon">📊</span> Resumo da Geração</div>
        
        <div class="resumo-box">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr;gap:15px;">
                <div><div class="label">📅 Período</div><div class="numero" id="resumoPeriodo" style="font-size:18px;">-</div></div>
                <div><div class="label">💰 Valor</div><div class="numero" id="resumoValor">Kz 0,00</div></div>
                <div><div class="label">📄 Total de Meses</div><div class="numero" id="resumoMeses">-</div></div>
                <div><div class="label">👨‍🎓 Alunos</div><div class="numero" id="resumoAlunos">-</div></div>
                <div><div class="label">📋 Total Registros</div><div class="numero" id="resumoTotal">-</div></div>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-success" id="btnGerar">✅ Gerar Mensalidades</button>
            <button type="button" class="btn btn-warning" onclick="calcularResumo()">📊 Calcular Resumo</button>
            <button type="button" class="btn btn-info" onclick="preencherPadrao()">📅 Ano Letivo Atual</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script>
// ===== VARIÁVEIS =====
var alunosData = [];
<?php foreach ($alunos as $a): ?>
alunosData.push({id: <?= $a['id'] ?>, nome: '<?= addslashes($a['nome']) ?>', classe: '<?= addslashes($a['Classe']) ?>', turma: '<?= addslashes($a['TURMA']) ?>'});
<?php endforeach; ?>

var emolumentosData = [];
<?php foreach ($emolumentos as $e): ?>
emolumentosData.push({id: <?= $e['id'] ?>, nome: '<?= addslashes($e['nome']) ?>', classe: '<?= addslashes($e['classe']) ?>', valor: <?= $e['valor'] ?>});
<?php endforeach; ?>

// ===== FUNÇÃO PARA CARREGAR TURMAS POR CLASSE =====
function carregarTurmasPorClasse() {
    var classe = document.getElementById('filtroClasse').value;
    var turmaSelect = document.getElementById('filtroTurma');
    var options = turmaSelect.querySelectorAll('option[data-classe]');
    
    turmaSelect.value = '';
    
    options.forEach(function(opt) {
        if (classe === '' || opt.dataset.classe === classe) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
        }
    });
    
    calcularResumo();
}

// ===== FUNÇÃO PARA CARREGAR VALOR DO EMOLUMENTO =====
function carregarValorEmolumento() {
    var emolumentoId = document.getElementById('filtroEmolumento').value;
    var valorBase = document.getElementById('valorBase');
    var infoDiv = document.getElementById('emolumentoInfo');
    var valorDisplay = document.getElementById('emolumentoValorDisplay');
    
    if (emolumentoId) {
        var emol = emolumentosData.find(function(e) { return e.id == emolumentoId; });
        if (emol) {
            valorDisplay.textContent = 'Kz ' + emol.valor.toFixed(2).replace('.', ',');
            infoDiv.style.display = 'block';
            
            if (!valorBase.value || valorBase.value == '0') {
                valorBase.placeholder = 'Kz ' + emol.valor.toFixed(2).replace('.', ',');
                document.getElementById('valorAutoHint').innerHTML = '✅ Valor carregado automaticamente: <strong>Kz ' + emol.valor.toFixed(2).replace('.', ',') + '</strong>';
            }
        }
    } else {
        infoDiv.style.display = 'none';
        valorBase.placeholder = 'Deixe em branco para usar o valor do emolumento';
        document.getElementById('valorAutoHint').innerHTML = '🟢 Se deixar em branco, será carregado automaticamente do emolumento';
    }
    
    // Carregar classe do emolumento
    if (emolumentoId) {
        var emol = emolumentosData.find(function(e) { return e.id == emolumentoId; });
        if (emol && emol.classe) {
            document.getElementById('filtroClasse').value = emol.classe;
            carregarTurmasPorClasse();
        }
    }
    
    calcularResumo();
}

// ===== TOGGLE TIPO GERAÇÃO =====
function toggleTipoGeracao() {
    var tipo = document.querySelector('input[name="tipo_geracao"]:checked').value;
    document.getElementById('camposPeriodo').style.display = tipo == 'periodo' ? 'block' : 'none';
    document.getElementById('camposUnico').style.display = tipo == 'unico' ? 'block' : 'none';
    calcularResumo();
}

// ===== PREENCHER PADRÃO =====
function preencherPadrao() {
    var anoAtual = new Date().getFullYear();
    var mesAtual = new Date().getMonth() + 1;
    
    if (mesAtual >= 9) {
        document.querySelector('select[name="ano_inicio"]').value = anoAtual;
        document.querySelector('select[name="ano_fim"]').value = anoAtual + 1;
        document.querySelector('select[name="mes_inicio"]').value = 9;
        document.querySelector('select[name="mes_fim"]').value = 8;
    } else {
        document.querySelector('select[name="ano_inicio"]').value = anoAtual - 1;
        document.querySelector('select[name="ano_fim"]').value = anoAtual;
        document.querySelector('select[name="mes_inicio"]').value = 9;
        document.querySelector('select[name="mes_fim"]').value = 8;
    }
    calcularResumo();
}

// ===== CALCULAR RESUMO =====
function calcularResumo() {
    var tipo = document.querySelector('input[name="tipo_geracao"]:checked').value;
    var valorBase = parseFloat(document.getElementById('valorBase').value) || 0;
    var emolumentoId = document.getElementById('filtroEmolumento').value;
    var classe = document.getElementById('filtroClasse').value;
    var turmaId = document.getElementById('filtroTurma').value;
    var meses = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
    
    // Buscar valor do emolumento
    var valorEmolumento = 0;
    if (emolumentoId) {
        var emol = emolumentosData.find(function(e) { return e.id == emolumentoId; });
        if (emol) valorEmolumento = emol.valor;
    }
    
    var valorFinal = valorBase > 0 ? valorBase : valorEmolumento;
    
    document.getElementById('resumoValor').textContent = 'Kz ' + (valorFinal > 0 ? valorFinal.toFixed(2).replace('.', ',') : '0,00');
    
    var totalMeses = 0;
    var periodoTexto = '';
    
    if (tipo == 'periodo') {
        var mesInicio = parseInt(document.querySelector('select[name="mes_inicio"]').value) || 9;
        var mesFim = parseInt(document.querySelector('select[name="mes_fim"]').value) || 8;
        var anoInicio = parseInt(document.querySelector('select[name="ano_inicio"]').value) || 0;
        var anoFim = parseInt(document.querySelector('select[name="ano_fim"]').value) || 0;
        
        var ano = anoInicio, mes = mesInicio;
        while (true) {
            totalMeses++;
            mes++;
            if (mes > 12) { mes = 1; ano++; }
            if (ano > anoFim || (ano == anoFim && mes > mesFim)) break;
        }
        
        periodoTexto = meses[mesInicio-1] + '/' + anoInicio + ' a ' + meses[mesFim-1] + '/' + anoFim;
    } else {
        var mesUnico = parseInt(document.querySelector('select[name="mes_unico"]').value) || 1;
        var anoUnico = parseInt(document.querySelector('select[name="ano_unico"]').value) || 0;
        totalMeses = 1;
        periodoTexto = meses[mesUnico-1] + '/' + anoUnico;
    }
    
    // Contar alunos
    var totalAlunos = 0;
    alunosData.forEach(function(aluno) {
        var match = true;
        if (classe && aluno.classe != classe) match = false;
        if (turmaId) {
            var turmaNome = document.querySelector('#filtroTurma option[value="' + turmaId + '"]')?.textContent || '';
            if (turmaNome && !aluno.turma.includes(turmaNome.replace(/\(.*\)/, '').trim())) match = false;
        }
        if (match) totalAlunos++;
    });
    
    document.getElementById('resumoPeriodo').textContent = periodoTexto;
    document.getElementById('resumoMeses').textContent = totalMeses + ' mes' + (totalMeses > 1 ? 'es' : '');
    document.getElementById('resumoAlunos').textContent = totalAlunos;
    document.getElementById('resumoTotal').textContent = (totalAlunos * totalMeses) + ' registros';
}

// ===== ATUALIZAR HINT DA DESCRIÇÃO =====
function atualizarHintDescricao() {
    var descricao = document.getElementById('descricao').value;
    var hint = document.getElementById('descricaoHint');
    var itensAvulsos = ['Folha de Prova', 'Boletim', 'Certificado', 'Declaração', 
                        'Transferência', 'Reconfirmação', 'Matrícula', 'Cartão',
                        'Uniforme', 'Prova', 'Exame', 'Atestado'];
    
    var isAvulso = false;
    for (var i = 0; i < itensAvulsos.length; i++) {
        if (descricao.toLowerCase().includes(itensAvulsos[i].toLowerCase())) {
            isAvulso = true;
            break;
        }
    }
    
    if (isAvulso) {
        hint.innerHTML = '🔵 <strong>Item Avulso</strong> - Será ADICIONADO aos registros existentes sem duplicar.';
        hint.style.color = '#1e40af';
    } else {
        hint.innerHTML = '🟢 <strong>Mensalidade Normal</strong> - Será gerada normalmente.';
        hint.style.color = '#065f46';
    }
}

// ===== EVENTOS =====
document.getElementById('descricao').addEventListener('input', atualizarHintDescricao);
document.getElementById('valorBase').addEventListener('input', calcularResumo);

document.addEventListener('DOMContentLoaded', function() {
    toggleTipoGeracao();
    preencherPadrao();
    atualizarHintDescricao();
    carregarValorEmolumento();
    
    var inputs = document.querySelectorAll('input, select');
    inputs.forEach(function(input) {
        input.addEventListener('change', calcularResumo);
        input.addEventListener('input', calcularResumo);
    });
    
    document.getElementById('formGerarMensalidades').addEventListener('submit', function(e) {
        var descricao = document.getElementById('descricao').value;
        var itensAvulsos = ['Folha de Prova', 'Boletim', 'Certificado', 'Declaração', 
                            'Transferência', 'Reconfirmação', 'Matrícula', 'Cartão',
                            'Uniforme', 'Prova', 'Exame', 'Atestado'];
        var isAvulso = false;
        for (var i = 0; i < itensAvulsos.length; i++) {
            if (descricao.toLowerCase().includes(itensAvulsos[i].toLowerCase())) {
                isAvulso = true;
                break;
            }
        }
        
        var mensagem = '⚠️ Atenção!\n\n';
        if (isAvulso) {
            mensagem += '📌 <strong>ITEM AVULSO</strong>\n';
            mensagem += 'Este item será ADICIONADO aos registros existentes.\n';
            mensagem += 'Registros duplicados serão ignorados automaticamente.\n\n';
        } else {
            mensagem += '📅 <strong>MENSALIDADE NORMAL</strong>\n';
            mensagem += 'Serão geradas mensalidades para todos os alunos.\n';
            mensagem += 'Registros já existentes serão ignorados.\n\n';
        }
        
        var totalAlunos = parseInt(document.getElementById('resumoAlunos').textContent) || 0;
        var totalMeses = parseInt(document.getElementById('resumoMeses').textContent) || 0;
        mensagem += '📊 Resumo:\n';
        mensagem += '👨‍🎓 Alunos: ' + totalAlunos + '\n';
        mensagem += '📄 Meses: ' + totalMeses + '\n';
        mensagem += '📋 Total: ' + (totalAlunos * totalMeses) + ' registros\n\n';
        mensagem += 'Deseja continuar?';
        
        if (!confirm(mensagem)) {
            e.preventDefault();
            return false;
        }
        
        document.getElementById('btnGerar').disabled = true;
        document.getElementById('btnGerar').innerHTML = '⏳ Gerando...';
    });
});
</script>

<?php include '../../includes/footer_escola.php'; ?>