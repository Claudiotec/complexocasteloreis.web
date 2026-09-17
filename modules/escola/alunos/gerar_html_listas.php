<?php
// ============================================
// modules/escola/alunos/gerar_html_listas.php - Gerar HTML das Listas
// ============================================

require_once '../../../config/database.php';
require_once '../../../config/app_modes.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'visualizar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ===== FUNÇÕES =====
function disciplinasPorClasse($classe) {
    $classe = strtoupper(trim($classe));
    $disciplinas_base = ["L. POT", "INGLÊS", "MAT.", "ED. FISICA"];
    
    if (strpos($classe, 'PRÉ') !== false) {
        return array_merge($disciplinas_base, ["BIOLOGIA/ C. Nat/ Est. Meio", "E. V. P./ Ed. Manual Plastica", "ED. LABORAL/ Ed. Musical"]);
    } elseif (preg_match('/[1-4]ª/', $classe)) {
        return array_merge($disciplinas_base, ["BIOLOGIA/ C. Nat/ Est. Meio", "E. V. P./ Ed. Manual Plastica", "ED. LABORAL/ Ed. Musical"]);
    } elseif (preg_match('/[5-6]ª/', $classe)) {
        return array_merge($disciplinas_base, ["BIOLOGIA/ C. Nat/ Est. Meio", "E. V. P./ Ed. Manual Plastica", "ED. LABORAL/ Ed. Musical", "E. M. C.", "GEOGRAFIA", "HISTORIA", "XADREZ"]);
    } elseif (preg_match('/[7-9]ª/', $classe)) {
        return array_merge($disciplinas_base, ["BIOLOGIA/ C. Nat/ Est. Meio", "E. V. P./ Ed. Manual Plastica", "ED. LABORAL/ Ed. Musical", "FISICA", "E. M. C.", "GEOGRAFIA", "HISTORIA", "QUIMICA", "EMPREED.", "INFORMÁTICA"]);
    }
    return ["L. POT", "INGLÊS", "MAT.", "BIOLOGIA/ C. Nat/ Est. Meio", "E. M. C.", "GEOGRAFIA", "HISTORIA", "ED. FISICA", "E. V. P./ Ed. Manual Plastica", "ED. LABORAL/ Ed. Musical", "EMPREED.", "FISICA", "QUIMICA", "INFORMÁTICA", "XADREZ"];
}

function obterNomeDisciplina($disciplina, $classe) {
    $classe = strtoupper(trim($classe));
    if ($disciplina == "BIOLOGIA/ C. Nat/ Est. Meio") {
        if (preg_match('/[1-4]ª/', $classe)) return "ESTUDO DO MEIO";
        elseif (preg_match('/[5-6]ª/', $classe)) return "CIÊNCIAS DA NATUREZA";
        elseif (preg_match('/[7-9]ª/', $classe)) return "BIOLOGIA";
        elseif (strpos($classe, 'PRÉ') !== false) return "MEIO FÍSICO E SOCIAL";
    } elseif ($disciplina == "E. V. P./ Ed. Manual Plastica") {
        if (preg_match('/[1-6]ª/', $classe) || strpos($classe, 'PRÉ') !== false) return "ED. MANUAL PLÁSTICA";
        elseif (preg_match('/[7-9]ª/', $classe)) return "E.V.P";
    } elseif ($disciplina == "ED. LABORAL/ Ed. Musical") {
        if (strpos($classe, 'PRÉ') !== false || preg_match('/[1-6]ª/', $classe)) return "ED. MUSICAL";
        return "EDUCAÇÃO LABORAL";
    } elseif ($disciplina == "MAT.") {
        return (strpos($classe, 'PRÉ') !== false) ? "REPRESENTAÇÃO MATEMÁTICA" : "MATEMÁTICA";
    } elseif ($disciplina == "L. POT") {
        return (strpos($classe, 'PRÉ') !== false) ? "COMUNICAÇÃO LINGUÍSTICA" : "LÍNGUA PORTUGUESA";
    }
    return $disciplina;
}

// ===== BUSCAR ALUNOS =====
$alunos = [];
try {
    $alunos = $pdo->query("
        SELECT id, nome, Sexo, Idade, dia, mes, Ano, 
               Classe, Curso, TURMA, SALA, Periodo, Situacao_Cadastro
        FROM alunos 
        WHERE Situacao_Cadastro = 'Matrícula' OR Situacao_Cadastro = 'Confirmação'
        ORDER BY Classe, TURMA, nome
    ")->fetchAll();
} catch (Exception $e) {}

// ===== DADOS DA EMPRESA =====
$empresa = [];
try {
    $stmt = $pdo->query("SELECT * FROM empresa LIMIT 1");
    $empresa = $stmt->fetch();
} catch (Exception $e) {}

$nomeEmpresa = $empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? 'SoftGest Sistemas';

// ===== CALCULAR ANO LETIVO =====
$mes_atual = date('m');
$ano_atual = date('Y');
if ($mes_atual >= 9) {
    $ano_letivo = $ano_atual . '/' . ($ano_atual + 1);
} else {
    $ano_letivo = ($ano_atual - 1) . '/' . $ano_atual;
}

// ===== AGRUPAR =====
$grupos = [];
foreach ($alunos as $aluno) {
    $classe = $aluno['Classe'] ?? 'Sem Classe';
    $turma = $aluno['TURMA'] ?? 'Sem Turma';
    $sala = $aluno['SALA'] ?? 'Sem Sala';
    $key = $classe . '|' . $turma . '|' . $sala;
    
    if (!isset($grupos[$key])) {
        $grupos[$key] = [
            'classe' => $classe,
            'turma' => $turma,
            'sala' => $sala,
            'periodo' => $aluno['Periodo'] ?? 'Manhã',
            'alunos' => []
        ];
    }
    $grupos[$key]['alunos'][] = $aluno;
}

// ===== GERAR HTML =====
$data_atual = date('d/m/Y H:i');
$output = '';

foreach($grupos as $grupo) {
    $classe = $grupo['classe'];
    $turma = $grupo['turma'];
    $sala = $grupo['sala'];
    $periodo = $grupo['periodo'];
    $alunos_lista = $grupo['alunos'];
    $total = count($alunos_lista);
    
    // Obter disciplinas
    $disciplinas = disciplinasPorClasse($classe);
    $disciplinas_formatadas = array_map(function($d) use ($classe) {
        return obterNomeDisciplina($d, $classe);
    }, $disciplinas);
    
    $output .= '
    <div style="page-break-after: always; font-family: Arial, sans-serif; margin: 20px; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
        <div style="text-align: center; border-bottom: 2px solid #1a2332; padding-bottom: 15px; margin-bottom: 15px;">
            <h1 style="font-size: 22px; font-weight: 800; color: #1a2332; text-transform: uppercase; margin: 0;">
                ' . htmlspecialchars($nomeEmpresa) . ' <span style="color: #c9a84c;">Web</span>
            </h1>
            <p style="font-size: 11px; color: #4a5568; margin: 2px 0;">
                ' . htmlspecialchars($nomeEmpresa) . ' | ' . htmlspecialchars($empresa['endereco'] ?? '') . '
            </p>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr 1fr; gap: 10px; margin: 15px 0; background: #f8fafc; padding: 10px; border-radius: 5px;">
            <div style="text-align: center;"><strong>Classe:</strong> ' . htmlspecialchars($classe) . '</div>
            <div style="text-align: center;"><strong>Turma:</strong> ' . htmlspecialchars($turma) . '</div>
            <div style="text-align: center;"><strong>Sala:</strong> ' . htmlspecialchars($sala) . '</div>
            <div style="text-align: center;"><strong>Período:</strong> ' . htmlspecialchars($periodo) . '</div>
            <div style="text-align: center;"><strong>Ano Lectivo:</strong> ' . $ano_letivo . '</div>
        </div>
        
        <div style="text-align: center; margin: 15px 0;">
            <h2 style="font-size: 16px; font-weight: 700; color: #1a2332; text-transform: uppercase; margin: 0;">LISTA NOMINAL DOS ALUNOS</h2>
            <p style="font-size: 11px; color: #94a3b8;">Relatório gerado em ' . $data_atual . '</p>
        </div>
        
        <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
            <thead>
                <tr>
                    <th style="background: #1a2332; color: white; padding: 6px 8px; border: 1px solid #1a2332; text-align: left;">Nº</th>
                    <th style="background: #1a2332; color: white; padding: 6px 8px; border: 1px solid #1a2332; text-align: left;">Nome do Aluno</th>
                    <th style="background: #1a2332; color: white; padding: 6px 8px; border: 1px solid #1a2332; text-align: left;">Sexo</th>
                    <th style="background: #1a2332; color: white; padding: 6px 8px; border: 1px solid #1a2332; text-align: left;">Idade</th>
                    <th style="background: #1a2332; color: white; padding: 6px 8px; border: 1px solid #1a2332; text-align: left;">Data Nasc.</th>';
    
    foreach($disciplinas_formatadas as $disc) {
        $output .= '<th style="background: #4a6baf; color: white; padding: 6px 8px; border: 1px solid #4a6baf; text-align: center; font-size: 9px;">' . htmlspecialchars($disc) . '</th>';
    }
    
    $output .= '
                </tr>
            </thead>
            <tbody>';
    
    $num = 1;
    foreach($alunos_lista as $aluno) {
        $sexo = $aluno['Sexo'] ?? 'M';
        $sexoClass = $sexo == 'M' ? 'M' : 'F';
        $data_nasc = (isset($aluno['dia']) && isset($aluno['mes']) && isset($aluno['Ano']) && $aluno['dia'] > 0) ? 
            sprintf("%02d/%02d/%04d", $aluno['dia'], $aluno['mes'], $aluno['Ano']) : '-';
        
        $output .= '
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 4px 8px;">' . $num . '</td>
                    <td style="padding: 4px 8px;">' . htmlspecialchars($aluno['nome']) . '</td>
                    <td style="padding: 4px 8px; text-align: center;">
                        <span style="display: inline-block; padding: 1px 8px; border-radius: 10px; font-size: 9px; font-weight: 600; background: ' . ($sexoClass == 'M' ? '#dbeafe' : '#fce7f3') . '; color: ' . ($sexoClass == 'M' ? '#1e40af' : '#9d174d') . ';">
                            ' . $sexoClass . '
                        </span>
                    </td>
                    <td style="padding: 4px 8px; text-align: center;">' . ($aluno['Idade'] ?? '-') . '</td>
                    <td style="padding: 4px 8px; text-align: center;">' . $data_nasc . '</td>';
        
        foreach($disciplinas_formatadas as $disc) {
            $output .= '<td style="padding: 4px 8px; text-align: center;"></td>';
        }
        
        $output .= '
                </tr>';
        $num++;
    }
    
    $output .= '
            </tbody>
        </table>
        
        <div style="text-align: center; margin-top: 15px; padding-top: 10px; border-top: 1px solid #e2e8f0; font-size: 10px; color: #94a3b8;">
            Total de alunos: <strong style="color: #1a2332;">' . $total . '</strong> | Sistema de Gestão Escolar ' . htmlspecialchars($nomeEmpresa) . '
        </div>
    </div>';
}

// ===== SALVAR HTML =====
$pasta = $_SERVER['DOCUMENT_ROOT'] . '/softgest_web/uploads/listas_nominais/';
if (!file_exists($pasta)) {
    mkdir($pasta, 0777, true);
}

$nome_arquivo = 'listas_nominais_' . date('Y-m-d_H-i-s') . '.html';
$caminho = $pasta . $nome_arquivo;

file_put_contents($caminho, '
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listas Nominais - ' . htmlspecialchars($nomeEmpresa) . '</title>
    <style>
        @page { size: landscape; margin: 10px; }
        body { font-family: Arial, sans-serif; background: white; }
        @media print { 
            body { margin: 0; padding: 0; }
            div[style*="page-break-after: always"] { page-break-after: always; }
        }
    </style>
</head>
<body>
' . $output . '
</body>
</html>
');

// ===== REDIRECIONAR =====
header('Content-Type: text/html');
header('Content-Disposition: attachment; filename="' . $nome_arquivo . '"');
readfile($caminho);
unlink($caminho);
exit;
?>