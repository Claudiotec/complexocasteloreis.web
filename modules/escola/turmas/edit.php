<?php
// Configuração de conexão com o banco de dados
$host = 'localhost';
$dbname = 'softgest_db'; // Ajuste para o nome do seu banco
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}

// Inicializa variáveis
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$mensagem = '';
$erro = '';

// Busca os dados da turma
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM turmas WHERE id = ?");
    $stmt->execute([$id]);
    $turma = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$turma) {
        $erro = "Turma não encontrada!";
    }
} else {
    $erro = "ID inválido!";
}

// Busca lista de professores para o select
$professores = [];
try {
    $stmt = $pdo->query("SELECT id, nome FROM professores ORDER BY nome");
    $professores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Se a tabela professores não existir, continua sem erro
}

// Processa o formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar'])) {
    // Mapeamento correto dos campos
    $nome = $_POST['nome'] ?? '';
    $classe = $_POST['classe'] ?? '';
    $curso = $_POST['curso'] ?? '';
    $ano_letivo = $_POST['ano_letivo'] ?? date('Y');
    $turno = $_POST['turno'] ?? 'manha';
    $sala = $_POST['sala'] ?? '';
    $capacidade = intval($_POST['capacidade'] ?? 0);
    $limite = intval($_POST['limite'] ?? 0);
    $idades = $_POST['idades'] ?? '';
    $disciplinas = $_POST['disciplinas'] ?? '';
    $professor_id = $_POST['professor_id'] ?? '';
    $status = $_POST['status'] ?? 'ativa';
    $descricao = $_POST['descricao'] ?? '';
    
    // Validação básica
    if (empty($nome) || empty($classe) || empty($curso)) {
        $mensagem = '<div class="alert alert-error">❌ Preencha os campos obrigatórios: Nome da Turma, Classe e Curso.</div>';
    } else {
        try {
            $sql = "UPDATE turmas SET 
                        nome = ?, 
                        classe = ?, 
                        curso = ?, 
                        ano_letivo = ?,
                        turno = ?, 
                        sala = ?, 
                        capacidade = ?,
                        limite = ?,
                        idades = ?, 
                        disciplinas = ?,
                        professor_id = ?,
                        status = ?,
                        descricao = ?
                    WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $nome, 
                $classe, 
                $curso, 
                $ano_letivo,
                $turno, 
                $sala, 
                $capacidade,
                $limite,
                $idades, 
                $disciplinas,
                $professor_id,
                $status,
                $descricao,
                $id
            ]);
            
            $mensagem = '<div class="alert alert-success">✅ Turma atualizada com sucesso!</div>';
            
            // Recarrega os dados atualizados
            $stmt = $pdo->prepare("SELECT * FROM turmas WHERE id = ?");
            $stmt->execute([$id]);
            $turma = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $mensagem = '<div class="alert alert-error">❌ Erro ao atualizar: ' . $e->getMessage() . '</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SoftGest - Editar Turma</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            color: #333;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e8ecf1;
        }
        
        .header h1 {
            font-size: 24px;
            color: #2c3e50;
        }
        
        .header h1 span {
            color: #3498db;
        }
        
        .btn-back {
            background: #ecf0f1;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            color: #2c3e50;
            font-weight: 500;
            transition: background 0.3s;
        }
        
        .btn-back:hover {
            background: #d5dbdb;
        }
        
        .alert {
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: #2c3e50;
            font-size: 14px;
        }
        
        .form-group label .required {
            color: #e74c3c;
        }
        
        .form-group .help-text {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 4px;
            display: block;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #dce1e8;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
            background: #fafbfc;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.15);
            background: #fff;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 60px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #2c3e50;
            margin: 25px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #e8ecf1;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title .emoji {
            font-size: 20px;
        }
        
        .info-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #3498db;
        }
        
        .info-box strong {
            color: #2c3e50;
        }
        
        .info-box .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-ativa {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-concluída {
            background: #cce5ff;
            color: #004085;
        }
        
        .badge-cancelada {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-inativa {
            background: #f8d7da;
            color: #721c24;
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #e8ecf1;
        }
        
        .btn {
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #3498db;
            color: #fff;
        }
        
        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }
        
        .btn-secondary {
            background: #ecf0f1;
            color: #2c3e50;
        }
        
        .btn-secondary:hover {
            background: #d5dbdb;
        }
        
        .btn-success {
            background: #27ae60;
            color: #fff;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .btn-sm {
            padding: 6px 14px;
            font-size: 12px;
        }
        
        .status-select {
            font-weight: 600;
        }
        
        .status-select option[value="ativa"] { color: #27ae60; }
        .status-select option[value="concluída"] { color: #2980b9; }
        .status-select option[value="cancelada"] { color: #e74c3c; }
        .status-select option[value="inativa"] { color: #e74c3c; }
        
        @media (max-width: 600px) {
            .form-row,
            .form-row-3 {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 15px;
            }
            
            .header {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>✏️ Editar Turma <span>#<?php echo $id; ?></span></h1>
            <a href="javascript:history.back()" class="btn-back">← Voltar</a>
        </div>
        
        <?php if ($erro): ?>
            <div class="alert alert-error">❌ <?php echo $erro; ?></div>
        <?php endif; ?>
        
        <?php echo $mensagem; ?>
        
        <?php if ($turma && !$erro): ?>
            <!-- Informações da Turma -->
            <div class="info-box">
                <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <strong>📚 Turma:</strong> <?php echo htmlspecialchars($turma['nome'] ?? ''); ?>
                    </div>
                    <div>
                        <strong>📊 Status:</strong>
                        <span class="badge badge-<?php echo strtolower($turma['status'] ?? 'ativa'); ?>">
                            <?php echo htmlspecialchars($turma['status'] ?? 'Ativa'); ?>
                        </span>
                    </div>
                    <div>
                        <strong>👨‍🎓 Alunos:</strong> <?php echo htmlspecialchars($turma['alunos'] ?? 0); ?>
                    </div>
                </div>
            </div>
            
            <!-- Formulário -->
            <form method="POST">
                <!-- Dados da Turma -->
                <div class="section-title">
                    <span class="emoji">📌</span> Dados da Turma
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="nome">Nome da Turma <span class="required">*</span></label>
                        <input type="text" id="nome" name="nome" 
                               value="<?php echo htmlspecialchars($turma['nome'] ?? ''); ?>" 
                               placeholder="Ex: 1AM, 2BM" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="classe">Classe <span class="required">*</span></label>
                        <select id="classe" name="classe" required>
                            <option value="">Selecione uma classe</option>
                            <option value="1ª" <?php echo (($turma['classe'] ?? '') == '1ª') ? 'selected' : ''; ?>>1ª</option>
                            <option value="2ª" <?php echo (($turma['classe'] ?? '') == '2ª') ? 'selected' : ''; ?>>2ª</option>
                            <option value="3ª" <?php echo (($turma['classe'] ?? '') == '3ª') ? 'selected' : ''; ?>>3ª</option>
                            <option value="4ª" <?php echo (($turma['classe'] ?? '') == '4ª') ? 'selected' : ''; ?>>4ª</option>
                            <option value="5ª" <?php echo (($turma['classe'] ?? '') == '5ª') ? 'selected' : ''; ?>>5ª</option>
                            <option value="6ª" <?php echo (($turma['classe'] ?? '') == '6ª') ? 'selected' : ''; ?>>6ª</option>
                            <option value="7ª" <?php echo (($turma['classe'] ?? '') == '7ª') ? 'selected' : ''; ?>>7ª</option>
                            <option value="8ª" <?php echo (($turma['classe'] ?? '') == '8ª') ? 'selected' : ''; ?>>8ª</option>
                            <option value="9ª" <?php echo (($turma['classe'] ?? '') == '9ª') ? 'selected' : ''; ?>>9ª</option>
                            <option value="PRE" <?php echo (($turma['classe'] ?? '') == 'PRE') ? 'selected' : ''; ?>>Pré-Escolar</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="curso">Curso <span class="required">*</span></label>
                        <input type="text" id="curso" name="curso" 
                               value="<?php echo htmlspecialchars($turma['curso'] ?? ''); ?>" 
                               placeholder="Ex: SN, Ensino Primário" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="ano_letivo">Ano Letivo <span class="required">*</span></label>
                        <select id="ano_letivo" name="ano_letivo" required>
                            <?php for($ano = 2020; $ano <= 2030; $ano++): ?>
                                <option value="<?php echo $ano; ?>" <?php echo (($turma['ano_letivo'] ?? date('Y')) == $ano) ? 'selected' : ''; ?>>
                                    <?php echo $ano; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="turno">Turno <span class="required">*</span></label>
                        <select id="turno" name="turno" required>
                            <option value="manha" <?php echo (($turma['turno'] ?? '') == 'manha') ? 'selected' : ''; ?>>Manhã</option>
                            <option value="tarde" <?php echo (($turma['turno'] ?? '') == 'tarde') ? 'selected' : ''; ?>>Tarde</option>
                            <option value="noite" <?php echo (($turma['turno'] ?? '') == 'noite') ? 'selected' : ''; ?>>Noite</option>
                            <option value="integral" <?php echo (($turma['turno'] ?? '') == 'integral') ? 'selected' : ''; ?>>Integral</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="sala">Sala</label>
                        <input type="text" id="sala" name="sala" 
                               value="<?php echo htmlspecialchars($turma['sala'] ?? ''); ?>" 
                               placeholder="Ex: Sala 101">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="capacidade">Capacidade <span class="required">*</span></label>
                        <input type="number" id="capacidade" name="capacidade" 
                               value="<?php echo htmlspecialchars($turma['capacidade'] ?? 30); ?>" 
                               min="1" max="100" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="limite">Limite de Alunos <span class="required">*</span></label>
                        <input type="number" id="limite" name="limite" 
                               value="<?php echo htmlspecialchars($turma['limite'] ?? 45); ?>" 
                               min="1" max="100" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="idades">👶 Idades Compreendidas <span class="required">*</span></label>
                        <input type="text" id="idades" name="idades" 
                               value="<?php echo htmlspecialchars($turma['idades'] ?? ''); ?>" 
                               placeholder="Ex: 10-12 anos" required>
                        <span class="help-text">💡 Informe o intervalo de idades dos alunos desta turma. Exemplos: 10-12 anos | 6-8 anos | 14-16 anos</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="descricao">Descrição</label>
                        <input type="text" id="descricao" name="descricao" 
                               value="<?php echo htmlspecialchars($turma['descricao'] ?? ''); ?>" 
                               placeholder="Breve descrição da turma">
                    </div>
                </div>
                
                <!-- Disciplinas -->
                <div class="section-title">
                    <span class="emoji">📚</span> Disciplinas
                </div>
                
                <div class="form-group">
                    <label for="disciplinas">Disciplinas (separadas por vírgula) <span class="required">*</span></label>
                    <textarea id="disciplinas" name="disciplinas" 
                              placeholder="Digite as disciplinas separadas por vírgula" 
                              required><?php echo htmlspecialchars($turma['disciplinas'] ?? ''); ?></textarea>
                    <span class="help-text">Ex: Matemática, Português, História, Geografia, Ciências</span>
                </div>
                
                <!-- Dados Complementares -->
                <div class="section-title">
                    <span class="emoji">👨‍🏫</span> Dados Complementares
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="professor_id">Professor Responsável</label>
                        <select id="professor_id" name="professor_id">
                            <option value="">Selecione um professor</option>
                            <?php foreach ($professores as $prof): ?>
                                <option value="<?php echo $prof['id']; ?>" 
                                    <?php echo (($turma['professor_id'] ?? '') == $prof['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($prof['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="status-select">
                            <option value="ativa" <?php echo (($turma['status'] ?? 'ativa') == 'ativa') ? 'selected' : ''; ?>>✅ Ativa</option>
                            <option value="concluída" <?php echo (($turma['status'] ?? '') == 'concluída') ? 'selected' : ''; ?>>📌 Concluída</option>
                            <option value="cancelada" <?php echo (($turma['status'] ?? '') == 'cancelada') ? 'selected' : ''; ?>>❌ Cancelada</option>
                            <option value="inativa" <?php echo (($turma['status'] ?? '') == 'inativa') ? 'selected' : ''; ?>>⛔ Inativa</option>
                        </select>
                    </div>
                </div>
                
                <!-- Ações -->
                <div class="form-actions">
                    <button type="submit" name="salvar" class="btn btn-primary">💾 Salvar Turma</button>
                    <a href="javascript:history.back()" class="btn btn-secondary">Cancelar</a>
                    <a href="../listar.php" class="btn btn-success btn-sm" style="margin-left: auto;">📋 Ver Lista</a>
                </div>
            </form>
        <?php endif; ?>
        
        <!-- Rodapé -->
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e8ecf1; text-align: center; font-size: 12px; color: #95a5a6;">
            🎓 Módulo Gestão Escolar © 2026 SoftGest v1.0 | Sistema Online
        </div>
    </div>
</body>
</html>