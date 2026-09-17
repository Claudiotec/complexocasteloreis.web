<?php
// config/database_connections.php
// Configuração para múltiplas conexões de banco de dados

// ============================================
// CONEXÃO LOCAL (MySQL - XAMPP)
// ============================================
define('DB_LOCAL_HOST', 'localhost');
define('DB_LOCAL_PORT', '3306');
define('DB_LOCAL_NAME', 'softgest_db');
define('DB_LOCAL_USER', 'root');
define('DB_LOCAL_PASS', '');

// ============================================
// CONEXÃO PÚBLICA (PostgreSQL - Neon.tech)
// ============================================
define('DB_PUBLIC_URL', 'postgresql://neondb_owner:npg_gRkKHXNJAI40@ep-holy-resonance-atm1ick2-pooler.c-9.us-east-1.aws.neon.tech/neondb?sslmode=require');

// Extrair informações da URL
function parsePublicDbUrl($url) {
    $parts = parse_url($url);
    return [
        'host' => $parts['host'] ?? '',
        'port' => $parts['port'] ?? 5432,
        'user' => $parts['user'] ?? '',
        'password' => $parts['pass'] ?? '',
        'dbname' => ltrim($parts['path'] ?? '', '/'),
        'sslmode' => 'require'
    ];
}

$public_db = parsePublicDbUrl(DB_PUBLIC_URL);

define('DB_PUBLIC_HOST', $public_db['host']);
define('DB_PUBLIC_PORT', $public_db['port']);
define('DB_PUBLIC_NAME', $public_db['dbname']);
define('DB_PUBLIC_USER', $public_db['user']);
define('DB_PUBLIC_PASS', $public_db['password']);
define('DB_PUBLIC_SSL', $public_db['sslmode']);

// ============================================
// TABELAS PARA SINCRONIZAÇÃO
// ============================================
$tabelas_sincronizacao = [
    'clientes' => [
        'campos' => ['id', 'nome', 'email', 'telefone', 'documento', 'endereco', 'cidade', 'estado', 'cep', 'status', 'created_at', 'updated_at'],
        'filtros' => ['status' => 'ativo'],
        'chave' => 'id'
    ],
    'produtos' => [
        'campos' => ['id', 'nome', 'descricao', 'codigo_barras', 'preco_custo', 'preco_venda', 'quantidade', 'quantidade_minima', 'categoria', 'status', 'created_at', 'updated_at'],
        'filtros' => ['status' => 'ativo'],
        'chave' => 'id'
    ],
    'usuarios' => [
        'campos' => ['id', 'nome', 'email', 'perfil', 'status', 'created_at', 'updated_at'],
        'filtros' => ['status' => 'ativo'],
        'chave' => 'id'
    ],
    'faturas_proforma' => [
        'campos' => ['id', 'numero_fatura', 'cliente_id', 'data_emissao', 'data_vencimento', 'valor_total', 'status', 'created_at', 'updated_at'],
        'filtros' => ['status' => 'pendente'],
        'chave' => 'id'
    ],
    'licencas' => [
        'campos' => ['id', 'codigo_licenca', 'chave_ativacao', 'tipo', 'status', 'cliente_nome', 'cliente_email', 'cliente_empresa', 'data_expiracao', 'max_usuarios', 'created_at', 'updated_at'],
        'filtros' => ['status' => 'ativa'],
        'chave' => 'id'
    ],
    'movimentacoes_caixa' => [
        'campos' => ['id', 'tipo', 'categoria', 'descricao', 'valor', 'data_movimento', 'status', 'usuario_id', 'created_at'],
        'filtros' => ['status' => 'confirmado'],
        'chave' => 'id'
    ]
];

// ============================================
// FUNÇÕES DE CONEXÃO
// ============================================

function conectarLocal() {
    try {
        $dsn = "mysql:host=" . DB_LOCAL_HOST . ";port=" . DB_LOCAL_PORT . ";dbname=" . DB_LOCAL_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_LOCAL_USER, DB_LOCAL_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Erro na conexão local: " . $e->getMessage());
    }
}

function conectarPublico() {
    try {
        $dsn = sprintf(
            "pgsql:host=%s;port=%s;dbname=%s;sslmode=%s",
            DB_PUBLIC_HOST,
            DB_PUBLIC_PORT,
            DB_PUBLIC_NAME,
            DB_PUBLIC_SSL
        );
        
        $pdo = new PDO($dsn, DB_PUBLIC_USER, DB_PUBLIC_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Erro na conexão pública: " . $e->getMessage());
    }
}

// ============================================
// FUNÇÕES DE SINCRONIZAÇÃO
// ============================================

function sincronizarDados($tabela, $direcao = 'publico_para_local', $filtros = []) {
    global $tabelas_sincronizacao;
    
    if (!isset($tabelas_sincronizacao[$tabela])) {
        return ['success' => false, 'message' => "Tabela '$tabela' não configurada para sincronização"];
    }
    
    $config = $tabelas_sincronizacao[$tabela];
    $campos = $config['campos'];
    $chave = $config['chave'];
    $filtros_padrao = $config['filtros'];
    
    // Mesclar filtros
    $filtros_finais = array_merge($filtros_padrao, $filtros);
    
    try {
        if ($direcao === 'publico_para_local') {
            return sincronizarPublicoParaLocal($tabela, $campos, $chave, $filtros_finais);
        } else {
            return sincronizarLocalParaPublico($tabela, $campos, $chave, $filtros_finais);
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function sincronizarPublicoParaLocal($tabela, $campos, $chave, $filtros) {
    $publico = conectarPublico();
    $local = conectarLocal();
    
    // Buscar dados do público
    $where = [];
    $params = [];
    foreach ($filtros as $campo => $valor) {
        if (in_array($campo, $campos)) {
            $where[] = "$campo = ?";
            $params[] = $valor;
        }
    }
    
    $sql = "SELECT " . implode(', ', $campos) . " FROM $tabela";
    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    
    $stmt = $publico->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll();
    
    if (empty($dados)) {
        return ['success' => true, 'message' => "Nenhum dado encontrado para sincronizar", 'total' => 0];
    }
    
    // Inserir/Atualizar no local
    $inseridos = 0;
    $atualizados = 0;
    
    foreach ($dados as $row) {
        // Verificar se existe
        $stmt = $local->prepare("SELECT COUNT(*) FROM $tabela WHERE $chave = ?");
        $stmt->execute([$row[$chave]]);
        $existe = $stmt->fetchColumn();
        
        if ($existe) {
            // Atualizar
            $set = [];
            $params = [];
            foreach ($campos as $campo) {
                if ($campo !== $chave) {
                    $set[] = "$campo = ?";
                    $params[] = $row[$campo];
                }
            }
            $params[] = $row[$chave];
            
            $sql = "UPDATE $tabela SET " . implode(', ', $set) . " WHERE $chave = ?";
            $stmt = $local->prepare($sql);
            $stmt->execute($params);
            $atualizados++;
        } else {
            // Inserir
            $placeholders = implode(', ', array_fill(0, count($campos), '?'));
            $sql = "INSERT INTO $tabela (" . implode(', ', $campos) . ") VALUES ($placeholders)";
            $stmt = $local->prepare($sql);
            $stmt->execute(array_values($row));
            $inseridos++;
        }
    }
    
    return [
        'success' => true,
        'message' => "Sincronização concluída!",
        'total' => count($dados),
        'inseridos' => $inseridos,
        'atualizados' => $atualizados
    ];
}

function sincronizarLocalParaPublico($tabela, $campos, $chave, $filtros) {
    $publico = conectarPublico();
    $local = conectarLocal();
    
    // Buscar dados do local
    $where = [];
    $params = [];
    foreach ($filtros as $campo => $valor) {
        if (in_array($campo, $campos)) {
            $where[] = "$campo = ?";
            $params[] = $valor;
        }
    }
    
    $sql = "SELECT " . implode(', ', $campos) . " FROM $tabela";
    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    
    $stmt = $local->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll();
    
    if (empty($dados)) {
        return ['success' => true, 'message' => "Nenhum dado encontrado para sincronizar", 'total' => 0];
    }
    
    // Inserir/Atualizar no público
    $inseridos = 0;
    $atualizados = 0;
    
    foreach ($dados as $row) {
        // Verificar se existe
        $stmt = $publico->prepare("SELECT COUNT(*) FROM $tabela WHERE $chave = ?");
        $stmt->execute([$row[$chave]]);
        $existe = $stmt->fetchColumn();
        
        if ($existe) {
            // Atualizar
            $set = [];
            $params = [];
            foreach ($campos as $campo) {
                if ($campo !== $chave) {
                    $set[] = "$campo = ?";
                    $params[] = $row[$campo];
                }
            }
            $params[] = $row[$chave];
            
            $sql = "UPDATE $tabela SET " . implode(', ', $set) . " WHERE $chave = ?";
            $stmt = $publico->prepare($sql);
            $stmt->execute($params);
            $atualizados++;
        } else {
            // Inserir
            $placeholders = implode(', ', array_fill(0, count($campos), '?'));
            $sql = "INSERT INTO $tabela (" . implode(', ', $campos) . ") VALUES ($placeholders)";
            $stmt = $publico->prepare($sql);
            $stmt->execute(array_values($row));
            $inseridos++;
        }
    }
    
    return [
        'success' => true,
        'message' => "Sincronização concluída!",
        'total' => count($dados),
        'inseridos' => $inseridos,
        'atualizados' => $atualizados
    ];
}
?>