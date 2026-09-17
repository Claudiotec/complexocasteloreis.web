<?php
// ============================================
// modules/escola/alunos/gerar_ficha_matricula.php - Gerar Ficha de Matrícula
// SEM BORDA NA INSÍGNIA
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

$id = $_GET['id'] ?? 0;
$aluno = null;

if ($id) {
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("SELECT * FROM alunos WHERE id = ?");
        $stmt->execute([$id]);
        $aluno = $stmt->fetch();
        
        if (!$aluno) {
            header('Location: index.php');
            exit;
        }
        
        $campos_padrao = [
            'Data_Matricula' => null,
            'Data_Emissao_do_BI' => null,
            'foto' => null,
            'Sexo' => 'M',
            'Situacao_Cadastro' => 'Matrícula',
            'Cadastro_Transporte' => 'Não',
            'Debilidade' => 'Nenhuma',
            'Idade' => 0
        ];
        
        foreach ($campos_padrao as $campo => $padrao) {
            if (!isset($aluno[$campo])) {
                $aluno[$campo] = $padrao;
            }
        }
        
    } catch (Exception $e) {
        header('Location: index.php');
        exit;
    }
}

// ===== DADOS DA EMPRESA - BUSCANDO DO BANCO =====
$empresa = [];
try {
    $pdo = conectarBanco();
    $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
    $empresa = $stmt->fetch();
} catch (Exception $e) {}

// ===== CAMPOS DA EMPRESA =====
$idEmpresa = $empresa['id'] ?? '';
$razaoSocial = $empresa['razao_social'] ?? 'COMPLEXO ESCOLAR CASTELO REIS';
$nomeFantasia = $empresa['nome_fantasia'] ?? 'COMPLEXO ESCOLAR CASTELO REIS';
$cnpj = $empresa['cnpj'] ?? '500 10923';
$inscricaoEstadual = $empresa['inscricao_estadual'] ?? '';
$inscricaoMunicipal = $empresa['inscricao_municipal'] ?? '';
$endereco = $empresa['endereco'] ?? 'Icolo e Bengo; Km44, Desvio do Bom Jesus';
$numero = $empresa['numero'] ?? 'Km44';
$complemento = $empresa['complemento'] ?? 'Desvio Bom Jesus';
$bairro = $empresa['bairro'] ?? 'Icolo e Bengo';
$cidade = $empresa['cidade'] ?? '';
$estado = $empresa['estado'] ?? '';
$cep = $empresa['cep'] ?? '500109175';
$telefone = $empresa['telefone'] ?? '972902412';
$celular = $empresa['celular'] ?? '972902412';
$email = $empresa['email'] ?? 'complexoescolarcasteloreis@gmail.com';
$site = $empresa['site'] ?? 'www.softgest.com';
$logo = $empresa['logo'] ?? '';

// ===== NOME DA ESCOLA (prioridade: nome_fantasia > razao_social) =====
$nomeEscola = !empty($nomeFantasia) ? $nomeFantasia : $razaoSocial;

// ===== ENDEREÇO COMPLETO =====
$enderecoCompleto = $endereco;
if ($numero) $enderecoCompleto .= ', ' . $numero;
if ($complemento) $enderecoCompleto .= ' - ' . $complemento;
if ($bairro) $enderecoCompleto .= ', ' . $bairro;
if ($cidade) $enderecoCompleto .= ', ' . $cidade;
if ($estado) $enderecoCompleto .= ' - ' . $estado;

// ============================================
// FUNÇÃO PARA ENCONTRAR A FOTO
// ============================================
function encontrarFotoAlunoFicha($aluno) {
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
            
            foreach ($extensoes as $ext) {
                $caminho = $dir . 'aluno_' . $id_aluno . '.' . $ext;
                if (file_exists($caminho)) {
                    return '/softgest_web/uploads/alunos/aluno_' . $id_aluno . '.' . $ext;
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

$foto_url = encontrarFotoAlunoFicha($aluno);
$tem_foto = !is_null($foto_url);

// ===== FORMATAR DADOS =====
$data_nasc = '';
if (($aluno['dia'] ?? 0) > 0 && ($aluno['mes'] ?? 0) > 0 && ($aluno['Ano'] ?? 0) > 0) {
    $data_nasc = sprintf("%02d/%02d/%04d", $aluno['dia'], $aluno['mes'], $aluno['Ano']);
} else {
    $data_nasc = '-';
}

$data_matricula = '-';
if (isset($aluno['Data_Matricula']) && !empty($aluno['Data_Matricula']) && $aluno['Data_Matricula'] != '0000-00-00') {
    $data_matricula = date('d/m/Y', strtotime($aluno['Data_Matricula']));
}

$data_emissao_bi = '-';
if (isset($aluno['Data_Emissao_do_BI']) && !empty($aluno['Data_Emissao_do_BI']) && $aluno['Data_Emissao_do_BI'] != '0000-00-00') {
    $data_emissao_bi = date('d/m/Y', strtotime($aluno['Data_Emissao_do_BI']));
}

$sexo = $aluno['Sexo'] ?? 'M';
$sexoLabel = $sexo == 'M' ? 'Masculino' : 'Feminino';
$sexoClass = $sexo == 'M' ? 'badge-m' : 'badge-f';

$situacao = $aluno['Situacao_Cadastro'] ?? 'Matrícula';
$situacaoClass = $situacao == 'Matrícula' ? 'badge-matricula' : 'badge-confirmacao';

$idade = $aluno['Idade'] ?? 0;
if ($idade == 0 && !empty($data_nasc) && $data_nasc != '-') {
    $data_nasc_obj = DateTime::createFromFormat('d/m/Y', $data_nasc);
    if ($data_nasc_obj) {
        $hoje = new DateTime();
        $idade = $hoje->diff($data_nasc_obj)->y;
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha de Matrícula - <?= htmlspecialchars($aluno['nome'] ?? 'Aluno') ?></title>
    <style>
        /* ============================================
           RESET E LAYOUT PRINCIPAL
           ============================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            font-size: 13px;
            background: #ffffff;
            padding: 15px;
            color: #1a2332;
        }
        
        .container {
            max-width: 210mm;
            margin: 0 auto;
            background: #ffffff;
            padding: 25px 30px;
            border: 1px solid #e2e8f0;
            page-break-inside: avoid;
        }
        
        /* ============================================
           HEADER INSTITUCIONAL
           ============================================ */
        .header-institucional {
            text-align: center;
            border-bottom: 3px solid #1a2332;
            padding-bottom: 12px;
            margin-bottom: 15px;
            position: relative;
        }
        
        .header-institucional .insignia-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 5px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .header-institucional .insignia-container:hover {
            transform: scale(1.05);
        }
        
        .header-institucional .insignia-container .insignia {
            width: 85px;
            height: 85px;
            border-radius: 50%;
            /* BORDA REMOVIDA */
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .header-institucional .insignia-container .insignia img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
            padding: 5px;
        }
        
        .header-institucional .insignia-container .insignia .insignia-emoji {
            font-size: 50px;
            line-height: 1;
        }
        
        .header-institucional .insignia-container .edit-icon-sm {
            font-size: 12px;
            opacity: 0.4;
            margin-left: 5px;
            align-self: center;
        }
        
        .header-institucional .linha1 {
            font-size: 14px;
            font-weight: 700;
            color: #1a2332;
            letter-spacing: 2px;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .header-institucional .linha1:hover {
            color: #c9a84c;
        }
        
        .header-institucional .linha2 {
            font-size: 13px;
            font-weight: 600;
            color: #1a2332;
            letter-spacing: 1px;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .header-institucional .linha2:hover {
            color: #c9a84c;
        }
        
        .header-institucional .linha3 {
            font-size: 16px;
            font-weight: 800;
            color: #1a2332;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: color 0.2s;
            margin-top: 2px;
        }
        
        .header-institucional .linha3:hover {
            color: #c9a84c;
        }
        
        .header-institucional .linha3 span {
            color: #c9a84c;
        }
        
        /* NOVA LINHA - NOME DA ESCOLA */
        .header-institucional .linha4 {
            font-size: 15px;
            font-weight: 700;
            color: #1a2332;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: color 0.2s;
            margin-top: 2px;
        }
        
        .header-institucional .linha4:hover {
            color: #c9a84c;
        }
        
        .header-institucional .linha4 span {
            color: #c9a84c;
        }
        
        .header-institucional .edit-icon {
            font-size: 12px;
            opacity: 0.4;
            margin-left: 5px;
            transition: opacity 0.2s;
        }
        
        .header-institucional .edit-icon:hover {
            opacity: 1;
        }
        
        /* Dados da Empresa */
        .header-institucional .dados-empresa {
            font-size: 12px;
            color: #4a5568;
            margin-top: 4px;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .header-institucional .dados-empresa:hover {
            color: #c9a84c;
        }
        
        .header-institucional .dados-empresa .sep {
            margin: 0 5px;
            color: #c9a84c;
        }
        
        /* ============================================
           HEADER COM FOTO - ESTILO MODELO
           ============================================ */
        .header-foto {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 10px 0 5px;
            padding: 10px 15px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        
        .header-foto .info-aluno {
            flex: 1;
        }
        
        .header-foto .info-aluno .nome-aluno {
            font-size: 18px;
            font-weight: 700;
            color: #1a2332;
        }
        
        .header-foto .info-aluno .detalhes-aluno {
            display: flex;
            gap: 20px;
            margin-top: 3px;
            flex-wrap: wrap;
        }
        
        .header-foto .info-aluno .detalhes-aluno span {
            font-size: 12px;
            color: #4a5568;
        }
        
        .header-foto .info-aluno .detalhes-aluno strong {
            color: #1a2332;
        }
        
        .header-foto .foto-container {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
            border: 3px solid #c9a84c;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .header-foto .foto-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .header-foto .foto-container .sem-foto {
            font-size: 38px;
            color: #94a3b8;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            background: #f1f5f9;
        }
        
        /* ============================================
           TÍTULO
           ============================================ */
        .titulo-ficha {
            text-align: center;
            margin: 5px 0 15px;
        }
        
        .titulo-ficha h1 {
            font-size: 19px;
            font-weight: 700;
            color: #1a2332;
            letter-spacing: 2px;
            text-transform: uppercase;
            background: #c9a84c;
            color: #1a2332;
            padding: 6px 30px;
            display: inline-block;
            border-radius: 4px;
        }
        
        /* ============================================
           SEÇÕES
           ============================================ */
        .section-title {
            font-size: 14px;
            font-weight: 700;
            color: #1a2332;
            margin: 14px 0 10px;
            padding-bottom: 6px;
            border-bottom: 2px solid #c9a84c;
            clear: both;
        }
        
        .section-title .icon {
            margin-right: 5px;
        }
        
        /* ============================================
           GRID EM DUAS COLUNAS
           ============================================ */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 30px;
            margin-bottom: 5px;
        }
        
        .info-item {
            display: flex;
            padding: 5px 0;
            border-bottom: 1px solid #f1f5f9;
            align-items: center;
        }
        
        .info-item .label {
            width: 130px;
            font-weight: 600;
            color: #4a5568;
            flex-shrink: 0;
            font-size: 13px;
        }
        
        .info-item .valor {
            color: #1a2332;
            font-weight: 500;
            font-size: 13px;
        }
        
        .info-item .valor strong {
            font-weight: 700;
        }
        
        /* Badges */
        .badge-sexo {
            display: inline-block;
            padding: 2px 14px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-m {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .badge-f {
            background: #fce7f3;
            color: #9d174d;
        }
        
        .badge-situacao {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-matricula {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .badge-confirmacao {
            background: #d1fae5;
            color: #065f46;
        }
        
        /* ============================================
           ASSINATURAS - 3 COLUNAS
           ============================================ */
        .assinatura {
            margin-top: 25px;
            padding-top: 18px;
            border-top: 2px solid #e2e8f0;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            text-align: center;
        }
        
        .assinatura .linha {
            border-bottom: 1.5px solid #1a2332;
            margin: 30px auto 8px;
            width: 80%;
            max-width: 180px;
        }
        
        .assinatura .label {
            font-size: 13px;
            font-weight: 600;
            color: #1a2332;
        }
        
        /* ============================================
           FOOTER
           ============================================ */
        .footer {
            text-align: center;
            margin-top: 18px;
            font-size: 11px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 12px;
        }
        
        /* ============================================
           IMPRESSÃO
           ============================================ */
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .container {
                border: none;
                padding: 18px 22px;
                box-shadow: none;
                page-break-after: avoid;
            }
            .no-print {
                display: none !important;
            }
            .info-grid {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                gap: 4px 30px !important;
            }
            .info-item {
                display: flex !important;
                padding: 4px 0 !important;
                border-bottom: 1px solid #f1f5f9 !important;
            }
            .info-item .label {
                width: 130px !important;
                font-size: 12px !important;
            }
            .info-item .valor {
                font-size: 12px !important;
            }
            .assinatura {
                display: grid !important;
                grid-template-columns: 1fr 1fr 1fr !important;
                gap: 20px !important;
                text-align: center !important;
            }
            .assinatura .linha {
                width: 80% !important;
                max-width: 160px !important;
                margin: 25px auto 8px !important;
            }
            .assinatura .label {
                font-size: 13px !important;
                font-weight: 600 !important;
            }
            .header-foto .foto-container {
                width: 80px;
                height: 80px;
            }
            .header-institucional .insignia-container .insignia {
                width: 75px;
                height: 75px;
            }
            .header-institucional .insignia-container .insignia .insignia-emoji {
                font-size: 40px;
            }
            .section-title {
                font-size: 13px;
                margin: 12px 0 8px;
            }
        }
        
        /* ============================================
           RESPONSIVO
           ============================================ */
        @media (max-width: 768px) {
            .container {
                padding: 12px;
            }
            .header-foto {
                flex-direction: column-reverse;
                text-align: center;
                gap: 10px;
            }
            .header-foto .info-aluno .detalhes-aluno {
                justify-content: center;
            }
            .info-grid {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .info-item .label {
                width: 100px;
            }
            .assinatura {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            .assinatura .linha {
                max-width: 150px;
            }
            .header-foto .foto-container {
                width: 70px;
                height: 70px;
            }
            .header-institucional .insignia-container .insignia {
                width: 65px;
                height: 65px;
            }
            .header-institucional .insignia-container .insignia .insignia-emoji {
                font-size: 36px;
            }
            .header-institucional .linha1 {
                font-size: 12px;
            }
            .header-institucional .linha2 {
                font-size: 11px;
            }
            .header-institucional .linha3 {
                font-size: 13px;
            }
            .header-institucional .linha4 {
                font-size: 12px;
            }
        }
        
        /* ============================================
           MODAL PARA EDIÇÃO
           ============================================ */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal-content {
            background: #fff;
            padding: 25px 30px;
            border-radius: 12px;
            max-width: 550px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        
        .modal-content h3 {
            font-size: 18px;
            color: #1a2332;
            margin-bottom: 10px;
        }
        
        .modal-content label {
            font-weight: 600;
            display: block;
            margin: 10px 0 5px;
            color: #4a5568;
            font-size: 13px;
        }
        
        .modal-content input[type="text"],
        .modal-content textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d0d5dd;
            border-radius: 6px;
            font-size: 14px;
            font-family: 'Arial', sans-serif;
        }
        
        .modal-content textarea {
            resize: vertical;
            min-height: 40px;
        }
        
        .modal-content .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
        
        .modal-content .btn-group button {
            padding: 8px 25px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            font-size: 13px;
        }
        
        .modal-content .btn-group .btn-salvar {
            background: #1a2332;
            color: #fff;
        }
        
        .modal-content .btn-group .btn-salvar:hover {
            background: #c9a84c;
        }
        
        .modal-content .btn-group .btn-cancelar {
            background: #f1f5f9;
            color: #4a5568;
        }
        
        .modal-content .btn-group .btn-cancelar:hover {
            background: #e2e8f0;
        }
        
        .modal-content .btn-group .btn-importar {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .modal-content .btn-group .btn-importar:hover {
            background: #bfdbfe;
        }
        
        .modal-content .preview-insignia {
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 10px 0;
            padding: 10px;
            background: #f8fafc;
            border-radius: 6px;
            border: 1px dashed #d0d5dd;
        }
        
        .modal-content .preview-insignia .preview-box {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            background: transparent;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .modal-content .preview-insignia .preview-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 4px;
        }
        
        .modal-content .preview-insignia .preview-label {
            font-size: 12px;
            color: #4a5568;
        }
        
        .modal-content .preview-insignia .preview-box .preview-emoji {
            font-size: 38px;
            line-height: 1;
        }
    </style>
</head>
<body>

<!-- ===== MODAL PARA EDIÇÃO ===== -->
<div class="modal-overlay" id="modalEditar">
    <div class="modal-content">
        <h3>✏️ Editar Cabeçalho Institucional</h3>
        
        <label for="edit_linha1">Linha 1 - República</label>
        <input type="text" id="edit_linha1" value="REPÚBLICA DE ANGOLA">
        
        <label for="edit_linha2">Linha 2 - Governo Provincial</label>
        <input type="text" id="edit_linha2" value="GOVERNO PROVINCIAL DO ÍCOLO E BENGO">
        
        <label for="edit_linha3">Linha 3 - Direcção Municipal</label>
        <input type="text" id="edit_linha3" value="DIRECÇÃO MUNICIPAL DA EDUCAÇÃO DE BOM JESUS">
        
        <label for="edit_linha4">Linha 4 - Nome da Escola (vindo do banco)</label>
        <input type="text" id="edit_linha4" value="<?= htmlspecialchars($nomeEscola) ?>">
        
        <label for="edit_dados_empresa">Dados da Empresa (Endereço, Telefone, Email, NIF)</label>
        <textarea id="edit_dados_empresa" rows="2"><?= htmlspecialchars($enderecoCompleto) ?> | Tel: <?= htmlspecialchars($telefone) ?> | Email: <?= htmlspecialchars($email) ?> | NIF: <?= htmlspecialchars($cnpj) ?></textarea>
        
        <label>Insígnia</label>
        <div class="preview-insignia">
            <div class="preview-box" id="previewInsignia">
                <span class="preview-emoji" id="previewInsigniaConteudo">🏛️</span>
            </div>
            <div class="preview-label">Clique no botão abaixo para importar imagem</div>
        </div>
        
        <input type="text" id="edit_insignia" placeholder="URL da imagem da insígnia ou emoji (ex: 🏛️)">
        
        <div class="btn-group">
            <button class="btn-importar" onclick="importarInsignia()">📁 Importar Imagem</button>
        </div>
        
        <input type="file" id="fileInput" accept="image/*" style="display:none" onchange="uploadInsignia(event)">
        
        <div class="btn-group" style="margin-top:15px;">
            <button class="btn-cancelar" onclick="fecharModal()">Cancelar</button>
            <button class="btn-salvar" onclick="salvarEdicao()">Salvar</button>
        </div>
    </div>
</div>

<div class="container" id="fichaContainer">
    <!-- ===== HEADER INSTITUCIONAL ===== -->
    <div class="header-institucional">
        <!-- Insígnia no centro - SEM BORDA -->
        <div class="insignia-container" onclick="abrirModal()" title="Clique para editar a insígnia">
            <div class="insignia" id="insigniaContainer">
                <span class="insignia-emoji" id="insigniaConteudo">🏛️</span>
            </div>
            <span class="edit-icon-sm">✎</span>
        </div>
        
        <!-- Linhas do cabeçalho -->
        <div class="linha1" id="linha1" onclick="abrirModal()">
            REPÚBLICA DE ANGOLA
            <span class="edit-icon">✎</span>
        </div>
        <div class="linha2" id="linha2" onclick="abrirModal()">
            GOVERNO PROVINCIAL DO ÍCOLO E BENGO
            <span class="edit-icon">✎</span>
        </div>
        <div class="linha3" id="linha3" onclick="abrirModal()">
            DIRECÇÃO MUNICIPAL DA EDUCAÇÃO DE <span>BOM JESUS</span>
            <span class="edit-icon">✎</span>
        </div>
        
        <!-- NOVA LINHA - NOME DA ESCOLA VINDO DO BANCO -->
        <div class="linha4" id="linha4" onclick="abrirModal()">
            <span><?= htmlspecialchars($nomeEscola) ?></span>
            <span class="edit-icon">✎</span>
        </div>
        
        <!-- Dados da Empresa - VINDO DO BANCO -->
        <div class="dados-empresa" id="dadosEmpresa" onclick="abrirModal()">
            <?= htmlspecialchars($enderecoCompleto) ?>
            <?php if ($telefone): ?>
            <span class="sep">|</span> Tel: <?= htmlspecialchars($telefone) ?>
            <?php endif; ?>
            <?php if ($celular && $celular != $telefone): ?>
            <span class="sep">|</span> Cel: <?= htmlspecialchars($celular) ?>
            <?php endif; ?>
            <?php if ($email): ?>
            <span class="sep">|</span> Email: <?= htmlspecialchars($email) ?>
            <?php endif; ?>
            <?php if ($cnpj): ?>
            <span class="sep">|</span> NIF: <?= htmlspecialchars($cnpj) ?>
            <?php endif; ?>
            <span class="edit-icon">✎</span>
        </div>
    </div>

    <!-- ===== HEADER COM FOTO - ESTILO MODELO ===== -->
    <div class="header-foto">
        <div class="info-aluno">
            <div class="nome-aluno">
                <?= htmlspecialchars($aluno['nome'] ?? 'Aluno') ?>
                <span style="font-size:13px;font-weight:400;color:#4a6a8e;margin-left:10px;">#<?= htmlspecialchars($aluno['id'] ?? '') ?></span>
            </div>
            <div class="detalhes-aluno">
                <span><strong>Sexo:</strong> <?= $sexoLabel ?></span>
                <span><strong>Nascimento:</strong> <?= $data_nasc ?></span>
                <span><strong>Idade:</strong> <?= $idade ?> anos</span>
                <span><strong>Natural:</strong> <?= htmlspecialchars($aluno['Naturalidade'] ?? '-') ?></span>
                <span><strong>Classe:</strong> <?= htmlspecialchars($aluno['Classe'] ?? '-') ?></span>
            </div>
        </div>
        <div class="foto-container">
            <?php if ($tem_foto): ?>
                <img src="<?= $foto_url ?>" alt="Foto de <?= htmlspecialchars($aluno['nome'] ?? '') ?>">
            <?php else: ?>
                <div class="sem-foto">👤</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== TÍTULO ===== -->
    <div class="titulo-ficha">
        <h1>📄 FICHA DE MATRÍCULA</h1>
    </div>

    <!-- ==========================================
         DADOS PESSOAIS - DUAS COLUNAS
         ========================================== -->
    <div class="section-title"><span class="icon">📌</span> Dados Pessoais</div>
    <div class="info-grid">
        <div class="info-item"><span class="label">Nº Processo:</span><span class="valor"><strong><?= htmlspecialchars($aluno['id'] ?? '-') ?></strong></span></div>
        <div class="info-item"><span class="label">Nome Completo:</span><span class="valor"><strong><?= htmlspecialchars($aluno['nome'] ?? '-') ?></strong></span></div>
        <div class="info-item"><span class="label">Sexo:</span><span class="valor"><span class="badge-sexo <?= $sexoClass ?>"><?= $sexoLabel ?></span></span></div>
        <div class="info-item"><span class="label">Data Nascimento:</span><span class="valor"><?= $data_nasc ?></span></div>
        <div class="info-item"><span class="label">Idade:</span><span class="valor"><?= $idade ?> anos</span></div>
        <div class="info-item"><span class="label">Morada:</span><span class="valor"><?= htmlspecialchars($aluno['Morada'] ?? '-') ?></span></div>
        <div class="info-item"><span class="label">Transporte:</span><span class="valor"><?= $aluno['Cadastro_Transporte'] ?? 'Não' ?></span></div>
        <div class="info-item"><span class="label">Contacto:</span><span class="valor"><?= htmlspecialchars($aluno['Contacto_do_Aluno'] ?? '-') ?></span></div>
        <div class="info-item"><span class="label">Naturalidade:</span><span class="valor"><?= htmlspecialchars($aluno['Naturalidade'] ?? '-') ?></span></div>
        <div class="info-item"><span class="label">Município:</span><span class="valor"><?= htmlspecialchars($aluno['Municipio'] ?? '-') ?></span></div>
        <div class="info-item"><span class="label">Província:</span><span class="valor"><?= htmlspecialchars($aluno['Provincia'] ?? '-') ?></span></div>
        <div class="info-item"><span class="label">Nº BI:</span><span class="valor"><?= htmlspecialchars($aluno['N_BI'] ?? '-') ?></span></div>
        <div class="info-item"><span class="label">Debilidade:</span><span class="valor"><?= htmlspecialchars($aluno['Debilidade'] ?? 'Nenhuma') ?></span></div>
        <div class="info-item"><span class="label">Ocupação do Aluno:</span><span class="valor"><?= htmlspecialchars($aluno['Ocupacao_do_Aluno'] ?? '-') ?></span></div>
        <div class="info-item"><span class="label">Data Emissão BI:</span><span class="valor"><?= $data_emissao_bi ?></span></div>
        <div class="info-item"><span class="label">Arq. Identificação:</span><span class="valor"><?= htmlspecialchars($aluno['Arq_identificacao'] ?? '-') ?></span></div>
    </div>

    <!-- ==========================================
         DADOS ESCOLARES - DUAS COLUNAS
         ========================================== -->
    <div class="section-title"><span class="icon">🏫</span> Dados Escolares</div>
    <div class="info-grid">
        <div class="info-item"><span class="label">Classe:</span><span class="valor"><?= htmlspecialchars($aluno['Classe'] ?? '-') ?></span></div>
        <div class="info-item"><span class="label">Curso:</span><span class="valor"><?= htmlspecialchars($aluno['Curso'] ?? '-') ?></span></div>
        <div class="info-item"><span class="label">Turma:</span><span class="valor"><?= htmlspecialchars($aluno['TURMA'] ?? '-') ?></span></div>
        <div class="info-item"><span class="label">Sala:</span><span class="valor"><?= htmlspecialchars($aluno['SALA'] ?? '-') ?></span></div>
        <div class="info-item"><span class="label">Período:</span><span class="valor"><?= htmlspecialchars($aluno['Periodo'] ?? '-') ?></span></div>
        <div class="info-item"><span class="label">Data Matrícula:</span><span class="valor"><?= $data_matricula ?></span></div>
        <div class="info-item"><span class="label">Situação:</span><span class="valor"><span class="badge-situacao <?= $situacaoClass ?>"><?= $situacao ?></span></span></div>
    </div>

    <!-- ==========================================
         DADOS DOS PAIS - DUAS COLUNAS
         ========================================== -->
    <div class="section-title"><span class="icon">👨‍👩‍👦</span> Dados dos Pais / Encarregados</div>
    <div class="info-grid">
        <div class="info-item"><span class="label">Nome do Pai:</span><span class="valor"><?= htmlspecialchars($aluno['Nome_do_Pai'] ?? '-') ?></span></div>
        <div class="info-item"><span class="label">Contacto do Pai:</span><span class="valor"><?= htmlspecialchars($aluno['Contacto4'] ?? '-') ?></span></div>
        <div class="info-item"><span class="label">Ocupação do Pai:</span><span class="valor"><?= htmlspecialchars($aluno['Ocupacao'] ?? '-') ?></span></div>
        <div class="info-item"><span class="label">Local de Trabalho:</span><span class="valor"><?= htmlspecialchars($aluno['Local_de_Trabalho'] ?? '-') ?></span></div>
        <div class="info-item"><span class="label">Nome da Mãe:</span><span class="valor"><?= htmlspecialchars($aluno['Nome_da_mae'] ?? '-') ?></span></div>
        <div class="info-item"><span class="label">Contacto da Mãe:</span><span class="valor"><?= htmlspecialchars($aluno['Contacto_Mae'] ?? '-') ?></span></div>
    </div>

    <!-- ==========================================
         ASSINATURAS - 3 COLUNAS
         ========================================== -->
    <div class="assinatura">
        <div>
            <div class="linha"></div>
            <div class="label">Assinatura do Aluno</div>
        </div>
        <div>
            <div class="linha"></div>
            <div class="label">Assinatura do Encarregado</div>
        </div>
        <div>
            <div class="linha"></div>
            <div class="label">Assinatura do Director</div>
        </div>
    </div>

    <!-- ===== FOOTER ===== -->
    <div class="footer">
        <p>Documento emitido por sistema autorizado • <strong><?= htmlspecialchars($nomeEscola) ?></strong></p>
        <p style="margin-top: 2px;">Data de emissão: <?= date('d/m/Y H:i:s') ?></p>
    </div>
</div>

<!-- ===== BOTÃO IMPRIMIR ===== -->
<div class="no-print" style="text-align:center;margin-top:15px;padding:12px;background:#f8fafc;border-radius:8px;max-width:210mm;margin-left:auto;margin-right:auto;">
    <button onclick="window.print()" style="padding:10px 35px;background:#c9a84c;color:#1a2332;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;box-shadow:0 2px 15px rgba(201,168,76,0.3);">
        🖨️ Imprimir / Salvar PDF
    </button>
    <button onclick="abrirModal()" style="padding:10px 25px;background:#f1f5f9;color:#4a5568;border:1px solid #d0d5dd;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;margin-left:10px;">
        ✏️ Editar Cabeçalho
    </button>
    <a href="view.php?id=<?= $aluno['id'] ?>" style="display:inline-block;margin-left:10px;padding:10px 20px;background:#f1f5f9;color:#4a5568;text-decoration:none;border-radius:8px;font-weight:600;">
        ← Voltar
    </a>
</div>

<!-- ==========================================
     JAVASCRIPT
     ========================================== -->
<script>
// ============================================
// MODAL DE EDIÇÃO
// ============================================
function abrirModal() {
    document.getElementById('modalEditar').classList.add('active');
}

function fecharModal() {
    document.getElementById('modalEditar').classList.remove('active');
}

function salvarEdicao() {
    const linha1 = document.getElementById('edit_linha1').value;
    const linha2 = document.getElementById('edit_linha2').value;
    const linha3 = document.getElementById('edit_linha3').value;
    const linha4 = document.getElementById('edit_linha4').value;
    const dadosEmpresa = document.getElementById('edit_dados_empresa').value;
    const insignia = document.getElementById('edit_insignia').value || '🏛️';
    
    document.getElementById('linha1').innerHTML = linha1 + ' <span class="edit-icon">✎</span>';
    document.getElementById('linha2').innerHTML = linha2 + ' <span class="edit-icon">✎</span>';
    document.getElementById('linha3').innerHTML = linha3 + ' <span class="edit-icon">✎</span>';
    document.getElementById('linha4').innerHTML = '<span>' + linha4 + '</span> <span class="edit-icon">✎</span>';
    document.getElementById('dadosEmpresa').innerHTML = dadosEmpresa + ' <span class="edit-icon">✎</span>';
    
    // Atualiza a insígnia
    atualizarInsignia(insignia);
    
    // Salvar no localStorage
    localStorage.setItem('ficha_linha1', linha1);
    localStorage.setItem('ficha_linha2', linha2);
    localStorage.setItem('ficha_linha3', linha3);
    localStorage.setItem('ficha_linha4', linha4);
    localStorage.setItem('ficha_dados_empresa', dadosEmpresa);
    localStorage.setItem('ficha_insignia', insignia);
    
    fecharModal();
}

function atualizarInsignia(conteudo) {
    const insigniaContainer = document.getElementById('insigniaConteudo');
    const previewContainer = document.getElementById('previewInsigniaConteudo');
    
    // Verifica se é uma URL de imagem
    if (conteudo.match(/\.(jpeg|jpg|gif|png|webp)$/i) || conteudo.startsWith('data:image') || conteudo.startsWith('http')) {
        insigniaContainer.innerHTML = '<img src="' + conteudo + '" alt="Insígnia">';
        previewContainer.innerHTML = '<img src="' + conteudo + '" alt="Insígnia">';
        previewContainer.style.display = 'block';
    } else {
        // É um emoji ou texto
        insigniaContainer.innerHTML = conteudo;
        insigniaContainer.className = 'insignia-emoji';
        previewContainer.innerHTML = conteudo;
        previewContainer.className = 'preview-emoji';
    }
}

function importarInsignia() {
    document.getElementById('fileInput').click();
}

function uploadInsignia(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    const reader = new FileReader();
    reader.onload = function(e) {
        const imageUrl = e.target.result;
        document.getElementById('edit_insignia').value = imageUrl;
        atualizarInsignia(imageUrl);
    };
    reader.readAsDataURL(file);
}

// Carregar dados salvos
document.addEventListener('DOMContentLoaded', function() {
    const linha1Salva = localStorage.getItem('ficha_linha1');
    const linha2Salva = localStorage.getItem('ficha_linha2');
    const linha3Salva = localStorage.getItem('ficha_linha3');
    const linha4Salva = localStorage.getItem('ficha_linha4');
    const dadosEmpresaSalva = localStorage.getItem('ficha_dados_empresa');
    const insigniaSalva = localStorage.getItem('ficha_insignia');
    
    if (linha1Salva) {
        document.getElementById('linha1').innerHTML = linha1Salva + ' <span class="edit-icon">✎</span>';
        document.getElementById('edit_linha1').value = linha1Salva;
    }
    
    if (linha2Salva) {
        document.getElementById('linha2').innerHTML = linha2Salva + ' <span class="edit-icon">✎</span>';
        document.getElementById('edit_linha2').value = linha2Salva;
    }
    
    if (linha3Salva) {
        document.getElementById('linha3').innerHTML = linha3Salva + ' <span class="edit-icon">✎</span>';
        document.getElementById('edit_linha3').value = linha3Salva;
    }
    
    if (linha4Salva) {
        document.getElementById('linha4').innerHTML = '<span>' + linha4Salva + '</span> <span class="edit-icon">✎</span>';
        document.getElementById('edit_linha4').value = linha4Salva;
    }
    
    if (dadosEmpresaSalva) {
        document.getElementById('dadosEmpresa').innerHTML = dadosEmpresaSalva + ' <span class="edit-icon">✎</span>';
        document.getElementById('edit_dados_empresa').value = dadosEmpresaSalva;
    }
    
    if (insigniaSalva) {
        document.getElementById('edit_insignia').value = insigniaSalva;
        atualizarInsignia(insigniaSalva);
    }
});

// Fechar modal com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        fecharModal();
    }
});

// Fechar modal clicando fora
document.getElementById('modalEditar').addEventListener('click', function(e) {
    if (e.target === this) {
        fecharModal();
    }
});
</script>

</body>
</html>