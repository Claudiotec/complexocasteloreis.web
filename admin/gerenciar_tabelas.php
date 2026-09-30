<?php
// ============================================
// admin/gerenciar_tabelas.php
// Gerenciador de Tabelas — MySQL Local + Neon PostgreSQL
// Com limpeza: simples, múltipla e total
// ============================================

require_once '../config/database.php';
require_once '../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// ============================================
// SENHA DE ACESSO
// ============================================
$SENHA_MESTRA = 'Claudtec2011';
$senhaOk = $_SESSION['gerenciar_tabelas_ok'] ?? false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['senha_acesso'])) {
    if ($_POST['senha_acesso'] === $SENHA_MESTRA) {
        $_SESSION['gerenciar_tabelas_ok'] = true;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $erroSenha = '❌ Senha incorreta!';
    }
}

if (isset($_GET['sair'])) {
    unset($_SESSION['gerenciar_tabelas_ok']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$usuario_nome = $_SESSION['usuario_nome'] ?? 'Admin';

// ============================================
// CONFIGURAÇÕES DAS CONEXÕES
// ============================================
define('MYSQL_LOCAL', [
    'host' => 'localhost',
    'name' => 'softgest_db',
    'user' => 'root',
    'pass' => '',
]);

define('NEON_URL', 'postgresql://neondb_owner:npg_xKFNESzC5pt2@ep-aged-paper-b4jtvclh-pooler.c-6.us-east-2.aws.neon.tech/neondb?sslmode=require');

// ============================================
// SE SENHA NÃO OK → TELA DE SENHA
// ============================================
if (!$senhaOk) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔒 Acesso Restrito</title>
    <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body {
        font-family: 'Segoe UI', Tahoma, sans-serif;
        background: linear-gradient(135deg, #1a2332 0%, #2c3e50 100%);
        min-height: 100vh;
        display: flex; align-items: center; justify-content: center;
        padding: 20px;
    }
    .lock-card {
        background: rgba(255,255,255,0.05);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 20px;
        padding: 45px 40px;
        max-width: 420px; width: 100%;
        text-align: center;
        box-shadow: 0 20px 60px rgba(0,0,0,0.4);
        animation: fadeIn 0.5s ease;
    }
    @keyframes fadeIn { from{opacity:0;transform:translateY(20px);} to{opacity:1;transform:translateY(0);} }
    .lock-icon { font-size: 64px; margin-bottom: 15px; display: block; animation: pulse 2s infinite; }
    @keyframes pulse { 0%,100%{transform:scale(1);} 50%{transform:scale(1.1);} }
    .lock-card h1 { color: #c9a84c; font-size: 24px; margin-bottom: 8px; font-weight: 800; }
    .lock-card p { color: #94a3b8; font-size: 14px; margin-bottom: 30px; }
    .lock-input {
        width: 100%; padding: 15px 20px;
        background: rgba(0,0,0,0.3);
        border: 2px solid rgba(255,255,255,0.1);
        border-radius: 12px; color: #fff;
        font-size: 16px; font-family: inherit;
        letter-spacing: 3px; text-align: center;
        transition: all 0.3s; margin-bottom: 15px;
    }
    .lock-input:focus { outline:none; border-color:#c9a84c; background:rgba(0,0,0,0.5); box-shadow:0 0 20px rgba(201,168,76,0.3); }
    .lock-btn {
        width: 100%; padding: 15px;
        background: linear-gradient(135deg, #c9a84c, #f5d76e);
        color: #1a2332; border: none; border-radius: 12px;
        font-size: 16px; font-weight: 700; cursor: pointer;
        transition: all 0.3s; font-family: inherit;
    }
    .lock-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(201,168,76,0.4); }
    .erro {
        background: rgba(231,76,60,0.2);
        border: 1px solid rgba(231,76,60,0.4);
        color: #fca5a5; padding: 12px; border-radius: 8px;
        font-size: 14px; margin-bottom: 15px;
    }
    .voltar-link { display:inline-block; margin-top:20px; color:#94a3b8; text-decoration:none; font-size:13px; }
    .voltar-link:hover { color:#c9a84c; }
    </style>
    </head>
    <body>
        <div class="lock-card">
            <span class="lock-icon">🔒</span>
            <h1>Acesso Restrito</h1>
            <p>Digite a senha para acessar o gerenciador de tabelas</p>

            <?php if (!empty($erroSenha)): ?>
                <div class="erro"><?= htmlspecialchars($erroSenha) ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <input type="password" name="senha_acesso" class="lock-input"
                       placeholder="••••••••••" autofocus required>
                <button type="submit" class="lock-btn">🔓 Desbloquear</button>
            </form>

            <a href="../index.php" class="voltar-link">← Voltar ao sistema</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ============================================
// CONEXÕES
// ============================================
function conexaoMysqlLocal() {
    return new PDO(
        "mysql:host=" . MYSQL_LOCAL['host'] . ";dbname=" . MYSQL_LOCAL['name'] . ";charset=utf8mb4",
        MYSQL_LOCAL['user'],
        MYSQL_LOCAL['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

function conexaoNeon() {
    $url = parse_url(NEON_URL);
    $host = $url['host'];
    $port = $url['port'] ?? 5432;
    $user = $url['user'];
    $pass = $url['pass'];
    $db   = ltrim($url['path'], '/');

    $partesHost = explode('.', $host);
    $endpoint = $partesHost[0] ?? '';

    $dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require;options='endpoint=$endpoint'";

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 30,
    ]);
}

// ============================================
// SELEÇÃO DE BANCO
// ============================================
$bancoAtual = $_SESSION['gerenciar_banco'] ?? 'mysql';
if (isset($_GET['banco']) && in_array($_GET['banco'], ['mysql', 'neon'], true)) {
    $bancoAtual = $_GET['banco'];
    $_SESSION['gerenciar_banco'] = $bancoAtual;
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

try {
    $pdo = ($bancoAtual === 'neon') ? conexaoNeon() : conexaoMysqlLocal();
} catch (PDOException $e) {
    die("❌ Erro de conexão com <b>" . strtoupper($bancoAtual) . "</b>: " . $e->getMessage());
}

$driver = ($bancoAtual === 'neon') ? 'pgsql' : 'mysql';

// ============================================
// HELPERS SQL
// ============================================
function listarTabelas($pdo, $driver) {
    if ($driver === 'pgsql') {
        $stmt = $pdo->query("
            SELECT tablename FROM pg_tables
            WHERE schemaname = 'public'
            ORDER BY tablename
        ");
    } else {
        $stmt = $pdo->query("SHOW TABLES");
    }
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function estruturaTabela($pdo, $driver, $tabela) {
    if ($driver === 'pgsql') {
        $stmt = $pdo->prepare("
            SELECT column_name AS \"Field\",
                   data_type AS \"Type\",
                   is_nullable AS \"Null\",
                   column_default AS \"Default\"
            FROM information_schema.columns
            WHERE table_schema = 'public' AND table_name = ?
            ORDER BY ordinal_position
        ");
        $stmt->execute([$tabela]);
        $cols = $stmt->fetchAll();

        $stmtPk = $pdo->prepare("
            SELECT kcu.column_name
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
              ON tc.constraint_name = kcu.constraint_name
            WHERE tc.table_schema = 'public'
              AND tc.table_name = ?
              AND tc.constraint_type = 'PRIMARY KEY'
            LIMIT 1
        ");
        $stmtPk->execute([$tabela]);
        $pk = $stmtPk->fetchColumn() ?: null;

        return ['colunas' => $cols, 'pk' => $pk];
    } else {
        $stmt = $pdo->query("DESCRIBE `$tabela`");
        $cols = $stmt->fetchAll();
        $pk = null;
        foreach ($cols as $c) {
            if ($c['Key'] === 'PRI') { $pk = $c['Field']; break; }
        }
        return ['colunas' => $cols, 'pk' => $pk];
    }
}

function quoteId($nome, $driver) {
    if ($driver === 'pgsql') return '"' . str_replace('"', '""', $nome) . '"';
    return '`' . str_replace('`', '``', $nome) . '`';
}

// ============================================
// FUNÇÃO AUXILIAR: EXECUTA TRUNCATE EM UMA TABELA
// ============================================
function truncarTabela($pdo, $driver, $tabela) {
    $tq = quoteId($tabela, $driver);
    $antes = (int)$pdo->query("SELECT COUNT(*) FROM $tq")->fetchColumn();

    if ($driver === 'pgsql') {
        $pdo->exec("TRUNCATE TABLE $tq RESTART IDENTITY CASCADE");
    } else {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        try {
            $pdo->exec("TRUNCATE TABLE $tq");
        } finally {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        }
    }

    return $antes;
}

// ============================================
// AÇÕES VIA AJAX
// ============================================
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $acao = $_GET['ajax'];

    try {
        // ---------- LISTAR TABELAS ----------
        if ($acao === 'tabelas') {
            $tabelas = listarTabelas($pdo, $driver);
            echo json_encode([
                'success' => true,
                'tabelas' => $tabelas,
                'banco'   => $bancoAtual
            ]);
            exit;
        }

        // ---------- ESTRUTURA ----------
        if ($acao === 'estrutura') {
            $tabela = $_GET['tabela'] ?? '';
            if (!$tabela || !preg_match('/^[a-zA-Z0-9_]+$/', $tabela)) {
                throw new Exception('Nome de tabela inválido');
            }
            $info = estruturaTabela($pdo, $driver, $tabela);
            echo json_encode([
                'success' => true,
                'colunas' => $info['colunas'],
                'pk' => $info['pk'],
                'banco' => $bancoAtual
            ]);
            exit;
        }

        // ---------- LISTAR DADOS ----------
        if ($acao === 'dados') {
            $tabela = $_GET['tabela'] ?? '';
            if (!$tabela || !preg_match('/^[a-zA-Z0-9_]+$/', $tabela)) {
                throw new Exception('Nome de tabela inválido');
            }

            $pagina = max(1, (int)($_GET['pagina'] ?? 1));
            $limite = max(5, min(100, (int)($_GET['limite'] ?? 20)));
            $offset = ($pagina - 1) * $limite;

            $busca = trim($_GET['busca'] ?? '');
            $colunaBusca = trim($_GET['coluna_busca'] ?? '');
            $ordem = trim($_GET['ordem'] ?? '');
            $direcao = strtoupper($_GET['direcao'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

            $info = estruturaTabela($pdo, $driver, $tabela);
            $nomesColunas = array_map(fn($c) => $c['Field'], $info['colunas']);

            $where = '';
            $params = [];
            if ($busca !== '' && $colunaBusca !== '' && in_array($colunaBusca, $nomesColunas, true)) {
                if ($driver === 'pgsql') {
                    $where = "WHERE CAST(" . quoteId($colunaBusca, $driver) . " AS TEXT) ILIKE :busca";
                } else {
                    $where = "WHERE " . quoteId($colunaBusca, $driver) . " LIKE :busca";
                }
                $params[':busca'] = '%' . $busca . '%';
            }

            $orderSql = '';
            if ($ordem !== '' && in_array($ordem, $nomesColunas, true)) {
                $orderSql = "ORDER BY " . quoteId($ordem, $driver) . " $direcao";
            }

            $tq = quoteId($tabela, $driver);

            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM $tq $where");
            $stmtCount->execute($params);
            $total = (int)$stmtCount->fetchColumn();

            $sql = "SELECT * FROM $tq $where $orderSql LIMIT $limite OFFSET $offset";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $dados = $stmt->fetchAll();

            $dados = array_map(function($row) {
                foreach ($row as $k => $v) {
                    if ($v instanceof \DateTimeInterface) {
                        $row[$k] = $v->format('Y-m-d H:i:s');
                    } elseif (is_bool($v)) {
                        $row[$k] = $v ? 'true' : 'false';
                    } elseif (is_resource($v)) {
                        $row[$k] = stream_get_contents($v);
                    }
                }
                return $row;
            }, $dados);

            echo json_encode([
                'success' => true,
                'dados' => $dados,
                'colunas' => $nomesColunas,
                'total' => $total,
                'pagina' => $pagina,
                'limite' => $limite,
                'total_paginas' => ceil($total / $limite),
                'banco' => $bancoAtual
            ]);
            exit;
        }

        // ---------- EDITAR ----------
        if ($acao === 'editar') {
            $input = json_decode(file_get_contents('php://input'), true);
            $tabela = $input['tabela'] ?? '';
            $pk = $input['pk'] ?? '';
            $pkValor = $input['pk_valor'] ?? null;
            $dados = $input['dados'] ?? [];

            if (!$tabela || !$pk || $pkValor === null) throw new Exception('Dados incompletos');
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $tabela)) throw new Exception('Tabela inválida');
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $pk)) throw new Exception('PK inválida');

            $info = estruturaTabela($pdo, $driver, $tabela);
            $colunasValidas = array_map(fn($c) => $c['Field'], $info['colunas']);

            $sets = [];
            $params = [':pk_valor' => $pkValor];
            $i = 0;
            foreach ($dados as $col => $val) {
                if (!in_array($col, $colunasValidas, true)) continue;
                if ($col === $pk) continue;
                $ph = ":col_$i";
                $sets[] = quoteId($col, $driver) . " = $ph";
                $params[$ph] = $val;
                $i++;
            }

            if (empty($sets)) throw new Exception('Nada para atualizar');

            $tq = quoteId($tabela, $driver);
            $pkq = quoteId($pk, $driver);

            $sql = "UPDATE $tq SET " . implode(', ', $sets) . " WHERE $pkq = :pk_valor";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            echo json_encode(['success' => true, 'afetados' => $stmt->rowCount()]);
            exit;
        }

        // ---------- REMOVER MÚLTIPLOS ----------
        if ($acao === 'remover') {
            $input = json_decode(file_get_contents('php://input'), true);
            $tabela = $input['tabela'] ?? '';
            $pk = $input['pk'] ?? '';
            $ids = $input['ids'] ?? [];

            if (!$tabela || !$pk || empty($ids)) throw new Exception('Dados incompletos');
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $tabela)) throw new Exception('Tabela inválida');
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $pk)) throw new Exception('PK inválida');

            $placeholders = [];
            $params = [];
            foreach ($ids as $i => $id) {
                $ph = ":id_$i";
                $placeholders[] = $ph;
                $params[$ph] = $id;
            }

            $tq = quoteId($tabela, $driver);
            $pkq = quoteId($pk, $driver);

            $sql = "DELETE FROM $tq WHERE $pkq IN (" . implode(',', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            echo json_encode(['success' => true, 'removidos' => $stmt->rowCount()]);
            exit;
        }

        // ---------- LIMPAR UMA TABELA ----------
        if ($acao === 'limpar') {
            $input = json_decode(file_get_contents('php://input'), true);
            $tabela = $input['tabela'] ?? '';
            $confirmacao = trim($input['confirmacao'] ?? '');

            if (!$tabela) throw new Exception('Tabela não informada');
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $tabela)) throw new Exception('Nome de tabela inválido');

            if ($confirmacao !== $tabela) {
                throw new Exception('Confirmação inválida — digite o nome exato da tabela');
            }

            $removidos = truncarTabela($pdo, $driver, $tabela);

            echo json_encode([
                'success' => true,
                'tabela' => $tabela,
                'removidos' => $removidos,
                'banco' => $bancoAtual
            ]);
            exit;
        }

        // ---------- LIMPAR MÚLTIPLAS TABELAS ----------
        if ($acao === 'limpar_multiplas') {
            $input = json_decode(file_get_contents('php://input'), true);
            $tabelas = $input['tabelas'] ?? [];
            $confirmacao = trim($input['confirmacao'] ?? '');

            if (empty($tabelas) || !is_array($tabelas)) {
                throw new Exception('Nenhuma tabela informada');
            }

            // Frase exata exigida
            if ($confirmacao !== 'LIMPAR SELECIONADAS') {
                throw new Exception('Confirmação inválida — digite exatamente: LIMPAR SELECIONADAS');
            }

            $totalRemovidos = 0;
            $detalhes = [];
            $erros = [];

            foreach ($tabelas as $t) {
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $t)) {
                    $erros[] = "$t (nome inválido)";
                    continue;
                }
                try {
                    $removidos = truncarTabela($pdo, $driver, $t);
                    $totalRemovidos += $removidos;
                    $detalhes[$t] = $removidos;
                } catch (Exception $e) {
                    $erros[] = "$t: " . $e->getMessage();
                }
            }

            echo json_encode([
                'success' => true,
                'tabelas' => count($detalhes),
                'removidos' => $totalRemovidos,
                'detalhes' => $detalhes,
                'erros' => $erros,
                'banco' => $bancoAtual
            ]);
            exit;
        }

        // ---------- LIMPAR TODAS AS TABELAS ----------
        if ($acao === 'limpar_todas') {
            $input = json_decode(file_get_contents('php://input'), true);
            $confirmacao = trim($input['confirmacao'] ?? '');

            // Frase exata obrigatória (mais forte ainda)
            if ($confirmacao !== 'APAGAR TUDO') {
                throw new Exception('Confirmação inválida — digite exatamente: APAGAR TUDO');
            }

            $tabelas = listarTabelas($pdo, $driver);

            // Em PostgreSQL, usar TRUNCATE ... CASCADE em bloco é muito mais eficiente
            if ($driver === 'pgsql' && !empty($tabelas)) {
                $totalRemovidos = 0;
                $detalhes = [];

                // Conta antes
                foreach ($tabelas as $t) {
                    try {
                        $tq = quoteId($t, $driver);
                        $detalhes[$t] = (int)$pdo->query("SELECT COUNT(*) FROM $tq")->fetchColumn();
                        $totalRemovidos += $detalhes[$t];
                    } catch (Exception $e) {
                        $detalhes[$t] = 0;
                    }
                }

                // Trunca todas em bloco
                $lista = implode(', ', array_map(fn($t) => quoteId($t, $driver), $tabelas));
                $pdo->exec("TRUNCATE TABLE $lista RESTART IDENTITY CASCADE");

                echo json_encode([
                    'success' => true,
                    'tabelas' => count($tabelas),
                    'removidos' => $totalRemovidos,
                    'detalhes' => $detalhes,
                    'erros' => [],
                    'banco' => $bancoAtual
                ]);
                exit;
            }

            // MySQL: truncar uma a uma com FK check desabilitado
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

            $totalRemovidos = 0;
            $detalhes = [];
            $erros = [];

            try {
                foreach ($tabelas as $t) {
                    try {
                        $tq = quoteId($t, $driver);
                        $antes = (int)$pdo->query("SELECT COUNT(*) FROM $tq")->fetchColumn();
                        $pdo->exec("TRUNCATE TABLE $tq");
                        $totalRemovidos += $antes;
                        $detalhes[$t] = $antes;
                    } catch (Exception $e) {
                        $erros[] = "$t: " . $e->getMessage();
                    }
                }
            } finally {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            }

            echo json_encode([
                'success' => true,
                'tabelas' => count($detalhes),
                'removidos' => $totalRemovidos,
                'detalhes' => $detalhes,
                'erros' => $erros,
                'banco' => $bancoAtual
            ]);
            exit;
        }

        // ---------- ESTATÍSTICAS ----------
        if ($acao === 'stats') {
            $tabelas = listarTabelas($pdo, $driver);
            $stats = [];
            foreach ($tabelas as $t) {
                try {
                    $tq = quoteId($t, $driver);
                    $total = (int)$pdo->query("SELECT COUNT(*) FROM $tq")->fetchColumn();
                    $stats[$t] = $total;
                } catch (Exception $e) {
                    $stats[$t] = 0;
                }
            }
            echo json_encode(['success' => true, 'stats' => $stats, 'banco' => $bancoAtual]);
            exit;
        }

        throw new Exception('Ação desconhecida');

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>🗄️ Gerenciador de Tabelas</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: 'Segoe UI', Tahoma, sans-serif;
    background: linear-gradient(135deg, #1a2332 0%, #2c3e50 100%);
    min-height: 100vh;
    color: #e2e8f0;
    padding: 20px;
}
.container { max-width: 1600px; margin: 0 auto; }

.header {
    display: flex; justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: 15px; margin-bottom: 25px;
    animation: fadeInDown 0.5s ease;
}
.header h1 {
    font-size: 28px; font-weight: 800;
    background: linear-gradient(135deg, #c9a84c, #f5d76e);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text;
}
.header p { color: #94a3b8; font-size: 14px; margin-top: 4px; }
.header-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }

.db-switcher {
    display: inline-flex;
    background: rgba(0,0,0,0.3);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px;
    overflow: hidden;
}
.db-switcher a {
    padding: 10px 20px; color: #94a3b8; text-decoration: none;
    font-size: 13px; font-weight: 600; transition: all 0.3s;
    display: flex; align-items: center; gap: 6px;
}
.db-switcher a:hover { background: rgba(255,255,255,0.05); color: #fff; }
.db-switcher a.active {
    background: linear-gradient(135deg, #c9a84c, #f5d76e);
    color: #1a2332;
}
.db-switcher a.active.pg {
    background: linear-gradient(135deg, #336791, #4a90c2);
    color: #fff;
}

.btn {
    padding: 10px 20px; border-radius: 8px; border: none;
    font-size: 14px; font-weight: 600; cursor: pointer;
    transition: all 0.3s; display: inline-flex; align-items: center;
    gap: 6px; text-decoration: none; font-family: inherit;
}
.btn:hover { transform: translateY(-2px); }
.btn-primary { background: linear-gradient(135deg, #c9a84c, #f5d76e); color: #1a2332; }
.btn-danger { background: linear-gradient(135deg, #e74c3c, #c0392b); color: #fff; }
.btn-secondary { background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(255,255,255,0.2); }
.btn-secondary:hover { background: rgba(255,255,255,0.2); }
.btn-warning { background: linear-gradient(135deg, #f39c12, #e67e22); color: #fff; }
.btn-warning:hover { box-shadow: 0 8px 25px rgba(243,156,18,0.4); }
.btn-black { background: linear-gradient(135deg, #2c3e50, #1a2332); color: #fff; border: 1px solid rgba(231,76,60,0.4); }
.btn-black:hover { box-shadow: 0 8px 25px rgba(0,0,0,0.5); border-color: #e74c3c; }
.btn-sm { padding: 6px 12px; font-size: 12px; }
.btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; }

.main-layout {
    display: grid; grid-template-columns: 320px 1fr;
    gap: 20px; align-items: start;
}

.tabelas-panel {
    background: rgba(255,255,255,0.05);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 14px; padding: 18px;
    max-height: calc(100vh - 140px);
    overflow-y: auto; position: sticky; top: 20px;
}
.tabelas-panel h2 {
    color: #c9a84c; font-size: 15px; margin-bottom: 12px;
    text-transform: uppercase; letter-spacing: 0.5px;
}
.tabelas-search {
    width: 100%; padding: 10px 14px;
    background: rgba(0,0,0,0.3);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px; color: #fff;
    font-size: 13px; font-family: inherit; margin-bottom: 12px;
}
.tabelas-search:focus { outline: none; border-color: #c9a84c; }
.tabela-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 12px; border-radius: 8px; cursor: pointer;
    transition: all 0.2s; margin-bottom: 4px; font-size: 13px;
}
.tabela-item:hover { background: rgba(255,255,255,0.08); }
.tabela-item.active {
    background: rgba(201,168,76,0.2);
    border-left: 3px solid #c9a84c;
    color: #c9a84c; font-weight: 600;
}
.tabela-item .nome { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tabela-item .count {
    background: rgba(0,0,0,0.4); color: #c9a84c;
    font-size: 11px; padding: 2px 8px; border-radius: 10px;
    font-weight: 700; margin-left: 8px;
}

/* Barra de ações de limpeza no topo da sidebar */
.bulk-actions {
    display: flex;
    gap: 6px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}
.bulk-actions .btn {
    padding: 6px 10px;
    font-size: 11px;
    flex: 1;
    min-width: 100px;
    justify-content: center;
}

.content-panel {
    background: rgba(255,255,255,0.05);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 14px; padding: 20px; min-height: 500px;
}

.empty-state {
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    height: 400px; color: #94a3b8; text-align: center;
}
.empty-state .icon { font-size: 64px; margin-bottom: 15px; opacity: 0.5; }
.empty-state h3 { font-size: 18px; margin-bottom: 8px; color: #cbd5e0; }
.empty-state p { font-size: 14px; }

.toolbar {
    display: flex; justify-content: space-between;
    align-items: center; flex-wrap: wrap; gap: 12px;
    margin-bottom: 15px; padding-bottom: 15px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
}
.toolbar-left, .toolbar-right { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.toolbar input, .toolbar select {
    padding: 8px 12px; background: rgba(0,0,0,0.3);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px; color: #fff;
    font-size: 13px; font-family: inherit;
}
.toolbar input:focus, .toolbar select:focus { outline: none; border-color: #c9a84c; }
.toolbar select option { background: #1a2332; color: #fff; }

.tabela-titulo {
    font-size: 18px; font-weight: 700; color: #c9a84c;
    display: flex; align-items: center; gap: 8px;
}
.db-badge {
    font-size: 10px; padding: 3px 10px; border-radius: 12px;
    font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.5px;
}
.db-badge.mysql { background: rgba(52,152,219,0.25); color: #3498db; }
.db-badge.neon  { background: rgba(51,103,145,0.3); color: #4a90c2; }

.table-wrapper {
    overflow-x: auto; border-radius: 10px;
    border: 1px solid rgba(255,255,255,0.08);
}
.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.data-table thead th {
    background: rgba(201,168,76,0.15); color: #c9a84c;
    padding: 12px 10px; text-align: left;
    font-size: 11px; text-transform: uppercase; font-weight: 700;
    border-bottom: 2px solid rgba(201,168,76,0.3);
    white-space: nowrap; position: sticky; top: 0;
    cursor: pointer; user-select: none;
}
.data-table thead th:hover { background: rgba(201,168,76,0.25); }
.data-table thead th.sortable::after { content: ' ⇅'; opacity: 0.4; font-size: 10px; }
.data-table tbody td {
    padding: 10px; border-bottom: 1px solid rgba(255,255,255,0.05);
    color: #e2e8f0; max-width: 300px; overflow: hidden;
    text-overflow: ellipsis; white-space: nowrap;
}
.data-table tbody tr:hover { background: rgba(255,255,255,0.03); }
.data-table tbody tr.selected { background: rgba(201,168,76,0.1); }

.col-check { width: 40px; text-align: center; }
.col-actions { width: 130px; text-align: right; white-space: nowrap; }

.checkbox-custom { width: 18px; height: 18px; accent-color: #c9a84c; cursor: pointer; }

.pagination {
    display: flex; justify-content: space-between;
    align-items: center; flex-wrap: wrap; gap: 12px;
    margin-top: 15px; padding-top: 15px;
    border-top: 1px solid rgba(255,255,255,0.08);
}
.pagination-info { color: #94a3b8; font-size: 13px; }
.pagination-buttons { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.page-btn {
    min-width: 34px; height: 34px; padding: 0 10px;
    border-radius: 8px; background: rgba(255,255,255,0.08);
    color: #e2e8f0; border: 1px solid rgba(255,255,255,0.1);
    cursor: pointer; font-size: 13px; font-weight: 600;
    transition: all 0.2s; font-family: inherit;
}
.page-btn:hover:not(:disabled) { background: rgba(201,168,76,0.2); border-color: #c9a84c; }
.page-btn.active { background: linear-gradient(135deg, #c9a84c, #f5d76e); color: #1a2332; border-color: transparent; }
.page-btn:disabled { opacity: 0.3; cursor: not-allowed; }

.modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,0.7); backdrop-filter: blur(4px);
    z-index: 9999; align-items: center; justify-content: center;
    padding: 20px;
}
.modal-overlay.active { display: flex; }
.modal {
    background: #1e2936; border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px; padding: 25px;
    max-width: 700px; width: 100%; max-height: 90vh;
    overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.5);
    animation: modalIn 0.3s ease;
}
@keyframes modalIn { from{opacity:0;transform:scale(0.95);} to{opacity:1;transform:scale(1);} }
.modal-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 20px; padding-bottom: 15px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.modal-header h3 { color: #c9a84c; font-size: 18px; }
.modal-header h3.danger { color: #e74c3c; }
.modal-header h3.critical {
    color: #ff2d55;
    text-shadow: 0 0 20px rgba(255,45,85,0.6);
    animation: pulse 1.5s infinite;
}
.modal-close {
    background: none; border: none; color: #94a3b8;
    font-size: 24px; cursor: pointer; padding: 0 8px;
    transition: color 0.2s;
}
.modal-close:hover { color: #e74c3c; }

.form-group { margin-bottom: 15px; }
.form-group label {
    display: block; color: #94a3b8; font-size: 12px;
    margin-bottom: 6px; text-transform: uppercase;
    font-weight: 600; letter-spacing: 0.5px;
}
.form-group input, .form-group textarea, .form-group select {
    width: 100%; padding: 10px 14px;
    background: rgba(0,0,0,0.3);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px; color: #fff;
    font-size: 14px; font-family: inherit;
}
.form-group input:focus, .form-group textarea:focus, .form-group select:focus {
    outline: none; border-color: #c9a84c;
}
.form-group input:disabled { opacity: 0.5; cursor: not-allowed; background: rgba(0,0,0,0.5); }
.form-group textarea { min-height: 80px; resize: vertical; }

.modal-footer {
    display: flex; justify-content: flex-end; gap: 10px;
    margin-top: 20px; padding-top: 15px;
    border-top: 1px solid rgba(255,255,255,0.1);
}

/* Aviso dentro dos modais de limpeza */
.aviso-limpar {
    background: rgba(231,76,60,0.15);
    border-left: 4px solid #e74c3c;
    color: #fca5a5;
    padding: 14px 18px;
    border-radius: 8px;
    font-size: 13px;
    line-height: 1.6;
    margin-bottom: 18px;
}
.aviso-limpar strong { color: #fff; }
.aviso-limpar code {
    background: rgba(0,0,0,0.4);
    color: #f5d76e;
    padding: 2px 8px;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: 13px;
}
.aviso-limpar.critical {
    background: rgba(255,45,85,0.15);
    border-left-color: #ff2d55;
    color: #ffccd5;
}

/* Lista de tabelas para limpeza múltipla */
.lista-tabelas-limpar {
    max-height: 350px;
    overflow-y: auto;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    padding: 10px;
    background: rgba(0,0,0,0.2);
    margin-bottom: 15px;
}
.lista-tabelas-limpar label {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 6px;
    cursor: pointer;
    transition: background 0.2s;
    font-size: 13px;
    color: #e2e8f0;
    text-transform: none;
    letter-spacing: 0;
    font-weight: 500;
    margin-bottom: 0;
}
.lista-tabelas-limpar label:hover { background: rgba(255,255,255,0.05); }
.lista-tabelas-limpar input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: #e74c3c;
    cursor: pointer;
}
.lista-tabelas-limpar .count-badge {
    margin-left: auto;
    background: rgba(0,0,0,0.4);
    color: #c9a84c;
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: 700;
}
.lista-tabelas-limpar .bulk-actions-list {
    display: flex;
    gap: 8px;
    padding: 8px 10px;
    margin-bottom: 8px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    position: sticky;
    top: 0;
    background: rgba(0,0,0,0.4);
    z-index: 1;
}
.lista-tabelas-limpar .bulk-actions-list button {
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.1);
    color: #e2e8f0;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    cursor: pointer;
    font-family: inherit;
}
.lista-tabelas-limpar .bulk-actions-list button:hover {
    background: rgba(231,76,60,0.3);
    border-color: #e74c3c;
}

#toast {
    position: fixed; top: 20px; left: 50%;
    transform: translateX(-50%) translateY(-100px);
    padding: 14px 24px; border-radius: 10px;
    font-weight: 600; font-size: 14px;
    z-index: 10000; transition: transform 0.4s ease;
    box-shadow: 0 10px 40px rgba(0,0,0,0.4); color: #fff;
}
#toast.show { transform: translateX(-50%) translateY(0); }
#toast.success { background: #16a34a; }
#toast.error { background: #dc2626; }
#toast.info { background: #3b82f6; }
#toast.warn { background: #f39c12; }

.loading {
    display: flex; align-items: center; justify-content: center;
    padding: 40px; color: #94a3b8; gap: 12px;
}
.spinner {
    width: 24px; height: 24px;
    border: 3px solid rgba(201,168,76,0.3);
    border-top-color: #c9a84c; border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes fadeInDown { from{opacity:0;transform:translateY(-20px);} to{opacity:1;transform:translateY(0);} }
@keyframes pulse { 0%,100%{opacity:1;} 50%{opacity:0.6;} }

@media (max-width: 900px) {
    .main-layout { grid-template-columns: 1fr; }
    .tabelas-panel { position: static; max-height: 400px; }
    .header h1 { font-size: 22px; }
    .header-actions { width: 100%; }
}
</style>
</head>
<body>

<div id="toast"></div>

<div class="container">

    <div class="header">
        <div>
            <h1>🗄️ Gerenciador de Tabelas</h1>
            <p>Liste, filtre, edite, remova e limpe dados — MySQL Local e Neon PostgreSQL</p>
        </div>
        <div class="header-actions">

            <div class="db-switcher">
                <a href="?banco=mysql" class="<?= $bancoAtual === 'mysql' ? 'active' : '' ?>">
                    🐬 MySQL Local
                </a>
                <a href="?banco=neon" class="<?= $bancoAtual === 'neon' ? 'active pg' : '' ?>">
                    🐘 Neon PostgreSQL
                </a>
            </div>

            <button class="btn btn-secondary" onclick="atualizarStats()">🔃 Contagens</button>
            <a href="?sair=1" class="btn btn-danger">🚪 Bloquear</a>
            <a href="../index.php" class="btn btn-secondary">← Voltar</a>
        </div>
    </div>

    <div class="main-layout">
        <aside class="tabelas-panel">
            <h2>
                📋 Tabelas
                <span id="totalTabelas" style="color:#94a3b8;font-weight:400;">(0)</span>
                <span class="db-badge <?= $bancoAtual ?>" style="float:right;margin-top:2px;">
                    <?= $bancoAtual === 'neon' ? '🐘 Neon' : '🐬 MySQL' ?>
                </span>
            </h2>

            <!-- Ações em massa -->
            <div class="bulk-actions">
                <button class="btn btn-warning btn-sm" onclick="abrirLimparMultiplas()" title="Limpar várias tabelas de uma vez">
                    🧨 Limpar Selecionadas
                </button>
                <button class="btn btn-black btn-sm" onclick="abrirLimparTodas()" title="Limpar TODAS as tabelas do banco atual">
                    💣 Limpar TODAS
                </button>
            </div>

            <input type="text" class="tabelas-search" id="filtroTabelas"
                   placeholder="🔍 Filtrar tabelas..." oninput="filtrarListaTabelas(this.value)">
            <div id="listaTabelas">
                <div class="loading"><div class="spinner"></div> Carregando...</div>
            </div>
        </aside>

        <main class="content-panel" id="contentPanel">
            <div class="empty-state">
                <div class="icon">📊</div>
                <h3>Selecione uma tabela</h3>
                <p>Escolha uma tabela à esquerda para ver e gerenciar seus dados</p>
            </div>
        </main>
    </div>
</div>

<!-- MODAL DE EDIÇÃO -->
<div class="modal-overlay" id="modalEditar">
    <div class="modal">
        <div class="modal-header">
            <h3>✏️ Editar Registro</h3>
            <button class="modal-close" onclick="fecharModal('modalEditar')">✕</button>
        </div>
        <form id="formEditar" onsubmit="salvarEdicao(event)">
            <div id="formFields"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fecharModal('modalEditar')">Cancelar</button>
                <button type="submit" class="btn btn-primary">💾 Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DE EXCLUSÃO -->
<div class="modal-overlay" id="modalExcluir">
    <div class="modal" style="max-width: 450px;">
        <div class="modal-header">
            <h3>⚠️ Confirmar Exclusão</h3>
            <button class="modal-close" onclick="fecharModal('modalExcluir')">✕</button>
        </div>
        <p style="color: #cbd5e0; font-size: 15px; line-height: 1.6;">
            Tem certeza que deseja remover <strong id="qtdExcluir" style="color: #e74c3c;">0</strong> registro(s)?
        </p>
        <p style="color: #94a3b8; font-size: 13px; margin-top: 10px;">
            ⚠️ Esta ação é irreversível!
        </p>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="fecharModal('modalExcluir')">Cancelar</button>
            <button type="button" class="btn btn-danger" onclick="confirmarExclusao()">🗑️ Excluir</button>
        </div>
    </div>
</div>

<!-- MODAL DE LIMPAR UMA TABELA -->
<div class="modal-overlay" id="modalLimpar">
    <div class="modal" style="max-width: 520px;">
        <div class="modal-header">
            <h3 class="danger">🧹 Limpar Tabela</h3>
            <button class="modal-close" onclick="fecharModal('modalLimpar')">✕</button>
        </div>

        <div class="aviso-limpar">
            ⚠️ <strong>ATENÇÃO — AÇÃO IRREVERSÍVEL!</strong><br><br>
            Você está prestes a <strong>APAGAR TODOS OS REGISTROS</strong> da tabela
            <code id="nomeTabelaLimpar">—</code> no banco
            <strong><?= $bancoAtual === 'neon' ? '🐘 Neon PostgreSQL' : '🐬 MySQL Local' ?></strong>.
            <br><br>
            <strong>Isso não pode ser desfeito!</strong>
            <?php if ($bancoAtual === 'mysql'): ?>
            <br><br>
            🔒 O contador <code>AUTO_INCREMENT</code> será <strong>reiniciado para 1</strong>.
            <?php else: ?>
            <br><br>
            🔒 As <strong>sequências (IDENTITY)</strong> serão reiniciadas e registros
            dependentes serão removidos em cascata.
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label>Digite o nome exato da tabela para confirmar:</label>
            <input type="text"
                   id="inputConfirmacao"
                   placeholder="Digite..."
                   autocomplete="off"
                   onkeypress="if(event.key==='Enter'){event.preventDefault();confirmarLimpeza();}">
            <p style="color:#94a3b8;font-size:12px;margin-top:6px;">
                Digite exatamente <code id="tabelaEsperada" style="color:#f5d76e;background:rgba(0,0,0,0.4);padding:2px 6px;border-radius:4px;">—</code> para liberar o botão.
            </p>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="fecharModal('modalLimpar')">Cancelar</button>
            <button type="button"
                    class="btn btn-danger"
                    id="btnConfirmarLimpeza"
                    onclick="confirmarLimpeza()"
                    disabled>
                🧹 Apagar Tudo
            </button>
        </div>
    </div>
</div>

<!-- MODAL DE LIMPAR MÚLTIPLAS -->
<div class="modal-overlay" id="modalLimparMultiplas">
    <div class="modal" style="max-width: 620px;">
        <div class="modal-header">
            <h3 class="danger">🧨 Limpar Tabelas Selecionadas</h3>
            <button class="modal-close" onclick="fecharModal('modalLimparMultiplas')">✕</button>
        </div>

        <div class="aviso-limpar">
            ⚠️ <strong>ATENÇÃO — APAGA DADOS DE VÁRIAS TABELAS!</strong><br>
            Marque abaixo as tabelas que devem ser <strong>totalmente limpas</strong> no banco
            <strong><?= $bancoAtual === 'neon' ? '🐘 Neon PostgreSQL' : '🐬 MySQL Local' ?></strong>.
        </div>

        <div class="form-group">
            <label>Tabelas (marque uma ou mais):</label>
            <div class="lista-tabelas-limpar" id="listaTabelasLimpar">
                <div class="loading"><div class="spinner"></div> Carregando tabelas...</div>
            </div>
        </div>

        <div class="form-group">
            <label>Digite exatamente <code style="color:#e74c3c;background:rgba(0,0,0,0.4);padding:2px 8px;border-radius:4px;">LIMPAR SELECIONADAS</code> para confirmar:</label>
            <input type="text"
                   id="inputConfirmacaoMult"
                   placeholder="Digite: LIMPAR SELECIONADAS"
                   autocomplete="off">
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="fecharModal('modalLimparMultiplas')">Cancelar</button>
            <button type="button"
                    class="btn btn-danger"
                    id="btnConfirmarLimpezaMult"
                    onclick="confirmarLimpezaMultipla()"
                    disabled>
                🧨 Apagar Selecionadas
            </button>
        </div>
    </div>
</div>

<!-- MODAL DE LIMPAR TODAS -->
<div class="modal-overlay" id="modalLimparTodas">
    <div class="modal" style="max-width: 560px;">
        <div class="modal-header">
            <h3 class="critical">💣 APAGAR TUDO</h3>
            <button class="modal-close" onclick="fecharModal('modalLimparTodas')">✕</button>
        </div>

        <div class="aviso-limpar critical">
            ☢️ <strong>EXTREMO CUIDADO — ISSO APAGA TUDO!!!</strong><br><br>
            Você está prestes a <strong>APAGAR TODOS OS REGISTROS DE TODAS AS TABELAS</strong>
            do banco <strong><?= $bancoAtual === 'neon' ? '🐘 Neon PostgreSQL' : '🐬 MySQL Local' ?></strong>.
            <br><br>
            <strong>Não haverá como recuperar. Nem um único registro sobrará.</strong>
            <br><br>
            Isso equivale a <code>TRUNCATE</code> em <strong>todas</strong> as tabelas listadas.
        </div>

        <div class="form-group">
            <label>Digite exatamente <code style="color:#ff2d55;background:rgba(0,0,0,0.5);padding:2px 8px;border-radius:4px;">APAGAR TUDO</code> para confirmar:</label>
            <input type="text"
                   id="inputConfirmacaoTodas"
                   placeholder="Digite: APAGAR TUDO"
                   autocomplete="off"
                   style="text-align:center;font-size:16px;letter-spacing:2px;font-weight:700;">
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="fecharModal('modalLimparTodas')">Cancelar</button>
            <button type="button"
                    class="btn btn-danger"
                    id="btnConfirmarLimpezaTodas"
                    onclick="confirmarLimpezaTodas()"
                    disabled>
                💣 APAGAR TUDO
            </button>
        </div>
    </div>
</div>

<script>
// ============================================
// ESTADO
// ============================================
const state = {
    tabelas: [],
    stats: {},
    tabelaAtual: null,
    colunas: [],
    pk: null,
    dados: [],
    pagina: 1,
    limite: 20,
    totalPaginas: 1,
    total: 0,
    busca: '',
    colunaBusca: '',
    ordem: '',
    direcao: 'ASC',
    selecionados: new Set(),
    registroEditando: null,
    tabelasParaLimpar: new Set()
};

const BANCO = '<?= $bancoAtual ?>';

function toast(msg, tipo = 'info') {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.className = tipo + ' show';
    setTimeout(() => el.className = tipo, 4000);
}
function abrirModal(id) { document.getElementById(id).classList.add('active'); }
function fecharModal(id) { document.getElementById(id).classList.remove('active'); }
document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('active');
});

// ============================================
// CARREGAR TABELAS
// ============================================
async function carregarTabelas() {
    try {
        const res = await fetch('?ajax=tabelas');
        const data = await res.json();
        if (!data.success) throw new Error(data.message);
        state.tabelas = data.tabelas || [];
        document.getElementById('totalTabelas').textContent = `(${state.tabelas.length})`;
        renderizarListaTabelas();
        atualizarStats();
    } catch (e) {
        document.getElementById('listaTabelas').innerHTML =
            `<div style="color:#e74c3c;padding:10px;">❌ ${e.message}</div>`;
    }
}

function renderizarListaTabelas(filtro = '') {
    const container = document.getElementById('listaTabelas');
    const f = filtro.toLowerCase();
    const filtradas = state.tabelas.filter(t => t.toLowerCase().includes(f));

    if (filtradas.length === 0) {
        container.innerHTML = '<div style="color:#94a3b8;padding:10px;text-align:center;font-size:13px;">Nenhuma tabela encontrada</div>';
        return;
    }
    container.innerHTML = filtradas.map(t => {
        const count = state.stats[t] !== undefined ? state.stats[t] : '…';
        const ativa = state.tabelaAtual === t ? ' active' : '';
        return `
            <div class="tabela-item${ativa}" onclick="selecionarTabela('${t}')">
                <span class="nome">📄 ${t}</span>
                <span class="count">${count}</span>
            </div>`;
    }).join('');
}

function filtrarListaTabelas(valor) { renderizarListaTabelas(valor); }

async function atualizarStats() {
    try {
        const res = await fetch('?ajax=stats');
        const data = await res.json();
        if (data.success) {
            state.stats = data.stats;
            renderizarListaTabelas(document.getElementById('filtroTabelas').value);
        }
    } catch (e) {}
}

// ============================================
// SELECIONAR TABELA
// ============================================
async function selecionarTabela(tabela) {
    state.tabelaAtual = tabela;
    state.pagina = 1;
    state.busca = '';
    state.colunaBusca = '';
    state.ordem = '';
    state.direcao = 'ASC';
    state.selecionados.clear();

    renderizarListaTabelas(document.getElementById('filtroTabelas').value);

    document.getElementById('contentPanel').innerHTML =
        '<div class="loading"><div class="spinner"></div> Carregando dados...</div>';

    try {
        const res = await fetch(`?ajax=estrutura&tabela=${encodeURIComponent(tabela)}`);
        const est = await res.json();
        if (!est.success) throw new Error(est.message);

        state.colunas = est.colunas.map(c => c.Field);
        state.pk = est.pk;
        await carregarDados();
    } catch (e) {
        document.getElementById('contentPanel').innerHTML =
            `<div style="color:#e74c3c;padding:20px;">❌ ${e.message}</div>`;
    }
}

// ============================================
// CARREGAR DADOS
// ============================================
async function carregarDados() {
    if (!state.tabelaAtual) return;
    const params = new URLSearchParams({
        ajax: 'dados', tabela: state.tabelaAtual,
        pagina: state.pagina, limite: state.limite,
        busca: state.busca, coluna_busca: state.colunaBusca,
        ordem: state.ordem, direcao: state.direcao
    });
    try {
        const res = await fetch('?' + params.toString());
        const data = await res.json();
        if (!data.success) throw new Error(data.message);
        state.dados = data.dados;
        state.total = data.total;
        state.totalPaginas = data.total_paginas || 1;
        state.colunas = data.colunas;
        state.selecionados.clear();
        renderizarConteudo();
    } catch (e) { toast('❌ ' + e.message, 'error'); }
}

// ============================================
// RENDERIZAR
// ============================================
function renderizarConteudo() {
    const colunas = state.colunas;
    const dados = state.dados;
    const badgeDb = BANCO === 'neon'
        ? '<span class="db-badge neon">🐘 Neon</span>'
        : '<span class="db-badge mysql">🐬 MySQL</span>';

    let html = `
        <div class="toolbar">
            <div class="toolbar-left">
                <span class="tabela-titulo">📄 ${state.tabelaAtual} ${badgeDb}</span>
                <span style="color:#94a3b8;font-size:13px;">(${state.total} registros)</span>
            </div>
            <div class="toolbar-right">
                <select id="colunaBusca" onchange="aplicarBusca()" style="min-width:150px;">
                    <option value="">🔍 Todas as colunas</option>
                    ${colunas.map(c => `<option value="${c}" ${state.colunaBusca === c ? 'selected' : ''}>${c}</option>`).join('')}
                </select>
                <input type="text" id="inputBusca" placeholder="Buscar..."
                       value="${escapeHtml(state.busca)}"
                       onkeypress="if(event.key==='Enter') aplicarBusca()">
                <button class="btn btn-secondary btn-sm" onclick="aplicarBusca()">🔎 Buscar</button>
                <button class="btn btn-secondary btn-sm" onclick="limparBusca()">✕ Limpar</button>
                <button class="btn btn-danger btn-sm" id="btnExcluirSel"
                        onclick="abrirConfirmacaoExcluir()" disabled>
                    🗑️ Excluir (<span id="qtdSel">0</span>)
                </button>
                <button class="btn btn-warning btn-sm" onclick="abrirLimparTabela()"
                        title="Apagar TODOS os registros da tabela atual">
                    🧹 Limpar Tabela
                </button>
            </div>
        </div>`;

    if (dados.length === 0) {
        html += `<div class="empty-state" style="height:250px;">
                    <div class="icon">📭</div>
                    <h3>Nenhum registro encontrado</h3>
                    <p>Tente ajustar os filtros</p>
                 </div>`;
    } else {
        html += '<div class="table-wrapper"><table class="data-table"><thead><tr>';
        html += `<th class="col-check"><input type="checkbox" class="checkbox-custom"
                     id="checkAll" onchange="toggleTodos(this.checked)"></th>`;

        for (const col of colunas) {
            const isSorted = state.ordem === col;
            const seta = isSorted ? (state.direcao === 'ASC' ? ' ↑' : ' ↓') : '';
            html += `<th class="sortable" onclick="ordenarPor('${col}')">${col}${seta}</th>`;
        }
        html += '<th class="col-actions">Ações</th></tr></thead><tbody>';

        for (let i = 0; i < dados.length; i++) {
            const row = dados[i];
            const pkValor = row[state.pk];
            const sel = state.selecionados.has(String(pkValor));

            html += `<tr class="${sel ? 'selected' : ''}" data-idx="${i}">`;
            html += `<td class="col-check">
                <input type="checkbox" class="checkbox-custom check-row"
                       data-pk="${escapeHtml(String(pkValor))}"
                       ${sel ? 'checked' : ''}
                       onchange="toggleSelecionado(this, '${escapeHtml(String(pkValor))}')">
            </td>`;

            for (const col of colunas) {
                const val = row[col];
                const display = val === null
                    ? '<span style="color:#94a3b8;font-style:italic;">NULL</span>'
                    : escapeHtml(String(val));
                const isPk = col === state.pk;
                html += `<td title="${escapeHtml(String(val ?? ''))}"
                             ${isPk ? 'style="color:#c9a84c;font-weight:600;"' : ''}>${display}</td>`;
            }
            html += `<td class="col-actions">
                <button class="btn btn-secondary btn-sm" onclick="abrirEditar(${i})" title="Editar">✏️</button>
            </td></tr>`;
        }
        html += '</tbody></table></div>';
    }

    html += renderizarPaginacao();
    document.getElementById('contentPanel').innerHTML = html;
}

function renderizarPaginacao() {
    const { pagina, totalPaginas, total, limite } = state;
    const inicio = total === 0 ? 0 : (pagina - 1) * limite + 1;
    const fim = Math.min(pagina * limite, total);

    let html = `<div class="pagination">
        <div class="pagination-info">Mostrando <strong>${inicio}-${fim}</strong> de <strong>${total}</strong></div>
        <div class="pagination-buttons">`;

    html += `<button class="page-btn" ${pagina <= 1 ? 'disabled' : ''} onclick="irParaPagina(1)">««</button>`;
    html += `<button class="page-btn" ${pagina <= 1 ? 'disabled' : ''} onclick="irParaPagina(${pagina - 1})">‹</button>`;

    const ini = Math.max(1, pagina - 2);
    const fimP = Math.min(totalPaginas, pagina + 2);
    if (ini > 1) html += `<span style="color:#94a3b8;padding:0 4px;">…</span>`;
    for (let p = ini; p <= fimP; p++) {
        html += `<button class="page-btn ${p === pagina ? 'active' : ''}" onclick="irParaPagina(${p})">${p}</button>`;
    }
    if (fimP < totalPaginas) html += `<span style="color:#94a3b8;padding:0 4px;">…</span>`;

    html += `<button class="page-btn" ${pagina >= totalPaginas ? 'disabled' : ''} onclick="irParaPagina(${pagina + 1})">›</button>`;
    html += `<button class="page-btn" ${pagina >= totalPaginas ? 'disabled' : ''} onclick="irParaPagina(${totalPaginas})">»»</button>`;
    html += `</div></div>`;
    return html;
}

// ============================================
// BUSCA / ORDENAÇÃO / PÁGINA
// ============================================
function aplicarBusca() {
    state.busca = document.getElementById('inputBusca').value.trim();
    state.colunaBusca = document.getElementById('colunaBusca').value;
    state.pagina = 1;
    carregarDados();
}
function limparBusca() {
    state.busca = '';
    state.colunaBusca = '';
    state.pagina = 1;
    carregarDados();
}
function ordenarPor(coluna) {
    if (state.ordem === coluna) {
        state.direcao = state.direcao === 'ASC' ? 'DESC' : 'ASC';
    } else {
        state.ordem = coluna;
        state.direcao = 'ASC';
    }
    state.pagina = 1;
    carregarDados();
}
function irParaPagina(p) {
    if (p < 1 || p > state.totalPaginas) return;
    state.pagina = p;
    carregarDados();
}

// ============================================
// SELEÇÃO
// ============================================
function toggleTodos(checked) {
    state.selecionados.clear();
    document.querySelectorAll('.check-row').forEach(chk => {
        chk.checked = checked;
        if (checked) state.selecionados.add(chk.dataset.pk);
    });
    atualizarSelecaoUI();
}
function toggleSelecionado(checkbox, pkValor) {
    if (checkbox.checked) state.selecionados.add(pkValor);
    else state.selecionados.delete(pkValor);
    const tr = checkbox.closest('tr');
    if (tr) tr.classList.toggle('selected', checkbox.checked);
    atualizarSelecaoUI();
}
function atualizarSelecaoUI() {
    const qtd = state.selecionados.size;
    document.getElementById('qtdSel').textContent = qtd;
    document.getElementById('btnExcluirSel').disabled = qtd === 0;
    const ca = document.getElementById('checkAll');
    if (ca) ca.checked = qtd > 0 && qtd === state.dados.length;
}

// ============================================
// EDITAR
// ============================================
function abrirEditar(idx) {
    const row = state.dados[idx];
    state.registroEditando = row;
    const form = document.getElementById('formFields');
    form.innerHTML = '';
    for (const col of state.colunas) {
        const isPk = col === state.pk;
        const val = row[col];
        const valStr = val === null ? '' : String(val);
        const isLong = valStr.length > 100;
        form.innerHTML += `
            <div class="form-group">
                <label>${col} ${isPk ? '(PK 🔑)' : ''}</label>
                ${isLong
                    ? `<textarea name="${col}" ${isPk ? 'disabled' : ''}>${escapeHtml(valStr)}</textarea>`
                    : `<input type="text" name="${col}" value="${escapeHtml(valStr)}" ${isPk ? 'disabled' : ''}>`}
            </div>`;
    }
    abrirModal('modalEditar');
}

async function salvarEdicao(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const dados = {};
    for (const [col, val] of formData.entries()) {
        if (col === state.pk) continue;
        dados[col] = val === '' ? null : val;
    }
    const payload = {
        tabela: state.tabelaAtual,
        pk: state.pk,
        pk_valor: state.registroEditando[state.pk],
        dados
    };
    try {
        const res = await fetch('?ajax=editar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            toast('✅ Registro atualizado!', 'success');
            fecharModal('modalEditar');
            await carregarDados();
            atualizarStats();
        } else {
            toast('❌ ' + data.message, 'error');
        }
    } catch (e) { toast('❌ ' + e.message, 'error'); }
}

// ============================================
// EXCLUIR SELECIONADOS
// ============================================
function abrirConfirmacaoExcluir() {
    const qtd = state.selecionados.size;
    if (qtd === 0) return;
    document.getElementById('qtdExcluir').textContent = qtd;
    abrirModal('modalExcluir');
}

async function confirmarExclusao() {
    const payload = {
        tabela: state.tabelaAtual,
        pk: state.pk,
        ids: Array.from(state.selecionados)
    };
    try {
        const res = await fetch('?ajax=remover', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            toast(`✅ ${data.removidos} registro(s) removido(s)!`, 'success');
            fecharModal('modalExcluir');
            state.selecionados.clear();
            await carregarDados();
            atualizarStats();
        } else {
            toast('❌ ' + data.message, 'error');
        }
    } catch (e) { toast('❌ ' + e.message, 'error'); }
}

// ============================================
// LIMPAR UMA TABELA
// ============================================
function abrirLimparTabela() {
    if (!state.tabelaAtual) {
        toast('⚠️ Selecione uma tabela primeiro', 'error');
        return;
    }
    if (state.total === 0) {
        toast('ℹ️ A tabela já está vazia', 'info');
        return;
    }

    document.getElementById('nomeTabelaLimpar').textContent = state.tabelaAtual;
    document.getElementById('tabelaEsperada').textContent = state.tabelaAtual;
    document.getElementById('inputConfirmacao').value = '';
    document.getElementById('inputConfirmacao').placeholder = 'Digite: ' + state.tabelaAtual;
    document.getElementById('btnConfirmarLimpeza').disabled = true;

    abrirModal('modalLimpar');
    setTimeout(() => document.getElementById('inputConfirmacao').focus(), 100);
}

// Monitora o input do modal simples
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('inputConfirmacao');
    if (input) {
        input.addEventListener('input', function() {
            const val = this.value.trim();
            const esperado = state.tabelaAtual || '';
            const btn = document.getElementById('btnConfirmarLimpeza');
            btn.disabled = !(val === esperado && esperado !== '');

            if (val === '') this.style.borderColor = 'rgba(255,255,255,0.1)';
            else if (val === esperado) {
                this.style.borderColor = '#2ecc71';
                this.style.boxShadow = '0 0 10px rgba(46,204,113,0.3)';
            } else {
                this.style.borderColor = '#e74c3c';
                this.style.boxShadow = 'none';
            }
        });
    }

    // Monitora o input do modal múltiplo
    const inputMult = document.getElementById('inputConfirmacaoMult');
    if (inputMult) {
        inputMult.addEventListener('input', function() {
            const ok = this.value.trim() === 'LIMPAR SELECIONADAS';
            const btn = document.getElementById('btnConfirmarLimpezaMult');
            const temSel = state.tabelasParaLimpar.size > 0;
            btn.disabled = !(ok && temSel);
        });
    }

    // Monitora o input do modal "todas"
    const inputTodas = document.getElementById('inputConfirmacaoTodas');
    if (inputTodas) {
        inputTodas.addEventListener('input', function() {
            const ok = this.value.trim() === 'APAGAR TUDO';
            document.getElementById('btnConfirmarLimpezaTodas').disabled = !ok;
        });
    }
});

async function confirmarLimpeza() {
    const tabela = state.tabelaAtual;
    if (!tabela) return;

    const confirmacao = document.getElementById('inputConfirmacao').value.trim();
    if (confirmacao !== tabela) {
        toast('❌ Digite o nome exato da tabela', 'error');
        return;
    }

    const btn = document.getElementById('btnConfirmarLimpeza');
    btn.disabled = true;
    btn.textContent = '⏳ Apagando...';

    try {
        const res = await fetch('?ajax=limpar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tabela, confirmacao })
        });
        const data = await res.json();

        if (data.success) {
            toast(`🧹 Tabela "${data.tabela}" limpa — ${data.removidos} registro(s) removido(s)`, 'success');
            fecharModal('modalLimpar');
            state.selecionados.clear();
            await carregarDados();
            atualizarStats();
        } else {
            toast('❌ ' + data.message, 'error');
        }
    } catch (e) {
        toast('❌ Erro: ' + e.message, 'error');
    } finally {
        btn.textContent = '🧹 Apagar Tudo';
        btn.disabled = false;
    }
}

// ============================================
// LIMPAR MÚLTIPLAS
// ============================================
async function abrirLimparMultiplas() {
    if (state.tabelas.length === 0) {
        toast('⚠️ Nenhuma tabela carregada', 'error');
        return;
    }

    state.tabelasParaLimpar.clear();
    document.getElementById('inputConfirmacaoMult').value = '';
    document.getElementById('btnConfirmarLimpezaMult').disabled = true;

    renderizarListaLimpar();

    abrirModal('modalLimparMultiplas');
}

function renderizarListaLimpar() {
    const container = document.getElementById('listaTabelasLimpar');
    let html = `
        <div class="bulk-actions-list">
            <button onclick="marcarTodasLimpar(true)">☑️ Marcar todas</button>
            <button onclick="marcarTodasLimpar(false)">☐ Desmarcar</button>
            <button onclick="inverterSelecaoLimpar()">🔄 Inverter</button>
        </div>
    `;

    html += state.tabelas.map(t => {
        const count = state.stats[t] !== undefined ? state.stats[t] : '?';
        const checked = state.tabelasParaLimpar.has(t) ? 'checked' : '';
        return `
            <label>
                <input type="checkbox" value="${escapeHtml(t)}" ${checked}
                       onchange="toggleTabelaLimpar(this, '${escapeHtml(t)}')">
                <span>📄 ${escapeHtml(t)}</span>
                <span class="count-badge">${count}</span>
            </label>
        `;
    }).join('');

    container.innerHTML = html;
}

function toggleTabelaLimpar(checkbox, tabela) {
    if (checkbox.checked) state.tabelasParaLimpar.add(tabela);
    else state.tabelasParaLimpar.delete(tabela);

    // Atualiza o botão
    const input = document.getElementById('inputConfirmacaoMult');
    const ok = input.value.trim() === 'LIMPAR SELECIONADAS';
    const btn = document.getElementById('btnConfirmarLimpezaMult');
    btn.disabled = !(ok && state.tabelasParaLimpar.size > 0);
}

function marcarTodasLimpar(marcar) {
    state.tabelasParaLimpar.clear();
    if (marcar) state.tabelas.forEach(t => state.tabelasParaLimpar.add(t));
    renderizarListaLimpar();

    const input = document.getElementById('inputConfirmacaoMult');
    const ok = input.value.trim() === 'LIMPAR SELECIONADAS';
    document.getElementById('btnConfirmarLimpezaMult').disabled = !(ok && state.tabelasParaLimpar.size > 0);
}

function inverterSelecaoLimpar() {
    const novo = new Set();
    state.tabelas.forEach(t => {
        if (!state.tabelasParaLimpar.has(t)) novo.add(t);
    });
    state.tabelasParaLimpar = novo;
    renderizarListaLimpar();

    const input = document.getElementById('inputConfirmacaoMult');
    const ok = input.value.trim() === 'LIMPAR SELECIONADAS';
    document.getElementById('btnConfirmarLimpezaMult').disabled = !(ok && state.tabelasParaLimpar.size > 0);
}

async function confirmarLimpezaMultipla() {
    const tabelas = Array.from(state.tabelasParaLimpar);

    if (tabelas.length === 0) {
        toast('⚠️ Marque pelo menos uma tabela', 'error');
        return;
    }
    if (document.getElementById('inputConfirmacaoMult').value.trim() !== 'LIMPAR SELECIONADAS') {
        toast('❌ Digite exatamente: LIMPAR SELECIONADAS', 'error');
        return;
    }

    const btn = document.getElementById('btnConfirmarLimpezaMult');
    btn.disabled = true;
    btn.textContent = '⏳ Apagando...';

    try {
        const res = await fetch('?ajax=limpar_multiplas', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tabelas: tabelas,
                confirmacao: 'LIMPAR SELECIONADAS'
            })
        });
        const data = await res.json();

        if (data.success) {
            let msg = `🧨 ${data.tabelas} tabela(s) limpa(s) — ${data.removidos} registro(s) removido(s)`;
            if (data.erros && data.erros.length > 0) {
                msg += ` (${data.erros.length} erro(s))`;
            }
            toast(msg, 'success');

            fecharModal('modalLimparMultiplas');

            // Se a tabela atual foi limpa, recarrega
            if (tabelas.includes(state.tabelaAtual)) {
                await carregarDados();
            }
            atualizarStats();
        } else {
            toast('❌ ' + data.message, 'error');
        }
    } catch (e) {
        toast('❌ Erro: ' + e.message, 'error');
    } finally {
        btn.textContent = '🧨 Apagar Selecionadas';
        btn.disabled = false;
    }
}

// ============================================
// LIMPAR TODAS
// ============================================
function abrirLimparTodas() {
    if (state.tabelas.length === 0) {
        toast('⚠️ Nenhuma tabela carregada', 'error');
        return;
    }

    document.getElementById('inputConfirmacaoTodas').value = '';
    document.getElementById('btnConfirmarLimpezaTodas').disabled = true;

    abrirModal('modalLimparTodas');
    setTimeout(() => document.getElementById('inputConfirmacaoTodas').focus(), 100);
}

async function confirmarLimpezaTodas() {
    const confirmacao = document.getElementById('inputConfirmacaoTodas').value.trim();
    if (confirmacao !== 'APAGAR TUDO') {
        toast('❌ Digite exatamente: APAGAR TUDO', 'error');
        return;
    }

    const btn = document.getElementById('btnConfirmarLimpezaTodas');
    btn.disabled = true;
    btn.textContent = '⏳ Apagando TUDO...';

    try {
        const res = await fetch('?ajax=limpar_todas', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ confirmacao: 'APAGAR TUDO' })
        });
        const data = await res.json();

        if (data.success) {
            let msg = `💣 ${data.tabelas} tabela(s) limpa(s) — ${data.removidos} registro(s) apagado(s)`;
            if (data.erros && data.erros.length > 0) {
                msg += ` (${data.erros.length} erro(s))`;
            }
            toast(msg, 'success');

            fecharModal('modalLimparTodas');

            // Recarrega os dados da tabela atual (se houver)
            if (state.tabelaAtual) {
                await carregarDados();
            }
            atualizarStats();
        } else {
            toast('❌ ' + data.message, 'error');
        }
    } catch (e) {
        toast('❌ Erro: ' + e.message, 'error');
    } finally {
        btn.textContent = '💣 APAGAR TUDO';
        btn.disabled = false;
    }
}

// ============================================
// HELPERS
// ============================================
function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

document.addEventListener('DOMContentLoaded', carregarTabelas);
</script>

</body>
</html>