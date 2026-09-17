<?php
require_once 'config/database.php';

echo "<h1>🔐 Verificação de Login</h1>";

$email = 'admin@softgest.com';
$senha = 'admin123';

echo "<h2>1. Buscando usuário...</h2>";

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
$stmt->execute([$email]);
$usuario = $stmt->fetch();

if ($usuario) {
    echo "<p style='color:green;'>✅ Usuário encontrado!</p>";
    echo "<pre>";
    print_r([
        'id' => $usuario['id'],
        'nome' => $usuario['nome'],
        'email' => $usuario['email'],
        'status' => $usuario['status'],
        'senha_hash' => $usuario['senha']
    ]);
    echo "</pre>";
    
    echo "<h2>2. Verificando senha...</h2>";
    
    if (password_verify($senha, $usuario['senha'])) {
        echo "<p style='color:green;font-size:24px;font-weight:bold;'>✅ SENHA CORRETA!</p>";
        echo "<p>O login vai funcionar perfeitamente.</p>";
        
        // Fazer login automático para teste
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_email'] = $usuario['email'];
        $_SESSION['usuario_perfil'] = $usuario['perfil'];
        
        echo "<p style='color:green;'>✅ Sessão criada com sucesso!</p>";
        echo "<p><a href='index.php' style='display:inline-block;padding:10px 20px;background:#c9a84c;color:#1a2332;text-decoration:none;border-radius:8px;font-weight:bold;'>📊 Ir para o Dashboard</a></p>";
        
    } else {
        echo "<p style='color:red;font-size:20px;font-weight:bold;'>❌ SENHA INCORRETA!</p>";
        
        // Resetar senha automaticamente
        $novo_hash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
        $stmt->execute([$novo_hash, $usuario['id']]);
        echo "<p style='color:green;'>✅ Senha resetada automaticamente! Tente novamente.</p>";
        echo "<p><a href='verificar_login.php'>🔄 Clique aqui para testar novamente</a></p>";
    }
} else {
    echo "<p style='color:red;'>❌ Usuário não encontrado!</p>";
}
?>