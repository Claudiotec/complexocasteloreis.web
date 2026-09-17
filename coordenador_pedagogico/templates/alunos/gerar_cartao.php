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
    'ano_letivo' => ''
];

if (isset($_SESSION['cartao_config'])) {
    $config = array_merge($config_default, $_SESSION['cartao_config']);
} else {
    $config = $config_default;
}

// Processar upload do logo
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
            $alunos_massa = $stmt->fetchAll();
            
            if (empty($alunos_massa)) {
                $erro_massa = 'Nenhum aluno encontrado com os critérios selecionados.';
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
// FUNÇÃO PARA GERAR FRENTE DO CARTÃO
// ============================================
function gerarFrenteCartao($aluno, $config, $empresa, $ano_letivo, $telefone2) {
    $foto_url = encontrarFotoAlunoCartao($aluno);
    $tem_foto = !is_null($foto_url);
    
    $data_nasc = (isset($aluno['dia']) && isset($aluno['mes']) && isset($aluno['Ano']) && $aluno['dia'] > 0) ? 
        sprintf("%02d/%02d/%04d", $aluno['dia'], $aluno['mes'], $aluno['Ano']) : '-';
    
    $num_estudante = 'CR' . date('Y') . str_pad($aluno['id'] ?? 0, 4, '0', STR_PAD_LEFT);
    $classe = $aluno['Classe'] ?? '';
    $turma = $aluno['TURMA'] ?? '';
    $classe_turma = $classe . 'ª CLASSE – ' . $turma;
    $turno = strtoupper(htmlspecialchars($aluno['Periodo'] ?? ''));
    
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
    $mostrar_foto = $config['mostrar_foto'];
    $mostrar_info_importante = $config['mostrar_info_importante'];
    $fundo_decorado = $config['fundo_decorado'];
    $layout = $config['layout'];
    $logo_path = $config['logo_upload'] ?? '';
    
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
    
    $enderecoEmpresa = $empresa['endereco'] ?? 'Município do Bom Jesus – Km 44, Desvio';
    $cidadeEmpresa = $empresa['cidade'] ?? 'Província de Ícolo e Bengo – Angola';
    $telefoneEmpresa = $empresa['telefone'] ?? '923 456 789 / 934 567 890';
    $emailEmpresa = $empresa['email'] ?? 'www.casteloreisfilhos.ao';
    
    $html = '<div class="cartao-item">';
    $html .= '<div class="cartao cartao-frente" style="width:' . $tamanho_largura . 'mm;min-height:' . $tamanho_altura . 'mm;background:' . $cor_fundo . ';border-radius:' . $borda_arredondada . 'mm;border:1px solid ' . $cor_secundaria . ';font-size:' . $tamanho_fonte . 'pt;">';
    $html .= '<div class="fundo-decorado" style="' . $fundo_style . '"></div>';
    
    $html .= '<div class="cartao-header" style="background:' . $cor_cabecalho . ';border-bottom:3px solid ' . $cor_secundaria . ';">';
    $html .= '<div class="logo-container">';
    if (!empty($logo_path) && file_exists($_SERVER['DOCUMENT_ROOT'] . $logo_path)) {
        $html .= '<div class="logo-img" style="border:1px solid ' . $cor_secundaria . ';"><img src="' . $logo_path . '" alt="Logo"></div>';
    }
    $html .= '<div><div class="logo-text" style="color:' . $cor_secundaria . ';">COMPLEXO ESCOLAR PRIVADO<br>CASTELO REIS & FILHOS</div>';
    $html .= '<div class="logo-sub" style="color:#f0e6d3;">EDUCAR PARA TRANSFORMAR, FORMAR PARA A VIDA.</div></div></div></div>';
    
    $html .= '<div class="cartao-body" style="padding:4mm 5mm 3mm;">';
    $html .= '<div class="titulo-cartao" style="color:' . $cor_texto . ';border-bottom:2px solid ' . $cor_secundaria . ';">CARTÃO DE ESTUDANTE</div>';
    
    $html .= '<div class="foto-dados">';
    if ($mostrar_foto == 'sim') {
        $html .= '<div class="foto-container" style="border:2px solid ' . $cor_secundaria . ';background:#f5f0eb;">';
        if ($tem_foto) {
            $html .= '<img src="' . $foto_url . '" alt="Foto do Aluno">';
        } else {
            $html .= '<div class="sem-foto">👤</div>';
        }
        $html .= '</div>';
    }
    
    $html .= '<div class="dados-container">';
    $html .= '<div class="campo"><span class="label" style="color:#5a4a3a;">NOME DO ESTUDANTE</span>';
    $html .= '<div class="valor destaque" style="color:' . $cor_secundaria . ';">' . strtoupper(htmlspecialchars($aluno['nome'] ?? '')) . '</div></div>';
    
    $html .= '<div class="campo-duplo">';
    $html .= '<div class="item"><span class="label" style="color:#5a4a3a;">Nº DE ESTUDANTE</span><div class="valor" style="color:' . $cor_texto . ';">' . $num_estudante . '</div></div>';
    $html .= '<div class="item"><span class="label" style="color:#5a4a3a;">DATA DE NASCIMENTO</span><div class="valor" style="color:' . $cor_texto . ';">' . $data_nasc . '</div></div>';
    $html .= '</div>';
    
    $html .= '<div class="campo-tres-itens">';
    $html .= '<div class="item"><span class="label" style="color:#5a4a3a;">CLASSE / TURMA</span><div class="valor" style="color:' . $cor_texto . ';">' . htmlspecialchars($classe_turma) . '</div></div>';
    $html .= '<div class="item"><span class="label" style="color:#5a4a3a;">TURNO</span><div class="valor" style="color:' . $cor_texto . ';">' . $turno . '</div></div>';
    $html .= '<div class="item"><span class="label" style="color:#5a4a3a;">ANO LECTIVO</span><div class="valor" style="color:' . $cor_texto . ';">' . $ano_letivo . '</div></div>';
    $html .= '</div></div></div>';
    
    if ($mostrar_info_importante == 'sim') {
        $html .= '<div class="info-importante" style="border-left:3px solid ' . $cor_secundaria . ';background:#f8f4ef;">';
        $html .= '<div class="titulo-info" style="color:' . $cor_texto . ';">INFORMAÇÕES IMPORTANTES</div>';
        $html .= '<ul class="texto-info" style="color:#4a3a2a;">';
        $html .= '<li>Este cartão é pessoal e intransmissível.</li>';
        $html .= '<li>Deve ser apresentado sempre que solicitado.</li>';
        $html .= '<li>Em caso de perda, comunique imediatamente à Direcção da Escola.</li>';
        $html .= '<li>O uso deste cartão implica o cumprimento das normas e regulamentos do Complexo Escolar Castelo Reis & Filhos.</li>';
        $html .= '</ul></div>';
    }
    $html .= '</div>';
    
    $html .= '<div class="cartao-footer" style="background:' . $cor_rodape . ';border-top:2px solid ' . $cor_secundaria . ';">';
    $html .= '<div class="contato"><strong style="color:' . $cor_texto . ';">' . htmlspecialchars($enderecoEmpresa) . '</strong><br>' . htmlspecialchars($cidadeEmpresa) . '</div>';
    $html .= '<div class="contato"><strong style="color:' . $cor_texto . ';">' . htmlspecialchars($telefoneEmpresa) . '</strong><br>' . htmlspecialchars($emailEmpresa) . ' | ' . $telefone2 . '</div>';
    $html .= '</div></div></div>';
    
    return $html;
}

// ============================================
// FUNÇÃO PARA GERAR VERSO DO CARTÃO
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
    
    $fundos_decorados = [
        'classico' => '',
        'gradiente' => 'background: linear-gradient(135deg, ' . $cor_primaria . ' 0%, ' . $cor_secundaria . ' 100%); opacity: 0.08;',
        'geometrico' => 'background: repeating-linear-gradient(45deg, ' . $cor_primaria . ', ' . $cor_primaria . ' 2mm, ' . $cor_secundaria . ' 2mm, ' . $cor_secundaria . ' 4mm); opacity: 0.08;',
        'ondas' => 'background: radial-gradient(circle at 20% 50%, ' . $cor_primaria . ' 0%, transparent 50%), radial-gradient(circle at 80% 50%, ' . $cor_secundaria . ' 0%, transparent 50%); opacity: 0.12;',
        'diamante' => 'background: linear-gradient(45deg, ' . $cor_primaria . ' 25%, transparent 25%, transparent 75%, ' . $cor_primaria . ' 75%), linear-gradient(45deg, ' . $cor_primaria . ' 25%, transparent 25%, transparent 75%, ' . $cor_primaria . ' 75%); background-size: 4mm 4mm; background-position: 0 0, 2mm 2mm; opacity: 0.08;',
    ];
    $fundo_style = ($fundo_decorado == 'sim' && isset($fundos_decorados[$layout])) ? $fundos_decorados[$layout] : '';
    
    $html = '<div class="cartao-item">';
    $html .= '<div class="cartao cartao-verso" style="width:' . $tamanho_largura . 'mm;min-height:' . $tamanho_altura . 'mm;background:' . $cor_fundo . ';border-radius:' . $borda_arredondada . 'mm;border:1px solid ' . $cor_secundaria . ';font-size:' . $tamanho_fonte . 'pt;">';
    $html .= '<div class="fundo-decorado-verso" style="' . $fundo_style . '"></div>';
    
    $html .= '<div class="verso-header" style="background:' . $cor_cabecalho . ';border-bottom:3px solid ' . $cor_secundaria . ';">';
    $html .= '<div class="verso-titulo" style="color:' . $cor_secundaria . ';">CONTROLE DE PAGAMENTOS</div></div>';
    
    $html .= '<div class="verso-body">';
    $html .= '<div class="info-encarregado" style="border-left:3px solid ' . $cor_secundaria . ';background:#f8f4ef;">';
    $html .= '<strong style="color:' . $cor_texto . ';">Encarregado:</strong> ' . htmlspecialchars($aluno['Nome_do_Pai'] ?? 'Não informado') . '<br>';
    $html .= '<strong style="color:' . $cor_texto . ';">Contacto:</strong> ' . htmlspecialchars($aluno['Contacto4'] ?? 'Não informado') . '</div>';
    
    $html .= '<table class="tabela-pagamentos">';
    $html .= '<thead>';
    $html .= '<tr><th colspan="3" style="background:' . $cor_secundaria . ';color:' . $cor_texto . ';">PAGAMENTOS MENSAIS</th></tr>';
    $html .= '<tr>';
    $html .= '<th style="background:' . $cor_secundaria . ';color:' . $cor_texto . ';width:40%;">Mês</th>';
    $html .= '<th style="background:' . $cor_secundaria . ';color:' . $cor_texto . ';width:30%;">Propina</th>';
    $html .= '<th style="background:' . $cor_secundaria . ';color:' . $cor_texto . ';width:30%;">Transporte</th>';
    $html .= '</tr></thead>';
    $html .= '<tbody>';
    $meses = ['SET', 'OUT', 'NOV', 'DEZ', 'JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL'];
    foreach($meses as $mes) {
        $html .= '<tr><td>' . $mes . '</td><td></td><td></td></tr>';
    }
    $html .= '</tbody></table>';
    $html .= '</div>';
    
    $html .= '<div class="verso-footer" style="background:' . $cor_rodape . ';border-top:2px solid ' . $cor_secundaria . ';">';
    $html .= '<strong style="color:' . $cor_texto . ';">ID:</strong> ' . htmlspecialchars($aluno['id'] ?? '') . ' &nbsp;|&nbsp;';
    $html .= '<strong style="color:' . $cor_texto . ';">ANO:</strong> ' . $ano_letivo . ' &nbsp;|&nbsp;';
    $html .= '<strong style="color:' . $cor_texto . ';">Emitido:</strong> ' . date('d/m/Y') . '</div></div></div>';
    
    return $html;
}

// ============================================
// FUNÇÃO PARA GERAR GRADE DE CARTÕES (2 POR LINHA)
// ============================================
function gerarGradeCartoes($alunos, $config, $empresa, $ano_letivo, $telefone2, $tipo = 'frente') {
    $html = '';
    $total = count($alunos);
    $por_linha = 2;
    
    for ($i = 0; $i < $total; $i += $por_linha) {
        $html .= '<div class="linha-cartoes">';
        
        for ($j = 0; $j < $por_linha; $j++) {
            $idx = $i + $j;
            if ($idx < $total) {
                if ($tipo === 'verso') {
                    $html .= gerarVersoCartao($alunos[$idx], $config, $empresa, $ano_letivo, $telefone2);
                } else {
                    $html .= gerarFrenteCartao($alunos[$idx], $config, $empresa, $ano_letivo, $telefone2);
                }
            } else {
                $html .= '<div class="cartao-item vazio"></div>';
            }
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
        /* ============================================
           CONFIGURAÇÕES DINÂMICAS
           ============================================ */
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

        /* ============================================
           ESTILOS DO CARTÃO - AJUSTADO PARA FOLHA A4
           ============================================ */
        @page {
            size: A4;
            margin: 5mm;
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
        
        /* ============================================
           GRADE DE CARTÕES - LADO A LADO
           ============================================ */
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
        
        /* ============================================
           PAINEL DE CONFIGURAÇÃO
           ============================================ */
        .config-panel, .massa-panel {
            background: #ffffff;
            border-radius: 12px;
            padding: 20px 25px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            max-width: 950px;
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
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }
        
        .config-panel .config-group {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        
        .config-panel .config-group label {
            font-size: 11px;
            font-weight: 600;
            color: #4a5568;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .config-panel .config-group input[type="color"] {
            width: 100%;
            height: 35px;
            padding: 2px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            cursor: pointer;
        }
        
        .config-panel .config-group input[type="number"],
        .config-panel .config-group input[type="text"] {
            width: 100%;
            padding: 6px 10px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 13px;
        }
        
        .config-panel .config-group select {
            width: 100%;
            padding: 6px 10px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 13px;
        }
        
        .config-panel .config-group .range-labels {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: #94a3b8;
        }
        
        .config-panel .config-group.checkbox-group {
            flex-direction: row;
            align-items: center;
            gap: 8px;
            padding-top: 8px;
        }
        
        .config-panel .config-group.checkbox-group label {
            text-transform: none;
            font-weight: 500;
            font-size: 13px;
            cursor: pointer;
        }
        
        .config-panel .config-group.checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--cor-secundaria);
            cursor: pointer;
        }
        
        .config-panel .upload-logo {
            display: flex;
            gap: 8px;
            align-items: center;
            padding: 6px 10px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px dashed #e2e8f0;
        }
        
        .config-panel .upload-logo .preview-logo {
            width: 30px;
            height: 30px;
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
            font-size: 11px;
            flex: 1;
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
            padding: 8px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
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
            font-size: 12px;
            color: #94a3b8;
            margin-top: 10px;
            text-align: center;
            border-top: 1px solid #f1f5f9;
            padding-top: 10px;
        }

        /* ============================================
           PAINEL DE GERAÇÃO EM MASSA
           ============================================ */
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
            font-size: 12px;
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

        /* ============================================
           CARTÃO FRENTE
           ============================================ */
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
        
        .cartao-header {
            background: var(--cor-cabecalho);
            padding: 3mm 4mm 2mm;
            text-align: center;
            border-bottom: 2px solid var(--cor-secundaria);
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
            width: 8mm;
            height: 8mm;
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
            font-size: 0.9em;
            font-weight: 800;
            color: var(--cor-secundaria);
            letter-spacing: 0.5px;
            text-transform: uppercase;
            line-height: 1.1;
        }
        
        .cartao-header .logo-sub {
            font-size: 0.5em;
            color: #f0e6d3;
            font-weight: 400;
            letter-spacing: 1px;
            margin-top: 0.5mm;
            text-transform: uppercase;
        }
        
        .cartao-body {
            padding: 2mm 3mm 2mm;
            position: relative;
            z-index: 1;
        }
        
        .cartao-body .titulo-cartao {
            text-align: center;
            font-size: 0.9em;
            font-weight: 700;
            color: var(--cor-texto);
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1.5px solid var(--cor-secundaria);
            padding-bottom: 1.5mm;
            margin-bottom: 2mm;
        }
        
        .foto-dados {
            display: flex;
            gap: 2mm;
            align-items: flex-start;
        }
        
        .foto-container {
            width: 22mm;
            height: 26mm;
            background: #f5f0eb;
            border: 1.5px solid var(--cor-secundaria);
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
            font-size: 24px;
            color: #b0a090;
        }
        
        .dados-container {
            flex: 1;
        }
        
        .dados-container .campo {
            margin-bottom: 1mm;
            padding-bottom: 0.5mm;
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
            font-size: 0.5em;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: block;
        }
        
        .dados-container .valor {
            font-weight: 600;
            color: var(--cor-texto);
            font-size: 0.7em;
            margin-top: 0.3mm;
        }
        
        .dados-container .valor.destaque {
            color: var(--cor-secundaria);
            font-size: 0.75em;
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
            font-size: 0.5em;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: block;
        }
        
        .campo-duplo .item .valor {
            font-weight: 600;
            color: var(--cor-texto);
            font-size: 0.7em;
            margin-top: 0.3mm;
        }
        
        .campo-tres-itens {
            display: flex;
            gap: 2mm;
            padding-top: 0.5mm;
            border-top: 0.5px dashed #e0d5c8;
        }
        
        .campo-tres-itens .item {
            flex: 1;
        }
        
        .campo-tres-itens .item .label {
            font-weight: 600;
            color: #5a4a3a;
            font-size: 0.45em;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: block;
        }
        
        .campo-tres-itens .item .valor {
            font-weight: 600;
            color: var(--cor-texto);
            font-size: 0.65em;
            margin-top: 0.3mm;
        }
        
        .info-importante {
            margin-top: 2mm;
            padding: 1.5mm 2.5mm;
            background: #f8f4ef;
            border-radius: 2mm;
            border-left: 2px solid var(--cor-secundaria);
        }
        
        .info-importante .titulo-info {
            font-size: 0.5em;
            font-weight: 700;
            color: var(--cor-texto);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5mm;
        }
        
        .info-importante .texto-info {
            font-size: 0.5em;
            color: #4a3a2a;
            line-height: 1.3;
            list-style: none;
            padding-left: 0;
        }
        
        .info-importante .texto-info li {
            padding: 0.2mm 0;
            padding-left: 2.5mm;
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
            padding: 1.5mm 3mm;
            border-top: 2px solid var(--cor-secundaria);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.5em;
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
        
        /* ============================================
           VERSO DO CARTÃO
           ============================================ */
        .cartao-verso {
            background: var(--cor-fundo);
            border-radius: var(--borda-arredondada);
            box-shadow: 0 2mm 4mm rgba(0,0,0,0.1);
            overflow: hidden;
            border: 1px solid var(--cor-secundaria);
            position: relative;
            width: var(--largura-cartao);
            min-height: var(--altura-cartao);
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
            border-bottom: 2px solid var(--cor-secundaria);
            position: relative;
            z-index: 1;
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
        }
        
        .cartao-verso .info-encarregado {
            font-size: 0.7em;
            padding: 1.5mm 2mm;
            background: #f8f4ef;
            border-radius: 1.5mm;
            margin-bottom: 2mm;
            border-left: 2px solid var(--cor-secundaria);
        }
        
        .cartao-verso .info-encarregado strong {
            color: var(--cor-texto);
        }
        
        .cartao-verso .tabela-pagamentos {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.6em;
        }
        
        .cartao-verso .tabela-pagamentos th {
            background: var(--cor-secundaria);
            color: var(--cor-texto);
            padding: 1mm 0.5mm;
            text-align: center;
            font-weight: 700;
            border: 0.5px solid var(--cor-secundaria);
        }
        
        .cartao-verso .tabela-pagamentos td {
            border: 0.5px solid #e0d5c8;
            padding: 1mm 0.5mm;
            text-align: center;
            height: 3.5mm;
        }
        
        .cartao-verso .tabela-pagamentos tr:nth-child(even) td {
            background: #faf8f5;
        }
        
        .cartao-verso .verso-footer {
            background: var(--cor-rodape);
            padding: 1.5mm 3mm;
            border-top: 2px solid var(--cor-secundaria);
            text-align: center;
            font-size: 0.55em;
            color: #5a4a3a;
            position: relative;
            z-index: 1;
        }
        
        .cartao-verso .verso-footer strong {
            color: var(--cor-texto);
        }
        
        /* ============================================
           IMPRESSÃO
           ============================================ */
        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }
            .no-print {
                display: none !important;
            }
            .cartao-wrapper {
                max-width: 100%;
                padding: 0;
                margin: 0;
            }
            .linha-cartoes {
                gap: 3mm;
                margin-bottom: 3mm;
                page-break-inside: avoid;
            }
            .cartao-frente, .cartao-verso {
                box-shadow: none;
                border: 1px solid #ccc;
            }
            .cartao-item {
                page-break-inside: avoid;
            }
            .config-panel, .massa-panel {
                display: none !important;
            }
            .contador-cartoes {
                display: none !important;
            }
        }
        
        /* ============================================
           RESPONSIVO
           ============================================ */
        @media (max-width: 768px) {
            .config-panel .config-grid,
            .massa-panel .massa-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .linha-cartoes {
                flex-direction: column;
                align-items: center;
                gap: 10px;
            }
            
            .cartao-item.vazio {
                display: none;
            }
        }
        
        @media (max-width: 480px) {
            .config-panel .config-grid,
            .massa-panel .massa-grid {
                grid-template-columns: 1fr;
            }
            .config-actions, .massa-actions {
                flex-direction: column;
            }
            .config-actions .btn,
            .massa-actions .btn-massa,
            .massa-actions .btn-massa-secondary {
                width: 100%;
                justify-content: center;
                text-align: center;
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
            
            <!-- Upload Logo -->
            <div class="config-group" style="grid-column: span 1;">
                <label>📤 Upload Logo</label>
                <div class="upload-logo">
                    <div class="preview-logo">
                        <?php if (!empty($config['logo_upload']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $config['logo_upload'])): ?>
                            <img src="<?= $config['logo_upload'] ?>" alt="Logo">
                        <?php else: ?>
                            <span style="font-size:10px;color:#999;">CR</span>
                        <?php endif; ?>
                    </div>
                    <input type="file" name="logo_upload" accept="image/*">
                    <button type="submit" name="upload_logo" class="btn btn-save" style="padding:4px 12px;font-size:11px;">⬆️</button>
                </div>
            </div>
            
            <!-- Ano Letivo -->
            <div class="config-group">
                <label>📅 Ano Lectivo</label>
                <input type="text" name="ano_letivo" value="<?= htmlspecialchars($ano_letivo) ?>" placeholder="Ex: 2024 / 2025">
                <span style="font-size:10px;color:#94a3b8;">Deixe em branco para usar o ano actual</span>
            </div>
            
            <!-- Cores -->
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
            
            <!-- Tamanhos -->
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
            
            <!-- Opções -->
            <div class="config-group checkbox-group">
                <input type="checkbox" name="fundo_decorado" id="fundo_decorado" <?= $config['fundo_decorado'] == 'sim' ? 'checked' : '' ?>>
                <label for="fundo_decorado">🎨 Fundo Decorado</label>
            </div>
            <div class="config-group checkbox-group">
                <input type="checkbox" name="mostrar_foto" id="mostrar_foto" <?= $config['mostrar_foto'] == 'sim' ? 'checked' : '' ?>>
                <label for="mostrar_foto">📸 Mostrar Foto</label>
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
    </form>
</div>

<!-- ==========================================
     CARTÕES - FRENTE
     ========================================== -->
<div id="frente-cartoes" class="cartao-wrapper">
    <?php if ($modo_massa && empty($erro_massa) && !empty($alunos_massa)): ?>
        <div class="contador-cartoes no-print">
            📄 <?= count($alunos_massa) ?> cartões - FRENTE (2 por linha)
        </div>
        <?= gerarGradeCartoes($alunos_massa, $config, $empresa, $ano_letivo, $telefone2, 'frente') ?>
        
    <?php elseif (!$modo_massa && $aluno): ?>
        <div class="linha-cartoes">
            <?= gerarFrenteCartao($aluno, $config, $empresa, $ano_letivo, $telefone2) ?>
        </div>
        
    <?php elseif ($modo_massa && !empty($erro_massa) && $aluno): ?>
        <div class="linha-cartoes">
            <?= gerarFrenteCartao($aluno, $config, $empresa, $ano_letivo, $telefone2) ?>
        </div>
    <?php endif; ?>
</div>

<!-- ==========================================
     CARTÕES - VERSO (OCULTO PARA IMPRESSÃO)
     ========================================== -->
<?php if ($config['mostrar_verso'] == 'sim'): ?>
<div id="verso-cartoes" style="display:none;">
    <div class="cartao-wrapper">
        <?php if ($modo_massa && empty($erro_massa) && !empty($alunos_massa)): ?>
            <div class="contador-cartoes no-print">
                📄 <?= count($alunos_massa) ?> cartões - VERSO (2 por linha)
            </div>
            <?= gerarGradeCartoes($alunos_massa, $config, $empresa, $ano_letivo, $telefone2, 'verso') ?>
            
        <?php elseif (!$modo_massa && $aluno): ?>
            <div class="linha-cartoes">
                <?= gerarVersoCartao($aluno, $config, $empresa, $ano_letivo, $telefone2) ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
    // ============================================
    // FUNÇÕES DO PAINEL DE CONFIGURAÇÃO
    // ============================================
    
    function toggleConfig() {
        var grid = document.getElementById('configGrid');
        if (grid.style.display === 'none') {
            grid.style.display = 'grid';
        } else {
            grid.style.display = 'none';
        }
    }
    
    // ============================================
    // FUNÇÕES DO PAINEL DE GERAÇÃO EM MASSA
    // ============================================
    
    function toggleMassaOptions() {
        var tipo = document.getElementById('tipo_geracao').value;
        var turmaGroup = document.getElementById('turma_group');
        var classeGroup = document.getElementById('classe_group');
        var quantidadeGroup = document.getElementById('quantidade_group');
        var idsGroup = document.getElementById('ids_group');
        
        turmaGroup.style.display = 'none';
        classeGroup.style.display = 'none';
        quantidadeGroup.style.display = 'none';
        idsGroup.style.display = 'none';
        
        if (tipo === 'turma') {
            turmaGroup.style.display = 'block';
            classeGroup.style.display = 'block';
        } else if (tipo === 'quantidade') {
            quantidadeGroup.style.display = 'block';
        } else if (tipo === 'ids') {
            idsGroup.style.display = 'block';
        }
    }
    
    // ============================================
    // FUNÇÃO PARA IMPRIMIR FRENTE
    // ============================================
    
    function imprimirFrente() {
        var frenteDiv = document.getElementById('frente-cartoes');
        var versoDiv = document.getElementById('verso-cartoes');
        var configPanels = document.querySelectorAll('.config-panel, .massa-panel, .contador-cartoes');
        
        // Esconder o verso e os painéis
        if (versoDiv) versoDiv.style.display = 'none';
        configPanels.forEach(function(el) {
            el.style.display = 'none';
        });
        
        // Garantir que a frente está visível
        if (frenteDiv) frenteDiv.style.display = 'block';
        
        // Imprimir
        window.print();
        
        // Restaurar visibilidade
        setTimeout(function() {
            if (versoDiv) versoDiv.style.display = 'none';
            configPanels.forEach(function(el) {
                el.style.display = '';
            });
            if (frenteDiv) frenteDiv.style.display = 'block';
        }, 500);
    }
    
    // ============================================
    // FUNÇÃO PARA IMPRIMIR VERSO
    // ============================================
    
    function imprimirVerso() {
        var versoDiv = document.getElementById('verso-cartoes');
        var frenteDiv = document.getElementById('frente-cartoes');
        var configPanels = document.querySelectorAll('.config-panel, .massa-panel, .contador-cartoes');
        
        if (!versoDiv) {
            alert('O verso não está disponível. Verifique a configuração "Mostrar Verso".');
            return;
        }
        
        // Esconder a frente e os painéis
        if (frenteDiv) frenteDiv.style.display = 'none';
        configPanels.forEach(function(el) {
            el.style.display = 'none';
        });
        
        // Mostrar o verso
        versoDiv.style.display = 'block';
        
        // Imprimir
        window.print();
        
        // Restaurar visibilidade
        setTimeout(function() {
            if (frenteDiv) frenteDiv.style.display = 'block';
            configPanels.forEach(function(el) {
                el.style.display = '';
            });
            versoDiv.style.display = 'none';
        }, 500);
    }
    
    // ============================================
    // INICIALIZAÇÃO
    // ============================================
    
    document.addEventListener('DOMContentLoaded', function() {
        toggleMassaOptions();
        
        // Validar tamanhos mínimos e máximos
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
        
        // Mostrar configurações por padrão
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
                    setTimeout(function() {
                        msg.remove();
                    }, 500);
                }, 3000);
            }
        }
        if (window.location.search.indexOf('logo=ok') !== -1) {
            var configPanel = document.querySelector('.config-panel');
            if (configPanel) {
                var msg = document.createElement('div');
                msg.style.cssText = 'background:#d1fae5;color:#065f46;padding:10px 15px;border-radius:8px;margin-top:10px;text-align:center;font-weight:600;';
                msg.textContent = '✅ Logo enviado com sucesso!';
                configPanel.appendChild(msg);
                setTimeout(function() {
                    msg.style.opacity = '0';
                    msg.style.transition = 'opacity 0.5s';
                    setTimeout(function() {
                        msg.remove();
                    }, 500);
                }, 3000);
            }
        }
    });
</script>

</body>
</html>