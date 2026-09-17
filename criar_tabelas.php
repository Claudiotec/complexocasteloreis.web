<?php
// ============================================================
// SCRIPT PARA CRIAR TODAS AS TABELAS NO RENDER DB
// ============================================================

// Configuração do Render DB
$render_host = 'dpg-da5bufrm8hqs73c5dadg-a.virginia-postgres.render.com';
$render_port = '5432';
$render_db = 'softgest_web';
$render_user = 'softgest_web_user';
$render_pass = '0gN9IscY8pBk5EYSH7LeqXQy9f6WwMen';

echo "<h1>📊 Criando Tabelas no Render DB</h1>";
echo "<p>Iniciando em " . date('d/m/Y H:i:s') . "</p>";

try {
    // Conecta ao Render DB
    echo "<p>Conectando ao Render DB...</p>";
    $dsn = "pgsql:host=$render_host;port=$render_port;dbname=$render_db;sslmode=require;connect_timeout=30";
    $pdo = new PDO($dsn, $render_user, $render_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 60
    ]);
    echo "<p style='color:green'>✅ Conectado ao Render DB</p>";

    // ============================================================
    // SQL PARA CRIAR TODAS AS TABELAS
    // ============================================================
    
    $sqls = [
        // 1. TABELA: alunos
        "CREATE TABLE IF NOT EXISTS alunos (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            Sexo VARCHAR(1) DEFAULT 'M',
            dia INTEGER DEFAULT 0,
            mes INTEGER DEFAULT 0,
            Ano INTEGER DEFAULT 0,
            Idade INTEGER DEFAULT 0,
            Morada TEXT,
            Cadastro_Transporte VARCHAR(3) DEFAULT 'Não',
            Contacto_do_Aluno VARCHAR(20),
            Debilidade TEXT,
            Naturalidade VARCHAR(100),
            Municipio VARCHAR(100),
            Provincia VARCHAR(100),
            N_BI VARCHAR(20),
            Classe VARCHAR(10),
            Curso VARCHAR(50),
            Nome_do_Pai VARCHAR(100),
            Morada3 TEXT,
            Contacto4 VARCHAR(20),
            Ocupacao VARCHAR(100),
            Local_de_Trabalho VARCHAR(100),
            Nome_da_mae VARCHAR(100),
            Contacto_Mae VARCHAR(20),
            data_nascimento DATE,
            genero VARCHAR(5) DEFAULT 'M',
            email VARCHAR(100),
            telefone VARCHAR(20),
            endereco TEXT,
            nome_pai VARCHAR(100),
            nome_mae VARCHAR(100),
            telefone_responsavel VARCHAR(20),
            documento VARCHAR(20),
            status VARCHAR(20) DEFAULT 'ativo',
            data_matricula DATE,
            Ocupacao_do_Aluno VARCHAR(100),
            Periodo VARCHAR(20),
            Data_Emissao_do_BI DATE,
            Arq_identificacao VARCHAR(100),
            Situacao_Cadastro VARCHAR(20) DEFAULT 'Matrícula',
            TURMA VARCHAR(20),
            SALA VARCHAR(20),
            foto VARCHAR(255),
            observacoes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        // 2. TABELA: alunos_ano_anterior
        "CREATE TABLE IF NOT EXISTS alunos_ano_anterior (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(255),
            Sexo VARCHAR(10),
            dia VARCHAR(10),
            mes VARCHAR(20),
            Ano VARCHAR(10),
            Morada TEXT,
            Cadastro_Transporte VARCHAR(10),
            Contacto_do_Aluno VARCHAR(50),
            Debilidade VARCHAR(100),
            Idade INTEGER,
            Naturalidade VARCHAR(100),
            Municipio VARCHAR(100),
            Provincia VARCHAR(100),
            N_BI VARCHAR(50),
            Classe VARCHAR(10),
            Nome_do_Pai VARCHAR(255),
            Morada3 TEXT,
            Contacto4 VARCHAR(50),
            Ocupacao VARCHAR(100),
            Local_de_Trabalho VARCHAR(255),
            Nome_da_mae VARCHAR(255),
            Contacto_Mae VARCHAR(50),
            Data_Matricula DATE,
            Ocupacao_do_Aluno VARCHAR(100),
            Periodo VARCHAR(20),
            Data_Emissao_do_BI VARCHAR(50),
            Arq_identificacao VARCHAR(100),
            Situacao_Cadastro VARCHAR(50),
            TURMA VARCHAR(50),
            SALA VARCHAR(50),
            Curso VARCHAR(50)
        )",

        // 3. TABELA: usuarios
        "CREATE TABLE IF NOT EXISTS usuarios (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            telefone VARCHAR(20),
            telefone_verificado INTEGER DEFAULT 0,
            senha VARCHAR(255) NOT NULL,
            perfil VARCHAR(30) DEFAULT 'usuario',
            multi_perfil VARCHAR(255),
            status VARCHAR(20) DEFAULT 'pendente',
            created_by INTEGER,
            aprovado_em TIMESTAMP,
            ultimo_acesso TIMESTAMP,
            saldo_caixa DECIMAL(10,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        // 4. TABELA: modulos
        "CREATE TABLE IF NOT EXISTS modulos (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(50) NOT NULL,
            icone VARCHAR(50),
            url VARCHAR(100),
            ordem INTEGER DEFAULT 0,
            ativo INTEGER DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        // 5. TABELA: turmas
        "CREATE TABLE IF NOT EXISTS turmas (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(50) NOT NULL,
            descricao TEXT,
            ano_letivo VARCHAR(10),
            sala VARCHAR(20),
            turno VARCHAR(10) DEFAULT 'manha',
            capacidade INTEGER DEFAULT 30,
            professor_id INTEGER,
            status VARCHAR(20) DEFAULT 'ativa',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            idades VARCHAR(20) DEFAULT '',
            classe VARCHAR(10) NOT NULL DEFAULT '',
            curso VARCHAR(50) NOT NULL DEFAULT '',
            disciplinas TEXT,
            limite INTEGER DEFAULT 30
        )",

        // 6. TABELA: permissoes
        "CREATE TABLE IF NOT EXISTS permissoes (
            id SERIAL PRIMARY KEY,
            usuario_id INTEGER,
            modulo VARCHAR(50) NOT NULL,
            visualizar INTEGER DEFAULT 1,
            criar INTEGER DEFAULT 0,
            editar INTEGER DEFAULT 0,
            excluir INTEGER DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        // 7. TABELA: config_agt
        "CREATE TABLE IF NOT EXISTS config_agt (
            id SERIAL PRIMARY KEY,
            empresa_id INTEGER,
            nif VARCHAR(20) NOT NULL,
            nome_comercial VARCHAR(200),
            endereco TEXT,
            telefone VARCHAR(20),
            email VARCHAR(100),
            site VARCHAR(100),
            regime_iva VARCHAR(20) DEFAULT 'normal',
            taxa_iva_padrao DECIMAL(5,2) DEFAULT 14.00,
            serie_fatura VARCHAR(10) DEFAULT 'A',
            ultimo_numero INTEGER DEFAULT 1,
            codigo_validacao VARCHAR(50),
            certificado_digital TEXT,
            ativo INTEGER DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        // 8. TABELA: destribuicao_professores
        "CREATE TABLE IF NOT EXISTS destribuicao_professores (
            id SERIAL PRIMARY KEY,
            professor_id INTEGER NOT NULL,
            professor_nome VARCHAR(255) NOT NULL,
            turma_id INTEGER NOT NULL,
            turma_nome VARCHAR(100) NOT NULL,
            classe VARCHAR(50) NOT NULL,
            disciplinas TEXT NOT NULL,
            ano_letivo VARCHAR(20) DEFAULT '2026',
            tipo VARCHAR(20) DEFAULT 'PROFESSOR',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        // 9. TABELA: emolumentos
        "CREATE TABLE IF NOT EXISTS emolumentos (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            classe VARCHAR(50),
            descricao TEXT,
            mes_referencia INTEGER,
            ano_referencia INTEGER,
            valor DECIMAL(10,2) NOT NULL,
            codigo_iva VARCHAR(10) DEFAULT 'ISE',
            taxa_iva DECIMAL(5,2) DEFAULT 0,
            regime_iva VARCHAR(20) DEFAULT 'isento',
            categoria VARCHAR(20) DEFAULT 'servico',
            multa_tipo VARCHAR(20) DEFAULT 'percentual',
            multa_valor DECIMAL(10,2) DEFAULT 0,
            prazo_dias INTEGER DEFAULT 30,
            mes_inicio_multa INTEGER,
            ano_inicio_multa INTEGER,
            tipo VARCHAR(20) DEFAULT 'outro',
            status VARCHAR(10) DEFAULT 'ativo',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        // 10. TABELA: funcionarios
        "CREATE TABLE IF NOT EXISTS funcionarios (
            id SERIAL PRIMARY KEY,
            num_agente VARCHAR(50),
            nome VARCHAR(100) NOT NULL,
            categoria_actual VARCHAR(100),
            instituicao_salario VARCHAR(200),
            funcao_instituicao VARCHAR(200),
            disciplina_lecciona VARCHAR(200),
            formacao_disciplina VARCHAR(200),
            data_inicio_funcao DATE,
            data_inicio_instituicao DATE,
            num_bi VARCHAR(30),
            data_nascimento DATE,
            genero VARCHAR(1),
            habilitacoes_literarias VARCHAR(100),
            especialidade_medio VARCHAR(200),
            especialidade_superior VARCHAR(200),
            contacto_telefonico VARCHAR(20),
            municipio_residencia VARCHAR(100),
            cargo VARCHAR(50),
            departamento VARCHAR(50),
            email VARCHAR(100),
            salario_base DECIMAL(15,2),
            iban VARCHAR(34),
            telefone VARCHAR(20),
            data_admissao DATE,
            salario DECIMAL(10,2),
            status VARCHAR(20) DEFAULT 'ativo',
            foto VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        // 11. TABELA: forca_trabalho
        "CREATE TABLE IF NOT EXISTS forca_trabalho (
            id SERIAL PRIMARY KEY,
            numero_ordem VARCHAR(10),
            numero_instituicao VARCHAR(20),
            numero_agente VARCHAR(20),
            nome_completo VARCHAR(200) NOT NULL,
            categoria_actual VARCHAR(100),
            instituicao VARCHAR(200),
            funcao VARCHAR(100),
            disciplina VARCHAR(100),
            formacao_disciplina VARCHAR(50),
            data_inicio_funcao DATE,
            data_inicio_instituicao DATE,
            bi VARCHAR(30),
            data_nascimento DATE,
            genero VARCHAR(1),
            habilitacoes VARCHAR(100),
            especialidade_medio VARCHAR(100),
            especialidade_superior VARCHAR(100),
            contacto VARCHAR(20),
            municipio VARCHAR(100),
            email VARCHAR(100),
            status VARCHAR(20) DEFAULT 'ativo',
            device_id VARCHAR(255),
            foto VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        // 12. TABELA: licencas
        "CREATE TABLE IF NOT EXISTS licencas (
            id SERIAL PRIMARY KEY,
            cliente VARCHAR(255) NOT NULL,
            codigo_licenca VARCHAR(255) NOT NULL,
            tipo VARCHAR(50) DEFAULT 'anual',
            data_ativacao DATE,
            data_expiracao DATE NOT NULL,
            status VARCHAR(20) DEFAULT 'ativa',
            observacoes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        // 13. TABELA: pagamentos
        "CREATE TABLE IF NOT EXISTS pagamentos (
            id SERIAL PRIMARY KEY,
            aluno_id INTEGER NOT NULL,
            nome_aluno VARCHAR(255),
            emolumento_id INTEGER,
            valor DECIMAL(10,2) NOT NULL,
            codigo_iva VARCHAR(10) DEFAULT 'ISE',
            taxa_iva DECIMAL(5,2) DEFAULT 0,
            valor_iva DECIMAL(15,2) DEFAULT 0,
            data_pagamento DATE NOT NULL,
            forma_pagamento VARCHAR(20) DEFAULT 'dinheiro',
            referencia VARCHAR(50),
            numero_fatura VARCHAR(50),
            hash_autenticacao VARCHAR(255),
            status VARCHAR(20) DEFAULT 'confirmado',
            observacoes TEXT,
            mes_referencia VARCHAR(20) DEFAULT '-',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        // 14. TABELA: notificacoes
        "CREATE TABLE IF NOT EXISTS notificacoes (
            id SERIAL PRIMARY KEY,
            tipo VARCHAR(50) NOT NULL,
            titulo VARCHAR(200) NOT NULL,
            mensagem TEXT NOT NULL,
            link VARCHAR(255),
            icone VARCHAR(50),
            cor VARCHAR(20) DEFAULT 'gold',
            lido INTEGER DEFAULT 0,
            usuario_id INTEGER,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        // 15. TABELA: logs_escolares
        "CREATE TABLE IF NOT EXISTS logs_escolares (
            id SERIAL PRIMARY KEY,
            usuario_id INTEGER,
            usuario_nome VARCHAR(100),
            acao VARCHAR(50) NOT NULL,
            tabela VARCHAR(50) NOT NULL,
            registro_id VARCHAR(50),
            descricao TEXT,
            ip VARCHAR(45),
            data_hora TIMESTAMP NOT NULL
        )",

        // 16. TABELA: mensalidades
        "CREATE TABLE IF NOT EXISTS mensalidades (
            id SERIAL PRIMARY KEY,
            aluno_id INTEGER NOT NULL,
            turma_id INTEGER,
            emolumento_id INTEGER,
            tipo_emolumento VARCHAR(50),
            mes INTEGER NOT NULL,
            ano INTEGER NOT NULL,
            valor DECIMAL(10,2) NOT NULL,
            valor_pago DECIMAL(10,2) DEFAULT 0,
            data_vencimento DATE,
            status VARCHAR(20) DEFAULT 'pendente',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            descricao VARCHAR(255),
            num_documento VARCHAR(50),
            multa_tipo VARCHAR(20) DEFAULT 'percentual',
            multa_valor DECIMAL(10,2) DEFAULT 0,
            prazo_dias INTEGER DEFAULT 30,
            ano_letivo_inicio INTEGER,
            ano_letivo_fim INTEGER
        )",

        // 17. TABELA: disciplinas
        "CREATE TABLE IF NOT EXISTS disciplinas (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            descricao TEXT,
            carga_horaria INTEGER,
            professor_id INTEGER,
            status VARCHAR(10) DEFAULT 'ativa',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        // 18. TABELA: disciplina_turma
        "CREATE TABLE IF NOT EXISTS disciplina_turma (
            id SERIAL PRIMARY KEY,
            disciplina_id INTEGER NOT NULL,
            turma_id INTEGER NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        // 19. TABELA: notas_alunos
        "CREATE TABLE IF NOT EXISTS notas_alunos (
            id SERIAL PRIMARY KEY,
            id_aluno VARCHAR(20) NOT NULL,
            nome_aluno VARCHAR(100) NOT NULL,
            disciplina VARCHAR(100) NOT NULL,
            turma VARCHAR(20) NOT NULL,
            classe VARCHAR(10) NOT NULL,
            mac_t1 DECIMAL(5,2) DEFAULT 0,
            npt_t1 DECIMAL(5,2) DEFAULT 0,
            mt1 DECIMAL(5,2) DEFAULT 0,
            mac_t2 DECIMAL(5,2) DEFAULT 0,
            npt_t2 DECIMAL(5,2) DEFAULT 0,
            mt2 DECIMAL(5,2) DEFAULT 0,
            mac_t3 DECIMAL(5,2) DEFAULT 0,
            npt_t3 DECIMAL(5,2) DEFAULT 0,
            mt3 DECIMAL(5,2) DEFAULT 0,
            neo DECIMAL(5,2) DEFAULT 0,
            en DECIMAL(5,2) DEFAULT 0,
            mec DECIMAL(5,2) DEFAULT 0,
            mfed DECIMAL(5,2) DEFAULT 0,
            mfd DECIMAL(5,2) DEFAULT 0,
            classificacao VARCHAR(20) DEFAULT '',
            sexo VARCHAR(10),
            idade INTEGER,
            sala VARCHAR(10),
            turno VARCHAR(20),
            ano_letivo VARCHAR(20),
            data_lancamento DATE,
            data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        // 20. TABELA: frequencia
        "CREATE TABLE IF NOT EXISTS frequencia (
            id SERIAL PRIMARY KEY,
            aluno_id INTEGER NOT NULL,
            turma_id INTEGER NOT NULL,
            disciplina_id INTEGER NOT NULL,
            data DATE NOT NULL,
            status VARCHAR(20) DEFAULT 'presente',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    ];

    // ============================================================
    // EXECUTAR CRIAÇÃO DAS TABELAS
    // ============================================================
    echo "<h2>📊 Criando tabelas...</h2>";
    
    $count = 0;
    $total = count($sqls);
    
    foreach ($sqls as $sql) {
        $count++;
        try {
            $pdo->exec($sql);
            echo "<p style='color:green'>✅ Tabela criada (" . $count . "/" . $total . ")</p>";
        } catch (PDOException $e) {
            echo "<p style='color:orange'>⚠️ " . $e->getMessage() . "</p>";
        }
    }

    echo "<h2 style='color:green'>✅ TABELAS CRIADAS COM SUCESSO! ($count tabelas)</h2>";
    echo "<p><a href='migrar_dados.php' class='btn btn-success'>🔜 Próximo: Migrar Dados</a></p>";
    echo "<p><a href='/' class='btn btn-primary'>Voltar ao Sistema</a></p>";

} catch (PDOException $e) {
    echo "<h2 style='color:red'>❌ ERRO: " . $e->getMessage() . "</h2>";
    echo "<p>Verifique se o banco de dados está acessível.</p>";
}
?>