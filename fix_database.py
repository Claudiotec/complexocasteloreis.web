# fix_database.py
import sqlite3
import os

DB_PATH = os.path.join(os.path.dirname(__file__), 'licenses.db')

def fix_database():
    try:
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        
        # Verificar quais colunas existem
        cursor.execute("PRAGMA table_info(licencas)")
        colunas = [col[1] for col in cursor.fetchall()]
        
        print("Colunas existentes:", colunas)
        
        # Adicionar colunas faltantes
        colunas_para_adicionar = {
            'cliente_cnpj': 'TEXT',
            'observacoes': 'TEXT',
            'criado_por': 'TEXT',
            'atualizado_em': 'TEXT'
        }
        
        for coluna, tipo in colunas_para_adicionar.items():
            if coluna not in colunas:
                try:
                    cursor.execute(f"ALTER TABLE licencas ADD COLUMN {coluna} {tipo}")
                    print(f"✅ Coluna '{coluna}' adicionada com sucesso!")
                except sqlite3.OperationalError as e:
                    print(f"⚠️ Erro ao adicionar coluna {coluna}: {e}")
        
        conn.commit()
        conn.close()
        
        print("\n✅ Banco de dados atualizado com sucesso!")
        
        # Verificar novamente
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        cursor.execute("PRAGMA table_info(licencas)")
        colunas = [col[1] for col in cursor.fetchall()]
        print("\n📋 Colunas atualizadas:", colunas)
        conn.close()
        
    except Exception as e:
        print(f"❌ Erro: {e}")

if __name__ == "__main__":
    fix_database()
