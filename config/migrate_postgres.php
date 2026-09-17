<?php
// config/migrate_postgres.php
// Script para criar tabelas no PostgreSQL

require_once 'database_postgres.php';

// ============================================
// FUNÇÃO PARA CRIAR TABELAS
// ============================================

function criarTabelas($pdo) {
    $sqls = [];
    
    // ============================================
    // TABELA: clientes
    // ============================================
    $sqls[] = "
        CREATE TABLE IF NOT EXISTS clientes (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE,
            telefone VARCHAR(20),
            documento VARCHAR(20) UNIQUE,
            endereco TEXT,
            cidade VARCHAR(100),
            estado VARCHAR(2),
            cep VARCHAR(10),
            status VARCHAR(20) DEFAULT 'ativo',
            observacoes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";
    
    // ============================================
    // TABELA: usuarios
    // ============================================
    $sqls[] = "
        CREATE TABLE IF NOT EXISTS usuarios (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            senha VARCHAR(255) NOT NULL,
            perfil VARCHAR(20) DEFAULT 'usuario',
            status VARCHAR(20) DEFAULT 'ativo',
            ultimo_acesso TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";
    
    // ============================================
    // TABELA: produtos
    // ============================================
    $sqls[] = "
        CREATE TABLE IF NOT EXISTS produtos (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            descricao TEXT,
            codigo_barras VARCHAR(50) UNIQUE,
            preco_custo DECIMAL(10,2) DEFAULT 0,
            preco_venda DECIMAL(10,2) DEFAULT 0,
            quantidade INTEGER DEFAULT 0,
            quantidade_minima INTEGER DEFAULT 0,
            categoria VARCHAR(50),
            status VARCHAR(20) DEFAULT 'ativo',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";
    
    // ============================================
    // TABELA: licencas
    // ============================================
    $sqls[] = "
        CREATE TABLE IF NOT EXISTS licencas (
            id SERIAL PRIMARY KEY,
            codigo_licenca VARCHAR(50) UNIQUE NOT NULL,
            chave_ativacao VARCHAR(50),
            tipo VARCHAR(20) DEFAULT 'teste',
            status VARCHAR(20) DEFAULT 'ativa',
            cliente_nome VARCHAR(100),
            cliente_email VARCHAR(100),
            cliente_empresa VARCHAR(100),
            cliente_cnpj VARCHAR(20),
            data_ativacao TIMESTAMP,
            data_expiracao TIMESTAMP,
            max_usuarios INTEGER DEFAULT 0,
            modulos_liberados TEXT,
            hardware_id VARCHAR(255),
            observacoes TEXT,
            criado_por VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";
    
    // ============================================
    // TABELA: faturas_proforma
    // ============================================
    $sqls[] = "
        CREATE TABLE IF NOT EXISTS faturas_proforma (
            id SERIAL PRIMARY KEY,
            numero_fatura VARCHAR(20) UNIQUE NOT NULL,
            cliente_id INTEGER REFERENCES clientes(id),
            data_emissao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            data_vencimento TIMESTAMP,
            valor_total DECIMAL(10,2) DEFAULT 0,
            status VARCHAR(20) DEFAULT 'pendente',
            observacoes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";
    
    // ============================================
    // TABELA: movimentacoes_caixa
    // ============================================
    $sqls[] = "
        CREATE TABLE IF NOT EXISTS movimentacoes_caixa (
            id SERIAL PRIMARY KEY,
            tipo VARCHAR(20) NOT NULL,
            categoria VARCHAR(50),
            descricao TEXT,
            valor DECIMAL(10,2) DEFAULT 0,
            data_movimento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(20) DEFAULT 'confirmado',
            usuario_id INTEGER REFERENCES usuarios(id),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";
    
    // ============================================
    // EXECUTAR MIGRAÇÕES
    // ============================================
    
    echo "========================================\n";
    echo "📦 CRIANDO TABELAS NO POSTGRESQL\n";
    echo "========================================\n\n";
    
    $total = count($sqls);
    $criadas = 0;
    
    foreach ($sqls as $i => $sql) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela " . ($i + 1) . "/$total criada com sucesso!\n";
            $criadas++;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "⏭️ Tabela " . ($i + 1) . "/$total já existe\n";
            } else {
                echo "❌ Erro na tabela " . ($i + 1) . "/$total: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n========================================\n";
    echo "📊 RESUMO DA MIGRAÇÃO\n";
    echo "========================================\n";
    echo "Total de tabelas: $total\n";
    echo "Tabelas criadas: $criadas\n";
    echo "========================================\n";
    
    return $criadas > 0;
}

// ============================================
// VERIFICAR CONEXÃO
// ============================================

if (DB_CONNECTED) {
    echo "✅ Conexão com PostgreSQL estabelecida!\n\n";
    criarTabelas($pdo);
    echo "\n✅ Migração concluída com sucesso!\n";
} else {
    echo "❌ Falha na conexão com PostgreSQL\n";
    echo "Verifique as configurações e tente novamente.\n";
}
?>