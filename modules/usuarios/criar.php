<?php
// modules/usuarios/criar.php
// Criar novo usuário

require_once '../../config/database.php';
require_once '../../config/functions.php';

// Verificar login
if (!isLoggedIn()) {
    redirect('login.php');
}

// Verificar permissão
if (!temPermissao('Usuarios', 'criar')) {
    redirect('index.php');
}

$mensagem = '';
$tipo_mensagem = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');
    $perfil = $_POST['perfil'] ?? 'usuario';
    $status = $_POST['status'] ?? 'ativo';
    
    $erros = [];
    
    if (empty($nome)) $erros[] = "Nome é obrigatório";
    if (empty($email)) $erros[] = "Email é obrigatório";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erros[] = "Email inválido";
    if (empty($senha)) $erros[] = "Senha é obrigatória";
    if (strlen($senha) < 6) $erros[] = "Senha deve ter no mínimo 6 caracteres";
    
    if (empty($erros)) {
        try {
            // Verificar se email já existe
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $erros[] = "Email já cadastrado";
            } else {
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, perfil, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nome, $email, $senha_hash, $perfil, $status]);
                
                $_SESSION['mensagem'] = "✅ Usuário criado com sucesso!";
                $_SESSION['tipo_mensagem'] = 'success';
                redirect('modules/usuarios/index.php');
            }
        } catch (PDOException $e) {
            $mensagem = "Erro ao criar usuário: " . $e->getMessage();
            $tipo_mensagem = 'danger';
        }
    }
    
    if (!empty($erros)) {
        $mensagem = implode('<br>', $erros);
        $tipo_mensagem = 'warning';
    }
}

// Incluir header
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12 col-md-8 col-lg-6 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">👤 Novo Usuário</h5>
                </div>
                <div class="card-body">
                    <?php if ($mensagem): ?>
                        <div class="alert alert-<?= $tipo_mensagem ?>"><?= $mensagem ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Nome *</label>
                            <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Senha *</label>
                            <input type="password" name="senha" class="form-control" required minlength="6">
                            <small class="text-muted">Mínimo 6 caracteres</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Perfil</label>
                            <select name="perfil" class="form-control">
                                <option value="admin">Administrador</option>
                                <option value="usuario" selected>Usuário</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="ativo" selected>Ativo</option>
                                <option value="inativo">Inativo</option>
                            </select>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-success">✅ Criar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>