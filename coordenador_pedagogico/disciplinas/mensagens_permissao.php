<?php
// ============================================
// mensagens_permissao.php - Sistema de Mensagens de Permissão
// Módulo: Escola > Alunos
// ============================================

// ============================================
// FUNÇÃO PARA EXIBIR MENSAGEM DE PERMISSÃO NEGADA
// ============================================
function exibirMensagemPermissaoNegada($acao, $voltar_para = 'index.php') {
    $icones = [
        'visualizar' => '👁️',
        'criar' => '➕',
        'editar' => '✏️',
        'excluir' => '🗑️',
        'imprimir' => '🖨️',
        'exportar' => '📤',
        'configurar' => '⚙️'
    ];
    
    $acoes_pt = [
        'visualizar' => 'visualizar',
        'criar' => 'criar',
        'editar' => 'editar',
        'excluir' => 'excluir',
        'imprimir' => 'imprimir',
        'exportar' => 'exportar',
        'configurar' => 'configurar'
    ];
    
    $icon = $icones[$acao] ?? '🚫';
    $acao_pt = $acoes_pt[$acao] ?? $acao;
    
    echo '
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acesso Negado - SoftGest</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: "Segoe UI", Arial, sans-serif;
                background: #f1f5f9;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
                padding: 20px;
            }
            .container {
                max-width: 500px;
                width: 100%;
                background: #ffffff;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.12);
                padding: 40px 35px;
                text-align: center;
                border-top: 6px solid #e74c3c;
            }
            .icon {
                font-size: 72px;
                display: block;
                margin-bottom: 15px;
            }
            h1 {
                font-size: 24px;
                font-weight: 700;
                color: #1a2332;
                margin: 0 0 8px 0;
            }
            .subtitle {
                font-size: 16px;
                color: #64748b;
                margin: 0 0 5px 0;
            }
            .mensagem {
                font-size: 14px;
                color: #475569;
                margin: 15px 0 25px 0;
                padding: 15px 20px;
                background: #fef2f2;
                border-radius: 10px;
                border-left: 4px solid #e74c3c;
                text-align: left;
            }
            .mensagem strong {
                color: #dc2626;
            }
            .mensagem .acao {
                display: inline-block;
                background: #e74c3c;
                color: white;
                padding: 2px 12px;
                border-radius: 4px;
                font-weight: 600;
                font-size: 12px;
            }
            .botoes {
                display: flex;
                gap: 10px;
                justify-content: center;
                flex-wrap: wrap;
                margin-top: 5px;
            }
            .btn {
                padding: 10px 28px;
                border-radius: 10px;
                text-decoration: none;
                font-size: 14px;
                font-weight: 600;
                transition: all 0.3s;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                border: none;
                cursor: pointer;
            }
            .btn-primary {
                background: #c9a84c;
                color: #1a2332;
            }
            .btn-primary:hover {
                background: #b8973a;
                transform: translateY(-2px);
                box-shadow: 0 4px 15px rgba(201,168,76,0.3);
            }
            .btn-secondary {
                background: #f1f5f9;
                color: #4a5568;
            }
            .btn-secondary:hover {
                background: #e2e8f0;
            }
            .btn-danger {
                background: #e74c3c;
                color: #fff;
            }
            .btn-danger:hover {
                background: #c0392b;
                transform: translateY(-2px);
                box-shadow: 0 4px 15px rgba(231,76,60,0.3);
            }
            .footer {
                margin-top: 20px;
                padding-top: 15px;
                border-top: 1px solid #eef2f7;
                font-size: 12px;
                color: #94a3b8;
            }
            .footer strong {
                color: #1a2332;
            }
            .codigo-erro {
                font-size: 11px;
                color: #cbd5e1;
                margin-top: 8px;
                font-family: monospace;
            }
            @media (max-width: 600px) {
                .container { padding: 25px 20px; }
                .icon { font-size: 56px; }
                h1 { font-size: 20px; }
                .botoes { flex-direction: column; align-items: stretch; }
                .btn { justify-content: center; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <span class="icon">' . $icon . '</span>
            <h1>🚫 Acesso Negado</h1>
            <p class="subtitle">Você não tem permissão para realizar esta ação</p>
            
            <div class="mensagem">
                <strong>⚠️ Atenção:</strong><br>
                Você não possui permissão para <strong>' . $acao_pt . '</strong> neste módulo.<br>
                <span style="font-size:12px;color:#94a3b8;display:block;margin-top:5px;">
                    Módulo: <strong>Escola > Alunos</strong> | Ação: <span class="acao">' . strtoupper($acao_pt) . '</span>
                </span>
            </div>
            
            <div class="botoes">
                <a href="' . $voltar_para . '" class="btn btn-primary">← Voltar</a>
                <a href="../../../index.php" class="btn btn-secondary">🏠 Dashboard</a>
                <a href="../../../logout.php" class="btn btn-danger">🚪 Sair</a>
            </div>
            
            <div class="footer">
                <strong>SoftGest Web</strong> - Sistema de Gestão Escolar<br>
                <span style="font-size:11px;">Entre em contato com o administrador se acredita que isso é um erro.</span>
                <div class="codigo-erro">Erro: PERMISSION_DENIED_' . strtoupper($acao) . '</div>
            </div>
        </div>
    </body>
    </html>
    ';
    exit;
}

// ============================================
// FUNÇÃO PARA EXIBIR MENSAGEM DE SUCESSO
// ============================================
function exibirMensagemSucesso($mensagem, $titulo = '✅ Sucesso!', $voltar_para = 'index.php') {
    echo '
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Sucesso - SoftGest</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: "Segoe UI", Arial, sans-serif;
                background: #f1f5f9;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
                padding: 20px;
            }
            .container {
                max-width: 500px;
                width: 100%;
                background: #ffffff;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.12);
                padding: 40px 35px;
                text-align: center;
                border-top: 6px solid #2ecc71;
            }
            .icon {
                font-size: 72px;
                display: block;
                margin-bottom: 15px;
            }
            h1 {
                font-size: 24px;
                font-weight: 700;
                color: #1a2332;
                margin: 0 0 8px 0;
            }
            .mensagem {
                font-size: 14px;
                color: #475569;
                margin: 15px 0 25px 0;
                padding: 15px 20px;
                background: #f0fdf4;
                border-radius: 10px;
                border-left: 4px solid #2ecc71;
                text-align: left;
            }
            .botoes {
                display: flex;
                gap: 10px;
                justify-content: center;
                flex-wrap: wrap;
                margin-top: 5px;
            }
            .btn {
                padding: 10px 28px;
                border-radius: 10px;
                text-decoration: none;
                font-size: 14px;
                font-weight: 600;
                transition: all 0.3s;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                border: none;
                cursor: pointer;
            }
            .btn-primary {
                background: #c9a84c;
                color: #1a2332;
            }
            .btn-primary:hover {
                background: #b8973a;
                transform: translateY(-2px);
                box-shadow: 0 4px 15px rgba(201,168,76,0.3);
            }
            .btn-secondary {
                background: #f1f5f9;
                color: #4a5568;
            }
            .btn-secondary:hover {
                background: #e2e8f0;
            }
            .footer {
                margin-top: 20px;
                padding-top: 15px;
                border-top: 1px solid #eef2f7;
                font-size: 12px;
                color: #94a3b8;
            }
            .footer strong {
                color: #1a2332;
            }
            @media (max-width: 600px) {
                .container { padding: 25px 20px; }
                .icon { font-size: 56px; }
                h1 { font-size: 20px; }
                .botoes { flex-direction: column; align-items: stretch; }
                .btn { justify-content: center; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <span class="icon">✅</span>
            <h1>' . $titulo . '</h1>
            <div class="mensagem">' . $mensagem . '</div>
            <div class="botoes">
                <a href="' . $voltar_para . '" class="btn btn-primary">← Voltar</a>
                <a href="../../../index.php" class="btn btn-secondary">🏠 Dashboard</a>
            </div>
            <div class="footer">
                <strong>SoftGest Web</strong> - Sistema de Gestão Escolar
            </div>
        </div>
    </body>
    </html>
    ';
    exit;
}

// ============================================
// FUNÇÃO PARA EXIBIR MENSAGEM DE ERRO
// ============================================
function exibirMensagemErro($mensagem, $titulo = '❌ Erro!', $voltar_para = 'index.php') {
    echo '
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Erro - SoftGest</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: "Segoe UI", Arial, sans-serif;
                background: #f1f5f9;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
                padding: 20px;
            }
            .container {
                max-width: 500px;
                width: 100%;
                background: #ffffff;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.12);
                padding: 40px 35px;
                text-align: center;
                border-top: 6px solid #e74c3c;
            }
            .icon {
                font-size: 72px;
                display: block;
                margin-bottom: 15px;
            }
            h1 {
                font-size: 24px;
                font-weight: 700;
                color: #1a2332;
                margin: 0 0 8px 0;
            }
            .mensagem {
                font-size: 14px;
                color: #475569;
                margin: 15px 0 25px 0;
                padding: 15px 20px;
                background: #fef2f2;
                border-radius: 10px;
                border-left: 4px solid #e74c3c;
                text-align: left;
            }
            .botoes {
                display: flex;
                gap: 10px;
                justify-content: center;
                flex-wrap: wrap;
                margin-top: 5px;
            }
            .btn {
                padding: 10px 28px;
                border-radius: 10px;
                text-decoration: none;
                font-size: 14px;
                font-weight: 600;
                transition: all 0.3s;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                border: none;
                cursor: pointer;
            }
            .btn-primary {
                background: #c9a84c;
                color: #1a2332;
            }
            .btn-primary:hover {
                background: #b8973a;
                transform: translateY(-2px);
                box-shadow: 0 4px 15px rgba(201,168,76,0.3);
            }
            .btn-secondary {
                background: #f1f5f9;
                color: #4a5568;
            }
            .btn-secondary:hover {
                background: #e2e8f0;
            }
            .footer {
                margin-top: 20px;
                padding-top: 15px;
                border-top: 1px solid #eef2f7;
                font-size: 12px;
                color: #94a3b8;
            }
            .footer strong {
                color: #1a2332;
            }
            @media (max-width: 600px) {
                .container { padding: 25px 20px; }
                .icon { font-size: 56px; }
                h1 { font-size: 20px; }
                .botoes { flex-direction: column; align-items: stretch; }
                .btn { justify-content: center; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <span class="icon">❌</span>
            <h1>' . $titulo . '</h1>
            <div class="mensagem">' . $mensagem . '</div>
            <div class="botoes">
                <a href="' . $voltar_para . '" class="btn btn-primary">← Voltar</a>
                <a href="../../../index.php" class="btn btn-secondary">🏠 Dashboard</a>
            </div>
            <div class="footer">
                <strong>SoftGest Web</strong> - Sistema de Gestão Escolar
            </div>
        </div>
    </body>
    </html>
    ';
    exit;
}

// ============================================
// FUNÇÃO PARA EXIBIR MENSAGEM DE AVISO
// ============================================
function exibirMensagemAviso($mensagem, $titulo = '⚠️ Atenção!', $voltar_para = 'index.php') {
    echo '
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Aviso - SoftGest</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: "Segoe UI", Arial, sans-serif;
                background: #f1f5f9;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
                padding: 20px;
            }
            .container {
                max-width: 500px;
                width: 100%;
                background: #ffffff;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.12);
                padding: 40px 35px;
                text-align: center;
                border-top: 6px solid #f39c12;
            }
            .icon {
                font-size: 72px;
                display: block;
                margin-bottom: 15px;
            }
            h1 {
                font-size: 24px;
                font-weight: 700;
                color: #1a2332;
                margin: 0 0 8px 0;
            }
            .mensagem {
                font-size: 14px;
                color: #475569;
                margin: 15px 0 25px 0;
                padding: 15px 20px;
                background: #fffbeb;
                border-radius: 10px;
                border-left: 4px solid #f39c12;
                text-align: left;
            }
            .botoes {
                display: flex;
                gap: 10px;
                justify-content: center;
                flex-wrap: wrap;
                margin-top: 5px;
            }
            .btn {
                padding: 10px 28px;
                border-radius: 10px;
                text-decoration: none;
                font-size: 14px;
                font-weight: 600;
                transition: all 0.3s;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                border: none;
                cursor: pointer;
            }
            .btn-primary {
                background: #c9a84c;
                color: #1a2332;
            }
            .btn-primary:hover {
                background: #b8973a;
                transform: translateY(-2px);
                box-shadow: 0 4px 15px rgba(201,168,76,0.3);
            }
            .btn-secondary {
                background: #f1f5f9;
                color: #4a5568;
            }
            .btn-secondary:hover {
                background: #e2e8f0;
            }
            .footer {
                margin-top: 20px;
                padding-top: 15px;
                border-top: 1px solid #eef2f7;
                font-size: 12px;
                color: #94a3b8;
            }
            .footer strong {
                color: #1a2332;
            }
            @media (max-width: 600px) {
                .container { padding: 25px 20px; }
                .icon { font-size: 56px; }
                h1 { font-size: 20px; }
                .botoes { flex-direction: column; align-items: stretch; }
                .btn { justify-content: center; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <span class="icon">⚠️</span>
            <h1>' . $titulo . '</h1>
            <div class="mensagem">' . $mensagem . '</div>
            <div class="botoes">
                <a href="' . $voltar_para . '" class="btn btn-primary">← Voltar</a>
                <a href="../../../index.php" class="btn btn-secondary">🏠 Dashboard</a>
            </div>
            <div class="footer">
                <strong>SoftGest Web</strong> - Sistema de Gestão Escolar
            </div>
        </div>
    </body>
    </html>
    ';
    exit;
}
?>