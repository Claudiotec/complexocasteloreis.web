<?php
// ============================================
// admin/sync_publico_para_local.php
// Sincroniza dados do PÚBLICO (PostgreSQL) para o LOCAL (MySQL)
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base_path = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web';

require_once $base_path . '/config/app_modes.php';
require_once $base_path . '/config/database.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

if (($_SESSION['usuario_perfil'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Apenas administradores']);
    exit;
}

$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);
$acao = $input['acao'] ?? 'sincronizar_tudo';

try {
    // ===== CONEXÃO LOCAL (MySQL) =====
    $local = conectarLocal();
    
    // ===== CONEXÃO PÚBLICA (PostgreSQL / Neon) =====
    $publico = conectarPublico();
    
    $tabelas = [
        'alunos',
        'turmas',
        'disciplinas',
        'funcionarios',
        'empresa',
        'mensagens',
        'horarios',
        'tempos',
        'matriculas'
    ];
    
    $total_registros = 0;
    $detalhes = [];
    $erros = [];
    
    foreach ($tabelas as $tabela) {
        try {
            try {
                $publico->query("SELECT 1 FROM \"$tabela\" LIMIT 1");
            } catch (Exception $e) {
                $detalhes[$tabela] = 'Não existe no público';
                continue;
            }
            
            try {
                $local->query("SELECT 1 FROM `$tabela` LIMIT 1");
            } catch (Exception $e) {
                $detalhes[$tabela] = 'Não existe no local';
                continue;
            }
            
            // Buscar dados da tabela PÚBLICA (PostgreSQL)
            $stmt = $publico->query("SELECT * FROM \"$tabela\"");
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($dados)) {
                $detalhes[$tabela] = 0;
                continue;
            }
            
            // ===== SINCRONIZAR PARA MYSQL =====
            $local->exec("SET FOREIGN_KEY_CHECKS=0");
            $local->exec("TRUNCATE TABLE `$tabela`");
            
            // Preparar INSERT para MySQL
            $colunas = array_keys($dados[0]);
            $colunas_quoted = array_map(function($c) { return '`' . $c . '`'; }, $colunas);
            $placeholders = array_fill(0, count($colunas), '?');
            
            $colunas_str = implode(', ', $colunas_quoted);
            $placeholders_str = implode(', ', $placeholders);
            
            $insert = $local->prepare("INSERT INTO `$tabela` ($colunas_str) VALUES ($placeholders_str)");
            
            $contador = 0;
            $local->beginTransaction();
            
            foreach ($dados as $linha) {
                try {
                    $insert->execute(array_values($linha));
                    $contador++;
                } catch (Exception $e) {
                    $erros[] = "$tabela: " . $e->getMessage();
                }
            }
            
            $local->commit();
            $local->exec("SET FOREIGN_KEY_CHECKS=1");
            
            $detalhes[$tabela] = $contador;
            $total_registros += $contador;
            
        } catch (Exception $e) {
            $detalhes[$tabela] = 'ERRO: ' . $e->getMessage();
            $erros[] = "$tabela: " . $e->getMessage();
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Sincronização Público → Local concluída',
        'total_registros' => $total_registros,
        'detalhes' => $detalhes,
        'erros' => array_slice($erros, 0, 10),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}