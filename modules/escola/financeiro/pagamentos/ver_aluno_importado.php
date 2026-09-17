<?php
// ============================================
// modules/escola/financeiro/pagamentos/ver_aluno_importado.php
// Visualizar detalhes de um aluno importado
// ============================================

require_once '../../../../config/database.php';
require_once '../../../../config/app_modes.php';

header('Content-Type: text/html; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}

function limparString($texto) {
    if (empty($texto)) return '';
    if (!mb_check_encoding($texto, 'UTF-8')) {
        $texto = mb_convert_encoding($texto, 'UTF-8', 'auto');
    }
    $texto = preg_replace('/[[:cntrl:]]/', '', $texto);
    $texto = str_replace(['?', '�', '�', '�', '�', '�', '�'], '', $texto);
    $texto = trim($texto);
    $texto = preg_replace('/\s+/', ' ', $texto);
    return $texto;
}

include '../../includes/header_escola.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$aluno = null;
$erro = '';

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM alunos_ano_anterior WHERE id = ?");
        $stmt->execute([$id]);
        $aluno = $stmt->fetch();
        
        if (!$aluno) {
            $erro = 'Aluno não encontrado!';
        }
    } catch (Exception $e) {
        $erro = 'Erro ao buscar aluno: ' . $e->getMessage();
    }
} else {
    $erro = 'ID inválido!';
}
?>

<style>
.page-header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;margin-bottom:25px}
.page-header h1{font-size:24px;font-weight:700;color:#1a2332;margin:0}
.btn{padding:8px 20px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;transition:all .3s;display:inline-flex;align-items:center;gap:6px;border:none;cursor:pointer}
.btn-secondary{background:#f1f5f9;color:#4a5568}
.btn-secondary:hover{background:#e2e8f0}
.btn-primary{background:#c9a84c;color:#1a2332}
.btn-primary:hover{background:#b8973a}
.alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:12px 16px;border-radius:8px;margin-bottom:20px}
.detalhes-card{background:#fff;border-radius:12px;border:1px solid #eef2f7;padding:25px;max-width:900px;margin:0 auto}
.detalhes-card .header-card{border-bottom:2px solid #f1f5f9;padding-bottom:15px;margin-bottom:20px}
.detalhes-card .header-card h2{font-size:20px;color:#1a2332;margin:0}
.detalhes-card .header-card .subtitle{color:#94a3b8;font-size:14px}
.detalhes-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px}
.detalhes-grid .item{border-bottom:1px solid #f1f5f9;padding-bottom:8px}
.detalhes-grid .item .label{font-size:12px;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:0.5px}
.detalhes-grid .item .value{font-size:14px;color:#1a2332;font-weight:500;word-break:break-word}
@media(max-width:768px){.detalhes-grid{grid-template-columns:1fr}}
</style>

<div class="page-header">
    <div><h1>👁️ Detalhes do Aluno Importado</h1></div>
    <a href="importados_alunos.php" class="btn btn-secondary">← Voltar</a>
</div>

<?php if ($erro): ?>
<div class="alert-error">❌ <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if ($aluno): ?>
<div class="detalhes-card">
    <div class="header-card">
        <h2><?= htmlspecialchars(limparString($aluno['nome']), ENT_QUOTES, 'UTF-8') ?></h2>
        <div class="subtitle">ID: #<?= $aluno['id'] ?> | Classe: <?= htmlspecialchars($aluno['Classe'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
    </div>
    
    <div class="detalhes-grid">
        <div class="item">
            <div class="label">Sexo</div>
            <div class="value"><?= htmlspecialchars($aluno['Sexo'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="item">
            <div class="label">Data de Nascimento</div>
            <div class="value"><?= htmlspecialchars($aluno['dia'] ?? '') . '/' . htmlspecialchars($aluno['mes'] ?? '') . '/' . htmlspecialchars($aluno['Ano'] ?? '') ?></div>
        </div>
        <div class="item">
            <div class="label">Idade</div>
            <div class="value"><?= htmlspecialchars($aluno['Idade'] ?? '-', ENT_QUOTES, 'UTF-8') ?> anos</div>
        </div>
        <div class="item">
            <div class="label">Naturalidade</div>
            <div class="value"><?= htmlspecialchars($aluno['Naturalidade'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="item">
            <div class="label">Município</div>
            <div class="value"><?= htmlspecialchars($aluno['Município'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="item">
            <div class="label">Província</div>
            <div class="value"><?= htmlspecialchars($aluno['Província'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="item" style="grid-column:1/-1;">
            <div class="label">Morada</div>
            <div class="value"><?= htmlspecialchars($aluno['Morada'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="item">
            <div class="label">Nº BI</div>
            <div class="value"><?= htmlspecialchars($aluno['N_BI'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="item">
            <div class="label">Classe</div>
            <div class="value"><?= htmlspecialchars($aluno['Classe'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="item">
            <div class="label">Turma</div>
            <div class="value"><?= htmlspecialchars($aluno['TURMA'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="item">
            <div class="label">Sala</div>
            <div class="value"><?= htmlspecialchars($aluno['SALA'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="item">
            <div class="label">Período</div>
            <div class="value"><?= htmlspecialchars($aluno['Periodo'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="item">
            <div class="label">Situação Cadastro</div>
            <div class="value"><?= htmlspecialchars($aluno['Situacao_Cadastro'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="item">
            <div class="label">Data Matrícula</div>
            <div class="value"><?= !empty($aluno['Data_Matricula']) ? date('d/m/Y', strtotime($aluno['Data_Matricula'])) : '-' ?></div>
        </div>
        <div class="item" style="grid-column:1/-1;">
            <div class="label">Pai</div>
            <div class="value"><?= htmlspecialchars($aluno['Nome_do_Pai'] ?? '-', ENT_QUOTES, 'UTF-8') ?> | Contacto: <?= htmlspecialchars($aluno['Contacto4'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="item" style="grid-column:1/-1;">
            <div class="label">Mãe</div>
            <div class="value"><?= htmlspecialchars($aluno['Nome_da_mae'] ?? '-', ENT_QUOTES, 'UTF-8') ?> | Contacto: <?= htmlspecialchars($aluno['Contacto_Mae'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="item">
            <div class="label">Debilidade</div>
            <div class="value"><?= htmlspecialchars($aluno['Debilidade'] ?? 'Nenhuma', ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="item">
            <div class="label">Cadastro Transporte</div>
            <div class="value"><?= htmlspecialchars($aluno['Cadastro_Transporte'] ?? 'Não', ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>
    
    <div style="margin-top:20px;padding-top:15px;border-top:2px solid #f1f5f9;display:flex;gap:10px;flex-wrap:wrap;">
        <a href="importados_alunos.php" class="btn btn-secondary">← Voltar</a>
        <a href="?delete=<?= $aluno['id'] ?>" onclick="return confirm('Excluir este registro?')" class="btn btn-danger">🗑️ Excluir</a>
    </div>
</div>
<?php endif; ?>

<?php include '../../includes/footer_escola.php'; ?>