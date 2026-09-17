# license_server.py
from flask import Flask, request, jsonify
from flask_cors import CORS
import hashlib
import secrets
import string
import sqlite3
import json
from datetime import datetime, timedelta
import os
import re

app = Flask(__name__)
CORS(app)

# Configuração
SECRET_KEY = "SOFTGEST_SECRET_KEY_2026"
DB_PATH = os.path.join(os.path.dirname(__file__), 'licenses.db')

class LicenseGenerator:
    """Gerador de Licenças"""
    
    def __init__(self):
        self.prefixos = ['SG', 'LIC', 'PRO', 'ENT', 'BUS']
        self.init_database()
    
    def init_database(self):
        """Inicializa banco de dados SQLite"""
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        
        # Tabela de licenças
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS licencas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                codigo TEXT UNIQUE NOT NULL,
                chave_ativacao TEXT NOT NULL,
                tipo TEXT DEFAULT 'teste',
                status TEXT DEFAULT 'ativa',
                cliente_nome TEXT,
                cliente_email TEXT,
                cliente_empresa TEXT,
                data_ativacao TEXT,
                data_expiracao TEXT,
                max_usuarios INTEGER DEFAULT 0,
                modulos_liberados TEXT DEFAULT '["todos"]',
                hardware_id TEXT,
                criado_em TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ''')
        
        # Tabela de logs
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                codigo_licenca TEXT,
                acao TEXT,
                ip TEXT,
                detalhes TEXT,
                data_hora TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ''')
        
        conn.commit()
        conn.close()
    
    def gerar_codigo(self, prefixo='SG'):
        """Gera código único da licença"""
        if prefixo not in self.prefixos:
            prefixo = 'SG'
        
        ano = datetime.now().strftime('%Y')
        mes = datetime.now().strftime('%m')
        random_part = ''.join(secrets.choice(string.ascii_uppercase + string.digits) for _ in range(8))
        return f"{prefixo}-{ano}{mes}-{random_part}"
    
    def gerar_chave_ativacao(self, codigo):
        """Gera chave de ativação baseada no código"""
        return hashlib.sha256((codigo + SECRET_KEY).encode()).hexdigest()[:16].upper()
    
    def criar_licenca(self, dados):
        """Cria uma nova licença"""
        try:
            codigo = self.gerar_codigo(dados.get('prefixo', 'SG'))
            chave = self.gerar_chave_ativacao(codigo)
            
            # Calcular data de expiração
            tipo = dados.get('tipo', 'teste')
            dias = {
                'teste': 30,
                'mensal': 30,
                'trimestral': 90,
                'anual': 365,
                'perpetua': None
            }.get(tipo, 30)
            
            data_expiracao = None
            if dias:
                data_expiracao = (datetime.now() + timedelta(days=dias)).isoformat()
            
            conn = sqlite3.connect(DB_PATH)
            cursor = conn.cursor()
            
            cursor.execute('''
                INSERT INTO licencas (
                    codigo, chave_ativacao, tipo, status,
                    cliente_nome, cliente_email, cliente_empresa,
                    data_expiracao, max_usuarios, modulos_liberados
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ''', (
                codigo,
                chave,
                dados.get('tipo', 'teste'),
                'ativa',
                dados.get('cliente_nome', ''),
                dados.get('cliente_email', ''),
                dados.get('cliente_empresa', ''),
                data_expiracao,
                int(dados.get('max_usuarios', 0)),
                json.dumps(dados.get('modulos', ['todos']))
            ))
            
            conn.commit()
            conn.close()
            
            return {
                'success': True,
                'codigo': codigo,
                'chave_ativacao': chave,
                'data_expiracao': data_expiracao,
                'tipo': tipo
            }
            
        except Exception as e:
            return {
                'success': False,
                'error': str(e)
            }
    
    def validar_licenca(self, codigo, hardware_id=None, dominio=None, ip=None):
        """Valida uma licença"""
        try:
            conn = sqlite3.connect(DB_PATH)
            cursor = conn.cursor()
            
            cursor.execute('SELECT * FROM licencas WHERE codigo = ?', (codigo,))
            licenca = cursor.fetchone()
            
            if not licenca:
                return {
                    'valid': False,
                    'status': 'invalida',
                    'message': 'Código de licença inválido'
                }
            
            # Estrutura da licença
            licenca_dict = {
                'id': licenca[0],
                'codigo': licenca[1],
                'chave': licenca[2],
                'tipo': licenca[3],
                'status': licenca[4],
                'cliente_nome': licenca[5],
                'cliente_email': licenca[6],
                'cliente_empresa': licenca[7],
                'data_ativacao': licenca[8],
                'data_expiracao': licenca[9],
                'max_usuarios': licenca[10],
                'modulos': json.loads(licenca[11]) if licenca[11] else ['todos'],
                'hardware_id': licenca[12],
                'criado_em': licenca[13]
            }
            
            # Verificar status
            if licenca_dict['status'] != 'ativa':
                return {
                    'valid': False,
                    'status': licenca_dict['status'],
                    'message': f'Licença {licenca_dict["status"]}'
                }
            
            # Verificar expiração
            if licenca_dict['data_expiracao']:
                expiracao = datetime.fromisoformat(licenca_dict['data_expiracao'])
                if expiracao < datetime.now():
                    # Atualizar status
                    cursor.execute('UPDATE licencas SET status = "expirada" WHERE id = ?', (licenca_dict['id'],))
                    conn.commit()
                    return {
                        'valid': False,
                        'status': 'expirada',
                        'message': 'Licença expirada'
                    }
            
            # Registrar validação
            cursor.execute('''
                INSERT INTO logs (codigo_licenca, acao, ip, detalhes)
                VALUES (?, ?, ?, ?)
            ''', (codigo, 'validacao', ip or '', 'Licença validada com sucesso'))
            conn.commit()
            conn.close()
            
            # Calcular dias restantes
            dias_restantes = None
            if licenca_dict['data_expiracao']:
                expiracao = datetime.fromisoformat(licenca_dict['data_expiracao'])
                dias_restantes = (expiracao - datetime.now()).days
            
            return {
                'valid': True,
                'status': 'ativa',
                'tipo': licenca_dict['tipo'],
                'data_expiracao': licenca_dict['data_expiracao'],
                'dias_restantes': dias_restantes,
                'max_usuarios': licenca_dict['max_usuarios'],
                'modulos': licenca_dict['modulos'],
                'cliente': licenca_dict['cliente_nome'],
                'empresa': licenca_dict['cliente_empresa'],
                'message': 'Licença válida'
            }
            
        except Exception as e:
            return {
                'valid': False,
                'status': 'erro',
                'message': str(e)
            }
    
    def ativar_licenca(self, codigo, chave_ativacao, hardware_id=None):
        """Ativa uma licença"""
        try:
            conn = sqlite3.connect(DB_PATH)
            cursor = conn.cursor()
            
            cursor.execute('SELECT * FROM licencas WHERE codigo = ? AND chave_ativacao = ?', (codigo, chave_ativacao))
            licenca = cursor.fetchone()
            
            if not licenca:
                return {
                    'success': False,
                    'message': 'Código ou chave de ativação inválidos'
                }
            
            # Verificar se já está ativa
            if licenca[4] == 'ativa':
                return {
                    'success': False,
                    'message': 'Licença já está ativa'
                }
            
            # Ativar licença
            cursor.execute('''
                UPDATE licencas 
                SET status = 'ativa', 
                    data_ativacao = ?,
                    hardware_id = ?
                WHERE id = ?
            ''', (datetime.now().isoformat(), hardware_id, licenca[0]))
            
            conn.commit()
            conn.close()
            
            return {
                'success': True,
                'message': 'Licença ativada com sucesso!',
                'codigo': codigo
            }
            
        except Exception as e:
            return {
                'success': False,
                'message': str(e)
            }
    
    def listar_licencas(self):
        """Lista todas as licenças"""
        try:
            conn = sqlite3.connect(DB_PATH)
            cursor = conn.cursor()
            cursor.execute('SELECT * FROM licencas ORDER BY id DESC')
            rows = cursor.fetchall()
            conn.close()
            
            licencas = []
            for row in rows:
                licencas.append({
                    'id': row[0],
                    'codigo': row[1],
                    'chave': row[2],
                    'tipo': row[3],
                    'status': row[4],
                    'cliente': row[5],
                    'email': row[6],
                    'empresa': row[7],
                    'data_ativacao': row[8],
                    'data_expiracao': row[9],
                    'max_usuarios': row[10],
                    'modulos': json.loads(row[11]) if row[11] else ['todos']
                })
            
            return licencas
            
        except Exception as e:
            return []

# ==================== ROTAS DA API ====================

generator = LicenseGenerator()

@app.route('/api/licenca/gerar', methods=['POST'])
def api_gerar():
    """Gerar nova licença"""
    dados = request.get_json()
    resultado = generator.criar_licenca(dados)
    return jsonify(resultado)

@app.route('/api/licenca/validar', methods=['POST'])
def api_validar():
    """Validar licença existente"""
    dados = request.get_json()
    codigo = dados.get('codigo')
    hardware_id = dados.get('hardware_id')
    dominio = dados.get('dominio')
    ip = request.remote_addr
    
    if not codigo:
        return jsonify({
            'valid': False,
            'message': 'Código da licença é obrigatório'
        }), 400
    
    resultado = generator.validar_licenca(codigo, hardware_id, dominio, ip)
    return jsonify(resultado)

@app.route('/api/licenca/ativar', methods=['POST'])
def api_ativar():
    """Ativar licença"""
    dados = request.get_json()
    codigo = dados.get('codigo')
    chave = dados.get('chave_ativacao')
    hardware_id = dados.get('hardware_id')
    
    if not codigo or not chave:
        return jsonify({
            'success': False,
            'message': 'Código e chave de ativação são obrigatórios'
        }), 400
    
    resultado = generator.ativar_licenca(codigo, chave, hardware_id)
    return jsonify(resultado)

@app.route('/api/licenca/listar', methods=['GET'])
def api_listar():
    """Listar todas as licenças"""
    licencas = generator.listar_licencas()
    return jsonify({
        'success': True,
        'licencas': licencas,
        'total': len(licencas)
    })

@app.route('/api/licenca/estatisticas', methods=['GET'])
def api_estatisticas():
    """Estatísticas do sistema"""
    licencas = generator.listar_licencas()
    total = len(licencas)
    ativas = sum(1 for l in licencas if l['status'] == 'ativa')
    
    return jsonify({
        'success': True,
        'total': total,
        'ativas': ativas,
        'inativas': total - ativas
    })

if __name__ == '__main__':
    print("🚀 Servidor de Licenças iniciado em http://localhost:5000")
    print("📌 Endpoints disponíveis:")
    print("   POST /api/licenca/gerar - Gerar nova licença")
    print("   POST /api/licenca/validar - Validar licença")
    print("   POST /api/licenca/ativar - Ativar licença")
    print("   GET  /api/licenca/listar - Listar licenças")
    print("   GET  /api/licenca/estatisticas - Estatísticas")
    app.run(debug=True, host='0.0.0.0', port=5000)
