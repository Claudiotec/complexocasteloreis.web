<?php
// ============================================
// modules/escola/financeiro/pagamentos/view.php - Visualizar Pagamento
// ============================================

require_once '../../../../config/database.php';
require_once '../../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}

$id = $_GET['id'] ?? 0;

if (!$id) {
    header('Location: index.php?erro=ID não informado');
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT p.*, a.nome as aluno_nome, a.classe, a.turma, a.genero, e.nome as emolumento_nome
        FROM pagamentos p
        LEFT JOIN alunos a ON p.aluno_id = a.id
        LEFT JOIN emolumentos e ON p.emolumento_id = e.id
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    $pagamento = $stmt->fetch();
    
    if (!$pagamento) {
        header('Location: index.php?erro=Pagamento não encontrado');
        exit;
    }
} catch (Exception $e) {
    header('Location: index.php?erro=Erro ao carregar pagamento');
    exit;
}

include '../../includes/header_escola.php';
?>

<style>
.status-badge{display:inline-block;padding:3px 14px;border-radius:20px;font-size:11px;font-weight:600;}
.status-badge.confirmado{background:#d1fae5;color:#065f46;}
.status-badge.pendente{background:#fef3c7;color:#92400e;}
.status-badge.atrasado{background:#fee2e2;color:#991b1b;}
.status-badge.cancelado{background:#f1f5f9;color:#4a5568;}
.btn-primary{background:#c9a84c;color:#1a2332;padding:8px 20px;border-radius:8px;text-decoration:none;font-weight:600;transition:all .3s;display:inline-block;}
.btn-primary:hover{background:#b8973a;transform:translateY(-2px);}
.btn-secondary{background:#f1f5f9;color:#4a5568;padding:8px 20px;border-radius:8px;text-decoration:none;font-weight:600;transition:all .3s;display:inline-block;}
.btn-secondary:hover{background:#e2e8f0;transform:translateY(-2px);}
</style>

<div style="max-width:800px;margin:30px auto;padding:0 20px;">
    <div style="background:#fff;border-radius:12px;padding:30px;box-shadow:0 4px 25px rgba(0,0,0,0.06);border:1px solid #eef2f7;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:2px solid #eef2f7;padding-bottom:15px;">
            <h2 style="margin:0;color:#1a2332;">💰 Detalhes do Pagamento</h2>
            <a href="index.php" class="btn-secondary">← Voltar</a>
        </div>
        
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
            <div><strong>ID:</strong> #<?= $pagamento['id'] ?></div>
            <div><strong>Status:</strong> <span class="status-badge <?= $pagamento['status'] ?>"><?= ucfirst($pagamento['status']) ?></span></div>
            <div><strong>Aluno:</strong> <?= htmlspecialchars($pagamento['aluno_nome'] ?? $pagamento['nome_aluno'] ?? 'N/A') ?></div>
            <div><strong>Classe/Turma:</strong> <?= htmlspecialchars($pagamento['classe'] ?? '') ?> <?= htmlspecialchars($pagamento['turma'] ?? '') ?></div>
            <div><strong>Emolumento:</strong> <?= htmlspecialchars($pagamento['emolumento_nome'] ?? 'N/A') ?></div>
            <div><strong>Valor:</strong> <strong style="color:#2ecc71;font-size:18px;"><?= number_format($pagamento['valor'], 2, ',', '.') ?> Kz</strong></div>
            <div><strong>Data Pagamento:</strong> <?= date('d/m/Y H:i', strtotime($pagamento['data_pagamento'])) ?></div>
            <div><strong>Forma:</strong> <?= ucfirst($pagamento['forma_pagamento'] ?? 'N/A') ?></div>
            <div><strong>Mês Referência:</strong> <?= htmlspecialchars($pagamento['mes_referencia'] ?? '-') ?></div>
            <div><strong>Referência:</strong> <?= htmlspecialchars($pagamento['referencia'] ?? 'N/A') ?></div>
        </div>
        
        <?php if (!empty($pagamento['observacoes'])): ?>
        <div style="margin-top:20px;padding:12px 16px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;">
            <strong>📝 Observações:</strong>
            <p style="margin:5px 0 0;color:#4a5568;"><?= nl2br(htmlspecialchars($pagamento['observacoes'])) ?></p>
        </div>
        <?php endif; ?>
        
        <div style="margin-top:25px;display:flex;gap:10px;flex-wrap:wrap;border-top:1px solid #eef2f7;padding-top:20px;">
            <a href="../relatorios/relatorio_agt.php?pagamento_id=<?= $pagamento['id'] ?>" class="btn-primary" target="_blank">📄 Fatura</a>
            <a href="editar.php?id=<?= $pagamento['id'] ?>" class="btn-secondary">✏️ Editar</a>
            <a href="excluir.php?id=<?= $pagamento['id'] ?>" class="btn-secondary" style="background:#fee2e2;color:#991b1b;" onclick="return confirm('Tem certeza que deseja excluir este pagamento?')">🗑️ Excluir</a>
        </div>
    </div>
</div>

<?php include '../../includes/footer_escola.php'; ?>