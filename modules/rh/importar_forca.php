<?php
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivo'])) {
    $arquivo = $_FILES['arquivo']['tmp_name'];
    $linhas = file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    // Pular cabeçalho
    array_shift($linhas);
    
    $importados = 0;
    $erros = 0;
    
    foreach($linhas as $linha) {
        $dados = str_getcsv($linha, "\t");
        
        if (count($dados) >= 18) {
            try {
                $stmt = $pdo->prepare("INSERT INTO forca_trabalho (
                    numero_instituicao, numero_agente, nome_completo, categoria_actual,
                    instituicao, funcao, disciplina, formacao_disciplina,
                    data_inicio_funcao, data_inicio_instituicao, bi, data_nascimento,
                    genero, habilitacoes, especialidade_medio, especialidade_superior,
                    contacto, municipio, email, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ativo')");
                
                // Converter datas
                $dataInicioFuncao = DateTime::createFromFormat('d/m/Y', trim($dados[8])) ?: null;
                $dataInicioInstituicao = DateTime::createFromFormat('d/m/Y', trim($dados[9])) ?: null;
                $dataNascimento = DateTime::createFromFormat('d/m/Y', trim($dados[11])) ?: null;
                
                $stmt->execute([
                    trim($dados[0]) ?? null,  // numero_instituicao
                    trim($dados[1]) ?? null,  // numero_agente
                    trim($dados[2]),          // nome_completo
                    trim($dados[3]) ?? null,  // categoria_actual
                    trim($dados[4]) ?? null,  // instituicao
                    trim($dados[5]) ?? null,  // funcao
                    trim($dados[6]) ?? null,  // disciplina
                    trim($dados[7]) ?? null,  // formacao_disciplina
                    $dataInicioFuncao ? $dataInicioFuncao->format('Y-m-d') : null,
                    $dataInicioInstituicao ? $dataInicioInstituicao->format('Y-m-d') : null,
                    trim($dados[10]) ?? null, // bi
                    $dataNascimento ? $dataNascimento->format('Y-m-d') : null,
                    trim($dados[12]) ?? null, // genero
                    trim($dados[13]) ?? null, // habilitacoes
                    trim($dados[14]) ?? null, // especialidade_medio
                    trim($dados[15]) ?? null, // especialidade_superior
                    trim($dados[16]) ?? null, // contacto
                    trim($dados[17]) ?? null, // municipio
                    trim($dados[18]) ?? null  // email
                ]);
                
                $importados++;
            } catch(Exception $e) {
                $erros++;
                error_log("Erro ao importar: " . $e->getMessage());
            }
        } else {
            $erros++;
        }
    }
    
    $mensagem = "✅ Importação concluída! $importados registros importados, $erros erros.";
    header("Location: importar_forca.php?success=" . urlencode($mensagem));
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Força de Trabalho - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .upload-area { 
            border: 2px dashed #c9a84c; 
            padding: 50px; 
            text-align: center; 
            border-radius: 12px; 
            background: #fefcf5;
            transition: all 0.3s ease;
        }
        .upload-area:hover { background: #f8f4e8; border-color: #b8953a; }
        .upload-area .icon { font-size: 64px; display: block; margin-bottom: 20px; }
        .upload-area input[type="file"] { display: none; }
        .upload-area .file-label { 
            display: inline-block; 
            padding: 12px 30px; 
            background: linear-gradient(135deg, #c9a84c, #f5d76e); 
            color: #1a2332; 
            border-radius: 8px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: all 0.3s ease;
        }
        .upload-area .file-label:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(197, 165, 50, 0.3); }
        .file-info { margin-top: 15px; color: #64748b; font-size: 14px; }
        .btn-import { 
            background: linear-gradient(135deg, #c9a84c, #f5d76e); 
            color: #1a2332; 
            padding: 12px 40px; 
            border: none; 
            border-radius: 8px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: all 0.3s ease;
            font-size: 16px;
        }
        .btn-import:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(197, 165, 50, 0.3); }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <h2>📥 Importar Força de Trabalho</h2>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
        <?php endif; ?>
        
        <div style="background: white; padding: 30px; border-radius: 12px; border: 1px solid #eef2f7; margin-top: 20px;">
            <h3>Instruções</h3>
            <ul style="color: #64748b; line-height: 2;">
                <li>📄 O arquivo deve estar no formato <strong>CSV</strong> ou <strong>TXT</strong></li>
                <li>🔤 Use <strong>Tab</strong> como separador de colunas</li>
                <li>📋 As colunas devem estar na ordem correta</li>
                <li>📅 Datas no formato <strong>DD/MM/YYYY</strong></li>
                <li>⚠️ A primeira linha deve ser o cabeçalho</li>
            </ul>
            
            <div style="background: #f8fafc; padding: 15px; border-radius: 8px; margin: 20px 0;">
                <p style="font-weight: 600; color: #1a2332;">📋 Ordem das colunas:</p>
                <p style="color: #64748b; font-size: 13px;">
                    Nº Instituição | Nº Agente | Nome Completo | Categoria | Instituição | Função | Disciplina | 
                    Formação | Data Início Função | Data Início Instituição | BI | Data Nascimento | 
                    Gênero (M/F) | Habilitações | Especialidade Médio | Especialidade Superior | 
                    Contacto | Município | Email
                </p>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="upload-area" id="uploadArea">
                    <span class="icon">📂</span>
                    <p style="font-size: 18px; font-weight: 600; color: #1a2332;">Arraste seu arquivo aqui</p>
                    <p style="color: #94a3b8;">ou</p>
                    <label for="arquivo" class="file-label">📁 Selecionar Arquivo</label>
                    <input type="file" name="arquivo" id="arquivo" accept=".csv,.txt">
                    <div class="file-info" id="fileInfo">Nenhum arquivo selecionado</div>
                </div>
                
                <div style="text-align: center; margin-top: 20px;">
                    <button type="submit" class="btn-import">📥 Importar Dados</button>
                </div>
            </form>
        </div>
        
        <!-- Modelo para download -->
        <div style="margin-top: 20px; text-align: center;">
            <a href="modelo_forca_trabalho.csv" download class="btn-gold-outline">📄 Baixar Modelo CSV</a>
            <a href="index.php" class="btn-gold-outline">← Voltar ao Dashboard</a>
        </div>
    </div>
    
    <script>
        const fileInput = document.getElementById('arquivo');
        const fileInfo = document.getElementById('fileInfo');
        const uploadArea = document.getElementById('uploadArea');
        
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                fileInfo.textContent = `📄 ${this.files[0].name} (${(this.files[0].size / 1024).toFixed(2)} KB)`;
                uploadArea.style.borderColor = '#2ecc71';
            }
        });
        
        // Drag and drop
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.borderColor = '#2ecc71';
            this.style.background = '#f0fdf4';
        });
        
        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.style.borderColor = '#c9a84c';
            this.style.background = '#fefcf5';
        });
        
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.borderColor = '#c9a84c';
            this.style.background = '#fefcf5';
            
            if (e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                fileInfo.textContent = `📄 ${e.dataTransfer.files[0].name} (${(e.dataTransfer.files[0].size / 1024).toFixed(2)} KB)`;
                uploadArea.style.borderColor = '#2ecc71';
            }
        });
    </script>
</body>
</html>