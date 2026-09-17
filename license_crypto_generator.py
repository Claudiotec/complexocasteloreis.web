# license_crypto_generator.py
import hashlib
import secrets
import string
import json
import base64
from datetime import datetime, timedelta
from cryptography.fernet import Fernet
from cryptography.hazmat.primitives import hashes
from cryptography.hazmat.primitives.kdf.pbkdf2 import PBKDF2HMAC

class CryptoLicenseGenerator:
    """Gerador de Licenças com Criptografia"""
    
    def __init__(self, master_key="SOFTGEST_MASTER_KEY_2026"):
        self.master_key = master_key
        
    def gerar_chave_cliente(self, email, empresa):
        """Gera uma chave única para o cliente baseada no email e empresa"""
        base = f"{email}|{empresa}|{self.master_key}"
        return hashlib.sha256(base.encode()).hexdigest()[:32]
    
    def gerar_codigo_licenca(self, prefixo="SG"):
        """Gera código único da licença"""
        ano = datetime.now().strftime('%Y')
        mes = datetime.now().strftime('%m')
        random_part = ''.join(secrets.choice(string.ascii_uppercase + string.digits) for _ in range(8))
        return f"{prefixo}-{ano}{mes}-{random_part}"
    
    def gerar_chave_ativacao(self, codigo, chave_cliente):
        """Gera chave de ativação baseada no código + chave do cliente"""
        base = f"{codigo}|{chave_cliente}|{self.master_key}"
        chave = hashlib.sha256(base.encode()).hexdigest()[:16].upper()
        return f"{chave[:4]}-{chave[4:8]}-{chave[8:12]}-{chave[12:16]}"
    
    def criptografar_licenca(self, dados, chave_cliente):
        """Criptografa os dados da licença usando a chave do cliente"""
        # Criar chave Fernet a partir da chave do cliente
        kdf = PBKDF2HMAC(
            algorithm=hashes.SHA256(),
            length=32,
            salt=self.master_key.encode(),
            iterations=100000,
        )
        key = base64.urlsafe_b64encode(kdf.derive(chave_cliente.encode()))
        f = Fernet(key)
        
        # Serializar e criptografar
        dados_str = json.dumps(dados)
        dados_cripto = f.encrypt(dados_str.encode())
        
        return base64.b64encode(dados_cripto).decode()
    
    def descriptografar_licenca(self, dados_cripto_b64, chave_cliente):
        """Descriptografa os dados da licença usando a chave do cliente"""
        try:
            # Criar chave Fernet
            kdf = PBKDF2HMAC(
                algorithm=hashes.SHA256(),
                length=32,
                salt=self.master_key.encode(),
                iterations=100000,
            )
            key = base64.urlsafe_b64encode(kdf.derive(chave_cliente.encode()))
            f = Fernet(key)
            
            # Descriptografar
            dados_cripto = base64.b64decode(dados_cripto_b64)
            dados_str = f.decrypt(dados_cripto).decode()
            
            return json.loads(dados_str)
            
        except Exception as e:
            return None
    
    def criar_licenca(self, dados_cliente):
        """Cria uma licença completa com criptografia"""
        # Extrair dados
        email = dados_cliente.get('email')
        empresa = dados_cliente.get('empresa', '')
        nome = dados_cliente.get('nome', '')
        
        # Gerar chave do cliente
        chave_cliente = self.gerar_chave_cliente(email, empresa)
        
        # Gerar código
        codigo = self.gerar_codigo_licenca(dados_cliente.get('prefixo', 'SG'))
        
        # Gerar chave de ativação
        chave_ativacao = self.gerar_chave_ativacao(codigo, chave_cliente)
        
        # Dados da licença
        dados_licenca = {
            'codigo': codigo,
            'tipo': dados_cliente.get('tipo', 'anual'),
            'cliente_nome': nome,
            'cliente_email': email,
            'cliente_empresa': empresa,
            'max_usuarios': dados_cliente.get('max_usuarios', 5),
            'modulos': dados_cliente.get('modulos', ['todos']),
            'data_geracao': datetime.now().isoformat(),
            'data_expiracao': (datetime.now() + timedelta(days=365)).isoformat(),
            'chave_cliente_hash': hashlib.sha256(chave_cliente.encode()).hexdigest()[:16]
        }
        
        # Criptografar
        dados_cripto = self.criptografar_licenca(dados_licenca, chave_cliente)
        
        # Criar assinatura digital
        assinatura = hashlib.sha256(
            f"{codigo}|{chave_ativacao}|{dados_cripto}|{self.master_key}".encode()
        ).hexdigest()[:32]
        
        return {
            'success': True,
            'codigo': codigo,
            'chave_ativacao': chave_ativacao,
            'chave_cliente': chave_cliente,
            'dados_cripto': dados_cripto,
            'assinatura': assinatura,
            'dados': dados_licenca,
            'data_expiracao': dados_licenca['data_expiracao']
        }
    
    def validar_licenca(self, codigo, chave_ativacao, chave_cliente):
        """Valida uma licença usando a chave do cliente"""
        try:
            # Buscar licença no banco
            conn = sqlite3.connect('licenses.db')
            cursor = conn.cursor()
            
            cursor.execute('SELECT dados_cripto, assinatura FROM licencas WHERE codigo = ? AND chave_ativacao = ?', (codigo, chave_ativacao))
            resultado = cursor.fetchone()
            conn.close()
            
            if not resultado:
                return {'valid': False, 'message': 'Licença não encontrada'}
            
            dados_cripto, assinatura_armazenada = resultado
            
            # Descriptografar
            dados = self.descriptografar_licenca(dados_cripto, chave_cliente)
            
            if not dados:
                return {'valid': False, 'message': 'Chave inválida - não foi possível descriptografar'}
            
            # Verificar assinatura
            assinatura_calculada = hashlib.sha256(
                f"{codigo}|{chave_ativacao}|{dados_cripto}|{self.master_key}".encode()
            ).hexdigest()[:32]
            
            if assinatura_calculada != assinatura_armazenada:
                return {'valid': False, 'message': 'Assinatura inválida - licença pode estar corrompida'}
            
            # Verificar expiração
            if dados.get('data_expiracao'):
                expiracao = datetime.fromisoformat(dados['data_expiracao'])
                if expiracao < datetime.now():
                    return {'valid': False, 'message': 'Licença expirada'}
            
            return {
                'valid': True,
                'dados': dados,
                'message': 'Licença válida'
            }
            
        except Exception as e:
            return {'valid': False, 'message': str(e)}
