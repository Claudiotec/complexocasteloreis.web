<?php
// ============================================
// modules/escola/agt/gerar_fatura_pagamento.php - Gerar Fatura do Pagamento
// ============================================

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';
require_once 'agt_functions.php';
require_once 'gerar_fatura_html.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'criar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ============================================
// FUNÇÃO AUXILIAR
// ============================================
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

// ============================================
// VARIÁVEIS
// ============================================
$erro = '';
$sucesso = '';
$fatura_html = '';
$fatura_id = null;

// ============================================
// PROCESSAR GERAÇÃO DE FATURA
// ============================================
if (isset($_GET['pagamento_id'])) {
    $pagamento_id = intval($_GET['pagamento_id']);
    
    try {
        // Buscar pagamento
        $stmt = $pdo->prepare("
            SELECT p.*, a.nome as aluno_nome, a.Classe, a.TURMA, a.Morada, a.Contacto_do_Aluno,
                   a.nif, a.Nome_do_Pai, a.Contacto4, a.Periodo,
                   e.nome as emolumento_nome, e.valor as emolumento_valor,
                   e.codigo_iva, e.taxa_iva, e.regime_iva
            FROM pagamentos p
            LEFT JOIN alunos a ON p.aluno_id = a.id
            LEFT JOIN emolumentos e ON p.emolumento_id = e.id
            WHERE p.id = ?
        ");
        $stmt->execute([$pagamento_id]);
        $pagamento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$pagamento) {
            throw new Exception('Pagamento não encontrado!');
        }
        
        // Verificar se já tem fatura
        if (!empty($pagamento['numero_fatura'])) {
            throw new Exception('Este pagamento já possui fatura: ' . $pagamento['numero_fatura']);
        }
        
        // Buscar configuração AGT
        $config = buscarConfigAGT($pdo);
        if (!$config) {
            throw new Exception('Configuração AGT não encontrada! Configure os dados da empresa.');
        }
        
        // Gerar número da fatura
        $numero_fatura = gerarNumeroFaturaAGT($pdo);
        
        // Dados da empresa
        $empresa = [
            'nome' => $config['nome_comercial'],
            'nif' => $config['nif'],
            'endereco' => $config['endereco'],
            'telefone' => $config['telefone'],
            'email' => $config['email'],
            'regime_iva' => $config['regime_iva'],
            'taxa_iva_padrao' => $config['taxa_iva_padrao']
        ];
        
        // Dados do aluno
        $aluno = [
            'nome' => $pagamento['aluno_nome'] ?? 'Aluno',
            'nif' => $pagamento['nif'] ?? '9999999999',
            'endereco' => $pagamento['Morada'] ?? '',
            'classe' => $pagamento['Classe'] ?? '',
            'turma' => $pagamento['TURMA'] ?? '',
            'telefone' => $pagamento['Contacto_do_Aluno'] ?? '',
            'periodo' => $pagamento['Periodo'] ?? 'Manhã'
        ];
        
        // Buscar emolumento com IVA
        $emolumento = buscarEmolumentoComIVA($pdo, $pagamento['emolumento_id']);
        $taxa_iva = floatval($emolumento['taxa_iva'] ?? 0);
        $valor = floatval($pagamento['valor'] ?? 0);
        $valor_iva = ($valor * $taxa_iva) / 100;
        $total_liquido = $valor + $valor_iva;
        
        // Itens da fatura
        $itens = [[
            'descricao' => $pagamento['emolumento_nome'] ?? 'Pagamento Escolar',
            'quantidade' => 1,
            'valor_base' => $valor,
            'valor_iva' => $valor_iva,
            'taxa_iva' => $taxa_iva,
            'desconto' => 0,
            'multa' => 0,
            'valor_liquido' => $total_liquido,
            'mes_referencia' => $pagamento['mes_referencia'] ?? null,
            'ano_referencia' => date('Y'),
            'emolumento_id' => $pagamento['emolumento_id']
        ]];
        
        // Salvar fatura
        $resultado = salvarFaturaAGT($pdo, [
            'aluno_id' => $pagamento['aluno_id'],
            'data_pagamento' => $pagamento['data_pagamento'],
            'forma_pagamento' => $pagamento['forma_pagamento'],
            'referencia' => $pagamento['referencia'],
            'observacoes' => 'Fatura gerada a partir do pagamento #' . $pagamento_id
        ], $itens);
        
        if (!$resultado['success']) {
            throw new Exception($resultado['error']);
        }
        
        // Atualizar pagamento com número da fatura
        atualizarPagamentoComFatura($pdo, $pagamento_id, $numero_fatura, $resultado['hash']);
        
        // Gerar HTML da fatura
        $fatura_html = gerarFaturaHTMLAGT(
            $empresa,
            $aluno,
            $itens,
            $numero_fatura,
            $resultado['totais']['total_base'],
            $resultado['totais']['total_iva'],
            $resultado['totais']['total_liquido'],
            $resultado['hash']
        );
        
        $fatura_id = $resultado['fatura_id'];
        $sucesso = "✅ Fatura gerada com sucesso! Número: $numero_fatura";
        
    } catch (Exception $e) {
        $erro = 'Erro ao gerar fatura: ' . $e->getMessage();
    }
}

// ============================================
// INCLUIR HEADER
// ============================================
include '../../includes/header_escola.php';
?>

<style>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
}
.page-header h1 {
    font-size: 24px;
    font-weight: 700;
    color: #1a2332;
    margin: 0;
}
.page-header .subtitle {
    color: #94a3b8;
    font-size: 14px;
    margin: 2px 0 0;
}
.btn {
    padding: 8px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    transition: all .3s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: none;
    cursor: pointer;
}
.btn-primary {
    background: #c9a84c;
    color: #1a2332;
}
.btn-primary:hover {
    background: #b8973d;
    transform: translateY(-2px);
}
.btn-secondary {
    background: #f1f5f9;
    color: #4a5568;
}
.btn-secondary:hover {
    background: #e2e8f0;
}
.btn-success {
    background: #2ecc71;
    color: #fff;
}
.btn-success:hover {
    background: #27ae60;
}
.btn-info {
    background: #3498db;
    color: #fff;
}
.btn-info:hover {
    background: #2980b9;
}
.alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
}
.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}
.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}
.alert-info {
    background: #dbeafe;
    color: #1e40af;
    border: 1px solid #bfdbfe;
}
.form-container {
    background: #fff;
    border-radius: 12px;
    padding: 30px;
    border: 1px solid #eef2f7;
    max-width: 700px;
    margin: 0 auto;
}
.form-group {
    margin-bottom: 18px;
}
.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
    color: #1a2332;
    font-size: 13px;
}
.form-group label .required {
    color: #e74c3c;
}
.form-group input,
.form-group select {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color .3s;
    font-family: inherit;
}
.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #c9a84c;
    box-shadow: 0 0 0 3px rgba(201,168,76,0.1);
}
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
.form-actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
    flex-wrap: wrap;
}
.fatura-modal {
    display: <?= $fatura_html ? 'block' : 'none' ?>;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.6);
    z-index: 9999;
    overflow-y: auto;
    padding: 20px;
}
.fatura-modal-content {
    max-width: 210mm;
    margin: 20px auto;
    background: #fff;
    border-radius: 8px;
    padding: 0;
    position: relative;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
}
.fatura-modal .btn-fechar {
    position: sticky;
    top: 0;
    float: right;
    background: #e74c3c;
    color: #fff;
    border: none;
    padding: 8px 18px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    margin: 10px;
    z-index: 10;
}
.fatura-modal .btn-fechar:hover {
    background: #c0392b;
}
.fatura-iframe {
    width: 100%;
    height: 900px;
    border: none;
    border-radius: 0 0 8px 8px;
}
@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }
    .form-container {
        padding: 20px;
    }
    .form-actions {
        flex-direction: column;
    }
    .form-actions .btn {
        justify-content: center;
    }
    .fatura-modal-content {
        margin: 10px;
    }
    .fatura-iframe {
        height: 500px;
    }
}
</style>

<div class="page-header">
    <div>
        <h1>📄 Gerar Fatura AGT</h1>
        <p class="subtitle">Gerar fatura a partir de um pagamento existente</p>
    </div>
    <a href="../pagamentos/index.php" class="btn btn-secondary">← Voltar</a>
</div>

<?php if ($sucesso): ?>
    <div class="alert alert-success"><?= $sucesso ?></div>
<?php endif; ?>

<?php if ($erro): ?>
    <div class="alert alert-error"><?= $erro ?></div>
<?php endif; ?>

<div class="alert alert-info">
    <strong>📌 Como funciona:</strong>
    <ul style="margin:8px 0 0 20px;font-size:13px;">
        <li>Informe o ID do pagamento para gerar a fatura</li>
        <li>A fatura será gerada com os dados do pagamento e do aluno</li>
        <li>O sistema calcula automaticamente o IVA com base no emolumento</li>
        <li>Após gerada, a fatura pode ser impressa ou salva como PDF</li>
    </ul>
</div>

<div class="form-container">
    <form method="GET" action="">
        <div class="form-group">
            <label>ID do Pagamento <span class="required">*</span></label>
            <input type="number" name="pagamento_id" placeholder="Digite o ID do pagamento..." required min="1">
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">📄 Gerar Fatura</button>
            <a href="../pagamentos/index.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<?php if ($fatura_html): ?>
<div class="fatura-modal" id="faturaModal">
    <div class="fatura-modal-content">
        <button class="btn-fechar" onclick="fecharFatura()">✕ Fechar</button>
        <iframe class="fatura-iframe" srcdoc="<?= htmlspecialchars($fatura_html) ?>"></iframe>
    </div>
</div>

<script>
function fecharFatura() {
    document.getElementById('faturaModal').style.display = 'none';
    window.location.href = 'gerar_fatura_pagamento.php';
}
</script>
<?php endif; ?>

<?php include '../../includes/footer_escola.php'; ?>