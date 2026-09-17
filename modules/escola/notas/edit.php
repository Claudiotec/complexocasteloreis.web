<?php
// ============================================
// modules/escola/notas/edit.php - Editar Nota
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

if (!isset($pdo) || !$pdo) {
    $pdo = conectarBanco();
}

$id = intval($_GET['id'] ?? 0);
$msg = '';
$msgTipo = '';

// ===== BUSCAR NOTA =====
$nota = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM notas_alunos WHERE id = ?");
    $stmt->execute([$id]);
    $nota = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erro ao buscar nota: " . $e->getMessage());
}

if (!$nota) {
    header('Location: index.php?msg=nao_encontrado');
    exit;
}

// ===== SALVAR =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mac_t1 = $_POST['mac_t1'] !== '' ? floatval(str_replace(',', '.', $_POST['mac_t1'])) : null;
    $npt_t1 = $_POST['npt_t1'] !== '' ? floatval(str_replace(',', '.', $_POST['npt_t1'])) : null;
    $mt1 = ($mac_t1 !== null && $npt_t1 !== null) ? ($mac_t1 + $npt_t1) / 2 : null;

    $mac_t2 = $_POST['mac_t2'] !== '' ? floatval(str_replace(',', '.', $_POST['mac_t2'])) : null;
    $npt_t2 = $_POST['npt_t2'] !== '' ? floatval(str_replace(',', '.', $_POST['npt_t2'])) : null;
    $mt2 = ($mac_t2 !== null && $npt_t2 !== null) ? ($mac_t2 + $npt_t2) / 2 : null;

    $mac_t3 = $_POST['mac_t3'] !== '' ? floatval(str_replace(',', '.', $_POST['mac_t3'])) : null;
    $npt_t3 = $_POST['npt_t3'] !== '' ? floatval(str_replace(',', '.', $_POST['npt_t3'])) : null;
    $mt3 = ($mac_t3 !== null && $npt_t3 !== null) ? ($mac_t3 + $npt_t3) / 2 : null;

    // MFD = (MT1 + MT2 + MT3) / 3 (ignorando nulos)
    $valores = array_filter([$mt1, $mt2, $mt3], function($v) { return $v !== null; });
    $mfd = !empty($valores) ? round(array_sum($valores) / count($valores), 1) : null;

    // Classificação conforme escala da classe
    $classe = $nota['classe'] ?? '';
    $classeNum = intval(preg_replace('/[^0-9]/', '', $classe));
    $isPreSexta = ($classeNum >= 1 && $classeNum <= 6) || strtoupper($classe) === 'PRE';
    $notaMinima = $isPreSexta ? 5 : 10;
    $notaRecup = $isPreSexta ? 3 : 5;

    if ($mfd === null) {
        $classificacao = '';
    } elseif ($mfd >= $notaMinima) {
        $classificacao = $isPreSexta ? 'MUITO BOM' : 'SUFICIENTE';
    } elseif ($mfd >= $notaRecup) {
        $classificacao = 'RECUPERAÇÃO';
    } else {
        $classificacao = 'MAU';
    }

    try {
        $sql = "UPDATE notas_alunos SET 
                    mac_t1 = ?, npt_t1 = ?, mt1 = ?,
                    mac_t2 = ?, npt_t2 = ?, mt2 = ?,
                    mac_t3 = ?, npt_t3 = ?, mt3 = ?,
                    mfd = ?, classificacao = ?,
                    data_atualizacao = CURRENT_TIMESTAMP
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $mac_t1, $npt_t1, $mt1,
            $mac_t2, $npt_t2, $mt2,
            $mac_t3, $npt_t3, $mt3,
            $mfd, $classificacao,
            $id
        ]);
        $msg = 'Nota atualizada com sucesso!';
        $msgTipo = 'success';

        // Recarrega
        $stmt = $pdo->prepare("SELECT * FROM notas_alunos WHERE id = ?");
        $stmt->execute([$id]);
        $nota = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erro ao salvar: " . $e->getMessage());
        $msg = 'Erro ao salvar: ' . $e->getMessage();
        $msgTipo = 'error';
    }
}

include '../includes/header_escola.php';
?>

<style>
    .page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 25px; }
    .page-header h1 { font-size: 24px; font-weight: 700; color: #1a2332; margin: 0; }
    .btn { padding: 8px 20px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; transition: all 0.3s; display: inline-flex; align-items: center; gap: 6px; border: none; cursor: pointer; }
    .btn-primary { background: #c9a84c; color: #1a2332; }
    .btn-primary:hover { background: #b8973a; }
    .btn-secondary { background: #f1f5f9; color: #4a5568; }
    .btn-secondary:hover { background: #e2e8f0; }
    .btn-success { background: #2ecc71; color: #fff; }
    .btn-success:hover { background: #27ae60; }
    .alert { padding: 12px 18px; border-radius: 8px; margin-bottom: 15px; font-weight: 500; }
    .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #2ecc71; }
    .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #e74c3c; }
    .card { background: white; border-radius: 12px; padding: 25px 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); border: 1px solid #eef2f7; }
    .info-header { background: #f8fafc; padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid #c9a84c; }
    .info-header p { margin: 4px 0; font-size: 13px; color: #4a5568; }
    .info-header strong { color: #1a2332; }
    .trimestre-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin: 20px 0; }
    .trimestre-box { background: #f8fafc; padding: 15px 20px; border-radius: 10px; border: 1px solid #e2e8f0; }
    .trimestre-box h4 { font-size: 14px; color: #1a2332; margin: 0 0 12px; padding-bottom: 6px; border-bottom: 2px solid #c9a84c; }
    .form-group { margin-bottom: 12px; }
    .form-group label { display: block; font-weight: 600; font-size: 12px; color: #4a5568; margin-bottom: 4px; }
    .form-group input { width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; }
    .form-group input:focus { outline: none; border-color: #c9a84c; box-shadow: 0 0 0 3px rgba(201,168,76,0.1); }
    .form-group .media-box { background: #e8f4fc; padding: 6px 10px; border-radius: 6px; font-weight: 700; color: #1e40af; font-size: 13px; display: inline-block; margin-top: 5px; }
    .resultado-box { background: #f5edd6; padding: 15px 20px; border-radius: 10px; margin: 20px 0; border-left: 4px solid #c9a84c; }
    .resultado-box .mfd-valor { font-size: 26px; font-weight: 700; color: #1a2332; }
    .resultado-box .classif { font-size: 14px; font-weight: 600; padding: 4px 14px; border-radius: 12px; background: #c9a84c; color: #1a2332; display: inline-block; margin-left: 10px; }
    .actions { display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap; }
</style>

<div class="page-header">
    <div>
        <h1>✏️ Editar Nota</h1>
        <p class="subtitle" style="color:#94a3b8;font-size:14px;margin:2px 0 0;">
            <?= htmlspecialchars($nota['nome_aluno']) ?> • <?= htmlspecialchars($nota['disciplina']) ?>
        </p>
    </div>
    <a href="index.php" class="btn btn-secondary">← Voltar</a>
</div>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgTipo ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="card">
    <div class="info-header">
        <p><strong>👤 Aluno:</strong> <?= htmlspecialchars($nota['nome_aluno']) ?></p>
        <p><strong>📖 Disciplina:</strong> <?= htmlspecialchars($nota['disciplina']) ?></p>
        <p><strong>📚 Classe:</strong> <?= htmlspecialchars($nota['classe']) ?> • 
           <strong>👥 Turma:</strong> <?= htmlspecialchars($nota['turma']) ?> • 
           <strong>📅 Ano:</strong> <?= htmlspecialchars($nota['ano_letivo']) ?></p>
    </div>

    <form method="POST">
        <div class="trimestre-grid">
            <!-- 1º TRIMESTRE -->
            <div class="trimestre-box">
                <h4>1º Trimestre</h4>
                <div class="form-group">
                    <label>MAC</label>
                    <input type="number" name="mac_t1" step="0.1" min="0" max="20" 
                           value="<?= htmlspecialchars($nota['mac_t1'] ?? '') ?>"
                           oninput="calcMT(1)">
                </div>
                <div class="form-group">
                    <label>NPT</label>
                    <input type="number" name="npt_t1" step="0.1" min="0" max="20" 
                           value="<?= htmlspecialchars($nota['npt_t1'] ?? '') ?>"
                           oninput="calcMT(1)">
                </div>
                <div class="form-group">
                    <label>MT1</label>
                    <div class="media-box" id="mt1_box"><?= $nota['mt1'] !== null ? number_format((float)$nota['mt1'], 1) : '-' ?></div>
                </div>
            </div>

            <!-- 2º TRIMESTRE -->
            <div class="trimestre-box">
                <h4>2º Trimestre</h4>
                <div class="form-group">
                    <label>MAC</label>
                    <input type="number" name="mac_t2" step="0.1" min="0" max="20" 
                           value="<?= htmlspecialchars($nota['mac_t2'] ?? '') ?>"
                           oninput="calcMT(2)">
                </div>
                <div class="form-group">
                    <label>NPT</label>
                    <input type="number" name="npt_t2" step="0.1" min="0" max="20" 
                           value="<?= htmlspecialchars($nota['npt_t2'] ?? '') ?>"
                           oninput="calcMT(2)">
                </div>
                <div class="form-group">
                    <label>MT2</label>
                    <div class="media-box" id="mt2_box"><?= $nota['mt2'] !== null ? number_format((float)$nota['mt2'], 1) : '-' ?></div>
                </div>
            </div>

            <!-- 3º TRIMESTRE -->
            <div class="trimestre-box">
                <h4>3º Trimestre</h4>
                <div class="form-group">
                    <label>MAC</label>
                    <input type="number" name="mac_t3" step="0.1" min="0" max="20" 
                           value="<?= htmlspecialchars($nota['mac_t3'] ?? '') ?>"
                           oninput="calcMT(3)">
                </div>
                <div class="form-group">
                    <label>NPT</label>
                    <input type="number" name="npt_t3" step="0.1" min="0" max="20" 
                           value="<?= htmlspecialchars($nota['npt_t3'] ?? '') ?>"
                           oninput="calcMT(3)">
                </div>
                <div class="form-group">
                    <label>MT3</label>
                    <div class="media-box" id="mt3_box"><?= $nota['mt3'] !== null ? number_format((float)$nota['mt3'], 1) : '-' ?></div>
                </div>
            </div>
        </div>

        <div class="resultado-box">
            <span style="font-size:13px;color:#4a5568;font-weight:600;">MFD (Média Final da Disciplina):</span>
            <span class="mfd-valor" id="mfd_box"><?= $nota['mfd'] !== null ? number_format((float)$nota['mfd'], 1) : '-' ?></span>
            <span class="classif" id="classif_box"><?= htmlspecialchars($nota['classificacao'] ?? '-') ?></span>
            <div style="margin-top:8px;font-size:11px;color:#94a3b8;">
                🧮 MFD = (MT1 + MT2 + MT3) ÷ 3
            </div>
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-success">💾 Salvar Alterações</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script>
function getVal(name) {
    var el = document.querySelector('input[name="' + name + '"]');
    if (!el || el.value === '') return null;
    var v = parseFloat(el.value);
    return isNaN(v) ? null : v;
}

function calcMT(num) {
    var mac = getVal('mac_t' + num);
    var npt = getVal('npt_t' + num);
    var box = document.getElementById('mt' + num + '_box');
    
    if (mac !== null && npt !== null) {
        var mt = (mac + npt) / 2;
        box.textContent = mt.toFixed(1);
    } else if (mac !== null) {
        box.textContent = mac.toFixed(1);
    } else if (npt !== null) {
        box.textContent = npt.toFixed(1);
    } else {
        box.textContent = '-';
    }
    calcMFD();
}

function calcMFD() {
    var mts = [];
    [1,2,3].forEach(function(i) {
        var box = document.getElementById('mt' + i + '_box');
        var v = parseFloat(box.textContent);
        if (!isNaN(v)) mts.push(v);
    });
    
    var mfdBox = document.getElementById('mfd_box');
    var classifBox = document.getElementById('classif_box');
    
    if (mts.length === 0) {
        mfdBox.textContent = '-';
        classifBox.textContent = '-';
        return;
    }
    
    var mfd = mts.reduce(function(a,b){return a+b;}, 0) / mts.length;
    mfdBox.textContent = mfd.toFixed(1);
    
    // Classificação conforme a classe
    var classe = '<?= addslashes($nota['classe'] ?? '') ?>';
    var classeNum = parseInt(classe.replace(/[^0-9]/g, ''));
    var isPreSexta = (classeNum >= 1 && classeNum <= 6) || classe.toUpperCase() === 'PRE';
    var notaMin = isPreSexta ? 5 : 10;
    var notaRec = isPreSexta ? 3 : 5;
    
    if (mfd >= notaMin) {
        classifBox.textContent = 'APROVADO';
        classifBox.style.background = '#2ecc71';
        classifBox.style.color = '#fff';
    } else if (mfd >= notaRec) {
        classifBox.textContent = 'RECUPERAÇÃO';
        classifBox.style.background = '#f39c12';
        classifBox.style.color = '#fff';
    } else {
        classifBox.textContent = 'REPROVADO';
        classifBox.style.background = '#e74c3c';
        classifBox.style.color = '#fff';
    }
}
</script>

<?php include '../includes/footer_escola.php'; ?>