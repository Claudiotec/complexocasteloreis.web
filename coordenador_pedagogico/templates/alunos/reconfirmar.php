<?php
// ============================================
// modules/escola/alunos/reconfirmar.php - Reconfirmação de Matrícula
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

// ============================================
// FUNÇÃO PARA LIMPAR STRING - CORRIGIDA (MANTÉM ACENTOS)
// ============================================
function limparString($texto) {
    if (empty($texto)) return '';
    
    // Garantir que está em UTF-8
    if (!mb_check_encoding($texto, 'UTF-8')) {
        $texto = mb_convert_encoding($texto, 'UTF-8', 'auto');
    }
    
    // Remove apenas caracteres de controle (não remove acentos)
    $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $texto);
    
    // Remove caracteres de controle adicionais
    $texto = preg_replace('/[[:cntrl:]]/', '', $texto);
    
    // Normaliza espaços (mantém acentos)
    $texto = trim($texto);
    $texto = preg_replace('/\s+/', ' ', $texto);
    
    return $texto;
}

// ============================================
// FUNÇÃO PARA CORRIGIR NOMES CORROMPIDOS
// ============================================
function corrigirNomeCorrompido($nome) {
    if (empty($nome)) return '';
    
    // Mapeamento de caracteres corrompidos comuns
    $mapa_correcao = [
        // Substitui ? por caracteres acentuados comuns
        '?' => [
            'ç' => ['Concei', 'Concei?', 'Concei??'],
            'ã' => ['Ad', 'Ad?', 'Ad??', 'Irm', 'Irm?'],
            'õ' => ['Jo', 'Jo?', 'Le', 'Le?'],
            'á' => ['Jos', 'Jos?'],
            'é' => ['Andr', 'Andr?'],
            'í' => ['Lu', 'Lu?'],
            'ó' => ['Antn', 'Antn?'],
            'ú' => ['Jes', 'Jes?'],
            'â' => ['Joa', 'Joa?'],
            'ê' => ['Alfr', 'Alfr?'],
            'ô' => ['Nels', 'Nels?'],
            'à' => ['Gilb', 'Gilb?'],
        ]
    ];
    
    // Primeiro, tenta substituir padrões comuns
    $padroes = [
        'Concei??o' => 'Conceição',
        'Concei?o' => 'Conceição',
        'Ad?o' => 'Adão',
        'Ad??o' => 'Adão',
        'Irm?o' => 'Irmão',
        'Irm??o' => 'Irmão',
        'Jo?o' => 'João',
        'Jo??o' => 'João',
        'Le?o' => 'Leão',
        'Jos?' => 'José',
        'Andr?' => 'André',
        'Lu?s' => 'Luís',
        'Ant?nio' => 'António',
        'Ant??nio' => 'António',
        'Jes?s' => 'Jesus',
        'Joa?o' => 'João',
        'Alfr?do' => 'Alfredo',
        'Nels?o' => 'Nelsão',
        'Gilb?rto' => 'Gilberto',
    ];
    
    foreach ($padroes as $errado => $correto) {
        if (strpos($nome, $errado) !== false) {
            $nome = str_replace($errado, $correto, $nome);
        }
    }
    
    // Substitui ? e � isolados por tentativa de contexto
    if (strpos($nome, '?') !== false || strpos($nome, '�') !== false) {
        // Tenta identificar padrões baseados no contexto
        $nome = preg_replace('/\?/', '', $nome);
        $nome = preg_replace('/�/', '', $nome);
        
        // Tenta adivinhar a vogal correta baseado no contexto
        $contextos = [
            '/Ad(\s|$|,|\.)/i' => 'Adão',
            '/Concei/i' => 'Conceição',
            '/Irm/i' => 'Irmão',
            '/Jo/i' => 'João',
            '/Le/i' => 'Leão',
            '/Jos/i' => 'José',
            '/Andr/i' => 'André',
            '/Lu/i' => 'Luís',
            '/Antn/i' => 'António',
            '/Jes/i' => 'Jesus',
            '/Joa/i' => 'João',
            '/Alfr/i' => 'Alfredo',
            '/Nels/i' => 'Nelsão',
            '/Gilb/i' => 'Gilberto',
        ];
        
        foreach ($contextos as $padrao => $substituicao) {
            if (preg_match($padrao, $nome)) {
                // Substitui a parte corrompida pelo nome correto
                $nome = preg_replace($padrao, $substituicao, $nome);
                break;
            }
        }
    }
    
    return $nome;
}

// ============================================
// FUNÇÃO PARA NORMALIZAR CLASSE
// ============================================
function normalizarClasse($classe) {
    if (empty($classe)) return '';
    
    $classe = limparString($classe);
    $classe = trim($classe);
    $classe = str_replace(' ', '', $classe);
    
    // Normaliza "1?" para "1ª"
    $classe = str_replace('1?', '1ª', $classe);
    $classe = str_replace('2?', '2ª', $classe);
    $classe = str_replace('3?', '3ª', $classe);
    $classe = str_replace('4?', '4ª', $classe);
    $classe = str_replace('5?', '5ª', $classe);
    $classe = str_replace('6?', '6ª', $classe);
    $classe = str_replace('7?', '7ª', $classe);
    $classe = str_replace('8?', '8ª', $classe);
    $classe = str_replace('9?', '9ª', $classe);
    $classe = str_replace('10?', '10ª', $classe);
    $classe = str_replace('11?', '11ª', $classe);
    $classe = str_replace('12?', '12ª', $classe);
    
    if (preg_match('/^PRE/i', $classe) || preg_match('/^PRÉ/i', $classe)) {
        return '1ª';
    }
    
    if (preg_match('/^(\d+)ª/', $classe, $matches)) {
        $num = intval($matches[1]);
        if ($num >= 1 && $num <= 12) return $num . 'ª';
    }
    
    if (preg_match('/^(\d+)º/', $classe, $matches)) {
        $num = intval($matches[1]);
        if ($num >= 1 && $num <= 12) return $num . 'ª';
    }
    
    if (preg_match('/^(\d+)/', $classe, $matches)) {
        $num = intval($matches[1]);
        if ($num >= 1 && $num <= 12) return $num . 'ª';
    }
    
    if (preg_match('/^(\d+)$/', $classe, $matches)) {
        $num = intval($matches[1]);
        if ($num >= 1 && $num <= 12) return $num . 'ª';
    }
    
    return $classe;
}

// ============================================
// FUNÇÃO PARA EXTRAIR NÚMERO DA CLASSE
// ============================================
function extrairNumeroClasse($classe) {
    $classe_limpa = preg_replace('/[^0-9]/', '', $classe);
    return !empty($classe_limpa) ? intval($classe_limpa) : 1;
}

// ============================================
// FUNÇÃO PARA NORMALIZAR NOME DA TURMA
// ============================================
function normalizarNomeTurma($classe, $periodo) {
    $classe_limpa = preg_replace('/[^0-9]/', '', $classe);
    if (empty($classe_limpa)) {
        $classe_limpa = '1';
    }
    
    $periodo_abreviado = '';
    if (stripos($periodo, 'manh') !== false) {
        $periodo_abreviado = 'M';
    } elseif (stripos($periodo, 'tard') !== false) {
        $periodo_abreviado = 'T';
    } elseif (stripos($periodo, 'noit') !== false) {
        $periodo_abreviado = 'N';
    } else {
        $periodo_abreviado = 'M';
    }
    
    return $classe_limpa . $periodo_abreviado;
}

// ============================================
// FUNÇÃO PARA CALCULAR PRÓXIMA CLASSE (CORRIGIDA)
// ============================================
function calcularProximaClasse($classe_atual, $manter_classe = false) {
    // SE MANTIVER CLASSE, RETORNA A CLASSE ATUAL NORMALIZADA
    if ($manter_classe) {
        return normalizarClasse($classe_atual);
    }
    
    // PRÉ-ESCOLA DEVE AVANÇAR PARA 1ª (NÃO PARA 2ª)
    $CLASSES_PRE_DEFINIDAS = [
        'PRÉ' => '1ª',
        'PRE' => '1ª',
        'PreA' => '1ª',
        'PREB' => '1ª',
        '1ª' => '2ª',
        '2ª' => '3ª',
        '3ª' => '4ª',
        '4ª' => '5ª',
        '5ª' => '6ª',
        '6ª' => '7ª',
        '7ª' => '8ª',
        '8ª' => '9ª',
        '9ª' => '10ª',
        '10ª' => '11ª',
        '11ª' => '12ª',
        '12ª' => '12ª'
    ];
    
    $classe_normalizada = normalizarClasse($classe_atual);
    
    // Verificar se é PRÉ ou PRE
    if (strtoupper($classe_normalizada) == 'PRÉ' || strtoupper($classe_normalizada) == 'PRE' || $classe_normalizada == '1ª' && strtoupper($classe_atual) == 'PRE') {
        return '1ª';
    }
    
    if (isset($CLASSES_PRE_DEFINIDAS[$classe_normalizada])) {
        return $CLASSES_PRE_DEFINIDAS[$classe_normalizada];
    }
    
    if (preg_match('/^(\d+)ª/', $classe_normalizada, $matches)) {
        $num = intval($matches[1]);
        if ($num >= 1 && $num <= 11) {
            return ($num + 1) . 'ª';
        }
        if ($num == 12) {
            return '12ª';
        }
    }
    
    return $classe_normalizada;
}

// ============================================
// FUNÇÃO PARA NORMALIZAR PERÍODO
// ============================================
function normalizarPeriodo($periodo) {
    if (empty($periodo)) return 'Manhã';
    $periodo = limparString($periodo);
    $periodo = trim($periodo);
    
    if (stripos($periodo, 'manh') !== false) return 'Manhã';
    if (stripos($periodo, 'tard') !== false) return 'Tarde';
    if (stripos($periodo, 'noit') !== false) return 'Noite';
    return 'Manhã';
}

// ============================================
// FUNÇÃO PARA NORMALIZAR STATUS
// ============================================
function normalizarStatus($status) {
    if (empty($status)) return 'Pendente';
    $status = limparString($status);
    $status = strtolower($status);
    if ($status == 'pago' || $status == 'confirmado') return 'Pago';
    if ($status == 'pendente' || $status == 'pendent' || $status == 'nulo' || $status == 'null') return 'Pendente';
    if ($status == 'cancelado' || $status == 'cancel') return 'Cancelado';
    if ($status == 'isento') return 'Isento';
    return ucfirst($status);
}

// Mapeamento de meses em português para número
$MESES = [
    'Janeiro' => 1, 'Fevereiro' => 2, 'Março' => 3, 'Abril' => 4,
    'Maio' => 5, 'Junho' => 6, 'Julho' => 7, 'Agosto' => 8,
    'Setembro' => 9, 'Outubro' => 10, 'Novembro' => 11, 'Dezembro' => 12
];

// ============================================
// CARREGAR EMOLUMENTOS
// ============================================
$emolumentos = [];
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'emolumentos_anterior'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("SELECT * FROM emolumentos_anterior ORDER BY Classe, Descricao, Periodo");
        while ($row = $stmt->fetch()) {
            $classe = normalizarClasse($row['Classe']);
            $descricao = limparString($row['Descricao']);
            $periodo = normalizarPeriodo($row['Periodo']);
            $valor = floatval($row['Valor']);
            $key = $classe . '|' . $descricao . '|' . $periodo;
            $emolumentos[$key] = $valor;
        }
    }
} catch (Exception $e) {}

// ============================================
// FUNÇÃO PARA BUSCAR VALOR DO EMOLUMENTO
// ============================================
function buscarValorEmolumento($classe, $descricao, $periodo, $emolumentos) {
    $classe = normalizarClasse($classe);
    $periodo = normalizarPeriodo($periodo);
    $descricao = limparString($descricao);
    
    $mapa_descricao = [
        'folha de prova' => 'Folha de Prova',
        'boletim de notas' => 'Boletim de Notas',
        'cartão' => 'Cartão',
        'propina' => 'Propina',
        'transporte' => 'Transporte'
    ];
    
    $desc_lower = strtolower($descricao);
    $desc_normalizada = $descricao;
    
    foreach ($mapa_descricao as $key => $value) {
        if (strpos($desc_lower, strtolower($key)) !== false) {
            $desc_normalizada = $value;
            break;
        }
    }
    
    $keys_to_try = [
        $classe . '|' . $desc_normalizada . '|' . $periodo,
        $classe . '|' . $desc_normalizada . '|Manhã',
        $classe . '|' . $descricao . '|' . $periodo,
        $classe . '|' . $descricao . '|Manhã'
    ];
    
    foreach ($keys_to_try as $key) {
        if (isset($emolumentos[$key])) {
            return $emolumentos[$key];
        }
    }
    return 0;
}

// ============================================
// FUNÇÃO PARA VERIFICAR PENDÊNCIAS
// ============================================
function verificarPendencias($aluno_id, $pdo, $emolumentos) {
    $pendencias = [];
    $total_pendente = 0;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM pendencias_anterior WHERE id = ?");
        $stmt->execute([$aluno_id]);
        $pendencia = $stmt->fetch();
        
        if (!$pendencia) {
            return [
                'tem_pendencias' => false,
                'pendencias' => [],
                'total_pendente' => 0
            ];
        }
        
        $classe = normalizarClasse($pendencia['classe'] ?? '');
        $periodo = normalizarPeriodo($pendencia['periodo'] ?? 'Manhã');
        
        // --- PROPINA ---
        $status_propina = normalizarStatus($pendencia['propina_status'] ?? 'Pendente');
        $qtd_propina = intval($pendencia['propina_quantidade'] ?? 0);
        if ($status_propina != 'Pago' && $qtd_propina > 0) {
            $valor_unitario = buscarValorEmolumento($classe, 'Propina', $periodo, $emolumentos);
            $valor = $valor_unitario * $qtd_propina;
            if ($valor > 0) {
                $pendencias[] = [
                    'item' => 'Propina',
                    'quantidade' => $qtd_propina,
                    'valor_unitario' => $valor_unitario,
                    'valor_total' => $valor,
                    'status' => $status_propina
                ];
                $total_pendente += $valor;
            }
        }
        
        // --- TRANSPORTE ---
        $status_transporte = normalizarStatus($pendencia['transporte_status'] ?? 'Pendente');
        $qtd_transporte = intval($pendencia['transporte_quantidade'] ?? 0);
        if ($status_transporte != 'Pago' && $qtd_transporte > 0) {
            $valor_unitario = buscarValorEmolumento($classe, 'Transporte', $periodo, $emolumentos);
            $valor = $valor_unitario * $qtd_transporte;
            if ($valor > 0) {
                $pendencias[] = [
                    'item' => 'Transporte',
                    'quantidade' => $qtd_transporte,
                    'valor_unitario' => $valor_unitario,
                    'valor_total' => $valor,
                    'status' => $status_transporte
                ];
                $total_pendente += $valor;
            }
        }
        
        // --- FOLHA DE PROVA 1º TRIMESTRE ---
        $status_prova1 = normalizarStatus($pendencia['folha_prova_1_status'] ?? 'Pendente');
        if ($status_prova1 != 'Pago') {
            $valor = buscarValorEmolumento($classe, 'Folha de Prova', $periodo, $emolumentos);
            if ($valor > 0) {
                $pendencias[] = [
                    'item' => '1º Prova',
                    'quantidade' => 1,
                    'valor_unitario' => $valor,
                    'valor_total' => $valor,
                    'status' => $status_prova1
                ];
                $total_pendente += $valor;
            }
        }
        
        // --- FOLHA DE PROVA 2º TRIMESTRE ---
        $status_prova2 = normalizarStatus($pendencia['folha_prova_2_status'] ?? 'Pendente');
        if ($status_prova2 != 'Pago') {
            $valor = buscarValorEmolumento($classe, 'Folha de Prova', $periodo, $emolumentos);
            if ($valor > 0) {
                $pendencias[] = [
                    'item' => '2º Prova',
                    'quantidade' => 1,
                    'valor_unitario' => $valor,
                    'valor_total' => $valor,
                    'status' => $status_prova2
                ];
                $total_pendente += $valor;
            }
        }
        
        // --- FOLHA DE PROVA 3º TRIMESTRE ---
        $status_prova3 = normalizarStatus($pendencia['folha_prova_3_status'] ?? 'Pendente');
        if ($status_prova3 != 'Pago') {
            $valor = buscarValorEmolumento($classe, 'Folha de Prova', $periodo, $emolumentos);
            if ($valor > 0) {
                $pendencias[] = [
                    'item' => '3º Prova',
                    'quantidade' => 1,
                    'valor_unitario' => $valor,
                    'valor_total' => $valor,
                    'status' => $status_prova3
                ];
                $total_pendente += $valor;
            }
        }
        
        // --- BOLETIM 1º TRIMESTRE ---
        $status_boletim1 = normalizarStatus($pendencia['boletim_notas_1_status'] ?? 'Pendente');
        if ($status_boletim1 != 'Pago') {
            $valor = buscarValorEmolumento($classe, 'Boletim de Notas', $periodo, $emolumentos);
            if ($valor > 0) {
                $pendencias[] = [
                    'item' => '1º Boletim',
                    'quantidade' => 1,
                    'valor_unitario' => $valor,
                    'valor_total' => $valor,
                    'status' => $status_boletim1
                ];
                $total_pendente += $valor;
            }
        }
        
        // --- BOLETIM 2º TRIMESTRE ---
        $status_boletim2 = normalizarStatus($pendencia['boletim_notas_2_status'] ?? 'Pendente');
        if ($status_boletim2 != 'Pago') {
            $valor = buscarValorEmolumento($classe, 'Boletim de Notas', $periodo, $emolumentos);
            if ($valor > 0) {
                $pendencias[] = [
                    'item' => '2º Boletim',
                    'quantidade' => 1,
                    'valor_unitario' => $valor,
                    'valor_total' => $valor,
                    'status' => $status_boletim2
                ];
                $total_pendente += $valor;
            }
        }
        
        // --- CARTÃO ---
        $status_cartao = normalizarStatus($pendencia['cartao_status'] ?? 'Pendente');
        if ($status_cartao != 'Pago') {
            $valor = buscarValorEmolumento($classe, 'Cartão', $periodo, $emolumentos);
            if ($valor > 0) {
                $pendencias[] = [
                    'item' => 'Cartão',
                    'quantidade' => 1,
                    'valor_unitario' => $valor,
                    'valor_total' => $valor,
                    'status' => $status_cartao
                ];
                $total_pendente += $valor;
            }
        }
        
    } catch (Exception $e) {
        return [
            'tem_pendencias' => false,
            'pendencias' => [],
            'total_pendente' => 0
        ];
    }
    
    return [
        'tem_pendencias' => $total_pendente > 0,
        'pendencias' => $pendencias,
        'total_pendente' => $total_pendente
    ];
}

// ============================================
// FUNÇÃO PARA GERAR NÚMERO DA FATURA
// ============================================
function gerarNumeroFatura($pdo) {
    $ano = date('Y');
    $mes = date('m');
    
    try {
        $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(numero_fatura, 9) AS UNSIGNED)) as ultimo FROM faturas_anteriores WHERE numero_fatura LIKE 'FAT-$ano$mes%'");
        $row = $stmt->fetch();
        $num = ($row && $row['ultimo']) ? intval($row['ultimo']) + 1 : 1;
        return 'FAT-' . $ano . $mes . str_pad($num, 4, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        return 'FAT-' . $ano . $mes . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
}

// ============================================
// FUNÇÃO PARA BUSCAR TURMA (CORRIGIDA)
// ============================================
function buscarTurma($pdo, $classe, $periodo) {
    try {
        $classe_normalizada = normalizarClasse($classe);
        $periodo_normalizado = normalizarPeriodo($periodo);
        $classe_num = extrairNumeroClasse($classe_normalizada);
        
        $periodo_abreviado = ($periodo_normalizado == 'Manhã') ? 'M' : 
                             (($periodo_normalizado == 'Tarde') ? 'T' : 'N');
        
        $possiveis_nomes = [
            $classe_num . $periodo_abreviado,
            $classe_num . 'A' . $periodo_abreviado,
            $classe_num . 'B' . $periodo_abreviado,
            $classe_num . $periodo_abreviado . 'A',
            $classe_num . $periodo_abreviado . 'B',
        ];
        
        foreach ($possiveis_nomes as $nome_turma) {
            $stmt = $pdo->prepare("SELECT * FROM turmas WHERE nome = ? AND status = 'Ativa' LIMIT 1");
            $stmt->execute([$nome_turma]);
            $turma = $stmt->fetch();
            if ($turma) return $turma;
        }
        
        $stmt = $pdo->prepare("SELECT * FROM turmas WHERE classe = ? AND turno = ? AND status = 'Ativa' LIMIT 1");
        $stmt->execute([$classe_normalizada, $periodo_normalizado]);
        $turma = $stmt->fetch();
        if ($turma) return $turma;
        
        $stmt = $pdo->prepare("SELECT * FROM turmas WHERE classe = ? AND status = 'Ativa' LIMIT 1");
        $stmt->execute([$classe_normalizada]);
        $turma = $stmt->fetch();
        if ($turma) return $turma;
        
        $stmt = $pdo->prepare("SELECT * FROM turmas WHERE nome LIKE ? AND status = 'Ativa' LIMIT 1");
        $stmt->execute(['%' . $classe_num . '%']);
        $turma = $stmt->fetch();
        if ($turma) return $turma;
        
        $stmt = $pdo->prepare("SELECT * FROM turmas WHERE status = 'Ativa' LIMIT 1");
        $stmt->execute();
        return $stmt->fetch() ?: null;
        
    } catch (Exception $e) {
        return null;
    }
}

// ============================================
// FUNÇÃO PARA CALCULAR IDADE
// ============================================
function calcularIdade($dia, $mes, $ano) {
    if (empty($dia) || empty($mes) || empty($ano)) {
        return null;
    }
    
    global $MESES;
    $dia = limparString($dia);
    $mes = limparString($mes);
    $ano = limparString($ano);
    
    if (isset($MESES[$mes])) {
        $mes_num = $MESES[$mes];
    } else {
        $mes_num = intval($mes);
    }
    
    if ($mes_num < 1 || $mes_num > 12) {
        return null;
    }
    
    $data_nascimento = $ano . '-' . str_pad($mes_num, 2, '0', STR_PAD_LEFT) . '-' . str_pad($dia, 2, '0', STR_PAD_LEFT);
    
    try {
        $nascimento = new DateTime($data_nascimento);
        $hoje = new DateTime();
        $idade = $hoje->diff($nascimento);
        return $idade->y;
    } catch (Exception $e) {
        return null;
    }
}

// ============================================
// PROCESSAR PAGAMENTO
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pagar_pendencia']) && isset($_POST['aluno_id'])) {
    $aluno_id = intval($_POST['aluno_id']);
    $forma_pagamento = isset($_POST['forma_pagamento']) ? limparString($_POST['forma_pagamento']) : 'dinheiro';
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT * FROM alunos_ano_anterior WHERE id = ?");
        $stmt->execute([$aluno_id]);
        $aluno = $stmt->fetch();
        
        if (!$aluno) {
            throw new Exception('Aluno não encontrado!');
        }
        
        $verificacao = verificarPendencias($aluno_id, $pdo, $emolumentos);
        
        if (!$verificacao['tem_pendencias']) {
            throw new Exception('Este aluno não possui pendências.');
        }
        
        $numero_fatura = gerarNumeroFatura($pdo);
        $total_pago = $verificacao['total_pendente'];
        $classe_aluno = normalizarClasse($aluno['Classe'] ?? '');
        $periodo_aluno = normalizarPeriodo($aluno['Periodo'] ?? 'Manhã');
        
        $itens_json = [];
        $itens_descricao = [];
        
        foreach ($verificacao['pendencias'] as $p) {
            $itens_json[] = [
                'item' => $p['item'],
                'quantidade' => $p['quantidade'],
                'valor_unitario' => $p['valor_unitario'],
                'valor_total' => $p['valor_total'],
                'status' => $p['status']
            ];
            $itens_descricao[] = $p['item'] . " (" . $p['quantidade'] . "x " . number_format($p['valor_unitario'], 2, ',', '.') . " Kz)";
        }
        
        $descricao_completa = "Pagamento de pendências do aluno " . limparString($aluno['nome']);
        if (!empty($itens_descricao)) {
            $descricao_completa .= " - " . implode(" | ", $itens_descricao);
        }
        if (strlen($descricao_completa) > 500) {
            $descricao_completa = substr($descricao_completa, 0, 497) . '...';
        }
        
        $stmt_fatura = $pdo->prepare("
            INSERT INTO faturas_anteriores (
                aluno_id,
                aluno_nome,
                classe,
                periodo,
                numero_fatura,
                descricao,
                valor_total,
                forma_pagamento,
                status,
                data_pagamento,
                usuario_id,
                usuario_nome,
                itens_detalhados
            ) VALUES (
                :aluno_id,
                :aluno_nome,
                :classe,
                :periodo,
                :numero_fatura,
                :descricao,
                :valor_total,
                :forma_pagamento,
                :status,
                NOW(),
                :usuario_id,
                :usuario_nome,
                :itens_detalhados
            )
        ");
        
        $stmt_fatura->execute([
            ':aluno_id' => $aluno_id,
            ':aluno_nome' => limparString($aluno['nome']),
            ':classe' => $classe_aluno,
            ':periodo' => $periodo_aluno,
            ':numero_fatura' => $numero_fatura,
            ':descricao' => $descricao_completa,
            ':valor_total' => $total_pago,
            ':forma_pagamento' => $forma_pagamento,
            ':status' => 'pago',
            ':usuario_id' => $_SESSION['usuario_id'],
            ':usuario_nome' => $_SESSION['usuario_nome'] ?? 'Administrador',
            ':itens_detalhados' => json_encode($itens_json, JSON_UNESCAPED_UNICODE)
        ]);
        
        $fatura_id = $pdo->lastInsertId();
        
        $stmt_update = $pdo->prepare("
            UPDATE pendencias_anterior SET 
                propina_status = 'pago',
                transporte_status = 'pago',
                folha_prova_1_status = 'pago',
                folha_prova_2_status = 'pago',
                folha_prova_3_status = 'pago',
                boletim_notas_1_status = 'pago',
                boletim_notas_2_status = 'pago',
                cartao_status = 'pago'
            WHERE id = ?
        ");
        $stmt_update->execute([$aluno_id]);
        
        $pdo->commit();
        
        header('Location: visualizar_fatura.php?id=' . $fatura_id . '&inserido=1');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $erro = 'Erro ao processar pagamento: ' . $e->getMessage();
    }
}

$erro = '';
$sucesso = '';
$alunos = [];
$classes_disponiveis = [];
$ano_letivo_atual = date('Y');

// ============================================
// FILTROS DE BUSCA
// ============================================
$busca = isset($_GET['busca']) ? limparString($_GET['busca']) : '';
$filtro_classe = isset($_GET['filtro_classe']) ? limparString($_GET['filtro_classe']) : '';
$filtro_status = isset($_GET['filtro_status']) ? limparString($_GET['filtro_status']) : '';
$manter_classe = isset($_GET['manter_classe']) && $_GET['manter_classe'] == '1';

// ============================================
// BUSCAR ALUNOS
// ============================================
try {
    $tabela_existe = $pdo->query("SHOW TABLES LIKE 'alunos_ano_anterior'")->rowCount() > 0;
    
    if ($tabela_existe) {
        $where_conditions = [];
        
        if (!empty($busca)) {
            $where_conditions[] = "(nome LIKE '%$busca%' OR N_BI LIKE '%$busca%' OR id LIKE '%$busca%')";
        }
        
        if (!empty($filtro_classe)) {
            $where_conditions[] = "Classe = '$filtro_classe'";
        }
        
        if (!empty($filtro_status)) {
            $where_conditions[] = "Situacao_Cadastro = '$filtro_status'";
        }
        
        $where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $sql = "SELECT * FROM alunos_ano_anterior $where_sql ORDER BY Classe, nome";
        $stmt = $pdo->query($sql);
        $alunos = $stmt->fetchAll();
        
        $stmt_classes = $pdo->query("SELECT DISTINCT Classe FROM alunos_ano_anterior WHERE Classe IS NOT NULL AND Classe != '' ORDER BY Classe");
        $classes_disponiveis = $stmt_classes->fetchAll(PDO::FETCH_COLUMN);
        
    } else {
        $erro = 'Tabela alunos_ano_anterior não encontrada!';
        $classes_disponiveis = [];
    }
} catch (Exception $e) {
    $erro = 'Erro ao buscar alunos: ' . $e->getMessage();
    $classes_disponiveis = [];
}

// ============================================
// PROCESSAR RECONFIRMAÇÃO INDIVIDUAL
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['aluno_id']) && !isset($_POST['reconfirmar_todos']) && !isset($_POST['pagar_pendencia'])) {
    $aluno_id = $_POST['aluno_id'];
    $manter_classe_post = isset($_POST['manter_classe']) && $_POST['manter_classe'] == '1';
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT * FROM alunos_ano_anterior WHERE id = ?");
        $stmt->execute([$aluno_id]);
        $aluno = $stmt->fetch();
        
        if (!$aluno) {
            $erro = 'Aluno não encontrado!';
            $pdo->rollBack();
        } else {
            $verificacao = verificarPendencias($aluno_id, $pdo, $emolumentos);
            
            if ($verificacao['tem_pendencias']) {
                $pdo->rollBack();
                
                $msg_pendencias = "<strong>❌ Não é possível reconfirmar!</strong><br><br>";
                $msg_pendencias .= "O aluno <strong>" . htmlspecialchars(limparString($aluno['nome']), ENT_QUOTES, 'UTF-8') . "</strong> possui pendências financeiras.<br><br>";
                $msg_pendencias .= "Clique em <strong>💰 Pagar</strong> para liquidar as pendências.<br><br>";
                $msg_pendencias .= "<strong style='color:#e74c3c;font-size:16px;'>Total a pagar: " . number_format($verificacao['total_pendente'], 2, ',', '.') . " Kz</strong>";
                
                $erro = $msg_pendencias;
                
            } else {
                $idade = calcularIdade($aluno['dia'], $aluno['mes'], $aluno['Ano']);
                $classe_atual = $aluno['Classe'] ?? '';
                
                // CALCULA A CLASSE (MANTENDO OU AVANÇANDO)
                $nova_classe = calcularProximaClasse($classe_atual, $manter_classe_post);
                
                // SE AINDA ASSIM A CLASSE ESTIVER VAZIA, USA A ATUAL
                if (empty($nova_classe)) {
                    $nova_classe = $classe_atual;
                }
                
                $periodo = normalizarPeriodo($aluno['Periodo'] ?? 'Manhã');
                $turma = buscarTurma($pdo, $nova_classe, $periodo);
                
                if ($turma) {
                    $turma_nome = $turma['nome'] ?? '';
                    $sala_nome = $turma['sala'] ?? '';
                    if (!empty($turma['turno'])) {
                        $periodo = $turma['turno'];
                    }
                } else {
                    $turma_nome = normalizarNomeTurma($nova_classe, $periodo);
                    $sala_nome = '';
                }
                
                $turma_nome = preg_replace('/[^0-9A-Z]/', '', strtoupper($turma_nome));
                
                // CORRIGE O NOME DO ALUNO ANTES DE INSERIR
                $nome_corrigido = corrigirNomeCorrompido(limparString($aluno['nome']));
                
                // ============================================
                // VERIFICAR SE O ID JÁ EXISTE NA TABELA alunos
                // ============================================
                $stmt_check = $pdo->prepare("SELECT id FROM alunos WHERE id = ?");
                $stmt_check->execute([$aluno['id']]);
                $id_existe = $stmt_check->rowCount() > 0;
                
                if ($id_existe) {
                    // Se o ID já existe, usa o próximo ID disponível
                    $stmt_max = $pdo->query("SELECT MAX(id) as max_id FROM alunos");
                    $max = $stmt_max->fetch();
                    $novo_id = $max['max_id'] + 1;
                } else {
                    // Usa o ID original
                    $novo_id = $aluno['id'];
                }
                
                // ============================================
                // INSERIR COM O ID (MANTENDO O ORIGINAL SE POSSÍVEL)
                // ============================================
                $sql_insert = "
                    INSERT INTO alunos (
                        id,
                        nome, Sexo, dia, mes, Ano, Morada, Cadastro_Transporte,
                        Contacto_do_Aluno, Debilidade, Idade, Naturalidade,
                        Municipio, Provincia, N_BI, Classe, Nome_do_Pai,
                        Morada3, Contacto4, Ocupacao, Local_de_Trabalho,
                        Nome_da_mae, Contacto_Mae, Data_Matricula,
                        Ocupacao_do_Aluno, Periodo, Data_Emissao_do_BI,
                        Arq_identificacao, Situacao_Cadastro, TURMA, SALA,
                        Curso
                    ) VALUES (
                        :id,
                        :nome, :Sexo, :dia, :mes, :Ano, :Morada, :Cadastro_Transporte,
                        :Contacto_do_Aluno, :Debilidade, :Idade, :Naturalidade,
                        :Municipio, :Provincia, :N_BI, :Classe, :Nome_do_Pai,
                        :Morada3, :Contacto4, :Ocupacao, :Local_de_Trabalho,
                        :Nome_da_mae, :Contacto_Mae, NOW(),
                        :Ocupacao_do_Aluno, :Periodo, :Data_Emissao_do_BI,
                        :Arq_identificacao, 'Confirmação', :TURMA, :SALA,
                        :Curso
                    )
                ";
                
                $stmt_insert = $pdo->prepare($sql_insert);
                $stmt_insert->execute([
                    ':id' => $novo_id,
                    ':nome' => $nome_corrigido,
                    ':Sexo' => $aluno['Sexo'],
                    ':dia' => $aluno['dia'],
                    ':mes' => $aluno['mes'],
                    ':Ano' => $aluno['Ano'],
                    ':Morada' => $aluno['Morada'],
                    ':Cadastro_Transporte' => $aluno['Cadastro_Transporte'] ?? 'Não',
                    ':Contacto_do_Aluno' => $aluno['Contacto_do_Aluno'] ?? '',
                    ':Debilidade' => $aluno['Debilidade'] ?? 'Nenhuma',
                    ':Idade' => $idade,
                    ':Naturalidade' => $aluno['Naturalidade'] ?? '',
                    ':Municipio' => $aluno['Município'] ?? '',
                    ':Provincia' => $aluno['Província'] ?? '',
                    ':N_BI' => $aluno['N_BI'] ?? '',
                    ':Classe' => $nova_classe,
                    ':Nome_do_Pai' => $aluno['Nome_do_Pai'] ?? '',
                    ':Morada3' => $aluno['Morada3'] ?? '',
                    ':Contacto4' => $aluno['Contacto4'] ?? '',
                    ':Ocupacao' => $aluno['Ocupacao'] ?? '',
                    ':Local_de_Trabalho' => $aluno['Local_de_Trabalho'] ?? '',
                    ':Nome_da_mae' => $aluno['Nome_da_mae'] ?? '',
                    ':Contacto_Mae' => $aluno['Contacto_Mae'] ?? '',
                    ':Ocupacao_do_Aluno' => $aluno['Ocupacao_do_Aluno'] ?? '',
                    ':Periodo' => $periodo,
                    ':Data_Emissao_do_BI' => $aluno['Data_Emissao_do_BI'] ?? null,
                    ':Arq_identificacao' => $aluno['Arq_identificação'] ?? '',
                    ':TURMA' => $turma_nome,
                    ':SALA' => $sala_nome,
                    ':Curso' => $aluno['Curso'] ?? ''
                ]);
                
                // ============================================
                // LOG PARA DEBUG
                // ============================================
                error_log("Aluno reconfirmado: ID Original = {$aluno['id']} -> ID Novo = $novo_id, Nome = $nome_corrigido");
                
                // DELETE DO ALUNO ANTIGO
                $stmt_delete = $pdo->prepare("DELETE FROM alunos_ano_anterior WHERE id = ?");
                $stmt_delete->execute([$aluno_id]);
                
                $pdo->commit();
                
                $sucesso = "✅ Aluno <strong>" . htmlspecialchars($nome_corrigido, ENT_QUOTES, 'UTF-8') . "</strong> reconfirmado!<br>";
                if ($novo_id == $aluno['id']) {
                    $sucesso .= "🆔 ID mantido: <strong>$novo_id</strong><br>";
                } else {
                    $sucesso .= "🆔 ID Original: <strong>{$aluno['id']}</strong> → Novo ID: <strong>$novo_id</strong> (conflito de ID)<br>";
                }
                if ($manter_classe_post) {
                    $sucesso .= "🔒 Classe mantida: <strong>" . htmlspecialchars($classe_atual, ENT_QUOTES, 'UTF-8') . "</strong><br>";
                } else {
                    $sucesso .= "📚 <strong>" . htmlspecialchars($classe_atual, ENT_QUOTES, 'UTF-8') . "</strong> → <strong>" . htmlspecialchars($nova_classe, ENT_QUOTES, 'UTF-8') . "</strong><br>";
                }
                $sucesso .= "🏫 Turma: <strong>" . htmlspecialchars($turma_nome, ENT_QUOTES, 'UTF-8') . "</strong> " . ($idade !== null ? "| Idade: $idade anos." : "");
                
                // Recarregar lista
                $where_conditions = [];
                if (!empty($busca)) {
                    $where_conditions[] = "(nome LIKE '%$busca%' OR N_BI LIKE '%$busca%' OR id LIKE '%$busca%')";
                }
                if (!empty($filtro_classe)) {
                    $where_conditions[] = "Classe = '$filtro_classe'";
                }
                if (!empty($filtro_status)) {
                    $where_conditions[] = "Situacao_Cadastro = '$filtro_status'";
                }
                $where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
                $sql = "SELECT * FROM alunos_ano_anterior $where_sql ORDER BY Classe, nome";
                $stmt = $pdo->query($sql);
                $alunos = $stmt->fetchAll();
            }
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $erro = 'Erro ao reconfirmar: ' . $e->getMessage();
        error_log("Erro na reconfirmação: " . $e->getMessage());
    }
}

// ============================================
// PROCESSAR RECONFIRMAÇÃO EM MASSA
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reconfirmar_todos']) && !isset($_POST['pagar_pendencia'])) {
    $reconfirmados = 0;
    $erros = 0;
    $bloqueados = 0;
    $mensagens_bloqueio = [];
    $detalhes_reconfirmados = [];
    $manter_classe_massa = isset($_POST['manter_classe_massa']) && $_POST['manter_classe_massa'] == '1';
    
    try {
        $pdo->beginTransaction();
        
        $where_conditions = [];
        if (!empty($busca)) {
            $where_conditions[] = "(nome LIKE '%$busca%' OR N_BI LIKE '%$busca%' OR id LIKE '%$busca%')";
        }
        if (!empty($filtro_classe)) {
            $where_conditions[] = "Classe = '$filtro_classe'";
        }
        if (!empty($filtro_status)) {
            $where_conditions[] = "Situacao_Cadastro = '$filtro_status'";
        }
        $where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $sql = "SELECT * FROM alunos_ano_anterior $where_sql";
        $stmt = $pdo->query($sql);
        $alunos_lista = $stmt->fetchAll();
        
        foreach ($alunos_lista as $aluno) {
            $verificacao = verificarPendencias($aluno['id'], $pdo, $emolumentos);
            
            if ($verificacao['tem_pendencias']) {
                $bloqueados++;
                $mensagens_bloqueio[] = "<strong>" . htmlspecialchars(limparString($aluno['nome']), ENT_QUOTES, 'UTF-8') . "</strong> - Pendência: " . number_format($verificacao['total_pendente'], 2, ',', '.') . " Kz";
                continue;
            }
            
            try {
                $idade = calcularIdade($aluno['dia'], $aluno['mes'], $aluno['Ano']);
                $classe_atual = $aluno['Classe'] ?? '';
                $nova_classe = calcularProximaClasse($classe_atual, $manter_classe_massa);
                
                if (empty($nova_classe)) {
                    $nova_classe = $classe_atual;
                }
                
                $periodo = normalizarPeriodo($aluno['Periodo'] ?? 'Manhã');
                $turma = buscarTurma($pdo, $nova_classe, $periodo);
                
                if ($turma) {
                    $turma_nome = $turma['nome'] ?? '';
                    $sala_nome = $turma['sala'] ?? '';
                    if (!empty($turma['turno'])) {
                        $periodo = $turma['turno'];
                    }
                } else {
                    $turma_nome = normalizarNomeTurma($nova_classe, $periodo);
                    $sala_nome = '';
                }
                
                $turma_nome = preg_replace('/[^0-9A-Z]/', '', strtoupper($turma_nome));
                
                // CORRIGE O NOME DO ALUNO ANTES DE INSERIR
                $nome_corrigido = corrigirNomeCorrompido(limparString($aluno['nome']));
                
                // ============================================
                // VERIFICAR SE O ID JÁ EXISTE NA TABELA alunos
                // ============================================
                $stmt_check = $pdo->prepare("SELECT id FROM alunos WHERE id = ?");
                $stmt_check->execute([$aluno['id']]);
                $id_existe = $stmt_check->rowCount() > 0;
                
                if ($id_existe) {
                    // Se o ID já existe, usa o próximo ID disponível
                    $stmt_max = $pdo->query("SELECT MAX(id) as max_id FROM alunos");
                    $max = $stmt_max->fetch();
                    $novo_id = $max['max_id'] + 1;
                } else {
                    // Usa o ID original
                    $novo_id = $aluno['id'];
                }
                
                // ============================================
                // INSERIR COM O ID (MANTENDO O ORIGINAL SE POSSÍVEL)
                // ============================================
                $sql_insert = "
                    INSERT INTO alunos (
                        id,
                        nome, Sexo, dia, mes, Ano, Morada, Cadastro_Transporte,
                        Contacto_do_Aluno, Debilidade, Idade, Naturalidade,
                        Municipio, Provincia, N_BI, Classe, Nome_do_Pai,
                        Morada3, Contacto4, Ocupacao, Local_de_Trabalho,
                        Nome_da_mae, Contacto_Mae, Data_Matricula,
                        Ocupacao_do_Aluno, Periodo, Data_Emissao_do_BI,
                        Arq_identificacao, Situacao_Cadastro, TURMA, SALA,
                        Curso
                    ) VALUES (
                        :id,
                        :nome, :Sexo, :dia, :mes, :Ano, :Morada, :Cadastro_Transporte,
                        :Contacto_do_Aluno, :Debilidade, :Idade, :Naturalidade,
                        :Municipio, :Provincia, :N_BI, :Classe, :Nome_do_Pai,
                        :Morada3, :Contacto4, :Ocupacao, :Local_de_Trabalho,
                        :Nome_da_mae, :Contacto_Mae, NOW(),
                        :Ocupacao_do_Aluno, :Periodo, :Data_Emissao_do_BI,
                        :Arq_identificacao, 'Confirmação', :TURMA, :SALA,
                        :Curso
                    )
                ";
                
                $stmt_insert = $pdo->prepare($sql_insert);
                $stmt_insert->execute([
                    ':id' => $novo_id,
                    ':nome' => $nome_corrigido,
                    ':Sexo' => $aluno['Sexo'],
                    ':dia' => $aluno['dia'],
                    ':mes' => $aluno['mes'],
                    ':Ano' => $aluno['Ano'],
                    ':Morada' => $aluno['Morada'],
                    ':Cadastro_Transporte' => $aluno['Cadastro_Transporte'] ?? 'Não',
                    ':Contacto_do_Aluno' => $aluno['Contacto_do_Aluno'] ?? '',
                    ':Debilidade' => $aluno['Debilidade'] ?? 'Nenhuma',
                    ':Idade' => $idade,
                    ':Naturalidade' => $aluno['Naturalidade'] ?? '',
                    ':Municipio' => $aluno['Município'] ?? '',
                    ':Provincia' => $aluno['Província'] ?? '',
                    ':N_BI' => $aluno['N_BI'] ?? '',
                    ':Classe' => $nova_classe,
                    ':Nome_do_Pai' => $aluno['Nome_do_Pai'] ?? '',
                    ':Morada3' => $aluno['Morada3'] ?? '',
                    ':Contacto4' => $aluno['Contacto4'] ?? '',
                    ':Ocupacao' => $aluno['Ocupacao'] ?? '',
                    ':Local_de_Trabalho' => $aluno['Local_de_Trabalho'] ?? '',
                    ':Nome_da_mae' => $aluno['Nome_da_mae'] ?? '',
                    ':Contacto_Mae' => $aluno['Contacto_Mae'] ?? '',
                    ':Ocupacao_do_Aluno' => $aluno['Ocupacao_do_Aluno'] ?? '',
                    ':Periodo' => $periodo,
                    ':Data_Emissao_do_BI' => $aluno['Data_Emissao_do_BI'] ?? null,
                    ':Arq_identificacao' => $aluno['Arq_identificação'] ?? '',
                    ':TURMA' => $turma_nome,
                    ':SALA' => $sala_nome,
                    ':Curso' => $aluno['Curso'] ?? ''
                ]);
                
                $stmt_delete = $pdo->prepare("DELETE FROM alunos_ano_anterior WHERE id = ?");
                $stmt_delete->execute([$aluno['id']]);
                $reconfirmados++;
                
                $detalhes_reconfirmados[] = htmlspecialchars($nome_corrigido, ENT_QUOTES, 'UTF-8') . 
                    " (ID: $novo_id" . ($novo_id != $aluno['id'] ? " antigo: {$aluno['id']}" : "") . " | " . 
                    htmlspecialchars($classe_atual, ENT_QUOTES, 'UTF-8') . " → " . 
                    htmlspecialchars($nova_classe, ENT_QUOTES, 'UTF-8') . " - " . 
                    htmlspecialchars($turma_nome, ENT_QUOTES, 'UTF-8') . ")";
                
            } catch (Exception $e) {
                $erros++;
                error_log("Erro ao reconfirmar aluno ID {$aluno['id']}: " . $e->getMessage());
            }
        }
        
        $pdo->commit();
        
        $msg = [];
        if ($reconfirmados > 0) {
            $msg[] = "✅ <strong>$reconfirmados alunos reconfirmados com sucesso!</strong>";
            if ($manter_classe_massa) {
                $msg[] = "🔒 <strong>Classe mantida</strong> para todos os alunos.";
            }
            if (count($detalhes_reconfirmados) <= 10) {
                $msg[] = "<ul style='margin:10px 0 0 20px;'>";
                foreach ($detalhes_reconfirmados as $det) {
                    $msg[] = "<li>" . $det . "</li>";
                }
                $msg[] = "</ul>";
            } else {
                $msg[] = "<ul style='margin:10px 0 0 20px;'>";
                for ($i = 0; $i < 5; $i++) {
                    $msg[] = "<li>" . $detalhes_reconfirmados[$i] . "</li>";
                }
                $msg[] = "<li>... e mais " . ($reconfirmados - 5) . " alunos</li>";
                $msg[] = "</ul>";
            }
        }
        if ($bloqueados > 0) {
            $msg[] = "⚠️ <strong>$bloqueados alunos bloqueados por pendências:</strong>";
            $msg[] = "<ul style='margin:5px 0 0 20px;'>";
            foreach ($mensagens_bloqueio as $mb) {
                $msg[] = "<li>" . $mb . "</li>";
            }
            $msg[] = "</ul>";
        }
        if ($erros > 0) {
            $msg[] = "❌ $erros alunos tiveram erro.";
        }
        
        if (!empty($msg)) {
            $sucesso = implode("<br>", $msg);
        } else {
            $erro = "Nenhuma ação foi realizada.";
        }
        
        // Recarregar lista
        $where_conditions = [];
        if (!empty($busca)) {
            $where_conditions[] = "(nome LIKE '%$busca%' OR N_BI LIKE '%$busca%' OR id LIKE '%$busca%')";
        }
        if (!empty($filtro_classe)) {
            $where_conditions[] = "Classe = '$filtro_classe'";
        }
        if (!empty($filtro_status)) {
            $where_conditions[] = "Situacao_Cadastro = '$filtro_status'";
        }
        $where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        $sql = "SELECT * FROM alunos_ano_anterior $where_sql ORDER BY Classe, nome";
        $stmt = $pdo->query($sql);
        $alunos = $stmt->fetchAll();
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $erro = 'Erro ao reconfirmar todos: ' . $e->getMessage();
        error_log("Erro na reconfirmação em massa: " . $e->getMessage());
    }
}

// ============================================
// ESTATÍSTICAS
// ============================================
$total_alunos = count($alunos);
$com_idade = 0;
$sem_idade = 0;
$por_classe = [];

foreach ($alunos as $a) {
    $classe = normalizarClasse($a['Classe'] ?? 'Sem Classe');
    if (!isset($por_classe[$classe])) $por_classe[$classe] = 0;
    $por_classe[$classe]++;
    
    $idade = calcularIdade($a['dia'], $a['mes'], $a['Ano']);
    if ($idade !== null) $com_idade++; else $sem_idade++;
}


?>

<style>
.page-header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;margin-bottom:25px}
.page-header h1{font-size:24px;font-weight:700;color:#1a2332;margin:0}
.page-header .subtitle{color:#94a3b8;font-size:14px;margin:2px 0 0}
.btn{padding:8px 20px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;transition:all .3s;display:inline-flex;align-items:center;gap:6px;border:none;cursor:pointer}
.btn-secondary{background:#f1f5f9;color:#4a5568}
.btn-secondary:hover{background:#e2e8f0}
.btn-success{background:#2ecc71;color:#fff}
.btn-success:hover{background:#27ae60}
.btn-primary{background:#c9a84c;color:#1a2332}
.btn-primary:hover{background:#b8973d;color:#1a2332}
.btn-info{background:#3498db;color:#fff}
.btn-info:hover{background:#2980b9}
.btn-sm{padding:4px 12px;font-size:11px;border-radius:6px}
.btn-danger{background:#e74c3c;color:#fff}
.btn-danger:hover{background:#c0392b}
.btn-pagar{background:#f39c12;color:#fff}
.btn-pagar:hover{background:#e67e22}
.btn-success-small{background:#2ecc71;color:#fff}
.btn-success-small:hover{background:#27ae60}
.stats-bar{display:flex;gap:20px;flex-wrap:wrap;margin-bottom:20px;padding:15px 20px;background:#fff;border-radius:12px;border:1px solid #eef2f7}
.stats-bar .stat-item{display:flex;align-items:center;gap:8px;font-size:14px;color:#4a5568}
.stats-bar .stat-item .number{font-weight:700;font-size:18px;color:#1a2332}
.filtros{display:flex;gap:15px;flex-wrap:wrap;margin-bottom:20px;padding:15px 20px;background:#fff;border-radius:12px;border:1px solid #eef2f7;align-items:center}
.filtros input,.filtros select{padding:8px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;outline:none;transition:border-color .3s}
.filtros input:focus,.filtros select:focus{border-color:#c9a84c}
.filtros input{flex:1;min-width:200px}
.filtros label.manter-classe{display:flex;align-items:center;gap:6px;font-size:13px;color:#4a5568;cursor:pointer;white-space:nowrap;padding:5px 12px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;transition:all .3s}
.filtros label.manter-classe:hover{background:#f1f5f9;border-color:#c9a84c}
.filtros label.manter-classe:has(input:checked){background:#fef3c7;border-color:#c9a84c;color:#92400e}
.filtros label.manter-classe input[type="checkbox"]{width:16px;height:16px;accent-color:#c9a84c;cursor:pointer}
.table-responsive{overflow-x:auto;background:#fff;border-radius:12px;border:1px solid #eef2f7}
.table{width:100%;border-collapse:collapse;font-size:12px;min-width:1100px}
.table th{background:#f8fafc;padding:8px 10px;text-align:left;font-weight:600;color:#4a5568;border-bottom:2px solid #e2e8f0;white-space:nowrap;font-size:9px;text-transform:uppercase;letter-spacing:.3px}
.table td{padding:8px 10px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.table tr:hover{background:#fafbfc}
.table tr.pendente{background:#fffbeb}
.table tr.pendente:hover{background:#fef3c7}
.status-badge{display:inline-block;padding:2px 12px;border-radius:12px;font-size:10px;font-weight:600}
.status-Matrícula{background:#dbeafe;color:#1e40af}
.status-Confirmação{background:#d1fae5;color:#065f46}
.status-Pendente{background:#fef3c7;color:#92400e}
.status-Bloqueado{background:#fee2e2;color:#991b1b}
.empty-state{text-align:center;padding:40px 20px;color:#94a3b8}
.empty-state .icon{font-size:48px;display:block;margin-bottom:15px}
.empty-state h3{font-size:18px;color:#4a5568;margin:0 0 5px}
.alert{padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:14px}
.alert-success{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0}
.alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
.alert-info{background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe}
.alert-warning{background:#fef3c7;color:#92400e;border:1px solid #fde68a}
.nav-alunos{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:25px;padding:15px 20px;background:#fff;border-radius:12px;border:1px solid #eef2f7}
.nav-alunos a{padding:8px 18px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:500;transition:all .3s;color:#4a5568;background:#f8fafc;border:1px solid #e2e8f0;display:inline-flex;align-items:center;gap:6px}
.nav-alunos a:hover{background:#c9a84c;color:#1a2332;border-color:#c9a84c;transform:translateY(-2px)}
.nav-alunos a.active{background:#c9a84c;color:#1a2332;border-color:#c9a84c}
.acoes-rapidas{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px;justify-content:center;padding:15px;background:#f8fafc;border-radius:12px;border:1px solid #eef2f7;align-items:center}
.idade-badge{background:#e9d5ff;color:#6b21a8;padding:2px 10px;border-radius:10px;font-size:11px;font-weight:600;display:inline-block}
.classe-old{color:#94a3b8;text-decoration:line-through;font-size:12px}
.classe-new{color:#c9a84c;font-weight:700;font-size:14px}
.classe-new.mantida{background:#fef3c7;padding:2px 8px;border-radius:4px;color:#92400e}
.sem-classe{color:#e74c3c;font-size:12px}
.table-actions{display:flex;gap:4px;flex-wrap:wrap}
.footer{text-align:center;padding:20px;color:#94a3b8;font-size:12px;border-top:1px solid #eef2f7;margin-top:20px}
.pendencia-badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:9px;font-weight:600;margin:1px}
.pendencia-badge.propina{background:#fef3c7;color:#92400e}
.pendencia-badge.transporte{background:#fef3c7;color:#92400e}
.pendencia-badge.prova{background:#fef3c7;color:#92400e}
.pendencia-badge.boletim{background:#fef3c7;color:#92400e}
.pendencia-badge.cartao{background:#fef3c7;color:#92400e}
.modal-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;justify-content:center;align-items:center}
.modal-box{background:#fff;border-radius:16px;padding:30px;max-width:700px;width:95%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3)}
.modal-box h2{color:#1a2332;margin-top:0;border-bottom:2px solid #f1f5f9;padding-bottom:15px}
.modal-box .total-pagar{font-size:24px;color:#e74c3c;font-weight:700}
.modal-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:15px;border-top:1px solid #eef2f7}
.modal-actions .btn{padding:10px 30px}
.modal-box .pendencia-item{padding:10px 0;border-bottom:1px solid #f1f5f9}
.modal-box .pendencia-item:last-child{border-bottom:none}
.forma-pagamento-select{padding:8px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;outline:none;width:100%;max-width:200px}
.forma-pagamento-select:focus{border-color:#c9a84c}
.modo-manter-classe{display:inline-block;padding:4px 16px;background:#fef3c7;border-radius:6px;border:1px solid #fde68a;font-size:13px;color:#92400e;margin-top:8px}
.acoes-rapidas label{display:flex;align-items:center;gap:6px;font-size:13px;color:#4a5568;cursor:pointer;background:#fff;padding:5px 12px;border-radius:6px;border:1px solid #e2e8f0;transition:all .3s}
.acoes-rapidas label:hover{background:#f1f5f9;border-color:#c9a84c}
.acoes-rapidas label:has(input:checked){background:#fef3c7;border-color:#c9a84c;color:#92400e}
.acoes-rapidas label input[type="checkbox"]{width:16px;height:16px;accent-color:#c9a84c;cursor:pointer}
@media(max-width:768px){
.page-header{flex-direction:column;align-items:stretch}
.nav-alunos{flex-direction:column;align-items:stretch}
.nav-alunos a{text-align:center;justify-content:center}
.table{font-size:11px;min-width:850px}
.table th,.table td{padding:4px 6px}
.btn-sm{font-size:9px;padding:2px 6px}
.acoes-rapidas{flex-direction:column;align-items:stretch}
.acoes-rapidas .btn{justify-content:center}
.stats-bar{flex-direction:column;gap:10px}
.filtros{flex-direction:column}
.filtros input{width:100%}
.modal-box{padding:20px}
}
</style>

<div class="container">
    <div class="page-header">
        <div>
            <h1>🔄 Reconfirmação de Matrícula</h1>
            <p class="subtitle">Reconfirmar alunos da tabela <strong>alunos_ano_anterior</strong> para a nova turma</p>
            <?php if ($manter_classe): ?>
                <div class="modo-manter-classe">
                    🔒 Modo: <strong>Manter Classe Atual</strong> ativado
                </div>
            <?php endif; ?>
        </div>
        <a href="index.php" class="btn btn-secondary">← Voltar</a>
    </div>

    <div class="nav-alunos">
        <a href="index.php">📋 Lista de Alunos</a>
        <a href="reconfirmar.php" class="active">🔄 Reconfirmação</a>
        <a href="consulta.php">🔍 Consulta</a>
        <a href="relatorio.php">📈 Relatório</a>
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
            <li>Os alunos são carregados da tabela <strong>alunos_ano_anterior</strong></li>
            <li>A idade é calculada automaticamente com base na data de nascimento</li>
            <li>A nova classe é calculada com base na classe atual (ex: 1ª → 2ª, 2ª → 3ª)</li>
            <li><strong style="color:#c9a84c;">🔒 Marque "Manter classe"</strong> para não avançar os alunos de série</li>
            <li><strong style="color:#e74c3c;">⚠️ Alunos com pendências financeiras NÃO podem ser reconfirmados</strong></li>
            <li>Clique em <strong style="color:#f39c12;">💰 Pagar</strong> para liquidar as pendências e gerar fatura</li>
            <li>Após o pagamento, o aluno é <strong>liberado automaticamente</strong> para reconfirmação</li>
            <li>Ao reconfirmar, o aluno é <strong>movido</strong> para a tabela principal <strong>alunos</strong></li>
            <li><strong style="color:#2ecc71;">✅ O ID do aluno é mantido</strong> durante a reconfirmação</li>
            <li>A turma é atribuída automaticamente com base na classe e período</li>
            <li><strong style="color:#2ecc71;">✅ Os nomes são preservados com acentos</strong> (Conceição, Adão, João, etc.)</li>
        </ul>
    </div>

    <!-- Filtros -->
    <div class="filtros">
        <form method="GET" style="display:flex;gap:15px;flex-wrap:wrap;width:100%;align-items:center;">
            <input type="text" name="busca" placeholder="🔍 Buscar por nome, BI ou ID..." value="<?= htmlspecialchars($busca, ENT_QUOTES, 'UTF-8') ?>">
            <select name="filtro_classe">
                <option value="">📚 Todas as classes</option>
                <?php foreach ($classes_disponiveis as $classe): 
                    $classe_normalizada = normalizarClasse($classe);
                ?>
                    <option value="<?= htmlspecialchars($classe, ENT_QUOTES, 'UTF-8') ?>" <?= ($filtro_classe == $classe) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($classe_normalizada, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="filtro_status">
                <option value="">📌 Todos os status</option>
                <option value="Matrícula" <?= ($filtro_status == 'Matrícula') ? 'selected' : '' ?>>Matrícula</option>
                <option value="Pendente" <?= ($filtro_status == 'Pendente') ? 'selected' : '' ?>>Pendente</option>
                <option value="Confirmação" <?= ($filtro_status == 'Confirmação') ? 'selected' : '' ?>>Confirmação</option>
            </select>
            
            <!-- Checkbox para manter classe -->
            <label class="manter-classe">
                <input type="checkbox" name="manter_classe" value="1" <?= $manter_classe ? 'checked' : '' ?>>
                🔒 Manter classe atual
            </label>
            
            <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
            <?php if (!empty($busca) || !empty($filtro_classe) || !empty($filtro_status) || $manter_classe): ?>
                <a href="reconfirmar.php" class="btn btn-secondary">🗑️ Limpar Filtros</a>
            <?php endif; ?>
            <span style="margin-left:auto;font-size:13px;color:#94a3b8;">
                <strong><?= $total_alunos ?></strong> resultado(s)
            </span>
        </form>
    </div>

    <!-- Estatísticas -->
    <div class="stats-bar">
        <div class="stat-item">
            <span class="number"><?= $total_alunos ?></span>
            <span class="label">Total de Alunos</span>
        </div>
        <div class="stat-item">
            <span class="number" style="color:#2ecc71;"><?= $com_idade ?></span>
            <span class="label">Com idade</span>
        </div>
        <div class="stat-item">
            <span class="number" style="color:#e74c3c;"><?= $sem_idade ?></span>
            <span class="label">Sem idade</span>
        </div>
        <?php foreach ($por_classe as $classe => $qtd): ?>
        <div class="stat-item">
            <span class="number"><?= $qtd ?></span>
            <span class="label"><?= htmlspecialchars($classe, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Tabela -->
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Sexo</th>
                    <th>Idade</th>
                    <th>Classe Atual</th>
                    <th>Nova Classe</th>
                    <th>Período</th>
                    <th>Status</th>
                    <th>Pendências</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($alunos) > 0): ?>
                    <?php foreach($alunos as $a): 
                        $idade = calcularIdade($a['dia'], $a['mes'], $a['Ano']);
                        $classe_atual = $a['Classe'] ?? '';
                        // CALCULA A CLASSE COM BASE NA OPÇÃO MANTER CLASSE
                        $nova_classe = calcularProximaClasse($classe_atual, $manter_classe);
                        
                        // SE A CLASSE ATUAL FOR PRÉ, A NOVA CLASSE É 1ª (independente do manter_classe)
                        $classe_atual_normalizada = normalizarClasse($classe_atual);
                        if (strtoupper($classe_atual_normalizada) == 'PRÉ' || strtoupper($classe_atual_normalizada) == 'PRE') {
                            $nova_classe = '1ª';
                        }
                        
                        $periodo = normalizarPeriodo($a['Periodo'] ?? 'Manhã');
                        $status = $a['Situacao_Cadastro'] ?? 'Pendente';
                        
                        $tem_data = !empty($a['dia']) && !empty($a['mes']) && !empty($a['Ano']);
                        
                        $verificacao = verificarPendencias($a['id'], $pdo, $emolumentos);
                        $tem_pendencias = $verificacao['tem_pendencias'];
                        $total_pendente = $verificacao['total_pendente'];
                        $pendencias_lista = $verificacao['pendencias'];
                        
                        $turma_info = buscarTurma($pdo, $nova_classe, $periodo);
                        $turma_nome_display = $turma_info['nome'] ?? '';
                        
                        if (empty($turma_nome_display)) {
                            $turma_nome_display = normalizarNomeTurma($nova_classe, $periodo);
                        }
                        $turma_nome_display = preg_replace('/[^0-9A-Z]/', '', strtoupper($turma_nome_display));
                        
                        // Verificar se a classe foi mantida
                        $classe_mantida = ($manter_classe && $classe_atual_normalizada == $nova_classe);
                        
                        // Nome do aluno com correção para exibição
                        $nome_exibicao = corrigirNomeCorrompido(limparString($a['nome']));
                    ?>
                    <tr class="<?= $tem_pendencias ? 'pendente' : '' ?>">
                        <td><strong><?= htmlspecialchars($a['id'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td><?= htmlspecialchars($nome_exibicao, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($a['Sexo'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if ($idade !== null): ?>
                                <span class="idade-badge"><?= $idade ?> anos</span>
                            <?php else: ?>
                                <span style="color:#94a3b8;font-size:11px;">
                                    <?= $tem_data ? '⚠️ erro' : 'dados incompletos' ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($classe_atual)): ?>
                                <span class="classe-old"><?= htmlspecialchars($classe_atual, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php else: ?>
                                <span class="sem-classe">⚠️ Sem classe</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($nova_classe)): ?>
                                <?php if ($classe_mantida): ?>
                                    <span class="classe-new mantida">🔒 <?= htmlspecialchars($nova_classe, ENT_QUOTES, 'UTF-8') ?></span>
                                    <span style="font-size:9px;color:#92400e;display:block;">(classe mantida)</span>
                                <?php elseif (strtoupper($classe_atual_normalizada) == 'PRÉ' || strtoupper($classe_atual_normalizada) == 'PRE'): ?>
                                    <span class="classe-new"><?= htmlspecialchars($nova_classe, ENT_QUOTES, 'UTF-8') ?></span>
                                    <span style="font-size:10px;color:#94a3b8;">(<?= htmlspecialchars($classe_atual, ENT_QUOTES, 'UTF-8') ?> →)</span>
                                <?php elseif ($nova_classe != $classe_atual): ?>
                                    <span class="classe-new"><?= htmlspecialchars($nova_classe, ENT_QUOTES, 'UTF-8') ?></span>
                                    <span style="font-size:10px;color:#94a3b8;">(<?= htmlspecialchars($classe_atual, ENT_QUOTES, 'UTF-8') ?> →)</span>
                                <?php else: ?>
                                    <span style="color:#94a3b8;"><?= htmlspecialchars($nova_classe, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                                <br><span style="font-size:9px;color:#c9a84c;">Turma: <?= $turma_nome_display ?></span>
                            <?php else: ?>
                                <span class="sem-classe">⚠️ Definir</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($periodo, ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if ($tem_pendencias): ?>
                                <span class="status-badge status-Bloqueado">🔒 Bloqueado</span>
                            <?php else: ?>
                                <span class="status-badge status-<?= $status ?>">
                                    <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($tem_pendencias): ?>
                                <?php foreach ($pendencias_lista as $p): ?>
                                    <span class="pendencia-badge <?= strtolower(str_replace(' ', '', $p['item'])) ?>">
                                        <?= $p['item'] ?>: <?= number_format($p['valor_total'], 0, ',', '.') ?> Kz
                                    </span>
                                <?php endforeach; ?>
                                <br>
                                <strong style="color:#e74c3c;font-size:12px;">
                                    Total: <?= number_format($total_pendente, 2, ',', '.') ?> Kz
                                </strong>
                            <?php else: ?>
                                <span style="color:#2ecc71;font-size:12px;">✅ Em dia</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="table-actions">
                                <?php if ($tem_pendencias): ?>
                                    <button class="btn btn-sm btn-pagar" onclick="abrirModal(<?= $a['id'] ?>, '<?= htmlspecialchars(addslashes($nome_exibicao), ENT_QUOTES, 'UTF-8') ?>', <?= htmlspecialchars(json_encode($pendencias_lista), ENT_QUOTES, 'UTF-8') ?>, <?= $total_pendente ?>)">
                                        💰 Pagar
                                    </button>
                                    <button class="btn btn-sm btn-danger" disabled>
                                        🔒 Bloqueado
                                    </button>
                                <?php else: ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Deseja reconfirmar <?= htmlspecialchars(addslashes($nome_exibicao), ENT_QUOTES, 'UTF-8') ?> para a nova classe? Turma: <?= $turma_nome_display ?>')">
                                        <input type="hidden" name="aluno_id" value="<?= $a['id'] ?>">
                                        <?php if ($manter_classe): ?>
                                            <input type="hidden" name="manter_classe" value="1">
                                        <?php endif; ?>
                                        <button type="submit" class="btn btn-sm btn-success-small">
                                            <?= $manter_classe ? '🔒' : '✅' ?> Reconf.
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10">
                            <div class="empty-state">
                                <span class="icon"><?= (!empty($busca) || !empty($filtro_classe) || !empty($filtro_status)) ? '🔍' : '📭' ?></span>
                                <h3><?= (!empty($busca) || !empty($filtro_classe) || !empty($filtro_status)) ? 'Nenhum resultado encontrado' : 'Nenhum aluno para reconfirmar' ?></h3>
                                <p>
                                    <?php if (!empty($busca) || !empty($filtro_classe) || !empty($filtro_status)): ?>
                                        Tente ajustar os filtros de busca.
                                        <br><a href="reconfirmar.php" class="btn btn-secondary" style="margin-top:10px;">🗑️ Limpar Filtros</a>
                                    <?php else: ?>
                                        <?php if (isset($tabela_existe) && !$tabela_existe): ?>
                                            A tabela <strong>alunos_ano_anterior</strong> não foi encontrada.
                                        <?php else: ?>
                                            Todos os alunos já foram reconfirmados ou não há alunos pendentes.
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Ações Rápidas -->
    <?php if (count($alunos) > 0): ?>
    <div class="acoes-rapidas">
        <span style="color:#94a3b8;font-size:13px;font-weight:500;">⚡ Ações:</span>
        
        <form method="POST" style="display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <input type="hidden" name="reconfirmar_todos" value="1">
            
            <!-- Checkbox para manter classe em massa -->
            <label>
                <input type="checkbox" name="manter_classe_massa" value="1" <?= $manter_classe ? 'checked' : '' ?>>
                🔒 Manter classe
            </label>
            
            <button type="submit" class="btn btn-primary" onclick="return confirm('⚠️ Deseja reconfirmar TODOS os <?= count($alunos) ?> alunos pendentes? Apenas alunos sem pendências serão reconfirmados!')">
                🔄 Reconfirmar Todos (<?= count($alunos) ?>)
            </button>
        </form>
        
        <a href="relatorio_reconfirmacao.php" class="btn btn-info">📈 Relatório de Reconfirmação</a>
    </div>
    <?php endif; ?>

    <div class="footer">
        © <?= date('Y') ?> - Sistema de Gestão Escolar | Reconfirmação de Matrícula | Tabela: alunos_ano_anterior
    </div>
</div>

<!-- Modal de Pagamento -->
<div id="modalPagar" class="modal-overlay" onclick="if(event.target===this) fecharModal()">
    <div class="modal-box">
        <h2>💰 Pagamento de Pendências</h2>
        <p><strong>👤 Aluno:</strong> <span id="modalNomeAluno" style="color:#c9a84c;font-weight:700;"></span></p>
        <hr>
        <h3>📋 Detalhes da Dívida:</h3>
        <div id="modalPendencias" style="margin:10px 0;"></div>
        <hr>
        <div style="display:flex;align-items:center;gap:15px;flex-wrap:wrap;justify-content:center;padding:10px 0;">
            <div>
                <label style="font-size:14px;font-weight:600;display:block;margin-bottom:5px;">💳 Forma de Pagamento</label>
                <select id="formaPagamentoModal" class="forma-pagamento-select">
                    <option value="dinheiro">💰 Dinheiro</option>
                    <option value="transferencia">🏦 Transferência</option>
                    <option value="pix">📱 Pix</option>
                    <option value="cartao">💳 Cartão</option>
                </select>
            </div>
            <div style="text-align:center;">
                <p style="font-size:16px;margin:0;"><strong>Total a Pagar:</strong></p>
                <p class="total-pagar" id="modalTotalPagar"></p>
            </div>
        </div>
        <p style="font-size:13px;color:#94a3b8;text-align:center;">⚠️ Ao confirmar, será gerada uma fatura e todas as pendências serão marcadas como pagas. O aluno será liberado automaticamente.</p>
        <div class="modal-actions">
            <button onclick="fecharModal()" class="btn btn-secondary">❌ Cancelar</button>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Confirma o pagamento de todas as pendências?')">
                <input type="hidden" name="aluno_id" id="modalAlunoId" value="">
                <input type="hidden" name="forma_pagamento" id="modalFormaPagamento" value="dinheiro">
                <input type="hidden" name="pagar_pendencia" value="1">
                <button type="submit" class="btn btn-success">✅ Confirmar Pagamento</button>
            </form>
        </div>
    </div>
</div>

<script>
function abrirModal(alunoId, nome, pendencias, total) {
    document.getElementById('modalAlunoId').value = alunoId;
    document.getElementById('modalNomeAluno').textContent = nome;
    
    var html = '';
    if (pendencias.length > 0) {
        html += '<ul style="list-style:none;padding:0;margin:0;">';
        pendencias.forEach(function(p) {
            html += '<li class="pendencia-item" style="padding:8px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;">';
            html += '<div>';
            html += '<strong>' + p.item + '</strong>';
            html += ' <span style="font-size:11px;color:#94a3b8;">(' + p.quantidade + 'x)</span>';
            html += '</div>';
            html += '<div style="text-align:right;">';
            html += '<span style="font-size:12px;color:#94a3b8;">' + formatMoney(p.valor_unitario) + ' Kz</span>';
            html += ' <span style="color:#e74c3c;font-weight:700;">= ' + formatMoney(p.valor_total) + ' Kz</span>';
            html += ' <span style="font-size:10px;color:#94a3b8;">(' + p.status + ')</span>';
            html += '</div>';
            html += '</li>';
        });
        html += '</ul>';
    } else {
        html = '<p style="color:#2ecc71;">✅ Nenhuma pendência encontrada.</p>';
    }
    
    document.getElementById('modalPendencias').innerHTML = html;
    document.getElementById('modalTotalPagar').textContent = formatMoney(total) + ' Kz';
    
    document.getElementById('formaPagamentoModal').value = 'dinheiro';
    document.getElementById('modalFormaPagamento').value = 'dinheiro';
    
    document.getElementById('modalPagar').style.display = 'flex';
}

function fecharModal() {
    document.getElementById('modalPagar').style.display = 'none';
}

function formatMoney(value) {
    return value.toLocaleString('pt-AO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

document.addEventListener('DOMContentLoaded', function() {
    var select = document.getElementById('formaPagamentoModal');
    if (select) {
        select.addEventListener('change', function() {
            document.getElementById('modalFormaPagamento').value = this.value;
        });
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        fecharModal();
    }
});
</script>

<?php include '../includes/footer_escola.php'; ?>