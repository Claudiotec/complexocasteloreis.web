<?php
// api/mensagens.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

// Verificar autenticação
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

require_once '../models/Mensagem.php';
$mensagem = new Mensagem();

try {
    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'conversas':
                    $conversas = $mensagem->listarConversas($usuario_id);
                    echo json_encode(['success' => true, 'conversas' => $conversas]);
                    break;
                    
                case 'mensagens':
                    $conversa_id = $_GET['conversa_id'] ?? 0;
                    $limite = $_GET['limite'] ?? 50;
                    $offset = $_GET['offset'] ?? 0;
                    
                    if (!$conversa_id) {
                        throw new Exception("ID da conversa é obrigatório");
                    }
                    
                    $mensagens = $mensagem->buscarMensagens($conversa_id, $usuario_id, $limite, $offset);
                    echo json_encode(['success' => true, 'mensagens' => $mensagens]);
                    break;
                    
                case 'usuarios':
                    $termo = $_GET['termo'] ?? '';
                    $usuarios = $mensagem->buscarUsuariosDisponiveis($usuario_id, $termo);
                    echo json_encode(['success' => true, 'usuarios' => $usuarios]);
                    break;
                    
                case 'status':
                    $target_id = $_GET['usuario_id'] ?? 0;
                    $status = $mensagem->buscarStatus($target_id);
                    echo json_encode(['success' => true, 'status' => $status]);
                    break;
                    
                case 'nao_lidas':
                    $conversas = $mensagem->listarConversas($usuario_id);
                    $total_nao_lidas = array_sum(array_column($conversas, 'nao_lidas'));
                    echo json_encode(['success' => true, 'total' => $total_nao_lidas]);
                    break;
                    
                default:
                    throw new Exception("Ação não encontrada");
            }
            break;
            
        case 'POST':
            switch ($action) {
                case 'enviar':
                    $conteudo = $_POST['conteudo'] ?? '';
                    $conversa_id = $_POST['conversa_id'] ?? 0;
                    $tipo = $_POST['tipo'] ?? 'texto';
                    
                    if (!$conversa_id || !$conteudo) {
                        throw new Exception("Dados incompletos");
                    }
                    
                    $arquivo = null;
                    if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {
                        $arquivo = [
                            'nome' => $_FILES['arquivo']['name'],
                            'tamanho' => $_FILES['arquivo']['size']
                        ];
                        // Salvar arquivo
                        $upload_dir = '../uploads/mensagens/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        move_uploaded_file($_FILES['arquivo']['tmp_name'], $upload_dir . $_FILES['arquivo']['name']);
                    }
                    
                    $resultado = $mensagem->enviarMensagem($conversa_id, $usuario_id, $conteudo, $tipo, $arquivo);
                    echo json_encode(['success' => true, 'mensagem' => $resultado]);
                    break;
                    
                case 'conversa':
                    $participantes = $_POST['participantes'] ?? [];
                    $titulo = $_POST['titulo'] ?? null;
                    $tipo = $_POST['tipo'] ?? 'privada';
                    
                    if (empty($participantes)) {
                        throw new Exception("Selecione pelo menos um participante");
                    }
                    
                    $conversa_id = $mensagem->criarConversa($usuario_id, $participantes, $titulo, $tipo);
                    echo json_encode(['success' => true, 'conversa_id' => $conversa_id]);
                    break;
                    
                case 'status':
                    $status = $_POST['status'] ?? 'online';
                    $dispositivo = $_POST['dispositivo'] ?? null;
                    $mensagem->atualizarStatus($usuario_id, $status, $dispositivo);
                    echo json_encode(['success' => true]);
                    break;
                    
                default:
                    throw new Exception("Ação não encontrada");
            }
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            
            switch ($action) {
                case 'editar':
                    $mensagem_id = $data['mensagem_id'] ?? 0;
                    $conteudo = $data['conteudo'] ?? '';
                    
                    if (!$mensagem_id || !$conteudo) {
                        throw new Exception("Dados incompletos");
                    }
                    
                    $resultado = $mensagem->editarMensagem($mensagem_id, $usuario_id, $conteudo);
                    echo json_encode(['success' => true, 'mensagem' => $resultado]);
                    break;
                    
                default:
                    throw new Exception("Ação não encontrada");
            }
            break;
            
        case 'DELETE':
            switch ($action) {
                case 'remover':
                    $mensagem_id = $_GET['mensagem_id'] ?? 0;
                    $para_todos = $_GET['para_todos'] ?? false;
                    
                    if (!$mensagem_id) {
                        throw new Exception("ID da mensagem é obrigatório");
                    }
                    
                    $mensagem->removerMensagem($mensagem_id, $usuario_id, $para_todos);
                    echo json_encode(['success' => true]);
                    break;
                    
                default:
                    throw new Exception("Ação não encontrada");
            }
            break;
            
        default:
            throw new Exception("Método não suportado");
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}