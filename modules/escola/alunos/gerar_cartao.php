<?php
// ============================================
// modules/escola/alunos/gerar_cartao.php - Gerar Cartão Individual
// ============================================

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (function_exists('temPermissao')) {
    if (!temPermissao('Escola', 'visualizar')) {
        header('Location: ' . SITE_URL);
        exit;
    }
}

// ============================================
// VERIFICAR PAGAMENTO DO CARTÃO
// ============================================
define('VALOR_CARTAO', 1500.00);

function verificarPagamentoCartao($pdo, $aluno_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM pagamentos 
            WHERE aluno_id = ? 
            AND valor = ?
            AND status = 'confirmado'
        ");
        $stmt->execute([$aluno_id, VALOR_CARTAO]);
        $result = $stmt->fetch();
        return $result['total'] > 0;
    } catch (Exception $e) {
        return false;
    }
}

// ============================================
// CONFIGURAÇÕES DO CARTÃO
// ============================================
$config_default = [
    'cor_primaria' => '#1a2a3a',
    'cor_secundaria' => '#d4a843',
    'cor_fundo' => '#ffffff',
    'cor_texto' => '#1a2a3a',
    'cor_cabecalho' => '#1a2a3a',
    'cor_rodape' => '#f5f0eb',
    'tamanho_largura' => '85',
    'tamanho_altura' => '120',
    'tamanho_fonte' => '10',
    'borda_arredondada' => '6',
    'mostrar_foto' => 'sim',
    'mostrar_verso' => 'sim',
    'mostrar_info_importante' => 'sim',
    'fundo_decorado' => 'sim',
    'layout' => 'classico',
    'logo_upload' => '',
    'logo_fundo' => '',
    'foto_largura' => '22',
    'foto_altura' => '28',
    'nome_cor' => '#d4a843',
    'nome_tamanho' => '14',
    'nome_peso' => '700',
    'nome_maiusculo' => 'sim',
    'classe_cor' => '#1a2a3a',
    'classe_tamanho' => '11',
    'classe_peso' => '600',
    'mostrar_linha_ondulada' => 'sim',
    'linha_ondulada_cor' => '#d4a843',
    'linha_ondulada_altura' => '3',
    'linha_ondulada_posicao' => 'antes_nome',
    'linha_ondulada_offset_y' => '0',
    'linha_ondulada_curvatura' => '1',
    'mostrar_qr_code_frente' => 'sim',
    'qr_code_tamanho' => '12',
    'mostrar_assinatura_frente' => 'sim',
    'assinatura_frente_nome' => 'Dr. Carlos Manuel Reis',
    'assinatura_frente_tamanho' => '0.35',
    'mostrar_assinatura_verso' => 'sim',
    'assinatura_verso_nome' => 'Dr. Carlos Manuel Reis',
    'assinatura_verso_tamanho' => '0.55',
    'assinatura_verso_cargo' => 'DIRECTOR DA ESCOLA',
    'tabela_altura_linha' => '3.5',
    'tabela_distribuicao' => '1.2',
    'verso_offset_x' => '0',
    'verso_offset_y' => '0',
    'ano_letivo' => ''
];

if (isset($_SESSION['cartao_config'])) {
    $config = array_merge($config_default, $_SESSION['cartao_config']);
} else {
    $config = $config_default;
}

// Processar upload do logo (cabeçalho)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logo_upload']) && $_FILES['logo_upload']['error'] === UPLOAD_ERR_OK) {
    $extensoes = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    $ext = strtolower(pathinfo($_FILES['logo_upload']['name'], PATHINFO_EXTENSION));
    
    if (in_array($ext, $extensoes)) {
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/logos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $nome_arquivo = 'logo_cartao.' . $ext;
        $caminho = $upload_dir . $nome_arquivo;
        
        if (move_uploaded_file($_FILES['logo_upload']['tmp_name'], $caminho)) {
            $config['logo_upload'] = '/softgest_web/uploads/logos/' . $nome_arquivo;
            $_SESSION['cartao_config'] = $config;
            header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . ($_GET['id'] ?? 0) . '&logo=ok');
            exit;
        }
    }
}

// Processar upload do logo de fundo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logo_fundo_upload']) && $_FILES['logo_fundo_upload']['error'] === UPLOAD_ERR_OK) {
    $extensoes = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    $ext = strtolower(pathinfo($_FILES['logo_fundo_upload']['name'], PATHINFO_EXTENSION));
    
    if (in_array($ext, $extensoes)) {
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/logos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $nome_arquivo = 'logo_fundo_cartao.' . $ext;
        $caminho = $upload_dir . $nome_arquivo;
        
        if (move_uploaded_file($_FILES['logo_fundo_upload']['tmp_name'], $caminho)) {
            $config['logo_fundo'] = '/softgest_web/uploads/logos/' . $nome_arquivo;
            $_SESSION['cartao_config'] = $config;
            header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . ($_GET['id'] ?? 0) . '&logo_fundo=ok');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_config'])) {
    $config['cor_primaria'] = $_POST['cor_primaria'] ?? $config_default['cor_primaria'];
    $config['cor_secundaria'] = $_POST['cor_secundaria'] ?? $config_default['cor_secundaria'];
    $config['cor_fundo'] = $_POST['cor_fundo'] ?? $config_default['cor_fundo'];
    $config['cor_texto'] = $_POST['cor_texto'] ?? $config_default['cor_texto'];
    $config['cor_cabecalho'] = $_POST['cor_cabecalho'] ?? $config_default['cor_cabecalho'];
    $config['cor_rodape'] = $_POST['cor_rodape'] ?? $config_default['cor_rodape'];
    $config['tamanho_largura'] = $_POST['tamanho_largura'] ?? $config_default['tamanho_largura'];
    $config['tamanho_altura'] = $_POST['tamanho_altura'] ?? $config_default['tamanho_altura'];
    $config['tamanho_fonte'] = $_POST['tamanho_fonte'] ?? $config_default['tamanho_fonte'];
    $config['borda_arredondada'] = $_POST['borda_arredondada'] ?? $config_default['borda_arredondada'];
    $config['foto_largura'] = $_POST['foto_largura'] ?? $config_default['foto_largura'];
    $config['foto_altura'] = $_POST['foto_altura'] ?? $config_default['foto_altura'];
    
    $config['nome_cor'] = $_POST['nome_cor'] ?? $config_default['nome_cor'];
    $config['nome_tamanho'] = $_POST['nome_tamanho'] ?? $config_default['nome_tamanho'];
    $config['nome_peso'] = $_POST['nome_peso'] ?? $config_default['nome_peso'];
    $config['nome_maiusculo'] = isset($_POST['nome_maiusculo']) ? 'sim' : 'nao';
    
    $config['classe_cor'] = $_POST['classe_cor'] ?? $config_default['classe_cor'];
    $config['classe_tamanho'] = $_POST['classe_tamanho'] ?? $config_default['classe_tamanho'];
    $config['classe_peso'] = $_POST['classe_peso'] ?? $config_default['classe_peso'];
    
    $config['mostrar_linha_ondulada'] = isset($_POST['mostrar_linha_ondulada']) ? 'sim' : 'nao';
    $config['linha_ondulada_cor'] = $_POST['linha_ondulada_cor'] ?? $config_default['linha_ondulada_cor'];
    $config['linha_ondulada_altura'] = $_POST['linha_ondulada_altura'] ?? $config_default['linha_ondulada_altura'];
    $config['linha_ondulada_posicao'] = $_POST['linha_ondulada_posicao'] ?? $config_default['linha_ondulada_posicao'];
    $config['linha_ondulada_offset_y'] = $_POST['linha_ondulada_offset_y'] ?? $config_default['linha_ondulada_offset_y'];
    $config['linha_ondulada_curvatura'] = $_POST['linha_ondulada_curvatura'] ?? $config_default['linha_ondulada_curvatura'];
    
    $config['mostrar_qr_code_frente'] = isset($_POST['mostrar_qr_code_frente']) ? 'sim' : 'nao';
    $config['qr_code_tamanho'] = $_POST['qr_code_tamanho'] ?? $config_default['qr_code_tamanho'];
    
    $config['mostrar_assinatura_frente'] = isset($_POST['mostrar_assinatura_frente']) ? 'sim' : 'nao';
    $config['assinatura_frente_nome'] = $_POST['assinatura_frente_nome'] ?? $config_default['assinatura_frente_nome'];
    $config['assinatura_frente_tamanho'] = $_POST['assinatura_frente_tamanho'] ?? $config_default['assinatura_frente_tamanho'];
    
    $config['mostrar_assinatura_verso'] = isset($_POST['mostrar_assinatura_verso']) ? 'sim' : 'nao';
    $config['assinatura_verso_nome'] = $_POST['assinatura_verso_nome'] ?? $config_default['assinatura_verso_nome'];
    $config['assinatura_verso_tamanho'] = $_POST['assinatura_verso_tamanho'] ?? $config_default['assinatura_verso_tamanho'];
    $config['assinatura_verso_cargo'] = $_POST['assinatura_verso_cargo'] ?? $config_default['assinatura_verso_cargo'];
    
    $config['tabela_altura_linha'] = $_POST['tabela_altura_linha'] ?? $config_default['tabela_altura_linha'];
    $config['tabela_distribuicao'] = $_POST['tabela_distribuicao'] ?? $config_default['tabela_distribuicao'];
    
    $config['verso_offset_x'] = $_POST['verso_offset_x'] ?? $config_default['verso_offset_x'];
    $config['verso_offset_y'] = $_POST['verso_offset_y'] ?? $config_default['verso_offset_y'];
    
    $config['mostrar_foto'] = isset($_POST['mostrar_foto']) ? 'sim' : 'nao';
    $config['mostrar_verso'] = isset($_POST['mostrar_verso']) ? 'sim' : 'nao';
    $config['mostrar_info_importante'] = isset($_POST['mostrar_info_importante']) ? 'sim' : 'nao';
    $config['fundo_decorado'] = isset($_POST['fundo_decorado']) ? 'sim' : 'nao';
    $config['layout'] = $_POST['layout'] ?? 'classico';
    $config['ano_letivo'] = $_POST['ano_letivo'] ?? '';
    
    $_SESSION['cartao_config'] = $config;
    
    header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . ($_GET['id'] ?? 0) . '&config=salvo');
    exit;
}

if (isset($_GET['reset_config'])) {
    unset($_SESSION['cartao_config']);
    header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . ($_GET['id'] ?? 0));
    exit;
}

// ============================================
// PROCESSAR GERAÇÃO EM MASSA
// ============================================
$modo_massa = false;
$alunos_massa = [];
$alunos_bloqueados = [];
$erro_massa = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gerar_massa'])) {
    $modo_massa = true;
    $tipo_geracao = $_POST['tipo_geracao'] ?? 'turma';
    $quantidade = intval($_POST['quantidade'] ?? 0);
    $ids_alunos = $_POST['ids_alunos'] ?? '';
    $turma = $_POST['turma_selecionada'] ?? '';
    $classe = $_POST['classe_selecionada'] ?? '';
    
    try {
        $pdo = conectarBanco();
        $sql = "SELECT id, nome, Sexo, Idade, dia, mes, Ano, 
                       Classe, Curso, TURMA, SALA, Periodo, Situacao_Cadastro,
                       Nome_do_Pai, Contacto4, foto
                FROM alunos WHERE 1=1";
        $params = [];
        
        if ($tipo_geracao === 'turma' && !empty($turma)) {
            $sql .= " AND TURMA = ?";
            $params[] = $turma;
            
            if (!empty($classe)) {
                $sql .= " AND Classe = ?";
                $params[] = $classe;
            }
        } elseif ($tipo_geracao === 'ids' && !empty($ids_alunos)) {
            $ids = array_filter(array_map('trim', explode(',', $ids_alunos)));
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $sql .= " AND id IN ($placeholders)";
                $params = array_merge($params, $ids);
            } else {
                $erro_massa = 'Nenhum ID válido informado.';
            }
        } elseif ($tipo_geracao === 'quantidade' && $quantidade > 0) {
            $sql .= " ORDER BY id DESC LIMIT ?";
            $params[] = $quantidade;
        } else {
            $erro_massa = 'Selecione uma opção válida para geração em massa.';
        }
        
        if (empty($erro_massa)) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $alunos_temp = $stmt->fetchAll();
            
            $alunos_massa = [];
            $alunos_bloqueados = [];
            
            foreach ($alunos_temp as $aluno) {
                $tem_pagamento = verificarPagamentoCartao($pdo, $aluno['id']);
                if ($tem_pagamento) {
                    $alunos_massa[] = $aluno;
                } else {
                    $alunos_bloqueados[] = $aluno;
                }
            }
            
            if (empty($alunos_massa)) {
                $erro_massa = 'Nenhum aluno com pagamento de ' . number_format(VALOR_CARTAO, 2, ',', '.') . ' Kz encontrado.';
            }
        }
    } catch (Exception $e) {
        $erro_massa = 'Erro ao buscar alunos: ' . $e->getMessage();
    }
}

// ============================================
// BUSCAR DADOS PARA O SELECT DE TURMAS
// ============================================
$turmas_disponiveis = [];
$classes_disponiveis = [];
try {
    $pdo = conectarBanco();
    $stmt = $pdo->query("SELECT DISTINCT TURMA FROM alunos WHERE TURMA IS NOT NULL AND TURMA != '' ORDER BY TURMA");
    $turmas_disponiveis = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $stmt = $pdo->query("SELECT DISTINCT Classe FROM alunos WHERE Classe IS NOT NULL AND Classe != '' ORDER BY Classe");
    $classes_disponiveis = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// ============================================
// MODO INDIVIDUAL
// ============================================
$id = $_GET['id'] ?? 0;
$aluno = null;

if ($id && !$modo_massa) {
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("
            SELECT id, nome, Sexo, Idade, dia, mes, Ano, 
                   Classe, Curso, TURMA, SALA, Periodo, Situacao_Cadastro,
                   Nome_do_Pai, Contacto4, foto
            FROM alunos 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $aluno = $stmt->fetch();
        
        if ($aluno) {
            $tem_pagamento = verificarPagamentoCartao($pdo, $id);
            if (!$tem_pagamento) {
                $_SESSION['erro_cartao'] = "Aluno #{$id} não possui pagamento de " . number_format(VALOR_CARTAO, 2, ',', '.') . " Kz confirmado.";
                header('Location: cartoes_escolares.php');
                exit;
            }
        }
        
    } catch (Exception $e) {}
}

if (!$aluno && !$modo_massa) {
    header('Location: cartoes_escolares.php');
    exit;
}

// ============================================
// DADOS DA EMPRESA
// ============================================
$empresa = [];
try {
    $pdo = conectarBanco();
    $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
    $empresa = $stmt->fetch();
} catch (Exception $e) {}

$nomeEmpresa = $empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? 'COMPLEXO ESCOLAR PRIVADO CASTELO REIS & FILHOS';
$enderecoEmpresa = $empresa['endereco'] ?? 'Município do Bom Jesus – Km 44, Desvio';
$cidadeEmpresa = $empresa['cidade'] ?? 'Província de Ícolo e Bengo – Angola';
$telefoneEmpresa = $empresa['telefone'] ?? '923 456 789 / 934 567 890';
$emailEmpresa = $empresa['email'] ?? 'www.complexoescolarprivadocasteloreis-2.onrender.com';
$telefone2 =  $empresa['telefone'] ?? '923 456 789 / 934 567 890';

// ============================================
// CALCULAR ANO LETIVO
// ============================================
if (!empty($config['ano_letivo'])) {
    $ano_letivo = $config['ano_letivo'];
} else {
    $mes_atual = date('m');
    $ano_atual = date('Y');
    if ($mes_atual >= 9) {
        $ano_letivo = $ano_atual . ' / ' . ($ano_atual + 1);
    } else {
        $ano_letivo = ($ano_atual - 1) . ' / ' . $ano_atual;
    }
}

// ============================================
// FUNÇÃO PARA ENCONTRAR A FOTO
// ============================================
function encontrarFotoAlunoCartao($aluno) {
    $id_aluno = $aluno['id'] ?? 0;
    
    if (!empty($aluno['foto'])) {
        $foto = $aluno['foto'];
        $diretorios = [
            $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/alunos/',
            $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/fotos_alunos/',
        ];
        foreach ($diretorios as $dir) {
            $caminho = $dir . basename($foto);
            if (file_exists($caminho)) {
                return '/softgest_web/uploads/alunos/' . basename($foto);
            }
        }
    }
    
    if ($id_aluno > 0) {
        $extensoes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $diretorios = [
            $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/alunos/',
            $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/fotos_alunos/',
        ];
        foreach ($diretorios as $dir) {
            if (!is_dir($dir)) continue;
            foreach ($extensoes as $ext) {
                $caminho = $dir . $id_aluno . '.' . $ext;
                if (file_exists($caminho)) {
                    return '/softgest_web/uploads/alunos/' . $id_aluno . '.' . $ext;
                }
            }
            $arquivos = glob($dir . 'aluno_' . $id_aluno . '_*');
            if (!empty($arquivos)) {
                return '/softgest_web/uploads/alunos/' . basename($arquivos[0]);
            }
        }
    }
    return null;
}

// ============================================
// FUNÇÃO PARA GERAR QR CODE
// ============================================
function gerarQRCodeSVG($dados, $tamanho = 150) {
    $url = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $tamanho . 'x' . $tamanho . '&data=' . urlencode($dados);
    return '<img src="' . $url . '" alt="QR Code" style="width:100%;height:100%;object-fit:contain;">';
}

// ============================================
// FUNÇÃO PARA GERAR LINHA ONDULADA
// ============================================
function gerarLinhaOndulada($cor, $altura, $offset_y = 0, $curvatura = 1) {
    $altura_px = $altura * 1.5;
    $offset_px = $offset_y * 1.5;
    $curvatura_ajustada = $curvatura * 8;
    
    $html = '
    <svg viewBox="0 0 200 20" xmlns="http://www.w3.org/2000/svg" 
         style="width:100%;height:' . $altura_px . 'mm;display:block;">
        <defs>
            <linearGradient id="gradOnda" x1="0%" y1="0%" x2="100%" y2="0%">
                <stop offset="0%" stop-color="' . $cor . '" stop-opacity="0.2"/>
                <stop offset="30%" stop-color="' . $cor . '" stop-opacity="1"/>
                <stop offset="70%" stop-color="' . $cor . '" stop-opacity="1"/>
                <stop offset="100%" stop-color="' . $cor . '" stop-opacity="0.2"/>
            </linearGradient>
        </defs>
        <path d="M 0 ' . (10 + $offset_px) . ' 
                 C ' . (15 + $curvatura_ajustada) . ' ' . (2 + $offset_px) . ', 
                   ' . (25 - $curvatura_ajustada) . ' ' . (18 + $offset_px) . ', 
                   40 ' . (10 + $offset_px) . ' 
                 C ' . (55 + $curvatura_ajustada) . ' ' . (2 + $offset_px) . ', 
                   ' . (65 - $curvatura_ajustada) . ' ' . (18 + $offset_px) . ', 
                   80 ' . (10 + $offset_px) . ' 
                 C ' . (95 + $curvatura_ajustada) . ' ' . (2 + $offset_px) . ', 
                   ' . (105 - $curvatura_ajustada) . ' ' . (18 + $offset_px) . ', 
                   120 ' . (10 + $offset_px) . ' 
                 C ' . (135 + $curvatura_ajustada) . ' ' . (2 + $offset_px) . ', 
                   ' . (145 - $curvatura_ajustada) . ' ' . (18 + $offset_px) . ', 
                   160 ' . (10 + $offset_px) . ' 
                 C ' . (175 + $curvatura_ajustada) . ' ' . (2 + $offset_px) . ', 
                   ' . (185 - $curvatura_ajustada) . ' ' . (18 + $offset_px) . ', 
                   200 ' . (10 + $offset_px) . '" 
              stroke="' . $cor . '" 
              stroke-width="2.8" 
              fill="none" 
              opacity="0.9"/>
        <path d="M 0 ' . (13 + $offset_px) . ' 
                 C ' . (15 + $curvatura_ajustada) . ' ' . (5 + $offset_px) . ', 
                   ' . (25 - $curvatura_ajustada) . ' ' . (21 + $offset_px) . ', 
                   40 ' . (13 + $offset_px) . ' 
                 C ' . (55 + $curvatura_ajustada) . ' ' . (5 + $offset_px) . ', 
                   ' . (65 - $curvatura_ajustada) . ' ' . (21 + $offset_px) . ', 
                   80 ' . (13 + $offset_px) . ' 
                 C ' . (95 + $curvatura_ajustada) . ' ' . (5 + $offset_px) . ', 
                   ' . (105 - $curvatura_ajustada) . ' ' . (21 + $offset_px) . ', 
                   120 ' . (13 + $offset_px) . ' 
                 C ' . (135 + $curvatura_ajustada) . ' ' . (5 + $offset_px) . ', 
                   ' . (145 - $curvatura_ajustada) . ' ' . (21 + $offset_px) . ', 
                   160 ' . (13 + $offset_px) . ' 
                 C ' . (175 + $curvatura_ajustada) . ' ' . (5 + $offset_px) . ', 
                   ' . (185 - $curvatura_ajustada) . ' ' . (21 + $offset_px) . ', 
                   200 ' . (13 + $offset_px) . '" 
              stroke="' . $cor . '" 
              stroke-width="1.2" 
              fill="none" 
              opacity="0.3"/>
        <circle cx="10" cy="' . (10 + $offset_px) . '" r="3" fill="' . $cor . '" opacity="0.5"/>
        <circle cx="190" cy="' . (10 + $offset_px) . '" r="3" fill="' . $cor . '" opacity="0.5"/>
    </svg>';
    
    return $html;
}

// ============================================
// FUNÇÃO PARA GERAR FRENTE DO CARTÃO
// ============================================
function gerarFrenteCartao($aluno, $config, $empresa, $ano_letivo, $telefone2) {
    $foto_url = encontrarFotoAlunoCartao($aluno);
    $tem_foto = !is_null($foto_url);
    
    $num_estudante = 'CR' . date('Y') . str_pad($aluno['id'] ?? 0, 4, '0', STR_PAD_LEFT);
    $classe = $aluno['Classe'] ?? '';
    $turma = $aluno['TURMA'] ?? '';
    $classe_turma = $classe . 'ª CLASSE – ' . $turma;
    $turno = strtoupper(htmlspecialchars($aluno['Periodo'] ?? ''));
    
    // CONFIGURAÇÕES
    $cor_primaria = $config['cor_primaria'];
    $cor_secundaria = $config['cor_secundaria'];
    $cor_fundo = $config['cor_fundo'];
    $cor_texto = $config['cor_texto'];
    $cor_cabecalho = $config['cor_cabecalho'];
    $cor_rodape = $config['cor_rodape'];
    $tamanho_largura = floatval($config['tamanho_largura'] ?? 85);
    $tamanho_altura = floatval($config['tamanho_altura'] ?? 120);
    $tamanho_fonte = floatval($config['tamanho_fonte'] ?? 10);
    $borda_arredondada = $config['borda_arredondada'];
    $mostrar_foto = $config['mostrar_foto'];
    $mostrar_info_importante = $config['mostrar_info_importante'];
    $fundo_decorado = $config['fundo_decorado'];
    $layout = $config['layout'];
    $logo_path = $config['logo_upload'] ?? '';
    $logo_fundo_path = $config['logo_fundo'] ?? '';
    
    $foto_largura = floatval($config['foto_largura'] ?? 22);
    $foto_altura = floatval($config['foto_altura'] ?? 28);
    $foto_largura = min($foto_largura, $tamanho_largura * 0.3);
    $foto_altura = min($foto_altura, $tamanho_altura * 0.35);
    
    $nome_cor = $config['nome_cor'] ?? '#d4a843';
    $nome_tamanho = floatval($config['nome_tamanho'] ?? 14);
    $nome_peso = $config['nome_peso'] ?? '700';
    $nome_maiusculo = $config['nome_maiusculo'] ?? 'sim';
    $nome_tamanho = min($nome_tamanho, $tamanho_altura * 0.12);
    $nome_tamanho = max($nome_tamanho, $tamanho_altura * 0.06);
    
    $classe_cor = $config['classe_cor'] ?? '#1a2a3a';
    $classe_tamanho = floatval($config['classe_tamanho'] ?? 11);
    $classe_peso = $config['classe_peso'] ?? '600';
    $classe_tamanho = min($classe_tamanho, $tamanho_altura * 0.1);
    $classe_tamanho = max($classe_tamanho, $tamanho_altura * 0.05);
    
    $mostrar_linha_ondulada = $config['mostrar_linha_ondulada'] ?? 'sim';
    $linha_ondulada_cor = $config['linha_ondulada_cor'] ?? '#d4a843';
    $linha_ondulada_altura = floatval($config['linha_ondulada_altura'] ?? 3);
    $linha_ondulada_posicao = $config['linha_ondulada_posicao'] ?? 'antes_nome';
    $linha_ondulada_offset_y = floatval($config['linha_ondulada_offset_y'] ?? 0);
    $linha_ondulada_curvatura = floatval($config['linha_ondulada_curvatura'] ?? 1);
    
    $mostrar_qr_code_frente = $config['mostrar_qr_code_frente'] ?? 'nao';
    $qr_code_tamanho = floatval($config['qr_code_tamanho'] ?? 12);
    $qr_code_tamanho = min($qr_code_tamanho, $tamanho_largura * 0.15);
    $qr_code_tamanho = max($qr_code_tamanho, $tamanho_altura * 0.06);
    
    $mostrar_assinatura_frente = $config['mostrar_assinatura_frente'] ?? 'sim';
    $assinatura_frente_nome = $config['assinatura_frente_nome'] ?? 'Dr. Carlos Manuel Reis';
    $assinatura_frente_tamanho = floatval($config['assinatura_frente_tamanho'] ?? 0.35);
    
    // DADOS DA EMPRESA
    $enderecoEmpresa = $empresa['endereco'] ?? 'Município do Bom Jesus – Km 44, Desvio';
    $cidadeEmpresa = $empresa['cidade'] ?? 'Província de Ícolo e Bengo – Angola';
    $telefoneEmpresa = $empresa['telefone'] ?? '923 456 789 / 934 567 890';
    $emailEmpresa = $empresa['email'] ?? 'www.casteloreisfilhos.ao';
    
    $nome_aluno = $aluno['nome'] ?? '';
    if ($nome_maiusculo == 'sim') {
        $nome_aluno = strtoupper($nome_aluno);
    }
    
    // FUNDO DECORADO
    $fundos_decorados = [
        'classico' => '',
        'gradiente' => 'background: linear-gradient(135deg, ' . $cor_primaria . ' 0%, ' . $cor_secundaria . ' 100%); opacity: 0.08;',
        'geometrico' => 'background: repeating-linear-gradient(45deg, ' . $cor_primaria . ', ' . $cor_primaria . ' 2mm, ' . $cor_secundaria . ' 2mm, ' . $cor_secundaria . ' 4mm); opacity: 0.08;',
        'ondas' => 'background: radial-gradient(circle at 20% 50%, ' . $cor_primaria . ' 0%, transparent 50%), radial-gradient(circle at 80% 50%, ' . $cor_secundaria . ' 0%, transparent 50%); opacity: 0.12;',
        'diamante' => 'background: linear-gradient(45deg, ' . $cor_primaria . ' 25%, transparent 25%, transparent 75%, ' . $cor_primaria . ' 75%), linear-gradient(45deg, ' . $cor_primaria . ' 25%, transparent 25%, transparent 75%, ' . $cor_primaria . ' 75%); background-size: 4mm 4mm; background-position: 0 0, 2mm 2mm; opacity: 0.08;',
    ];
    $fundo_style = ($fundo_decorado == 'sim' && isset($fundos_decorados[$layout])) ? $fundos_decorados[$layout] : '';
    
    // LOGO
    if (empty($logo_path) || !file_exists($_SERVER['DOCUMENT_ROOT'] . $logo_path)) {
        $logo_path = '';
    }
    
    $logo_fundo_html = '';
    if (!empty($logo_fundo_path) && file_exists($_SERVER['DOCUMENT_ROOT'] . $logo_fundo_path)) {
        $logo_fundo_html = '<div class="logo-fundo"><img src="' . $logo_fundo_path . '" alt="Logo Fundo"></div>';
    }
    
    // LINHA ONDULADA
    $linha_ondulada_html = '';
    if ($mostrar_linha_ondulada == 'sim') {
        $linha_ondulada_html = '<div class="linha-ondulada-container" style="padding:0 2mm;margin:0.2mm 0;">' . 
            gerarLinhaOndulada($linha_ondulada_cor, $linha_ondulada_altura, $linha_ondulada_offset_y, $linha_ondulada_curvatura) . 
            '</div>';
    }
    
    // QR CODE
    $qr_code_html = '';
    if ($mostrar_qr_code_frente == 'sim') {
        $qr_dados = 'ID: ' . ($aluno['id'] ?? '') . ' | Nome: ' . ($aluno['nome'] ?? '') . ' | Classe: ' . ($aluno['Classe'] ?? '') . ' | Turma: ' . ($aluno['TURMA'] ?? '') . ' | Ano: ' . $ano_letivo;
        $qr_code_html = '<div class="qr-code-frente" style="width:' . $qr_code_tamanho . 'mm;height:' . $qr_code_tamanho . 'mm;flex-shrink:0;border:1px solid ' . $cor_secundaria . ';border-radius:1.5mm;overflow:hidden;padding:0.5mm;background:white;">' . 
            gerarQRCodeSVG($qr_dados, 150) . 
            '</div>';
    }
    
    // ASSINATURA FRENTE
    $assinatura_html = '';
    if ($mostrar_assinatura_frente == 'sim') {
        $assinatura_html = '<div class="assinatura-frente" style="text-align:center;margin-top:0.2mm;border-top:1px solid ' . $cor_texto . ';padding-top:0.2mm;width:100%;">';
        $assinatura_html .= '<span style="font-size:' . $assinatura_frente_tamanho . 'em;color:' . $cor_texto . ';font-weight:600;letter-spacing:0.3px;">' . htmlspecialchars($assinatura_frente_nome) . '</span>';
        $assinatura_html .= '</div>';
    }
    
    // ============================================
    // CALCULAR ALTURAS
    // ============================================
    $altura_cabecalho = min(14, $tamanho_altura * 0.16);
    $altura_rodape = min(7, $tamanho_altura * 0.07);
    $altura_titulo = min(5, $tamanho_altura * 0.05);
    $altura_info = $mostrar_info_importante == 'sim' ? min(12, $tamanho_altura * 0.12) : 0;
    $altura_assinatura = ($mostrar_assinatura_frente == 'sim' && $mostrar_info_importante == 'nao') ? min(5, $tamanho_altura * 0.05) : 0;
    $altura_linha_ondulada = ($mostrar_linha_ondulada == 'sim') ? min(4, $tamanho_altura * 0.04) : 0;
    
    $altura_disponivel = $tamanho_altura - $altura_cabecalho - $altura_rodape - $altura_titulo - $altura_info - $altura_assinatura - $altura_linha_ondulada;
    $altura_dados = max(18, $altura_disponivel);
    
    // FONTE BASE
    $fonte_base = $tamanho_fonte;
    
    // TAMANHO DO LOGO
    $tamanho_logo_texto = max($fonte_base * 0.85, $tamanho_altura * 0.055);
    $tamanho_logo_sub = max($fonte_base * 0.5, $tamanho_altura * 0.035);
    $tamanho_logo_img = min(8, $tamanho_altura * 0.075);
    
    // ============================================
    // HTML DO CARTÃO FRENTE
    // ============================================
    $html = '<div class="cartao cartao-frente" style="width:' . $tamanho_largura . 'mm;height:' . $tamanho_altura . 'mm;background:' . $cor_fundo . ';border-radius:' . $borda_arredondada . 'mm;border:1px solid ' . $cor_secundaria . ';font-size:' . $fonte_base . 'pt;position:relative;overflow:hidden;display:flex;flex-direction:column;box-sizing:border-box;">';
    $html .= '<div class="fundo-decorado" style="' . $fundo_style . ';position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:0;"></div>';
    $html .= $logo_fundo_html;
    
    // HEADER
    $html .= '<div class="cartao-header" style="background:' . $cor_cabecalho . ';border-bottom:2px solid ' . $cor_secundaria . ';position:relative;z-index:1;flex-shrink:0;padding:' . ($altura_cabecalho * 0.15) . 'mm ' . ($tamanho_largura * 0.04) . 'mm;text-align:center;">';
    $html .= '<div class="logo-container" style="display:flex;align-items:center;justify-content:center;gap:2mm;">';
    
    if (!empty($logo_path) && file_exists($_SERVER['DOCUMENT_ROOT'] . $logo_path)) {
        $html .= '<div class="logo-img" style="width:' . $tamanho_logo_img . 'mm;height:' . $tamanho_logo_img . 'mm;border-radius:50%;overflow:hidden;background:#fff;border:1.5px solid ' . $cor_secundaria . ';flex-shrink:0;"><img src="' . $logo_path . '" alt="Logo" style="width:100%;height:100%;object-fit:cover;"></div>';
    }
    
    $html .= '<div>';
    $html .= '<div class="logo-text" style="font-size:' . $tamanho_logo_texto . 'pt;font-weight:900;color:' . $cor_secundaria . ';letter-spacing:1px;text-transform:uppercase;line-height:1.2;">COMPLEXO ESCOLAR PRIVADO<br>CASTELO REIS</div>';
    $html .= '<div class="logo-sub" style="font-size:' . $tamanho_logo_sub . 'pt;color:#f0e6d3;font-weight:500;letter-spacing:2px;margin-top:0.3mm;text-transform:uppercase;">EDUCAR PARA TRANSFORMAR, FORMAR PARA A VIDA.</div>';
    $html .= '</div></div></div>';
    
    // BODY
    $html .= '<div class="cartao-body" style="padding:' . ($tamanho_altura * 0.012) . 'mm ' . ($tamanho_largura * 0.025) . 'mm;position:relative;z-index:1;flex:1;display:flex;flex-direction:column;min-height:0;">';
    
    // TÍTULO
    $html .= '<div class="titulo-cartao" style="text-align:center;font-size:' . ($fonte_base * 0.7) . 'pt;font-weight:700;color:' . $cor_texto . ';text-transform:uppercase;letter-spacing:1px;border-bottom:1.5px solid ' . $cor_secundaria . ';padding-bottom:0.4mm;margin-bottom:0.4mm;">CARTÃO DE ESTUDANTE</div>';
    
    // LINHA ONDULADA - ANTES DO NOME
    if ($linha_ondulada_posicao == 'antes_nome' || $linha_ondulada_posicao == 'ambos') {
        $html .= $linha_ondulada_html;
    }
    
    // ============================================
    // FOTO + DADOS (COM QR CODE NA MESMA LINHA)
    // ============================================
    $html .= '<div class="foto-dados" style="display:flex;gap:' . ($tamanho_largura * 0.015) . 'mm;align-items:stretch;flex:1;min-height:0;">';
    
    // FOTO
    if ($mostrar_foto == 'sim') {
        $html .= '<div class="foto-container" style="width:' . $foto_largura . 'mm;height:' . $foto_altura . 'mm;border:1.5px solid ' . $cor_secundaria . ';background:#f5f0eb;border-radius:1.5mm;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;align-self:center;">';
        if ($tem_foto) {
            $html .= '<img src="' . $foto_url . '" alt="Foto do Aluno" style="width:100%;height:100%;object-fit:cover;">';
        } else {
            $html .= '<div class="sem-foto" style="font-size:' . ($fonte_base * 2) . 'pt;color:#b0a090;">👤</div>';
        }
        $html .= '</div>';
    }
    
    // DADOS E QR CODE
    $html .= '<div class="dados-qr-container" style="flex:1;display:flex;flex-direction:row;gap:' . ($tamanho_largura * 0.01) . 'mm;min-height:0;align-items:stretch;">';
    
    // DADOS
    $html .= '<div class="dados-container" style="flex:1;display:flex;flex-direction:column;min-height:0;overflow:hidden;">';
    
    // NOME
    $html .= '<div class="campo" style="margin-bottom:' . ($tamanho_altura * 0.003) . 'mm;padding-bottom:' . ($tamanho_altura * 0.002) . 'mm;border-bottom:0.5px dashed #e0d5c8;margin-top:1.5mm;">';
    $html .= '<span class="label" style="font-weight:600;color:#5a4a3a;font-size:' . ($fonte_base * 0.45) . 'pt;text-transform:uppercase;letter-spacing:0.3px;display:block;">NOME DO ESTUDANTE</span>';
    $html .= '<div class="valor destaque" style="font-weight:' . $nome_peso . ';color:' . $nome_cor . ';font-size:' . $nome_tamanho . 'pt;margin-top:0.1mm;line-height:1.1;word-break:break-word;overflow-wrap:break-word;">' . htmlspecialchars($nome_aluno) . '</div></div>';
    
    // Nº ESTUDANTE + CLASSE
    $html .= '<div class="campo-duplo" style="display:flex;gap:' . ($tamanho_largura * 0.015) . 'mm;margin-bottom:' . ($tamanho_altura * 0.002) . 'mm;">';
    $html .= '<div class="item" style="flex:1;min-width:0;"><span class="label" style="font-weight:600;color:#5a4a3a;font-size:' . ($fonte_base * 0.45) . 'pt;text-transform:uppercase;letter-spacing:0.3px;display:block;">Nº DE ESTUDANTE</span><div class="valor" style="font-weight:600;color:' . $cor_texto . ';font-size:' . ($fonte_base * 0.6) . 'pt;margin-top:0.1mm;word-break:break-word;">' . $num_estudante . '</div></div>';
    $html .= '<div class="item" style="flex:1;min-width:0;"><span class="label" style="font-weight:600;color:#5a4a3a;font-size:' . ($fonte_base * 0.45) . 'pt;text-transform:uppercase;letter-spacing:0.3px;display:block;">CLASSE / TURMA</span>';
    $html .= '<div class="valor" style="font-weight:' . $classe_peso . ';color:' . $classe_cor . ';font-size:' . $classe_tamanho . 'pt;margin-top:0.1mm;word-break:break-word;">' . htmlspecialchars($classe_turma) . '</div></div>';
    $html .= '</div>';
    
    // TURNO + ANO
    $html .= '<div class="campo-duplo" style="display:flex;gap:' . ($tamanho_largura * 0.015) . 'mm;padding-top:' . ($tamanho_altura * 0.001) . 'mm;border-top:0.5px dashed #e0d5c8;flex:1;align-items:flex-start;">';
    $html .= '<div class="item" style="flex:1;min-width:0;"><span class="label" style="font-weight:600;color:#5a4a3a;font-size:' . ($fonte_base * 0.4) . 'pt;text-transform:uppercase;letter-spacing:0.3px;display:block;">TURNO</span><div class="valor" style="font-weight:600;color:' . $cor_texto . ';font-size:' . ($fonte_base * 0.55) . 'pt;margin-top:0.1mm;">' . $turno . '</div></div>';
    $html .= '<div class="item" style="flex:1;min-width:0;"><span class="label" style="font-weight:600;color:#5a4a3a;font-size:' . ($fonte_base * 0.4) . 'pt;text-transform:uppercase;letter-spacing:0.3px;display:block;">ANO LECTIVO</span><div class="valor" style="font-weight:600;color:' . $cor_texto . ';font-size:' . ($fonte_base * 0.55) . 'pt;margin-top:0.1mm;">' . $ano_letivo . '</div></div>';
    $html .= '</div>';
    
    $html .= '</div>'; // FIM dados-container
    
    // QR CODE
    if ($mostrar_qr_code_frente == 'sim' && !empty($qr_code_html)) {
        $html .= '<div style="display:flex;align-items:center;justify-content:center;flex-shrink:0;padding-left:' . ($tamanho_largura * 0.005) . 'mm;">' . $qr_code_html . '</div>';
    }
    
    $html .= '</div>'; // FIM dados-qr-container
    $html .= '</div>'; // FIM foto-dados
    
    // LINHA ONDULADA - DEPOIS DO NOME
    if ($linha_ondulada_posicao == 'depois_nome' || $linha_ondulada_posicao == 'ambos') {
        $html .= $linha_ondulada_html;
    }
    
    // ASSINATURA
    if ($mostrar_assinatura_frente == 'sim' && $mostrar_info_importante == 'nao') {
        $html .= $assinatura_html;
    }
    
    // INFORMAÇÕES IMPORTANTES
    if ($mostrar_info_importante == 'sim') {
        $html .= '<div class="info-importante" style="margin-top:' . ($tamanho_altura * 0.002) . 'mm;padding:' . ($tamanho_altura * 0.006) . 'mm ' . ($tamanho_largura * 0.012) . 'mm;background:#f8f4ef;border-radius:1.5mm;border-left:1.5px solid ' . $cor_secundaria . ';flex-shrink:0;">';
        $html .= '<div class="titulo-info" style="font-size:' . ($fonte_base * 0.38) . 'pt;font-weight:700;color:' . $cor_texto . ';text-transform:uppercase;letter-spacing:0.5px;margin-bottom:0.1mm;">INFORMAÇÕES IMPORTANTES</div>';
        $html .= '<ul class="texto-info" style="font-size:' . ($fonte_base * 0.32) . 'pt;color:#4a3a2a;line-height:1.1;list-style:none;padding-left:0;margin:0;">';
        $html .= '<li style="padding:0.04mm 0;padding-left:1.5mm;position:relative;">Este cartão é pessoal e intransmissível.</li>';
        $html .= '<li style="padding:0.04mm 0;padding-left:1.5mm;position:relative;">Deve ser apresentado sempre que solicitado.</li>';
        $html .= '<li style="padding:0.04mm 0;padding-left:1.5mm;position:relative;">Em caso de perda, comunique imediatamente à Direcção da Escola.</li>';
        $html .= '</ul></div>';
        
        if ($mostrar_assinatura_frente == 'sim') {
            $html .= $assinatura_html;
        }
    }
    
    $html .= '</div>'; // FIM cartao-body
    
    // FOOTER
    $html .= '<div class="cartao-footer" style="background:' . $cor_rodape . ';border-top:1.5px solid ' . $cor_secundaria . ';padding:' . ($tamanho_altura * 0.006) . 'mm ' . ($tamanho_largura * 0.025) . 'mm;display:flex;justify-content:space-between;align-items:center;font-size:' . ($fonte_base * 0.38) . 'pt;color:#5a4a3a;position:relative;z-index:1;flex-shrink:0;">';
    $html .= '<div class="contato" style="text-align:center;flex:1;min-width:0;"><strong style="color:' . $cor_texto . ';">' . htmlspecialchars($enderecoEmpresa) . '</strong><br>' . htmlspecialchars($cidadeEmpresa) . '</div>';
    $html .= '<div class="contato" style="text-align:center;flex:1;min-width:0;"><strong style="color:' . $cor_texto . ';">' . htmlspecialchars($telefoneEmpresa) . '</strong><br>' . htmlspecialchars($emailEmpresa) . ' | ' . $telefone2 . '</div>';
    $html .= '</div>';
    
    $html .= '</div>'; // FIM cartao-frente
    
    return $html;
}

// ============================================
// FUNÇÃO PARA GERAR VERSO DO CARTÃO - TABELAS DIVIDIDAS
// ============================================
function gerarVersoCartao($aluno, $config, $empresa, $ano_letivo, $telefone2) {
    $cor_primaria = $config['cor_primaria'];
    $cor_secundaria = $config['cor_secundaria'];
    $cor_fundo = $config['cor_fundo'];
    $cor_texto = $config['cor_texto'];
    $cor_cabecalho = $config['cor_cabecalho'];
    $cor_rodape = $config['cor_rodape'];
    $tamanho_largura = $config['tamanho_largura'];
    $tamanho_altura = $config['tamanho_altura'];
    $tamanho_fonte = $config['tamanho_fonte'];
    $borda_arredondada = $config['borda_arredondada'];
    $fundo_decorado = $config['fundo_decorado'];
    $layout = $config['layout'];
    
    $tabela_altura_linha = $config['tabela_altura_linha'] ?? '3.5';
    $tabela_distribuicao = $config['tabela_distribuicao'] ?? '1.2';
    $verso_offset_x = floatval($config['verso_offset_x'] ?? 0);
    $verso_offset_y = floatval($config['verso_offset_y'] ?? 0);
    
    $mostrar_assinatura_verso = $config['mostrar_assinatura_verso'] ?? 'sim';
    $assinatura_verso_nome = $config['assinatura_verso_nome'] ?? 'Dr. Carlos Manuel Reis';
    $assinatura_verso_tamanho = $config['assinatura_verso_tamanho'] ?? '0.55';
    $assinatura_verso_cargo = $config['assinatura_verso_cargo'] ?? 'DIRECTOR DA ESCOLA';
    
    $fundos_decorados = [
        'classico' => '',
        'gradiente' => 'background: linear-gradient(135deg, ' . $cor_primaria . ' 0%, ' . $cor_secundaria . ' 100%); opacity: 0.08;',
        'geometrico' => 'background: repeating-linear-gradient(45deg, ' . $cor_primaria . ', ' . $cor_primaria . ' 2mm, ' . $cor_secundaria . ' 2mm, ' . $cor_secundaria . ' 4mm); opacity: 0.08;',
        'ondas' => 'background: radial-gradient(circle at 20% 50%, ' . $cor_primaria . ' 0%, transparent 50%), radial-gradient(circle at 80% 50%, ' . $cor_secundaria . ' 0%, transparent 50%); opacity: 0.12;',
        'diamante' => 'background: linear-gradient(45deg, ' . $cor_primaria . ' 25%, transparent 25%, transparent 75%, ' . $cor_primaria . ' 75%), linear-gradient(45deg, ' . $cor_primaria . ' 25%, transparent 25%, transparent 75%, ' . $cor_primaria . ' 75%); background-size: 4mm 4mm; background-position: 0 0, 2mm 2mm; opacity: 0.08;',
    ];
    $fundo_style = ($fundo_decorado == 'sim' && isset($fundos_decorados[$layout])) ? $fundos_decorados[$layout] : '';
    
    $data_atual = date('d/m/Y');
    
    $tem_assinatura = ($mostrar_assinatura_verso == 'sim');
    
    $altura_cartao = floatval($tamanho_altura);
    $largura_cartao = floatval($tamanho_largura);
    
    // ============================================
    // CALCULAR ALTURAS
    // ============================================
    $altura_cabecalho = min(12, $altura_cartao * 0.14);
    $altura_rodape = min(6, $altura_cartao * 0.06);
    $altura_titulo = min(4, $altura_cartao * 0.045);
    $altura_encarregado = min(7, $altura_cartao * 0.07);
    $altura_assinatura_espaco = $tem_assinatura ? min(7, $altura_cartao * 0.07) : 0;
    $padding_vertical = 2;
    
    $altura_disponivel = $altura_cartao - $altura_cabecalho - $altura_rodape - $altura_titulo - $altura_encarregado - $altura_assinatura_espaco - $padding_vertical;
    $altura_tabela = max(28, $altura_disponivel);
    
    // Dividir em duas tabelas
    $meses_tabela1 = ['SET', 'OUT', 'NOV', 'DEZ', 'JAN'];
    $meses_tabela2 = ['FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL'];
    
    $total_linhas = count($meses_tabela1) + count($meses_tabela2);
    $altura_linha_calculada = max(2.5, min(5.5, $altura_tabela / max($total_linhas, 1)));
    
    $fonte_base = floatval($tamanho_fonte);
    
    // ============================================
    // HTML DO CARTÃO VERSO
    // ============================================
    $html = '<div class="cartao cartao-verso" style="width:' . $largura_cartao . 'mm;height:' . $altura_cartao . 'mm;background:' . $cor_fundo . ';border-radius:' . $borda_arredondada . 'mm;border:1px solid ' . $cor_secundaria . ';font-size:' . $fonte_base . 'pt;position:relative;overflow:hidden;transform:translate(' . $verso_offset_x . 'mm, ' . $verso_offset_y . 'mm);display:flex;flex-direction:column;box-sizing:border-box;page-break-inside:avoid;">';
    $html .= '<div class="fundo-decorado-verso" style="' . $fundo_style . ';position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:0;"></div>';
    
    // HEADER
    $html .= '<div class="verso-header" style="background:' . $cor_cabecalho . ';border-bottom:2px solid ' . $cor_secundaria . ';position:relative;z-index:1;flex-shrink:0;padding:2mm ' . ($largura_cartao * 0.04) . 'mm 1.5mm;text-align:center;">';
    $html .= '<div class="verso-titulo" style="font-size:' . ($fonte_base * 0.8) . 'pt;font-weight:900;color:' . $cor_secundaria . ';text-transform:uppercase;letter-spacing:2px;">CONTROLE DE PAGAMENTOS</div>';
    $html .= '</div>';
    
    // BODY
    $html .= '<div class="verso-body" style="padding:1.5mm ' . ($largura_cartao * 0.025) . 'mm 1.5mm;position:relative;z-index:1;flex:1;display:flex;flex-direction:column;min-height:0;">';
    
    // TÍTULO
    $html .= '<div class="titulo-verso" style="text-align:center;font-size:' . ($fonte_base * 0.65) . 'pt;font-weight:700;color:' . $cor_texto . ';text-transform:uppercase;letter-spacing:1px;border-bottom:1.5px solid ' . $cor_secundaria . ';padding-bottom:0.3mm;margin-bottom:0.5mm;flex-shrink:0;">CONTROLE DE PAGAMENTOS</div>';
    
    // ENCARREGADO
    $html .= '<div class="info-encarregado" style="font-size:' . ($fonte_base * 0.55) . 'pt;padding:0.8mm 2mm;background:#f8f4ef;border-radius:1.5mm;margin-bottom:1mm;border-left:2px solid ' . $cor_secundaria . ';flex-shrink:0;">';
    $html .= '<strong style="color:' . $cor_texto . ';">Encarregado:</strong> ' . htmlspecialchars($aluno['Nome_do_Pai'] ?? 'Não informado') . ' &nbsp;|&nbsp; ';
    $html .= '<strong style="color:' . $cor_texto . ';">Contacto:</strong> ' . htmlspecialchars($aluno['Contacto4'] ?? 'Não informado') . '</div>';
    
    // ============================================
    // DUAS TABELAS LADO A LADO
    // ============================================
    $html .= '<div class="verso-tabelas-duplas" style="display:flex;gap:' . ($largura_cartao * 0.02) . 'mm;flex:1;min-height:0;">';
    
    // TABELA 1 - SETEMBRO A JANEIRO
    $html .= '<div class="verso-tabela-container" style="flex:1;display:flex;flex-direction:column;min-height:0;">';
    $html .= '<table class="tabela-pagamentos" style="width:100%;border-collapse:collapse;font-size:' . ($fonte_base * 0.5) . 'pt;flex:1;">';
    $html .= '<thead>';
    $html .= '<tr><th colspan="3" style="background:' . $cor_secundaria . ';color:' . $cor_texto . ';padding:0.3mm 0.5mm;text-align:center;font-weight:700;border:0.5px solid ' . $cor_secundaria . ';font-size:' . ($fonte_base * 0.6) . 'pt;">PAGAMENTOS</th></tr>';
    $html .= '<tr>';
    $html .= '<th style="background:' . $cor_secundaria . ';color:' . $cor_texto . ';padding:0.3mm 0.5mm;text-align:center;font-weight:700;border:0.5px solid ' . $cor_secundaria . ';font-size:' . ($fonte_base * 0.5) . 'pt;">Mês</th>';
    $html .= '<th style="background:' . $cor_secundaria . ';color:' . $cor_texto . ';padding:0.3mm 0.5mm;text-align:center;font-weight:700;border:0.5px solid ' . $cor_secundaria . ';font-size:' . ($fonte_base * 0.5) . 'pt;">Propina</th>';
    $html .= '<th style="background:' . $cor_secundaria . ';color:' . $cor_texto . ';padding:0.3mm 0.5mm;text-align:center;font-weight:700;border:0.5px solid ' . $cor_secundaria . ';font-size:' . ($fonte_base * 0.5) . 'pt;">Transporte</th>';
    $html .= '</tr></thead>';
    $html .= '<tbody>';
    
    foreach($meses_tabela1 as $mes) {
        $html .= '<tr><td style="border:0.5px solid #e0d5c8;padding:0.2mm 0.5mm;text-align:center;height:' . $altura_linha_calculada . 'mm;font-size:' . ($fonte_base * 0.5) . 'pt;font-weight:600;">' . $mes . '</td><td style="border:0.5px solid #e0d5c8;padding:0.2mm 0.5mm;text-align:center;height:' . $altura_linha_calculada . 'mm;"></td><td style="border:0.5px solid #e0d5c8;padding:0.2mm 0.5mm;text-align:center;height:' . $altura_linha_calculada . 'mm;"></td></tr>';
    }
    $html .= '</tbody></table>';
    $html .= '</div>';
    
    // TABELA 2 - FEVEREIRO A JULHO
    $html .= '<div class="verso-tabela-container" style="flex:1;display:flex;flex-direction:column;min-height:0;">';
    $html .= '<table class="tabela-pagamentos" style="width:100%;border-collapse:collapse;font-size:' . ($fonte_base * 0.5) . 'pt;flex:1;">';
    $html .= '<thead>';
    $html .= '<tr><th colspan="3" style="background:' . $cor_secundaria . ';color:' . $cor_texto . ';padding:0.3mm 0.5mm;text-align:center;font-weight:700;border:0.5px solid ' . $cor_secundaria . ';font-size:' . ($fonte_base * 0.6) . 'pt;">PAGAMENTOS</th></tr>';
    $html .= '<tr>';
    $html .= '<th style="background:' . $cor_secundaria . ';color:' . $cor_texto . ';padding:0.3mm 0.5mm;text-align:center;font-weight:700;border:0.5px solid ' . $cor_secundaria . ';font-size:' . ($fonte_base * 0.5) . 'pt;">Mês</th>';
    $html .= '<th style="background:' . $cor_secundaria . ';color:' . $cor_texto . ';padding:0.3mm 0.5mm;text-align:center;font-weight:700;border:0.5px solid ' . $cor_secundaria . ';font-size:' . ($fonte_base * 0.5) . 'pt;">Propina</th>';
    $html .= '<th style="background:' . $cor_secundaria . ';color:' . $cor_texto . ';padding:0.3mm 0.5mm;text-align:center;font-weight:700;border:0.5px solid ' . $cor_secundaria . ';font-size:' . ($fonte_base * 0.5) . 'pt;">Transporte</th>';
    $html .= '</tr></thead>';
    $html .= '<tbody>';
    
    foreach($meses_tabela2 as $mes) {
        $html .= '<tr><td style="border:0.5px solid #e0d5c8;padding:0.2mm 0.5mm;text-align:center;height:' . $altura_linha_calculada . 'mm;font-size:' . ($fonte_base * 0.5) . 'pt;font-weight:600;">' . $mes . '</td><td style="border:0.5px solid #e0d5c8;padding:0.2mm 0.5mm;text-align:center;height:' . $altura_linha_calculada . 'mm;"></td><td style="border:0.5px solid #e0d5c8;padding:0.2mm 0.5mm;text-align:center;height:' . $altura_linha_calculada . 'mm;"></td></tr>';
    }
    $html .= '</tbody></table>';
    $html .= '</div>';
    
    $html .= '</div>'; // FIM verso-tabelas-duplas
    
    // ASSINATURA (se habilitada) - ABAIXO DAS TABELAS
    if ($tem_assinatura) {
        $html .= '<div class="verso-assinatura-container" style="display:flex;justify-content:center;align-items:center;padding-top:1mm;flex-shrink:0;">';
        $html .= '<div class="assinatura-container" style="width:60%;text-align:center;">';
        $html .= '<div class="linha-assinatura" style="border-top:1.5px solid ' . $cor_texto . ';width:80%;margin:0 auto 0.3mm auto;"></div>';
        $html .= '<div class="assinatura-texto" style="font-size:' . $assinatura_verso_tamanho . 'em;text-align:center;color:' . $cor_texto . ';font-weight:700;letter-spacing:0.5px;text-transform:uppercase;line-height:1.2;">' . htmlspecialchars($assinatura_verso_nome) . '</div>';
        $html .= '<div class="assinatura-cargo" style="font-size:' . ($fonte_base * 0.35) . 'pt;text-align:center;color:#94a3b8;font-weight:400;margin-top:0.3mm;">' . htmlspecialchars($assinatura_verso_cargo) . '</div>';
        $html .= '</div>';
        $html .= '</div>';
    }
    
    $html .= '</div>'; // FIM verso-body
    
    // FOOTER
    $html .= '<div class="verso-footer" style="background:' . $cor_rodape . ';border-top:1.5px solid ' . $cor_secundaria . ';padding:' . ($altura_cartao * 0.006) . 'mm ' . ($largura_cartao * 0.025) . 'mm;text-align:center;font-size:' . ($fonte_base * 0.45) . 'pt;color:#5a4a3a;position:relative;z-index:1;flex-shrink:0;">';
    $html .= '<strong style="color:' . $cor_texto . ';">ID:</strong> ' . htmlspecialchars($aluno['id'] ?? '') . ' &nbsp;|&nbsp; ';
    $html .= '<strong style="color:' . $cor_texto . ';">ANO:</strong> ' . $ano_letivo . ' &nbsp;|&nbsp; ';
    $html .= '<strong style="color:' . $cor_texto . ';">Emitido:</strong> ' . $data_atual . '</div>';
    $html .= '</div>'; // FIM cartao-verso
    
    return $html;
}

// ============================================
// FUNÇÃO PARA GERAR GRADE DE CARTÕES - 2 POR LINHA
// ============================================
function gerarGradeCartoes($alunos, $config, $empresa, $ano_letivo, $telefone2, $tipo = 'frente') {
    $html = '';
    $total = count($alunos);
    
    $largura_cartao = floatval($config['tamanho_largura'] ?? 85);
    $altura_cartao = floatval($config['tamanho_altura'] ?? 120);
    
    $largura_a4 = 210;
    $altura_a4 = 297;
    
    $margem = 5;
    $espacamento = 6;
    $por_linha = 2;
    
    $total_largura_cartoes = $por_linha * $largura_cartao;
    $total_espacamento = ($por_linha - 1) * $espacamento;
    $espaco_largura = $largura_a4 - ($margem * 2);
    $espaco_sobrando = $espaco_largura - $total_largura_cartoes - $total_espacamento;
    $espacamento_extra = $espaco_sobrando / 2;
    
    $espaco_altura = $altura_a4 - ($margem * 2);
    $linhas_por_pagina = floor(($espaco_altura + $espacamento) / ($altura_cartao + $espacamento));
    $linhas_por_pagina = max(1, min($linhas_por_pagina, 3));
    
    $cartoes_por_pagina = $por_linha * $linhas_por_pagina;
    $total_paginas = max(1, ceil($total / $cartoes_por_pagina));
    
    for ($pagina = 0; $pagina < $total_paginas; $pagina++) {
        $html .= '<div class="pagina-cartoes">';
        
        $inicio = $pagina * $cartoes_por_pagina;
        $fim = min($inicio + $cartoes_por_pagina, $total);
        
        $altura_linha = ($altura_a4 - ($margem * 2) - (($linhas_por_pagina - 1) * $espacamento)) / $linhas_por_pagina;
        
        for ($linha = 0; $linha < $linhas_por_pagina; $linha++) {
            $html .= '<div class="linha-cartoes" style="height:' . $altura_linha . 'mm;">';
            
            $pos_inicio = $inicio + ($linha * $por_linha);
            
            if ($tipo === 'verso') {
                $indices = [];
                for ($j = $por_linha - 1; $j >= 0; $j--) {
                    $idx = $pos_inicio + $j;
                    if ($idx < $fim && $idx < $total) {
                        $indices[] = $idx;
                    }
                }
                while (count($indices) < $por_linha) {
                    $indices[] = -1;
                }
                
                foreach ($indices as $idx) {
                    if ($idx >= 0 && $idx < $total) {
                        $html .= '<div class="cartao-item">';
                        $html .= gerarVersoCartao($alunos[$idx], $config, $empresa, $ano_letivo, $telefone2);
                        $html .= '</div>';
                    } else {
                        $html .= '<div class="cartao-item vazio"></div>';
                    }
                }
            } else {
                for ($j = 0; $j < $por_linha; $j++) {
                    $idx = $pos_inicio + $j;
                    if ($idx < $fim && $idx < $total) {
                        $html .= '<div class="cartao-item">';
                        $html .= gerarFrenteCartao($alunos[$idx], $config, $empresa, $ano_letivo, $telefone2);
                        $html .= '</div>';
                    } else {
                        $html .= '<div class="cartao-item vazio"></div>';
                    }
                }
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
    }
    
    return $html;
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cartão Escolar - <?= $modo_massa ? 'Geração em Massa' : htmlspecialchars($aluno['nome'] ?? 'Aluno') ?></title>
    <style>
        :root {
            --cor-primaria: <?= $config['cor_primaria'] ?>;
            --cor-secundaria: <?= $config['cor_secundaria'] ?>;
            --cor-fundo: <?= $config['cor_fundo'] ?>;
            --cor-texto: <?= $config['cor_texto'] ?>;
            --cor-cabecalho: <?= $config['cor_cabecalho'] ?>;
            --cor-rodape: <?= $config['cor_rodape'] ?>;
            --tamanho-fonte: <?= $config['tamanho_fonte'] ?>pt;
            --borda-arredondada: <?= $config['borda_arredondada'] ?>mm;
            --largura-cartao: <?= $config['tamanho_largura'] ?>mm;
            --altura-cartao: <?= $config['tamanho_altura'] ?>mm;
        }

        @page {
            size: A4 portrait;
            margin: 0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            background: #e8e8e8;
            padding: 10px;
        }
        
        .cartao-wrapper {
            max-width: 297mm;
            margin: 0 auto;
        }
        
        .linha-cartoes {
            display: flex;
            gap: 5mm;
            justify-content: center;
            margin-bottom: 5mm;
            page-break-inside: avoid;
        }
        
        .cartao-item {
            flex: 0 0 auto;
            page-break-inside: avoid;
        }
        
        .cartao-item.vazio {
            visibility: hidden;
            flex: 0 0 <?= $config['tamanho_largura'] ?>mm;
            min-height: <?= $config['tamanho_altura'] ?>mm;
        }
        
        .config-panel, .massa-panel {
            background: #ffffff;
            border-radius: 12px;
            padding: 20px 25px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            max-width: 1200px;
            width: 100%;
            margin: 0 auto 20px auto;
        }
        
        .config-panel h3, .massa-panel h3 {
            color: #1a2a3a;
            font-size: 16px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .config-panel h3 .toggle-btn {
            background: #f1f5f9;
            border: none;
            padding: 5px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            color: #4a5568;
        }
        
        .config-panel .config-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
        }
        
        .config-panel .config-group {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        
        .config-panel .config-group label {
            font-size: 10px;
            font-weight: 600;
            color: #4a5568;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .config-panel .config-group input[type="color"] {
            width: 100%;
            height: 32px;
            padding: 2px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            cursor: pointer;
        }
        
        .config-panel .config-group input[type="number"],
        .config-panel .config-group input[type="text"] {
            width: 100%;
            padding: 5px 8px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 12px;
        }
        
        .config-panel .config-group select {
            width: 100%;
            padding: 5px 8px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 12px;
        }
        
        .config-panel .config-group .range-labels {
            display: flex;
            justify-content: space-between;
            font-size: 9px;
            color: #94a3b8;
        }
        
        .config-panel .config-group.checkbox-group {
            flex-direction: row;
            align-items: center;
            gap: 8px;
            padding-top: 6px;
        }
        
        .config-panel .config-group.checkbox-group label {
            text-transform: none;
            font-weight: 500;
            font-size: 12px;
            cursor: pointer;
        }
        
        .config-panel .config-group.checkbox-group input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--cor-secundaria);
            cursor: pointer;
        }
        
        .config-panel .config-divider {
            grid-column: 1 / -1;
            border-top: 2px solid #f1f5f9;
            padding-top: 10px;
            margin-top: 5px;
            font-size: 11px;
            font-weight: 700;
            color: #4a5568;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .config-panel .upload-logo {
            display: flex;
            gap: 6px;
            align-items: center;
            padding: 4px 8px;
            background: #f8fafc;
            border-radius: 6px;
            border: 1px dashed #e2e8f0;
            flex-wrap: wrap;
        }
        
        .config-panel .upload-logo .preview-logo {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            flex-shrink: 0;
        }
        
        .config-panel .upload-logo .preview-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .config-panel .upload-logo input[type="file"] {
            font-size: 10px;
            flex: 1;
            min-width: 80px;
        }
        
        .config-actions, .massa-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
            flex-wrap: wrap;
        }
        
        .config-actions .btn {
            padding: 7px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .config-actions .btn-save {
            background: var(--cor-secundaria);
            color: #1a2a3a;
        }
        
        .config-actions .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(212,168,67,0.3);
        }
        
        .config-actions .btn-reset {
            background: #f1f5f9;
            color: #4a5568;
        }
        
        .config-actions .btn-reset:hover {
            background: #e2e8f0;
        }
        
        .config-actions .btn-print {
            background: #1a2a3a;
            color: #fff;
        }
        
        .config-actions .btn-print:hover {
            background: #2c3e50;
            transform: translateY(-2px);
        }
        
        .config-actions .btn-print-verso {
            background: #2c3e50;
            color: #fff;
        }
        
        .config-actions .btn-print-verso:hover {
            background: #1a2a3a;
            transform: translateY(-2px);
        }
        
        .config-panel .config-info {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 10px;
            text-align: center;
            border-top: 1px solid #f1f5f9;
            padding-top: 10px;
        }

        .massa-panel {
            border-left: 4px solid var(--cor-secundaria);
        }
        
        .massa-panel .massa-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .massa-panel .massa-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .massa-panel .massa-group label {
            font-size: 11px;
            font-weight: 600;
            color: #4a5568;
        }
        
        .massa-panel .massa-group select,
        .massa-panel .massa-group input[type="number"],
        .massa-panel .massa-group input[type="text"] {
            padding: 8px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 13px;
            width: 100%;
        }
        
        .massa-panel .massa-group select:focus,
        .massa-panel .massa-group input:focus {
            border-color: var(--cor-secundaria);
            outline: none;
        }
        
        .massa-panel .btn-massa {
            background: var(--cor-secundaria);
            color: #1a2a3a;
            padding: 10px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .massa-panel .btn-massa:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(212,168,67,0.3);
        }
        
        .massa-panel .btn-massa-secondary {
            background: #f1f5f9;
            color: #4a5568;
            padding: 10px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .massa-panel .btn-massa-secondary:hover {
            background: #e2e8f0;
        }
        
        .massa-panel .massa-erro {
            background: #fee2e2;
            color: #991b1b;
            padding: 10px 15px;
            border-radius: 8px;
            margin-top: 10px;
            font-weight: 500;
        }
        
        .massa-panel .massa-sucesso {
            background: #d1fae5;
            color: #065f46;
            padding: 10px 15px;
            border-radius: 8px;
            margin-top: 10px;
            font-weight: 500;
        }
        
        .massa-panel .massa-alerta {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
            padding: 10px 15px;
            border-radius: 8px;
            margin-top: 10px;
        }
        
        .massa-panel .massa-alerta .titulo {
            font-weight: 700;
            font-size: 13px;
        }
        
        .massa-panel .massa-alerta ul {
            margin: 5px 0 0 20px;
            max-height: 100px;
            overflow-y: auto;
        }
        
        .massa-panel .massa-alerta ul li {
            font-size: 12px;
            padding: 2px 0;
        }
        
        .contador-cartoes {
            background: var(--cor-secundaria);
            color: #1a2a3a;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 14px;
            display: inline-block;
            margin: 10px auto;
            text-align: center;
            width: 100%;
        }

        .cartao-frente {
            background: var(--cor-fundo);
            border-radius: var(--borda-arredondada);
            box-shadow: 0 2mm 4mm rgba(0,0,0,0.1);
            overflow: hidden;
            border: 1px solid var(--cor-secundaria);
            font-size: var(--tamanho-fonte);
            position: relative;
            width: var(--largura-cartao);
            min-height: var(--altura-cartao);
        }
        
        .fundo-decorado {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }
        
        .logo-fundo {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.15;
            z-index: 0;
            pointer-events: none;
            max-width: 65%;
            max-height: 65%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logo-fundo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .qr-code-frente {
            flex-shrink: 0;
            border: 1px solid var(--cor-secundaria);
            border-radius: 2mm;
            overflow: hidden;
            padding: 1mm;
            background: white;
        }
        
        .qr-code-frente img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .assinatura-frente {
            text-align: center;
            margin-top: 0.5mm;
            border-top: 1px solid var(--cor-texto);
            padding-top: 0.5mm;
            width: 100%;
        }
        
        .linha-ondulada-container {
            padding: 0 3mm;
            margin: 0.5mm 0;
        }
        
        .cartao-header {
            background: var(--cor-cabecalho);
            padding: 2.5mm 4mm 2mm;
            text-align: center;
            border-bottom: 3px solid var(--cor-secundaria);
            position: relative;
            z-index: 1;
        }
        
        .cartao-header .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 2mm;
        }
        
        .cartao-header .logo-img {
            width: 7mm;
            height: 7mm;
            border-radius: 50%;
            overflow: hidden;
            background: #fff;
            border: 1px solid var(--cor-secundaria);
            flex-shrink: 0;
        }
        
        .cartao-header .logo-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .cartao-header .logo-text {
            font-size: 0.8em;
            font-weight: 800;
            color: var(--cor-secundaria);
            letter-spacing: 0.5px;
            text-transform: uppercase;
            line-height: 1.1;
        }
        
        .cartao-header .logo-sub {
            font-size: 0.45em;
            color: #f0e6d3;
            font-weight: 400;
            letter-spacing: 1px;
            margin-top: 0.3mm;
            text-transform: uppercase;
        }
        
        .cartao-body {
            padding: 2mm 3mm 1.5mm;
            position: relative;
            z-index: 1;
        }
        
        .cartao-body .titulo-cartao {
            text-align: center;
            font-size: 0.85em;
            font-weight: 700;
            color: var(--cor-texto);
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 2px solid var(--cor-secundaria);
            padding-bottom: 1mm;
            margin-bottom: 1mm;
        }
        
        .foto-container {
            border: 2px solid var(--cor-secundaria);
            border-radius: 2mm;
            overflow: hidden;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .foto-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .foto-container .sem-foto {
            color: #b0a090;
        }
        
        .dados-container {
            flex: 1;
        }
        
        .dados-container .campo {
            margin-bottom: 0.5mm;
            padding-bottom: 0.3mm;
            border-bottom: 0.5px dashed #e0d5c8;
        }
        
        .dados-container .campo:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .dados-container .label {
            font-weight: 600;
            color: #5a4a3a;
            font-size: 0.45em;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: block;
        }
        
        .dados-container .valor {
            font-weight: 600;
            color: var(--cor-texto);
            font-size: 0.65em;
            margin-top: 0.2mm;
        }
        
        .dados-container .valor.destaque {
            color: var(--cor-secundaria);
        }
        
        .campo-duplo {
            display: flex;
            gap: 2mm;
        }
        
        .campo-duplo .item {
            flex: 1;
        }
        
        .campo-duplo .item .label {
            font-weight: 600;
            color: #5a4a3a;
            font-size: 0.45em;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: block;
        }
        
        .campo-duplo .item .valor {
            font-weight: 600;
            color: var(--cor-texto);
            font-size: 0.65em;
            margin-top: 0.2mm;
        }
        
        .info-importante {
            margin-top: 1mm;
            padding: 1mm 2mm;
            background: #f8f4ef;
            border-radius: 2mm;
            border-left: 2px solid var(--cor-secundaria);
        }
        
        .info-importante .titulo-info {
            font-size: 0.45em;
            font-weight: 700;
            color: var(--cor-texto);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.3mm;
        }
        
        .info-importante .texto-info {
            font-size: 0.4em;
            color: #4a3a2a;
            line-height: 1.2;
            list-style: none;
            padding-left: 0;
            margin: 0;
        }
        
        .info-importante .texto-info li {
            padding: 0.1mm 0;
            padding-left: 2mm;
            position: relative;
        }
        
        .info-importante .texto-info li::before {
            content: "•";
            color: var(--cor-secundaria);
            font-weight: 700;
            position: absolute;
            left: 0;
        }
        
        .cartao-footer {
            background: var(--cor-rodape);
            border-top: 2px solid var(--cor-secundaria);
            padding: 1mm 3mm;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.45em;
            color: #5a4a3a;
            position: relative;
            z-index: 1;
        }
        
        .cartao-footer .contato {
            text-align: center;
            flex: 1;
        }
        
        .cartao-footer .contato strong {
            color: var(--cor-texto);
        }
        
        .cartao-verso {
            background: var(--cor-fundo);
            border-radius: var(--borda-arredondada);
            box-shadow: 0 2mm 4mm rgba(0,0,0,0.1);
            overflow: hidden;
            border: 1px solid var(--cor-secundaria);
            position: relative;
            width: var(--largura-cartao);
            min-height: var(--altura-cartao);
            transition: transform 0.3s;
            page-break-inside: avoid;
        }
        
        .cartao-verso .fundo-decorado-verso {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }
        
        .cartao-verso .verso-header {
            background: var(--cor-cabecalho);
            padding: 3mm 4mm 2mm;
            text-align: center;
            border-bottom: 3px solid var(--cor-secundaria);
            position: relative;
            z-index: 1;
            flex-shrink: 0;
        }
        
        .cartao-verso .verso-header .verso-titulo {
            font-size: 0.9em;
            font-weight: 700;
            color: var(--cor-secundaria);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .cartao-verso .verso-body {
            padding: 2mm 3mm 2mm;
            position: relative;
            z-index: 1;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        
        .cartao-verso .titulo-verso {
            text-align: center;
            font-size: 0.7em;
            font-weight: 700;
            color: var(--cor-texto);
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 2px solid var(--cor-secundaria);
            padding-bottom: 0.5mm;
            margin-bottom: 0.5mm;
            flex-shrink: 0;
        }
        
        .cartao-verso .info-encarregado {
            font-size: 0.6em;
            padding: 1mm 2mm;
            background: #f8f4ef;
            border-radius: 1.5mm;
            margin-bottom: 1mm;
            border-left: 2px solid var(--cor-secundaria);
            flex-shrink: 0;
        }
        
        .cartao-verso .info-encarregado strong {
            color: var(--cor-texto);
        }
        
        .cartao-verso .verso-tabelas-duplas {
            display: flex;
            gap: 2mm;
            flex: 1;
            min-height: 0;
        }
        
        .cartao-verso .verso-tabela-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        
        .cartao-verso .tabela-pagamentos {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.55em;
            flex: 1;
        }
        
        .cartao-verso .tabela-pagamentos th {
            background: var(--cor-secundaria);
            color: var(--cor-texto);
            padding: 0.5mm 0.5mm;
            text-align: center;
            font-weight: 700;
            border: 0.5px solid var(--cor-secundaria);
        }
        
        .cartao-verso .tabela-pagamentos td {
            border: 0.5px solid #e0d5c8;
            padding: 0.3mm 0.5mm;
            text-align: center;
        }
        
        .cartao-verso .tabela-pagamentos tr:nth-child(even) td {
            background: #faf8f5;
        }
        
        .cartao-verso .verso-assinatura-container {
            display: flex;
            justify-content: center;
            align-items: center;
            padding-top: 1mm;
            flex-shrink: 0;
        }
        
        .cartao-verso .assinatura-container {
            width: 60%;
            text-align: center;
        }
        
        .cartao-verso .linha-assinatura {
            border-top: 1.5px solid var(--cor-texto);
            width: 80%;
            margin: 0 auto 0.3mm auto;
        }
        
        .cartao-verso .assinatura-texto {
            text-align: center;
            color: var(--cor-texto);
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            line-height: 1.2;
        }
        
        .cartao-verso .assinatura-cargo {
            font-size: 0.4em;
            text-align: center;
            color: #94a3b8;
            font-weight: 400;
            margin-top: 0.3mm;
        }
        
        .cartao-verso .verso-footer {
            background: var(--cor-rodape);
            border-top: 2px solid var(--cor-secundaria);
            padding: 1.5mm 3mm;
            text-align: center;
            font-size: 0.5em;
            color: #5a4a3a;
            position: relative;
            z-index: 1;
            flex-shrink: 0;
        }
        
        .cartao-verso .verso-footer strong {
            color: var(--cor-texto);
        }

        /* ============================================
           VISUALIZAÇÃO NA TELA
           ============================================ */
        .pagina-cartoes {
            display: flex;
            flex-direction: column;
            gap: 0;
            width: 210mm;
            min-height: 297mm;
            height: 297mm;
            padding: 5mm 5mm;
            box-sizing: border-box;
            margin: 0 auto 5mm auto;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            page-break-after: always;
            overflow: hidden;
        }

        .linha-cartoes {
            display: flex;
            flex-direction: row;
            justify-content: center;
            align-items: center;
            gap: 6mm;
            width: 100%;
            flex: 1;
            min-height: 0;
            max-height: 100%;
            margin: 0;
            padding: 0;
        }

        .cartao-item {
            flex: 0 0 auto;
            width: <?= $config['tamanho_largura'] ?>mm;
            height: <?= $config['tamanho_altura'] ?>mm;
            max-height: 100%;
            page-break-inside: avoid;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }

        .cartao-item.vazio {
            visibility: hidden;
            width: <?= $config['tamanho_largura'] ?>mm;
            height: <?= $config['tamanho_altura'] ?>mm;
        }

        .cartao-item > div {
            width: 100%;
            height: 100%;
            max-height: 100%;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
            overflow: hidden;
        }

        .cartao-frente, .cartao-verso {
            height: 100%;
            max-height: 100%;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
            overflow: hidden;
            border-radius: <?= $config['borda_arredondada'] ?>mm;
        }

        .cartao-frente .cartao-body,
        .cartao-verso .verso-body {
            flex: 1;
            overflow: hidden;
        }

        .cartao-frente .cartao-header,
        .cartao-verso .verso-header,
        .cartao-frente .cartao-footer,
        .cartao-verso .verso-footer {
            flex-shrink: 0;
        }

        /* ============================================
           IMPRESSÃO
           ============================================ */
        @media print {
            html, body {
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 210mm !important;
                min-height: 297mm !important;
            }
            
            .no-print {
                display: none !important;
            }
            
            .cartao-wrapper {
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 210mm !important;
            }
            
            .pagina-cartoes {
                page-break-after: always !important;
                width: 210mm !important;
                height: 297mm !important;
                min-height: 297mm !important;
                max-height: 297mm !important;
                padding: 5mm 5mm !important;
                margin: 0 !important;
                background: white !important;
                display: flex !important;
                flex-direction: column !important;
                gap: 0 !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
            }
            
            .pagina-cartoes:last-child {
                page-break-after: avoid !important;
            }
            
            .linha-cartoes {
                display: flex !important;
                flex-direction: row !important;
                justify-content: center !important;
                align-items: center !important;
                gap: 6mm !important;
                width: 100% !important;
                flex: 1 !important;
                min-height: 0 !important;
                max-height: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            
            .cartao-item {
                flex: 0 0 auto !important;
                width: <?= $config['tamanho_largura'] ?>mm !important;
                height: <?= $config['tamanho_altura'] ?>mm !important;
                max-height: 100% !important;
                page-break-inside: avoid !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden !important;
            }
            
            .cartao-item.vazio {
                visibility: hidden !important;
                width: <?= $config['tamanho_largura'] ?>mm !important;
                height: <?= $config['tamanho_altura'] ?>mm !important;
            }
            
            .cartao-item > div {
                width: 100% !important;
                height: 100% !important;
                max-height: 100% !important;
                display: flex !important;
                flex-direction: column !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
            }
            
            .cartao-frente, .cartao-verso {
                box-shadow: none !important;
                border: 1px solid #ccc !important;
                width: 100% !important;
                height: 100% !important;
                max-height: 100% !important;
                display: flex !important;
                flex-direction: column !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
                border-radius: <?= $config['borda_arredondada'] ?>mm !important;
            }
            
            .config-panel, .massa-panel, .contador-cartoes {
                display: none !important;
            }
            
            .cartao-frente .cartao-body,
            .cartao-verso .verso-body {
                flex: 1 !important;
                overflow: hidden !important;
            }
            
            .cartao-frente .cartao-header,
            .cartao-verso .verso-header,
            .cartao-frente .cartao-footer,
            .cartao-verso .verso-footer {
                flex-shrink: 0 !important;
            }
            
            .cartao-verso {
                transform: none !important;
                page-break-inside: avoid !important;
            }
            
            .cartao-verso .verso-body {
                flex: 1 !important;
                overflow: hidden !important;
                display: flex !important;
                flex-direction: column !important;
            }
            
            .cartao-verso .verso-tabelas-duplas {
                flex: 1 !important;
                min-height: 0 !important;
                overflow: hidden !important;
            }
            
            .cartao-verso .verso-tabela-container {
                flex: 1 !important;
                overflow: hidden !important;
            }
            
            .cartao-verso .tabela-pagamentos {
                width: 100% !important;
                height: 100% !important;
                flex: 1 !important;
            }
            
            .cartao-verso .tabela-pagamentos tbody {
                display: table-row-group !important;
            }
            
            .cartao-verso .tabela-pagamentos tr {
                display: table-row !important;
            }
            
            .cartao-verso .tabela-pagamentos td,
            .cartao-verso .tabela-pagamentos th {
                display: table-cell !important;
            }
        }
    </style>
</head>
<body>

<!-- ==========================================
     PAINEL DE CONFIGURAÇÃO
     ========================================== -->
<div class="config-panel no-print">
    <h3>
        ⚙️ Personalizar Cartão
        <button class="toggle-btn" onclick="toggleConfig()">Mostrar/Ocultar</button>
    </h3>
    
    <form method="POST" id="configForm" enctype="multipart/form-data">
        <div class="config-grid" id="configGrid">
            <!-- Layout -->
            <div class="config-group">
                <label>🎨 Layout</label>
                <select name="layout">
                    <option value="classico" <?= $config['layout'] == 'classico' ? 'selected' : '' ?>>Clássico</option>
                    <option value="gradiente" <?= $config['layout'] == 'gradiente' ? 'selected' : '' ?>>Gradiente</option>
                    <option value="geometrico" <?= $config['layout'] == 'geometrico' ? 'selected' : '' ?>>Geométrico</option>
                    <option value="ondas" <?= $config['layout'] == 'ondas' ? 'selected' : '' ?>>Ondas</option>
                    <option value="diamante" <?= $config['layout'] == 'diamante' ? 'selected' : '' ?>>Diamante</option>
                </select>
            </div>
            
            <!-- Upload Logo Cabeçalho -->
            <div class="config-group" style="grid-column: span 1;">
                <label>📤 Logo Cabeçalho</label>
                <div class="upload-logo">
                    <div class="preview-logo">
                        <?php if (!empty($config['logo_upload']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $config['logo_upload'])): ?>
                            <img src="<?= $config['logo_upload'] ?>" alt="Logo">
                        <?php else: ?>
                            <span style="font-size:10px;color:#999;">CR</span>
                        <?php endif; ?>
                    </div>
                    <input type="file" name="logo_upload" accept="image/*">
                    <button type="submit" name="upload_logo" class="btn btn-save" style="padding:3px 10px;font-size:10px;">⬆️</button>
                </div>
            </div>
            
            <!-- Upload Logo Fundo -->
            <div class="config-group" style="grid-column: span 1;">
                <label>🖼️ Logo Fundo (centro)</label>
                <div class="upload-logo">
                    <div class="preview-logo">
                        <?php if (!empty($config['logo_fundo']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $config['logo_fundo'])): ?>
                            <img src="<?= $config['logo_fundo'] ?>" alt="Logo Fundo">
                        <?php else: ?>
                            <span style="font-size:10px;color:#999;">🏫</span>
                        <?php endif; ?>
                    </div>
                    <input type="file" name="logo_fundo_upload" accept="image/*">
                    <button type="submit" name="upload_logo_fundo" class="btn btn-save" style="padding:3px 10px;font-size:10px;">⬆️</button>
                </div>
            </div>
            
            <!-- Ano Letivo -->
            <div class="config-group">
                <label>📅 Ano Lectivo</label>
                <input type="text" name="ano_letivo" value="<?= htmlspecialchars($ano_letivo) ?>" placeholder="Ex: 2024 / 2025">
                <span style="font-size:9px;color:#94a3b8;">Deixe em branco para usar o ano actual</span>
            </div>
            
            <!-- DIVISOR: Foto -->
            <div class="config-divider">📸 Configurações da Foto</div>
            
            <div class="config-group checkbox-group">
                <input type="checkbox" name="mostrar_foto" id="mostrar_foto" <?= $config['mostrar_foto'] == 'sim' ? 'checked' : '' ?>>
                <label for="mostrar_foto">📸 Mostrar Foto</label>
            </div>
            
            <div class="config-group">
                <label>📏 Largura da Foto (mm)</label>
                <input type="number" name="foto_largura" value="<?= $config['foto_largura'] ?>" min="12" max="40" step="0.5">
                <span class="range-labels"><span>Min: 12mm</span><span>Max: 40mm</span></span>
            </div>
            <div class="config-group">
                <label>📏 Altura da Foto (mm)</label>
                <input type="number" name="foto_altura" value="<?= $config['foto_altura'] ?>" min="15" max="45" step="0.5">
                <span class="range-labels"><span>Min: 15mm</span><span>Max: 45mm</span></span>
            </div>
            
            <!-- DIVISOR: QR Code na Frente -->
            <div class="config-divider">📱 QR Code na Frente</div>
            
            <div class="config-group checkbox-group">
                <input type="checkbox" name="mostrar_qr_code_frente" id="mostrar_qr_code_frente" <?= $config['mostrar_qr_code_frente'] == 'sim' ? 'checked' : '' ?>>
                <label for="mostrar_qr_code_frente">📱 Mostrar QR Code na Frente</label>
            </div>
            
            <div class="config-group">
                <label>📏 Tamanho do QR Code (mm)</label>
                <input type="number" name="qr_code_tamanho" value="<?= $config['qr_code_tamanho'] ?>" min="8" max="25" step="0.5">
                <span class="range-labels"><span>Min: 8mm</span><span>Max: 25mm</span></span>
            </div>
            
            <!-- DIVISOR: Assinatura do Diretor na Frente -->
            <div class="config-divider">👔 Assinatura do Diretor (Frente)</div>
            
            <div class="config-group checkbox-group">
                <input type="checkbox" name="mostrar_assinatura_frente" id="mostrar_assinatura_frente" <?= $config['mostrar_assinatura_frente'] == 'sim' ? 'checked' : '' ?>>
                <label for="mostrar_assinatura_frente">👔 Mostrar Assinatura na Frente</label>
            </div>
            
            <div class="config-group">
                <label>📝 Nome do Diretor</label>
                <input type="text" name="assinatura_frente_nome" value="<?= htmlspecialchars($config['assinatura_frente_nome'] ?? 'Dr. Carlos Manuel Reis') ?>">
            </div>
            
            <div class="config-group">
                <label>📏 Tamanho da Assinatura (em)</label>
                <input type="number" name="assinatura_frente_tamanho" value="<?= $config['assinatura_frente_tamanho'] ?>" min="0.2" max="0.8" step="0.05">
                <span class="range-labels"><span>Min: 0.2em</span><span>Max: 0.8em</span></span>
            </div>
            
            <!-- DIVISOR: Configurações do NOME -->
            <div class="config-divider">✏️ Configurações do NOME</div>
            
            <div class="config-group">
                <label>🎨 Cor do Nome</label>
                <input type="color" name="nome_cor" value="<?= $config['nome_cor'] ?>">
            </div>
            <div class="config-group">
                <label>📏 Tamanho do Nome (pt)</label>
                <input type="number" name="nome_tamanho" value="<?= $config['nome_tamanho'] ?>" min="8" max="24" step="0.5">
                <span class="range-labels"><span>Min: 8pt</span><span>Max: 24pt</span></span>
            </div>
            <div class="config-group">
                <label>💪 Peso do Nome</label>
                <select name="nome_peso">
                    <option value="300" <?= $config['nome_peso'] == '300' ? 'selected' : '' ?>>Light (300)</option>
                    <option value="400" <?= $config['nome_peso'] == '400' ? 'selected' : '' ?>>Normal (400)</option>
                    <option value="600" <?= $config['nome_peso'] == '600' ? 'selected' : '' ?>>Semi-bold (600)</option>
                    <option value="700" <?= $config['nome_peso'] == '700' ? 'selected' : '' ?>>Bold (700)</option>
                    <option value="800" <?= $config['nome_peso'] == '800' ? 'selected' : '' ?>>Extra-bold (800)</option>
                    <option value="900" <?= $config['nome_peso'] == '900' ? 'selected' : '' ?>>Black (900)</option>
                </select>
            </div>
            <div class="config-group checkbox-group">
                <input type="checkbox" name="nome_maiusculo" id="nome_maiusculo" <?= $config['nome_maiusculo'] == 'sim' ? 'checked' : '' ?>>
                <label for="nome_maiusculo">🔠 Nome em MAIÚSCULO</label>
            </div>
            
            <!-- DIVISOR: Configurações da CLASSE -->
            <div class="config-divider">📚 Configurações da CLASSE</div>
            
            <div class="config-group">
                <label>🎨 Cor da Classe</label>
                <input type="color" name="classe_cor" value="<?= $config['classe_cor'] ?>">
            </div>
            <div class="config-group">
                <label>📏 Tamanho da Classe (pt)</label>
                <input type="number" name="classe_tamanho" value="<?= $config['classe_tamanho'] ?>" min="6" max="18" step="0.5">
                <span class="range-labels"><span>Min: 6pt</span><span>Max: 18pt</span></span>
            </div>
            <div class="config-group">
                <label>💪 Peso da Classe</label>
                <select name="classe_peso">
                    <option value="300" <?= $config['classe_peso'] == '300' ? 'selected' : '' ?>>Light (300)</option>
                    <option value="400" <?= $config['classe_peso'] == '400' ? 'selected' : '' ?>>Normal (400)</option>
                    <option value="600" <?= $config['classe_peso'] == '600' ? 'selected' : '' ?>>Semi-bold (600)</option>
                    <option value="700" <?= $config['classe_peso'] == '700' ? 'selected' : '' ?>>Bold (700)</option>
                    <option value="800" <?= $config['classe_peso'] == '800' ? 'selected' : '' ?>>Extra-bold (800)</option>
                    <option value="900" <?= $config['classe_peso'] == '900' ? 'selected' : '' ?>>Black (900)</option>
                </select>
            </div>
            
            <!-- DIVISOR: Linha Ondulada -->
            <div class="config-divider">〰️ Linha Ondulada Decorada</div>
            
            <div class="config-group checkbox-group">
                <input type="checkbox" name="mostrar_linha_ondulada" id="mostrar_linha_ondulada" <?= $config['mostrar_linha_ondulada'] == 'sim' ? 'checked' : '' ?>>
                <label for="mostrar_linha_ondulada">🎨 Mostrar Linha Ondulada</label>
            </div>
            
            <div class="config-group">
                <label>🎨 Cor da Linha</label>
                <input type="color" name="linha_ondulada_cor" value="<?= $config['linha_ondulada_cor'] ?>">
            </div>
            
            <div class="config-group">
                <label>📏 Altura da Linha</label>
                <input type="number" name="linha_ondulada_altura" value="<?= $config['linha_ondulada_altura'] ?>" min="1" max="8" step="0.5">
                <span class="range-labels"><span>Min: 1</span><span>Max: 8</span></span>
            </div>
            
            <div class="config-group">
                <label>📍 Posição da Linha</label>
                <select name="linha_ondulada_posicao">
                    <option value="antes_nome" <?= $config['linha_ondulada_posicao'] == 'antes_nome' ? 'selected' : '' ?>>Antes do Nome</option>
                    <option value="depois_nome" <?= $config['linha_ondulada_posicao'] == 'depois_nome' ? 'selected' : '' ?>>Depois do Nome</option>
                    <option value="ambos" <?= $config['linha_ondulada_posicao'] == 'ambos' ? 'selected' : '' ?>>Antes e Depois</option>
                </select>
            </div>
            
            <div class="config-group">
                <label>⬇️ Deslocamento Vertical (mm)</label>
                <input type="number" name="linha_ondulada_offset_y" value="<?= $config['linha_ondulada_offset_y'] ?>" min="-5" max="5" step="0.5">
                <span class="range-labels"><span>Min: -5mm</span><span>Max: 5mm</span></span>
            </div>
            
            <div class="config-group">
                <label>🌀 Curvatura da Onda</label>
                <input type="number" name="linha_ondulada_curvatura" value="<?= $config['linha_ondulada_curvatura'] ?>" min="0.5" max="2" step="0.1">
                <span class="range-labels"><span>Min: 0.5</span><span>Max: 2</span></span>
            </div>
            
            <!-- DIVISOR: Verso - Tabela de Pagamentos -->
            <div class="config-divider">📊 Verso - Tabela de Pagamentos</div>
            
            <div class="config-group">
                <label>📏 Altura da Linha (mm)</label>
                <input type="number" name="tabela_altura_linha" value="<?= $config['tabela_altura_linha'] ?>" min="2" max="6" step="0.5">
                <span class="range-labels"><span>Min: 2mm</span><span>Max: 6mm</span></span>
            </div>
            
            <div class="config-group">
                <label>📐 Distribuição (Mês/Propina/Transporte)</label>
                <input type="number" name="tabela_distribuicao" value="<?= $config['tabela_distribuicao'] ?>" min="0.5" max="2.5" step="0.1">
                <span class="range-labels"><span>Min: 0.5</span><span>Max: 2.5</span></span>
            </div>
            <div style="font-size:9px;color:#94a3b8;grid-column:span 2;margin-top:-5px;">
                💡 Quanto maior o valor, mais larga será a coluna "Mês" em relação às outras
            </div>
            
            <!-- DIVISOR: Verso - Assinatura do Diretor -->
            <div class="config-divider">👔 Verso - Assinatura do Diretor</div>
            
            <div class="config-group checkbox-group">
                <input type="checkbox" name="mostrar_assinatura_verso" id="mostrar_assinatura_verso" <?= $config['mostrar_assinatura_verso'] == 'sim' ? 'checked' : '' ?>>
                <label for="mostrar_assinatura_verso">👔 Mostrar Assinatura no Verso</label>
            </div>
            
            <div class="config-group">
                <label>📝 Nome do Diretor</label>
                <input type="text" name="assinatura_verso_nome" value="<?= htmlspecialchars($config['assinatura_verso_nome'] ?? 'Dr. Carlos Manuel Reis') ?>">
            </div>
            
            <div class="config-group">
                <label>📏 Tamanho da Assinatura (em)</label>
                <input type="number" name="assinatura_verso_tamanho" value="<?= $config['assinatura_verso_tamanho'] ?>" min="0.3" max="1.0" step="0.05">
                <span class="range-labels"><span>Min: 0.3em</span><span>Max: 1.0em</span></span>
            </div>
            
            <div class="config-group">
                <label>📝 Cargo</label>
                <input type="text" name="assinatura_verso_cargo" value="<?= htmlspecialchars($config['assinatura_verso_cargo'] ?? 'DIRECTOR DA ESCOLA') ?>">
            </div>
            
            <!-- DIVISOR: Verso - Ajuste de Posição -->
            <div class="config-divider">📐 Verso - Ajuste de Posição (alinhamento com a frente)</div>
            
            <div class="config-group">
                <label>⬅️ Deslocamento Horizontal (mm)</label>
                <input type="number" name="verso_offset_x" value="<?= $config['verso_offset_x'] ?>" min="-10" max="10" step="0.5">
                <span class="range-labels"><span>Esquerda (-)</span><span>Direita (+)</span></span>
            </div>
            
            <div class="config-group">
                <label>⬆️ Deslocamento Vertical (mm)</label>
                <input type="number" name="verso_offset_y" value="<?= $config['verso_offset_y'] ?>" min="-10" max="10" step="0.5">
                <span class="range-labels"><span>Cima (-)</span><span>Baixo (+)</span></span>
            </div>
            
            <!-- DIVISOR: Cores Gerais -->
            <div class="config-divider">🎨 Cores Gerais</div>
            
            <div class="config-group">
                <label>🎨 Cor Principal</label>
                <input type="color" name="cor_primaria" value="<?= $config['cor_primaria'] ?>">
            </div>
            <div class="config-group">
                <label>🎨 Cor Secundária</label>
                <input type="color" name="cor_secundaria" value="<?= $config['cor_secundaria'] ?>">
            </div>
            <div class="config-group">
                <label>🎨 Cor Fundo</label>
                <input type="color" name="cor_fundo" value="<?= $config['cor_fundo'] ?>">
            </div>
            <div class="config-group">
                <label>🎨 Cor Texto</label>
                <input type="color" name="cor_texto" value="<?= $config['cor_texto'] ?>">
            </div>
            <div class="config-group">
                <label>🎨 Cor Cabeçalho</label>
                <input type="color" name="cor_cabecalho" value="<?= $config['cor_cabecalho'] ?>">
            </div>
            <div class="config-group">
                <label>🎨 Cor Rodapé</label>
                <input type="color" name="cor_rodape" value="<?= $config['cor_rodape'] ?>">
            </div>
            
            <!-- DIVISOR: Tamanhos -->
            <div class="config-divider">📐 Tamanhos do Cartão</div>
            
            <div class="config-group">
                <label>📏 Largura (mm)</label>
                <input type="number" name="tamanho_largura" value="<?= $config['tamanho_largura'] ?>" min="35" max="140" step="1">
                <span class="range-labels"><span>Min: 35mm</span><span>Max: 140mm</span></span>
            </div>
            <div class="config-group">
                <label>📏 Altura (mm)</label>
                <input type="number" name="tamanho_altura" value="<?= $config['tamanho_altura'] ?>" min="35" max="140" step="1">
                <span class="range-labels"><span>Min: 35mm</span><span>Max: 140mm</span></span>
            </div>
            <div class="config-group">
                <label>📝 Tamanho Fonte (pt)</label>
                <input type="number" name="tamanho_fonte" value="<?= $config['tamanho_fonte'] ?>" min="6" max="18" step="0.5">
                <span class="range-labels"><span>Min: 6pt</span><span>Max: 18pt</span></span>
            </div>
            <div class="config-group">
                <label>🔄 Borda Arredondada (mm)</label>
                <input type="number" name="borda_arredondada" value="<?= $config['borda_arredondada'] ?>" min="0" max="15" step="1">
                <span class="range-labels"><span>Min: 0mm</span><span>Max: 15mm</span></span>
            </div>
            
            <!-- DIVISOR: Opções -->
            <div class="config-divider">☑️ Opções</div>
            
            <div class="config-group checkbox-group">
                <input type="checkbox" name="fundo_decorado" id="fundo_decorado" <?= $config['fundo_decorado'] == 'sim' ? 'checked' : '' ?>>
                <label for="fundo_decorado">🎨 Fundo Decorado</label>
            </div>
            <div class="config-group checkbox-group">
                <input type="checkbox" name="mostrar_verso" id="mostrar_verso" <?= $config['mostrar_verso'] == 'sim' ? 'checked' : '' ?>>
                <label for="mostrar_verso">🔄 Mostrar Verso</label>
            </div>
            <div class="config-group checkbox-group">
                <input type="checkbox" name="mostrar_info_importante" id="mostrar_info_importante" <?= $config['mostrar_info_importante'] == 'sim' ? 'checked' : '' ?>>
                <label for="mostrar_info_importante">ℹ️ Mostrar Informações Importantes</label>
            </div>
        </div>
        
        <div class="config-actions">
            <button type="submit" name="salvar_config" class="btn btn-save">💾 Aplicar Configurações</button>
            <a href="?id=<?= $id ?>&reset_config=1" class="btn btn-reset">↺ Restaurar Padrão</a>
            <button type="button" class="btn btn-print" onclick="imprimirFrente()">🖨️ Imprimir Frente</button>
            <?php if ($config['mostrar_verso'] == 'sim'): ?>
                <button type="button" class="btn btn-print-verso" onclick="imprimirVerso()">🔄 Imprimir Verso</button>
            <?php endif; ?>
            <a href="view.php?id=<?= $id ?>" class="btn btn-reset">← Voltar</a>
        </div>
        
        <div class="config-info">
            💡 As configurações são salvas na sua sessão. Clique em "Aplicar Configurações" para ver as alterações.
        </div>
    </form>
</div>

<!-- ==========================================
     PAINEL DE GERAÇÃO EM MASSA
     ========================================== -->
<div class="massa-panel no-print">
    <h3>📚 Geração de Cartões em Massa</h3>
    <p style="font-size:13px;color:#4a5568;margin-bottom:10px;">
        💳 Apenas alunos com pagamento de <strong><?= number_format(VALOR_CARTAO, 2, ',', '.') ?> Kz</strong> serão gerados.
    </p>
    
    <form method="POST" id="massaForm">
        <div class="massa-grid">
            <div class="massa-group">
                <label for="tipo_geracao">Tipo de Geração</label>
                <select name="tipo_geracao" id="tipo_geracao" onchange="toggleMassaOptions()">
                    <option value="turma">Por Turma</option>
                    <option value="quantidade">Últimos N Alunos</option>
                    <option value="ids">Por IDs Específicos</option>
                </select>
            </div>
            
            <div class="massa-group" id="turma_group">
                <label for="turma_selecionada">Turma</label>
                <select name="turma_selecionada" id="turma_selecionada">
                    <option value="">Selecione uma turma</option>
                    <?php foreach ($turmas_disponiveis as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="massa-group" id="classe_group">
                <label for="classe_selecionada">Classe (opcional)</label>
                <select name="classe_selecionada" id="classe_selecionada">
                    <option value="">Todas as classes</option>
                    <?php foreach ($classes_disponiveis as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?>ª Classe</option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="massa-group" id="quantidade_group" style="display:none;">
                <label for="quantidade">Quantidade de Alunos</label>
                <input type="number" name="quantidade" id="quantidade" value="10" min="1" max="100">
                <span style="font-size:11px;color:#94a3b8;">Quantos dos últimos alunos cadastrados</span>
            </div>
            
            <div class="massa-group" id="ids_group" style="display:none;">
                <label for="ids_alunos">IDs dos Alunos</label>
                <input type="text" name="ids_alunos" id="ids_alunos" placeholder="Ex: 1,2,3,4,5">
                <span style="font-size:11px;color:#94a3b8;">Separados por vírgula</span>
            </div>
        </div>
        
        <div class="massa-actions">
            <button type="submit" name="gerar_massa" class="btn-massa">🎯 Gerar Cartões em Massa</button>
            <a href="<?= $_SERVER['PHP_SELF'] ?>?id=<?= $id ?>" class="btn-massa-secondary">↺ Limpar</a>
            <span style="font-size:12px;color:#94a3b8;margin-left:10px;">
                ⚠️ A geração em massa pode demorar dependendo da quantidade de alunos
            </span>
        </div>
        
        <?php if (!empty($erro_massa)): ?>
            <div class="massa-erro">❌ <?= htmlspecialchars($erro_massa) ?></div>
        <?php endif; ?>
        
        <?php if ($modo_massa && empty($erro_massa) && !empty($alunos_massa)): ?>
            <div class="massa-sucesso">
                ✅ <?= count($alunos_massa) ?> cartões gerados com sucesso!
                <span style="display:block;font-size:12px;margin-top:5px;">
                    Clique em "🖨️ Imprimir Frente" para imprimir a frente dos cartões (2 por linha).
                    <?php if ($config['mostrar_verso'] == 'sim'): ?>
                        Depois clique em "🔄 Imprimir Verso" para imprimir o verso em outra folha.
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>
        
        <?php if ($modo_massa && !empty($alunos_bloqueados)): ?>
            <div class="massa-alerta">
                <div class="titulo">⚠️ Alunos bloqueados (sem pagamento de <?= number_format(VALOR_CARTAO, 2, ',', '.') ?> Kz):</div>
                <ul>
                    <?php foreach(array_slice($alunos_bloqueados, 0, 10) as $aluno): ?>
                        <li>#<?= $aluno['id'] ?> - <?= htmlspecialchars($aluno['nome']) ?> (<?= htmlspecialchars($aluno['Classe'] ?? '') ?> - Turma <?= htmlspecialchars($aluno['TURMA'] ?? '') ?>)</li>
                    <?php endforeach; ?>
                    <?php if (count($alunos_bloqueados) > 10): ?>
                        <li><em>... e mais <?= count($alunos_bloqueados) - 10 ?> alunos</em></li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>
    </form>
</div>

<!-- ==========================================
     CARTÕES - FRENTE
     ========================================== -->
<div id="frente-cartoes" class="cartao-wrapper" style="display:block;">
    <?php if ($modo_massa && empty($erro_massa) && !empty($alunos_massa)): ?>
        <div class="contador-cartoes no-print" style="text-align:center;padding:10px;background:#f0f0f0;margin-bottom:10px;border-radius:5px;">
            📄 <?= count($alunos_massa) ?> cartões - FRENTE (2 por linha)
        </div>
        <?= gerarGradeCartoes($alunos_massa, $config, $empresa, $ano_letivo, $telefone2, 'frente') ?>
        
    <?php elseif (!$modo_massa && $aluno): ?>
        <div class="linha-cartoes" style="display:flex;justify-content:center;padding:20px;">
            <?= gerarFrenteCartao($aluno, $config, $empresa, $ano_letivo, $telefone2) ?>
        </div>
        
    <?php elseif ($modo_massa && !empty($erro_massa) && $aluno): ?>
        <div class="linha-cartoes" style="display:flex;justify-content:center;padding:20px;">
            <?= gerarFrenteCartao($aluno, $config, $empresa, $ano_letivo, $telefone2) ?>
        </div>
    <?php endif; ?>
</div>

<!-- ==========================================
     CARTÕES - VERSO
     ========================================== -->
<?php if ($config['mostrar_verso'] == 'sim'): ?>
<div id="verso-cartoes" style="display:none;">
    <div class="cartao-wrapper">
        <?php if ($modo_massa && empty($erro_massa) && !empty($alunos_massa)): ?>
            <div class="contador-cartoes no-print" style="text-align:center;padding:10px;background:#f0f0f0;margin-bottom:10px;border-radius:5px;">
                📄 <?= count($alunos_massa) ?> cartões - VERSO (2 por linha - espelhado)
            </div>
            <?= gerarGradeCartoes($alunos_massa, $config, $empresa, $ano_letivo, $telefone2, 'verso') ?>
            
        <?php elseif (!$modo_massa && $aluno): ?>
            <div class="linha-cartoes" style="display:flex;justify-content:center;padding:20px;">
                <?= gerarVersoCartao($aluno, $config, $empresa, $ano_letivo, $telefone2) ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
    function toggleConfig() {
        var grid = document.getElementById('configGrid');
        if (grid.style.display === 'none') {
            grid.style.display = 'grid';
        } else {
            grid.style.display = 'none';
        }
    }
    
    function toggleMassaOptions() {
        var tipo = document.getElementById('tipo_geracao').value;
        document.getElementById('turma_group').style.display = 'none';
        document.getElementById('classe_group').style.display = 'none';
        document.getElementById('quantidade_group').style.display = 'none';
        document.getElementById('ids_group').style.display = 'none';
        
        if (tipo === 'turma') {
            document.getElementById('turma_group').style.display = 'block';
            document.getElementById('classe_group').style.display = 'block';
        } else if (tipo === 'quantidade') {
            document.getElementById('quantidade_group').style.display = 'block';
        } else if (tipo === 'ids') {
            document.getElementById('ids_group').style.display = 'block';
        }
    }
    
    function imprimirFrente() {
        var frenteDiv = document.getElementById('frente-cartoes');
        var versoDiv = document.getElementById('verso-cartoes');
        var configPanels = document.querySelectorAll('.config-panel, .massa-panel, .contador-cartoes');
        
        if (versoDiv) versoDiv.style.display = 'none';
        configPanels.forEach(function(el) { el.style.display = 'none'; });
        if (frenteDiv) frenteDiv.style.display = 'block';
        
        setTimeout(function() {
            window.print();
            
            setTimeout(function() {
                if (versoDiv) versoDiv.style.display = 'none';
                configPanels.forEach(function(el) { el.style.display = ''; });
                if (frenteDiv) frenteDiv.style.display = 'block';
            }, 500);
        }, 100);
    }
    
    function imprimirVerso() {
        var versoDiv = document.getElementById('verso-cartoes');
        var frenteDiv = document.getElementById('frente-cartoes');
        var configPanels = document.querySelectorAll('.config-panel, .massa-panel, .contador-cartoes');
        
        if (!versoDiv) {
            alert('O verso não está disponível. Verifique a configuração "Mostrar Verso".');
            return;
        }
        
        if (frenteDiv) frenteDiv.style.display = 'none';
        configPanels.forEach(function(el) { el.style.display = 'none'; });
        versoDiv.style.display = 'block';
        
        void versoDiv.offsetHeight;
        
        setTimeout(function() {
            window.print();
            
            setTimeout(function() {
                if (frenteDiv) frenteDiv.style.display = 'block';
                configPanels.forEach(function(el) { el.style.display = ''; });
                versoDiv.style.display = 'none';
            }, 500);
        }, 300);
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        toggleMassaOptions();
        
        document.querySelectorAll('input[type="number"]').forEach(function(input) {
            input.addEventListener('change', function() {
                var min = parseInt(this.getAttribute('min'));
                var max = parseInt(this.getAttribute('max'));
                var value = parseInt(this.value);
                
                if (value < min) {
                    this.value = min;
                    alert('O valor mínimo é ' + min);
                } else if (value > max) {
                    this.value = max;
                    alert('O valor máximo é ' + max);
                }
            });
            
            input.addEventListener('blur', function() {
                if (this.value === '') {
                    this.value = this.getAttribute('min') || 1;
                }
            });
        });
        
        if (window.location.search.indexOf('config=salvo') !== -1) {
            var configPanel = document.querySelector('.config-panel');
            if (configPanel) {
                var msg = document.createElement('div');
                msg.style.cssText = 'background:#d1fae5;color:#065f46;padding:10px 15px;border-radius:8px;margin-top:10px;text-align:center;font-weight:600;';
                msg.textContent = '✅ Configurações aplicadas com sucesso!';
                configPanel.appendChild(msg);
                setTimeout(function() {
                    msg.style.opacity = '0';
                    msg.style.transition = 'opacity 0.5s';
                    setTimeout(function() { msg.remove(); }, 500);
                }, 3000);
            }
        }
        if (window.location.search.indexOf('logo=ok') !== -1) {
            var configPanel = document.querySelector('.config-panel');
            if (configPanel) {
                var msg = document.createElement('div');
                msg.style.cssText = 'background:#d1fae5;color:#065f46;padding:10px 15px;border-radius:8px;margin-top:10px;text-align:center;font-weight:600;';
                msg.textContent = '✅ Logo do cabeçalho enviado com sucesso!';
                configPanel.appendChild(msg);
                setTimeout(function() {
                    msg.style.opacity = '0';
                    msg.style.transition = 'opacity 0.5s';
                    setTimeout(function() { msg.remove(); }, 500);
                }, 3000);
            }
        }
        if (window.location.search.indexOf('logo_fundo=ok') !== -1) {
            var configPanel = document.querySelector('.config-panel');
            if (configPanel) {
                var msg = document.createElement('div');
                msg.style.cssText = 'background:#d1fae5;color:#065f46;padding:10px 15px;border-radius:8px;margin-top:10px;text-align:center;font-weight:600;';
                msg.textContent = '✅ Logo de fundo enviado com sucesso!';
                configPanel.appendChild(msg);
                setTimeout(function() {
                    msg.style.opacity = '0';
                    msg.style.transition = 'opacity 0.5s';
                    setTimeout(function() { msg.remove(); }, 500);
                }, 3000);
            }
        }
    });
</script>

</body>
</html>