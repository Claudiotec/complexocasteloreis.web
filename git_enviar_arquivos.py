# -*- coding: utf-8 -*-
"""
Script interativo para enviar pastas e arquivos selecionados para o GitHub.
Com verificacoes de erro e correcoes para problemas comuns.
"""

import os
import subprocess
import sys

# ===== CONFIGURACOES =====
REPO_URL = "https://github.com/Claudiotec/complexocasteloreis.web.git"
BRANCH = "main"
# =========================


# ---------------------------------------------------------------
# UTILITARIOS
# ---------------------------------------------------------------
def rodar(cmd, capturar=False, mostrar_erro=True):
    """Executa um comando no terminal."""
    print(f"  > {cmd}")
    try:
        if capturar:
            resultado = subprocess.run(
                cmd, shell=True, capture_output=True, text=True, encoding="utf-8"
            )
            if mostrar_erro and resultado.returncode != 0:
                if resultado.stdout.strip():
                    print(f"    [stdout] {resultado.stdout.strip()}")
                if resultado.stderr.strip():
                    print(f"    [stderr] {resultado.stderr.strip()}")
            return (
                resultado.stdout.strip(),
                resultado.stderr.strip(),
                resultado.returncode,
            )
        else:
            return subprocess.call(cmd, shell=True)
    except Exception as e:
        print(f"  [ERRO] {e}")
        return 1


def pausar(msg="Pressione Enter para sair..."):
    input(f"\n{msg}")


def erro_fatal(msg):
    print("\n" + "=" * 60)
    print(f"  [ERRO] {msg}")
    print("=" * 60)
    pausar()
    sys.exit(1)


# ---------------------------------------------------------------
# LISTAGEM E SELECAO
# ---------------------------------------------------------------
def listar_itens(pasta="."):
    """Lista pastas e arquivos da pasta atual (ignora .git)."""
    itens = []
    for nome in sorted(os.listdir(pasta)):
        if nome == ".git":
            continue
        caminho = os.path.join(pasta, nome)
        tipo = "PASTA" if os.path.isdir(caminho) else "ARQUIVO"
        itens.append((nome, tipo))
    return itens


def selecionar_itens():
    """Mostra os itens e pede ao usuario quais enviar."""
    itens = listar_itens(".")

    if not itens:
        erro_fatal("Nenhum arquivo ou pasta encontrado nesta pasta.")

    print("\n" + "=" * 60)
    print("  ITENS DISPONIVEIS NA PASTA ATUAL")
    print("=" * 60)
    for i, (nome, tipo) in enumerate(itens, start=1):
        print(f"  [{i:02d}] ({tipo}) {nome}")
    print("=" * 60)

    print("\nDigite os numeros separados por virgula (ex: 1,3,5)")
    print("Ou digite 'T' para enviar TUDO")
    print("Ou 'S' para sair")

    escolha = input("\nSua escolha: ").strip().upper()

    if escolha == "S":
        print("Saindo...")
        sys.exit(0)

    if escolha == "T":
        return [nome for nome, _ in itens]

    selecionados = []
    for parte in escolha.split(","):
        parte = parte.strip()
        if parte.isdigit():
            idx = int(parte) - 1
            if 0 <= idx < len(itens):
                selecionados.append(itens[idx][0])
            else:
                print(f"  [AVISO] Numero invalido ignorado: {parte}")

    if not selecionados:
        erro_fatal("Nenhum item valido selecionado.")

    return selecionados


def confirmar(selecionados):
    """Pede confirmacao final."""
    print("\n" + "=" * 60)
    print("  ITENS QUE SERAO ENVIADOS:")
    print("=" * 60)
    for item in selecionados:
        print(f"  - {item}")
    print("=" * 60)

    resp = input("\nConfirma o envio? (s/N): ").strip().lower()
    return resp == "s"


# ---------------------------------------------------------------
# .GITIGNORE
# ---------------------------------------------------------------
def criar_gitignore():
    """Cria .gitignore se nao existir."""
    if not os.path.exists(".gitignore"):
        print("\n  Criando arquivo .gitignore...")
        conteudo = """# Dependencias
node_modules/
vendor/
__pycache__/
*.pyc

# Ambiente
.env
.env.local
.env.production

# Logs
*.log
npm-debug.log*

# Sistema
.DS_Store
Thumbs.db

# Build
dist/
build/
.cache/

# IDE
.vscode/
.idea/
"""
        with open(".gitignore", "w", encoding="utf-8") as f:
            f.write(conteudo)
    else:
        print("\n  .gitignore ja existe.")


# ---------------------------------------------------------------
# VERIFICACOES GIT
# ---------------------------------------------------------------
def verificar_git_instalado():
    """Verifica se o Git esta instalado."""
    print("\n[1/9] Verificando Git...")
    _, _, codigo = rodar("git --version", capturar=True, mostrar_erro=False)
    if codigo != 0:
        erro_fatal(
            "Git nao esta instalado ou nao esta no PATH.\n"
            "  Baixe em: https://git-scm.com/downloads"
        )
    print("  Git encontrado!")


def verificar_config_usuario():
    """Verifica se user.name e user.email estao configurados."""
    print("\n[2/9] Verificando configuracao do usuario Git...")

    nome, _, cod1 = rodar(
        'git config --global user.name', capturar=True, mostrar_erro=False
    )
    email, _, cod2 = rodar(
        'git config --global user.email', capturar=True, mostrar_erro=False
    )

    if cod1 != 0 or not nome:
        print("  [AVISO] user.name nao configurado.")
        novo_nome = input("  Digite seu nome para o Git: ").strip()
        if novo_nome:
            rodar(f'git config --global user.name "{novo_nome}"')
        else:
            erro_fatal("user.name e obrigatorio para fazer commits.")

    if cod2 != 0 or not email:
        print("  [AVISO] user.email nao configurado.")
        novo_email = input("  Digite seu email para o Git: ").strip()
        if novo_email:
            rodar(f'git config --global user.email "{novo_email}"')
        else:
            erro_fatal("user.email e obrigatorio para fazer commits.")

    print("  Configuracao do usuario OK!")


# ---------------------------------------------------------------
# MAIN
# ---------------------------------------------------------------
def main():
    print("=" * 60)
    print("  ENVIAR SELECAO PARA O GITHUB")
    print("=" * 60)
    print(f"  Repositorio: {REPO_URL}")
    print(f"  Branch:      {BRANCH}")
    print("=" * 60)

    # 1) Verifica Git
    verificar_git_instalado()

    # 2) Verifica config do usuario
    verificar_config_usuario()

    # 3) Selecao de itens
    print("\n[3/9] Selecionando itens...")
    selecionados = selecionar_itens()

    if not confirmar(selecionados):
        print("\nOperacao cancelada.")
        pausar()
        sys.exit(0)

    # 4) Inicializa repositorio
    print("\n[4/9] Inicializando repositorio Git...")
    if not os.path.exists(".git"):
        rodar("git init")
    else:
        print("  Repositorio Git ja existe.")

    # 5) .gitignore
    print("\n[5/9] Verificando .gitignore...")
    criar_gitignore()

    # 6) Configura remote
    print("\n[6/9] Configurando remote origin...")
    rodar("git remote remove origin", mostrar_erro=False)
    _, _, cod = rodar(f'git remote add origin "{REPO_URL}"', capturar=True)
    if cod != 0:
        erro_fatal("Nao foi possivel configurar o remote origin.")

    # 7) Adiciona itens selecionados
    print("\n[7/9] Adicionando itens selecionados ao stage...")

    # Limpa o stage (ignora erro se nao houver commits)
    rodar("git reset", mostrar_erro=False)

    for item in selecionados:
        rodar(f'git add "{item}"')

    # Adiciona .gitignore tambem
    rodar('git add ".gitignore"')

    # Verifica se ha algo no stage
    saida, _, _ = rodar("git status --porcelain", capturar=True, mostrar_erro=False)

    if not saida.strip():
        erro_fatal(
            "Nada foi adicionado ao stage.\n"
            "  Verifique se os itens selecionados existem e nao estao no .gitignore."
        )

    print("\n  --- Arquivos no stage ---")
    print(saida)

    # 8) Commit
    print("\n[8/9] Criando commit...")
    msg = input("  Mensagem do commit (Enter = padrao): ").strip()
    if not msg:
        msg = "Atualizacao do projeto"

    saida, erro, codigo = rodar(f'git commit -m "{msg}"', capturar=True)

    if codigo != 0:
        print("\n  [ERRO] O commit falhou!")
        if "nothing to commit" in saida.lower() or "nothing to commit" in erro.lower():
            print("  Motivo: nada para commitar (arquivos ja estavam commitados).")
        elif "author identity unknown" in (saida + erro).lower():
            print("  Motivo: usuario Git nao configurado.")
            print("  Rode: git config --global user.name \"Seu Nome\"")
            print("  Rode: git config --global user.email \"seu@email.com\"")
        erro_fatal("Commit nao foi criado. Corrija o problema acima e tente novamente.")

    # Confirma que o commit existe
    log, _, _ = rodar("git log --oneline -1", capturar=True, mostrar_erro=False)
    if not log:
        erro_fatal("Commit nao foi registrado. Algo deu errado.")

    print(f"\n  Commit criado: {log}")

    # 9) Branch + Push
    print("\n[9/9] Definindo branch e enviando para o GitHub...")
    rodar(f"git branch -M {BRANCH}")

    saida, erro, codigo = rodar(f"git push -u origin {BRANCH}", capturar=True)
    if saida:
        print(saida)
    if erro:
        print(erro)

    if codigo != 0:
        print("\n[AVISO] O push falhou. Tentando sincronizar com o remoto...")
        rodar(
            f"git pull origin {BRANCH} --allow-unrelated-histories --no-edit",
            capturar=True,
        )
        saida, erro, codigo = rodar(f"git push -u origin {BRANCH}", capturar=True)
        if saida:
            print(saida)
        if erro:
            print(erro)

        if codigo != 0:
            erro_fatal(
                "Push continua falhando.\n"
                "  Verifique se o repositorio remoto existe e se voce tem permissao.\n"
                "  Use um Personal Access Token no lugar da senha:\n"
                "  https://github.com/settings/tokens"
            )

    print("\n" + "=" * 60)
    print("  PROCESSO FINALIZADO COM SUCESSO!")
    print("=" * 60)
    print(f"  Repositorio: {REPO_URL}")
    pausar()


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("\n\nOperacao cancelada pelo usuario.")
        sys.exit(0)
