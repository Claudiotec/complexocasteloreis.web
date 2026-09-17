-- Adicionar campos extras na tabela correspondencias
ALTER TABLE correspondencias 
ADD COLUMN enviado_em TIMESTAMP NULL,
ADD COLUMN mensagem_erro TEXT,
ADD COLUMN destinatario_id INT,
ADD COLUMN modelo VARCHAR(100);

-- Criar tabela de modelos de mensagem
CREATE TABLE modelos_mensagem (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    assunto VARCHAR(200),
    conteudo TEXT,
    tipo ENUM('email','whatsapp','sms') DEFAULT 'email',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Inserir modelos padrão
INSERT INTO modelos_mensagem (nome, assunto, conteudo, tipo) VALUES 
('Fatura Proforma', 'Fatura Proforma {{numero}}', 'Prezado(a) {{cliente}},

Segue em anexo a fatura proforma nº {{numero}} no valor de R$ {{total}}.

Data de vencimento: {{vencimento}}

Atenciosamente,
{{empresa}}', 'email'),
('Recibo de Pagamento', 'Recibo nº {{numero}}', 'Prezado(a) {{cliente}},

Confirmamos o recebimento do pagamento no valor de R$ {{total}} referente ao recibo nº {{numero}}.

Atenciosamente,
{{empresa}}', 'email');