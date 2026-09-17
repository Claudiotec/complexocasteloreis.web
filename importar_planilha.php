<?php
// ============================================
// importar_planilha.php - Importar dados do Excel
// ============================================

require_once '../../../config/database.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

$erro = '';
$sucesso = '';
$importados = 0;
$ignorados = 0;

// Verificar se o arquivo foi enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['arquivo'])) {
    $arquivo = $_FILES['arquivo'];
    
    // Verificar extensão
    $extensao = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
    $extensoes_validas = ['xlsx', 'xls', 'csv'];
    
    if (!in_array(strtolower($extensao), $extensoes_validas)) {
        $erro = 'Formato de arquivo inválido. Use .xlsx, .xls ou .csv';
    } else {
        try {
            // Carregar o arquivo
            require_once '../../../vendor/autoload.php'; // Para PhpSpreadsheet
            
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($arquivo['tmp_name']);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            // Pular cabeçalho (linha 0)
            array_shift($rows);
            
            $pdo->beginTransaction();
            
            foreach ($rows as $row) {
                // Verificar se a linha tem dados
                if (empty($row[0]) && empty($row[1])) continue;
                
                // Mapear colunas (baseado na planilha)
                $dados = [
                    'id' => $row[0] ?? null,
                    'nome' => $row[1] ?? null,
                    'Sexo' => $row[2] ?? null,
                    'dia' => $row[3] ?? null,
                    'mes' => $row[4] ?? null,
                    'Ano' => $row[5] ?? null,
                    'Morada' => $row[6] ?? null,
                    'Cadastro_Transporte' => $row[7] ?? null,
                    'Contacto_do_Aluno' => $row[8] ?? null,
                    'Debilidade' => $row[9] ?? null,
                    'Idade' => $row[10] ?? null,
                    'Naturalidade' => $row[11] ?? null,
                    'Município' => $row[12] ?? null,
                    'Província' => $row[13] ?? null,
                    'N_BI' => $row[14] ?? null,
                    'Classe' => $row[15] ?? null,
                    'Nome_do_Pai' => $row[16] ?? null,
                    'Morada3' => $row[17] ?? null,
                    'Contacto4' => $row[18] ?? null,
                    'Ocupacao' => $row[19] ?? null,
                    'Local_de_Trabalho' => $row[20] ?? null,
                    'Nome_da_mae' => $row[21] ?? null,
                    'Contacto_Mae' => $row[22] ?? null,
                    'Data_Matricula' => $row[23] ?? null,
                    'Ocupacao_do_Aluno' => $row[24] ?? null,
                    'Periodo' => $row[25] ?? null,
                    'Data_Emissao_do_BI' => $row[26] ?? null,
                    'Arq_identificação' => $row[27] ?? null,
                    'Situacao_Cadastro' => $row[28] ?? null,
                    'TURMA' => $row[29] ?? null,
                    'SALA' => $row[30] ?? null,
                    'Coluna1' => $row[31] ?? null,
                    'Curso' => $row[32] ?? null,
                ];
                
                // Pular linhas vazias
                if (empty($dados['nome']) && empty($dados['id'])) continue;
                
                // Verificar se já existe
                $stmt_check = $pdo->prepare("SELECT id FROM alunos_ano_anterior WHERE id = ? OR (nome = ? AND Data_Matricula = ?)");
                $stmt_check->execute([
                    $dados['id'],
                    $dados['nome'],
                    $dados['Data_Matricula']
                ]);
                
                if ($stmt_check->rowCount() > 0) {
                    $ignorados++;
                    continue;
                }
                
                // Inserir
                $stmt = $pdo->prepare("
                    INSERT INTO alunos_ano_anterior (
                        id, nome, Sexo, dia, mes, Ano, Morada, 
                        Cadastro_Transporte, Contacto_do_Aluno, Debilidade,
                        Idade, Naturalidade, Município, Província, N_BI,
                        Classe, Nome_do_Pai, Morada3, Contacto4, Ocupacao,
                        Local_de_Trabalho, Nome_da_mae, Contacto_Mae,
                        Data_Matricula, Ocupacao_do_Aluno, Periodo,
                        Data_Emissao_do_BI, Arq_identificação, Situacao_Cadastro,
                        TURMA, SALA, Coluna1, Curso,
                        data_cadastro, usuario_cadastro, ano_letivo
                    ) VALUES (
                        :id, :nome, :Sexo, :dia, :mes, :Ano, :Morada,
                        :Cadastro_Transporte, :Contacto_do_Aluno, :Debilidade,
                        :Idade, :Naturalidade, :Municipio, :Provincia, :N_BI,
                        :Classe, :Nome_do_Pai, :Morada3, :Contacto4, :Ocupacao,
                        :Local_de_Trabalho, :Nome_da_mae, :Contacto_Mae,
                        :Data_Matricula, :Ocupacao_do_Aluno, :Periodo,
                        :Data_Emissao_do_BI, :Arq_identificacao, :Situacao_Cadastro,
                        :TURMA, :SALA, :Coluna1, :Curso,
                        NOW(), :usuario_cadastro, :ano_letivo
                    )
                ");
                
                $stmt->execute([
                    ':id' => $dados['id'],
                    ':nome' => $dados['nome'],
                    ':Sexo' => $dados['Sexo'],
                    ':dia' => $dados['dia'],
                    ':mes' => $dados['mes'],
                    ':Ano' => $dados['Ano'],
                    ':Morada' => $dados['Morada'],
                    ':Cadastro_Transporte' => $dados['Cadastro_Transporte'],
                    ':Contacto_do_Aluno' => $dados['Contacto_do_Aluno'],
                    ':Debilidade' => $dados['Debilidade'],
                    ':Idade' => $dados['Idade'],
                    ':Naturalidade' => $dados['Naturalidade'],
                    ':Municipio' => $dados['Município'],
                    ':Provincia' => $dados['Província'],
                    ':N_BI' => $dados['N_BI'],
                    ':Classe' => $dados['Classe'],
                    ':Nome_do_Pai' => $dados['Nome_do_Pai'],
                    ':Morada3' => $dados['Morada3'],
                    ':Contacto4' => $dados['Contacto4'],
                    ':Ocupacao' => $dados['Ocupacao'],
                    ':Local_de_Trabalho' => $dados['Local_de_Trabalho'],
                    ':Nome_da_mae' => $dados['Nome_da_mae'],
                    ':Contacto_Mae' => $dados['Contacto_Mae'],
                    ':Data_Matricula' => $dados['Data_Matricula'],
                    ':Ocupacao_do_Aluno' => $dados['Ocupacao_do_Aluno'],
                    ':Periodo' => $dados['Periodo'],
                    ':Data_Emissao_do_BI' => $dados['Data_Emissao_do_BI'],
                    ':Arq_identificacao' => $dados['Arq_identificação'],
                    ':Situacao_Cadastro' => $dados['Situacao_Cadastro'] ?? 'Matrícula',
                    ':TURMA' => $dados['TURMA'],
                    ':SALA' => $dados['SALA'],
                    ':Coluna1' => $dados['Coluna1'],
                    ':Curso' => $dados['Curso'],
                    ':usuario_cadastro' => $_SESSION['usuario_id'],
                    ':ano_letivo' => date('Y')
                ]);
                
                $importados++;
            }
            
            $pdo->commit();
            $sucesso = "Importação concluída! $importados alunos importados, $ignorados ignorados (já existentes).";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $erro = 'Erro ao importar: ' . $e->getMessage();
        }
    }
}

include '../includes/header_escola.php';
?>

<style>
    .upload-area {
        border: 2px dashed #c9a84c;
        border-radius: 12px;
        padding: 40px;
        text-align: center;
        background: #fafbfc;
        transition: all 0.3s;
        margin: 20px 0;
    }
    
    .upload-area:hover {
        background: #f8f5eb;
        border-color: #b8973d;
    }
    
    .upload-area .icon {
        font-size: 48px;
        display: block;
        margin-bottom: 15px;
    }
    
    .upload-area h3 {
        color: #1a2332;
        margin: 0 0 5px;
    }
    
    .upload-area p {
        color: #94a3b8;
        margin: 0 0 15px;
    }
    
    .btn-upload {
        background: #c9a84c;
        color: #1a2332;
        padding: 10px 30px;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .btn-upload:hover {
        background: #b8973d;
        transform: translateY(-2px);
    }
    
    .btn-upload:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    .file-input-wrapper {
        display: inline-block;
        position: relative;
        overflow: hidden;
    }
    
    .file-input-wrapper input[type="file"] {
        position: absolute;
        left: 0;
        top: 0;
        opacity: 0;
        width: 100%;
        height: 100%;
        cursor: pointer;
    }
</style>

<div class="page-header">
    <div>
        <h1>📤 Importar Alunos do Ano Anterior</h1>
        <p class="subtitle">Importar dados da planilha para a tabela de alunos do ano anterior</p>
    </div>
    <a href="reconfirmar.php" class="btn btn-secondary">← Voltar</a>
</div>

<?php if ($sucesso): ?>
    <div class="alert alert-success">✅ <?= $sucesso ?></div>
<?php endif; ?>

<?php if ($erro): ?>
    <div class="alert alert-error">❌ <?= $erro ?></div>
<?php endif; ?>

<div class="upload-area">
    <span class="icon">📊</span>
    <h3>Importar Planilha Excel</h3>
    <p>Selecione o arquivo <strong>cadastro_alunos.xlsx</strong> para importar os dados</p>
    
    <form method="POST" enctype="multipart/form-data">
        <div class="file-input-wrapper">
            <button type="button" class="btn-upload">📁 Escolher Arquivo</button>
            <input type="file" name="arquivo" accept=".xlsx,.xls,.csv" required>
        </div>
        <br><br>
        <button type="submit" class="btn btn-primary" style="font-size: 16px; padding: 10px 40px;">
            ⬆️ Importar Dados
        </button>
    </form>
    
    <div style="margin-top: 20px; padding: 15px; background: #f8fafc; border-radius: 8px; text-align: left;">
        <p style="margin: 0; font-size: 13px; color: #4a5568;">
            <strong>📋 Estrutura esperada da planilha:</strong>
            <br>
            As colunas devem seguir a ordem da planilha original:
            <br>
            <span style="font-size: 12px; color: #94a3b8;">
                id, nome, Sexo, dia, mes, Ano, Morada, Cadastro_Transporte, Contacto_do_Aluno, 
                Debilidade, Idade, Naturalidade, Município, Província, N_BI, Classe, 
                Nome_do_Pai, Morada3, Contacto4, Ocupacao, Local_de_Trabalho, 
                Nome_da_mae, Contacto_Mae, Data_Matricula, Ocupacao_do_Aluno, Periodo, 
                Data_Emissao_do_BI, Arq_identificação, Situacao_Cadastro, TURMA, SALA, 
                Coluna1, Curso
            </span>
        </p>
    </div>
</div>

<?php include '../includes/footer_escola.php'; ?>