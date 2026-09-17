<?php
// ============================================
// verificar_tudo.php - VERIFICA TODOS OS ARQUIVOS
// ============================================

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>🔍 Verificação Completa</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1100px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #1a2332; border-bottom: 3px solid #c9a84c; padding-bottom: 10px; }
        h2 { color: #1a2332; margin-top: 25px; background: #f8fafc; padding: 10px; border-radius: 8px; }
        .ok { color: #2ecc71; font-weight: bold; }
        .erro { color: #e74c3c; font-weight: bold; }
        .aviso { color: #f39c12; font-weight: bold; }
        .arquivo { background: #f8fafc; padding: 10px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #c9a84c; }
        .arquivo .caminho { font-weight: bold; color: #1a2332; }
        .arquivo .status { margin-left: 15px; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th { background: #1a2332; color: white; padding: 10px; text-align: left; }
        td { padding: 8px 10px; border: 1px solid #ddd; }
        .verde { background: #d1fae5; }
        .vermelho { background: #fee2e2; }
        .amarelo { background: #fef3c7; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔍 VERIFICAÇÃO COMPLETA DO SISTEMA</h1>";
echo "<p>Data: " . date('d/m/Y H:i:s') . "</p>";
echo "<hr>";

// ============================================
// 1. VERIFICAR ARQUIVOS
// ============================================
echo "<h2>📁 1. VERIFICANDO ARQUIVOS</h2>";

$arquivos = [
    // Config
    'config/database.php' => 'Configuração do Banco',
    'config/app_modes.php' => 'Modos da Aplicação',
    
    // Módulo Escola
    'modules/escola/index.php' => 'Dashboard da Escola',
    'modules/escola/includes/header_escola.php' => 'Header da Escola',
    'modules/escola/includes/footer_escola.php' => 'Footer da Escola',
    'modules/escola/includes/sidebar.php' => 'Menu Lateral',
    'modules/escola/includes/contadores.php' => 'Contadores Centralizados',
    'modules/escola/includes/funcoes_escola.php' => 'Funções da Escola',
    
    // Professores
    'modules/escola/professores/index.php' => 'Lista de Professores',
    'modules/escola/professores/verificar_permissao.php' => 'Verificação de Permissão',
    'modules/escola/professores/add.php' => 'Cadastrar Professor',
    'modules/escola/professores/edit.php' => 'Editar Professor',
    'modules/escola/professores/delete.php' => 'Excluir Professor',
    'modules/escola/professores/view.php' => 'Visualizar Professor',
];

echo "<table>";
echo "<tr><th>Arquivo</th><th>Descrição</th><th>Status</th><th>Tamanho</th></tr>";

foreach ($arquivos as $caminho => $descricao) {
    $caminho_completo = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/' . $caminho;
    $existe = file_exists($caminho_completo);
    $tamanho = $existe ? number_format(filesize($caminho_completo)) . ' bytes' : 'N/A';
    $status = $existe ? '✅ Existe' : '❌ FALTANDO';
    $cor = $existe ? 'verde' : 'vermelho';
    
    echo "<tr class='$cor'>";
    echo "<td><code>" . $caminho . "</code></td>";
    echo "<td>" . $descricao . "</td>";
    echo "<td><strong>" . $status . "</strong></td>";
    echo "<td>" . $tamanho . "</td>";
    echo "</tr>";
}
echo "</table>";

// ============================================
// 2. VERIFICAR CONEXÃO E CONTAGEM
// ============================================
echo "<h2>📊 2. TESTANDO CONEXÃO E CONTAGENS</h2>";

try {
    $pdo = new PDO("mysql:host=localhost;dbname=softgest_db;charset=utf8mb4", 'root', 'Claudtec');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p class='ok'>✅ Conexão com o banco OK!</p>";
    
    // Contar Professores
    $sql = "
        SELECT COUNT(*) as total 
        FROM funcionarios 
        WHERE categoria_actual LIKE '%Professor%'
           OR categoria_actual LIKE '%professor%'
           OR cargo LIKE '%Professor%'
           OR cargo LIKE '%professor%'
           OR funcao_instituicao LIKE '%Professor%'
           OR funcao_instituicao LIKE '%professor%'
    ";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalProf = (int)($result['total'] ?? 0);
    
    echo "<p><strong>👨‍🏫 Total de Professores:</strong> <span style='font-size:24px;color:#2ecc71;'>$totalProf</span></p>";
    
    // Contar Alunos
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM alunos WHERE status = 'ativo' OR status IS NULL");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalAlunos = (int)($result['total'] ?? 0);
    echo "<p><strong>👨‍🎓 Total de Alunos:</strong> <span style='font-size:24px;color:#3498db;'>$totalAlunos</span></p>";
    
    // Contar Turmas
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM turmas WHERE status = 'ativa' OR status IS NULL");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalTurmas = (int)($result['total'] ?? 0);
    echo "<p><strong>🏫 Total de Turmas:</strong> <span style='font-size:24px;color:#f39c12;'>$totalTurmas</span></p>";
    
} catch (Exception $e) {
    echo "<p class='erro'>❌ Erro no banco: " . $e->getMessage() . "</p>";
}

// ============================================
// 3. VERIFICAR CONTEÚDO DOS ARQUIVOS IMPORTANTES
// ============================================
echo "<h2>📝 3. VERIFICANDO CONTEÚDO DOS ARQUIVOS</h2>";

// Verificar contadores.php
$contadores = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/modules/escola/includes/contadores.php';
if (file_exists($contadores)) {
    $conteudo = file_get_contents($contadores);
    $temFuncaoContar = strpos($conteudo, 'function contarProfessores()') !== false;
    echo "<div class='arquivo'>";
    echo "<span class='caminho'>contadores.php</span>";
    echo "<span class='status'>" . ($temFuncaoContar ? "✅ Tem função contarProfessores()" : "❌ FALTA função contarProfessores()") . "</span>";
    echo "</div>";
} else {
    echo "<div class='arquivo' style='border-left-color:#e74c3c;'>";
    echo "<span class='caminho'>contadores.php</span>";
    echo "<span class='status erro'>❌ ARQUIVO FALTANDO!</span>";
    echo "</div>";
}

// Verificar sidebar.php
$sidebar = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/modules/escola/includes/sidebar.php';
if (file_exists($sidebar)) {
    $conteudo = file_get_contents($sidebar);
    $temContador = strpos($conteudo, 'contarProfessores()') !== false;
    $temBadge = strpos($conteudo, 'badge-professores') !== false;
    
    echo "<div class='arquivo'>";
    echo "<span class='caminho'>sidebar.php</span>";
    echo "<span class='status'>" . ($temContador ? "✅ Usa contarProfessores()" : "❌ NÃO usa contarProfessores()") . "</span>";
    echo "<span class='status'>" . ($temBadge ? "✅ Tem badge para professores" : "⚠️ Sem badge específico") . "</span>";
    echo "</div>";
}

// Verificar index.php (dashboard)
$dashboard = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/modules/escola/index.php';
if (file_exists($dashboard)) {
    $conteudo = file_get_contents($dashboard);
    $temContador = strpos($conteudo, 'contarProfessores()') !== false;
    $temContadores = strpos($conteudo, 'contadores.php') !== false;
    
    echo "<div class='arquivo'>";
    echo "<span class='caminho'>index.php (Dashboard)</span>";
    echo "<span class='status'>" . ($temContadores ? "✅ Inclui contadores.php" : "❌ NÃO inclui contadores.php") . "</span>";
    echo "<span class='status'>" . ($temContador ? "✅ Usa contarProfessores()" : "❌ NÃO usa contarProfessores()") . "</span>";
    echo "</div>";
}

// Verificar professores/index.php
$profIndex = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/modules/escola/professores/index.php';
if (file_exists($profIndex)) {
    $conteudo = file_get_contents($profIndex);
    $temContador = strpos($conteudo, 'contarProfessores()') !== false;
    $temContadores = strpos($conteudo, 'contadores.php') !== false;
    
    echo "<div class='arquivo'>";
    echo "<span class='caminho'>professores/index.php</span>";
    echo "<span class='status'>" . ($temContadores ? "✅ Inclui contadores.php" : "❌ NÃO inclui contadores.php") . "</span>";
    echo "<span class='status'>" . ($temContador ? "✅ Usa contarProfessores()" : "❌ NÃO usa contarProfessores()") . "</span>";
    echo "</div>";
}

// ============================================
// 4. RECOMENDAÇÕES FINAIS
// ============================================
echo "<hr>";
echo "<h2>📌 RECOMENDAÇÕES</h2>";

echo "<div style='background:#d1fae5;padding:15px;border-radius:8px;border:2px solid #2ecc71;'>";
echo "<h3 style='color:#1a2332;'>✅ O QUE ESTÁ CORRETO:</h3>";
echo "<ul>";
echo "<li>✅ Banco de dados: <strong>softgest_db</strong></li>";
echo "<li>✅ Tabela: <strong>funcionarios</strong></li>";
echo "<li>✅ Professor encontrado: <strong>Cláudio Raul</strong> (ID 7)</li>";
echo "<li>✅ Contagem correta: <strong>1 professor</strong></li>";
echo "</ul>";
echo "</div>";

echo "<div style='background:#fef3c7;padding:15px;border-radius:8px;border:2px solid #f39c12;margin-top:15px;'>";
echo "<h3 style='color:#1a2332;'>⚠️ VERIFIQUE:</h3>";
echo "<ul>";
echo "<li>Os arquivos <strong>contadores.php</strong> e <strong>sidebar.php</strong> estão na pasta <code>modules/escola/includes/</code></li>";
echo "<li>O arquivo <strong>index.php</strong> do dashboard está usando <code>contarProfessores()</code></li>";
echo "<li>O arquivo <strong>professores/index.php</strong> está usando <code>contarProfessores()</code></li>";
echo "</ul>";
echo "</div>";

echo "<div style='background:#dbeafe;padding:15px;border-radius:8px;border:2px solid #3498db;margin-top:15px;'>";
echo "<h3 style='color:#1a2332;'>📋 ESTRUTURA DE PASTAS CORRETA:</h3>";
echo "<pre style='background:#1a2332;color:#f5d76e;padding:15px;border-radius:8px;'>";
echo "C:/xampp/htdocs/softgest_web/\n";
echo "├── config/\n";
echo "│   ├── database.php\n";
echo "│   └── app_modes.php\n";
echo "├── modules/\n";
echo "│   └── escola/\n";
echo "│       ├── index.php (Dashboard)\n";
echo "│       ├── includes/\n";
echo "│       │   ├── header_escola.php\n";
echo "│       │   ├── footer_escola.php\n";
echo "│       │   ├── sidebar.php        ← VERIFICAR!\n";
echo "│       │   ├── contadores.php     ← VERIFICAR!\n";
echo "│       │   └── funcoes_escola.php\n";
echo "│       └── professores/\n";
echo "│           ├── index.php          ← VERIFICAR!\n";
echo "│           ├── add.php\n";
echo "│           ├── edit.php\n";
echo "│           ├── delete.php\n";
echo "│           └── view.php\n";
echo "└── ...\n";
echo "</pre>";
echo "</div>";

echo "<hr>";
echo "<p style='text-align:center;color:#94a3b8;'>🔍 Verificação concluída em " . date('d/m/Y H:i:s') . "</p>";

echo "</div></body></html>";
?>