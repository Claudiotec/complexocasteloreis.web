<?php
// ============================================
// admin/migracao_control.php
// Controlador da migração — dispara o Python e devolve status
// SEM verificação de internet (deixa o Python reportar erros)
// ============================================

require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// ============================================
// CONFIGURAÇÕES
// ============================================
// Caminho do Python — deixe apenas 'python' se estiver no PATH,
// ou coloque o caminho completo (ex: 'C:\\Python313\\python.exe')
$PYTHON = 'python';

// Caminho do script Python (relativo à raiz do projeto)
$SCRIPT = realpath(__DIR__ . '/../migrar_inteligente.py');

// Arquivo de status gerado pelo Python
$STATUS_FILE = __DIR__ . '/../migracao_status.json';

// Arquivo de lock (evita migrações simultâneas)
$LOCK_FILE = __DIR__ . '/../migracao.lock';

// Log de execução do Python
$PYTHON_LOG = __DIR__ . '/../migracao_python.log';

// ============================================
// HELPERS
// ============================================
function lerStatus($file) {
    if (!file_exists($file)) {
        return [
            'success' => true,
            'status' => 'aguardando',
            'progresso' => 0,
            'tabela_atual' => '',
            'tabelas_total' => 0,
            'tabelas_processadas' => 0,
            'registros_inseridos' => 0,
            'registros_atualizados' => 0,
            'registros_ignorados' => 0,
            'colunas_criadas' => 0,
            'erros' => 0,
            'log' => [],
            'por_tabela' => [],
            'tempo_decorrido' => 0,
        ];
    }

    $json = @file_get_contents($file);
    $dados = json_decode($json, true);

    if (!$dados) {
        return ['success' => true, 'status' => 'aguardando', 'log' => []];
    }

    // Calcula tempo decorrido
    if (!empty($dados['iniciado_em'])) {
        $inicio = strtotime($dados['iniciado_em']);
        $fim = !empty($dados['finalizado_em'])
            ? strtotime($dados['finalizado_em'])
            : time();
        $dados['tempo_decorrido'] = max(0, $fim - $inicio);
    }

    // Verifica se o processo ainda está rodando
    $dados['processo_ativo'] = processoAtivo();

    $dados['success'] = true;
    return $dados;
}

function processoAtivo() {
    global $LOCK_FILE;
    if (!file_exists($LOCK_FILE)) return false;

    // Lock com mais de 1 hora é considerado órfão
    $idade = time() - filemtime($LOCK_FILE);
    if ($idade > 3600) {
        @unlink($LOCK_FILE);
        return false;
    }

    // Windows: checa tasklist
    if (PHP_OS_FAMILY === 'Windows') {
        $out = @shell_exec('tasklist /FI "IMAGENAME eq python.exe" 2>NUL');
        return $out && stripos($out, 'python.exe') !== false;
    }

    // Linux/Mac: pgrep
    $out = @shell_exec('pgrep -f migrar_inteligente.py 2>/dev/null');
    return !empty(trim($out));
}

function dispararPython() {
    global $PYTHON, $SCRIPT, $PYTHON_LOG, $LOCK_FILE;

    // Valida script
    if (!$SCRIPT || !file_exists($SCRIPT)) {
        return ['ok' => false, 'erro' => "Script Python não encontrado"];
    }

    // Verifica se já tem migração rodando
    if (processoAtivo()) {
        return ['ok' => false, 'erro' => 'Já existe uma migração em andamento', 'em_andamento' => true];
    }

    // Cria o lock
    @file_put_contents($LOCK_FILE, date('Y-m-d H:i:s'));

    // Garante que o log existe
    @touch($PYTHON_LOG);

    // Comando para rodar em background
    if (PHP_OS_FAMILY === 'Windows') {
        // Windows: start /B para rodar sem janela
        $cmd = 'start /B "" "' . $PYTHON . '" "' . $SCRIPT . '" >> "' . $PYTHON_LOG . '" 2>&1';
        @pclose(@popen($cmd, 'r'));
    } else {
        // Linux/Mac
        $cmd = 'nohup "' . $PYTHON . '" "' . $SCRIPT . '" >> "' . $PYTHON_LOG . '" 2>&1 &';
        @exec($cmd);
    }

    return ['ok' => true];
}

// ============================================
// ROTEAMENTO DE AÇÕES
// ============================================
$acao = $_GET['acao'] ?? $_POST['acao'] ?? 'status';

try {
    switch ($acao) {

        // ---------- INICIAR MIGRAÇÃO ----------
        case 'iniciar':
            // Apaga status antigo para começar limpo
            if (file_exists($STATUS_FILE)) @unlink($STATUS_FILE);

            $result = dispararPython();

            if (!$result['ok']) {
                echo json_encode([
                    'success' => false,
                    'message' => $result['erro'],
                    'em_andamento' => $result['em_andamento'] ?? false,
                ]);
                exit;
            }

            echo json_encode([
                'success' => true,
                'message' => 'Migração iniciada em background'
            ]);
            exit;

        // ---------- AUTO (chamado pelo loop do painel) ----------
        case 'auto':
            if (processoAtivo()) {
                echo json_encode([
                    'success' => false,
                    'em_andamento' => true,
                    'message' => 'Migração já em andamento'
                ]);
                exit;
            }

            if (file_exists($STATUS_FILE)) @unlink($STATUS_FILE);

            $result = dispararPython();

            echo json_encode([
                'success' => $result['ok'],
                'message' => $result['ok'] ? 'Migração automática iniciada' : $result['erro'],
            ]);
            exit;

        // ---------- STATUS ----------
        case 'status':
            $dados = lerStatus($STATUS_FILE);
            echo json_encode($dados);
            exit;

        // ---------- CANCELAR ----------
        case 'cancelar':
            if (PHP_OS_FAMILY === 'Windows') {
                @shell_exec('taskkill /F /IM python.exe /T 2>NUL');
            } else {
                @shell_exec('pkill -f migrar_inteligente.py 2>/dev/null');
            }

            @unlink($LOCK_FILE);

            if (file_exists($STATUS_FILE)) {
                $dados = json_decode(file_get_contents($STATUS_FILE), true) ?: [];
                $dados['status'] = 'cancelado';
                $dados['finalizado_em'] = date('c');
                @file_put_contents($STATUS_FILE, json_encode($dados, JSON_PRETTY_PRINT));
            }

            echo json_encode(['success' => true, 'message' => 'Migração cancelada']);
            exit;

        // ---------- RESETAR ----------
        case 'resetar':
            if (processoAtivo()) {
                echo json_encode(['success' => false, 'message' => 'Não é possível resetar enquanto a migração está rodando']);
                exit;
            }

            @unlink($STATUS_FILE);
            @unlink($LOCK_FILE);

            echo json_encode(['success' => true, 'message' => 'Status resetado']);
            exit;

        // ---------- DIAGNÓSTICO ----------
        case 'diagnostico':
            $pythonVersao = '';
            if (PHP_OS_FAMILY === 'Windows') {
                $pythonVersao = @shell_exec('"' . $PYTHON . '" --version 2>&1');
            } else {
                $pythonVersao = @shell_exec($PYTHON . ' --version 2>&1');
            }

            echo json_encode([
                'success' => true,
                'python' => $PYTHON,
                'python_existe' => !empty($pythonVersao),
                'python_versao' => trim((string)$pythonVersao),
                'script' => $SCRIPT,
                'script_existe' => $SCRIPT ? file_exists($SCRIPT) : false,
                'status_file' => $STATUS_FILE,
                'status_existe' => file_exists($STATUS_FILE),
                'lock_file' => $LOCK_FILE,
                'lock_existe' => file_exists($LOCK_FILE),
                'config_file' => __DIR__ . '/../auto_sync_config.json',
                'config_existe' => file_exists(__DIR__ . '/../auto_sync_config.json'),
                'processo_ativo' => processoAtivo(),
                'php_version' => PHP_VERSION,
                'os' => PHP_OS_FAMILY,
                'log_python_existe' => file_exists($PYTHON_LOG),
                'log_python_ultimas_linhas' => file_exists($PYTHON_LOG)
                    ? array_slice(file($PYTHON_LOG), -20)
                    : [],
            ]);
            exit;

        // ---------- LIMPAR LOCK ----------
        case 'limpar_lock':
            @unlink($LOCK_FILE);
            echo json_encode(['success' => true, 'message' => 'Lock removido']);
            exit;

        // ---------- TESTAR PYTHON ----------
        case 'testar_python':
            // Testa se o Python roda de verdade
            $cmd = PHP_OS_FAMILY === 'Windows'
                ? '"' . $PYTHON . '" --version 2>&1'
                : $PYTHON . ' --version 2>&1';
            $out = @shell_exec($cmd);

            // Testa importações críticas
            $teste = @shell_exec(
                PHP_OS_FAMILY === 'Windows'
                    ? '"' . $PYTHON . '" -c "import mysql.connector, psycopg2; print(\'OK\')" 2>&1'
                    : $PYTHON . ' -c "import mysql.connector, psycopg2; print(\'OK\')" 2>&1'
            );

            echo json_encode([
                'success' => true,
                'python_cmd' => $PYTHON,
                'python_version' => trim((string)$out),
                'imports_ok' => stripos((string)$teste, 'OK') !== false,
                'imports_resultado' => trim((string)$teste),
            ]);
            exit;

        default:
            echo json_encode(['success' => false, 'message' => 'Ação desconhecida: ' . $acao]);
            exit;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}