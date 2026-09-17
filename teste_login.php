<?php
require_once 'config/database.php';

$email = 'admin@softgest.com';
$senha = 'admin123';

echo "=== TESTE DE LOGIN ===<br><br>";

// 1. Buscar usuário
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
$stmt->execute([$email]);
$usuario = $stmt->fetch();

if ($usuario) {
    echo "✅ Usuário encontrado!<br>";
    echo "ID: " . $usuario['id'] . "<br>";
    echo "Nome: " . $usuario['nome'] . "<br>";
    echo "Email: " . $usuario['email'] . "<br>";
    echo "Telefone: " . $usuario['telefone'] . "<br>";
    echo "Status: " . $usuario['status'] . "<br>";
    echo "Hash da senha: " . $usuario['senha'] . "<br><br>";
    
    // 2. Verificar senha
    if (password_verify($senha, $usuario['senha'])) {
        echo "✅ SENHA CORRETA!<br>";
        echo "Você pode fazer login com: admin@softgest.com / admin123";
    } else {
        echo "❌ SENHA INCORRETA!<br>";
        
        // Tentar criar novo hash
        $novo_hash = password_hash($senha, PASSWORD_DEFAULT);
        echo "Novo hash gerado: " . $novo_hash . "<br>";
        echo "Execute este SQL para atualizar:<br>";
        echo "UPDATE usuarios SET senha = '$novo_hash' WHERE email = 'admin@softgest.com';";
    }
} else {
    echo "❌ Usuário NÃO encontrado!";
}

// 3. Verificar estrutura da tabela
echo "<br><br>=== ESTRUTURA DA TABELA ===<br>";
$stmt = $pdo->query("DESCRIBE usuarios");
while ($row = $stmt->fetch()) {
    echo $row['Field'] . " - " . $row['Type'] . "<br>";
}
?>