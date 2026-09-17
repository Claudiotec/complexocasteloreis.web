# license_server.py
# Servidor de Licenças para SoftGest

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

# ============================================
# CONFIGURAÇÕES
# ============================================
SECRET_KEY = "SOFTGEST_SECRET_KEY_2026"  # Mude para uma chave única
DB_PATH = os.path.join(os.path.dirname(__file__), 'licenses.db')
API_VERSION = "1.0.0"

# ============================================
# GERADOR DE LICENÇAS
# ============================================
class LicenseGenerator:
    """Gerador de Licenças - Criação e Validação"""
    
    def __init__(self):
        self.prefixos = ['SG', 'LIC', 'PRO', 'ENT', 'BUS', 'TRIAL']
        self.init_database()
    
    def init_database(self):
        """Inicializa banco de dados SQLite"""
        try:
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
                    cliente_cnpj TEXT,
                    data_ativacao TEXT,
                    data_expiracao TEXT,
                    max_usuarios INTEGER DEFAULT 0,
                    modulos_liberados TEXT DEFAULT '["todos"]',
                    hardware_id TEXT,
                    observacoes TEXT,
                    criado_por TEXT,
                    criado_em TEXT DEFAULT CURRENT_TIMESTAMP,
                    atualizado_em TEXT
                )
            ''')
            
            # Tabela de logs
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS logs_licencas (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    licenca_id INTEGER,
                    codigo_licenca TEXT,
                    acao TEXT,
                    ip TEXT,
                    user_agent TEXT,
                    detalhes TEXT,
                    data_hora TEXT DEFAULT CURRENT_TIMESTAMP
                )
            ''')
            
            conn.commit()
            conn.close()
            print("✅ Banco de dados inicializado com sucesso!")
            
        except Exception as e:
            print(f"❌ Erro ao inicializar banco: {e}")
    
    # ============================================
    # GERAÇÃO DE CÓDIGOS
    # ============================================
    
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
        # Hash com salt
        base = f"{codigo}{SECRET_KEY}{datetime.now().year}"
        hash1 = hashlib.sha256(base.encode()).hexdigest()[:16].upper()
        
        # Adicionar parte aleatória
        random_part = ''.join(secrets.choice(string.ascii_uppercase + string.digits) for _ in range(4))
        
        # Formatar em blocos
        chave = f"{hash1[:4]}-{hash1[4:8]}-{hash1[8:12]}-{hash1[12:16]}-{random_part}"
        return chave
    
    def gerar_codigo_curto(self, prefixo='SG'):
        """Gera código curto para testes"""
        if prefixo not in self.prefixos:
            prefixo = 'SG'
        random_part = ''.join(secrets.choice(string.ascii_uppercase + string.digits) for _ in range(6))
        return f"{prefixo}-{random_part}"
    
    def gerar_codigo_com_data(self, prefixo='SG'):
        """Gera código com data"""
        if prefixo not in self.prefixos:
            prefixo = 'SG'
        data = datetime.now().strftime("%Y%m")
        random_part = ''.join(secrets.choice(string.ascii_uppercase + string.digits) for _ in range(6))
        return f"{prefixo}-{data}-{random_part}"
    
    def gerar_codigo_human_readable(self, prefixo='SG'):
        """Gera código legível por humanos"""
        if prefixo not in self.prefixos:
            prefixo = 'SG'
        
        partes = []
        for _ in range(3):
            parte = ''.join(secrets.choice(string.ascii_uppercase + string.digits) for _ in range(4))
            partes.append(parte)
        
        return f"{prefixo}-{'-'.join(partes)}"
    
    # ============================================
    # CRIAÇÃO DE LICENÇA
    # ============================================
    
    def criar_licenca(self, dados):
        """Cria uma nova licença"""
        try:
            # Validar dados
            tipo = dados.get('tipo', 'teste')
            if tipo not in ['teste', 'mensal', 'trimestral', 'anual', 'perpetua']:
                return {
                    'success': False,
                    'error': 'Tipo de licença inválido'
                }
            
            # Gerar código e chave
            prefixo = dados.get('prefixo', 'SG')
            codigo = self.gerar_codigo(prefixo)
            chave = self.gerar_chave_ativacao(codigo)
            
            # Calcular data de expiração
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
            
            # Modulos
            modulos = dados.get('modulos', ['todos'])
            if not isinstance(modulos, list):
                modulos = ['todos']
            
            # Conectar ao banco
            conn = sqlite3.connect(DB_PATH)
            cursor = conn.cursor()
            
            # Inserir licença
            cursor.execute('''
                INSERT INTO licencas (
                    codigo, chave_ativacao, tipo, status,
                    cliente_nome, cliente_email, cliente_empresa, cliente_cnpj,
                    data_expiracao, max_usuarios, modulos_liberados,
                    observacoes, criado_por
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ''', (
                codigo,
                chave,
                tipo,
                'ativa',
                dados.get('cliente_nome', ''),
                dados.get('cliente_email', ''),
                dados.get('cliente_empresa', ''),
                dados.get('cliente_cnpj', ''),
                data_expiracao,
                int(dados.get('max_usuarios', 0)),
                json.dumps(modulos),
                dados.get('observacoes', ''),
                dados.get('criado_por', 'API')
            ))
            
            licenca_id = cursor.lastrowid
            
            # Registrar log
            cursor.execute('''
                INSERT INTO logs_licencas (licenca_id, codigo_licenca, acao, detalhes)
                VALUES (?, ?, ?, ?)
            ''', (licenca_id, codigo, 'criacao', 'Licença criada com sucesso'))
            
            conn.commit()
            conn.close()
            
            return {
                'success': True,
                'codigo': codigo,
                'chave_ativacao': chave,
                'data_expiracao': data_expiracao,
                'tipo': tipo,
                'max_usuarios': int(dados.get('max_usuarios', 0)),
                'modulos': modulos
            }
            
        except Exception as e:
            return {
                'success': False,
                'error': str(e)
            }
    
    # ============================================
    # VALIDAÇÃO DE LICENÇA
    # ============================================
    
    def validar_licenca(self, codigo, hardware_id=None, dominio=None, ip=None):
        """Valida uma licença existente"""
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
            
            # Mapear dados
            licenca_dict = {
                'id': licenca[0],
                'codigo': licenca[1],
                'chave': licenca[2],
                'tipo': licenca[3],
                'status': licenca[4],
                'cliente_nome': licenca[5],
                'cliente_email': licenca[6],
                'cliente_empresa': licenca[7],
                'cliente_cnpj': licenca[8],
                'data_ativacao': licenca[9],
                'data_expiracao': licenca[10],
                'max_usuarios': licenca[11],
                'modulos': json.loads(licenca[12]) if licenca[12] else ['todos'],
                'hardware_id': licenca[13],
                'observacoes': licenca[14],
                'criado_por': licenca[15],
                'criado_em': licenca[16],
                'atualizado_em': licenca[17]
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
            
            # Verificar hardware ID (se definido)
            if licenca_dict['hardware_id'] and hardware_id:
                if licenca_dict['hardware_id'] != hardware_id:
                    return {
                        'valid': False,
                        'status': 'hardware_invalido',
                        'message': 'Hardware ID não corresponde'
                    }
            
            # Registrar validação
            cursor.execute('''
                INSERT INTO logs_licencas (licenca_id, codigo_licenca, acao, ip, detalhes)
                VALUES (?, ?, ?, ?, ?)
            ''', (licenca_dict['id'], codigo, 'validacao', ip or '', 'Licença validada com sucesso'))
            
            # Atualizar última validação
            cursor.execute('''
                UPDATE licencas SET atualizado_em = ? WHERE id = ?
            ''', (datetime.now().isoformat(), licenca_dict['id']))
            
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
    
    # ============================================
    # ATIVAÇÃO DE LICENÇA
    # ============================================
    
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
                    hardware_id = ?,
                    atualizado_em = ?
                WHERE id = ?
            ''', (datetime.now().isoformat(), hardware_id, datetime.now().isoformat(), licenca[0]))
            
            # Registrar log
            cursor.execute('''
                INSERT INTO logs_licencas (licenca_id, codigo_licenca, acao, detalhes)
                VALUES (?, ?, ?, ?)
            ''', (licenca[0], codigo, 'ativacao', 'Licença ativada com sucesso'))
            
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
    
    # ============================================
    # LISTAGEM E ESTATÍSTICAS
    # ============================================
    
    def listar_licencas(self, filtros=None):
        """Lista todas as licenças"""
        try:
            conn = sqlite3.connect(DB_PATH)
            cursor = conn.cursor()
            
            query = 'SELECT * FROM licencas ORDER BY id DESC'
            params = []
            
            if filtros and filtros.get('status'):
                query = 'SELECT * FROM licencas WHERE status = ? ORDER BY id DESC'
                params = [filtros['status']]
            
            cursor.execute(query, params)
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
                    'cnpj': row[8],
                    'data_ativacao': row[9],
                    'data_expiracao': row[10],
                    'max_usuarios': row[11],
                    'modulos': json.loads(row[12]) if row[12] else ['todos'],
                    'hardware_id': row[13],
                    'criado_em': row[16]
                })
            
            return licencas
            
        except Exception as e:
            return []
    
    def get_estatisticas(self):
        """Obtém estatísticas do sistema"""
        try:
            conn = sqlite3.connect(DB_PATH)
            cursor = conn.cursor()
            
            # Total
            cursor.execute('SELECT COUNT(*) FROM licencas')
            total = cursor.fetchone()[0]
            
            # Ativas
            cursor.execute('SELECT COUNT(*) FROM licencas WHERE status = "ativa"')
            ativas = cursor.fetchone()[0]
            
            # Expiradas
            cursor.execute('SELECT COUNT(*) FROM licencas WHERE status = "expirada"')
            expiradas = cursor.fetchone()[0]
            
            # Por tipo
            cursor.execute('SELECT tipo, COUNT(*) FROM licencas GROUP BY tipo')
            por_tipo = cursor.fetchall()
            
            # Expirando em 7 dias
            data_limite = (datetime.now() + timedelta(days=7)).isoformat()
            cursor.execute('SELECT COUNT(*) FROM licencas WHERE status = "ativa" AND data_expiracao <= ? AND data_expiracao IS NOT NULL', (data_limite,))
            expirando = cursor.fetchone()[0]
            
            conn.close()
            
            return {
                'total': total,
                'ativas': ativas,
                'expiradas': expiradas,
                'expirando': expirando,
                'por_tipo': dict(por_tipo)
            }
            
        except Exception as e:
            return {
                'total': 0,
                'ativas': 0,
                'expiradas': 0,
                'expirando': 0,
                'por_tipo': {}
            }

# ============================================
# INICIALIZAR GERADOR
# ============================================
generator = LicenseGenerator()

# ============================================
# ROTAS DA API
# ============================================

@app.route('/', methods=['GET'])
def home():
    """Página inicial da API"""
    return jsonify({
        'nome': 'SoftGest License Server',
        'versao': API_VERSION,
        'status': 'online',
        'endpoints': [
            {'path': '/api/licenca/gerar', 'method': 'POST', 'description': 'Gerar nova licença'},
            {'path': '/api/licenca/validar', 'method': 'POST', 'description': 'Validar licença'},
            {'path': '/api/licenca/ativar', 'method': 'POST', 'description': 'Ativar licença'},
            {'path': '/api/licenca/listar', 'method': 'GET', 'description': 'Listar licenças'},
            {'path': '/api/licenca/estatisticas', 'method': 'GET', 'description': 'Estatísticas'},
            {'path': '/api/licenca/gerar-lote', 'method': 'POST', 'description': 'Gerar lote de licenças'},
        ]
    })

@app.route('/api/licenca/gerar', methods=['POST'])
def api_gerar():
    """Gerar nova licença"""
    try:
        dados = request.get_json()
        if not dados:
            return jsonify({'success': False, 'error': 'Dados não fornecidos'}), 400
        
        resultado = generator.criar_licenca(dados)
        return jsonify(resultado)
        
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/licenca/validar', methods=['POST'])
def api_validar():
    """Validar licença existente"""
    try:
        dados = request.get_json()
        if not dados or not dados.get('codigo'):
            return jsonify({'valid': False, 'message': 'Código da licença é obrigatório'}), 400
        
        codigo = dados.get('codigo')
        hardware_id = dados.get('hardware_id')
        dominio = dados.get('dominio')
        ip = request.remote_addr
        
        resultado = generator.validar_licenca(codigo, hardware_id, dominio, ip)
        return jsonify(resultado)
        
    except Exception as e:
        return jsonify({'valid': False, 'message': str(e)}), 500

@app.route('/api/licenca/ativar', methods=['POST'])
def api_ativar():
    """Ativar licença"""
    try:
        dados = request.get_json()
        if not dados:
            return jsonify({'success': False, 'message': 'Dados não fornecidos'}), 400
        
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
        
    except Exception as e:
        return jsonify({'success': False, 'message': str(e)}), 500

@app.route('/api/licenca/listar', methods=['GET'])
def api_listar():
    """Listar todas as licenças"""
    try:
        status = request.args.get('status')
        filtros = {}
        if status:
            filtros['status'] = status
        
        licencas = generator.listar_licencas(filtros)
        return jsonify({
            'success': True,
            'licencas': licencas,
            'total': len(licencas)
        })
        
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/licenca/estatisticas', methods=['GET'])
def api_estatisticas():
    """Estatísticas do sistema"""
    try:
        stats = generator.get_estatisticas()
        return jsonify({
            'success': True,
            'estatisticas': stats
        })
        
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/licenca/gerar-lote', methods=['POST'])
def api_gerar_lote():
    """Gerar lote de licenças"""
    try:
        dados = request.get_json()
        quantidade = dados.get('quantidade', 5)
        
        if quantidade > 50:
            return jsonify({
                'success': False,
                'error': 'Máximo de 50 licenças por lote'
            }), 400
        
        licencas = []
        for i in range(quantidade):
            licenca = generator.criar_licenca({
                'tipo': dados.get('tipo', 'teste'),
                'prefixo': dados.get('prefixo', 'SG'),
                'cliente_nome': dados.get('cliente_nome', f'Cliente {i+1}'),
                'cliente_email': dados.get('cliente_email', f'cliente{i+1}@email.com'),
                'cliente_empresa': dados.get('cliente_empresa', f'Empresa {i+1}'),
                'max_usuarios': dados.get('max_usuarios', 5),
                'modulos': dados.get('modulos', ['todos']),
                'criado_por': 'API Lote'
            })
            if licenca['success']:
                licencas.append(licenca)
        
        return jsonify({
            'success': True,
            'licencas': licencas,
            'quantidade': len(licencas)
        })
        
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/licenca/buscar', methods=['GET'])
def api_buscar():
    """Buscar licença por código"""
    try:
        codigo = request.args.get('codigo')
        if not codigo:
            return jsonify({'success': False, 'error': 'Código não informado'}), 400
        
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        cursor.execute('SELECT * FROM licencas WHERE codigo = ?', (codigo,))
        row = cursor.fetchone()
        conn.close()
        
        if not row:
            return jsonify({'success': False, 'error': 'Licença não encontrada'}), 404
        
        return jsonify({
            'success': True,
            'licenca': {
                'id': row[0],
                'codigo': row[1],
                'tipo': row[3],
                'status': row[4],
                'cliente': row[5],
                'email': row[6],
                'empresa': row[7],
                'data_expiracao': row[10],
                'max_usuarios': row[11],
                'modulos': json.loads(row[12]) if row[12] else ['todos']
            }
        })
        
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

# ============================================
# INICIAR SERVIDOR
# ============================================
if __name__ == '__main__':
    print("=" * 50)
    print("🚀 SoftGest License Server")
    print("=" * 50)
    print(f"📌 Versão: {API_VERSION}")
    print(f"📌 Banco: {DB_PATH}")
    print("📌 Endpoints disponíveis:")
    print("   POST /api/licenca/gerar - Gerar nova licença")
    print("   POST /api/licenca/validar - Validar licença")
    print("   POST /api/licenca/ativar - Ativar licença")
    print("   GET  /api/licenca/listar - Listar licenças")
    print("   GET  /api/licenca/estatisticas - Estatísticas")
    print("   POST /api/licenca/gerar-lote - Gerar lote")
    print("   GET  /api/licenca/buscar - Buscar por código")
    print("=" * 50)
    print("✅ Servidor rodando em http://localhost:5000")
    print("=" * 50)
    
    app.run(debug=True, host='0.0.0.0', port=5000)
