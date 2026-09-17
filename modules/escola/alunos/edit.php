<?php
// ============================================
// modules/escola/alunos/edit.php - Editar Aluno
// ============================================

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'editar')) {
    header('Location: ' . SITE_URL);
    exit;
}

$id = $_GET['id'] ?? 0;
$aluno = null;

if ($id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM alunos WHERE id = ?");
        $stmt->execute([$id]);
        $aluno = $stmt->fetch();
    } catch (Exception $e) {}
}

if (!$aluno) {
    header('Location: index.php');
    exit;
}

$CLASSES_PRE_DEFINIDAS = [
    'PRÉ', '1ª', '2ª', '3ª', '4ª', '5ª', '6ª', 
    '7ª', '8ª', '9ª', '10ª', '11ª', '12ª'
];

$erro = '';
$sucesso = '';

// Configuração de upload
$UPLOAD_DIR = '../../../uploads/alunos/';
$MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
$ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

// Criar diretório se não existir
if (!is_dir($UPLOAD_DIR)) {
    mkdir($UPLOAD_DIR, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = $_POST['nome'] ?? '';
    $sexo = $_POST['sexo'] ?? 'M';
    $dia = $_POST['dia'] ?? 0;
    $mes = $_POST['mes'] ?? 0;
    $ano = $_POST['ano'] ?? 0;
    $morada = $_POST['morada'] ?? '';
    $transporte = $_POST['transporte'] ?? 'Não';
    $contacto = $_POST['contacto'] ?? '';
    $debilidade = $_POST['debilidade'] ?? '';
    $naturalidade = $_POST['naturalidade'] ?? '';
    $municipio = $_POST['municipio'] ?? '';
    $provincia = $_POST['provincia'] ?? '';
    $bi = $_POST['bi'] ?? '';
    $classe = $_POST['classe'] ?? '';
    $curso = $_POST['curso'] ?? '';
    $nome_pai = $_POST['nome_pai'] ?? '';
    $contacto_pai = $_POST['contacto_pai'] ?? '';
    $ocupacao_pai = $_POST['ocupacao_pai'] ?? '';
    $local_trabalho = $_POST['local_trabalho'] ?? '';
    $nome_mae = $_POST['nome_mae'] ?? '';
    $contacto_mae = $_POST['contacto_mae'] ?? '';
    $data_matricula = $_POST['data_matricula'] ?? date('Y-m-d');
    $ocupacao_aluno = $_POST['ocupacao_aluno'] ?? '';
    $data_emissao_bi = $_POST['data_emissao_bi'] ?? '';
    $arq_identificacao = $_POST['arq_identificacao'] ?? '';
    $situacao = $_POST['situacao'] ?? 'Matrícula';
    $turma = $_POST['turma'] ?? '';
    $sala = $_POST['sala'] ?? '';
    $periodo = $_POST['periodo'] ?? '';
    $foto_atual = $_POST['foto_atual'] ?? '';

    // Processar upload da foto
    $foto_nome = $foto_atual; // Mantém a foto atual por padrão
    
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['foto'];
        
        // Validar tamanho
        if ($file['size'] > $MAX_FILE_SIZE) {
            $erro = 'A foto não pode ter mais que 5MB.';
        }
        // Validar tipo
        elseif (!in_array($file['type'], $ALLOWED_TYPES)) {
            $erro = 'Formato de imagem não permitido. Use JPG, PNG, GIF ou WEBP.';
        }
        else {
            // Gerar nome único para a foto
            $extensao = pathinfo($file['name'], PATHINFO_EXTENSION);
            $foto_nome = 'aluno_' . $id . '_' . time() . '.' . $extensao;
            $caminho_completo = $UPLOAD_DIR . $foto_nome;
            
            // Remover foto antiga se existir
            if (!empty($aluno['foto']) && file_exists($UPLOAD_DIR . $aluno['foto'])) {
                unlink($UPLOAD_DIR . $aluno['foto']);
            }
            
            // Mover arquivo
            if (move_uploaded_file($file['tmp_name'], $caminho_completo)) {
                // Redimensionar imagem (opcional)
                // Aqui você pode adicionar código para redimensionar a imagem
            } else {
                $erro = 'Erro ao fazer upload da foto.';
            }
        }
    }

    if (empty($erro) && (empty($nome) || empty($classe) || empty($curso))) {
        $erro = 'Preencha todos os campos obrigatórios!';
    }

    if (empty($erro)) {
        try {
            $idade = date('Y') - $ano;
            if ($idade < 0) $idade = 0;

            $stmt = $pdo->prepare("
                UPDATE alunos SET 
                    nome = ?, Sexo = ?, dia = ?, mes = ?, Ano = ?, Idade = ?,
                    Morada = ?, Cadastro_Transporte = ?, Contacto_do_Aluno = ?,
                    Debilidade = ?, Naturalidade = ?, Municipio = ?, Provincia = ?,
                    N_BI = ?, Classe = ?, Curso = ?, Nome_do_Pai = ?,
                    Contacto4 = ?, Ocupacao = ?, Local_de_Trabalho = ?,
                    Nome_da_mae = ?, Contacto_Mae = ?, Data_Matricula = ?,
                    Ocupacao_do_Aluno = ?, Periodo = ?, Data_Emissao_do_BI = ?,
                    Arq_identificacao = ?, Situacao_Cadastro = ?, TURMA = ?, SALA = ?,
                    foto = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $nome, $sexo, $dia, $mes, $ano, $idade,
                $morada, $transporte, $contacto,
                $debilidade, $naturalidade, $municipio, $provincia,
                $bi, $classe, $curso, $nome_pai,
                $contacto_pai, $ocupacao_pai, $local_trabalho,
                $nome_mae, $contacto_mae, $data_matricula,
                $ocupacao_aluno, $periodo, $data_emissao_bi,
                $arq_identificacao, $situacao, $turma, $sala,
                $foto_nome,
                $id
            ]);
            
            $sucesso = 'Aluno atualizado com sucesso!';
            
            // Recarregar dados
            $stmt = $pdo->prepare("SELECT * FROM alunos WHERE id = ?");
            $stmt->execute([$id]);
            $aluno = $stmt->fetch();
        } catch (Exception $e) {
            $erro = $e->getMessage();
        }
    }
}

include '../includes/header_escola.php';
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .page-header h1 {
        font-size: 24px;
        font-weight: 700;
        color: #1a2332;
        margin: 0;
    }
    
    .subtitle {
        color: #64748b;
        font-size: 14px;
        margin: 5px 0 0 0;
    }
    
    .btn {
        padding: 8px 20px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: none;
        cursor: pointer;
    }
    
    .btn-secondary {
        background: #f1f5f9;
        color: #4a5568;
    }
    
    .btn-secondary:hover {
        background: #e2e8f0;
    }
    
    .btn-primary {
        background: #c9a84c;
        color: #1a2332;
    }
    
    .btn-primary:hover {
        background: #b8973a;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(201,168,76,0.3);
    }
    
    .btn-danger {
        background: #ef4444;
        color: #fff;
    }
    
    .btn-danger:hover {
        background: #dc2626;
    }
    
    .form-container {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #eef2f7;
        max-width: 900px;
    }
    
    .form-section {
        margin-bottom: 20px;
    }
    
    .form-section-title {
        font-size: 16px;
        font-weight: 700;
        color: #1a2332;
        padding-bottom: 8px;
        border-bottom: 2px solid #eef2f7;
        margin-bottom: 15px;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 10px;
    }
    
    .form-row-3 {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 15px;
        margin-bottom: 10px;
    }
    
    .form-group {
        margin-bottom: 10px;
    }
    
    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 4px;
        color: #1a2332;
        font-size: 12px;
    }
    
    .form-group label .required {
        color: #e74c3c;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 13px;
        transition: border-color 0.3s;
        font-family: inherit;
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #c9a84c;
        box-shadow: 0 0 0 3px rgba(201,168,76,0.1);
    }
    
    .form-group textarea {
        min-height: 50px;
        resize: vertical;
    }
    
    .form-group input[type="file"] {
        padding: 6px;
        cursor: pointer;
    }
    
    .form-group input[type="file"]::-webkit-file-upload-button {
        background: #c9a84c;
        color: #1a2332;
        padding: 6px 15px;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        margin-right: 10px;
        transition: background 0.3s;
    }
    
    .form-group input[type="file"]::-webkit-file-upload-button:hover {
        background: #b8973a;
    }
    
    .form-group input[type="file"]::file-selector-button {
        background: #c9a84c;
        color: #1a2332;
        padding: 6px 15px;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        margin-right: 10px;
        transition: background 0.3s;
    }
    
    .form-group input[type="file"]::file-selector-button:hover {
        background: #b8973a;
    }
    
    .foto-preview {
        display: flex;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
    }
    
    .foto-preview img {
        width: 120px;
        height: 120px;
        object-fit: cover;
        border-radius: 50%;
        border: 3px solid #eef2f7;
    }
    
    .foto-preview .foto-info {
        font-size: 13px;
        color: #64748b;
    }
    
    .foto-preview .foto-info .btn {
        margin-top: 8px;
    }
    
    .foto-upload-area {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
    }
    
    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    
    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    
    .form-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
        flex-wrap: wrap;
    }
    
    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
            gap: 0;
        }
        .form-row-3 {
            grid-template-columns: 1fr;
            gap: 0;
        }
        .form-container {
            padding: 15px;
        }
        .page-header {
            flex-direction: column;
            align-items: stretch;
        }
        .form-actions {
            flex-direction: column;
        }
        .form-actions .btn {
            justify-content: center;
        }
        .foto-preview {
            flex-direction: column;
            text-align: center;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>✏️ Editar Aluno</h1>
        <p class="subtitle">Atualize os dados do aluno</p>
    </div>
    <a href="index.php" class="btn btn-secondary">← Voltar</a>
</div>

<?php if ($sucesso): ?>
    <div class="alert alert-success">✅ <?= $sucesso ?></div>
<?php endif; ?>

<?php if ($erro): ?>
    <div class="alert alert-error">❌ <?= $erro ?></div>
<?php endif; ?>

<div class="form-container">
    <form method="POST" enctype="multipart/form-data">
        <!-- Dados Pessoais -->
        <div class="form-section">
            <div class="form-section-title">📌 Dados Pessoais</div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Nº Processo</label>
                    <input type="text" value="<?= htmlspecialchars($aluno['id'] ?? '') ?>" disabled style="background: #f1f5f9;">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($aluno['id'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Nome Completo <span class="required">*</span></label>
                    <input type="text" name="nome" value="<?= htmlspecialchars($aluno['nome'] ?? '') ?>" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Sexo <span class="required">*</span></label>
                    <select name="sexo" required>
                        <option value="M" <?= ($aluno['Sexo'] ?? '') == 'M' ? 'selected' : '' ?>>Masculino</option>
                        <option value="F" <?= ($aluno['Sexo'] ?? '') == 'F' ? 'selected' : '' ?>>Feminino</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Data de Nascimento <span class="required">*</span></label>
                    <div class="form-row-3" style="gap: 5px; margin-bottom: 0;">
                        <input type="number" name="dia" placeholder="Dia" min="1" max="31" value="<?= $aluno['dia'] ?? 0 ?>" required>
                        <input type="number" name="mes" placeholder="Mês" min="1" max="12" value="<?= $aluno['mes'] ?? 0 ?>" required>
                        <input type="number" name="ano" placeholder="Ano" min="1900" max="<?= date('Y') ?>" value="<?= $aluno['Ano'] ?? 0 ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Idade</label>
                    <input type="text" value="<?= $aluno['Idade'] ?? 0 ?>" disabled style="background: #f1f5f9;">
                </div>
                <div class="form-group">
                    <label>Morada</label>
                    <input type="text" name="morada" value="<?= htmlspecialchars($aluno['Morada'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Transporte</label>
                    <select name="transporte">
                        <option value="Não" <?= ($aluno['Cadastro_Transporte'] ?? '') == 'Não' ? 'selected' : '' ?>>Não</option>
                        <option value="Sim" <?= ($aluno['Cadastro_Transporte'] ?? '') == 'Sim' ? 'selected' : '' ?>>Sim</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Contacto <span class="required">*</span></label>
                    <input type="tel" name="contacto" value="<?= htmlspecialchars($aluno['Contacto_do_Aluno'] ?? '') ?>" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Naturalidade</label>
                    <input type="text" name="naturalidade" value="<?= htmlspecialchars($aluno['Naturalidade'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Debilidade</label>
                    <input type="text" name="debilidade" value="<?= htmlspecialchars($aluno['Debilidade'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Município</label>
                    <input type="text" name="municipio" value="<?= htmlspecialchars($aluno['Municipio'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Província</label>
                    <input type="text" name="provincia" value="<?= htmlspecialchars($aluno['Provincia'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Nº BI</label>
                    <input type="text" name="bi" value="<?= htmlspecialchars($aluno['N_BI'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Data Emissão BI</label>
                    <input type="date" name="data_emissao_bi" value="<?= htmlspecialchars($aluno['Data_Emissao_do_BI'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Ocupação do Aluno</label>
                    <input type="text" name="ocupacao_aluno" value="<?= htmlspecialchars($aluno['Ocupacao_do_Aluno'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Arquivo Identificação</label>
                    <input type="text" name="arq_identificacao" value="<?= htmlspecialchars($aluno['Arq_identificacao'] ?? '') ?>">
                </div>
            </div>

            <!-- Campo de Foto -->
            <div class="form-section" style="margin-top: 15px;">
                <div class="form-section-title">📸 Foto do Aluno</div>
                
                <div class="form-group">
                    <div class="foto-preview">
                        <?php if (!empty($aluno['foto']) && file_exists($UPLOAD_DIR . $aluno['foto'])): ?>
                            <img src="../../../uploads/alunos/<?= htmlspecialchars($aluno['foto']) ?>" alt="Foto do aluno">
                            <div class="foto-info">
                                <p><strong>Foto atual:</strong> <?= htmlspecialchars($aluno['foto']) ?></p>
                                <button type="button" class="btn btn-danger" onclick="removerFoto(<?= $aluno['id'] ?>)">
                                    🗑️ Remover Foto
                                </button>
                            </div>
                            <input type="hidden" name="foto_atual" value="<?= htmlspecialchars($aluno['foto']) ?>">
                        <?php else: ?>
                            <div class="foto-info">
                                <p style="color: #94a3b8;">Sem foto cadastrada</p>
                            </div>
                            <input type="hidden" name="foto_atual" value="">
                        <?php endif; ?>
                    </div>
                    
                    <div class="foto-upload-area" style="margin-top: 10px;">
                        <label style="font-size: 13px; color: #64748b;">
                            <strong>Alterar foto:</strong> (JPG, PNG, GIF, WEBP - Máx. 5MB)
                        </label>
                        <input type="file" name="foto" accept="image/*">
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Dados Escolares -->
        <div class="form-section">
            <div class="form-section-title">🏫 Dados Escolares</div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Curso <span class="required">*</span></label>
                    <input type="text" name="curso" value="<?= htmlspecialchars($aluno['Curso'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Classe <span class="required">*</span></label>
                    <select name="classe" required>
                        <option value="">Selecione uma classe</option>
                        <?php foreach($CLASSES_PRE_DEFINIDAS as $classe): ?>
                        <option value="<?= $classe ?>" <?= ($aluno['Classe'] ?? '') == $classe ? 'selected' : '' ?>>
                            <?= $classe ?> Classe
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Turma</label>
                    <input type="text" name="turma" value="<?= htmlspecialchars($aluno['TURMA'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Sala</label>
                    <input type="text" name="sala" value="<?= htmlspecialchars($aluno['SALA'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Período</label>
                    <input type="text" name="periodo" value="<?= htmlspecialchars($aluno['Periodo'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Data Matrícula</label>
                    <input type="date" name="data_matricula" value="<?= htmlspecialchars($aluno['Data_Matricula'] ?? date('Y-m-d')) ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>Situação</label>
                <select name="situacao">
                    <option value="Matrícula" <?= ($aluno['Situacao_Cadastro'] ?? '') == 'Matrícula' ? 'selected' : '' ?>>Matrícula</option>
                    <option value="Confirmação" <?= ($aluno['Situacao_Cadastro'] ?? '') == 'Confirmação' ? 'selected' : '' ?>>Confirmação</option>
                </select>
            </div>
        </div>
        
        <!-- Dados dos Pais -->
        <div class="form-section">
            <div class="form-section-title">👨‍👩‍👦 Dados dos Pais</div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Nome do Pai</label>
                    <input type="text" name="nome_pai" value="<?= htmlspecialchars($aluno['Nome_do_Pai'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Contacto do Pai</label>
                    <input type="tel" name="contacto_pai" value="<?= htmlspecialchars($aluno['Contacto4'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Ocupação do Pai</label>
                    <input type="text" name="ocupacao_pai" value="<?= htmlspecialchars($aluno['Ocupacao'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Local de Trabalho</label>
                    <input type="text" name="local_trabalho" value="<?= htmlspecialchars($aluno['Local_de_Trabalho'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Nome da Mãe</label>
                    <input type="text" name="nome_mae" value="<?= htmlspecialchars($aluno['Nome_da_mae'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Contacto da Mãe</label>
                    <input type="tel" name="contacto_mae" value="<?= htmlspecialchars($aluno['Contacto_Mae'] ?? '') ?>">
                </div>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Atualizar Aluno</button>
            <a href="view.php?id=<?= $aluno['id'] ?>" class="btn btn-secondary">Ver Detalhes</a>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script>
function removerFoto(id) {
    if (confirm('Tem certeza que deseja remover a foto do aluno?')) {
        // Criar formulário para remover foto
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = 'remover_foto.php';
        
        var inputId = document.createElement('input');
        inputId.type = 'hidden';
        inputId.name = 'id';
        inputId.value = id;
        form.appendChild(inputId);
        
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include '../includes/footer_escola.php'; ?>