<?php
// ============================================
// modules/escola/agt/config_agt.php - Configuração AGT
// ============================================

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'editar')) {
    header('Location: ' . SITE_URL);
    exit;
}

function limparString($texto) {
    if (empty($texto)) return '';
    if (!mb_check_encoding($texto, 'UTF-8')) {
        $texto = mb_convert_encoding($texto, 'UTF-8', 'auto');
    }
    $texto = preg_replace('/[[:cntrl:]]/', '', $texto);
    $texto = trim($texto);
    $texto = preg_replace('/\s+/', ' ', $texto);
    return $texto;
}

$erro = '';
$sucesso = '';
$config = [];

// Buscar configuração atual
try {
    $stmt = $pdo->prepare("SELECT * FROM config_agt WHERE id = 1");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $erro = 'Erro ao carregar configuração: ' . $e->getMessage();
}

// Buscar dados da empresa
$empresa = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM empresa WHERE id = 1");
    $stmt->execute();
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {}

// Salvar configuração
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_config'])) {
    try {
        $nif = limparString($_POST['nif'] ?? '');
        $nome_comercial = limparString($_POST['nome_comercial'] ?? '');
        $endereco = limparString($_POST['endereco'] ?? '');
        $telefone = limparString($_POST['telefone'] ?? '');
        $email = limparString($_POST['email'] ?? '');
        $site = limparString($_POST['site'] ?? '');
        $regime_iva = $_POST['regime_iva'] ?? 'normal';
        $taxa_iva_padrao = floatval($_POST['taxa_iva_padrao'] ?? 14);
        $serie_fatura = limparString($_POST['serie_fatura'] ?? 'A');
        $codigo_validacao = limparString($_POST['codigo_validacao'] ?? '');
        
        $stmt = $pdo->prepare("SELECT id FROM config_agt LIMIT 1");
        $stmt->execute();
        $exists = $stmt->fetch();
        
        if ($exists) {
            $sql = "UPDATE config_agt SET
                nif = :nif,
                nome_comercial = :nome_comercial,
                endereco = :endereco,
                telefone = :telefone,
                email = :email,
                site = :site,
                regime_iva = :regime_iva,
                taxa_iva_padrao = :taxa_iva_padrao,
                serie_fatura = :serie_fatura,
                codigo_validacao = :codigo_validacao,
                updated_at = NOW()
                WHERE id = :id
            ";
            $params = [
                ':id' => $exists['id'],
                ':nif' => $nif,
                ':nome_comercial' => $nome_comercial,
                ':endereco' => $endereco,
                ':telefone' => $telefone,
                ':email' => $email,
                ':site' => $site,
                ':regime_iva' => $regime_iva,
                ':taxa_iva_padrao' => $taxa_iva_padrao,
                ':serie_fatura' => $serie_fatura,
                ':codigo_validacao' => $codigo_validacao
            ];
        } else {
            $sql = "INSERT INTO config_agt SET
                empresa_id = :empresa_id,
                nif = :nif,
                nome_comercial = :nome_comercial,
                endereco = :endereco,
                telefone = :telefone,
                email = :email,
                site = :site,
                regime_iva = :regime_iva,
                taxa_iva_padrao = :taxa_iva_padrao,
                serie_fatura = :serie_fatura,
                codigo_validacao = :codigo_validacao
            ";
            $params = [
                ':empresa_id' => 1,
                ':nif' => $nif,
                ':nome_comercial' => $nome_comercial,
                ':endereco' => $endereco,
                ':telefone' => $telefone,
                ':email' => $email,
                ':site' => $site,
                ':regime_iva' => $regime_iva,
                ':taxa_iva_padrao' => $taxa_iva_padrao,
                ':serie_fatura' => $serie_fatura,
                ':codigo_validacao' => $codigo_validacao
            ];
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $sucesso = '✅ Configuração AGT salva com sucesso!';
        
        // Recarregar configuração
        $stmt = $pdo->prepare("SELECT * FROM config_agt WHERE id = 1");
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        
    } catch (Exception $e) {
        $erro = 'Erro ao salvar configuração: ' . $e->getMessage();
    }
}

include '../includes/header_escola.php';
?>

<style>
.page-header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;margin-bottom:25px}
.page-header h1{font-size:24px;font-weight:700;color:#1a2332;margin:0}
.page-header .subtitle{color:#94a3b8;font-size:14px;margin:2px 0 0}
.btn{padding:8px 20px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;transition:all .3s;display:inline-flex;align-items:center;gap:6px;border:none;cursor:pointer}
.btn-primary{background:#c9a84c;color:#1a2332}
.btn-primary:hover{background:#b8973d;transform:translateY(-2px)}
.btn-secondary{background:#f1f5f9;color:#4a5568}
.btn-secondary:hover{background:#e2e8f0}
.btn-success{background:#2ecc71;color:#fff}
.btn-success:hover{background:#27ae60}
.form-container{background:#fff;border-radius:12px;padding:30px;border:1px solid #eef2f7;max-width:900px}
.form-group{margin-bottom:18px}
.form-group label{display:block;font-weight:600;margin-bottom:5px;color:#1a2332;font-size:13px}
.form-group label .required{color:#e74c3c}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:10px 14px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;transition:border-color .3s;font-family:inherit}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:#c9a84c;box-shadow:0 0 0 3px rgba(201,168,76,0.1)}
.form-group textarea{min-height:60px;resize:vertical}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.form-row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px}
.alert{padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:14px}
.alert-success{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0}
.alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
.alert-info{background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe}
.form-actions{display:flex;gap:10px;margin-top:20px;flex-wrap:wrap}
.info-box{background:#f8fafc;padding:15px;border-radius:8px;border:1px solid #e2e8f0;margin-top:10px}
.info-box .label{font-size:12px;color:#94a3b8;font-weight:600;text-transform:uppercase}
.info-box .value{font-size:16px;font-weight:700;color:#1a2332}
.menu-agt{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:25px;padding:15px 20px;background:#fff;border-radius:12px;border:1px solid #eef2f7}
.menu-agt a{padding:8px 18px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:500;color:#4a5568;background:#f8fafc;border:1px solid #e2e8f0;display:inline-flex;align-items:center;gap:6px}
.menu-agt a:hover,.menu-agt a.active{background:#c9a84c;color:#1a2332;border-color:#c9a84c}
@media(max-width:768px){.form-row,.form-row-3{grid-template-columns:1fr;gap:0}.form-container{padding:20px}.form-actions{flex-direction:column}.form-actions .btn{justify-content:center}}
</style>

<div class="page-header">
    <div>
        <h1>⚙️ Configuração AGT</h1>
        <p class="subtitle">Configurar dados para emissão de faturas conforme requisitos da AGT</p>
    </div>
    <a href="../index.php" class="btn btn-secondary">← Voltar</a>
</div>

<div class="menu-agt">
    <a href="config_agt.php" class="active">⚙️ Configuração</a>
    <a href="faturas.php">📄 Faturas AGT</a>
    <a href="gerar_fatura_pagamento.php">📄 Gerar Fatura</a>
    <a href="relatorio.php">📈 Relatório</a>
</div>

<?php if ($sucesso): ?>
    <div class="alert alert-success"><?= $sucesso ?></div>
<?php endif; ?>

<?php if ($erro): ?>
    <div class="alert alert-error"><?= $erro ?></div>
<?php endif; ?>

<div class="alert alert-info">
    <strong>📌 Requisitos AGT para Faturas:</strong>
    <ul style="margin:8px 0 0 20px;font-size:13px;">
        <li>NIF da empresa obrigatório</li>
        <li>NIF do cliente obrigatório (usar 9999999999 para alunos sem NIF)</li>
        <li>Valor do IVA calculado automaticamente com base na taxa configurada</li>
        <li>Hash de autenticação para integridade da fatura</li>
    </ul>
</div>

<div class="form-container">
    <form method="POST" action="">
        <input type="hidden" name="salvar_config" value="1">
        
        <h3 style="color:#1a2332;margin-top:0;border-bottom:2px solid #f1f5f9;padding-bottom:10px;">🏢 Dados da Empresa</h3>
        
        <div class="form-row">
            <div class="form-group">
                <label>NIF <span class="required">*</span></label>
                <input type="text" name="nif" value="<?= htmlspecialchars($config['nif'] ?? $empresa['cnpj'] ?? '5001234567', ENT_QUOTES, 'UTF-8') ?>" required>
            </div>
            <div class="form-group">
                <label>Nome Comercial <span class="required">*</span></label>
                <input type="text" name="nome_comercial" value="<?= htmlspecialchars($config['nome_comercial'] ?? $empresa['nome_fantasia'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </div>
        </div>
        
        <div class="form-group">
            <label>Endereço <span class="required">*</span></label>
            <textarea name="endereco" rows="2" required><?= htmlspecialchars($config['endereco'] ?? $empresa['endereco'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Telefone</label>
                <input type="text" name="telefone" value="<?= htmlspecialchars($config['telefone'] ?? $empresa['telefone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($config['email'] ?? $empresa['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
        </div>
        
        <div class="form-group">
            <label>Site</label>
            <input type="text" name="site" value="<?= htmlspecialchars($config['site'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        
        <h3 style="color:#1a2332;margin-top:20px;border-bottom:2px solid #f1f5f9;padding-bottom:10px;">📋 Configuração de Fatura</h3>
        
        <div class="form-row-3">
            <div class="form-group">
                <label>Regime de IVA</label>
                <select name="regime_iva">
                    <option value="normal" <?= ($config['regime_iva'] ?? 'normal') == 'normal' ? 'selected' : '' ?>>Normal</option>
                    <option value="isento" <?= ($config['regime_iva'] ?? 'normal') == 'isento' ? 'selected' : '' ?>>Isento</option>
                    <option value="nao_sujeito" <?= ($config['regime_iva'] ?? 'normal') == 'nao_sujeito' ? 'selected' : '' ?>>Não Sujeito</option>
                </select>
            </div>
            <div class="form-group">
                <label>Taxa IVA Padrão (%)</label>
                <input type="number" step="0.01" name="taxa_iva_padrao" value="<?= htmlspecialchars($config['taxa_iva_padrao'] ?? 14, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-group">
                <label>Série da Fatura</label>
                <input type="text" name="serie_fatura" value="<?= htmlspecialchars($config['serie_fatura'] ?? 'A', ENT_QUOTES, 'UTF-8') ?>" maxlength="10">
            </div>
        </div>
        
        <div class="form-group">
            <label>Código de Validação</label>
            <input type="text" name="codigo_validacao" value="<?= htmlspecialchars($config['codigo_validacao'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Código fornecido pela AGT">
            <small style="color:#94a3b8;display:block;margin-top:4px;">Deixe em branco para gerar automaticamente</small>
        </div>
        
        <?php if (!empty($config)): ?>
        <div class="info-box">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;">
                <div>
                    <div class="label">Última Fatura</div>
                    <div class="value">#<?= str_pad($config['ultimo_numero'] ?? 1, 6, '0', STR_PAD_LEFT) ?></div>
                </div>
                <div>
                    <div class="label">Próximo Número</div>
                    <div class="value" style="color:#c9a84c;">#<?= str_pad(($config['ultimo_numero'] ?? 1) + 1, 6, '0', STR_PAD_LEFT) ?></div>
                </div>
                <div>
                    <div class="label">Status</div>
                    <div class="value" style="color:#2ecc71;">✅ Configurado</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Salvar Configuração</button>
            <a href="../index.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<?php include '../includes/footer_escola.php'; ?>