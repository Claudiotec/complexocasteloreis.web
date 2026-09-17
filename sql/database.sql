-- ============================================================
-- Banco de Dados: softgest_db
-- Sistema: SoftGest Web
-- Versão: 9.0 (Completa - Login com Telefone, Permissões, AGT, IVA, Escola)
-- ============================================================

-- Criar banco de dados
CREATE DATABASE IF NOT EXISTS softgest_db;
USE softgest_db;

-- ============================================================
-- TABELA: usuarios (COMPLETA COM TELEFONE)
-- ============================================================
CREATE TABLE IF NOT EXISTS usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    telefone VARCHAR(20) NULL,
    telefone_verificado BOOLEAN DEFAULT FALSE,
    senha VARCHAR(255) NOT NULL,
    perfil ENUM('admin','gerente','usuario') DEFAULT 'usuario',
    status ENUM('pendente','ativo','bloqueado') DEFAULT 'pendente',
    created_by INT,
    aprovado_em TIMESTAMP NULL,
    ultimo_acesso TIMESTAMP NULL,
    saldo_caixa DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_telefone (telefone)
);

-- ============================================================
-- TABELA: modulos (PARA PERMISSÕES)
-- ============================================================
CREATE TABLE IF NOT EXISTS modulos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(50) NOT NULL,
    icone VARCHAR(50),
    url VARCHAR(100),
    ordem INT DEFAULT 0,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TABELA: permissoes (CONTROLE DE ACESSO)
-- ============================================================
CREATE TABLE IF NOT EXISTS permissoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT,
    modulo VARCHAR(50) NOT NULL,
    visualizar BOOLEAN DEFAULT TRUE,
    criar BOOLEAN DEFAULT FALSE,
    editar BOOLEAN DEFAULT FALSE,
    excluir BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_perm (usuario_id, modulo)
);

-- ============================================================
-- TABELA: empresa (DADOS DA EMPRESA)
-- ============================================================
CREATE TABLE IF NOT EXISTS empresa (
    id INT PRIMARY KEY AUTO_INCREMENT,
    razao_social VARCHAR(200) NOT NULL,
    nome_fantasia VARCHAR(200),
    cnpj VARCHAR(20),
    inscricao_estadual VARCHAR(20),
    inscricao_municipal VARCHAR(20),
    endereco TEXT,
    numero VARCHAR(20),
    complemento VARCHAR(100),
    bairro VARCHAR(100),
    cidade VARCHAR(100),
    estado VARCHAR(50),
    cep VARCHAR(10),
    telefone VARCHAR(20),
    celular VARCHAR(20),
    email VARCHAR(100),
    site VARCHAR(100),
    logo VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- TABELA: clientes (COMPLETA COM NIF_AGT)
-- ============================================================
CREATE TABLE IF NOT EXISTS clientes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    contato VARCHAR(100),
    tipo_pessoa ENUM('fisica','juridica') DEFAULT 'fisica',
    documento VARCHAR(20),
    nif_agt VARCHAR(20) COMMENT 'NIF Angola',
    nif VARCHAR(20) COMMENT 'NIF Internacional',
    rg VARCHAR(20),
    data_nascimento DATE,
    email VARCHAR(100),
    site VARCHAR(100),
    telefone VARCHAR(20),
    whatsapp VARCHAR(20),
    endereco TEXT,
    numero VARCHAR(20),
    complemento VARCHAR(100),
    bairro VARCHAR(100),
    cidade VARCHAR(100),
    estado VARCHAR(50),
    cep VARCHAR(10),
    observacoes TEXT,
    status ENUM('ativo','inativo','bloqueado') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- TABELA: produtos
-- ============================================================
CREATE TABLE IF NOT EXISTS produtos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    categoria VARCHAR(50),
    quantidade INT DEFAULT 0,
    preco_compra DECIMAL(10,2),
    preco_venda DECIMAL(10,2),
    fornecedor VARCHAR(100),
    estoque_minimo INT DEFAULT 5,
    localizacao VARCHAR(100),
    unidade VARCHAR(20) DEFAULT 'un',
    peso DECIMAL(10,3),
    dimensoes VARCHAR(50),
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- TABELA: movimentacoes_estoque
-- ============================================================
CREATE TABLE IF NOT EXISTS movimentacoes_estoque (
    id INT PRIMARY KEY AUTO_INCREMENT,
    produto_id INT,
    tipo ENUM('entrada','saida'),
    quantidade INT,
    observacao TEXT,
    data_movimento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
);

-- ============================================================
-- TABELA: faturas_proforma
-- ============================================================
CREATE TABLE IF NOT EXISTS faturas_proforma (
    id INT PRIMARY KEY AUTO_INCREMENT,
    numero VARCHAR(20) UNIQUE NOT NULL,
    cliente_id INT,
    data_emissao DATE,
    data_validade DATE,
    subtotal DECIMAL(10,2),
    desconto DECIMAL(10,2),
    total DECIMAL(10,2),
    observacoes TEXT,
    status ENUM('rascunho','enviada','aprovada','rejeitada') DEFAULT 'rascunho',
    data_envio TIMESTAMP NULL,
    data_aprovacao TIMESTAMP NULL,
    data_rejeicao TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
);

-- ============================================================
-- TABELA: fatura_proforma_itens
-- ============================================================
CREATE TABLE IF NOT EXISTS fatura_proforma_itens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fatura_id INT,
    produto_id INT,
    quantidade INT,
    preco_unitario DECIMAL(10,2),
    desconto DECIMAL(10,2),
    total DECIMAL(10,2),
    FOREIGN KEY (fatura_id) REFERENCES faturas_proforma(id) ON DELETE CASCADE,
    FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE SET NULL
);

-- ============================================================
-- TABELA: faturas_recibo
-- ============================================================
CREATE TABLE IF NOT EXISTS faturas_recibo (
    id INT PRIMARY KEY AUTO_INCREMENT,
    numero VARCHAR(20) UNIQUE NOT NULL,
    cliente_id INT,
    data_emissao DATE,
    data_vencimento DATE,
    subtotal DECIMAL(10,2),
    desconto DECIMAL(10,2),
    total DECIMAL(10,2),
    status ENUM('pendente','pago','cancelado') DEFAULT 'pendente',
    data_pagamento TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
);

-- ============================================================
-- TABELA: funcionarios (RH)
-- ============================================================
CREATE TABLE IF NOT EXISTS funcionarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    cargo VARCHAR(50),
    departamento VARCHAR(50),
    email VARCHAR(100),
    telefone VARCHAR(20),
    data_admissao DATE,
    salario DECIMAL(10,2),
    status ENUM('ativo','inativo','ferias') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- TABELA: folha_pagamento
-- ============================================================
CREATE TABLE IF NOT EXISTS folha_pagamento (
    id INT PRIMARY KEY AUTO_INCREMENT,
    funcionario_id INT,
    mes INT,
    ano INT,
    salario_base DECIMAL(10,2),
    bonus DECIMAL(10,2),
    descontos DECIMAL(10,2),
    total DECIMAL(10,2),
    status ENUM('calculado','pago') DEFAULT 'calculado',
    data_pagamento TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE CASCADE
);

-- ============================================================
-- TABELA: presencas (MAPA DE PRESENÇA)
-- ============================================================
CREATE TABLE IF NOT EXISTS presencas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    funcionario_id INT,
    data DATE,
    entrada TIME,
    saida TIME,
    status ENUM('presente','ausente','justificado') DEFAULT 'presente',
    observacao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE CASCADE
);

-- ============================================================
-- TABELA: faltas (REGISTRO DE FALTAS)
-- ============================================================
CREATE TABLE IF NOT EXISTS faltas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    funcionario_id INT,
    data DATE,
    tipo ENUM('falta','atraso','licenca') DEFAULT 'falta',
    justificativa TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE CASCADE
);

-- ============================================================
-- TABELA: plano_marketing
-- ============================================================
CREATE TABLE IF NOT EXISTS plano_marketing (
    id INT PRIMARY KEY AUTO_INCREMENT,
    titulo VARCHAR(200) NOT NULL,
    descricao TEXT,
    objetivo TEXT,
    publico_alvo VARCHAR(100),
    orcamento DECIMAL(10,2),
    data_inicio DATE,
    data_fim DATE,
    status ENUM('planejado','em_andamento','concluido','cancelado') DEFAULT 'planejado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- TABELA: correspondencias
-- ============================================================
CREATE TABLE IF NOT EXISTS correspondencias (
    id INT PRIMARY KEY AUTO_INCREMENT,
    destinatario VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    telefone VARCHAR(20),
    endereco TEXT,
    assunto VARCHAR(200),
    mensagem TEXT,
    tipo ENUM('email','whatsapp','sms') DEFAULT 'email',
    status ENUM('rascunho','enviado','entregue','falha') DEFAULT 'rascunho',
    enviado_em TIMESTAMP NULL,
    mensagem_erro TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- TABELA: modelos_mensagem
-- ============================================================
CREATE TABLE IF NOT EXISTS modelos_mensagem (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    assunto VARCHAR(200),
    conteudo TEXT,
    tipo ENUM('email','whatsapp','sms') DEFAULT 'email',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TABELA: movimentacoes_caixa (FLUXO DE CAIXA)
-- ============================================================
CREATE TABLE IF NOT EXISTS movimentacoes_caixa (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tipo ENUM('entrada','saida') NOT NULL,
    categoria VARCHAR(50) NOT NULL,
    descricao TEXT,
    valor DECIMAL(10,2) NOT NULL,
    desconto DECIMAL(10,2) DEFAULT 0,
    iva DECIMAL(10,2) DEFAULT 0,
    taxa_iva DECIMAL(5,2) DEFAULT 14,
    data_movimento DATE NOT NULL,
    forma_pagamento ENUM('dinheiro','cartao_credito','cartao_debito','pix','boleto','transferencia') DEFAULT 'dinheiro',
    cliente_id INT,
    produto_id INT,
    quantidade INT,
    status ENUM('pendente','confirmado','cancelado') DEFAULT 'confirmado',
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
    FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE SET NULL
);

-- ============================================================
-- TABELA: categorias_caixa
-- ============================================================
CREATE TABLE IF NOT EXISTS categorias_caixa (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(50) NOT NULL,
    tipo ENUM('entrada','saida') NOT NULL,
    cor VARCHAR(7) DEFAULT '#3498db',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TABELA: comprovantes_venda (COMPROVANTES AGT)
-- ============================================================
CREATE TABLE IF NOT EXISTS comprovantes_venda (
    id INT PRIMARY KEY AUTO_INCREMENT,
    movimento_id INT,
    numero_comprovante VARCHAR(20) UNIQUE NOT NULL,
    cliente_id INT,
    cliente_nome VARCHAR(100),
    cliente_nif VARCHAR(20),
    subtotal DECIMAL(10,2),
    desconto DECIMAL(10,2) DEFAULT 0,
    valor_iva DECIMAL(10,2) DEFAULT 0,
    taxa_iva DECIMAL(5,2) DEFAULT 14,
    valor_total DECIMAL(10,2),
    forma_pagamento VARCHAR(30),
    data_emissao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    qr_code TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (movimento_id) REFERENCES movimentacoes_caixa(id) ON DELETE SET NULL,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
);

-- ============================================================
-- ============================================================
-- TABELAS DO MÓDULO ESCOLA
-- ============================================================

-- ============================================================
-- TABELA: alunos
-- ============================================================
CREATE TABLE IF NOT EXISTS alunos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    data_nascimento DATE NOT NULL,
    genero ENUM('M','F','Outro') DEFAULT 'M',
    email VARCHAR(100),
    telefone VARCHAR(20),
    endereco TEXT,
    nome_pai VARCHAR(100),
    nome_mae VARCHAR(100),
    telefone_responsavel VARCHAR(20),
    documento VARCHAR(20),
    status ENUM('ativo','inativo','transferido','concluido') DEFAULT 'ativo',
    data_matricula DATE,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- TABELA: professores
-- ============================================================
CREATE TABLE IF NOT EXISTS professores (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    data_nascimento DATE,
    genero ENUM('M','F','Outro') DEFAULT 'M',
    email VARCHAR(100),
    telefone VARCHAR(20),
    endereco TEXT,
    documento VARCHAR(20),
    especialidade VARCHAR(100),
    formacao TEXT,
    data_contratacao DATE,
    salario DECIMAL(10,2),
    status ENUM('ativo','inativo','ferias','licenca') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- TABELA: turmas
-- ============================================================
CREATE TABLE IF NOT EXISTS turmas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(50) NOT NULL,
    descricao TEXT,
    ano_letivo VARCHAR(10),
    sala VARCHAR(20),
    turno ENUM('manha','tarde','noite','integral') DEFAULT 'manha',
    capacidade INT DEFAULT 30,
    professor_id INT,
    status ENUM('ativa','concluida','cancelada') DEFAULT 'ativa',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (professor_id) REFERENCES professores(id) ON DELETE SET NULL
);

-- ============================================================
-- TABELA: disciplinas
-- ============================================================
CREATE TABLE IF NOT EXISTS disciplinas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    carga_horaria INT,
    professor_id INT,
    status ENUM('ativa','inativa') DEFAULT 'ativa',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (professor_id) REFERENCES professores(id) ON DELETE SET NULL
);

-- ============================================================
-- TABELA: matriculas
-- ============================================================
CREATE TABLE IF NOT EXISTS matriculas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    aluno_id INT NOT NULL,
    turma_id INT NOT NULL,
    data_matricula DATE NOT NULL,
    status ENUM('ativa','trancada','cancelada','concluida') DEFAULT 'ativa',
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
    FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_matricula (aluno_id, turma_id)
);

-- ============================================================
-- TABELA: frequencia
-- ============================================================
CREATE TABLE IF NOT EXISTS frequencia (
    id INT PRIMARY KEY AUTO_INCREMENT,
    matricula_id INT NOT NULL,
    data DATE NOT NULL,
    status ENUM('presente','ausente','justificado','atrasado') DEFAULT 'presente',
    observacao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (matricula_id) REFERENCES matriculas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_frequencia (matricula_id, data)
);

-- ============================================================
-- TABELA: notas
-- ============================================================
CREATE TABLE IF NOT EXISTS notas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    matricula_id INT NOT NULL,
    disciplina_id INT NOT NULL,
    bimestre INT,
    nota_1 DECIMAL(5,2),
    nota_2 DECIMAL(5,2),
    nota_3 DECIMAL(5,2),
    nota_4 DECIMAL(5,2),
    media DECIMAL(5,2),
    resultado ENUM('aprovado','reprovado','recuperacao','dispensado') DEFAULT 'dispensado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (matricula_id) REFERENCES matriculas(id) ON DELETE CASCADE,
    FOREIGN KEY (disciplina_id) REFERENCES disciplinas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_nota (matricula_id, disciplina_id, bimestre)
);

-- ============================================================
-- TABELA: horarios
-- ============================================================
CREATE TABLE IF NOT EXISTS horarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    turma_id INT NOT NULL,
    disciplina_id INT NOT NULL,
    dia_semana ENUM('segunda','terca','quarta','quinta','sexta','sabado') NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fim TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE,
    FOREIGN KEY (disciplina_id) REFERENCES disciplinas(id) ON DELETE CASCADE
);

-- ============================================================
-- TABELA: emolumentos (TAXAS ESCOLARES)
-- ============================================================
CREATE TABLE IF NOT EXISTS emolumentos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    valor DECIMAL(10,2) NOT NULL,
    tipo ENUM('matricula','mensalidade','taxa','outro') DEFAULT 'outro',
    status ENUM('ativo','inativo') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- TABELA: pagamentos (PAGAMENTOS ESCOLARES)
-- ============================================================
CREATE TABLE IF NOT EXISTS pagamentos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    aluno_id INT NOT NULL,
    emolumento_id INT,
    valor DECIMAL(10,2) NOT NULL,
    data_pagamento DATE NOT NULL,
    forma_pagamento ENUM('dinheiro','cartao','transferencia','pix') DEFAULT 'dinheiro',
    referencia VARCHAR(50),
    status ENUM('confirmado','pendente','cancelado') DEFAULT 'confirmado',
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
    FOREIGN KEY (emolumento_id) REFERENCES emolumentos(id) ON DELETE SET NULL
);

-- ============================================================
-- TABELA: mensalidades
-- ============================================================
CREATE TABLE IF NOT EXISTS mensalidades (
    id INT PRIMARY KEY AUTO_INCREMENT,
    aluno_id INT NOT NULL,
    mes INT NOT NULL,
    ano INT NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    valor_pago DECIMAL(10,2) DEFAULT 0,
    data_vencimento DATE,
    status ENUM('pendente','pago','atrasado','cancelado') DEFAULT 'pendente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_mensalidade (aluno_id, mes, ano)
);

-- ============================================================
-- DADOS DE EXEMPLO
-- ============================================================

-- ============================================================
-- 1. USUÁRIO ADMIN (senha: admin123)
-- ============================================================
INSERT IGNORE INTO usuarios (id, nome, email, telefone, senha, perfil, status, aprovado_em) 
VALUES (1, 'Administrador', 'admin@softgest.com', '999999999', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'ativo', NOW());

-- ============================================================
-- 2. MÓDULOS
-- ============================================================
INSERT IGNORE INTO modulos (nome, icone, url, ordem) VALUES 
('Dashboard', '📊', '/', 1),
('Clientes', '👤', '/modules/clientes/', 2),
('Produtos', '📦', '/modules/produtos/', 3),
('Estoque', '📊', '/modules/estoque/', 4),
('Faturas', '📄', '/modules/fatura_proforma/', 5),
('Recibos', '🧾', '/modules/fatura_recibo/', 6),
('Fluxo de Caixa', '💰', '/modules/caixa/', 7),
('RH', '👥', '/modules/rh/', 8),
('Marketing', '📈', '/modules/marketing/', 9),
('Correspondência', '✉️', '/modules/correspondencia/', 10),
('Empresa', '⚙️', '/modules/empresa/', 11),
('Usuários', '🔐', '/modules/usuarios/', 12),
('Escola', '🎓', '/modules/escola/', 13);

-- ============================================================
-- 3. PERMISSÕES PARA ADMIN (TODOS OS MÓDULOS)
-- ============================================================
INSERT IGNORE INTO permissoes (usuario_id, modulo, visualizar, criar, editar, excluir)
SELECT 1, nome, TRUE, TRUE, TRUE, TRUE FROM modulos;

-- ============================================================
-- 4. CATEGORIAS DO CAIXA
-- ============================================================
INSERT IGNORE INTO categorias_caixa (nome, tipo, cor) VALUES 
('Vendas', 'entrada', '#2ecc71'),
('Serviços', 'entrada', '#3498db'),
('Aluguel', 'saida', '#e74c3c'),
('Salários', 'saida', '#e67e22'),
('Fornecedores', 'saida', '#f39c12'),
('Marketing', 'saida', '#9b59b6'),
('Impostos', 'saida', '#e74c3c'),
('Outros', 'entrada', '#95a5a6');

-- ============================================================
-- 5. MODELOS DE MENSAGEM
-- ============================================================
INSERT IGNORE INTO modelos_mensagem (nome, assunto, conteudo, tipo) VALUES 
('Fatura Proforma', 'Fatura Proforma {{numero}}', 'Prezado(a) {{cliente}},

Segue em anexo a fatura proforma nº {{numero}} no valor de R$ {{total}}.

Data de vencimento: {{vencimento}}

Atenciosamente,
{{empresa}}', 'email'),
('Recibo de Pagamento', 'Recibo nº {{numero}}', 'Prezado(a) {{cliente}},

Confirmamos o recebimento do pagamento no valor de R$ {{total}} referente ao recibo nº {{numero}}.

Atenciosamente,
{{empresa}}', 'email');

-- ============================================================
-- 6. EMPRESA
-- ============================================================
INSERT IGNORE INTO empresa (razao_social, nome_fantasia, cnpj, inscricao_estadual, endereco, numero, bairro, cidade, estado, cep, telefone, celular, email, site) VALUES 
('SoftGest Sistemas Ltda', 'SoftGest Web', '00.000.000/0001-00', '123.456.789.123', 'Rua da Tecnologia', '1000', 'Centro', 'São Paulo', 'SP', '01000-000', '(11) 3333-3333', '(11) 99999-9999', 'contato@softgest.com', 'www.softgest.com');

-- ============================================================
-- 7. CLIENTES
-- ============================================================
INSERT IGNORE INTO clientes (nome, contato, tipo_pessoa, documento, nif_agt, nif, email, telefone, endereco, cidade, estado, status) VALUES 
('Empresa XPTO', 'João Silva', 'juridica', '12.345.678/0001-90', '1234567890', '123456789', 'contato@xpto.com', '999999999', 'Rua das Empresas, 100', 'São Paulo', 'SP', 'ativo'),
('Comércio ABC', 'Maria Santos', 'juridica', '98.765.432/0001-10', '0987654321', '987654321', 'abc@comercio.com', '888888888', 'Av. Comercial, 500', 'São Paulo', 'SP', 'ativo');

-- ============================================================
-- 8. PRODUTOS
-- ============================================================
INSERT IGNORE INTO produtos (codigo, nome, descricao, categoria, quantidade, preco_compra, preco_venda, fornecedor, estoque_minimo) VALUES 
('PROD001', 'Notebook Dell', 'Notebook Dell Inspiron 15', 'Informática', 10, 2500.00, 3500.00, 'Dell Brasil', 3),
('PROD002', 'Monitor LG', 'Monitor LG 24" Full HD', 'Informática', 15, 800.00, 1200.00, 'LG Brasil', 5),
('PROD003', 'Teclado Logitech', 'Teclado Gamer Logitech', 'Periféricos', 30, 150.00, 250.00, 'Logitech', 10);

-- ============================================================
-- 9. FUNCIONÁRIOS
-- ============================================================
INSERT IGNORE INTO funcionarios (nome, cargo, departamento, email, telefone, data_admissao, salario, status) VALUES 
('João Silva', 'Gerente', 'Administração', 'joao@empresa.com', '777777777', '2020-01-15', 5000.00, 'ativo'),
('Maria Santos', 'Vendedor', 'Vendas', 'maria@empresa.com', '666666666', '2021-03-10', 3000.00, 'ativo'),
('Pedro Costa', 'Desenvolvedor', 'TI', 'pedro@empresa.com', '555555555', '2022-06-01', 4500.00, 'ativo');

-- ============================================================
-- 10. PLANOS DE MARKETING
-- ============================================================
INSERT IGNORE INTO plano_marketing (titulo, descricao, objetivo, publico_alvo, orcamento, data_inicio, data_fim, status) VALUES 
('Campanha Digital 2024', 'Campanha de marketing digital para redes sociais', 'Aumentar vendas em 20%', 'Jovens 18-35 anos', 15000.00, '2024-01-01', '2024-03-31', 'em_andamento');

-- ============================================================
-- 11. FATURAS PROFORMA
-- ============================================================
INSERT IGNORE INTO faturas_proforma (numero, cliente_id, data_emissao, data_validade, subtotal, desconto, total, observacoes, status, data_envio) VALUES 
('PF-2026-0001', 1, '2026-01-15', '2026-02-15', 9400.00, 100.00, 9300.00, 'Fatura para projeto especial', 'aprovada', '2026-01-16 10:00:00'),
('PF-2026-0002', 2, '2026-02-01', '2026-03-01', 250.00, 0.00, 250.00, 'Compra de teclados', 'enviada', '2026-02-02 09:30:00');

-- ============================================================
-- 12. ITENS FATURA PROFORMA
-- ============================================================
INSERT IGNORE INTO fatura_proforma_itens (fatura_id, produto_id, quantidade, preco_unitario, desconto, total) VALUES 
(1, 1, 2, 3500.00, 0.00, 7000.00),
(1, 2, 2, 1200.00, 100.00, 2300.00),
(2, 3, 1, 250.00, 0.00, 250.00);

-- ============================================================
-- 13. FATURAS RECIBO
-- ============================================================
INSERT IGNORE INTO faturas_recibo (numero, cliente_id, data_emissao, data_vencimento, subtotal, desconto, total, status, data_pagamento) VALUES 
('FR-2026-0001', 1, '2026-01-20', '2026-02-20', 5000.00, 100.00, 4900.00, 'pago', '2026-02-01 14:30:00'),
('FR-2026-0002', 2, '2026-02-10', '2026-03-10', 250.00, 0.00, 250.00, 'pendente', NULL);

-- ============================================================
-- 14. MOVIMENTAÇÕES DE CAIXA
-- ============================================================
INSERT IGNORE INTO movimentacoes_caixa (tipo, categoria, descricao, valor, desconto, iva, taxa_iva, data_movimento, forma_pagamento, cliente_id, produto_id, quantidade, status) VALUES 
('entrada', 'Vendas', 'Venda de 2x Notebook Dell', 7980.00, 0.00, 980.00, 14, '2026-01-16', 'pix', 1, 1, 2, 'confirmado'),
('entrada', 'Vendas', 'Venda de 2x Monitor LG', 2622.00, 0.00, 322.00, 14, '2026-01-16', 'cartao_credito', 1, 2, 2, 'confirmado'),
('entrada', 'Vendas', 'Venda de 1x Teclado Logitech', 285.00, 0.00, 35.00, 14, '2026-02-02', 'dinheiro', 2, 3, 1, 'confirmado');

-- ============================================================
-- 15. COMPROVANTES DE VENDA
-- ============================================================
INSERT IGNORE INTO comprovantes_venda (movimento_id, numero_comprovante, cliente_id, cliente_nome, cliente_nif, subtotal, desconto, valor_iva, taxa_iva, valor_total, forma_pagamento) VALUES 
(1, 'AGT-2026-0001', 1, 'Empresa XPTO', '1234567890', 7000.00, 0.00, 980.00, 14, 7980.00, 'pix'),
(2, 'AGT-2026-0002', 1, 'Empresa XPTO', '1234567890', 2300.00, 0.00, 322.00, 14, 2622.00, 'cartao_credito');

-- ============================================================
-- 16. DADOS DE EXEMPLO - MÓDULO ESCOLA
-- ============================================================

-- Alunos
INSERT IGNORE INTO alunos (nome, data_nascimento, genero, email, telefone, endereco, nome_pai, nome_mae, telefone_responsavel, documento, status, data_matricula) VALUES 
('Ana Oliveira', '2010-05-15', 'F', 'ana.oliveira@email.com', '912345678', 'Rua das Flores, 123', 'Carlos Oliveira', 'Maria Oliveira', '923456789', '123456789', 'ativo', '2026-01-15'),
('Pedro Santos', '2009-08-22', 'M', 'pedro.santos@email.com', '923456789', 'Av. Principal, 456', 'José Santos', 'Ana Santos', '934567890', '987654321', 'ativo', '2026-01-15'),
('Mariana Costa', '2010-11-10', 'F', 'mariana.costa@email.com', '934567890', 'Rua do Sol, 789', 'Roberto Costa', 'Fernanda Costa', '945678901', '456789123', 'ativo', '2026-02-01'),
('Lucas Ferreira', '2009-03-05', 'M', 'lucas.ferreira@email.com', '945678901', 'Rua da Lua, 101', 'André Ferreira', 'Carla Ferreira', '956789012', '789123456', 'ativo', '2026-02-01'),
('Julia Lima', '2010-07-19', 'F', 'julia.lima@email.com', '956789012', 'Av. das Estrelas, 202', 'Paulo Lima', 'Sofia Lima', '967890123', '321654987', 'ativo', '2026-02-15');

-- Professores
INSERT IGNORE INTO professores (nome, data_nascimento, genero, email, telefone, endereco, documento, especialidade, formacao, data_contratacao, salario, status) VALUES 
('Carlos Pereira', '1985-04-12', 'M', 'carlos.pereira@escola.com', '987654321', 'Rua dos Professores, 50', '111222333', 'Matemática', 'Licenciatura em Matemática - USP', '2020-01-15', 4500.00, 'ativo'),
('Ana Rodrigues', '1988-09-23', 'F', 'ana.rodrigues@escola.com', '987654322', 'Rua dos Educadores, 60', '222333444', 'Português', 'Licenciatura em Letras - UNESP', '2020-02-01', 4200.00, 'ativo'),
('Roberto Silva', '1982-11-30', 'M', 'roberto.silva@escola.com', '987654323', 'Av. dos Mestres, 70', '333444555', 'História', 'Licenciatura em História - PUC', '2021-01-10', 4000.00, 'ativo');

-- Turmas
INSERT IGNORE INTO turmas (nome, descricao, ano_letivo, sala, turno, capacidade, professor_id, status) VALUES 
('6º Ano A', 'Turma do 6º ano do Ensino Fundamental', '2026', 'Sala 101', 'manha', 30, 1, 'ativa'),
('6º Ano B', 'Turma do 6º ano do Ensino Fundamental', '2026', 'Sala 102', 'manha', 30, 2, 'ativa'),
('7º Ano A', 'Turma do 7º ano do Ensino Fundamental', '2026', 'Sala 201', 'tarde', 30, 3, 'ativa');

-- Disciplinas
INSERT IGNORE INTO disciplinas (nome, descricao, carga_horaria, professor_id, status) VALUES 
('Matemática', 'Matemática Básica e Avançada', 240, 1, 'ativa'),
('Português', 'Língua Portuguesa e Literatura', 200, 2, 'ativa'),
('História', 'História Geral e do Brasil', 160, 3, 'ativa'),
('Ciências', 'Ciências Naturais', 160, 1, 'ativa'),
('Geografia', 'Geografia Geral', 120, 3, 'ativa');

-- Matrículas
INSERT IGNORE INTO matriculas (aluno_id, turma_id, data_matricula, status) VALUES 
(1, 1, '2026-01-15', 'ativa'),
(2, 1, '2026-01-15', 'ativa'),
(3, 2, '2026-02-01', 'ativa'),
(4, 2, '2026-02-01', 'ativa'),
(5, 3, '2026-02-15', 'ativa');

-- Frequência (exemplos)
INSERT IGNORE INTO frequencia (matricula_id, data, status) VALUES 
(1, '2026-02-01', 'presente'),
(1, '2026-02-02', 'presente'),
(1, '2026-02-03', 'ausente'),
(2, '2026-02-01', 'presente'),
(2, '2026-02-02', 'presente'),
(2, '2026-02-03', 'presente');

-- Notas (exemplos)
INSERT IGNORE INTO notas (matricula_id, disciplina_id, bimestre, nota_1, nota_2, nota_3, nota_4, media, resultado) VALUES 
(1, 1, 1, 8.5, 9.0, NULL, NULL, 8.75, 'aprovado'),
(1, 2, 1, 7.0, 8.5, NULL, NULL, 7.75, 'aprovado'),
(2, 1, 1, 6.0, 7.5, NULL, NULL, 6.75, 'aprovado'),
(2, 2, 1, 5.5, 6.0, NULL, NULL, 5.75, 'recuperacao'),
(3, 1, 1, 9.0, 9.5, NULL, NULL, 9.25, 'aprovado');

-- Horários
INSERT IGNORE INTO horarios (turma_id, disciplina_id, dia_semana, hora_inicio, hora_fim) VALUES 
(1, 1, 'segunda', '08:00:00', '09:30:00'),
(1, 2, 'segunda', '09:45:00', '11:15:00'),
(1, 3, 'terca', '08:00:00', '09:30:00'),
(1, 4, 'quarta', '08:00:00', '09:30:00'),
(1, 5, 'quinta', '08:00:00', '09:30:00');

-- Emolumentos
INSERT IGNORE INTO emolumentos (nome, descricao, valor, tipo, status) VALUES 
('Matrícula 2026', 'Taxa de Matrícula para o ano letivo 2026', 300.00, 'matricula', 'ativo'),
('Mensalidade 6º Ano', 'Mensalidade para o 6º ano do Ensino Fundamental', 400.00, 'mensalidade', 'ativo'),
('Mensalidade 7º Ano', 'Mensalidade para o 7º ano do Ensino Fundamental', 450.00, 'mensalidade', 'ativo');

-- Pagamentos
INSERT IGNORE INTO pagamentos (aluno_id, emolumento_id, valor, data_pagamento, forma_pagamento, status) VALUES 
(1, 1, 300.00, '2026-01-10', 'transferencia', 'confirmado'),
(2, 1, 300.00, '2026-01-10', 'transferencia', 'confirmado'),
(3, 1, 300.00, '2026-01-25', 'pix', 'confirmado'),
(4, 1, 300.00, '2026-01-25', 'pix', 'confirmado'),
(5, 1, 300.00, '2026-02-05', 'dinheiro', 'confirmado');

-- Mensalidades
INSERT IGNORE INTO mensalidades (aluno_id, mes, ano, valor, data_vencimento, status) VALUES 
(1, 2, 2026, 400.00, '2026-02-10', 'pago'),
(2, 2, 2026, 400.00, '2026-02-10', 'pago'),
(3, 2, 2026, 400.00, '2026-02-10', 'pendente'),
(4, 2, 2026, 400.00, '2026-02-10', 'pendente'),
(5, 2, 2026, 450.00, '2026-02-15', 'pendente');

-- ============================================================
-- FIM DO ARQUIVO
-- ============================================================