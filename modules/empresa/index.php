<?php
require_once '../../config/database.php';

// Verificar se o usuário está logado
if (!isLoggedIn()) {
    header("Location: " . SITE_URL . "login.php");
    exit;
}

// Buscar dados da empresa
$empresa = getEmpresa();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dados da Empresa - SoftGest Web</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ===== VARIÁVEIS ===== */
        :root {
            --primary: #c9a84c;
            --primary-dark: #b8973a;
            --primary-light: #f5edd6;
            --secondary: #1a2332;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 20px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.12);
            --radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* ===== LAYOUT PRINCIPAL ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--gray-50);
            color: var(--gray-800);
        }

        .main-wrapper {
            display: flex;
            min-height: 100vh;
        }

        .content-area {
            flex: 1;
            margin-left: 250px;
            padding: 30px;
            background: var(--gray-50);
            min-height: 100vh;
        }

        /* ===== CONTAINER ===== */
        .container-modern {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* ===== CARD PRINCIPAL ===== */
        .company-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid var(--gray-200);
            transition: var(--transition);
        }

        .company-card:hover {
            box-shadow: var(--shadow-lg);
        }

        /* ===== HEADER ===== */
        .company-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--gray-100);
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .company-logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(201, 168, 76, 0.3);
            flex-shrink: 0;
        }

        .header-title h2 {
            color: var(--gray-900);
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            letter-spacing: -0.5px;
        }

        .header-title p {
            color: var(--gray-500);
            font-size: 14px;
            margin: 4px 0 0;
        }

        /* ===== BOTÃO EDITAR ===== */
        .btn-edit {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 28px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--secondary);
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(201, 168, 76, 0.3);
            white-space: nowrap;
        }

        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(201, 168, 76, 0.4);
            color: var(--secondary);
            text-decoration: none;
        }

        .btn-edit i {
            font-size: 16px;
        }

        /* ===== GRID DE CAMPOS ===== */
        .company-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
        }

        .company-field {
            padding: 16px 20px;
            background: var(--gray-50);
            border-radius: 10px;
            border-left: 4px solid var(--primary);
            transition: var(--transition);
        }

        .company-field:hover {
            background: var(--primary-light);
            transform: translateX(4px);
        }

        .company-field .label {
            font-size: 11px;
            text-transform: uppercase;
            color: var(--gray-500);
            font-weight: 600;
            letter-spacing: 0.8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .company-field .label i {
            font-size: 13px;
            color: var(--primary);
        }

        .company-field .value {
            font-size: 15px;
            color: var(--gray-800);
            font-weight: 500;
            margin-top: 6px;
            word-break: break-word;
            line-height: 1.5;
        }

        .company-field .value.empty {
            color: var(--gray-400);
            font-style: italic;
            font-weight: 400;
        }

        /* ===== ALERTA ===== */
        .alert-modern {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid #c6f6d5;
            background: #f0fff4;
            color: #22543d;
        }

        .alert-modern i {
            font-size: 20px;
            color: #38a169;
        }

        .alert-modern .alert-close {
            margin-left: auto;
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: #22543d;
            opacity: 0.7;
            transition: var(--transition);
        }

        .alert-modern .alert-close:hover {
            opacity: 1;
        }

        /* ===== PRÉ-VISUALIZAÇÃO ===== */
        .preview-section {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid var(--gray-100);
        }

        .preview-section h3 {
            color: var(--gray-900);
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .preview-section .preview-subtitle {
            color: var(--gray-500);
            margin-bottom: 20px;
            font-size: 14px;
        }

        .preview-box {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 10px;
            padding: 25px;
            border: 2px dashed var(--gray-300);
            transition: var(--transition);
        }

        .preview-box:hover {
            border-color: var(--primary);
            background: var(--primary-light);
        }

        .preview-box .preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .preview-box .company-name {
            font-size: 20px;
            font-weight: 700;
            color: var(--gray-900);
        }

        .preview-box .company-info {
            font-size: 14px;
            color: var(--gray-600);
            margin-top: 4px;
        }

        .preview-box .company-doc {
            font-size: 13px;
            color: var(--gray-500);
            margin-top: 4px;
        }

        .preview-box .preview-doc-type {
            text-align: right;
        }

        .preview-box .preview-doc-type .doc-label {
            font-size: 12px;
            color: var(--gray-400);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .preview-box .preview-doc-type .doc-number {
            font-size: 22px;
            font-weight: 700;
            color: var(--gray-800);
        }

        /* ===== RESPONSIVO ===== */
        @media (max-width: 1200px) {
            .content-area {
                padding: 20px;
            }
        }

        @media (max-width: 992px) {
            .content-area {
                margin-left: 0;
                padding: 15px;
            }

            .company-grid {
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .company-header {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }

            .header-left {
                flex-direction: column;
                text-align: center;
            }

            .btn-edit {
                justify-content: center;
                width: 100%;
            }

            .company-card {
                padding: 20px;
            }

            .company-grid {
                grid-template-columns: 1fr;
            }

            .company-field {
                border-left: none;
                border-top: 4px solid var(--primary);
                padding: 14px 16px;
            }

            .preview-box .preview-header {
                flex-direction: column;
                text-align: center;
            }

            .preview-box .preview-doc-type {
                text-align: center;
                margin-top: 10px;
            }

            .company-logo {
                width: 50px;
                height: 50px;
                font-size: 20px;
            }

            .header-title h2 {
                font-size: 20px;
            }
        }

        @media (max-width: 480px) {
            .content-area {
                padding: 10px;
            }

            .company-card {
                padding: 15px;
                border-radius: 10px;
            }

            .company-field {
                padding: 12px 14px;
            }

            .company-field .value {
                font-size: 14px;
            }

            .preview-box {
                padding: 18px;
            }

            .preview-box .company-name {
                font-size: 17px;
            }

            .preview-box .preview-doc-type .doc-number {
                font-size: 18px;
            }
        }

        /* ===== ANIMAÇÕES ===== */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .company-card {
            animation: fadeInUp 0.5s ease-out;
        }

        /* ===== SCROLLBAR ===== */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--gray-100);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }
    </style>
</head>
<body>
    <!-- Wrapper com menu lateral -->
    <div class="main-wrapper">
        <?php include '../../includes/header.php'; ?>
        
        <!-- Conteúdo Principal -->
        <div class="content-area">
            <div class="container-modern">
                <!-- Card Principal -->
                <div class="company-card">
                    <!-- Header -->
                    <div class="company-header">
                        <div class="header-left">
                            <div class="company-logo">SG</div>
                            <div class="header-title">
                                <h2><i class="fas fa-building" style="color: var(--primary);"></i> Dados da Empresa</h2>
                                <p><i class="fas fa-info-circle"></i> Configure as informações da sua empresa</p>
                            </div>
                        </div>
                        <a href="editar.php" class="btn-edit">
                            <i class="fas fa-edit"></i> Editar Dados
                        </a>
                    </div>
                    
                    <!-- Alerta de Sucesso -->
                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert-modern">
                            <i class="fas fa-check-circle"></i>
                            <span>✅ Dados atualizados com sucesso!</span>
                            <button class="alert-close" onclick="this.parentElement.remove()">×</button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Grid de Campos -->
                    <div class="company-grid">
                        <div class="company-field">
                            <div class="label"><i class="fas fa-building"></i> Razão Social</div>
                            <div class="value <?= empty($empresa['razao_social']) ? 'empty' : '' ?>">
                                <?= htmlspecialchars($empresa['razao_social'] ?? 'Não informado') ?>
                            </div>
                        </div>
                        
                        <div class="company-field">
                            <div class="label"><i class="fas fa-store"></i> Nome Fantasia</div>
                            <div class="value <?= empty($empresa['nome_fantasia']) ? 'empty' : '' ?>">
                                <?= htmlspecialchars($empresa['nome_fantasia'] ?? 'Não informado') ?>
                            </div>
                        </div>
                        
                        <div class="company-field">
                            <div class="label"><i class="fas fa-id-card"></i> CNPJ</div>
                            <div class="value <?= empty($empresa['cnpj']) ? 'empty' : '' ?>">
                                <?= htmlspecialchars($empresa['cnpj'] ?? 'Não informado') ?>
                            </div>
                        </div>
                        
                        <div class="company-field">
                            <div class="label"><i class="fas fa-file-alt"></i> Inscrição Estadual</div>
                            <div class="value <?= empty($empresa['inscricao_estadual']) ? 'empty' : '' ?>">
                                <?= htmlspecialchars($empresa['inscricao_estadual'] ?? 'Não informado') ?>
                            </div>
                        </div>
                        
                        <div class="company-field">
                            <div class="label"><i class="fas fa-file-invoice"></i> Inscrição Municipal</div>
                            <div class="value <?= empty($empresa['inscricao_municipal']) ? 'empty' : '' ?>">
                                <?= htmlspecialchars($empresa['inscricao_municipal'] ?? 'Não informado') ?>
                            </div>
                        </div>
                        
                        <div class="company-field" style="grid-column: span 2;">
                            <div class="label"><i class="fas fa-map-marker-alt"></i> Endereço</div>
                            <div class="value <?= empty($empresa['endereco']) ? 'empty' : '' ?>">
                                <?php if (!empty($empresa['endereco'])): ?>
                                    <?= htmlspecialchars($empresa['endereco']) ?>
                                    <?php if (!empty($empresa['numero'])): ?>, <?= htmlspecialchars($empresa['numero']) ?><?php endif; ?>
                                    <?php if (!empty($empresa['complemento'])): ?> - <?= htmlspecialchars($empresa['complemento']) ?><?php endif; ?>
                                    <?php if (!empty($empresa['bairro'])): ?><br><?= htmlspecialchars($empresa['bairro']) ?><?php endif; ?>
                                    <?php if (!empty($empresa['cidade']) && !empty($empresa['estado'])): ?>
                                        <br><?= htmlspecialchars($empresa['cidade']) ?> - <?= htmlspecialchars($empresa['estado']) ?>
                                    <?php endif; ?>
                                    <?php if (!empty($empresa['cep'])): ?><br>CEP: <?= htmlspecialchars($empresa['cep']) ?><?php endif; ?>
                                <?php else: ?>
                                    Não informado
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="company-field">
                            <div class="label"><i class="fas fa-phone"></i> Telefone</div>
                            <div class="value <?= empty($empresa['telefone']) ? 'empty' : '' ?>">
                                <?= htmlspecialchars($empresa['telefone'] ?? 'Não informado') ?>
                            </div>
                        </div>
                        
                        <div class="company-field">
                            <div class="label"><i class="fas fa-mobile-alt"></i> Celular</div>
                            <div class="value <?= empty($empresa['celular']) ? 'empty' : '' ?>">
                                <?= htmlspecialchars($empresa['celular'] ?? 'Não informado') ?>
                            </div>
                        </div>
                        
                        <div class="company-field">
                            <div class="label"><i class="fas fa-envelope"></i> E-mail</div>
                            <div class="value <?= empty($empresa['email']) ? 'empty' : '' ?>">
                                <?= htmlspecialchars($empresa['email'] ?? 'Não informado') ?>
                            </div>
                        </div>
                        
                        <div class="company-field">
                            <div class="label"><i class="fas fa-globe"></i> Site</div>
                            <div class="value <?= empty($empresa['site']) ? 'empty' : '' ?>">
                                <?= htmlspecialchars($empresa['site'] ?? 'Não informado') ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pré-visualização -->
                <div class="company-card preview-section">
                    <h3><i class="fas fa-eye" style="color: var(--primary);"></i> Pré-visualização nos Documentos</h3>
                    <p class="preview-subtitle">
                        <i class="fas fa-info-circle"></i> 
                        Como os dados da empresa aparecerão nas faturas e documentos
                    </p>
                    
                    <div class="preview-box">
                        <div class="preview-header">
                            <div>
                                <div class="company-name">
                                    <?= htmlspecialchars($empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? 'SoftGest Web') ?>
                                </div>
                                <div class="company-info">
                                    <?php if (!empty($empresa['cnpj'])): ?>
                                        CNPJ: <?= htmlspecialchars($empresa['cnpj']) ?>
                                    <?php endif; ?>
                                    <?php if (!empty($empresa['endereco'])): ?>
                                        | <?= htmlspecialchars($empresa['endereco']) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="company-doc">
                                    <?php if (!empty($empresa['telefone'])): ?>
                                        <i class="fas fa-phone"></i> <?= htmlspecialchars($empresa['telefone']) ?>
                                    <?php endif; ?>
                                    <?php if (!empty($empresa['email'])): ?>
                                        | <i class="fas fa-envelope"></i> <?= htmlspecialchars($empresa['email']) ?>
                                    <?php endif; ?>
                                    <?php if (!empty($empresa['site'])): ?>
                                        | <i class="fas fa-globe"></i> <?= htmlspecialchars($empresa['site']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="preview-doc-type">
                                <div class="doc-label"><i class="fas fa-file-invoice"></i> FATURA PROFORMA</div>
                                <div class="doc-number">PF-2026-0001</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
    
    <script>
        // Fechar alerta automaticamente após 5 segundos
        document.addEventListener('DOMContentLoaded', function() {
            const alert = document.querySelector('.alert-modern');
            if (alert) {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            }
        });
    </script>
</body>
</html>