<?php
// ============================================
// modules/escola/alunos/add.php - Cadastrar Aluno
// ============================================

// Usando caminho absoluto baseado no document root
$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';
require_once $base_path . '/config/app_modes.php';
require_once $base_path . '/config/database.php';
require_once 'verificar_permissao.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// 🔒 Verifica permissão para CRIAR
bloquearAcesso('criar');

// ============================================
// 1. FUNÇÃO PARA GERAR ID AUTOMÁTICO - FORMATO ANO+SEQUENCIAL
// Exemplo: 2026032, 2026033, 2026034...
// ============================================
function gerarIdAluno($pdo) {
    $ano_atual = date('Y');
    
    try {
        // BUSCA TODOS OS IDs DO ANO ATUAL (começam com o ano)
        $stmt = $pdo->prepare("SELECT id FROM alunos WHERE id LIKE ? ORDER BY id DESC");
        $stmt->execute([$ano_atual . '%']);
        $ids_existentes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($ids_existentes)) {
            return $ano_atual . '1';
        }
        
        // EXTRAI OS NÚMEROS DOS IDs EXISTENTES
        $numeros = [];
        foreach ($ids_existentes as $id) {
            $numero = intval(substr($id, 4));
            if ($numero > 0) {
                $numeros[] = $numero;
            }
        }
        
        if (empty($numeros)) {
            return $ano_atual . '1';
        }
        
        // ORDENA OS NÚMEROS
        sort($numeros);
        
        // PROCURA A PRIMEIRA LACUNA NA SEQUÊNCIA
        $esperado = 1;
        foreach ($numeros as $num) {
            if ($num > $esperado) {
                break;
            }
            $esperado = $num + 1;
        }
        
        $proximo_numero = $esperado;
        
        if ($proximo_numero > 9999) {
            for ($i = 1; $i <= 9999; $i++) {
                if (!in_array($i, $numeros)) {
                    $proximo_numero = $i;
                    break;
                }
            }
            if ($proximo_numero > 9999) {
                return $ano_atual . rand(1, 9999);
            }
        }
        
        return $ano_atual . $proximo_numero;
        
    } catch (Exception $e) {
        for ($tentativas = 0; $tentativas < 10; $tentativas++) {
            $numero = rand(1, 9999);
            $id_tentativa = $ano_atual . $numero;
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM alunos WHERE id = ?");
            $stmt->execute([$id_tentativa]);
            if ($stmt->fetchColumn() == 0) {
                return $id_tentativa;
            }
        }
        return $ano_atual . (time() % 10000);
    }
}

// ============================================
// 2. FUNÇÃO PARA NORMALIZAR NOME DA TURMA
// ============================================
function normalizarNomeTurma($classe, $periodo) {
    if (empty($classe)) return '';
    
    // Extrai o número da classe
    $classe_limpa = preg_replace('/[^0-9]/', '', $classe);
    if (empty($classe_limpa)) {
        // Se for PRÉ, retorna "PRE"
        if (strtoupper($classe) == 'PRÉ' || strtoupper($classe) == 'PRE') {
            return 'PRE';
        }
        $classe_limpa = '1';
    }
    
    // Abrevia o período
    $periodo_abreviado = '';
    if (stripos($periodo, 'manh') !== false) {
        $periodo_abreviado = 'M';
    } elseif (stripos($periodo, 'tard') !== false) {
        $periodo_abreviado = 'T';
    } elseif (stripos($periodo, 'noit') !== false) {
        $periodo_abreviado = 'N';
    } else {
        $periodo_abreviado = 'M'; // Padrão Manhã
    }
    
    return $classe_limpa . $periodo_abreviado;
}

// ============================================
// 3. FUNÇÃO PARA VERIFICAR SE ID EXISTE
// ============================================
function idExiste($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM alunos WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// ============================================
// 4. FUNÇÃO PARA REGISTRAR LOG
// ============================================
function registrarLog($pdo, $acao, $descricao, $tabela = 'alunos', $registro_id = null) {
    try {
        $usuario_id = $_SESSION['usuario_id'] ?? null;
        $usuario_nome = $_SESSION['usuario_nome'] ?? 'Sistema';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        $stmt = $pdo->query("SHOW TABLES LIKE 'logs_escolares'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS logs_escolares (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    usuario_id INT NULL,
                    usuario_nome VARCHAR(100) NULL,
                    acao VARCHAR(50) NOT NULL,
                    tabela VARCHAR(50) NOT NULL,
                    registro_id VARCHAR(50) NULL,
                    descricao TEXT NULL,
                    ip VARCHAR(45) NULL,
                    data_hora DATETIME NOT NULL,
                    INDEX idx_usuario (usuario_id),
                    INDEX idx_acao (acao),
                    INDEX idx_tabela (tabela),
                    INDEX idx_data (data_hora)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO logs_escolares (usuario_id, usuario_nome, acao, tabela, registro_id, descricao, ip, data_hora)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$usuario_id, $usuario_nome, $acao, $tabela, $registro_id, $descricao, $ip]);
        return true;
    } catch (Exception $e) {
        error_log("Erro ao registrar log: " . $e->getMessage());
        return false;
    }
}

// ============================================
// 5. FUNÇÃO PARA UPLOAD DE FOTO DO ALUNO
// ============================================
function uploadFotoAluno($file, $aluno_id = null) {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'O arquivo excede o tamanho máximo permitido pelo servidor.',
            UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o tamanho máximo permitido pelo formulário.',
            UPLOAD_ERR_PARTIAL => 'O arquivo foi enviado parcialmente.',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária não encontrada.',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever o arquivo no disco.',
            UPLOAD_ERR_EXTENSION => 'Uma extensão do PHP interrompeu o upload.'
        ];
        throw new Exception('Erro no upload: ' . ($errors[$file['error']] ?? 'Erro desconhecido'));
    }
    
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        throw new Exception('Tipo de arquivo não permitido. Use JPG, PNG, GIF ou WEBP.');
    }
    
    $max_size = 5 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        throw new Exception('O arquivo excede o tamanho máximo de 5MB.');
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $nome_arquivo = 'aluno_' . ($aluno_id ?? time()) . '_' . uniqid() . '.' . $extension;
    
    $upload_dir = __DIR__ . '/../../../uploads/alunos/';
    
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            throw new Exception('Não foi possível criar o diretório de upload.');
        }
    }
    
    $caminho_completo = $upload_dir . $nome_arquivo;
    
    if (!move_uploaded_file($file['tmp_name'], $caminho_completo)) {
        throw new Exception('Erro ao mover o arquivo para o destino final.');
    }
    
    return 'uploads/alunos/' . $nome_arquivo;
}

// ============================================
// 6. CONECTAR AO BANCO
// ============================================
try {
    $pdo = conectarBanco();
} catch (Exception $e) {
    die("❌ Erro de conexão: " . $e->getMessage());
}

// ============================================
// 7. CLASSES PRÉ-DEFINIDAS
// ============================================
$CLASSES_PRE_DEFINIDAS = [
    'PRÉ', '1ª', '2ª', '3ª', '4ª', '5ª', '6ª', 
    '7ª', '8ª', '9ª', '10ª', '11ª', '12ª'
];

// ============================================
// 8. BUSCAR CURSOS E TURMAS
// ============================================
$cursos = [];
$turmas = [];
try {
    // Buscar cursos distintos
    $stmt = $pdo->query("SELECT DISTINCT curso FROM turmas WHERE curso IS NOT NULL AND curso != '' AND status = 'ativa' ORDER BY curso");
    $cursos = $stmt->fetchAll();
    
    // Buscar turmas com todos os dados
    $stmt = $pdo->query("
        SELECT id, nome, classe, curso, turno, sala, limite, idades, status 
        FROM turmas 
        WHERE status = 'ativa' 
        ORDER BY classe, nome
    ");
    $turmas = $stmt->fetchAll();
    
    // Se não houver turmas, buscar qualquer turma
    if (empty($turmas)) {
        $stmt = $pdo->query("SELECT id, nome, classe, curso, turno, sala, limite, idades, status FROM turmas ORDER BY nome LIMIT 10");
        $turmas = $stmt->fetchAll();
    }
    
} catch (Exception $e) {
    error_log("Erro ao buscar turmas: " . $e->getMessage());
}

$erro = '';
$sucesso = '';
$notificacoes = [];
$novo_id_gerado = gerarIdAluno($pdo);

// =============================================
// ===== FUNÇÃO PARA VALIDAR IDADE COM A TURMA =====
// =============================================
function validarIdadeComTurma($idade, $turma_id, $pdo) {
    if (empty($turma_id)) {
        return ['valido' => true, 'mensagem' => ''];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT idades FROM turmas WHERE id = ?");
        $stmt->execute([$turma_id]);
        $result = $stmt->fetch();
        
        if (!$result || empty($result['idades'])) {
            return ['valido' => true, 'mensagem' => ''];
        }
        
        $idadesPermitidas = $result['idades'];
        
        if (strpos($idadesPermitidas, '-') !== false || strpos($idadesPermitidas, ' a ') !== false) {
            $intervalo = preg_replace('/\s*a\s*/', '-', $idadesPermitidas);
            $partes = explode('-', $intervalo);
            if (count($partes) == 2) {
                $min = intval(trim($partes[0]));
                $max = intval(trim($partes[1]));
                if ($idade >= $min && $idade <= $max) {
                    return ['valido' => true, 'mensagem' => ''];
                } else {
                    return [
                        'valido' => false, 
                        'mensagem' => "Idade $idade anos não compatível com a turma. Idades permitidas: $min a $max anos."
                    ];
                }
            }
        }
        
        if (strpos($idadesPermitidas, ',') !== false) {
            $idades = array_map('intval', array_map('trim', explode(',', $idadesPermitidas)));
            if (in_array($idade, $idades)) {
                return ['valido' => true, 'mensagem' => ''];
            } else {
                $listaIdades = implode(', ', $idades);
                return [
                    'valido' => false, 
                    'mensagem' => "Idade $idade anos não compatível com a turma. Idades permitidas: $listaIdades anos."
                ];
            }
        }
        
        $idadePermitida = intval(trim($idadesPermitidas));
        if ($idade == $idadePermitida) {
            return ['valido' => true, 'mensagem' => ''];
        } else {
            return [
                'valido' => false, 
                'mensagem' => "Idade $idade anos não compatível com a turma. Idade permitida: $idadePermitida anos."
            ];
        }
        
    } catch (Exception $e) {
        return ['valido' => true, 'mensagem' => ''];
    }
}

// =============================================
// ===== PROCESSAR IMPORTAÇÃO DE CSV =====
// =============================================
if (isset($_POST['importar_planilha']) && isset($_FILES['arquivo_importacao']) && $_FILES['arquivo_importacao']['error'] == 0) {
    $arquivo = $_FILES['arquivo_importacao']['tmp_name'];
    $extensao = strtolower(pathinfo($_FILES['arquivo_importacao']['name'], PATHINFO_EXTENSION));
    
    if ($extensao != 'csv') {
        $erro = "⚠️ Formato não suportado. Use arquivo CSV. O modelo baixado é em CSV.";
    } else {
        try {
            $handle = fopen($arquivo, 'r');
            if ($handle === false) {
                throw new Exception("Não foi possível abrir o arquivo");
            }
            
            $cabecalho = fgetcsv($handle, 0, ';');
            $separador = ';';
            
            if ($cabecalho === false || count($cabecalho) < 2) {
                rewind($handle);
                $cabecalho = fgetcsv($handle, 0, ',');
                $separador = ',';
            }
            
            if ($cabecalho === false || count($cabecalho) < 2) {
                rewind($handle);
                $cabecalho = fgetcsv($handle, 0, "\t");
                $separador = "\t";
            }
            
            $importados = 0;
            $erros_importacao = [];
            $ids_gerados = [];
            $nomes_importados = [];
            
            while (($data = fgetcsv($handle, 0, $separador)) !== false) {
                if (empty(array_filter($data))) continue;
                
                $primeiro = trim(strtoupper($data[0] ?? ''));
                if (strpos($primeiro, 'INSTRUÇÕES') !== false || strpos($primeiro, 'NOME') !== false) continue;
                
                $nome = trim($data[0] ?? '');
                $sexo = trim($data[1] ?? 'M');
                $data_nasc = trim($data[2] ?? '');
                $morada = trim($data[3] ?? '');
                $contacto = trim($data[4] ?? '');
                $classe = trim($data[5] ?? '');
                $curso = trim($data[6] ?? '');
                $naturalidade = trim($data[7] ?? '');
                $municipio = trim($data[8] ?? '');
                $provincia = trim($data[9] ?? '');
                $bi = trim($data[10] ?? '');
                $nome_pai = trim($data[11] ?? '');
                $contacto_pai = trim($data[12] ?? '');
                $nome_mae = trim($data[13] ?? '');
                $contacto_mae = trim($data[14] ?? '');
                
                if (empty($nome)) continue;
                
                $id = gerarIdAluno($pdo);
                
                $dia = 0;
                $mes = 0;
                $ano = 0;
                $idade = 0;
                
                if (!empty($data_nasc)) {
                    $formatos = ['d/m/Y', 'd-m-Y', 'Y-m-d', 'd.m.Y', 'm/d/Y'];
                    foreach ($formatos as $formato) {
                        $date = DateTime::createFromFormat($formato, $data_nasc);
                        if ($date) {
                            $dia = intval($date->format('d'));
                            $mes = intval($date->format('m'));
                            $ano = intval($date->format('Y'));
                            $idade = date('Y') - $ano;
                            break;
                        }
                    }
                }
                
                // Busca turma automaticamente baseado na classe
                $periodo = 'Manhã';
                $turmaNome = normalizarNomeTurma($classe, $periodo);
                $sala = '';
                
                // Tenta encontrar a turma no banco
                try {
                    $stmt_turma = $pdo->prepare("SELECT nome, sala, turno FROM turmas WHERE classe = ? AND status = 'ativa' LIMIT 1");
                    $stmt_turma->execute([$classe]);
                    $turmaData = $stmt_turma->fetch();
                    if ($turmaData) {
                        $turmaNome = $turmaData['nome'];
                        $sala = $turmaData['sala'] ?? '';
                        $periodo = $turmaData['turno'] ?? 'Manhã';
                    }
                } catch (Exception $e) {}
                
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO alunos (
                            id, nome, Sexo, dia, mes, Ano, Idade, Morada,
                            Cadastro_Transporte, Contacto_do_Aluno, Debilidade,
                            Naturalidade, Municipio, Provincia, N_BI,
                            Classe, Curso, Nome_do_Pai, Contacto4, Ocupacao,
                            Local_de_Trabalho, Nome_da_mae, Contacto_Mae,
                            Data_Matricula, Ocupacao_do_Aluno, Periodo,
                            Data_Emissao_do_BI, Arq_identificacao, Situacao_Cadastro,
                            TURMA, SALA, foto
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                        )
                    ");
                    
                    $stmt->execute([
                        $id, $nome, $sexo, $dia, $mes, $ano, $idade, $morada,
                        'Não', $contacto, '',
                        $naturalidade, $municipio, $provincia, $bi,
                        $classe, $curso, $nome_pai, $contacto_pai, '',
                        '', $nome_mae, $contacto_mae,
                        date('Y-m-d'), '', $periodo,
                        '', '', 'Matrícula',
                        $turmaNome, $sala, ''
                    ]);
                    
                    $importados++;
                    $ids_gerados[] = $id;
                    $nomes_importados[] = $nome;
                    
                    registrarLog($pdo, 'importar', "Aluno '$nome' importado com ID: $id, Turma: $turmaNome", 'alunos', $id);
                    
                } catch (Exception $e) {
                    $erros_importacao[] = "Linha " . ($importados + 2) . ": " . $e->getMessage();
                }
            }
            
            fclose($handle);
            
            if ($importados > 0) {
                $notificacoes[] = "📥 <strong>$importados alunos importados com sucesso!</strong>";
                $notificacoes[] = "📋 IDs gerados: " . implode(', ', array_slice($ids_gerados, 0, 10)) . (count($ids_gerados) > 10 ? " e mais " . (count($ids_gerados) - 10) . " IDs" : "");
                if (count($nomes_importados) <= 5) {
                    $notificacoes[] = "👤 Alunos: " . implode(', ', $nomes_importados);
                } else {
                    $notificacoes[] = "👤 Alunos: " . implode(', ', array_slice($nomes_importados, 0, 5)) . " e mais " . (count($nomes_importados) - 5) . " alunos";
                }
                $sucesso = implode("<br>", $notificacoes);
                
                if (count($erros_importacao) > 0) {
                    $erro = "⚠️ " . implode("; ", $erros_importacao);
                }
                
                $novo_id_gerado = gerarIdAluno($pdo);
            } else {
                $erro = "Nenhum aluno foi importado. Verifique o formato do arquivo.";
            }
            
        } catch (Exception $e) {
            $erro = "Erro ao importar: " . $e->getMessage();
        }
    }
}

// =============================================
// ===== EXPORTAR MODELO CSV =====
// =============================================
if (isset($_GET['exportar_modelo'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="modelo_cadastro_alunos.csv"');
    
    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF");
    
    fputcsv($output, [
        'NOME', 'SEXO', 'DATA_NASC', 'MORADA', 'CONTACTO', 
        'CLASSE', 'CURSO', 'NATURALIDADE', 'MUNICIPIO', 'PROVINCIA',
        'BI', 'NOME_PAI', 'CONTACTO_PAI', 'NOME_MAE', 'CONTACTO_MAE'
    ], ';');
    
    fputcsv($output, [
        'João Silva', 'M', '15/03/2010', 'Rua Principal 123', '912345678',
        '1ª', 'Informática', 'Luanda', 'Luanda', 'Luanda',
        '123456789LA', 'António Silva', '923456789', 'Maria Silva', '934567890'
    ], ';');
    
    fputcsv($output, [], ';');
    fputcsv($output, ['INSTRUÇÕES:'], ';');
    fputcsv($output, ['1. Preencha os dados a partir da linha 3'], ';');
    fputcsv($output, ['2. SEXO: M (Masculino) ou F (Feminino)'], ';');
    fputcsv($output, ['3. DATA_NASC: use formato DD/MM/AAAA (ex: 15/03/2010)'], ';');
    fputcsv($output, ['4. CLASSE: PRÉ, 1ª, 2ª, 3ª, 4ª, 5ª, 6ª, 7ª, 8ª, 9ª, 10ª, 11ª, 12ª'], ';');
    fputcsv($output, ['5. CURSO: nome do curso (ex: Informática, Eletrônica, etc.)'], ';');
    fputcsv($output, ['6. O ID será gerado automaticamente no formato ANO+SEQUENCIAL (ex: 2026032)'], ';');
    fputcsv($output, ['7. A TURMA e SALA serão atribuídas automaticamente baseado na CLASSE'], ';');
    
    fclose($output);
    exit;
}

// =============================================
// ===== PROCESSAR CADASTRO INDIVIDUAL =====
// =============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['importar_planilha'])) {
    // Coletar dados do formulário
    $id = trim($_POST['id'] ?? '');
    $nome = trim($_POST['nome'] ?? '');
    $sexo = $_POST['sexo'] ?? 'M';
    $dia = intval($_POST['dia'] ?? 0);
    $mes = intval($_POST['mes'] ?? 0);
    $ano = intval($_POST['ano'] ?? 0);
    $morada = trim($_POST['morada'] ?? '');
    $transporte = $_POST['transporte'] ?? 'Não';
    $contacto = trim($_POST['contacto'] ?? '');
    $debilidade = trim($_POST['debilidade'] ?? '');
    $naturalidade = trim($_POST['naturalidade'] ?? '');
    $municipio = trim($_POST['municipio'] ?? '');
    $provincia = trim($_POST['provincia'] ?? '');
    $bi = trim($_POST['bi'] ?? '');
    $classe = trim($_POST['classe'] ?? '');
    $curso = trim($_POST['curso'] ?? '');
    $nome_pai = trim($_POST['nome_pai'] ?? '');
    $contacto_pai = trim($_POST['contacto_pai'] ?? '');
    $ocupacao_pai = trim($_POST['ocupacao_pai'] ?? '');
    $local_trabalho = trim($_POST['local_trabalho'] ?? '');
    $nome_mae = trim($_POST['nome_mae'] ?? '');
    $contacto_mae = trim($_POST['contacto_mae'] ?? '');
    $data_matricula = $_POST['data_matricula'] ?? date('Y-m-d');
    $ocupacao_aluno = trim($_POST['ocupacao_aluno'] ?? '');
    $data_emissao_bi = $_POST['data_emissao_bi'] ?? '';
    $arq_identificacao = trim($_POST['arq_identificacao'] ?? '');
    $situacao = $_POST['situacao'] ?? 'Matrícula';
    $turma_id = isset($_POST['turma_id']) && $_POST['turma_id'] ? intval($_POST['turma_id']) : null;
    $periodo = trim($_POST['periodo'] ?? '');
    $sala = trim($_POST['sala'] ?? '');
    $foto_remover = isset($_POST['foto_remover']) ? intval($_POST['foto_remover']) : 0;
    $gerar_id_auto = isset($_POST['gerar_id_auto']) && $_POST['gerar_id_auto'] == '1';

    // 🔑 GERA ID AUTOMATICAMENTE SE NÃO FOI INSERIDO OU AUTO ESTÁ MARCADO
    if (empty($id) || $gerar_id_auto) {
        $id = gerarIdAluno($pdo);
        $id_auto_gerado = true;
        $notificacoes[] = "🔑 ID gerado automaticamente: <strong>$id</strong>";
    } else {
        $id_auto_gerado = false;
        if (idExiste($pdo, $id)) {
            $erro = "❌ O ID '$id' já está cadastrado!";
        }
    }

    // Validar campos obrigatórios
    if (empty($id) || empty($nome) || empty($classe) || empty($curso)) {
        $erro = 'Preencha todos os campos obrigatórios!';
    } elseif (empty($erro)) {
        try {
            $idade = date('Y') - $ano;
            if ($idade < 0) $idade = 0;
            
            // ============================================
            // BUSCAR DADOS DA TURMA SELECIONADA
            // ============================================
            $turmaNome = '';
            $salaTurma = '';
            $periodoTurma = '';
            
            if ($turma_id) {
                $validacao = validarIdadeComTurma($idade, $turma_id, $pdo);
                if (!$validacao['valido']) {
                    $erro = $validacao['mensagem'];
                }
                
                // BUSCA OS DADOS COMPLETOS DA TURMA
                $stmt = $pdo->prepare("SELECT nome, sala, turno FROM turmas WHERE id = ? AND status = 'ativa'");
                $stmt->execute([$turma_id]);
                $turmaData = $stmt->fetch();
                if ($turmaData) {
                    $turmaNome = $turmaData['nome'];  // Nome da turma (ex: 3M, 5AM, 9AT)
                    $salaTurma = $turmaData['sala'] ?? '';
                    $periodoTurma = $turmaData['turno'] ?? '';
                    
                    // SE O PERÍODO NÃO FOI PREENCHIDO MANUALMENTE, USA O DA TURMA
                    if (empty($periodo) && !empty($periodoTurma)) {
                        $periodo = $periodoTurma;
                    }
                    
                    // SE A SALA NÃO FOI PREENCHIDA MANUALMENTE, USA O DA TURMA
                    if (empty($sala) && !empty($salaTurma)) {
                        $sala = $salaTurma;
                    }
                    
                    $notificacoes[] = "🏫 Turma: <strong>$turmaNome</strong>";
                    $notificacoes[] = "📚 Sala: <strong>" . ($salaTurma ?: 'Não definida') . "</strong>";
                    $notificacoes[] = "⏰ Período: <strong>" . ($periodoTurma ?: 'Não definido') . "</strong>";
                }
            } else {
                // SE NÃO SELECIONOU TURMA, TENTA ENCONTRAR AUTOMATICAMENTE
                if (empty($periodo)) {
                    $periodo = 'Manhã'; // Valor padrão
                }
                
                // Tenta encontrar uma turma automaticamente baseado na classe e período
                try {
                    $stmt = $pdo->prepare("SELECT nome, sala, turno FROM turmas WHERE classe = ? AND turno = ? AND status = 'ativa' LIMIT 1");
                    $stmt->execute([$classe, $periodo]);
                    $turmaData = $stmt->fetch();
                    if ($turmaData) {
                        $turmaNome = $turmaData['nome'];
                        $sala = $turmaData['sala'] ?? '';
                        $periodo = $turmaData['turno'] ?? $periodo;
                        $notificacoes[] = "🏫 Turma encontrada automaticamente: <strong>$turmaNome</strong>";
                    } else {
                        // Se não encontrou, gera um nome de turma baseado na classe e período
                        $turmaNome = normalizarNomeTurma($classe, $periodo);
                        $notificacoes[] = "🏫 Turma gerada: <strong>$turmaNome</strong>";
                    }
                } catch (Exception $e) {
                    $turmaNome = normalizarNomeTurma($classe, $periodo);
                }
            }

            // ============================================
            // UPLOAD DA FOTO
            // ============================================
            $foto_nome = '';
            if (isset($_FILES['foto']) && is_array($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
                try {
                    $foto_nome = uploadFotoAluno($_FILES['foto'], $id);
                    $notificacoes[] = "📸 Foto carregada com sucesso!";
                } catch (Exception $e) {
                    error_log("Erro ao fazer upload da foto: " . $e->getMessage());
                    $notificacoes[] = "⚠️ Foto não carregada: " . $e->getMessage();
                    $foto_nome = '';
                }
            }

            // ============================================
            // INSERIR ALUNO COM TURMA E SALA CORRETAS
            // ============================================
            $stmt = $pdo->prepare("
                INSERT INTO alunos (
                    id, nome, Sexo, dia, mes, Ano, Idade, Morada,
                    Cadastro_Transporte, Contacto_do_Aluno, Debilidade,
                    Naturalidade, Municipio, Provincia, N_BI,
                    Classe, Curso, Nome_do_Pai, Contacto4, Ocupacao,
                    Local_de_Trabalho, Nome_da_mae, Contacto_Mae,
                    Data_Matricula, Ocupacao_do_Aluno, Periodo,
                    Data_Emissao_do_BI, Arq_identificacao, Situacao_Cadastro,
                    TURMA, SALA, foto
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ");
            
            $stmt->execute([
                $id, $nome, $sexo, $dia, $mes, $ano, $idade, $morada,
                $transporte, $contacto, $debilidade,
                $naturalidade, $municipio, $provincia, $bi,
                $classe, $curso, $nome_pai, $contacto_pai, $ocupacao_pai,
                $local_trabalho, $nome_mae, $contacto_mae,
                $data_matricula, $ocupacao_aluno, $periodo,
                $data_emissao_bi, $arq_identificacao, $situacao,
                $turmaNome,  // NOME DA TURMA (campo TURMA)
                $sala,       // SALA (campo SALA)
                $foto_nome
            ]);
            
            $notificacoes[] = "✅ Aluno <strong>'$nome'</strong> cadastrado com sucesso!";
            $notificacoes[] = "📋 ID: <strong>$id</strong>";
            $notificacoes[] = "📚 Classe: <strong>$classe</strong>";
            $notificacoes[] = "📘 Curso: <strong>$curso</strong>";
            if ($idade > 0) {
                $notificacoes[] = "🎂 Idade: <strong>$idade anos</strong>";
            }
            if (!empty($periodo)) {
                $notificacoes[] = "⏰ Período: <strong>$periodo</strong>";
            }
            if (!empty($turmaNome)) {
                $notificacoes[] = "🏫 Turma: <strong>$turmaNome</strong>";
            }
            if (!empty($sala)) {
                $notificacoes[] = "🏠 Sala: <strong>$sala</strong>";
            }
            
            $sucesso = implode("<br>", $notificacoes);
            
            $descricao_log = "Cadastrou aluno: $nome (ID: $id) - Classe: $classe, Curso: $curso";
            if ($turmaNome) {
                $descricao_log .= ", Turma: $turmaNome, Sala: $sala";
            }
            if ($id_auto_gerado) {
                $descricao_log .= " - ID gerado automaticamente";
            }
            registrarLog($pdo, 'cadastrar', $descricao_log, 'alunos', $id);
            
            $_POST = [];
            $novo_id_gerado = gerarIdAluno($pdo);
            
        } catch (Exception $e) {
            $erro = "❌ Erro ao cadastrar: " . $e->getMessage();
            registrarLog($pdo, 'erro_cadastro', "Erro ao cadastrar aluno: " . $e->getMessage(), 'alunos');
        }
    }
}


?>

<style>
    .page-header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;margin-bottom:25px}
    .page-header h1{font-size:24px;font-weight:700;color:#1a2332;margin:0}
    .subtitle{color:#6b7280;font-size:14px;margin:5px 0 0 0}
    .btn{padding:8px 20px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;transition:all .3s;display:inline-flex;align-items:center;gap:6px;border:none;cursor:pointer}
    .btn-secondary{background:#f1f5f9;color:#4a5568}
    .btn-secondary:hover{background:#e2e8f0;transform:translateY(-2px)}
    .btn-primary{background:#c9a84c;color:#1a2332}
    .btn-primary:hover{background:#b8973a;transform:translateY(-2px);box-shadow:0 4px 15px rgba(201,168,76,0.3)}
    .btn-danger{background:#e74c3c;color:#fff}
    .btn-danger:hover{background:#c0392b;transform:translateY(-2px)}
    .btn-success{background:#10b981;color:#fff}
    .btn-success:hover{background:#059669;transform:translateY(-2px);box-shadow:0 4px 15px rgba(16,185,129,0.3)}
    .btn-info{background:#3b82f6;color:#fff}
    .btn-info:hover{background:#2563eb;transform:translateY(-2px);box-shadow:0 4px 15px rgba(59,130,246,0.3)}
    .btn-sm{padding:4px 12px;font-size:11px;border-radius:6px}
    .form-container{background:white;border-radius:12px;padding:25px;box-shadow:0 2px 10px rgba(0,0,0,0.04);border:1px solid #eef2f7;max-width:900px}
    .form-section{margin-bottom:20px}
    .form-section-title{font-size:16px;font-weight:700;color:#1a2332;padding-bottom:8px;border-bottom:2px solid #eef2f7;margin-bottom:15px}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:10px}
    .form-row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:15px;margin-bottom:10px}
    .form-group{margin-bottom:10px}
    .form-group label{display:block;font-weight:600;margin-bottom:4px;color:#1a2332;font-size:12px}
    .form-group label .required{color:#e74c3c}
    .form-group label .help{font-weight:400;color:#94a3b8;font-size:10px}
    .form-group input,.form-group select,.form-group textarea{width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;transition:border-color .3s;font-family:inherit}
    .form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:#c9a84c;box-shadow:0 0 0 3px rgba(201,168,76,0.1)}
    .form-group textarea{min-height:50px;resize:vertical}
    .notification-box{background:#f0fdf4;border:1px solid #86efac;border-radius:12px;padding:15px 20px;margin-bottom:20px;max-width:900px}
    .notification-box .title{font-weight:700;font-size:15px;color:#065f46;margin-bottom:10px;display:flex;align-items:center;gap:8px}
    .notification-box .details{list-style:none;padding:0;margin:0}
    .notification-box .details li{padding:4px 0;font-size:13px;color:#1a2332;border-bottom:1px solid #d1fae5}
    .notification-box .details li:last-child{border-bottom:none}
    .notification-box .details li strong{color:#065f46}
    .alert{padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:14px}
    .alert-success{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0}
    .alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
    .form-actions{display:flex;gap:10px;margin-top:20px;flex-wrap:wrap}
    .nav-alunos{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:25px;padding:15px 20px;background:white;border-radius:12px;border:1px solid #eef2f7;box-shadow:0 2px 10px rgba(0,0,0,0.04)}
    .nav-alunos a{padding:8px 18px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:500;transition:all .3s;color:#4a5568;background:#f8fafc;border:1px solid #e2e8f0;display:inline-flex;align-items:center;gap:6px}
    .nav-alunos a:hover{background:#c9a84c;color:#1a2332;border-color:#c9a84c;transform:translateY(-2px)}
    .nav-alunos a.active{background:#c9a84c;color:#1a2332;border-color:#c9a84c}
    .import-section{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:25px;margin-bottom:25px;max-width:900px}
    .import-section h3{margin-top:0;margin-bottom:15px;display:flex;align-items:center;gap:10px;font-size:18px;color:#1a2332}
    .import-section .import-form{display:flex;gap:15px;flex-wrap:wrap;align-items:flex-end}
    .import-section .file-wrapper{flex:1;min-width:250px}
    .file-input-custom{background:white;border:2px dashed #d1d5db;border-radius:8px;padding:20px;text-align:center;cursor:pointer;transition:all .3s;width:100%;position:relative}
    .file-input-custom:hover{border-color:#c9a84c;background:#fefcf3}
    .file-input-custom.has-file{border-color:#10b981;background:#f0fdf4}
    .file-input-custom .icon{font-size:32px;display:block;margin-bottom:5px}
    .file-input-custom .label{display:block;font-weight:500;color:#1a2332}
    .file-input-custom .sub-label{display:block;font-size:11px;color:#94a3b8;margin-top:4px}
    .file-input-custom input[type="file"]{position:absolute;left:0;top:0;opacity:0;width:100%;height:100%;cursor:pointer}
    .import-actions{display:flex;gap:10px}
    .import-instructions{margin-top:12px;padding:12px 16px;background:#fef9e7;border-radius:6px;border-left:4px solid #f39c12;font-size:13px;color:#4a5568}
    .import-instructions strong{color:#1a2332}
    #dropZone{border:2px dashed #d1d5db;border-radius:8px;padding:20px;text-align:center;cursor:pointer;transition:all .3s;min-height:150px;display:flex;flex-direction:column;align-items:center;justify-content:center}
    #dropZone:hover{border-color:#c9a84c;background:#fefcf3}
    #previewFoto{max-width:150px;max-height:150px;border-radius:8px;margin:10px auto;object-fit:cover}
    .idades-turma-info{font-size:11px;color:#6b7280;margin-top:4px;padding:4px 8px;background:#f8fafc;border-radius:4px;border:1px solid #e5e7eb}
    .idades-turma-info strong{color:#1a2332}
    .id-field-wrapper{display:flex;gap:10px;align-items:center}
    .id-field-wrapper input{flex:1}
    .checkbox-auto-id{display:flex;align-items:center;gap:6px;font-size:13px;color:#4a5568;white-space:nowrap;cursor:pointer}
    .checkbox-auto-id input[type="checkbox"]{width:16px;height:16px;cursor:pointer;margin:0}
    .checkbox-auto-id:hover{color:#1a2332}
    @media(max-width:768px){.form-row{grid-template-columns:1fr;gap:0}.form-row-3{grid-template-columns:1fr;gap:0}.form-container,.import-section{padding:15px}.page-header{flex-direction:column;align-items:stretch}.form-actions{flex-direction:column}.form-actions .btn{justify-content:center}.nav-alunos{flex-direction:column;align-items:stretch}.nav-alunos a{text-align:center;justify-content:center}.import-section .import-form{flex-direction:column}.import-actions{width:100%}.import-actions .btn{flex:1;justify-content:center}.id-field-wrapper{flex-direction:column;align-items:stretch}}
</style>

<div class="page-header">
    <div>
        <h1>➕ Cadastrar Novo Aluno</h1>
        <p class="subtitle">Preencha todos os campos obrigatórios (*)</p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a href="?exportar_modelo=1" class="btn btn-info">📥 Baixar Modelo CSV</a>
        <a href="index.php" class="btn btn-secondary">← Voltar</a>
    </div>
</div>

<!-- Navegação -->
<div class="nav-alunos">
    <a href="index.php">📋 Lista de Alunos</a>
    <a href="add.php" class="active">➕ Cadastrar Aluno</a>
    <a href="reconfirmar.php">🔄 Reconfirmação</a>
    <a href="consulta.php">🔍 Consulta</a>
    <a href="relatorio.php">📈 Relatório</a>
</div>

<?php if ($sucesso): ?>
    <div class="notification-box">
        <div class="title">✅ <span>Cadastro Realizado com Sucesso!</span></div>
        <ul class="details">
            <?php foreach(explode('<br>', $sucesso) as $item): 
                if(trim($item)): ?>
                <li><?= $item ?></li>
            <?php endif; endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($erro): ?>
    <div class="alert alert-error">❌ <?= $erro ?></div>
<?php endif; ?>

<!-- ===== SEÇÃO DE IMPORTAÇÃO ===== -->
<div class="import-section">
    <h3>📤 Importar Alunos da Planilha CSV <span style="font-size:12px;font-weight:400;color:#6b7280;">(Arquivo .csv)</span></h3>
    
    <form method="POST" enctype="multipart/form-data" class="import-form">
        <div class="file-wrapper">
            <div class="file-input-custom" id="fileInputCustom">
                <span class="icon">📁</span>
                <span class="label" id="fileInputLabel">Clique para selecionar arquivo CSV</span>
                <span class="sub-label">Suporta arquivos .csv</span>
                <input type="file" name="arquivo_importacao" id="arquivo_importacao" accept=".csv" required>
            </div>
        </div>
        <div class="import-actions">
            <button type="submit" name="importar_planilha" class="btn btn-success" onclick="return confirm('Deseja importar os alunos do arquivo selecionado? Os IDs serão gerados automaticamente.')">
                📥 Importar
            </button>
            <button type="button" class="btn btn-info" onclick="window.location.href='?exportar_modelo=1'">📄 Baixar Modelo</button>
        </div>
    </form>
    
    <div class="import-instructions">
        <strong>ℹ️ Instruções:</strong> 
        Baixe o modelo CSV, preencha os dados e faça o upload para importar múltiplos alunos de uma vez.
        <strong>O ID será gerado automaticamente</strong> no formato <strong>ANO+SEQUENCIAL</strong> (ex: 2026032).
        <br>
        <strong>📌 IMPORTANTE:</strong> O arquivo deve estar no formato CSV com separador <strong>;</strong> (ponto e vírgula).
        <br>
        <strong>📌 Colunas esperadas:</strong> NOME, SEXO, DATA_NASC, MORADA, CONTACTO, CLASSE, CURSO, NATURALIDADE, MUNICIPIO, PROVINCIA, BI, NOME_PAI, CONTACTO_PAI, NOME_MAE, CONTACTO_MAE
        <br>
        <strong>📌 A TURMA e SALA serão atribuídas automaticamente</strong> baseado na CLASSE informada.
    </div>
</div>

<!-- ===== FORMULÁRIO DE CADASTRO INDIVIDUAL ===== -->
<div class="form-container">
    <form method="POST" enctype="multipart/form-data" id="formAluno">
        <!-- Dados Pessoais -->
        <div class="form-section">
            <div class="form-section-title">📌 Dados Pessoais</div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Nº Processo <span class="required">*</span></label>
                    <div class="id-field-wrapper">
                        <input type="text" name="id" id="id" value="<?= htmlspecialchars($_POST['id'] ?? $novo_id_gerado) ?>" placeholder="Ex: 2026032" required readonly style="background:#f1f5f9;">
                        <label class="checkbox-auto-id" title="Quando marcado, o ID é gerado automaticamente">
                            <input type="checkbox" name="gerar_id_auto" id="gerar_id_auto" value="1" checked onchange="toggleIdAutomatico()">
                            <span style="font-size:11px;">🔑 Auto</span>
                        </label>
                    </div>
                    <span class="help">ID gerado automaticamente no formato ANO+SEQUENCIAL (ex: 2026032). Desmarque "Auto" para inserir manualmente.</span>
                </div>
                <div class="form-group">
                    <label>Nome Completo <span class="required">*</span></label>
                    <input type="text" name="nome" id="nome" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Sexo <span class="required">*</span></label>
                    <select name="sexo" required>
                        <option value="M" <?= ($_POST['sexo'] ?? '') == 'M' ? 'selected' : '' ?>>Masculino</option>
                        <option value="F" <?= ($_POST['sexo'] ?? '') == 'F' ? 'selected' : '' ?>>Feminino</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Data de Nascimento <span class="required">*</span></label>
                    <div class="form-row-3" style="gap:5px;margin-bottom:0;">
                        <input type="number" name="dia" id="dia" placeholder="Dia" min="1" max="31" value="<?= $_POST['dia'] ?? '' ?>" required>
                        <input type="number" name="mes" id="mes" placeholder="Mês" min="1" max="12" value="<?= $_POST['mes'] ?? '' ?>" required>
                        <input type="number" name="ano" id="ano" placeholder="Ano" min="1900" max="<?= date('Y') ?>" value="<?= $_POST['ano'] ?? '' ?>" required>
                    </div>
                    <span class="help">Formato: Dia / Mês / Ano</span>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Idade</label>
                    <input type="number" name="idade" id="idade" value="<?= $_POST['idade'] ?? '' ?>" readonly style="background:#f1f5f9;">
                    <span class="help">Calculada automaticamente</span>
                </div>
                <div class="form-group">
                    <label>Morada</label>
                    <input type="text" name="morada" value="<?= htmlspecialchars($_POST['morada'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Transporte</label>
                    <select name="transporte">
                        <option value="Não" <?= ($_POST['transporte'] ?? '') == 'Não' ? 'selected' : '' ?>>Não</option>
                        <option value="Sim" <?= ($_POST['transporte'] ?? '') == 'Sim' ? 'selected' : '' ?>>Sim</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Contacto <span class="required">*</span></label>
                    <input type="tel" name="contacto" value="<?= htmlspecialchars($_POST['contacto'] ?? '') ?>" required pattern="[0-9]{9,10}">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Naturalidade</label>
                    <input type="text" name="naturalidade" value="<?= htmlspecialchars($_POST['naturalidade'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Debilidade</label>
                    <input type="text" name="debilidade" value="<?= htmlspecialchars($_POST['debilidade'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Município</label>
                    <input type="text" name="municipio" value="<?= htmlspecialchars($_POST['municipio'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Província</label>
                    <input type="text" name="provincia" value="<?= htmlspecialchars($_POST['provincia'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Nº BI</label>
                    <input type="text" name="bi" value="<?= htmlspecialchars($_POST['bi'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Data Emissão BI</label>
                    <input type="date" name="data_emissao_bi" value="<?= htmlspecialchars($_POST['data_emissao_bi'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Ocupação do Aluno</label>
                    <input type="text" name="ocupacao_aluno" value="<?= htmlspecialchars($_POST['ocupacao_aluno'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Arquivo Identificação</label>
                    <input type="text" name="arq_identificacao" value="<?= htmlspecialchars($_POST['arq_identificacao'] ?? '') ?>">
                </div>
            </div>
        </div>
        
        <!-- Dados Escolares -->
        <div class="form-section">
            <div class="form-section-title">🏫 Dados Escolares</div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Curso <span class="required">*</span></label>
                    <select name="curso" id="curso" required>
                        <option value="">Selecione um curso</option>
                        <?php foreach($cursos as $c): ?>
                        <option value="<?= htmlspecialchars($c['curso']) ?>" <?= ($_POST['curso'] ?? '') == $c['curso'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['curso']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Classe <span class="required">*</span></label>
                    <select name="classe" id="classe" required>
                        <option value="">Selecione uma classe</option>
                        <?php foreach($CLASSES_PRE_DEFINIDAS as $classe): ?>
                        <option value="<?= $classe ?>" <?= ($_POST['classe'] ?? '') == $classe ? 'selected' : '' ?>>
                            <?= $classe ?> Classe
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Turma</label>
                    <select name="turma_id" id="turma_id">
                        <option value="">Selecione uma turma (opcional)</option>
                        <?php foreach($turmas as $t): ?>
                        <option value="<?= $t['id'] ?>" 
                                data-classe="<?= $t['classe'] ?>" 
                                data-curso="<?= $t['curso'] ?>"
                                data-periodo="<?= $t['turno'] ?>"
                                data-sala="<?= $t['sala'] ?>"
                                data-idades="<?= htmlspecialchars($t['idades'] ?? '') ?>"
                                <?= ($_POST['turma_id'] ?? '') == $t['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['nome']) ?> (<?= $t['classe'] ?> - <?= $t['turno'] ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="idadesInfo" class="idades-turma-info" style="display:none;">
                        <strong>Idades permitidas:</strong> <span id="idadesPermitidas"></span>
                    </div>
                    <span class="help">Se não selecionar, a turma será atribuída automaticamente</span>
                </div>
                <div class="form-group">
                    <label>Período</label>
                    <input type="text" name="periodo" id="periodo" value="<?= htmlspecialchars($_POST['periodo'] ?? '') ?>" readonly style="background:#f1f5f9;">
                    <span class="help">Preenchido automaticamente pela turma selecionada</span>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Sala</label>
                    <input type="text" name="sala" id="sala" value="<?= htmlspecialchars($_POST['sala'] ?? '') ?>" readonly style="background:#f1f5f9;">
                    <span class="help">Preenchido automaticamente pela turma selecionada</span>
                </div>
                <div class="form-group">
                    <label>Data Matrícula</label>
                    <input type="date" name="data_matricula" value="<?= htmlspecialchars($_POST['data_matricula'] ?? date('Y-m-d')) ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>Situação</label>
                <select name="situacao">
                    <option value="Matrícula" <?= ($_POST['situacao'] ?? '') == 'Matrícula' ? 'selected' : '' ?>>Matrícula</option>
                    <option value="Confirmação" <?= ($_POST['situacao'] ?? '') == 'Confirmação' ? 'selected' : '' ?>>Confirmação</option>
                </select>
            </div>
        </div>
        
        <!-- Dados dos Pais -->
        <div class="form-section">
            <div class="form-section-title">👨‍👩‍👦 Dados dos Pais</div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Nome do Pai</label>
                    <input type="text" name="nome_pai" value="<?= htmlspecialchars($_POST['nome_pai'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Contacto do Pai</label>
                    <input type="tel" name="contacto_pai" value="<?= htmlspecialchars($_POST['contacto_pai'] ?? '') ?>" pattern="[0-9]{9,10}">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Ocupação do Pai</label>
                    <input type="text" name="ocupacao_pai" value="<?= htmlspecialchars($_POST['ocupacao_pai'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Local de Trabalho</label>
                    <input type="text" name="local_trabalho" value="<?= htmlspecialchars($_POST['local_trabalho'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Nome da Mãe</label>
                    <input type="text" name="nome_mae" value="<?= htmlspecialchars($_POST['nome_mae'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Contacto da Mãe</label>
                    <input type="tel" name="contacto_mae" value="<?= htmlspecialchars($_POST['contacto_mae'] ?? '') ?>" pattern="[0-9]{9,10}">
                </div>
            </div>
        </div>
        
        <!-- Foto do Aluno -->
        <div class="form-section">
            <div class="form-section-title">📸 Foto do Aluno</div>
            
            <div class="form-group">
                <label>Foto do Aluno</label>
                <div id="dropZone" onclick="document.getElementById('foto').click()">
                    <div id="previewContainer">
                        <span style="font-size:48px;display:block;">📷</span>
                        <span style="color:#94a3b8;font-size:13px;">Clique para selecionar uma foto</span>
                        <span style="color:#94a3b8;font-size:11px;display:block;">Formatos: JPG, PNG, GIF (máx. 5MB)</span>
                    </div>
                    <img id="previewFoto" style="display:none;max-width:150px;max-height:150px;border-radius:8px;margin:10px auto;object-fit:cover;">
                </div>
                <input type="file" name="foto" id="foto" accept="image/*" style="display:none;" onchange="previewFotoAluno(this)">
                <input type="hidden" name="foto_remover" id="foto_remover" value="0">
                <div style="margin-top:8px;">
                    <button type="button" class="btn btn-sm btn-danger" onclick="removerFotoSelecionada()" id="btnRemoverFoto" style="display:none;">🗑️ Remover Foto</button>
                </div>
                <span class="help">A foto será salva com o ID do aluno (Ex: 2026032.jpg)</span>
            </div>
        </div>
        
        <!-- Ações -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary" id="btnSubmit">💾 Cadastrar Aluno</button>
            <button type="reset" class="btn btn-secondary" onclick="limparFormulario()">🗑️ Limpar Campos</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script>
    // ===== TOGGLE ID AUTOMÁTICO =====
    function toggleIdAutomatico() {
        var checkbox = document.getElementById('gerar_id_auto');
        var idField = document.getElementById('id');
        if (checkbox.checked) {
            idField.readOnly = true;
            idField.style.background = '#f1f5f9';
            idField.value = '<?= $novo_id_gerado ?>';
        } else {
            idField.readOnly = false;
            idField.style.background = 'white';
            idField.value = '';
            idField.focus();
        }
    }
    
    // ===== CALCULAR IDADE =====
    document.querySelectorAll('input[name="dia"], input[name="mes"], input[name="ano"]').forEach(function(input) {
        input.addEventListener('change', function() {
            calcularIdade();
            validarIdadeComTurmaSelecionada();
        });
        input.addEventListener('keyup', function() {
            calcularIdade();
            validarIdadeComTurmaSelecionada();
        });
    });
    
    function calcularIdade() {
        var dia = parseInt(document.querySelector('input[name="dia"]').value) || 0;
        var mes = parseInt(document.querySelector('input[name="mes"]').value) || 0;
        var ano = parseInt(document.querySelector('input[name="ano"]').value) || 0;
        
        if (dia > 0 && mes > 0 && ano > 0) {
            var hoje = new Date();
            var dataNasc = new Date(ano, mes - 1, dia);
            var idade = hoje.getFullYear() - dataNasc.getFullYear();
            var m = hoje.getMonth() - dataNasc.getMonth();
            if (m < 0 || (m === 0 && hoje.getDate() < dataNasc.getDate())) {
                idade--;
            }
            if (idade >= 0) {
                document.getElementById('idade').value = idade;
                return idade;
            }
        }
        return 0;
    }
    
    // ===== ATUALIZAR TURMA INFO =====
    document.getElementById('turma_id').addEventListener('change', function() {
        var select = this;
        var option = select.options[select.selectedIndex];
        if (option.value) {
            document.getElementById('periodo').value = option.dataset.periodo || '';
            document.getElementById('sala').value = option.dataset.sala || '';
            
            var idades = option.dataset.idades || '';
            var infoDiv = document.getElementById('idadesInfo');
            var spanIdades = document.getElementById('idadesPermitidas');
            if (idades) {
                spanIdades.textContent = idades;
                infoDiv.style.display = 'block';
            } else {
                infoDiv.style.display = 'none';
            }
            
            validarIdadeComTurmaSelecionada();
        } else {
            document.getElementById('periodo').value = '';
            document.getElementById('sala').value = '';
            document.getElementById('idadesInfo').style.display = 'none';
        }
    });
    
    // ===== VALIDAR IDADE COM TURMA =====
    function validarIdadeComTurmaSelecionada() {
        var idade = parseInt(document.getElementById('idade').value) || 0;
        var turmaSelect = document.getElementById('turma_id');
        var option = turmaSelect.options[turmaSelect.selectedIndex];
        var btnSubmit = document.getElementById('btnSubmit');
        
        var msgExistente = document.getElementById('validacaoIdadeMsg');
        if (msgExistente) msgExistente.remove();
        
        if (idade > 0 && option.value) {
            var idadesPermitidas = option.dataset.idades || '';
            if (idadesPermitidas) {
                var valido = verificarIdadePermitida(idade, idadesPermitidas);
                
                var msg = document.createElement('div');
                msg.id = 'validacaoIdadeMsg';
                msg.style.marginTop = '8px';
                msg.style.padding = '8px 12px';
                msg.style.borderRadius = '6px';
                msg.style.fontSize = '13px';
                msg.style.fontWeight = '500';
                
                if (!valido) {
                    msg.style.background = '#fee2e2';
                    msg.style.color = '#991b1b';
                    msg.style.border = '1px solid #fecaca';
                    msg.innerHTML = '⚠️ Idade ' + idade + ' anos não compatível com esta turma. Idades permitidas: ' + idadesPermitidas + ' anos.';
                    btnSubmit.disabled = true;
                    btnSubmit.style.opacity = '0.5';
                    btnSubmit.style.cursor = 'not-allowed';
                } else {
                    msg.style.background = '#d1fae5';
                    msg.style.color = '#065f46';
                    msg.style.border = '1px solid #a7f3d0';
                    msg.innerHTML = '✅ Idade ' + idade + ' anos compatível com esta turma.';
                    btnSubmit.disabled = false;
                    btnSubmit.style.opacity = '1';
                    btnSubmit.style.cursor = 'pointer';
                }
                
                turmaSelect.parentNode.appendChild(msg);
            } else {
                btnSubmit.disabled = false;
                btnSubmit.style.opacity = '1';
                btnSubmit.style.cursor = 'pointer';
            }
        } else {
            btnSubmit.disabled = false;
            btnSubmit.style.opacity = '1';
            btnSubmit.style.cursor = 'pointer';
        }
    }
    
    function verificarIdadePermitida(idade, idadesPermitidas) {
        if (idadesPermitidas.indexOf('-') !== -1 || idadesPermitidas.indexOf(' a ') !== -1) {
            var intervalo = idadesPermitidas.replace(/\s*a\s*/, '-');
            var partes = intervalo.split('-');
            if (partes.length == 2) {
                var min = parseInt(partes[0].trim());
                var max = parseInt(partes[1].trim());
                return idade >= min && idade <= max;
            }
        }
        
        if (idadesPermitidas.indexOf(',') !== -1) {
            var idades = idadesPermitidas.split(',').map(function(item) {
                return parseInt(item.trim());
            });
            return idades.indexOf(idade) !== -1;
        }
        
        var idadePermitida = parseInt(idadesPermitidas.trim());
        return idade === idadePermitida;
    }
    
    // ===== PREVIEW DA FOTO =====
    function previewFotoAluno(input) {
        const preview = document.getElementById('previewFoto');
        const container = document.getElementById('previewContainer');
        const btnRemover = document.getElementById('btnRemoverFoto');
        const dropZone = document.getElementById('dropZone');
        
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
                container.style.display = 'none';
                btnRemover.style.display = 'inline-block';
                dropZone.style.borderColor = '#2ecc71';
                dropZone.style.background = '#f0fdf4';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    function removerFotoSelecionada() {
        const input = document.getElementById('foto');
        const preview = document.getElementById('previewFoto');
        const container = document.getElementById('previewContainer');
        const btnRemover = document.getElementById('btnRemoverFoto');
        const dropZone = document.getElementById('dropZone');
        
        input.value = '';
        preview.style.display = 'none';
        container.style.display = 'block';
        btnRemover.style.display = 'none';
        dropZone.style.borderColor = '#d1d5db';
        dropZone.style.background = 'transparent';
        document.getElementById('foto_remover').value = '1';
    }
    
    // ===== DRAG AND DROP =====
    const dropZone = document.getElementById('dropZone');
    if (dropZone) {
        dropZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.borderColor = '#c9a84c';
            this.style.background = '#fefcf3';
        });
        dropZone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.style.borderColor = '#d1d5db';
            this.style.background = 'transparent';
        });
        dropZone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.borderColor = '#d1d5db';
            this.style.background = 'transparent';
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('foto').files = files;
                previewFotoAluno(document.getElementById('foto'));
            }
        });
    }
    
    // ===== ARQUIVO DE IMPORTAÇÃO =====
    document.getElementById('arquivo_importacao').addEventListener('change', function() {
        var custom = document.getElementById('fileInputCustom');
        var label = document.getElementById('fileInputLabel');
        if (this.files && this.files[0]) {
            label.textContent = '📄 ' + this.files[0].name;
            custom.classList.add('has-file');
        } else {
            label.textContent = 'Clique para selecionar arquivo CSV';
            custom.classList.remove('has-file');
        }
    });
    
    // ===== LIMPAR FORMULÁRIO =====
    function limparFormulario() {
        document.querySelectorAll('input[type="text"], input[type="number"], input[type="tel"], input[type="date"], textarea').forEach(function(el) {
            el.value = '';
        });
        document.querySelectorAll('select').forEach(function(el) {
            el.selectedIndex = 0;
        });
        document.getElementById('idade').value = '';
        document.getElementById('periodo').value = '';
        document.getElementById('sala').value = '';
        document.getElementById('idadesInfo').style.display = 'none';
        document.getElementById('btnSubmit').disabled = false;
        document.getElementById('btnSubmit').style.opacity = '1';
        document.getElementById('btnSubmit').style.cursor = 'pointer';
        var msg = document.getElementById('validacaoIdadeMsg');
        if (msg) msg.remove();
        removerFotoSelecionada();
        
        document.getElementById('gerar_id_auto').checked = true;
        document.getElementById('id').readOnly = true;
        document.getElementById('id').style.background = '#f1f5f9';
        document.getElementById('id').value = '<?= $novo_id_gerado ?>';
    }
    
    // ===== VALIDAÇÃO ANTES DE ENVIAR =====
    document.getElementById('formAluno').addEventListener('submit', function(e) {
        var id = document.querySelector('input[name="id"]').value.trim();
        var nome = document.querySelector('input[name="nome"]').value.trim();
        var classe = document.querySelector('select[name="classe"]').value;
        var curso = document.querySelector('select[name="curso"]').value;
        var idade = parseInt(document.getElementById('idade').value) || 0;
        var turmaSelect = document.getElementById('turma_id');
        var option = turmaSelect.options[turmaSelect.selectedIndex];
        
        if (!id || !nome || !classe || !curso) {
            alert('Preencha todos os campos obrigatórios!');
            e.preventDefault();
            return false;
        }
        
        if (document.getElementById('btnSubmit').disabled) {
            alert('❌ A idade do aluno não é compatível com a turma selecionada!');
            e.preventDefault();
            return false;
        }
        
        if (turmaSelect.value && idade > 0) {
            var idadesPermitidas = option.dataset.idades || '';
            if (idadesPermitidas) {
                var valido = verificarIdadePermitida(idade, idadesPermitidas);
                if (!valido) {
                    alert('❌ Idade ' + idade + ' anos não é compatível com a turma selecionada!');
                    e.preventDefault();
                    return false;
                }
            }
        }
        
        return true;
    });
    
    // ===== INICIALIZAR =====
    document.addEventListener('DOMContentLoaded', function() {
        toggleIdAutomatico();
        
        var turmaSelect = document.getElementById('turma_id');
        if (turmaSelect.value) {
            var option = turmaSelect.options[turmaSelect.selectedIndex];
            var idades = option.dataset.idades || '';
            if (idades) {
                document.getElementById('idadesPermitidas').textContent = idades;
                document.getElementById('idadesInfo').style.display = 'block';
                var idade = parseInt(document.getElementById('idade').value) || 0;
                if (idade > 0) {
                    validarIdadeComTurmaSelecionada();
                }
            }
        }
    });
</script>

<?php include '../includes/footer_escola.php'; ?>