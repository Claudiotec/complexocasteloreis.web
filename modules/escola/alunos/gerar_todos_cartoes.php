<?php
// ============================================
// modules/escola/alunos/gerar_todos_cartoes.php - Gerar Todos os Cartões
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
// BUSCAR TODOS OS ALUNOS
// ============================================
$alunos = [];
try {
    $pdo = conectarBanco();
    $alunos = $pdo->query("
        SELECT id, nome, Sexo, Idade, dia, mes, Ano, 
               Classe, Curso, TURMA, SALA, Periodo, Situacao_Cadastro,
               Nome_do_Pai, Contacto4, foto
        FROM alunos 
        WHERE Situacao_Cadastro = 'Matrícula' OR Situacao_Cadastro = 'Confirmação'
        ORDER BY Classe, TURMA, nome
    ")->fetchAll();
} catch (Exception $e) {}

if (empty($alunos)) {
    $_SESSION['erro'] = 'Nenhum aluno encontrado para gerar cartões!';
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
    $html = '<div class="cartao-frente" style="width:' . $tamanho_largura . 'mm;height:' . $tamanho_altura . 'mm;background:' . $cor_fundo . ';border-radius:' . $borda_arredondada . 'mm;border:1px solid ' . $cor_secundaria . ';font-size:' . $fonte_base . 'pt;position:relative;overflow:hidden;display:flex;flex-direction:column;box-sizing:border-box;">';
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
    $html = '<div class="cartao-verso" style="width:' . $largura_cartao . 'mm;height:' . $altura_cartao . 'mm;background:' . $cor_fundo . ';border-radius:' . $borda_arredondada . 'mm;border:1px solid ' . $cor_secundaria . ';font-size:' . $fonte_base . 'pt;position:relative;overflow:hidden;transform:translate(' . $verso_offset_x . 'mm, ' . $verso_offset_y . 'mm);display:flex;flex-direction:column;box-sizing:border-box;page-break-inside:avoid;">';
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
    $html .= '<tr><th colspan="3" style="background:' . $cor_secundaria . ';color:' . $cor_texto . ';padding:0.3mm 0.5mm;text-align:center;font-weight:700;border:0.5px solid ' . $cor_secundaria . ';font-size:' . ($fonte_base * 0.6) . 'pt;">1º TRIMESTRE</th></tr>';
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
    $html .= '<tr><th colspan="3" style="background:' . $cor_secundaria . ';color:' . $cor_texto . ';padding:0.3mm 0.5mm;text-align:center;font-weight:700;border:0.5px solid ' . $cor_secundaria . ';font-size:' . ($fonte_base * 0.6) . 'pt;">2º TRIMESTRE</th></tr>';
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
// FUNÇÃO PARA GERAR GRADE DE CARTÕES - 2 POR LINHA, 3 LINHAS (RETRATO)
// ============================================
function gerarGradeCartoes($alunos, $config, $empresa, $ano_letivo, $telefone2, $tipo = 'frente') {
    $html = '';
    $total = count($alunos);
    
    $largura_cartao = floatval($config['tamanho_largura'] ?? 85);
    $altura_cartao = floatval($config['tamanho_altura'] ?? 120);
    
    $largura_a4 = 210; // retrato
    $altura_a4 = 297;
    
    $margem = 4;
    $espacamento = 5;
    $por_linha = 2;
    $linhas_por_pagina = 3;
    
    $cartoes_por_pagina = $por_linha * $linhas_por_pagina;
    $total_paginas = max(1, ceil($total / $cartoes_por_pagina));
    
    for ($pagina = 0; $pagina < $total_paginas; $pagina++) {
        $html .= '<div class="pagina">';
        
        $inicio = $pagina * $cartoes_por_pagina;
        $fim = min($inicio + $cartoes_por_pagina, $total);
        
        for ($linha = 0; $linha < $linhas_por_pagina; $linha++) {
            for ($coluna = 0; $coluna < $por_linha; $coluna++) {
                $idx = $inicio + ($linha * $por_linha) + $coluna;
                
                if ($idx < $fim && $idx < $total) {
                    $html .= '<div class="cartao-container">';
                    $html .= '<div class="cartao">';
                    if ($tipo === 'verso') {
                        $html .= gerarVersoCartao($alunos[$idx], $config, $empresa, $ano_letivo, $telefone2);
                    } else {
                        $html .= gerarFrenteCartao($alunos[$idx], $config, $empresa, $ano_letivo, $telefone2);
                    }
                    $html .= '</div></div>';
                } else {
                    $html .= '<div class="cartao-container vazio">';
                    $html .= '<div class="cartao"><div class="cartao-frente"><div class="vazio-texto">VAGO</div></div></div>';
                    $html .= '</div>';
                }
            }
        }
        
        $html .= '</div>';
    }
    
    return $html;
}

// ============================================
// AGRUPAR POR CLASSE
// ============================================
$classes = [];
foreach ($alunos as $aluno) {
    $classe = $aluno['Classe'] ?? 'Sem Classe';
    if (!isset($classes[$classe])) {
        $classes[$classe] = [];
    }
    $classes[$classe][] = $aluno;
}

$total_alunos = count($alunos);
$total_classes = count($classes);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Todos os Cartões - <?= htmlspecialchars($nomeEmpresa) ?></title>
    <style>
        @page {
            size: A4 portrait;
            margin: 4mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            background: #e8e8e8;
            padding: 0;
            margin: 0;
        }
        
        .no-print {
            text-align: center;
            padding: 15px;
            max-width: 210mm;
            margin: 0 auto 15px;
            background: #f8fafc;
            border-radius: 8px;
        }
        
        .no-print .btn-print {
            padding: 10px 30px;
            background: #d4a843;
            color: #1a2a3a;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .no-print .btn-print:hover {
            background: #c9a84c;
        }
        
        .no-print a {
            display: inline-block;
            margin-left: 10px;
            color: #4a5568;
            text-decoration: none;
        }
        
        .total-info {
            text-align: center;
            padding: 5px;
            font-size: 12px;
            color: #94a3b8;
        }
        
        .pagina {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            grid-template-rows: auto repeat(3, 1fr);
            gap: 4mm;
            padding: 4mm;
            width: 100%;
            max-width: 210mm;
            min-height: 297mm;
            height: 297mm;
            margin: 0 auto 5mm;
            background: white;
            border-radius: 2mm;
            box-shadow: 0 1mm 3mm rgba(0,0,0,0.1);
            page-break-after: always;
            box-sizing: border-box;
            overflow: hidden;
        }
        
        .pagina:last-child {
            page-break-after: avoid;
        }
        
        .pagina .classe-titulo {
            grid-column: 1 / -1;
            background: <?= $config['cor_secundaria'] ?>;
            color: <?= $config['cor_texto'] ?>;
            padding: 2mm;
            text-align: center;
            border-radius: 2mm;
            font-size: 11pt;
            font-weight: 700;
            margin-bottom: 1mm;
            flex-shrink: 0;
        }
        
        .cartao-container {
            width: 100%;
            height: 100%;
            max-height: <?= floatval($config['tamanho_altura']) + 2 ?>mm;
            display: flex;
            align-items: center;
            justify-content: center;
            perspective: 1000px;
        }
        
        .cartao {
            width: <?= $config['tamanho_largura'] ?>mm;
            height: <?= $config['tamanho_altura'] ?>mm;
            position: relative;
            transform-style: preserve-3d;
            transition: all 0.8s ease;
            border-radius: <?= $config['borda_arredondada'] ?>mm;
            flex-shrink: 0;
        }
        
        .cartao:hover {
            transform: rotateY(180deg);
        }
        
        .cartao-frente, .cartao-verso {
            position: absolute;
            width: 100%;
            height: 100%;
            backface-visibility: hidden;
            border-radius: <?= $config['borda_arredondada'] ?>mm;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
        }
        
        .cartao-frente {
            background: <?= $config['cor_fundo'] ?>;
            color: <?= $config['cor_texto'] ?>;
            border: 1px solid <?= $config['cor_secundaria'] ?>;
            z-index: 2;
        }
        
        .cartao-verso {
            background: <?= $config['cor_fundo'] ?>;
            color: <?= $config['cor_texto'] ?>;
            transform: rotateY(180deg);
            border: 1px solid <?= $config['cor_secundaria'] ?>;
        }
        
        .cartao-container.vazio {
            opacity: 0.3;
        }
        
        .cartao-container.vazio .cartao-frente {
            background: #f8fafc;
            border: 1px dashed #d1d5db;
        }
        
        .cartao-container.vazio .cartao-frente .vazio-texto {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #94a3b8;
            font-size: 10pt;
        }
        
        /* Estilos da frente */
        .cartao-frente .fundo-decorado {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }
        
        .cartao-frente .logo-fundo {
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
        
        .cartao-frente .logo-fundo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .cartao-frente .cartao-header {
            background: <?= $config['cor_cabecalho'] ?>;
            padding: 2.5mm 4mm 2mm;
            text-align: center;
            border-bottom: 3px solid <?= $config['cor_secundaria'] ?>;
            position: relative;
            z-index: 1;
        }
        
        .cartao-frente .cartao-header .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 2mm;
        }
        
        .cartao-frente .cartao-header .logo-img {
            width: 7mm;
            height: 7mm;
            border-radius: 50%;
            overflow: hidden;
            background: #fff;
            border: 1px solid <?= $config['cor_secundaria'] ?>;
            flex-shrink: 0;
        }
        
        .cartao-frente .cartao-header .logo-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .cartao-frente .cartao-header .logo-text {
            font-size: 0.8em;
            font-weight: 800;
            color: <?= $config['cor_secundaria'] ?>;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            line-height: 1.1;
        }
        
        .cartao-frente .cartao-header .logo-sub {
            font-size: 0.45em;
            color: #f0e6d3;
            font-weight: 400;
            letter-spacing: 1px;
            margin-top: 0.3mm;
            text-transform: uppercase;
        }
        
        .cartao-frente .cartao-body {
            padding: 2mm 3mm 1.5mm;
            position: relative;
            z-index: 1;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        
        .cartao-frente .cartao-body .titulo-cartao {
            text-align: center;
            font-size: 0.85em;
            font-weight: 700;
            color: <?= $config['cor_texto'] ?>;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 2px solid <?= $config['cor_secundaria'] ?>;
            padding-bottom: 1mm;
            margin-bottom: 1mm;
        }
        
        .cartao-frente .foto-container {
            border: 2px solid <?= $config['cor_secundaria'] ?>;
            border-radius: 2mm;
            overflow: hidden;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .cartao-frente .foto-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .cartao-frente .foto-container .sem-foto {
            color: #b0a090;
        }
        
        .cartao-frente .foto-dados {
            display: flex;
            gap: 1.5mm;
            align-items: stretch;
            flex: 1;
            min-height: 0;
        }
        
        .cartao-frente .dados-qr-container {
            flex: 1;
            display: flex;
            flex-direction: row;
            gap: 1mm;
            min-height: 0;
            align-items: stretch;
        }
        
        .cartao-frente .dados-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
            overflow: hidden;
        }
        
        .cartao-frente .dados-container .campo {
            margin-bottom: 0.3mm;
            padding-bottom: 0.2mm;
            border-bottom: 0.5px dashed #e0d5c8;
        }
        
        .cartao-frente .dados-container .campo:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .cartao-frente .dados-container .label {
            font-weight: 600;
            color: #5a4a3a;
            font-size: 0.45em;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: block;
        }
        
        .cartao-frente .dados-container .valor {
            font-weight: 600;
            color: <?= $config['cor_texto'] ?>;
            font-size: 0.65em;
            margin-top: 0.2mm;
        }
        
        .cartao-frente .dados-container .valor.destaque {
            color: <?= $config['nome_cor'] ?>;
        }
        
        .cartao-frente .campo-duplo {
            display: flex;
            gap: 2mm;
        }
        
        .cartao-frente .campo-duplo .item {
            flex: 1;
        }
        
        .cartao-frente .campo-duplo .item .label {
            font-weight: 600;
            color: #5a4a3a;
            font-size: 0.45em;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: block;
        }
        
        .cartao-frente .campo-duplo .item .valor {
            font-weight: 600;
            color: <?= $config['cor_texto'] ?>;
            font-size: 0.65em;
            margin-top: 0.2mm;
        }
        
        .cartao-frente .qr-code-frente {
            flex-shrink: 0;
            border: 1px solid <?= $config['cor_secundaria'] ?>;
            border-radius: 2mm;
            overflow: hidden;
            padding: 1mm;
            background: white;
        }
        
        .cartao-frente .qr-code-frente img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .cartao-frente .linha-ondulada-container {
            padding: 0 3mm;
            margin: 0.5mm 0;
        }
        
        .cartao-frente .assinatura-frente {
            text-align: center;
            margin-top: 0.5mm;
            border-top: 1px solid <?= $config['cor_texto'] ?>;
            padding-top: 0.5mm;
            width: 100%;
        }
        
        .cartao-frente .info-importante {
            margin-top: 1mm;
            padding: 1mm 2mm;
            background: #f8f4ef;
            border-radius: 2mm;
            border-left: 2px solid <?= $config['cor_secundaria'] ?>;
        }
        
        .cartao-frente .info-importante .titulo-info {
            font-size: 0.45em;
            font-weight: 700;
            color: <?= $config['cor_texto'] ?>;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.3mm;
        }
        
        .cartao-frente .info-importante .texto-info {
            font-size: 0.4em;
            color: #4a3a2a;
            line-height: 1.2;
            list-style: none;
            padding-left: 0;
            margin: 0;
        }
        
        .cartao-frente .info-importante .texto-info li {
            padding: 0.1mm 0;
            padding-left: 2mm;
            position: relative;
        }
        
        .cartao-frente .info-importante .texto-info li::before {
            content: "•";
            color: <?= $config['cor_secundaria'] ?>;
            font-weight: 700;
            position: absolute;
            left: 0;
        }
        
        .cartao-frente .cartao-footer {
            background: <?= $config['cor_rodape'] ?>;
            border-top: 2px solid <?= $config['cor_secundaria'] ?>;
            padding: 1mm 3mm;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.45em;
            color: #5a4a3a;
            position: relative;
            z-index: 1;
        }
        
        .cartao-frente .cartao-footer .contato {
            text-align: center;
            flex: 1;
        }
        
        .cartao-frente .cartao-footer .contato strong {
            color: <?= $config['cor_texto'] ?>;
        }
        
        /* Estilos do verso */
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
            background: <?= $config['cor_cabecalho'] ?>;
            padding: 3mm 4mm 2mm;
            text-align: center;
            border-bottom: 3px solid <?= $config['cor_secundaria'] ?>;
            position: relative;
            z-index: 1;
            flex-shrink: 0;
        }
        
        .cartao-verso .verso-header .verso-titulo {
            font-size: 0.9em;
            font-weight: 700;
            color: <?= $config['cor_secundaria'] ?>;
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
            color: <?= $config['cor_texto'] ?>;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 2px solid <?= $config['cor_secundaria'] ?>;
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
            border-left: 2px solid <?= $config['cor_secundaria'] ?>;
            flex-shrink: 0;
        }
        
        .cartao-verso .info-encarregado strong {
            color: <?= $config['cor_texto'] ?>;
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
            background: <?= $config['cor_secundaria'] ?>;
            color: <?= $config['cor_texto'] ?>;
            padding: 0.5mm 0.5mm;
            text-align: center;
            font-weight: 700;
            border: 0.5px solid <?= $config['cor_secundaria'] ?>;
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
            border-top: 1.5px solid <?= $config['cor_texto'] ?>;
            width: 80%;
            margin: 0 auto 0.3mm auto;
        }
        
        .cartao-verso .assinatura-texto {
            text-align: center;
            color: <?= $config['cor_texto'] ?>;
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
            background: <?= $config['cor_rodape'] ?>;
            border-top: 2px solid <?= $config['cor_secundaria'] ?>;
            padding: 1.5mm 3mm;
            text-align: center;
            font-size: 0.5em;
            color: #5a4a3a;
            position: relative;
            z-index: 1;
            flex-shrink: 0;
        }
        
        .cartao-verso .verso-footer strong {
            color: <?= $config['cor_texto'] ?>;
        }
        
        /* ============================================
           IMPRESSÃO - FRENTE E VERSO SEPARADOS
           ============================================ */
        @media print {
            html, body {
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 210mm !important;
                height: 297mm !important;
            }
            
            .no-print {
                display: none !important;
            }
            
            .pagina {
                page-break-after: always !important;
                width: 210mm !important;
                height: 297mm !important;
                min-height: 297mm !important;
                max-height: 297mm !important;
                padding: 4mm !important;
                margin: 0 !important;
                background: white !important;
                display: grid !important;
                grid-template-columns: repeat(2, 1fr) !important;
                grid-template-rows: auto repeat(3, 1fr) !important;
                gap: 3mm !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }
            
            .pagina:last-child {
                page-break-after: avoid !important;
            }
            
            .pagina .classe-titulo {
                font-size: 10pt !important;
                padding: 1.5mm !important;
                margin-bottom: 0.5mm !important;
            }
            
            .cartao-container {
                max-height: <?= floatval($config['tamanho_altura']) + 2 ?>mm !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            
            .cartao {
                width: <?= $config['tamanho_largura'] ?>mm !important;
                height: <?= $config['tamanho_altura'] ?>mm !important;
                box-shadow: none !important;
                border: 1px solid #ccc !important;
                border-radius: <?= $config['borda_arredondada'] ?>mm !important;
            }
            
            .cartao:hover {
                transform: none !important;
            }
            
            .cartao-frente, .cartao-verso {
                border-radius: <?= $config['borda_arredondada'] ?>mm !important;
                border: none !important;
            }
            
            .cartao-container.vazio {
                opacity: 0.2 !important;
            }
            
            .config-panel, .massa-panel, .contador-cartoes, .total-info {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="imprimirFrente()">🖨️ Imprimir Frente</button>
    <button class="btn-print" onclick="imprimirVerso()" style="margin-left:10px;">🔄 Imprimir Verso</button>
    <a href="cartoes_escolares.php" style="margin-left:10px;">← Voltar</a>
    <p style="margin-top: 5px; color: #94a3b8; font-size: 12px;">
        Total: <strong><?= $total_alunos ?></strong> alunos | 
        <strong><?= $total_classes ?></strong> classes | 
        <?= ceil($total_alunos / 6) ?> página(s) por lado
    </p>
</div>

<!-- ==========================================
     FRENTE DOS CARTÕES
     ========================================== -->
<div id="frente-cartoes">
<?php 
$classes_keys = array_keys($classes);

foreach($classes_keys as $classe_nome):
    $alunos_classe = $classes[$classe_nome];
    $total_classe = count($alunos_classe);
    $paginas_classe = ceil($total_classe / 6);
    
    for($pagina = 0; $pagina < $paginas_classe; $pagina++):
?>
<div class="pagina">
    <div class="classe-titulo">
        <?= htmlspecialchars($classe_nome) ?> - FRENTE - Total: <?= $total_classe ?> alunos
    </div>
    
    <?php 
    $inicio = $pagina * 6;
    $fim = min($inicio + 6, $total_classe);
    
    for($i = $inicio; $i < $fim; $i++): 
        $aluno = $alunos_classe[$i];
    ?>
    <div class="cartao-container">
        <div class="cartao">
            <?= gerarFrenteCartao($aluno, $config, $empresa, $ano_letivo, $telefone2) ?>
        </div>
    </div>
    <?php endfor; ?>
    
    <!-- Preencher espaços vazios na última página -->
    <?php for($i = $fim; $i < $inicio + 6 && $pagina == $paginas_classe - 1; $i++): ?>
    <div class="cartao-container vazio">
        <div class="cartao">
            <div class="cartao-frente">
                <div class="vazio-texto">VAGO</div>
            </div>
        </div>
    </div>
    <?php endfor; ?>
</div>
<?php 
    endfor; 
endforeach; 
?>
</div>

<!-- ==========================================
     VERSO DOS CARTÕES
     ========================================== -->
<div id="verso-cartoes" style="display:none;">
<?php 
foreach($classes_keys as $classe_nome):
    $alunos_classe = $classes[$classe_nome];
    $total_classe = count($alunos_classe);
    $paginas_classe = ceil($total_classe / 6);
    
    for($pagina = 0; $pagina < $paginas_classe; $pagina++):
?>
<div class="pagina">
    <div class="classe-titulo">
        <?= htmlspecialchars($classe_nome) ?> - VERSO - Total: <?= $total_classe ?> alunos
    </div>
    
    <?php 
    $inicio = $pagina * 6;
    $fim = min($inicio + 6, $total_classe);
    
    for($i = $inicio; $i < $fim; $i++): 
        $aluno = $alunos_classe[$i];
    ?>
    <div class="cartao-container">
        <div class="cartao">
            <?= gerarVersoCartao($aluno, $config, $empresa, $ano_letivo, $telefone2) ?>
        </div>
    </div>
    <?php endfor; ?>
    
    <!-- Preencher espaços vazios na última página -->
    <?php for($i = $fim; $i < $inicio + 6 && $pagina == $paginas_classe - 1; $i++): ?>
    <div class="cartao-container vazio">
        <div class="cartao">
            <div class="cartao-frente">
                <div class="vazio-texto">VAGO</div>
            </div>
        </div>
    </div>
    <?php endfor; ?>
</div>
<?php 
    endfor; 
endforeach; 
?>
</div>

<div class="total-info">
    <p>Total de cartões gerados: <strong><?= $total_alunos ?></strong> | 
    <?= $total_classes ?> classes | 
    <?= ceil($total_alunos / 6) ?> páginas por lado</p>
    <p style="font-size: 11px; color: #6b7280;">💡 Clique em "Imprimir Frente" ou "Imprimir Verso" para imprimir separadamente.</p>
</div>

<script>
    function imprimirFrente() {
        var frenteDiv = document.getElementById('frente-cartoes');
        var versoDiv = document.getElementById('verso-cartoes');
        var noPrint = document.querySelectorAll('.no-print, .total-info');
        
        if (versoDiv) versoDiv.style.display = 'none';
        if (frenteDiv) frenteDiv.style.display = 'block';
        noPrint.forEach(function(el) { el.style.display = 'none'; });
        
        setTimeout(function() {
            window.print();
            
            setTimeout(function() {
                noPrint.forEach(function(el) { el.style.display = ''; });
                if (versoDiv) versoDiv.style.display = 'none';
                if (frenteDiv) frenteDiv.style.display = 'block';
            }, 500);
        }, 300);
    }
    
    function imprimirVerso() {
        var frenteDiv = document.getElementById('frente-cartoes');
        var versoDiv = document.getElementById('verso-cartoes');
        var noPrint = document.querySelectorAll('.no-print, .total-info');
        
        if (!versoDiv) {
            alert('O verso não está disponível.');
            return;
        }
        
        if (frenteDiv) frenteDiv.style.display = 'none';
        if (versoDiv) versoDiv.style.display = 'block';
        noPrint.forEach(function(el) { el.style.display = 'none'; });
        
        setTimeout(function() {
            window.print();
            
            setTimeout(function() {
                noPrint.forEach(function(el) { el.style.display = ''; });
                if (frenteDiv) frenteDiv.style.display = 'block';
                if (versoDiv) versoDiv.style.display = 'none';
            }, 500);
        }, 300);
    }
    
    window.onload = function() {
        console.log('🪪 Todos os cartões gerados! Total: <?= $total_alunos ?> alunos');
    };
</script>

</body>
</html>