<?php
// ============================================
// boletins.php - Boletins de Notas (VERSÃO COMPLETA)
// ============================================

session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit;
}

// Verifica se é professor ou coordenador
$perfil = $_SESSION['usuario_perfil'] ?? 'usuario';
// Aceita professor, docente e coordenador pedagógico
if ($perfil != 'professor' && $perfil != 'docente' && $perfil != 'Coordenador Pedagógico' && $perfil != 'coordenador_pedagogico' && $perfil != 'coordenador') {
    header("Location: ../index.php");
    exit;
}

// Incluir configurações - caminho absoluto
require_once $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/config/database.php';

// ==========================================
// FUNÇÃO PARA OBTER CAMINHO DOS DOCUMENTS
// ==========================================
function getDocumentsPath() {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows
        $home = getenv('USERPROFILE');
        if ($home) {
            return $home . DIRECTORY_SEPARATOR . 'Documents';
        }
        $username = getenv('USERNAME');
        return 'C:' . DIRECTORY_SEPARATOR . 'Users' . DIRECTORY_SEPARATOR . $username . DIRECTORY_SEPARATOR . 'Documents';
    } else {
        // Linux/Mac
        return getenv('HOME') . DIRECTORY_SEPARATOR . 'Documents';
    }
}

// ==========================================
// CRIAR DIRETÓRIO PARA BOLETINS
// ==========================================
$documents_path = getDocumentsPath();
$boletins_dir = $documents_path . DIRECTORY_SEPARATOR . 'boletins_softgest';

if (!is_dir($boletins_dir)) {
    mkdir($boletins_dir, 0777, true);
}

// ==========================================
// DADOS DO USUÁRIO
// ==========================================
$nome = $_SESSION['usuario_nome'] ?? 'Usuário';
$email = $_SESSION['usuario_email'] ?? '';
$page_title = 'Boletins de Notas';
$active_page = 'boletins';

// ==========================================
// BUSCAR DADOS DA EMPRESA
// ==========================================
$escola_nome = 'Sistema de Gestão Escolar';
$empresa_dados = [];
$insignia_base64 = '';
$endereco_completo = '';
$telefone_empresa = '';
$email_empresa = '';
$cnpj_empresa = '';

try {
    $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($empresa) {
        $escola_nome = $empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? 'Sistema de Gestão Escolar';
        $empresa_dados = $empresa;
        $endereco_completo = $empresa['endereco'] ?? '';
        $telefone_empresa = $empresa['telefone'] ?? '';
        $email_empresa = $empresa['email'] ?? '';
        $cnpj_empresa = $empresa['cnpj'] ?? '';
        $logo_empresa = $empresa['logo'] ?? '';
        
        if (!empty($logo_empresa)) {
            $logo_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/empresa/' . $logo_empresa;
            if (file_exists($logo_path)) {
                $image_data = file_get_contents($logo_path);
                $insignia_base64 = 'data:image/' . pathinfo($logo_path, PATHINFO_EXTENSION) . ';base64,' . base64_encode($image_data);
            }
        }
    }
} catch (Exception $e) {}

// ==========================================
// BUSCAR PROFESSOR
// ==========================================
$professor_num_agente = null;
$professor_id = null;
try {
    if (!empty($email)) {
        $stmt = $pdo->prepare("SELECT id, num_agente, nome FROM funcionarios WHERE email = ? AND status = 'ativo' LIMIT 1");
        $stmt->execute([$email]);
        $professor = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($professor) {
            $professor_id = $professor['id'];
            $professor_num_agente = $professor['num_agente'];
            $nome = $professor['nome'] ?? $nome;
        }
    }
} catch (Exception $e) {}

// ==========================================
// BUSCAR TURMAS - MODIFICADO PARA COORDENADOR
// ==========================================
$turmas = [];
$perfil = $_SESSION['usuario_perfil'] ?? 'usuario';
$isCoordenador = in_array($perfil, ['Coordenador Pedagógico', 'coordenador_pedagogico', 'coordenador_pedagógico', 'coordenador']);

try {
    if ($isCoordenador) {
        // Coordenador: busca TODAS as turmas
        $stmt = $pdo->query("SELECT id, nome, classe, disciplinas FROM turmas ORDER BY classe, nome");
        $todasTurmas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($todasTurmas as $turma) {
            $turmas[] = [
                'id' => $turma['id'],
                'nome' => $turma['nome'],
                'classe' => $turma['classe'],
                'disciplinas' => $turma['disciplinas']
            ];
        }
    } elseif ($professor_num_agente) {
        // Professor: busca apenas suas turmas
        $stmt = $pdo->prepare("SELECT * FROM destribuicao_professores WHERE professor_id = ? AND tipo = 'PROFESSOR' ORDER BY turma_nome");
        $stmt->execute([$professor_num_agente]);
        $distribuicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($distribuicoes as $dist) {
            $turmas[] = [
                'id' => $dist['turma_id'],
                'nome' => $dist['turma_nome'],
                'classe' => $dist['classe'],
                'disciplinas' => $dist['disciplinas']
            ];
        }
    }
} catch (Exception $e) {
    error_log("Erro ao buscar turmas: " . $e->getMessage());
}

// ==========================================
// PROCESSAR REQUISIÇÃO
// ==========================================
$aluno_id = isset($_GET['aluno_id']) ? intval($_GET['aluno_id']) : 0;
$turma_selecionada = isset($_GET['turma']) ? intval($_GET['turma']) : 0;
$aluno_dados = null;
$notas_aluno = [];
$faltas_aluno = [];
$disciplinas_turma = [];

// Processar geração múltipla
$multi_alunos = isset($_POST['alunos_selecionados']) ? $_POST['alunos_selecionados'] : [];
$gerar_todos = isset($_POST['gerar_todos']) ? true : false;
$acao = isset($_POST['acao']) ? $_POST['acao'] : '';

// Processar geração de múltiplos boletins
if ($acao == 'gerar_multi' && !empty($multi_alunos) && $turma_selecionada > 0) {
    $boletins_gerados = [];
    $erros = [];
    $total_gerados = 0;
    
    foreach ($multi_alunos as $id_aluno) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM alunos WHERE id = ? AND status = 'ativo'");
            $stmt->execute([$id_aluno]);
            $aluno = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($aluno) {
                $turma_nome = $aluno['TURMA'] ?? '';
                $classe = $aluno['Classe'] ?? '';
                
                $stmt = $pdo->prepare("SELECT * FROM notas_alunos WHERE id_aluno = ? AND turma = ? AND classe = ?");
                $stmt->execute([$id_aluno, $turma_nome, $classe]);
                $notas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $ano_atual = date('Y');
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as total_faltas, disciplina_id 
                    FROM frequencia 
                    WHERE aluno_id = ? AND YEAR(data) = ? AND status = 'ausente'
                    GROUP BY disciplina_id
                ");
                $stmt->execute([$id_aluno, $ano_atual]);
                $faltas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $html_boletim = gerarBoletimHTML($aluno, $notas, $faltas, $empresa_dados, $insignia_base64);
                
                $nome_arquivo = 'boletim_' . $aluno['id'] . '_' . date('Ymd_His') . '.html';
                $caminho_arquivo = $boletins_dir . DIRECTORY_SEPARATOR . $nome_arquivo;
                file_put_contents($caminho_arquivo, $html_boletim);
                
                $boletins_gerados[] = [
                    'id' => $aluno['id'],
                    'nome' => $aluno['nome'],
                    'arquivo' => $nome_arquivo,
                    'caminho' => $caminho_arquivo
                ];
                $total_gerados++;
            }
        } catch (Exception $e) {
            $erros[] = "Erro ao gerar boletim para ID $id_aluno: " . $e->getMessage();
        }
    }
    
    $_SESSION['boletins_gerados'] = $boletins_gerados;
    $_SESSION['erros_boletins'] = $erros;
    
    if ($gerar_todos && !empty($boletins_gerados)) {
        $zip = new ZipArchive();
        $zip_nome = 'boletins_' . date('Ymd_His') . '.zip';
        $zip_caminho = $boletins_dir . DIRECTORY_SEPARATOR . $zip_nome;
        
        if ($zip->open($zip_caminho, ZipArchive::CREATE) === TRUE) {
            foreach ($boletins_gerados as $b) {
                if (file_exists($b['caminho'])) {
                    $zip->addFile($b['caminho'], $b['arquivo']);
                }
            }
            $zip->close();
            
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zip_nome . '"');
            header('Content-Length: ' . filesize($zip_caminho));
            readfile($zip_caminho);
            exit;
        }
    }
    
    $mensagem = $total_gerados . " boletins gerados com sucesso em: " . $boletins_dir;
    header("Location: boletins.php?turma=" . $turma_selecionada . "&gerados=" . $total_gerados . "&mensagem=" . urlencode($mensagem));
    exit;
}

// BUSCAR DADOS DO ALUNO SELECIONADO
if ($aluno_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM alunos WHERE id = ? AND status = 'ativo'");
        $stmt->execute([$aluno_id]);
        $aluno_dados = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($aluno_dados) {
            $turma_nome = $aluno_dados['TURMA'] ?? '';
            $classe = $aluno_dados['Classe'] ?? '';
            
            // Buscar disciplinas da turma
            $stmt = $pdo->prepare("SELECT disciplinas FROM turmas WHERE nome = ?");
            $stmt->execute([$turma_nome]);
            $turma_info = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($turma_info && !empty($turma_info['disciplinas'])) {
                $disciplinas_turma = array_map('trim', explode(',', $turma_info['disciplinas']));
            }
            
            // Buscar notas do aluno
            $stmt = $pdo->prepare("SELECT * FROM notas_alunos WHERE id_aluno = ? AND turma = ? AND classe = ?");
            $stmt->execute([$aluno_id, $turma_nome, $classe]);
            $notas_aluno = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Buscar faltas
            $ano_atual = date('Y');
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total_faltas, disciplina_id 
                FROM frequencia 
                WHERE aluno_id = ? AND YEAR(data) = ? AND status = 'ausente'
                GROUP BY disciplina_id
            ");
            $stmt->execute([$aluno_id, $ano_atual]);
            $faltas_aluno = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar dados do aluno: " . $e->getMessage());
    }
}

// Buscar todos os alunos da turma para o select
$alunos_turma = [];
if ($turma_selecionada > 0) {
    try {
        // Buscar nome da turma pelo ID
        $stmt = $pdo->prepare("SELECT nome FROM turmas WHERE id = ?");
        $stmt->execute([$turma_selecionada]);
        $turma_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($turma_info) {
            $turma_nome = $turma_info['nome'];
            $stmt = $pdo->prepare("SELECT id, nome, Sexo FROM alunos WHERE TURMA = ? AND status = 'ativo' ORDER BY nome");
            $stmt->execute([$turma_nome]);
            $alunos_turma = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar alunos da turma: " . $e->getMessage());
    }
}

// ==========================================
// FUNÇÃO PARA GERAR BOLETIM HTML
// ==========================================

function gerarBoletimHTML($aluno, $notas, $faltas, $empresa, $insignia) {
    global $pdo;
    
    $escola_nome = $empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? 'Sistema de Gestão Escolar';
    $endereco = $empresa['endereco'] ?? '';
    $telefone = $empresa['telefone'] ?? '';
    $email = $empresa['email'] ?? '';
    $cnpj = $empresa['cnpj'] ?? '';
    
    $classe = $aluno['Classe'] ?? '';
    $sistema = getSistemaAvaliacao($classe);
    $maxPontos = $sistema['max'];
    $minAprovacao = $sistema['min_aprovacao'];
    
    $total_media = 0;
    $count_media = 0;
    $aprovadas = 0;
    $reprovadas = 0;
    $total_faltas = 0;
    $contador = 0;
    $linhas_tabela = '';
    
    // Agrupar notas por disciplina
    $notas_por_disciplina = [];
    foreach ($notas as $nota) {
        $notas_por_disciplina[$nota['disciplina']] = $nota;
    }
    
    // Buscar disciplinas da turma
    $disciplinas_turma = [];
    if (!empty($aluno['TURMA'])) {
        try {
            $stmt = $pdo->prepare("SELECT disciplinas FROM turmas WHERE nome = ?");
            $stmt->execute([$aluno['TURMA']]);
            $turma_info = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($turma_info && !empty($turma_info['disciplinas'])) {
                $disciplinas_turma = array_map('trim', explode(',', $turma_info['disciplinas']));
            }
        } catch (Exception $e) {}
    }
    
    // Se não encontrou disciplinas da turma, usar as que têm notas
    if (empty($disciplinas_turma)) {
        $disciplinas_turma = array_keys($notas_por_disciplina);
    }
    
    foreach ($disciplinas_turma as $disciplina) {
        $nota = $notas_por_disciplina[$disciplina] ?? null;
        $contador++;
        
        if ($nota) {
            $mt1 = floatval($nota['mt1'] ?? 0);
            $mt2 = floatval($nota['mt2'] ?? 0);
            $mt3 = floatval($nota['mt3'] ?? 0);
            $mfd = floatval($nota['mfd'] ?? 0);
            
            if ($mfd == 0 && ($mt1 > 0 || $mt2 > 0 || $mt3 > 0)) {
                $medias = [];
                if ($mt1 > 0) $medias[] = $mt1;
                if ($mt2 > 0) $medias[] = $mt2;
                if ($mt3 > 0) $medias[] = $mt3;
                $mfd = !empty($medias) ? array_sum($medias) / count($medias) : 0;
            }
            
            $classificacao = getClassificacaoBoletim($mfd, $maxPontos);
            
            $nota_class = $mfd >= $minAprovacao ? 'nota-positiva' : 'nota-negativa';
            $mfd_display = $mfd > 0 ? number_format($mfd, 1) : '-';
            $mt1_display = $mt1 > 0 ? number_format($mt1, 1) : '-';
            $mt2_display = $mt2 > 0 ? number_format($mt2, 1) : '-';
            $mt3_display = $mt3 > 0 ? number_format($mt3, 1) : '-';
            
            $faltas_disc = 0;
            foreach ($faltas as $f) {
                if ($f['disciplina_id'] == $nota['disciplina']) {
                    $faltas_disc = $f['total_faltas'];
                    $total_faltas += $faltas_disc;
                    break;
                }
            }
            
            if ($mfd > 0) {
                $total_media += $mfd;
                $count_media++;
                if ($mfd >= $minAprovacao) $aprovadas++;
                else $reprovadas++;
            }
            
            $linhas_tabela .= '
            <tr>
                <td>'.$contador.'</td>
                <td style="text-align:left;font-weight:600;color:#1a2332;">'.htmlspecialchars($disciplina).'</td>
                <td>'.$mt1_display.'</td>
                <td>'.$mt2_display.'</td>
                <td>'.$mt3_display.'</td>
                <td class="'.$nota_class.'">'.$mfd_display.'</td>
                <td>'.$classificacao.'</td>
                <td>'.($faltas_disc > 0 ? $faltas_disc : '-').'</td>
            </tr>';
        } else {
            // Disciplina sem notas
            $linhas_tabela .= '
            <tr>
                <td>'.$contador.'</td>
                <td style="text-align:left;font-weight:600;color:#1a2332;">'.htmlspecialchars($disciplina).'</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
            </tr>';
        }
    }
    
    $media_geral = $count_media > 0 ? $total_media / $count_media : 0;
    $situacao_final = 'Aguardando avaliação';
    $situacao_class = 'aguardando';
    
    if ($count_media > 0) {
        if ($media_geral >= $minAprovacao && $reprovadas == 0) {
            $situacao_final = 'APROVADO';
            $situacao_class = 'aprovado';
        } elseif ($media_geral >= $minAprovacao && $reprovadas > 0) {
            $situacao_final = 'RECUPERAÇÃO';
            $situacao_class = 'recuperacao';
        } elseif ($media_geral >= $minAprovacao * 0.5 && $media_geral < $minAprovacao) {
            $situacao_final = 'RECUPERAÇÃO';
            $situacao_class = 'recuperacao';
        } elseif ($media_geral < $minAprovacao * 0.5 && $reprovadas > 0) {
            $situacao_final = 'REPROVADO';
            $situacao_class = 'reprovado';
        } else {
            $situacao_final = 'NÃO TRANSITA';
            $situacao_class = 'nao-transita';
        }
    }
    
    $insignia_html = $insignia ? '<img src="'.$insignia.'" style="max-width:80px;max-height:80px;object-fit:contain;">' : '';
    
    $html = '
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Boletim - '.htmlspecialchars($aluno['nome']).'</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: "Segoe UI", Arial, sans-serif; padding: 30px; background: white; }
            .boletim-header { text-align: center; border-bottom: 3px solid #c9a84c; padding-bottom: 20px; margin-bottom: 25px; }
            .boletim-header .escola-nome { font-size: 22px; font-weight: 700; color: #1a2332; text-transform: uppercase; }
            .boletim-header .subtitulo { font-size: 18px; font-weight: 600; color: #c9a84c; margin-top: 5px; }
            .boletim-header .info { font-size: 13px; color: #64748b; margin-top: 8px; }
            .boletim-header .endereco { font-size: 12px; color: #64748b; margin-top: 3px; }
            .boletim-aluno { display: flex; gap: 20px; padding: 15px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 20px; flex-wrap: wrap; align-items: center; }
            .boletim-aluno .foto { width: 70px; height: 70px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 30px; color: #94a3b8; flex-shrink: 0; border: 3px solid #c9a84c; overflow: hidden; }
            .boletim-aluno .foto img { width: 100%; height: 100%; object-fit: cover; }
            .boletim-aluno .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 20px; flex: 1; font-size: 14px; }
            .boletim-aluno .info-grid .label { color: #64748b; }
            .boletim-aluno .info-grid .value { font-weight: 600; color: #1a2332; }
            .boletim-table { width: 100%; border-collapse: collapse; font-size: 14px; margin: 15px 0; }
            .boletim-table th { background: #1a2332; color: white; padding: 10px 12px; text-align: center; border: 1px solid #1a2332; font-weight: 600; font-size: 13px; }
            .boletim-table td { padding: 8px 10px; border: 1px solid #e2e8f0; text-align: center; font-size: 13px; }
            .boletim-table tr:nth-child(even) { background: #f8fafc; }
            .boletim-table .disciplina-nome { text-align: left; font-weight: 600; color: #1a2332; }
            .boletim-table .nota-positiva { color: #22c55e; font-weight: 700; }
            .boletim-table .nota-negativa { color: #ef4444; font-weight: 700; }
            .boletim-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-top: 15px; }
            .boletim-summary .item { background: #f8fafc; padding: 10px 14px; border-radius: 6px; border: 1px solid #e2e8f0; text-align: center; }
            .boletim-summary .item .number { font-size: 24px; font-weight: 700; color: #1a2332; }
            .boletim-summary .item .label { font-size: 11px; color: #64748b; margin-top: 2px; }
            .boletim-summary .item.aprovado .number { color: #22c55e; }
            .boletim-summary .item.reprovado .number { color: #ef4444; }
            .boletim-summary .item.faltas .number { color: #f59e0b; }
            .situacao-final { margin-top: 15px; padding: 12px; border-radius: 6px; text-align: center; border: 1px solid; }
            .situacao-aprovado { background: #d1fae5; border-color: #bbf7d0; color: #065f46; }
            .situacao-reprovado { background: #fee2e2; border-color: #fecaca; color: #991b1b; }
            .situacao-recuperacao { background: #fef3c7; border-color: #fde68a; color: #92400e; }
            .situacao-nao-transita { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
            .situacao-aguardando { background: #e2e8f0; border-color: #cbd5e1; color: #475569; }
            .assinaturas { display: flex; justify-content: space-between; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
            .assinaturas .item { text-align: center; flex: 1; }
            .assinaturas .linha { border-top: 1px solid #1a2332; width: 80%; margin: 0 auto; padding-top: 5px; }
            .assinaturas .label { font-size: 11px; color: #64748b; margin-top: 4px; }
            .rodape { margin-top: 15px; text-align: center; font-size: 10px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 8px; }
            .insignia-container { display: flex; justify-content: center; align-items: center; gap: 10px; margin-bottom: 5px; }
            .insignia-container .insignia { max-width: 80px; max-height: 80px; object-fit: contain; border-radius: 50%; border: 2px solid #c9a84c; padding: 4px; }
            @media print { body { padding: 10px; } }
        </style>
    </head>
    <body>
        <div class="boletim-header">
            <div class="insignia-container">'.$insignia_html.'</div>
            <div class="escola-nome">'.htmlspecialchars($escola_nome).'</div>
            <div class="subtitulo">BOLETIM DE NOTAS</div>
            <div class="info">
                Ano Letivo: '.date('Y').'/'.(date('Y') + 1).' | 
                Emitido em: '.date('d/m/Y H:i:s').'
            </div>
            <div class="endereco">'.htmlspecialchars($endereco).' | Tel: '.htmlspecialchars($telefone).' | Email: '.htmlspecialchars($email).' | NIF: '.htmlspecialchars($cnpj).'</div>
        </div>

        <div class="boletim-aluno">
            <div class="foto">👤</div>
            <div class="info-grid">
                <div><span class="label">Nome:</span> <span class="value">'.htmlspecialchars($aluno['nome']).'</span></div>
                <div><span class="label">Classe:</span> <span class="value">'.htmlspecialchars($aluno['Classe']).'</span></div>
                <div><span class="label">Turma:</span> <span class="value">'.htmlspecialchars($aluno['TURMA']).'</span></div>
                <div><span class="label">Sexo:</span> <span class="value">'.htmlspecialchars($aluno['Sexo']).'</span></div>
                <div><span class="label">Nº BI:</span> <span class="value">'.htmlspecialchars($aluno['N_BI']).'</span></div>
                <div><span class="label">Sistema:</span> <span class="value">'.$maxPontos.' pontos</span></div>
            </div>
        </div>

        <table class="boletim-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th style="text-align:left;">Disciplina</th>
                    <th>1º Trim.</th>
                    <th>2º Trim.</th>
                    <th>3º Trim.</th>
                    <th>Média Final</th>
                    <th>Classificação</th>
                    <th>Faltas</th>
                </tr>
            </thead>
            <tbody>
                '.$linhas_tabela.'
            </tbody>
        </table>

        <div class="boletim-summary">
            <div class="item aprovado">
                <div class="number">'.$aprovadas.'</div>
                <div class="label">✅ Aprovadas</div>
            </div>
            <div class="item reprovado">
                <div class="number">'.$reprovadas.'</div>
                <div class="label">❌ Reprovadas</div>
            </div>
            <div class="item">
                <div class="number">'.($count_media > 0 ? number_format($media_geral, 1) : 'N/A').'</div>
                <div class="label">📊 Média Geral</div>
            </div>
            <div class="item faltas">
                <div class="number">'.$total_faltas.'</div>
                <div class="label">📝 Faltas</div>
            </div>
        </div>

        <div class="situacao-final situacao-'.$situacao_class.'">
            <strong style="font-size:18px;">
                '.($situacao_final == 'APROVADO' ? '✅ APROVADO - Parabéns!' : 
                  ($situacao_final == 'RECUPERAÇÃO' ? '📚 RECUPERAÇÃO - Continue se esforçando!' : 
                   ($situacao_final == 'REPROVADO' ? '❌ REPROVADO - Dedique-se mais!' : 
                    ($situacao_final == 'NÃO TRANSITA' ? '⚠️ NÃO TRANSITA - Necessita melhorar!' : '📋 Aguardando avaliação')))).'
            </strong>
            <div style="font-size:13px;color:#64748b;margin-top:4px;">
                Média Geral: '.($count_media > 0 ? number_format($media_geral, 1) : 'N/A').' | 
                Disciplinas: '.$count_media.' | 
                Aprovadas: '.$aprovadas.' | 
                Reprovadas: '.$reprovadas.'
            </div>
        </div>

        <div class="assinaturas">
            <div class="item"><div class="linha"></div><div class="label">O Professor</div></div>
            <div class="item"><div class="linha"></div><div class="label">O Director Pedagógico</div></div>
            <div class="item"><div class="linha"></div><div class="label">O Encarregado</div></div>
        </div>

        <div class="rodape">
            Documento gerado eletronicamente - '.date('d/m/Y H:i:s').' | '.htmlspecialchars($escola_nome).'
        </div>
    </body>
    </html>';
    
    return $html;
}

// ==========================================
// FUNÇÕES AUXILIARES
// ==========================================

function getSistemaAvaliacao($classe) {
    $classeNum = intval(preg_replace('/[^0-9]/', '', $classe));
    if ($classeNum <= 6) {
        return ['max' => 10, 'min_aprovacao' => 5, 'tipo' => 'Pré a 6ª'];
    } else {
        return ['max' => 20, 'min_aprovacao' => 10, 'tipo' => '7ª a 12ª'];
    }
}

function getClassificacaoBoletim($media, $maxPontos) {
    if ($media === null || $media <= 0) return '';
    if ($maxPontos == 10) {
        if ($media >= 9) return 'MUITO BOM';
        elseif ($media >= 7) return 'BOM';
        elseif ($media >= 5) return 'SUFICIENTE';
        elseif ($media >= 3) return 'MEDÍOCRE';
        else return 'MAU';
    } else {
        if ($media >= 18) return 'MUITO BOM';
        elseif ($media >= 14) return 'BOM';
        elseif ($media >= 10) return 'SUFICIENTE';
        elseif ($media >= 5) return 'MEDÍOCRE';
        else return 'MAU';
    }
}

// ==========================================
// INCLUIR CABEÇALHO E SIDEBAR - CORRIGIDO
// ==========================================

// Define caminho base
$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';

// Tenta incluir os arquivos
$sidebar_paths = [
    $base_path . '/includes/sidebar.php',
    __DIR__ . '/../../includes/sidebar.php',
    __DIR__ . '/../includes/sidebar.php'
];

$sidebar_included = false;
foreach ($sidebar_paths as $path) {
    if (file_exists($path)) {
        include $path;
        $sidebar_included = true;
        break;
    }
}

if (!$sidebar_included) {
    // Fallback: incluir do diretório atual
    $fallback_paths = [
        'includes/sidebar.php',
        '../includes/sidebar.php',
        '../../includes/sidebar.php'
    ];
    foreach ($fallback_paths as $path) {
        if (file_exists($path)) {
            include $path;
            $sidebar_included = true;
            break;
        }
    }
}

// Se ainda não encontrou, exibe erro amigável
if (!$sidebar_included) {
    echo '<div style="color:red;padding:20px;">⚠️ Arquivo sidebar.php não encontrado. Verifique o caminho.</div>';
}

?>
<!-- ==========================================
   ESTILOS
   ========================================== -->
<style>
    .boletim-wrapper { max-width: 1000px; margin: 0 auto; padding: 20px; }
    .boletim-card { background: white; border-radius: 12px; padding: 30px; margin-bottom: 25px; border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
    .boletim-header { text-align: center; border-bottom: 2px solid #c9a84c; padding-bottom: 20px; margin-bottom: 25px; }
    .boletim-header .escola-nome { font-size: 22px; font-weight: 700; color: #1a2332; text-transform: uppercase; }
    .boletim-header .subtitulo { font-size: 18px; font-weight: 600; color: #c9a84c; margin-top: 5px; }
    .boletim-header .info { font-size: 13px; color: #64748b; margin-top: 8px; }
    .boletim-header .endereco { font-size: 12px; color: #64748b; margin-top: 3px; }
    .boletim-aluno { display: flex; gap: 20px; padding: 15px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 20px; flex-wrap: wrap; align-items: center; }
    .boletim-aluno .foto { width: 80px; height: 80px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 32px; color: #94a3b8; flex-shrink: 0; border: 3px solid #c9a84c; overflow: hidden; }
    .boletim-aluno .foto img { width: 100%; height: 100%; object-fit: cover; }
    .boletim-aluno .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 20px; flex: 1; font-size: 14px; }
    .boletim-aluno .info-grid .label { color: #64748b; }
    .boletim-aluno .info-grid .value { font-weight: 600; color: #1a2332; }
    .boletim-table { width: 100%; border-collapse: collapse; font-size: 14px; margin: 15px 0; }
    .boletim-table th { background: #1a2332; color: white; padding: 10px 12px; text-align: center; border: 1px solid #1a2332; font-weight: 600; font-size: 13px; }
    .boletim-table td { padding: 8px 10px; border: 1px solid #e2e8f0; text-align: center; font-size: 13px; }
    .boletim-table tr:nth-child(even) { background: #f8fafc; }
    .boletim-table .disciplina-nome { text-align: left; font-weight: 600; color: #1a2332; }
    .boletim-table .nota-positiva { color: #22c55e; font-weight: 700; }
    .boletim-table .nota-negativa { color: #ef4444; font-weight: 700; }
    .boletim-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-top: 20px; }
    .boletim-summary .item { background: #f8fafc; padding: 12px 16px; border-radius: 8px; border: 1px solid #e2e8f0; text-align: center; }
    .boletim-summary .item .number { font-size: 28px; font-weight: 700; color: #1a2332; }
    .boletim-summary .item .label { font-size: 12px; color: #64748b; margin-top: 2px; }
    .boletim-summary .item.aprovado .number { color: #22c55e; }
    .boletim-summary .item.reprovado .number { color: #ef4444; }
    .boletim-summary .item.faltas .number { color: #f59e0b; }
    .situacao-final { margin-top: 20px; padding: 15px; border-radius: 8px; text-align: center; border: 1px solid; }
    .situacao-aprovado { background: #d1fae5; border-color: #bbf7d0; color: #065f46; }
    .situacao-reprovado { background: #fee2e2; border-color: #fecaca; color: #991b1b; }
    .situacao-recuperacao { background: #fef3c7; border-color: #fde68a; color: #92400e; }
    .situacao-nao-transita { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
    .situacao-aguardando { background: #e2e8f0; border-color: #cbd5e1; color: #475569; }
    .boletim-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0; justify-content: center; }
    .boletim-actions .btn { padding: 10px 25px; border-radius: 8px; font-weight: 600; font-size: 14px; border: none; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
    .boletim-actions .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
    .boletim-actions .btn-whatsapp { background: #25D366; color: white; }
    .boletim-actions .btn-whatsapp:hover { background: #1da851; }
    .boletim-actions .btn-pdf { background: #dc3545; color: white; }
    .boletim-actions .btn-pdf:hover { background: #c82333; }
    .boletim-actions .btn-print { background: #6c757d; color: white; }
    .boletim-actions .btn-print:hover { background: #5a6268; }
    .boletim-actions .btn-excel { background: #217346; color: white; }
    .boletim-actions .btn-excel:hover { background: #1a5e38; }
    .boletim-actions .btn-back { background: #e2e8f0; color: #1a2332; }
    .boletim-actions .btn-back:hover { background: #cbd5e1; }
    .boletim-actions .btn-multi { background: #8b5cf6; color: white; }
    .boletim-actions .btn-multi:hover { background: #7c3aed; }
    .filtros-boletim { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 25px; border: 1px solid #e2e8f0; }
    .filtros-boletim .row { display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; }
    .filtros-boletim .form-group { flex: 1; min-width: 180px; }
    .filtros-boletim .form-group label { display: block; font-size: 13px; font-weight: 600; color: #1a2332; margin-bottom: 4px; }
    .filtros-boletim .form-group select { width: 100%; padding: 8px 12px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px; background: white; transition: border-color 0.3s; }
    .filtros-boletim .form-group select:focus { border-color: #c9a84c; outline: none; }
    .filtros-boletim .btn-buscar { padding: 10px 30px; background: #c9a84c; color: #1a2332; border: none; border-radius: 6px; font-weight: 600; font-size: 14px; cursor: pointer; transition: all 0.3s; min-width: 120px; }
    .filtros-boletim .btn-buscar:hover { background: #b8973a; transform: translateY(-2px); }
    .selecao-multi { background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; margin-top: 15px; }
    .selecao-multi .aluno-checkbox { display: flex; align-items: center; gap: 8px; padding: 4px 8px; cursor: pointer; border-radius: 4px; transition: background 0.2s; }
    .selecao-multi .aluno-checkbox:hover { background: #e2e8f0; }
    .selecao-multi .aluno-checkbox input[type="checkbox"] { width: 16px; height: 16px; accent-color: #c9a84c; }
    .selecao-multi .aluno-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 4px; max-height: 200px; overflow-y: auto; padding: 8px; background: white; border-radius: 6px; border: 1px solid #e2e8f0; }
    .empty-boletim { text-align: center; padding: 60px 20px; background: white; border-radius: 12px; border: 2px dashed #e2e8f0; }
    .empty-boletim .icon { font-size: 64px; margin-bottom: 20px; color: #94a3b8; }
    .empty-boletim h3 { color: #1a2332; margin-bottom: 10px; }
    .empty-boletim p { color: #64748b; }
    .insignia-container { display: flex; justify-content: center; align-items: center; gap: 10px; margin-bottom: 5px; }
    .insignia-container .insignia { max-width: 80px; max-height: 80px; object-fit: contain; border-radius: 50%; border: 2px solid #c9a84c; padding: 4px; }
    
    .mensagem-sucesso {
        background: #d1fae5;
        border: 1px solid #bbf7d0;
        color: #065f46;
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .mensagem-sucesso i { font-size: 24px; }
    
    @media (max-width: 768px) {
        .boletim-aluno .info-grid { grid-template-columns: 1fr; }
        .boletim-actions { flex-direction: column; }
        .boletim-actions .btn { justify-content: center; }
        .filtros-boletim .row { flex-direction: column; }
        .filtros-boletim .form-group { min-width: 100%; }
        .boletim-table { font-size: 11px; }
        .boletim-table th, .boletim-table td { padding: 5px 6px; }
        .selecao-multi .aluno-grid { grid-template-columns: 1fr; }
    }
    @media print {
        .header, .filters-card, .filtros-boletim, .boletim-actions, .sidebar, .topbar, .back-btn, .btn-back, .no-print { display: none !important; }
        .main-content { margin-left: 0 !important; padding: 0 !important; }
        .boletim-card { box-shadow: none !important; border: none !important; padding: 10px !important; }
        .boletim-wrapper { padding: 0 !important; }
        .boletim-table th { background: #1a2332 !important; color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { background: white !important; }
    }
</style>

<!-- ==========================================
   CONTEÚDO PRINCIPAL
   ========================================== -->
<main class="main-content">
    <div class="topbar animate-fade-up">
        <div class="topbar-left">
            <div class="page-title">
                <h2>📄 Boletins de Notas</h2>
                <p>Visualize, imprima e compartilhe boletins por WhatsApp</p>
            </div>
        </div>
        <div class="topbar-right">
            <div class="date-time">
                <div class="time"><?= date('H:i:s') ?></div>
                <div><?= date('d/m/Y') ?></div>
            </div>
            <div class="status-indicator">
                <span class="dot"></span> Online
            </div>
        </div>
    </div>

    <div class="container" style="padding:20px;">

        <?php if (isset($_GET['mensagem'])): ?>
        <div class="mensagem-sucesso">
            <i class="fas fa-check-circle"></i>
            <div>
                <strong>✅ Sucesso!</strong> <?= htmlspecialchars($_GET['mensagem']) ?>
                <br>
                <small style="color:#065f46;">📍 Local: <?= htmlspecialchars($boletins_dir) ?></small>
            </div>
        </div>
        <?php endif; ?>

        <!-- FILTROS - TURMA -->
        <div class="filtros-boletim no-print">
            <form method="GET" action="">
                <div class="row">
                    <div class="form-group">
                        <label><i class="fas fa-users"></i> Turma</label>
                        <select name="turma" onchange="this.form.submit()">
                            <option value="">Selecione uma turma</option>
                            <?php foreach ($turmas as $turma): ?>
                                <option value="<?= $turma['id'] ?>" <?= ($turma_selecionada == $turma['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($turma['nome']) ?> - <?= htmlspecialchars($turma['classe']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="display:flex;gap:10px;align-items:flex-end;">
                        <button type="submit" class="btn-buscar">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                        <?php if ($turma_selecionada > 0): ?>
                            <a href="boletins.php" class="btn-buscar" style="background:#e2e8f0;color:#1a2332;">
                                <i class="fas fa-times"></i> Limpar
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <?php if ($turma_selecionada > 0 && !empty($alunos_turma)): ?>
        
        <!-- SELEÇÃO DE ALUNOS -->
        <div class="filtros-boletim no-print">
            <form method="GET" action="">
                <input type="hidden" name="turma" value="<?= $turma_selecionada ?>">
                <div class="row">
                    <div class="form-group" style="flex:2;">
                        <label><i class="fas fa-user-graduate"></i> Selecione um aluno</label>
                        <select name="aluno_id" onchange="this.form.submit()" style="width:100%;padding:8px 12px;border:2px solid #e2e8f0;border-radius:6px;font-size:14px;">
                            <option value="">-- Selecione um aluno --</option>
                            <?php foreach ($alunos_turma as $aluno): ?>
                                <option value="<?= $aluno['id'] ?>" <?= ($aluno_id == $aluno['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($aluno['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-buscar" style="margin-bottom:2px;">
                        <i class="fas fa-eye"></i> Visualizar
                    </button>
                </div>
            </form>
            
            <!-- Geração múltipla -->
            <form method="POST" action="" id="formMultiBoletins">
                <input type="hidden" name="turma" value="<?= $turma_selecionada ?>">
                <input type="hidden" name="acao" value="gerar_multi">
                
                <div class="selecao-multi">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:10px;">
                        <div>
                            <strong style="font-size:14px;">📋 Selecionar múltiplos alunos</strong>
                            <span style="font-size:12px;color:#64748b;margin-left:10px;">Marque os alunos para gerar boletins</span>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <button type="button" class="btn btn-sm" style="background:#e2e8f0;padding:5px 15px;border-radius:4px;border:none;cursor:pointer;font-size:12px;" onclick="selecionarTodos()">✅ Todos</button>
                            <button type="button" class="btn btn-sm" style="background:#e2e8f0;padding:5px 15px;border-radius:4px;border:none;cursor:pointer;font-size:12px;" onclick="desmarcarTodos()">❌ Nenhum</button>
                            <button type="button" class="btn btn-sm" style="background:#8b5cf6;color:white;padding:5px 15px;border-radius:4px;border:none;cursor:pointer;font-size:12px;" onclick="selecionarGerarTodos()">📁 Gerar Todos</button>
                        </div>
                    </div>
                    
                    <div class="aluno-grid" id="alunoGrid">
                        <?php foreach ($alunos_turma as $aluno): ?>
                        <label class="aluno-checkbox">
                            <input type="checkbox" name="alunos_selecionados[]" value="<?= $aluno['id'] ?>">
                            <span><?= htmlspecialchars($aluno['nome']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    
                    <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;">
                        <button type="submit" class="btn btn-multi" onclick="return confirm('Deseja gerar boletins para os alunos selecionados?\n\n📍 Os boletins serão salvos em:\n<?= htmlspecialchars($boletins_dir) ?>')">
                            <i class="fas fa-file-pdf"></i> Gerar Boletins Selecionados
                        </button>
                        <button type="submit" name="gerar_todos" value="1" class="btn btn-multi" onclick="return confirm('Deseja gerar boletins para TODOS os alunos da turma e baixar como ZIP?\n\n📍 Os boletins serão salvos em:\n<?= htmlspecialchars($boletins_dir) ?>')">
                            <i class="fas fa-archive"></i> Gerar Todos e Baixar ZIP
                        </button>
                    </div>
                    <small style="color:#94a3b8;font-size:11px;margin-top:8px;display:block;">
                        <i class="fas fa-folder-open"></i> Boletins salvos em: <strong><?= htmlspecialchars($boletins_dir) ?></strong>
                    </small>
                </div>
            </form>
        </div>

        <?php endif; ?>

        <?php if ($aluno_id > 0 && $aluno_dados): 
            $classe_aluno = $aluno_dados['Classe'] ?? '';
            $sistema = getSistemaAvaliacao($classe_aluno);
            $maxPontos = $sistema['max'];
            $minAprovacao = $sistema['min_aprovacao'];
        ?>
        
        <!-- BOLETIM INDIVIDUAL -->
        <div class="boletim-wrapper" id="boletimWrapper">
            <div class="boletim-card" id="boletimContent">
                
                <div class="boletim-header">
                    <div class="insignia-container">
                        <?php if ($insignia_base64): ?>
                            <img src="<?= $insignia_base64 ?>" class="insignia" alt="Insígnia">
                        <?php endif; ?>
                    </div>
                    <div class="escola-nome"><?= htmlspecialchars($escola_nome) ?></div>
                    <div class="subtitulo">BOLETIM DE NOTAS</div>
                    <div class="info">
                        Ano Letivo: <?= date('Y') . '/' . (date('Y') + 1) ?> | 
                        Emitido em: <?= date('d/m/Y H:i:s') ?>
                    </div>
                    <div class="endereco">
                        <?= htmlspecialchars($endereco_completo) ?>
                        <?php if ($telefone_empresa): ?> | Tel: <?= htmlspecialchars($telefone_empresa) ?><?php endif; ?>
                        <?php if ($email_empresa): ?> | Email: <?= htmlspecialchars($email_empresa) ?><?php endif; ?>
                        <?php if ($cnpj_empresa): ?> | NIF: <?= htmlspecialchars($cnpj_empresa) ?><?php endif; ?>
                    </div>
                </div>

                <div class="boletim-aluno">
                    <div class="foto">
                        <?php 
                        $foto_url = '';
                        if (!empty($aluno_dados['foto'])) {
                            $foto_url = '/softgest_web/uploads/alunos/' . $aluno_dados['foto'];
                            if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $foto_url)) {
                                $foto_url = '';
                            }
                        }
                        if ($foto_url): ?>
                            <img src="<?= $foto_url ?>" alt="Foto">
                        <?php else: ?>
                            <span>👤</span>
                        <?php endif; ?>
                    </div>
                    <div class="info-grid">
                        <div><span class="label">Nome:</span> <span class="value"><?= htmlspecialchars($aluno_dados['nome']) ?></span></div>
                        <div><span class="label">Classe:</span> <span class="value"><?= htmlspecialchars($aluno_dados['Classe'] ?? '-') ?></span></div>
                        <div><span class="label">Turma:</span> <span class="value"><?= htmlspecialchars($aluno_dados['TURMA'] ?? '-') ?></span></div>
                        <div><span class="label">Sexo:</span> <span class="value"><?= htmlspecialchars($aluno_dados['Sexo'] ?? '-') ?></span></div>
                        <div><span class="label">Data Nasc.:</span> <span class="value"><?= !empty($aluno_dados['data_nascimento']) ? date('d/m/Y', strtotime($aluno_dados['data_nascimento'])) : '-' ?></span></div>
                        <div><span class="label">Nº BI:</span> <span class="value"><?= htmlspecialchars($aluno_dados['N_BI'] ?? '-') ?></span></div>
                        <div><span class="label">Encarregado:</span> <span class="value"><?= htmlspecialchars($aluno_dados['nome_pai'] ?? $aluno_dados['nome_mae'] ?? '-') ?></span></div>
                        <div><span class="label">Contacto:</span> <span class="value"><?= htmlspecialchars($aluno_dados['telefone_responsavel'] ?? $aluno_dados['Contacto_Mae'] ?? '-') ?></span></div>
                    </div>
                </div>

                <!-- TABELA DE NOTAS -->
                <table class="boletim-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th style="text-align:left;">Disciplina</th>
                            <th>1º Trim.</th>
                            <th>2º Trim.</th>
                            <th>3º Trim.</th>
                            <th>Média Final</th>
                            <th>Classificação</th>
                            <th>Faltas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_media = 0;
                        $count_media = 0;
                        $aprovadas = 0;
                        $reprovadas = 0;
                        $total_faltas = 0;
                        $contador = 0;
                        
                        if (!empty($notas_aluno)):
                            $notas_por_disciplina = [];
                            foreach ($notas_aluno as $nota) {
                                $notas_por_disciplina[$nota['disciplina']] = $nota;
                            }
                            
                            $disciplinas_para_exibir = !empty($disciplinas_turma) ? $disciplinas_turma : array_keys($notas_por_disciplina);
                            
                            foreach ($disciplinas_para_exibir as $disciplina):
                                $contador++;
                                $nota = $notas_por_disciplina[$disciplina] ?? null;
                                
                                if ($nota) {
                                    $mt1 = floatval($nota['mt1'] ?? 0);
                                    $mt2 = floatval($nota['mt2'] ?? 0);
                                    $mt3 = floatval($nota['mt3'] ?? 0);
                                    $mfd = floatval($nota['mfd'] ?? 0);
                                    
                                    if ($mfd == 0 && ($mt1 > 0 || $mt2 > 0 || $mt3 > 0)) {
                                        $medias = [];
                                        if ($mt1 > 0) $medias[] = $mt1;
                                        if ($mt2 > 0) $medias[] = $mt2;
                                        if ($mt3 > 0) $medias[] = $mt3;
                                        $mfd = !empty($medias) ? array_sum($medias) / count($medias) : 0;
                                    }
                                    
                                    $classificacao = getClassificacaoBoletim($mfd, $maxPontos);
                                    $nota_class = $mfd >= $minAprovacao ? 'nota-positiva' : 'nota-negativa';
                                    $mfd_display = $mfd > 0 ? number_format($mfd, 1) : '-';
                                    $mt1_display = $mt1 > 0 ? number_format($mt1, 1) : '-';
                                    $mt2_display = $mt2 > 0 ? number_format($mt2, 1) : '-';
                                    $mt3_display = $mt3 > 0 ? number_format($mt3, 1) : '-';
                                    
                                    $faltas_disc = 0;
                                    foreach ($faltas_aluno as $f) {
                                        if ($f['disciplina_id'] == $nota['disciplina']) {
                                            $faltas_disc = $f['total_faltas'];
                                            $total_faltas += $faltas_disc;
                                            break;
                                        }
                                    }
                                    
                                    if ($mfd > 0) {
                                        $total_media += $mfd;
                                        $count_media++;
                                        if ($mfd >= $minAprovacao) $aprovadas++;
                                        else $reprovadas++;
                                    }
                        ?>
                        <tr>
                            <td><?= $contador ?></td>
                            <td class="disciplina-nome"><?= htmlspecialchars($disciplina) ?></td>
                            <td><?= $mt1_display ?></td>
                            <td><?= $mt2_display ?></td>
                            <td><?= $mt3_display ?></td>
                            <td class="<?= $nota_class ?>"><?= $mfd_display ?></td>
                            <td><?= htmlspecialchars($classificacao) ?></td>
                            <td><?= $faltas_disc > 0 ? $faltas_disc : '-' ?></td>
                        </tr>
                        <?php 
                                } else {
                        ?>
                        <tr>
                            <td><?= $contador ?></td>
                            <td class="disciplina-nome"><?= htmlspecialchars($disciplina) ?></td>
                            <td>-</td>
                            <td>-</td>
                            <td>-</td>
                            <td>-</td>
                            <td>-</td>
                            <td>-</td>
                        </tr>
                        <?php
                                }
                            endforeach;
                        else:
                        ?>
                        <tr>
                            <td colspan="8" style="text-align:center;padding:30px;color:#94a3b8;">
                                <i class="fas fa-info-circle"></i> Nenhuma nota encontrada para este aluno.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="boletim-summary">
                    <div class="item aprovado">
                        <div class="number"><?= $aprovadas ?></div>
                        <div class="label">✅ Aprovadas</div>
                    </div>
                    <div class="item reprovado">
                        <div class="number"><?= $reprovadas ?></div>
                        <div class="label">❌ Reprovadas</div>
                    </div>
                    <div class="item">
                        <div class="number"><?= $count_media > 0 ? number_format($total_media / $count_media, 1) : 'N/A' ?></div>
                        <div class="label">📊 Média Geral</div>
                    </div>
                    <div class="item faltas">
                        <div class="number"><?= $total_faltas ?></div>
                        <div class="label">📝 Faltas</div>
                    </div>
                </div>

                <?php 
                $media_geral = $count_media > 0 ? $total_media / $count_media : 0;
                $situacao_final = 'Aguardando avaliação';
                $situacao_class = 'aguardando';
                
                if ($count_media > 0) {
                    if ($media_geral >= $minAprovacao && $reprovadas == 0) {
                        $situacao_final = 'APROVADO';
                        $situacao_class = 'aprovado';
                    } elseif ($media_geral >= $minAprovacao && $reprovadas > 0) {
                        $situacao_final = 'RECUPERAÇÃO';
                        $situacao_class = 'recuperacao';
                    } elseif ($media_geral >= $minAprovacao * 0.5 && $media_geral < $minAprovacao) {
                        $situacao_final = 'RECUPERAÇÃO';
                        $situacao_class = 'recuperacao';
                    } elseif ($media_geral < $minAprovacao * 0.5 && $reprovadas > 0) {
                        $situacao_final = 'REPROVADO';
                        $situacao_class = 'reprovado';
                    } else {
                        $situacao_final = 'NÃO TRANSITA';
                        $situacao_class = 'nao-transita';
                    }
                }
                ?>
                
                <div class="situacao-final situacao-<?= $situacao_class ?>">
                    <strong style="font-size:18px;">
                        <?php if ($situacao_final == 'APROVADO'): ?>
                            ✅ APROVADO - Parabéns!
                        <?php elseif ($situacao_final == 'RECUPERAÇÃO'): ?>
                            📚 RECUPERAÇÃO - Continue se esforçando!
                        <?php elseif ($situacao_final == 'REPROVADO'): ?>
                            ❌ REPROVADO - Dedique-se mais nos estudos!
                        <?php elseif ($situacao_final == 'NÃO TRANSITA'): ?>
                            ⚠️ NÃO TRANSITA - Necessita melhorar!
                        <?php else: ?>
                            📋 Aguardando avaliação
                        <?php endif; ?>
                    </strong>
                    <div style="font-size:13px;color:#64748b;margin-top:4px;">
                        Média Geral: <?= $count_media > 0 ? number_format($media_geral, 1) : 'N/A' ?> | 
                        Disciplinas: <?= $count_media ?> | 
                        Aprovadas: <?= $aprovadas ?> | 
                        Reprovadas: <?= $reprovadas ?>
                    </div>
                </div>

                <div style="display:flex;justify-content:space-between;margin-top:30px;padding-top:20px;border-top:1px solid #e2e8f0;">
                    <div style="text-align:center;flex:1;">
                        <div style="border-top:1px solid #1a2332;width:80%;margin:0 auto;padding-top:5px;"></div>
                        <div style="font-size:12px;color:#64748b;margin-top:5px;">O Professor</div>
                    </div>
                    <div style="text-align:center;flex:1;">
                        <div style="border-top:1px solid #1a2332;width:80%;margin:0 auto;padding-top:5px;"></div>
                        <div style="font-size:12px;color:#64748b;margin-top:5px;">O Director Pedagógico</div>
                    </div>
                    <div style="text-align:center;flex:1;">
                        <div style="border-top:1px solid #1a2332;width:80%;margin:0 auto;padding-top:5px;"></div>
                        <div style="font-size:12px;color:#64748b;margin-top:5px;">O Encarregado de Educação</div>
                    </div>
                </div>

                <div style="margin-top:20px;text-align:center;font-size:11px;color:#94a3b8;border-top:1px solid #e2e8f0;padding-top:10px;">
                    <i class="fas fa-print"></i> Documento gerado eletronicamente - <?= date('d/m/Y H:i:s') ?> | 
                    <?= htmlspecialchars($escola_nome) ?>
                </div>
            </div>

            <!-- AÇÕES -->
            <div class="boletim-actions no-print">
                <?php 
                $whatsapp_numero = $aluno_dados['telefone_responsavel'] ?? $aluno_dados['Contacto_Mae'] ?? '';
                $whatsapp_numero = preg_replace('/[^0-9]/', '', $whatsapp_numero);
                if (strlen($whatsapp_numero) == 9) {
                    $whatsapp_numero = '244' . $whatsapp_numero;
                }
                ?>
                
                <?php if (!empty($whatsapp_numero) && strlen($whatsapp_numero) >= 12): ?>
                <button class="btn btn-whatsapp" onclick="enviarWhatsAppImagem('<?= $whatsapp_numero ?>')">
                    <i class="fab fa-whatsapp"></i> Enviar por WhatsApp
                </button>
                <?php else: ?>
                <button class="btn btn-whatsapp" onclick="alert('⚠️ Número de telefone do encarregado não encontrado!')" style="opacity:0.5;cursor:not-allowed;">
                    <i class="fab fa-whatsapp"></i> WhatsApp (Nº não disponível)
                </button>
                <?php endif; ?>
                
                <button class="btn btn-pdf" onclick="gerarPDF()">
                    <i class="fas fa-file-pdf"></i> Gerar PDF
                </button>
                
                <button class="btn btn-print" onclick="imprimirBoletim()">
                    <i class="fas fa-print"></i> Imprimir
                </button>
                
                <button class="btn btn-excel" onclick="exportarExcel()">
                    <i class="fas fa-file-excel"></i> Exportar Excel
                </button>
                
                <a href="boletins.php" class="btn btn-back">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>
        </div>

        <?php elseif ($turma_selecionada > 0 && !empty($alunos_turma) && $aluno_id == 0): ?>
        
        <div class="empty-boletim">
            <div class="icon">👨‍🎓</div>
            <h3>Selecione um aluno</h3>
            <p>Escolha um aluno na lista acima para visualizar o boletim.</p>
            <p style="font-size:13px;color:#94a3b8;margin-top:10px;">
                <i class="fas fa-info-circle"></i> Você também pode gerar boletins para múltiplos alunos usando os checkboxes.
            </p>
        </div>

        <?php elseif ($turma_selecionada > 0 && empty($alunos_turma)): ?>
        
        <div class="empty-boletim">
            <div class="icon">👨‍🎓</div>
            <h3>Nenhum aluno encontrado</h3>
            <p>Esta turma não possui alunos cadastrados.</p>
            <a href="boletins.php" class="btn btn-back" style="display:inline-block;margin-top:15px;padding:10px 25px;background:#e2e8f0;border-radius:6px;color:#1a2332;text-decoration:none;">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>

        <?php else: ?>
        
        <div class="empty-boletim">
            <div class="icon">📄</div>
            <h3>Selecione uma turma</h3>
            <p>Escolha a turma para visualizar os alunos e gerar boletins.</p>
            <p style="font-size:13px;color:#94a3b8;margin-top:10px;">
                <i class="fas fa-info-circle"></i> Selecione a turma acima para listar os alunos.
            </p>
        </div>

        <?php endif; ?>

    </div>

    <?php
    // ==========================================
    // INCLUIR FOOTER - CORRIGIDO
    // ==========================================
    $footer_paths = [
        $base_path . '/includes/footer.php',
        __DIR__ . '/../../includes/footer.php',
        __DIR__ . '/../includes/footer.php'
    ];

    $footer_included = false;
    foreach ($footer_paths as $path) {
        if (file_exists($path)) {
            include $path;
            $footer_included = true;
            break;
        }
    }

    if (!$footer_included) {
        // Fallback
        $fallback_paths = [
            'includes/footer.php',
            '../includes/footer.php',
            '../../includes/footer.php'
        ];
        foreach ($fallback_paths as $path) {
            if (file_exists($path)) {
                include $path;
                $footer_included = true;
                break;
            }
        }
    }
    ?>
</main>

<!-- ==========================================
   SCRIPTS
   ========================================== -->
<script>
// ============================================
// FUNÇÕES DE SELEÇÃO MÚLTIPLA
// ============================================
function selecionarTodos() {
    document.querySelectorAll('#alunoGrid input[type="checkbox"]').forEach(function(cb) {
        cb.checked = true;
    });
}

function desmarcarTodos() {
    document.querySelectorAll('#alunoGrid input[type="checkbox"]').forEach(function(cb) {
        cb.checked = false;
    });
}

function selecionarGerarTodos() {
    selecionarTodos();
    document.querySelector('button[name="gerar_todos"]').click();
}

// ============================================
// IMPRIMIR BOLETIM
// ============================================
function imprimirBoletim() {
    window.print();
}

// ============================================
// GERAR PDF
// ============================================
function gerarPDF() {
    var boletimContent = document.getElementById('boletimContent');
    if (!boletimContent) {
        alert('⚠️ Nenhum boletim para gerar PDF.');
        return;
    }
    
    var clone = boletimContent.cloneNode(true);
    var win = window.open('', '_blank', 'width=900,height=800,scrollbars=yes');
    
    if (!win) {
        alert('⚠️ Por favor, permita popups para gerar o PDF.');
        return;
    }
    
    win.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Boletim</title>');
    win.document.write('<style>');
    win.document.write('* { margin: 0; padding: 0; box-sizing: border-box; }');
    win.document.write('body { font-family: "Segoe UI", Arial, sans-serif; padding: 20px; background: white; }');
    win.document.write('.boletim-header { text-align: center; border-bottom: 3px solid #c9a84c; padding-bottom: 20px; margin-bottom: 25px; }');
    win.document.write('.boletim-header .escola-nome { font-size: 22px; font-weight: 700; color: #1a2332; text-transform: uppercase; }');
    win.document.write('.boletim-header .subtitulo { font-size: 18px; font-weight: 600; color: #c9a84c; margin-top: 5px; }');
    win.document.write('.boletim-header .info { font-size: 13px; color: #64748b; margin-top: 8px; }');
    win.document.write('.boletim-header .endereco { font-size: 12px; color: #64748b; margin-top: 3px; }');
    win.document.write('.boletim-aluno { display: flex; gap: 20px; padding: 15px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 20px; flex-wrap: wrap; align-items: center; }');
    win.document.write('.boletim-aluno .foto { width: 70px; height: 70px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 30px; color: #94a3b8; flex-shrink: 0; border: 3px solid #c9a84c; }');
    win.document.write('.boletim-aluno .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 20px; flex: 1; font-size: 14px; }');
    win.document.write('.boletim-aluno .info-grid .label { color: #64748b; }');
    win.document.write('.boletim-aluno .info-grid .value { font-weight: 600; color: #1a2332; }');
    win.document.write('.boletim-table { width: 100%; border-collapse: collapse; font-size: 14px; margin: 15px 0; }');
    win.document.write('.boletim-table th { background: #1a2332; color: white; padding: 10px 12px; text-align: center; border: 1px solid #1a2332; font-weight: 600; }');
    win.document.write('.boletim-table td { padding: 8px 10px; border: 1px solid #e2e8f0; text-align: center; font-size: 13px; }');
    win.document.write('.boletim-table tr:nth-child(even) { background: #f8fafc; }');
    win.document.write('.boletim-table .disciplina-nome { text-align: left; font-weight: 600; color: #1a2332; }');
    win.document.write('.boletim-table .nota-positiva { color: #22c55e; font-weight: 700; }');
    win.document.write('.boletim-table .nota-negativa { color: #ef4444; font-weight: 700; }');
    win.document.write('.boletim-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-top: 15px; }');
    win.document.write('.boletim-summary .item { background: #f8fafc; padding: 10px 14px; border-radius: 6px; border: 1px solid #e2e8f0; text-align: center; }');
    win.document.write('.boletim-summary .item .number { font-size: 24px; font-weight: 700; color: #1a2332; }');
    win.document.write('.boletim-summary .item .label { font-size: 11px; color: #64748b; margin-top: 2px; }');
    win.document.write('.boletim-summary .item.aprovado .number { color: #22c55e; }');
    win.document.write('.boletim-summary .item.reprovado .number { color: #ef4444; }');
    win.document.write('.boletim-summary .item.faltas .number { color: #f59e0b; }');
    win.document.write('.situacao-final { margin-top: 15px; padding: 12px; border-radius: 6px; text-align: center; border: 1px solid; }');
    win.document.write('.situacao-aprovado { background: #d1fae5; border-color: #bbf7d0; color: #065f46; }');
    win.document.write('.situacao-reprovado { background: #fee2e2; border-color: #fecaca; color: #991b1b; }');
    win.document.write('.situacao-recuperacao { background: #fef3c7; border-color: #fde68a; color: #92400e; }');
    win.document.write('.situacao-nao-transita { background: #fef2f2; border-color: #fecaca; color: #991b1b; }');
    win.document.write('.assinaturas { display: flex; justify-content: space-between; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0; }');
    win.document.write('.assinaturas .item { text-align: center; flex: 1; }');
    win.document.write('.assinaturas .linha { border-top: 1px solid #1a2332; width: 80%; margin: 0 auto; padding-top: 5px; }');
    win.document.write('.assinaturas .label { font-size: 11px; color: #64748b; margin-top: 4px; }');
    win.document.write('.rodape { margin-top: 15px; text-align: center; font-size: 10px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 8px; }');
    win.document.write('<\/style><\/head><body>');
    win.document.write(clone.outerHTML);
    win.document.write('<\/body><\/html>');
    win.document.close();
    
    setTimeout(function() {
        win.print();
    }, 500);
}

// ============================================
// ENVIAR POR WHATSAPP COMO IMAGEM
// ============================================
function enviarWhatsAppImagem(numero) {
    if (!numero || numero.length < 12) {
        alert('⚠️ Número de telefone do encarregado não encontrado!');
        return;
    }
    
    var boletimElement = document.getElementById('boletimContent');
    if (!boletimElement) {
        alert('⚠️ Nenhum boletim para enviar.');
        return;
    }
    
    if (typeof html2canvas === 'undefined') {
        var script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
        script.onload = function() {
            capturarEEnviarWhatsApp(boletimElement, numero);
        };
        document.head.appendChild(script);
    } else {
        capturarEEnviarWhatsApp(boletimElement, numero);
    }
}

function capturarEEnviarWhatsApp(elemento, numero) {
    var loading = document.createElement('div');
    loading.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:9999;display:flex;align-items:center;justify-content:center;flex-direction:column;color:white;font-size:18px;';
    loading.innerHTML = '<div style="font-size:50px;margin-bottom:20px;">📸</div><div>Gerando imagem do boletim...</div><div style="font-size:14px;margin-top:10px;opacity:0.7;">Aguarde um momento</div>';
    document.body.appendChild(loading);
    
    html2canvas(elemento, {
        scale: 2,
        useCORS: true,
        allowTaint: true,
        backgroundColor: '#ffffff',
        logging: false
    }).then(function(canvas) {
        document.body.removeChild(loading);
        
        var imagemData = canvas.toDataURL('image/png');
        
        var link = document.createElement('a');
        link.download = 'boletim_' + Date.now() + '.png';
        link.href = imagemData;
        link.click();
        
        var nomeAluno = '<?= addslashes($aluno_dados['nome'] ?? 'Aluno') ?>';
        var turma = '<?= addslashes($aluno_dados['TURMA'] ?? '') ?>';
        var escola = '<?= addslashes($escola_nome) ?>';
        var mensagem = '📄 *BOLETIM DE NOTAS*\n\n';
        mensagem += '🏫 *' + escola + '*\n';
        mensagem += '👨‍🎓 *Aluno:* ' + nomeAluno + '\n';
        mensagem += '📚 *Turma:* ' + turma + '\n';
        mensagem += '📅 *Data:* ' + new Date().toLocaleDateString('pt-BR') + '\n\n';
        mensagem += '📎 *Anexo:* Boletim em imagem';
        
        var mensagemEncoded = encodeURIComponent(mensagem);
        var url = 'https://wa.me/' + numero + '?text=' + mensagemEncoded;
        
        window.open(url, '_blank');
        
        alert('✅ Boletim gerado como imagem!\n\n📌 A imagem foi baixada automaticamente.\n📱 O WhatsApp será aberto para enviar o anexo.\n\n💡 Envie a imagem baixada como anexo no WhatsApp.');
        
    }).catch(function(error) {
        document.body.removeChild(loading);
        console.error('Erro ao gerar imagem:', error);
        alert('❌ Erro ao gerar imagem do boletim. Tente novamente.');
    });
}

// ============================================
// EXPORTAR EXCEL
// ============================================
function exportarExcel() {
    var table = document.querySelector('.boletim-table');
    if (!table) {
        alert('Nenhum dado para exportar!');
        return;
    }
    
    var cloneTable = table.cloneNode(true);
    cloneTable.querySelectorAll('[style]').forEach(function(el) {
        el.removeAttribute('style');
    });
    
    var nomeAluno = '<?= addslashes($aluno_dados['nome'] ?? 'Aluno') ?>';
    var turma = '<?= addslashes($aluno_dados['TURMA'] ?? '') ?>';
    var classe = '<?= addslashes($aluno_dados['Classe'] ?? '') ?>';
    var escola = '<?= addslashes($escola_nome) ?>';
    
    var htmlContent = `
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Boletim - ${nomeAluno}</title>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; padding: 20px; }
                .header { text-align: center; margin-bottom: 20px; }
                .header h1 { color: #1a2332; }
                .header .sub { color: #64748b; }
                table { width: 100%; border-collapse: collapse; font-size: 12px; }
                th { background: #1a2332; color: white; padding: 8px; border: 1px solid #000; }
                td { padding: 6px; border: 1px solid #ddd; }
                tr:nth-child(even) { background: #f8fafc; }
                .total { margin-top: 15px; font-weight: bold; text-align: right; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>${escola}</h1>
                <div class="sub">BOLETIM DE NOTAS - ${nomeAluno}</div>
                <div>Classe: ${classe} | Turma: ${turma}</div>
                <div>Data: ${new Date().toLocaleDateString('pt-BR')}</div>
            </div>
            ${cloneTable.outerHTML}
            <div class="total">
                Total de Disciplinas: ${document.querySelectorAll('.boletim-table tbody tr').length}
            </div>
        </body>
        </html>
    `;
    
    var blob = new Blob([htmlContent], { type: 'application/vnd.ms-excel;charset=UTF-8' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `boletim_${nomeAluno}_${new Date().toISOString().slice(0,10)}.xls`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

console.log('📄 Sistema de Boletins carregado!');
console.log('📍 Pasta de salvamento: <?= htmlspecialchars($boletins_dir) ?>');
console.log('👨‍🎓 Aluno: <?= htmlspecialchars($aluno_dados['nome'] ?? 'Nenhum') ?>');
</script>