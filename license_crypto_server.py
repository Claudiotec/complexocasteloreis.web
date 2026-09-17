# license_crypto_server.py
from flask import Flask, request, jsonify
from flask_cors import CORS
import sqlite3
import json
from datetime import datetime
from license_crypto_generator import CryptoLicenseGenerator

app = Flask(__name__)
CORS(app)

generator = CryptoLicenseGenerator()

@app.route('/api/licenca/gerar-crypto', methods=['POST'])
def gerar_licenca_crypto():
    """Gera licença criptografada"""
    dados = request.get_json()
    
    # Validar dados obrigatórios
    if not dados.get('cliente_email'):
        return jsonify({'error': 'Email do cliente é obrigatório'}), 400
    
    # Gerar licença
    resultado = generator.criar_licenca(dados)
    
    if resultado['success']:
        # Salvar no banco
        conn = sqlite3.connect('licenses.db')
        cursor = conn.cursor()
        
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS licencas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                codigo TEXT UNIQUE,
                chave_ativacao TEXT,
                chave_cliente TEXT,
                dados_cripto TEXT,
                assinatura TEXT,
                data_criacao TEXT
            )
        ''')
        
        cursor.execute('''
            INSERT INTO licencas (codigo, chave_ativacao, chave_cliente, dados_cripto, assinatura, data_criacao)
            VALUES (?, ?, ?, ?, ?, ?)
        ''', (
            resultado['codigo'],
            resultado['chave_ativacao'],
            resultado['chave_cliente'],
            resultado['dados_cripto'],
            resultado['assinatura'],
            datetime.now().isoformat()
        ))
        
        conn.commit()
        conn.close()
        
        return jsonify(resultado)
    
    return jsonify(resultado), 400

@app.route('/api/licenca/validar-crypto', methods=['POST'])
def validar_licenca_crypto():
    """Valida licença criptografada"""
    dados = request.get_json()
    
    codigo = dados.get('codigo')
    chave_ativacao = dados.get('chave_ativacao')
    chave_cliente = dados.get('chave_cliente')
    
    if not codigo or not chave_ativacao or not chave_cliente:
        return jsonify({'valid': False, 'message': 'Dados incompletos'}), 400
    
    resultado = generator.validar_licenca(codigo, chave_ativacao, chave_cliente)
    return jsonify(resultado)

@app.route('/api/licenca/verificar-chave', methods=['POST'])
def verificar_chave():
    """Verifica se a chave corresponde ao código"""
    dados = request.get_json()
    
    codigo = dados.get('codigo')
    chave_ativacao = dados.get('chave_ativacao')
    email = dados.get('email')
    empresa = dados.get('empresa', '')
    
    # Gerar chave esperada
    chave_cliente = generator.gerar_chave_cliente(email, empresa)
    chave_esperada = generator.gerar_chave_ativacao(codigo, chave_cliente)
    
    return jsonify({
        'corresponde': chave_ativacao == chave_esperada,
        'chave_esperada': chave_esperada,
        'chave_fornecida': chave_ativacao
    })

if __name__ == '__main__':
    print("🔐 Servidor de Licenças Criptografado")
    print("📌 Endpoints:")
    print("   POST /api/licenca/gerar-crypto - Gerar licença criptografada")
    print("   POST /api/licenca/validar-crypto - Validar licença")
    print("   POST /api/licenca/verificar-chave - Verificar chave")
    app.run(debug=True, host='0.0.0.0', port=5000)
