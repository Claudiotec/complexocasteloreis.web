<?php
// ============================================
// modules/escola/alunos/ficha_pagamento.php - Ficha de Pagamento do Aluno
// ============================================

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ============================================
// CONFIGURAÇÕES DO CARTÃO (MESMAS DO gerar_cartao.php)
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

// ============================================
// BUSCAR DADOS DO ALUNO
// ============================================
$id = $_GET['id'] ?? 0;
$aluno = null;

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
    
    if (!$aluno) {
        $_SESSION['erro'] = 'Aluno não encontrado!';
        header('Location: cartoes_escolares.php');
        exit;
    }
    
} catch (Exception $e) {
    $_SESSION['erro'] = 'Erro ao buscar aluno: ' . $e->getMessage();
    header('Location: cartoes_escolares.php');
    exit;
}

// ============================================
// BUSCAR TODOS OS PAGAMENTOS DO ALUNO POR EMOLUMENTO
// ============================================
$pagamentos = [];
$total_pago = 0;
$total_pendente = 0;
$total_geral = 0;

// Lista de emolumentos para exibir como colunas
$emolumentos_lista = [
    'Cartão' => 'cartao',
    'Matrícula' => 'matricula',
    'Propina' => 'propina',
    'Transporte' => 'transporte',
    'Boletim' => 'boletim',
    'Folha de Prova' => 'folha_prova',
    'Certificado' => 'certificado',
    'Atestado' => 'atestado',
    'Declaração' => 'declaracao',
    'Outros' => 'outros'
];

try {
    $pdo = conectarBanco();
    
    // Buscar todos os pagamentos do aluno com detalhes do emolumento
    $stmt = $pdo->prepare("
        SELECT p.*, e.nome as emolumento_nome, e.descricao as emolumento_descricao, e.categoria
        FROM pagamentos p
        LEFT JOIN emolumentos e ON p.emolumento_id = e.id
        WHERE p.aluno_id = ?
        ORDER BY p.data_pagamento DESC, p.data_vencimento DESC
    ");
    $stmt->execute([$id]);
    $pagamentos = $stmt->fetchAll();
    
    foreach ($pagamentos as $p) {
        $total_geral += floatval($p['valor']);
        if ($p['status'] == 'confirmado' || $p['status'] == 'pago') {
            $total_pago += floatval($p['valor']);
        } else {
            $total_pendente += floatval($p['valor']);
        }
    }
    
    // Buscar emolumentos disponíveis
    $stmt = $pdo->query("SELECT id, nome, descricao, valor_padrao FROM emolumentos ORDER BY nome");
    $emolumentos = $stmt->fetchAll();
    
} catch (Exception $e) {
    $erro = 'Erro ao buscar pagamentos: ' . $e->getMessage();
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
$emailEmpresa = $empresa['email'] ?? 'www.casteloreisfilhos.ao';
$telefone2 = '911 222 333';

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
function gerarLinhaOnduladaFicha($cor, $altura, $offset_y = 0, $curvatura = 1) {
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
// FUNÇÃO PARA FORMATAR STATUS
// ============================================
function getStatusBadgeFicha($status) {
    $statusMap = [
        'confirmado' => ['class' => 'badge-success', 'label' => '✅ Confirmado'],
        'pago' => ['class' => 'badge-success', 'label' => '✅ Pago'],
        'pendente' => ['class' => 'badge-warning', 'label' => '⏳ Pendente'],
        'atrasado' => ['class' => 'badge-danger', 'label' => '❌ Atrasado'],
        'cancelado' => ['class' => 'badge-danger', 'label' => '❌ Cancelado']
    ];
    return $statusMap[$status] ?? ['class' => 'badge-secondary', 'label' => $status];
}

// ============================================
// GERAR FRENTE DA FICHA
// ============================================
function gerarFrenteFicha($aluno, $config, $empresa, $ano_letivo, $telefone2, $pagamentos, $total_geral, $total_pago, $total_pendente) {
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
    
    if (empty($logo_path) || !file_exists($_SERVER['DOCUMENT_ROOT'] . $logo_path)) {
        $logo_path = '';
    }
    
    $logo_fundo_html = '';
    if (!empty($logo_fundo_path) && file_exists($_SERVER['DOCUMENT_ROOT'] . $logo_fundo_path)) {
        $logo_fundo_html = '<div class="logo-fundo"><img src="' . $logo_fundo_path . '" alt="Logo Fundo"></div>';
    }
    
    $linha_ondulada_html = '';
    if ($mostrar_linha_ondulada == 'sim') {
        $linha_ondulada_html = '<div class="linha-ondulada-container" style="padding:0 2mm;margin:0.2mm 0;">' . 
            gerarLinhaOnduladaFicha($linha_ondulada_cor, $linha_ondulada_altura, $linha_ondulada_offset_y, $linha_ondulada_curvatura) . 
            '</div>';
    }
    
    $qr_code_html = '';
    if ($mostrar_qr_code_frente == 'sim') {
        $qr_dados = 'ID: ' . ($aluno['id'] ?? '') . ' | Nome: ' . ($aluno['nome'] ?? '') . ' | Classe: ' . ($aluno['Classe'] ?? '') . ' | Turma: ' . ($aluno['TURMA'] ?? '') . ' | Ano: ' . $ano_letivo;
        $qr_code_html = '<div class="qr-code-frente" style="width:' . $qr_code_tamanho . 'mm;height:' . $qr_code_tamanho . 'mm;flex-shrink:0;border:1px solid ' . $cor_secundaria . ';border-radius:1.5mm;overflow:hidden;padding:0.5mm;background:white;">' . 
            gerarQRCodeSVG($qr_dados, 150) . 
            '</div>';
    }
    
    $assinatura_html = '';
    if ($mostrar_assinatura_frente == 'sim') {
        $assinatura_html = '<div class="assinatura-frente" style="text-align:center;margin-top:0.2mm;border-top:1px solid ' . $cor_texto . ';padding-top:0.2mm;width:100%;">';
        $assinatura_html .= '<span style="font-size:' . $assinatura_frente_tamanho . 'em;color:' . $cor_texto . ';font-weight:600;letter-spacing:0.3px;">' . htmlspecialchars($assinatura_frente_nome) . '</span>';
        $assinatura_html .= '</div>';
    }
    
    // CALCULAR ALTURAS
    $altura_cabecalho = min(14, $tamanho_altura * 0.16);
    $altura_rodape = min(7, $tamanho_altura * 0.07);
    $altura_titulo = min(5, $tamanho_altura * 0.05);
    $altura_info = $mostrar_info_importante == 'sim' ? min(12, $tamanho_altura * 0.12) : 0;
    $altura_assinatura = ($mostrar_assinatura_frente == 'sim' && $mostrar_info_importante == 'nao') ? min(5, $tamanho_altura * 0.05) : 0;
    $altura_linha_ondulada = ($mostrar_linha_ondulada == 'sim') ? min(4, $tamanho_altura * 0.04) : 0;
    
    $altura_disponivel = $tamanho_altura - $altura_cabecalho - $altura_rodape - $altura_titulo - $altura_info - $altura_assinatura - $altura_linha_ondulada;
    $altura_dados = max(18, $altura_disponivel);
    
    $fonte_base = $tamanho_fonte;
    
    $tamanho_logo_texto = max($fonte_base * 0.85, $tamanho_altura * 0.055);
    $tamanho_logo_sub = max($fonte_base * 0.5, $tamanho_altura * 0.035);
    $tamanho_logo_img = min(8, $tamanho_altura * 0.075);
    
    // HTML FRENTE
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
    $html .= '<div class="titulo-cartao" style="text-align:center;font-size:' . ($fonte_base * 0.7) . 'pt;font-weight:700;color:' . $cor_texto . ';text-transform:uppercase;letter-spacing:1px;border-bottom:1.5px solid ' . $cor_secundaria . ';padding-bottom:0.4mm;margin-bottom:0.4mm;">FICHA DE PAGAMENTO</div>';
    
    if ($linha_ondulada_posicao == 'antes_nome' || $linha_ondulada_posicao == 'ambos') {
        $html .= $linha_ondulada_html;
    }
    
    // FOTO + DADOS
    $html .= '<div class="foto-dados" style="display:flex;gap:' . ($tamanho_largura * 0.015) . 'mm;align-items:stretch;flex:1;min-height:0;">';
    
    if ($mostrar_foto == 'sim') {
        $html .= '<div class="foto-container" style="width:' . $foto_largura . 'mm;height:' . $foto_altura . 'mm;border:1.5px solid ' . $cor_secundaria . ';background:#f5f0eb;border-radius:1.5mm;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;align-self:center;">';
        if ($tem_foto) {
            $html .= '<img src="' . $foto_url . '" alt="Foto do Aluno" style="width:100%;height:100%;object-fit:cover;">';
        } else {
            $html .= '<div class="sem-foto" style="font-size:' . ($fonte_base * 2) . 'pt;color:#b0a090;">👤</div>';
        }
        $html .= '</div>';
    }
    
    $html .= '<div class="dados-qr-container" style="flex:1;display:flex;flex-direction:row;gap:' . ($tamanho_largura * 0.01) . 'mm;min-height:0;align-items:stretch;">';
    $html .= '<div class="dados-container" style="flex:1;display:flex;flex-direction:column;min-height:0;overflow:hidden;">';
    
    $html .= '<div class="campo" style="margin-bottom:' . ($tamanho_altura * 0.003) . 'mm;padding-bottom:' . ($tamanho_altura * 0.002) . 'mm;border-bottom:0.5px dashed #e0d5c8;margin-top:1.5mm;">';
    $html .= '<span class="label" style="font-weight:600;color:#5a4a3a;font-size:' . ($fonte_base * 0.45) . 'pt;text-transform:uppercase;letter-spacing:0.3px;display:block;">NOME DO ESTUDANTE</span>';
    $html .= '<div class="valor destaque" style="font-weight:' . $nome_peso . ';color:' . $nome_cor . ';font-size:' . $nome_tamanho . 'pt;margin-top:0.1mm;line-height:1.1;word-break:break-word;overflow-wrap:break-word;">' . htmlspecialchars($nome_aluno) . '</div></div>';
    
    $html .= '<div class="campo-duplo" style="display:flex;gap:' . ($tamanho_largura * 0.015) . 'mm;margin-bottom:' . ($tamanho_altura * 0.002) . 'mm;">';
    $html .= '<div class="item" style="flex:1;min-width:0;"><span class="label" style="font-weight:600;color:#5a4a3a;font-size:' . ($fonte_base * 0.45) . 'pt;text-transform:uppercase;letter-spacing:0.3px;display:block;">Nº DE ESTUDANTE</span><div class="valor" style="font-weight:600;color:' . $cor_texto . ';font-size:' . ($fonte_base * 0.6) . 'pt;margin-top:0.1mm;word-break:break-word;">' . $num_estudante . '</div></div>';
    $html .= '<div class="item" style="flex:1;min-width:0;"><span class="label" style="font-weight:600;color:#5a4a3a;font-size:' . ($fonte_base * 0.45) . 'pt;text-transform:uppercase;letter-spacing:0.3px;display:block;">CLASSE / TURMA</span>';
    $html .= '<div class="valor" style="font-weight:' . $classe_peso . ';color:' . $classe_cor . ';font-size:' . $classe_tamanho . 'pt;margin-top:0.1mm;word-break:break-word;">' . htmlspecialchars($classe_turma) . '</div></div>';
    $html .= '</div>';
    
    $html .= '<div class="campo-duplo" style="display:flex;gap:' . ($tamanho_largura * 0.015) . 'mm;padding-top:' . ($tamanho_altura * 0.001) . 'mm;border-top:0.5px dashed #e0d5c8;flex:1;align-items:flex-start;">';
    $html .= '<div class="item" style="flex:1;min-width:0;"><span class="label" style="font-weight:600;color:#5a4a3a;font-size:' . ($fonte_base * 0.4) . 'pt;text-transform:uppercase;letter-spacing:0.3px;display:block;">TURNO</span><div class="valor" style="font-weight:600;color:' . $cor_texto . ';font-size:' . ($fonte_base * 0.55) . 'pt;margin-top:0.1mm;">' . $turno . '</div></div>';
    $html .= '<div class="item" style="flex:1;min-width:0;"><span class="label" style="font-weight:600;color:#5a4a3a;font-size:' . ($fonte_base * 0.4) . 'pt;text-transform:uppercase;letter-spacing:0.3px;display:block;">ANO LECTIVO</span><div class="valor" style="font-weight:600;color:' . $cor_texto . ';font-size:' . ($fonte_base * 0.55) . 'pt;margin-top:0.1mm;">' . $ano_letivo . '</div></div>';
    $html .= '</div>';
    
    $html .= '</div>';
    
    if ($mostrar_qr_code_frente == 'sim' && !empty($qr_code_html)) {
        $html .= '<div style="display:flex;align-items:center;justify-content:center;flex-shrink:0;padding-left:' . ($tamanho_largura * 0.005) . 'mm;">' . $qr_code_html . '</div>';
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    if ($linha_ondulada_posicao == 'depois_nome' || $linha_ondulada_posicao == 'ambos') {
        $html .= $linha_ondulada_html;
    }
    
    if ($mostrar_assinatura_frente == 'sim' && $mostrar_info_importante == 'nao') {
        $html .= $assinatura_html;
    }
    
    if ($mostrar_info_importante == 'sim') {
        $html .= '<div class="info-importante" style="margin-top:' . ($tamanho_altura * 0.002) . 'mm;padding:' . ($tamanho_altura * 0.006) . 'mm ' . ($tamanho_largura * 0.012) . 'mm;background:#f8f4ef;border-radius:1.5mm;border-left:1.5px solid ' . $cor_secundaria . ';flex-shrink:0;">';
        $html .= '<div class="titulo-info" style="font-size:' . ($fonte_base * 0.38) . 'pt;font-weight:700;color:' . $cor_texto . ';text-transform:uppercase;letter-spacing:0.5px;margin-bottom:0.1mm;">RESUMO FINANCEIRO</div>';
        $html .= '<table style="width:100%;font-size:' . ($fonte_base * 0.3) . 'pt;border-collapse:collapse;">';
        $html .= '<tr><td style="padding:0.1mm 0;font-weight:600;">Total Geral:</td><td style="padding:0.1mm 0;text-align:right;font-weight:700;">' . number_format($total_geral, 2, ',', '.') . ' Kz</td></tr>';
        $html .= '<tr><td style="padding:0.1mm 0;font-weight:600;color:#22c55e;">Total Pago:</td><td style="padding:0.1mm 0;text-align:right;font-weight:700;color:#22c55e;">' . number_format($total_pago, 2, ',', '.') . ' Kz</td></tr>';
        $html .= '<tr><td style="padding:0.1mm 0;font-weight:600;color:#f59e0b;">Total Pendente:</td><td style="padding:0.1mm 0;text-align:right;font-weight:700;color:#f59e0b;">' . number_format($total_pendente, 2, ',', '.') . ' Kz</td></tr>';
        $html .= '<tr><td style="padding:0.1mm 0;font-weight:600;">Pagamentos:</td><td style="padding:0.1mm 0;text-align:right;font-weight:700;">' . count($pagamentos) . '</td></tr>';
        $html .= '</table>';
        $html .= '</div>';
        
        if ($mostrar_assinatura_frente == 'sim') {
            $html .= $assinatura_html;
        }
    }
    
    $html .= '</div>';
    
    // FOOTER
    $html .= '<div class="cartao-footer" style="background:' . $cor_rodape . ';border-top:1.5px solid ' . $cor_secundaria . ';padding:' . ($tamanho_altura * 0.006) . 'mm ' . ($tamanho_largura * 0.025) . 'mm;display:flex;justify-content:space-between;align-items:center;font-size:' . ($fonte_base * 0.38) . 'pt;color:#5a4a3a;position:relative;z-index:1;flex-shrink:0;">';
    $html .= '<div class="contato" style="text-align:center;flex:1;min-width:0;"><strong style="color:' . $cor_texto . ';">' . htmlspecialchars($enderecoEmpresa) . '</strong><br>' . htmlspecialchars($cidadeEmpresa) . '</div>';
    $html .= '<div class="contato" style="text-align:center;flex:1;min-width:0;"><strong style="color:' . $cor_texto . ';">' . htmlspecialchars($telefoneEmpresa) . '</strong><br>' . htmlspecialchars($emailEmpresa) . ' | ' . $telefone2 . '</div>';
    $html .= '</div>';
    
    $html .= '</div>';
    
    return $html;
}

// ============================================
// GERAR VERSO DA FICHA (TABELA DE PAGAMENTOS)
// ============================================
function gerarVersoFicha($aluno, $config, $empresa, $ano_letivo, $telefone2, $pagamentos) {
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
    $fonte_base = floatval($tamanho_fonte);
    
    // CALCULAR ALTURAS
    $altura_cabecalho = min(12, $altura_cartao * 0.14);
    $altura_rodape = min(6, $altura_cartao * 0.06);
    $altura_titulo = min(4, $altura_cartao * 0.045);
    $altura_encarregado = min(7, $altura_cartao * 0.07);
    $altura_assinatura_espaco = $tem_assinatura ? min(7, $altura_cartao * 0.07) : 0;
    $padding_vertical = 2;
    
    $altura_disponivel = $altura_cartao - $altura_cabecalho - $altura_rodape - $altura_titulo - $altura_encarregado - $altura_assinatura_espaco - $padding_vertical;
    $altura_tabela = max(28, $altura_disponivel);
    
    // Calcular altura da linha baseada no número de pagamentos
    $num_pagamentos = max(count($pagamentos), 1);
    $altura_linha = min(5.5, max(2.5, $altura_tabela / ($num_pagamentos + 1)));
    
    // Definir colunas da tabela
    $colunas = ['#', 'Emolumento', 'Valor', 'Vencimento', 'Pagamento', 'Status'];
    $col_width = round(100 / count($colunas));
    
    $html = '<div class="cartao cartao-verso" style="width:' . $largura_cartao . 'mm;height:' . $altura_cartao . 'mm;background:' . $cor_fundo . ';border-radius:' . $borda_arredondada . 'mm;border:1px solid ' . $cor_secundaria . ';font-size:' . $fonte_base . 'pt;position:relative;overflow:hidden;transform:translate(' . $verso_offset_x . 'mm, ' . $verso_offset_y . 'mm);display:flex;flex-direction:column;box-sizing:border-box;page-break-inside:avoid;">';
    $html .= '<div class="fundo-decorado-verso" style="' . $fundo_style . ';position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:0;"></div>';
    
    // HEADER
    $html .= '<div class="verso-header" style="background:' . $cor_cabecalho . ';border-bottom:2px solid ' . $cor_secundaria . ';position:relative;z-index:1;flex-shrink:0;padding:2mm ' . ($largura_cartao * 0.04) . 'mm 1.5mm;text-align:center;">';
    $html .= '<div class="verso-titulo" style="font-size:' . ($fonte_base * 0.8) . 'pt;font-weight:900;color:' . $cor_secundaria . ';text-transform:uppercase;letter-spacing:2px;">HISTÓRICO DE PAGAMENTOS</div>';
    $html .= '</div>';
    
    // BODY
    $html .= '<div class="verso-body" style="padding:1.5mm ' . ($largura_cartao * 0.025) . 'mm 1.5mm;position:relative;z-index:1;flex:1;display:flex;flex-direction:column;min-height:0;">';
    
    $html .= '<div class="titulo-verso" style="text-align:center;font-size:' . ($fonte_base * 0.65) . 'pt;font-weight:700;color:' . $cor_texto . ';text-transform:uppercase;letter-spacing:1px;border-bottom:1.5px solid ' . $cor_secundaria . ';padding-bottom:0.3mm;margin-bottom:0.5mm;flex-shrink:0;">LISTA DE PAGAMENTOS</div>';
    
    // ENCARREGADO
    $html .= '<div class="info-encarregado" style="font-size:' . ($fonte_base * 0.55) . 'pt;padding:0.8mm 2mm;background:#f8f4ef;border-radius:1.5mm;margin-bottom:1mm;border-left:2px solid ' . $cor_secundaria . ';flex-shrink:0;">';
    $html .= '<strong style="color:' . $cor_texto . ';">Encarregado:</strong> ' . htmlspecialchars($aluno['Nome_do_Pai'] ?? 'Não informado') . ' &nbsp;|&nbsp; ';
    $html .= '<strong style="color:' . $cor_texto . ';">Contacto:</strong> ' . htmlspecialchars($aluno['Contacto4'] ?? 'Não informado') . '</div>';
    
    // TABELA DE PAGAMENTOS
    $html .= '<div style="flex:1;display:flex;flex-direction:column;min-height:0;overflow:hidden;">';
    $html .= '<table class="tabela-pagamentos" style="width:100%;border-collapse:collapse;font-size:' . ($fonte_base * 0.45) . 'pt;flex:1;">';
    $html .= '<thead>';
    $html .= '<tr>';
    foreach ($colunas as $col) {
        $html .= '<th style="background:' . $cor_secundaria . ';color:' . $cor_texto . ';padding:0.3mm 0.5mm;text-align:center;font-weight:700;border:0.5px solid ' . $cor_secundaria . ';font-size:' . ($fonte_base * 0.5) . 'pt;">' . $col . '</th>';
    }
    $html .= '</tr></thead>';
    $html .= '<tbody>';
    
    if (count($pagamentos) > 0) {
        $index = 1;
        foreach ($pagamentos as $p) {
            $status = getStatusBadgeFicha($p['status']);
            $html .= '<tr>';
            $html .= '<td style="border:0.5px solid #e0d5c8;padding:0.2mm 0.5mm;text-align:center;height:' . $altura_linha . 'mm;font-weight:600;font-size:' . ($fonte_base * 0.45) . 'pt;">' . $index++ . '</td>';
            $html .= '<td style="border:0.5px solid #e0d5c8;padding:0.2mm 0.5mm;text-align:center;height:' . $altura_linha . 'mm;font-size:' . ($fonte_base * 0.45) . 'pt;">' . htmlspecialchars($p['emolumento_nome'] ?? 'N/A') . '</td>';
            $html .= '<td style="border:0.5px solid #e0d5c8;padding:0.2mm 0.5mm;text-align:center;height:' . $altura_linha . 'mm;font-weight:700;font-size:' . ($fonte_base * 0.45) . 'pt;">' . number_format($p['valor'], 2, ',', '.') . '</td>';
            $html .= '<td style="border:0.5px solid #e0d5c8;padding:0.2mm 0.5mm;text-align:center;height:' . $altura_linha . 'mm;font-size:' . ($fonte_base * 0.4) . 'pt;">' . ($p['data_vencimento'] ? date('d/m/Y', strtotime($p['data_vencimento'])) : '-') . '</td>';
            $html .= '<td style="border:0.5px solid #e0d5c8;padding:0.2mm 0.5mm;text-align:center;height:' . $altura_linha . 'mm;font-size:' . ($fonte_base * 0.4) . 'pt;">' . ($p['data_pagamento'] ? date('d/m/Y', strtotime($p['data_pagamento'])) : '-') . '</td>';
            $html .= '<td style="border:0.5px solid #e0d5c8;padding:0.2mm 0.5mm;text-align:center;height:' . $altura_linha . 'mm;font-size:' . ($fonte_base * 0.4) . 'pt;"><span style="background:' . ($p['status'] == 'confirmado' || $p['status'] == 'pago' ? '#d1fae5' : '#fef3c7') . ';padding:0.5mm 3mm;border-radius:3px;font-weight:600;">' . $status['label'] . '</span></td>';
            $html .= '</tr>';
        }
    } else {
        $html .= '<tr><td colspan="6" style="border:0.5px solid #e0d5c8;padding:0.5mm;text-align:center;color:#94a3b8;height:' . $altura_linha . 'mm;font-size:' . ($fonte_base * 0.4) . 'pt;">Nenhum pagamento registrado</td></tr>';
    }
    
    $html .= '</tbody></table>';
    $html .= '</div>';
    
    // ASSINATURA
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
// GERAR GRADE DE FICHAS - 2 POR LINHA
// ============================================
function gerarGradeFichas($alunos, $config, $empresa, $ano_letivo, $telefone2, $pagamentos_list, $totais, $tipo = 'frente') {
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
                        $html .= gerarVersoFicha($alunos[$idx], $config, $empresa, $ano_letivo, $telefone2, $pagamentos_list[$idx] ?? []);
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
                        $html .= gerarFrenteFicha($alunos[$idx], $config, $empresa, $ano_letivo, $telefone2, $pagamentos_list[$idx] ?? [], $totais[$idx]['total'] ?? 0, $totais[$idx]['pago'] ?? 0, $totais[$idx]['pendente'] ?? 0);
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

// ============================================
// PREPARAR DADOS PARA EXIBIÇÃO
// ============================================
$alunos_single = [$aluno];
$pagamentos_list = [$pagamentos];
$totais = [
    [
        'total' => $total_geral,
        'pago' => $total_pago,
        'pendente' => $total_pendente
    ]
];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha de Pagamento - <?= htmlspecialchars($aluno['nome'] ?? 'Aluno') ?></title>
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
        
        .config-panel {
            background: #ffffff;
            border-radius: 12px;
            padding: 20px 25px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            max-width: 1200px;
            width: 100%;
            margin: 0 auto 20px auto;
        }
        
        .config-panel h3 {
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
        
        .config-actions {
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
        
        .info-importante table {
            font-size: 0.4em;
        }
        
        .info-importante table td {
            padding: 0.1mm 0;
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
            
            .config-panel {
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
        ⚙️ Personalizar Ficha de Pagamento
        <button class="toggle-btn" onclick="toggleConfig()">Mostrar/Ocultar</button>
    </h3>
    
    <div class="config-actions">
        <button type="button" class="btn btn-print" onclick="imprimirFrente()">🖨️ Imprimir Frente</button>
        <?php if ($config['mostrar_verso'] == 'sim'): ?>
            <button type="button" class="btn btn-print-verso" onclick="imprimirVerso()">🔄 Imprimir Verso</button>
        <?php endif; ?>
        <a href="cartoes_escolares.php" class="btn btn-reset">← Voltar</a>
    </div>
    
    <div class="config-info">
        💡 A ficha de pagamento exibe todos os emolumentos pagos pelo aluno.
    </div>
</div>

<!-- ==========================================
     FICHAS - FRENTE
     ========================================== -->
<div id="frente-cartoes" class="cartao-wrapper" style="display:block;">
    <div class="linha-cartoes" style="display:flex;justify-content:center;padding:20px;">
        <?= gerarFrenteFicha($aluno, $config, $empresa, $ano_letivo, $telefone2, $pagamentos, $total_geral, $total_pago, $total_pendente) ?>
    </div>
</div>

<!-- ==========================================
     FICHAS - VERSO
     ========================================== -->
<?php if ($config['mostrar_verso'] == 'sim'): ?>
<div id="verso-cartoes" style="display:none;">
    <div class="cartao-wrapper">
        <div class="linha-cartoes" style="display:flex;justify-content:center;padding:20px;">
            <?= gerarVersoFicha($aluno, $config, $empresa, $ano_letivo, $telefone2, $pagamentos) ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    function toggleConfig() {
        var grid = document.querySelector('.config-panel');
        if (grid.style.display === 'none') {
            grid.style.display = 'block';
        } else {
            grid.style.display = 'none';
        }
    }
    
    function imprimirFrente() {
        var frenteDiv = document.getElementById('frente-cartoes');
        var versoDiv = document.getElementById('verso-cartoes');
        var configPanels = document.querySelectorAll('.config-panel');
        
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
        var configPanels = document.querySelectorAll('.config-panel');
        
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
    });
</script>

</body>
</html>