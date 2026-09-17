#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""
SOFTGEST WEB - GERADOR DE LICENÇAS
Versão 1.0
"""

import json
import hashlib
import hmac
import datetime
import os
import argparse
import sys
import uuid

# ============================================================
# CONFIGURAÇÕES - CHAVE DE ASSINATURA
# ============================================================
LICENSE_KEY = "SoftGestWeb2026!@#$%^&*()_+"  # ← MESMA CHAVE DO PHP
LICENSE_FILE = "license.dat"

# ============================================================
# FUNÇÕES
# ============================================================

def gerar_licenca(cliente, anos=1, dominios=None):
    if dominios is None:
        dominios = ['localhost', '127.0.0.1']
    
    data_atual = datetime.datetime.now()
    data_expiracao = data_atual + datetime.timedelta(days=anos * 365)
    licenca_id = str(uuid.uuid4())[:8].upper()
    
    dados = {
        'licenca_id': f'SG-{licenca_id}-{data_atual.year}',
        'cliente': cliente,
        'data_emissao': data_atual.strftime('%Y-%m-%d'),
        'data_expiracao': data_expiracao.strftime('%Y-%m-%d'),
        'anos': anos,
        'dominios': dominios,
        'status': 'ativo',
        'versao': '3.0'
    }
    
    # Ordenar chaves para consistência (MESMO QUE O PHP)
    dados_ordenados = dict(sorted(dados.items()))
    
    # Criar assinatura (MESMO FORMATO DO PHP)
    dados_json = json.dumps(dados_ordenados, ensure_ascii=False, separators=(',', ':'))
    assinatura = hmac.new(
        LICENSE_KEY.encode('utf-8'),
        dados_json.encode('utf-8'),
        hashlib.sha256
    ).hexdigest()
    
    dados['assinatura'] = assinatura
    
    return dados

def salvar_licenca(dados, arquivo=LICENSE_FILE):
    os.makedirs(os.path.dirname(arquivo) or '.', exist_ok=True)
    with open(arquivo, 'w', encoding='utf-8') as f:
        json.dump(dados, f, indent=2, ensure_ascii=False)
    print(f"✅ Licença salva em: {arquivo}")

def verificar_licenca(dados):
    dados_verificar = dados.copy()
    assinatura_recebida = dados_verificar.pop('assinatura', None)
    
    if not assinatura_recebida:
        return {'valida': False, 'erro': 'Assinatura não encontrada!'}
    
    dados_ordenados = dict(sorted(dados_verificar.items()))
    dados_json = json.dumps(dados_ordenados, ensure_ascii=False, separators=(',', ':'))
    assinatura_calculada = hmac.new(
        LICENSE_KEY.encode('utf-8'),
        dados_json.encode('utf-8'),
        hashlib.sha256
    ).hexdigest()
    
    if assinatura_recebida != assinatura_calculada:
        return {'valida': False, 'erro': f'Assinatura inválida!'}
    
    data_expiracao = datetime.datetime.strptime(dados['data_expiracao'], '%Y-%m-%d')
    if datetime.datetime.now() > data_expiracao:
        return {'valida': False, 'erro': 'Licença expirada!'}
    
    if dados.get('status') != 'ativo':
        return {'valida': False, 'erro': f"Licença {dados['status']}!"}
    
    return {'valida': True, 'dados': dados}

def main():
    parser = argparse.ArgumentParser(description='SoftGest Web - Gerador de Licenças')
    parser.add_argument('-c', '--cliente', required=True, help='Nome do cliente')
    parser.add_argument('-a', '--anos', type=int, default=1, help='Número de anos (padrão: 1)')
    parser.add_argument('-d', '--dominios', nargs='+', default=['localhost', '127.0.0.1'], help='Domínios autorizados')
    parser.add_argument('-f', '--arquivo', default=LICENSE_FILE, help='Arquivo de saída')
    parser.add_argument('-v', '--verbose', action='store_true', help='Mostrar detalhes')
    
    args = parser.parse_args()
    
    print("=" * 60)
    print("  SOFTGEST WEB - GERADOR DE LICENÇAS")
    print("=" * 60)
    print()
    print(f"📋 Cliente: {args.cliente}")
    print(f"📅 Anos: {args.anos}")
    print(f"🌐 Domínios: {', '.join(args.dominios)}")
    print()
    
    print("🔄 Gerando licença...")
    dados = gerar_licenca(args.cliente, args.anos, args.dominios)
    
    verificacao = verificar_licenca(dados)
    if verificacao['valida']:
        print("✅ Licença gerada com sucesso!")
    else:
        print(f"❌ Erro: {verificacao['erro']}")
        sys.exit(1)
    
    salvar_licenca(dados, args.arquivo)
    
    if args.verbose:
        print()
        print(json.dumps(dados, indent=2, ensure_ascii=False))
    
    print()
    print("=" * 60)
    print("  INFORMAÇÕES DA LICENÇA")
    print("=" * 60)
    print()
    print(f"🆔 ID: {dados['licenca_id']}")
    print(f"👤 Cliente: {dados['cliente']}")
    print(f"📅 Emissão: {dados['data_emissao']}")
    print(f"⏰ Expiração: {dados['data_expiracao']}")
    print(f"📆 Anos: {dados['anos']}")
    print(f"🌐 Domínios: {', '.join(dados['dominios'])}")
    print(f"📊 Status: {dados['status']}")
    print(f"🔑 Assinatura: {dados['assinatura'][:16]}...")
    print()
    print("=" * 60)
    print("  COMO INSTALAR")
    print("=" * 60)
    print()
    print(f"1. Copie {args.arquivo} para:")
    print("   C:\\xampp\\htdocs\\softgest_web\\license\\")
    print()
    print("2. Acesse o sistema")

if __name__ == "__main__":
    main()