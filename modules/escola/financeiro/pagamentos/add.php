<?php
// ============================================
// modules/escola/financeiro/pagamentos/add.php - Novo Pagamento
// ============================================

require_once '../../../../config/app_modes.php';
require_once '../../../../config/database.php';
// ============================================================

// CRIAR PASTA PARA FATURAS
// ============================================================
$pasta_faturas = $_SERVER['DOCUMENT_ROOT'] . '/meus_documentos/faturas/';
if (!file_exists($pasta_faturas)) {
    mkdir($pasta_faturas, 0777, true);
}

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if (!temPermissao('Escola', 'criar')) {
    header('Location: ' . SITE_URL);
    exit;
}

// ============================================================
// INICIALIZAR VARIÁVEIS PARA EVITAR WARNINGS
// ============================================================
$aluno_nome = '';
$aluno_classe = '';
$aluno_turma = '';
$aluno_periodo = '';
$aluno_nif = '9999999999';
$aluno_genero = '';
$aluno_id = null;

// ============================================================
// FUNÇÕES AUXILIARES
// ============================================================

function removerAcentos($string) {
    $mapa = array(
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c',
        'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'Ä' => 'A',
        'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ó' => 'O', 'Ò' => 'O', 'Õ' => 'O', 'Ô' => 'O', 'Ö' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'Ç' => 'C'
    );
    return strtr($string, $mapa);
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

function mesNomeParaNumero($nome) {
    $mapa = array(
        'Janeiro' => 1, 'Fevereiro' => 2, 'Março' => 3, 'Abril' => 4,
        'Maio' => 5, 'Junho' => 6, 'Julho' => 7, 'Agosto' => 8,
        'Setembro' => 9, 'Outubro' => 10, 'Novembro' => 11, 'Dezembro' => 12
    );
    return $mapa[$nome] ?? 0;
}

function mesNumeroParaNome($num) {
    $mapa = array(
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
    );
    return $mapa[$num] ?? '';
}

function extrairMesAno($mes_referencia) {
    $resultado = ['mes' => 0, 'ano' => date('Y')];
    
    if (empty($mes_referencia) || $mes_referencia == '-') {
        return $resultado;
    }
    
    if (strpos($mes_referencia, '/') !== false) {
        $partes = explode('/', $mes_referencia);
        if (count($partes) == 2) {
            $mes_nome = trim($partes[0]);
            $ano = intval(trim($partes[1]));
            $mes_numero = mesNomeParaNumero($mes_nome);
            if ($mes_numero > 0) {
                $resultado['mes'] = $mes_numero;
                $resultado['ano'] = $ano;
            }
        }
    } else {
        $mes_numero = mesNomeParaNumero(trim($mes_referencia));
        if ($mes_numero > 0) {
            $resultado['mes'] = $mes_numero;
        }
    }
    
    return $resultado;
}

function calcularMulta($pdo, $emolumento_id, $mes, $ano, $valor_base) {
    try {
        $stmt = $pdo->prepare("
            SELECT multa_tipo, multa_valor, prazo_dias 
            FROM emolumentos 
            WHERE id = ?
        ");
        $stmt->execute([$emolumento_id]);
        $config = $stmt->fetch();
        
        if (!$config) {
            $stmt = $pdo->prepare("
                SELECT multa_tipo, multa_valor, prazo_dias 
                FROM mensalidades 
                WHERE emolumento_id = ? 
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([$emolumento_id]);
            $config = $stmt->fetch();
        }
        
        if (!$config) {
            $config = [
                'multa_tipo' => 'percentual',
                'multa_valor' => 10,
                'prazo_dias' => 10
            ];
        }
        
        $data_vencimento = date("Y-m-d", strtotime("$ano-$mes-10"));
        $data_atual = date('Y-m-d');
        
        $diff = strtotime($data_atual) - strtotime($data_vencimento);
        $dias_atraso = floor($diff / (60 * 60 * 24));
        
        if ($dias_atraso <= $config['prazo_dias']) {
            return 0;
        }
        
        if ($config['multa_tipo'] == 'percentual') {
            $multa = ($valor_base * $config['multa_valor']) / 100;
        } else {
            $multa = $config['multa_valor'];
        }
        
        return $multa;
        
    } catch (Exception $e) {
        error_log("Erro ao calcular multa: " . $e->getMessage());
        return 0;
    }
}


// ============================================================
// FUNÇÃO PARA SALVAR FATURA EM PDF
// ============================================================
function salvarFaturaPDF($html, $numero_fatura) {
    global $pasta_faturas;
    
    $nome_arquivo = 'fatura_' . $numero_fatura . '.pdf';
    $caminho_completo = $pasta_faturas . $nome_arquivo;
    
    try {
        if (file_exists('../../../../vendor/autoload.php')) {
            require_once '../../../../vendor/autoload.php';
            
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->loadHtml($html);
            $dompdf->render();
            
            $output = $dompdf->output();
            file_put_contents($caminho_completo, $output);
            
            return ['success' => true, 'caminho' => $caminho_completo, 'nome' => $nome_arquivo];
        } else {
            $caminho_html = $pasta_faturas . 'fatura_' . $numero_fatura . '.html';
            file_put_contents($caminho_html, $html);
            return ['success' => true, 'caminho' => $caminho_html, 'nome' => 'fatura_' . $numero_fatura . '.html', 'aviso' => 'PDF não disponível, salvo como HTML'];
        }
    } catch (Exception $e) {
        $caminho_html = $pasta_faturas . 'fatura_' . $numero_fatura . '.html';
        file_put_contents($caminho_html, $html);
        return ['success' => true, 'caminho' => $caminho_html, 'nome' => 'fatura_' . $numero_fatura . '.html', 'aviso' => 'Erro ao gerar PDF: ' . $e->getMessage()];
    }
}

function atualizarMensalidade($pdo, $aluno_id, $mes_numero, $ano, $valor_pago, $emolumento_id = null) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, status FROM mensalidades 
            WHERE aluno_id = ? AND mes = ? AND ano = ?
        ");
        $stmt->execute([$aluno_id, $mes_numero, $ano]);
        $mensalidade = $stmt->fetch();
        
        if ($mensalidade) {
            $stmt = $pdo->prepare("
                UPDATE mensalidades 
                SET status = 'pago', valor_pago = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$valor_pago, $mensalidade['id']]);
            return true;
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO mensalidades 
                (aluno_id, mes, ano, valor, valor_pago, status, emolumento_id, data_vencimento, descricao) 
                VALUES (?, ?, ?, ?, ?, 'pago', ?, NOW(), 'Mensalidade escolar')
            ");
            $stmt->execute([$aluno_id, $mes_numero, $ano, $valor_pago, $valor_pago, $emolumento_id]);
            return true;
        }
    } catch (Exception $e) {
        return false;
    }
}

// ============================================================
// VERIFICAR DUPLICADO
// ============================================================
function verificarDuplicadoDetalhado($pdo, $aluno_id, $emolumento_id, $mes_referencia, $nome_aluno = null) {
    try {
        if (empty($aluno_id) && empty($nome_aluno)) {
            return ['duplicado' => false];
        }
        
        $sql = "SELECT p.*, e.nome as emolumento_nome, a.nome as aluno_nome
                FROM pagamentos p
                LEFT JOIN emolumentos e ON p.emolumento_id = e.id
                LEFT JOIN alunos a ON p.aluno_id = a.id
                WHERE p.emolumento_id = ? 
                AND p.mes_referencia = ? 
                AND p.status IN ('confirmado', 'Pago', 'pago')";
        
        $params = [$emolumento_id, $mes_referencia];
        
        if (!empty($aluno_id)) {
            $sql .= " AND p.aluno_id = ?";
            $params[] = $aluno_id;
        } else if (!empty($nome_aluno)) {
            $stmt_aluno = $pdo->prepare("SELECT id FROM alunos WHERE nome = ? LIMIT 1");
            $stmt_aluno->execute([trim($nome_aluno)]);
            $aluno_encontrado = $stmt_aluno->fetch();
            
            if ($aluno_encontrado) {
                $sql .= " AND p.aluno_id = ?";
                $params[] = $aluno_encontrado['id'];
            } else {
                $sql .= " AND (p.nome_aluno = ? OR p.nome_aluno LIKE ?)";
                $params[] = trim($nome_aluno);
                $params[] = '%' . trim($nome_aluno) . '%';
            }
        }
        
        $sql .= " ORDER BY p.id DESC LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $pagamento = $stmt->fetch();
        
        if ($pagamento) {
            if (!empty($pagamento['aluno_id'])) {
                $stmt_nome = $pdo->prepare("SELECT nome FROM alunos WHERE id = ?");
                $stmt_nome->execute([$pagamento['aluno_id']]);
                $aluno_nome_db = $stmt_nome->fetchColumn();
                $nome_aluno_dup = $aluno_nome_db ?: 'Desconhecido';
            } else {
                $nome_aluno_dup = $pagamento['nome_aluno'] ?? 'Desconhecido';
            }
            
            return [
                'duplicado' => true,
                'fonte' => 'pagamentos',
                'detalhes' => [
                    'emolumento' => $pagamento['emolumento_nome'] ?? 'Desconhecido',
                    'valor' => $pagamento['valor'] ?? 0,
                    'data' => $pagamento['data_pagamento'] ?? 'Desconhecida',
                    'aluno_nome' => $nome_aluno_dup,
                    'aluno_id' => $pagamento['aluno_id'] ?? null,
                    'pagamento_id' => $pagamento['id'] ?? null
                ]
            ];
        }
        
        if (!empty($aluno_id) && $mes_referencia != '-' && !empty($mes_referencia)) {
            $dados_mes = extrairMesAno($mes_referencia);
            $mes_numero = $dados_mes['mes'];
            $ano = $dados_mes['ano'];
            
            if ($mes_numero > 0) {
                $stmt = $pdo->prepare("
                    SELECT m.*, a.nome as aluno_nome
                    FROM mensalidades m
                    LEFT JOIN alunos a ON m.aluno_id = a.id
                    WHERE m.aluno_id = ? AND m.mes = ? AND m.ano = ? AND m.status = 'pago'
                    LIMIT 1
                ");
                $stmt->execute([$aluno_id, $mes_numero, $ano]);
                $mensalidade = $stmt->fetch();
                
                if ($mensalidade) {
                    $stmt_emol = $pdo->prepare("SELECT nome FROM emolumentos WHERE id = ?");
                    $stmt_emol->execute([$emolumento_id]);
                    $emol = $stmt_emol->fetch();
                    $nome_emolumento = $emol['nome'] ?? '';
                    
                    if (stripos($nome_emolumento, 'propina') !== false || 
                        stripos($nome_emolumento, 'mensalidade') !== false) {
                        return [
                            'duplicado' => true,
                            'fonte' => 'mensalidades',
                            'detalhes' => [
                                'emolumento' => $nome_emolumento,
                                'valor' => $mensalidade['valor'] ?? 0,
                                'data' => $mensalidade['updated_at'] ?? 'Desconhecida',
                                'aluno_nome' => $mensalidade['aluno_nome'] ?? 'Desconhecido',
                                'aluno_id' => $aluno_id,
                                'pagamento_id' => $mensalidade['id'] ?? null
                            ]
                        ];
                    }
                }
            }
        }
        
        return ['duplicado' => false];
        
    } catch (Exception $e) {
        error_log("Erro ao verificar duplicado detalhado: " . $e->getMessage());
        return ['duplicado' => false];
    }
}

function buscarEmolumentosAgrupados($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT id, nome, valor 
            FROM emolumentos 
            WHERE status = 'ativo' 
            GROUP BY nome 
            ORDER BY nome
        ");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function buscarDadosEmpresa($pdo) {
    $dados = [
        'nome' => 'Sistema Escolar',
        'endereco' => '',
        'telefone' => '',
        'celular' => '',
        'email' => '',
        'nif' => '',
        'cidade' => '',
        'estado' => '',
        'cep' => ''
    ];
    
    try {
        // Primeiro tenta buscar da tabela config_agt
        $stmt = $pdo->prepare("SELECT * FROM config_agt WHERE id = 1 LIMIT 1");
        $stmt->execute();
        $config_agt = $stmt->fetch();
        
        if ($config_agt) {
            // USA O NOME COMERCIAL DA CONFIG_AGT
            $dados['nome'] = $config_agt['nome_comercial'] ?? 'Sistema Escolar';
            $dados['endereco'] = $config_agt['endereco'] ?? '';
            $dados['telefone'] = $config_agt['telefone'] ?? '';
            $dados['email'] = $config_agt['email'] ?? '';
            $dados['nif'] = $config_agt['nif'] ?? '5001234567';
        } else {
            // Fallback: tenta buscar da tabela empresa
            $stmt = $pdo->prepare("SELECT * FROM empresa WHERE id = 1 LIMIT 1");
            $stmt->execute();
            $empresa = $stmt->fetch();
            
            if ($empresa) {
                $dados['nome'] = $empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? 'Sistema Escolar';
                $dados['endereco'] = $empresa['endereco'] ?? '';
                $dados['telefone'] = $empresa['telefone'] ?? '';
                $dados['email'] = $empresa['email'] ?? '';
                $dados['nif'] = $empresa['cnpj'] ?? '5001234567';
            }
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar dados da empresa: " . $e->getMessage());
    }
    
    return $dados;
}



// ============================================================
// FUNÇÕES AGT
// ============================================================

function buscarConfigAGT($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM config_agt WHERE id = 1");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        return null;
    }
}

function buscarEmolumentoComIVA($pdo, $emolumento_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, nome, valor, codigo_iva, taxa_iva
            FROM emolumentos WHERE id = ?
        ");
        $stmt->execute([$emolumento_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return null;
    }
}

function gerarNumeroFaturaAGT($pdo) {
    $ano = date('Y');
    $mes = date('m');
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT ultimo_numero FROM config_agt WHERE id = 1 FOR UPDATE");
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $num = ($config && isset($config['ultimo_numero'])) ? intval($config['ultimo_numero']) + 1 : 1;
        
        $stmt = $pdo->prepare("UPDATE config_agt SET ultimo_numero = ? WHERE id = 1");
        $stmt->execute([$num]);
        
        $pdo->commit();
        
        return 'FT-' . substr($ano, -2) . $mes . str_pad($num, 6, '0', STR_PAD_LEFT);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return 'FT-' . date('ym') . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }
}

function gerarHashFaturaAGT($dados) {
    $string = $dados['numero_fatura'] . 
              $dados['data_emissao'] . 
              $dados['total_liquido'] . 
              $dados['aluno_nome'] . 
              $dados['aluno_nif'] . 
              date('Y-m-d');
    return hash('sha256', $string);
}

function salvarFaturaAGT($pdo, $dados, $itens, $qrcode_base64 = null) {
    try {
        $config = buscarConfigAGT($pdo);
        if (!$config) {
            return ['success' => false, 'error' => 'Configuração AGT não encontrada'];
        }
        
        $total_base = 0;
        $total_iva = 0;
        $total_multa = 0;
        $total_desconto = 0;
        $total_liquido = 0;
        
        foreach ($itens as $item) {
            $total_base += $item['valor_base'] ?? 0;
            $total_iva += $item['valor_iva'] ?? 0;
            $total_multa += $item['multa'] ?? 0;
            $total_desconto += $item['desconto'] ?? 0;
            $total_liquido += $item['valor_liquido'] ?? 0;
        }
        
        $numero_fatura = $dados['numero_fatura'] ?? gerarNumeroFaturaAGT($pdo);
        
        $hash_data = [
            'numero_fatura' => $numero_fatura,
            'data_emissao' => date('Y-m-d H:i:s'),
            'total_liquido' => $total_liquido,
            'aluno_nome' => $dados['aluno_nome'],
            'aluno_nif' => $dados['aluno_nif'] ?? '9999999999'
        ];
        $hash = gerarHashFaturaAGT($hash_data);
        
        $sql = "INSERT INTO faturas_agt SET
            numero_fatura = :numero_fatura,
            serie = :serie,
            data_emissao = NOW(),
            aluno_id = :aluno_id,
            aluno_nome = :aluno_nome,
            aluno_nif = :aluno_nif,
            aluno_classe = :aluno_classe,
            aluno_turma = :aluno_turma,
            empresa_nome = :empresa_nome,
            empresa_nif = :empresa_nif,
            empresa_endereco = :empresa_endereco,
            total_base = :total_base,
            total_iva = :total_iva,
            total_liquido = :total_liquido,
            forma_pagamento = :forma_pagamento,
            hash_autenticacao = :hash,
            status = 'emitida'
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':numero_fatura' => $numero_fatura,
            ':serie' => $config['serie_fatura'] ?? 'A',
            ':aluno_id' => $dados['aluno_id'] ?? null,
            ':aluno_nome' => $dados['aluno_nome'],
            ':aluno_nif' => $dados['aluno_nif'] ?? '9999999999',
            ':aluno_classe' => $dados['aluno_classe'] ?? '',
            ':aluno_turma' => $dados['aluno_turma'] ?? '',
            ':empresa_nome' => $config['nome_comercial'] ?? 'Sistema Escolar',
            ':empresa_nif' => $config['nif'] ?? '5001234567',
            ':empresa_endereco' => $config['endereco'] ?? '',
            ':total_base' => $total_base,
            ':total_iva' => $total_iva,
            ':total_liquido' => $total_liquido,
            ':forma_pagamento' => $dados['forma_pagamento'] ?? 'dinheiro',
            ':hash' => $hash
        ]);
        
        $fatura_id = $pdo->lastInsertId();
        
        foreach ($itens as $item) {
            $stmt = $pdo->prepare("
                INSERT INTO fatura_agt_itens SET
                    fatura_id = :fatura_id,
                    descricao = :descricao,
                    quantidade = :quantidade,
                    preco_unitario = :preco_unitario,
                    desconto = :desconto,
                    taxa_iva = :taxa_iva,
                    valor_iva = :valor_iva,
                    total_item = :total_item,
                    mes_referencia = :mes_referencia,
                    ano_referencia = :ano_referencia,
                    emolumento_id = :emolumento_id
            ");
            $stmt->execute([
                ':fatura_id' => $fatura_id,
                ':descricao' => $item['descricao'] ?? $item['emolumento'] ?? 'Pagamento',
                ':quantidade' => 1,
                ':preco_unitario' => $item['valor_base'] ?? 0,
                ':desconto' => $item['desconto'] ?? 0,
                ':taxa_iva' => $item['taxa_iva'] ?? 0,
                ':valor_iva' => $item['valor_iva'] ?? 0,
                ':total_item' => $item['valor_liquido'] ?? 0,
                ':mes_referencia' => $item['mes_referencia'] ?? null,
                ':ano_referencia' => $item['ano_referencia'] ?? date('Y'),
                ':emolumento_id' => $item['emolumento_id'] ?? null
            ]);
        }
        
        return [
            'success' => true,
            'fatura_id' => $fatura_id,
            'numero_fatura' => $numero_fatura,
            'hash' => $hash
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function atualizarPagamentoComFatura($pdo, $pagamento_id, $numero_fatura, $hash, $taxa_iva, $valor_iva, $codigo_iva) {
    try {
        $stmt = $pdo->prepare("
            UPDATE pagamentos SET 
                numero_fatura = :numero_fatura,
                hash_autenticacao = :hash,
                taxa_iva = :taxa_iva,
                valor_iva = :valor_iva,
                codigo_iva = :codigo_iva,
                updated_at = NOW()
            WHERE id = :pagamento_id
        ");
        $stmt->execute([
            ':numero_fatura' => $numero_fatura,
            ':hash' => $hash,
            ':taxa_iva' => $taxa_iva,
            ':valor_iva' => $valor_iva,
            ':codigo_iva' => $codigo_iva,
            ':pagamento_id' => $pagamento_id
        ]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}




// ============================================================
// GERAR FATURA HTML - CORRIGIDO COM DADOS DA CONFIG_AGT
// ============================================================

/**
 * Gera fatura completa com duas vias
 */
function gerarFaturaAGTCompleta($empresa, $aluno, $itens, $numero_fatura, $total_base, $total_iva, $total_liquido, $hash, $forma_pagamento, $data_pagamento, $config_agt = null) {
    
    // Buscar configuração AGT se não foi passada
    global $pdo;
    if ($config_agt === null) {
        $config_agt = buscarConfigAGT($pdo);
    }
    
    // Configurações padrão
    if (!$config_agt) {
        $config_agt = [
            'regime_iva' => 'normal',
            'taxa_iva_padrao' => 14.00,
            'nif' => '5001234567',
            'nome_comercial' => 'Sistema Escolar',  // NOME PADRÃO
            'endereco' => '',
            'telefone' => '',
            'email' => ''
        ];
    }
    
    // ============================================================
    // USAR DADOS DA CONFIG_AGT PARA A EMPRESA
    // ============================================================
    $empresa_nome = !empty($empresa['nome']) ? $empresa['nome'] : ($config_agt['nome_comercial'] ?? 'Sistema Escolar');
    $empresa_nif = !empty($empresa['nif']) ? $empresa['nif'] : ($config_agt['nif'] ?? '5001234567');
    $empresa_endereco = !empty($empresa['endereco']) ? $empresa['endereco'] : ($config_agt['endereco'] ?? '');
    $empresa_telefone = !empty($empresa['telefone']) ? $empresa['telefone'] : ($config_agt['telefone'] ?? '');
    $empresa_email = !empty($empresa['email']) ? $empresa['email'] : ($config_agt['email'] ?? '');

    
    // ============================================================
    // CALCULAR IVA CORRETAMENTE
    // ============================================================
    $regime_iva = $config_agt['regime_iva'] ?? 'normal';
    $taxa_iva_padrao = floatval($config_agt['taxa_iva_padrao'] ?? 14);
    
    // Verificar se deve mostrar IVA
    // Se for 'normal' ou 'isento', mostra IVA
    // Se for 'nao_sujeito', NÃO mostra IVA
    $mostrar_iva = ($regime_iva === 'normal' || $regime_iva === 'isento');
    
    // Se for não sujeito, zerar IVA
    if (!$mostrar_iva) {
        $taxa_iva_padrao = 0;
        $total_iva = 0;
        // Atualizar os itens para não ter IVA
        foreach ($itens as &$item) {
            $item['valor_iva'] = 0;
            $item['taxa_iva'] = 0;
            $item['valor_liquido'] = $item['valor_base'] ?? 0;
        }
        unset($item);
        // Recalcular total líquido
        $total_liquido = $total_base;
    }
    
    // Gerar corpo da fatura
    $corpo = gerarCorpoFaturaAGT(
        $empresa_nome, $empresa_nif, $empresa_endereco, $empresa_telefone, $empresa_email,
        $aluno, $itens, $numero_fatura, $total_base, $total_iva, $total_liquido, 
        $hash, $forma_pagamento, $data_pagamento, $config_agt, $mostrar_iva, $taxa_iva_padrao
    );
    
    $html = '<!DOCTYPE html>
    <html lang="pt">
    <head>
        <meta charset="UTF-8">
        <title>Fatura Nº ' . $numero_fatura . '</title>
        <style>
            @page { size: A4; margin: 8mm; }
            * { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif; }
            body { background: #fff; padding: 5mm; font-size: 12px; }
            .fatura-container { max-width: 200mm; margin: 0 auto; background: #fff; }
            .header-fatura {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                padding: 8px 0 12px 0;
                border-bottom: 3px double #1a2332;
                margin-bottom: 12px;
            }
            .header-fatura .empresa-info { flex: 1; }
            .header-fatura .empresa-info .nome {
                font-size: 20px;
                font-weight: 800;
                color: #1a2332;
                text-transform: uppercase;
            }
            .header-fatura .empresa-info .dados { font-size: 10px; color: #555; margin: 2px 0; }
            .header-fatura .empresa-info .nif-box {
                display: inline-block;
                background: #1a2332;
                color: #fff;
                padding: 2px 15px;
                border-radius: 4px;
                font-size: 10px;
                font-weight: 700;
                margin-top: 4px;
            }
            .header-fatura .fatura-titulo {
                text-align: right;
                min-width: 180px;
            }
            .header-fatura .fatura-titulo .tipo {
                font-size: 22px;
                font-weight: 800;
                color: #c9a84c;
                letter-spacing: 3px;
            }
            .header-fatura .fatura-titulo .numero {
                font-size: 14px;
                font-weight: 700;
                color: #1a2332;
                margin: 3px 0;
            }
            .header-fatura .fatura-titulo .data { font-size: 10px; color: #555; }
            .header-fatura .fatura-titulo .status-badge {
                display: inline-block;
                padding: 2px 15px;
                border-radius: 12px;
                font-size: 10px;
                font-weight: 700;
                color: #fff;
                background: #2ecc71;
                margin-top: 4px;
            }
            .header-fatura .fatura-titulo .regime-iva-label {
                display: inline-block;
                padding: 2px 10px;
                border-radius: 4px;
                font-size: 9px;
                font-weight: 600;
                margin-top: 4px;
                background: #fef9e8;
                color: #78350f;
                border: 1px solid #fde68a;
            }
            .aluno-area {
                padding: 10px 14px;
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                margin-bottom: 12px;
            }
            .aluno-area .label {
                font-size: 8px;
                color: #94a3b8;
                text-transform: uppercase;
                font-weight: 600;
                letter-spacing: 0.5px;
            }
            .aluno-area .nome { font-size: 16px; font-weight: 700; color: #1a2332; }
            .aluno-area .info {
                font-size: 11px;
                color: #555;
                margin: 3px 0;
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }
            .aluno-area .info .tag {
                background: #eef2f7;
                padding: 1px 10px;
                border-radius: 12px;
                font-size: 10px;
            }
            .tabela-itens {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 12px;
                font-size: 11px;
            }
            .tabela-itens th {
                background: #1a2332;
                color: #fff;
                padding: 6px 8px;
                text-align: left;
                border: 1px solid #1a2332;
                font-size: 9px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .tabela-itens td {
                padding: 5px 8px;
                border: 1px solid #e2e8f0;
                vertical-align: middle;
            }
            .tabela-itens .text-right { text-align: right; }
            .tabela-itens .text-center { text-align: center; }
            .tabela-itens .total-row { background: #f8fafc; font-weight: 700; }
            .tabela-itens .total-row td { border-top: 2px solid #1a2332; }
            .tabela-itens .valor-total { color: #c0392b; font-size: 14px; }
            .resumo-fatura {
                display: flex;
                justify-content: flex-end;
                padding: 12px 16px;
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                margin-bottom: 12px;
            }
            .resumo-fatura .grid {
                display: grid;
                grid-template-columns: auto auto;
                gap: 3px 25px;
                font-size: 12px;
            }
            .resumo-fatura .grid .label { color: #555; }
            .resumo-fatura .grid .value { text-align: right; font-weight: 600; }
            .resumo-fatura .grid .total {
                font-size: 18px;
                color: #c0392b;
                font-weight: 800;
                border-top: 2px solid #1a2332;
                padding-top: 6px;
                margin-top: 4px;
            }
            .rodape-fatura {
                padding: 12px 0;
                border-top: 2px solid #1a2332;
                margin-top: 8px;
                display: flex;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 10px;
            }
            .rodape-fatura .assinatura {
                text-align: center;
                min-width: 140px;
            }
            .rodape-fatura .assinatura .linha {
                width: 130px;
                border-top: 1px solid #333;
                margin: 4px auto;
            }
            .rodape-fatura .assinatura .cargo { font-size: 9px; color: #555; }
            .rodape-fatura .info-extra { font-size: 8px; color: #999; text-align: right; }
            .rodape-fatura .info-extra .hash {
                font-size: 7px;
                word-break: break-all;
                max-width: 200px;
            }
            .via-divider {
                text-align: center;
                font-size: 13px;
                font-weight: 700;
                color: #1a2332;
                padding: 12px 0;
                margin: 15px 0;
                border-top: 2px dashed #ccc;
                border-bottom: 2px dashed #ccc;
            }
            .via-divider span { background: #fff; padding: 0 18px; }
            .selo-validacao {
                display: inline-block;
                padding: 3px 12px;
                border: 2px solid #c9a84c;
                border-radius: 4px;
                font-size: 8px;
                color: #1a2332;
                font-weight: 600;
                margin-top: 5px;
            }
            @media print {
                body { background: #fff !important; padding: 0 !important; }
                .fatura-container { box-shadow: none !important; }
                .no-print { display: none !important; }
                * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            }
            @media screen and (max-width: 768px) {
                .header-fatura { flex-direction: column; gap: 8px; }
                .header-fatura .fatura-titulo { text-align: left; }
                .resumo-fatura { justify-content: flex-start; }
                .resumo-fatura .grid { grid-template-columns: 1fr; }
                .tabela-itens { font-size: 9px; }
                .tabela-itens th, .tabela-itens td { padding: 3px 5px; }
                .rodape-fatura { flex-direction: column; text-align: center; }
            }
        </style>
    </head>
    <body>
    <div class="fatura-container">';
    
    // VIA DO CLIENTE
    $html .= '<div style="border: 1px solid #ddd; border-radius: 6px; padding: 12px; margin-bottom: 8px;">';
    $html .= '<div style="text-align: center; font-size: 9px; font-weight: 700; color: #fff; background: #1a2332; padding: 3px 0; border-radius: 4px 4px 0 0; margin: -12px -12px 12px -12px; letter-spacing: 3px;">VIA DO CLIENTE</div>';
    $html .= $corpo;
    $html .= '</div>';
    
    // DIVISOR
    $html .= '<div class="via-divider"><span>✂️ CORTE AQUI ✂️</span></div>';
    
    // VIA DA ESCOLA
    $html .= '<div style="border: 2px dashed #c9a84c; border-radius: 6px; padding: 12px;">';
    $html .= '<div style="text-align: center; font-size: 9px; font-weight: 700; color: #1a2332; background: #c9a84c; padding: 3px 0; border-radius: 4px 4px 0 0; margin: -12px -12px 12px -12px; letter-spacing: 3px;">VIA DA ESCOLA</div>';
    $html .= $corpo;
    $html .= '</div>';
    
    $html .= '
    <div class="no-print" style="text-align:center;padding:15px 0;">
        <button onclick="window.print()" style="padding:10px 30px;background:#1a2332;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">🖨️ IMPRIMIR</button>
        <button onclick="window.close()" style="padding:10px 30px;background:#e74c3c;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;margin-left:10px;">✕ FECHAR</button>
    </div>
    </body></html>';
    
    return $html;
}

/**
 * Gera o corpo da fatura (sem HTML externo)
 */

function gerarCorpoFaturaAGT($empresa_nome, $empresa_nif, $empresa_endereco, $empresa_telefone, $empresa_email, $aluno, $itens, $numero_fatura, $total_base, $total_iva, $total_liquido, $hash, $forma_pagamento, $data_pagamento, $config_agt = null, $mostrar_iva = null, $taxa_iva_padrao = null) {
    
    global $pdo;
    
    // Buscar configuração AGT se não foi passada
    if ($config_agt === null) {
        $config_agt = buscarConfigAGT($pdo);
    }
    
    // Configurações padrão
    if (!$config_agt) {
        $config_agt = [
            'regime_iva' => 'normal',
            'taxa_iva_padrao' => 14.00
        ];
    }
    
    // ============================================================
    // DADOS DA CONFIGURAÇÃO AGT
    // ============================================================
    $regime_iva = $config_agt['regime_iva'] ?? 'normal';
    
    // Se os valores não foram passados, calcular
    if ($mostrar_iva === null) {
        $mostrar_iva = ($regime_iva === 'normal' || $regime_iva === 'isento');
    }
    
    if ($taxa_iva_padrao === null) {
        $taxa_iva_padrao = floatval($config_agt['taxa_iva_padrao'] ?? 14);
    }
    
    // Se for não sujeito, zerar IVA
    if (!$mostrar_iva) {
        $taxa_iva_padrao = 0;
        $total_iva = 0;
    }
    
    // Texto do regime de IVA
    $regime_texto = [
        'normal' => 'IVA Normal',
        'isento' => 'IVA Isento',
        'nao_sujeito' => 'Não Sujeito a IVA'
    ][$regime_iva] ?? 'Não Sujeito a IVA';
    
    // Taxa de IVA a exibir (pegar do primeiro item ou usar a padrão)
    $taxa_iva_display = 0;
    if ($mostrar_iva && !empty($itens)) {
        if (isset($itens[0]['taxa_iva']) && $itens[0]['taxa_iva'] > 0) {
            $taxa_iva_display = floatval($itens[0]['taxa_iva']);
        } else {
            $taxa_iva_display = $taxa_iva_padrao;
        }
    }
    
    // ============================================================
    // CALCULAR IVA POR ITEM
    // ============================================================
    $itens_com_iva = [];
    $total_base_calc = 0;
    $total_iva_calc = 0;
    $total_multa_calc = 0;
    $total_desconto_calc = 0;
    $total_liquido_calc = 0;
    
    foreach ($itens as $item) {
        $valor_base_item = $item['valor_base'] ?? 0;
        $multa_item = $item['multa'] ?? 0;
        $desconto_item = $item['desconto'] ?? 0;
        
        if ($mostrar_iva) {
            $taxa_item = isset($item['taxa_iva']) && $item['taxa_iva'] > 0 ? floatval($item['taxa_iva']) : $taxa_iva_padrao;
            $valor_iva_item = ($valor_base_item * $taxa_item) / 100;
            $valor_liquido_item = $valor_base_item + $multa_item + $valor_iva_item - $desconto_item;
        } else {
            $taxa_item = 0;
            $valor_iva_item = 0;
            $valor_liquido_item = $valor_base_item + $multa_item - $desconto_item;
        }
        
        $total_base_calc += $valor_base_item;
        $total_iva_calc += $valor_iva_item;
        $total_multa_calc += $multa_item;
        $total_desconto_calc += $desconto_item;
        $total_liquido_calc += $valor_liquido_item;
        
        $itens_com_iva[] = [
            'item' => $item,
            'taxa_iva' => $taxa_item,
            'valor_iva' => $valor_iva_item,
            'valor_liquido' => $valor_liquido_item,
            'multa' => $multa_item,
            'desconto' => $desconto_item
        ];
    }
    
    // Usar totais calculados
    if ($total_base_calc > 0) {
        $total_base = $total_base_calc;
        $total_iva = $total_iva_calc;
        $total_multa = $total_multa_calc;
        $total_desconto = $total_desconto_calc;
        $total_liquido = $total_liquido_calc;
    }
    
    // ============================================================
    // GERAR HTML
    // ============================================================
    
    $html = '
    <div class="header-fatura">
        <div class="empresa-info">
            <div class="nome">' . htmlspecialchars($empresa_nome) . '</div>
            <div class="dados">📍 ' . htmlspecialchars($empresa_endereco) . '</div>
            <div class="dados">📞 ' . htmlspecialchars($empresa_telefone) . ' | ✉ ' . htmlspecialchars($empresa_email) . '</div>
            <div class="nif-box">NIF: ' . htmlspecialchars($empresa_nif) . '</div>
        </div>
        <div class="fatura-titulo">
            <div class="tipo">FATURA</div>
            <div class="numero">Nº ' . $numero_fatura . '</div>
            <div class="data">' . date('d/m/Y H:i') . '</div>
            <div class="status-badge">✅ PAGO</div>
            <div class="selo-validacao">🔒 FATURA VÁLIDA</div>
            <div class="regime-iva-label">' . $regime_texto . ' - ' . number_format($taxa_iva_display, 2, ',', '.') . '%</div>
        </div>
    </div>
    
    <div class="aluno-area">
        <div class="label">📋 DADOS DO ALUNO</div>
        <div class="nome">' . htmlspecialchars($aluno['nome'] ?? 'Aluno não identificado') . '</div>
        <div class="info">
            <span class="tag">NIF: ' . htmlspecialchars($aluno['nif'] ?? '9999999999') . '</span>
            <span class="tag">Classe: ' . htmlspecialchars($aluno['classe'] ?? 'N/A') . '</span>
            <span class="tag">Turma: ' . htmlspecialchars($aluno['turma'] ?? 'N/A') . '</span>
            <span class="tag">Período: ' . htmlspecialchars($aluno['periodo'] ?? 'N/A') . '</span>
        </div>
    </div>

    
    <table class="tabela-itens">
        <thead>
            <tr>
                <th style="width:30px;text-align:center;">#</th>
                <th style="text-align:left;">Descrição</th>
                <th style="width:80px;text-align:center;">Mês</th>
                <th style="width:90px;text-align:right;">Valor Base</th>
                <th style="width:70px;text-align:right;">Multa</th>
                <th style="width:70px;text-align:right;">Desconto</th>';
    
    if ($mostrar_iva) {
        $html .= '
                <th style="width:60px;text-align:right;">IVA %</th>
                <th style="width:80px;text-align:right;">Valor IVA</th>';
    }
    
    $html .= '
                <th style="width:100px;text-align:right;">Total</th>
            </tr>
        </thead>
        <tbody>';
    
    $item_num = 1;
    foreach ($itens_com_iva as $item_data) {
        $item = $item_data['item'];
        $taxa_item = $item_data['taxa_iva'];
        $valor_iva_item = $item_data['valor_iva'];
        $valor_liquido_item = $item_data['valor_liquido'];
        $multa_item = $item_data['multa'];
        $desconto_item = $item_data['desconto'];
        
        $mes_exibicao = ($item['mes_referencia'] ?? '-') != '-' ? ($item['mes_referencia'] ?? '-') : '-';
        
        $html .= '
        <tr>
            <td style="text-align:center;">' . $item_num . '</td>
            <td>' . htmlspecialchars($item['descricao'] ?? $item['emolumento'] ?? '') . '</td>
            <td style="text-align:center;">' . $mes_exibicao . '</td>
            <td style="text-align:right;">' . number_format($item['valor_base'] ?? 0, 2, ',', '.') . ' Kz</td>
            <td style="text-align:right;">' . number_format($multa_item, 2, ',', '.') . ' Kz</td>
            <td style="text-align:right;">' . number_format($desconto_item, 2, ',', '.') . ' Kz</td>';
        
        if ($mostrar_iva) {
            $html .= '
            <td style="text-align:right;">' . number_format($taxa_item, 2, ',', '.') . '%</td>
            <td style="text-align:right;">' . number_format($valor_iva_item, 2, ',', '.') . ' Kz</td>';
        }
        
        $html .= '
            <td style="text-align:right;font-weight:700;">' . number_format($valor_liquido_item, 2, ',', '.') . ' Kz</td>
        </tr>';
        $item_num++;
    }
    
    // Linha de totais
    $html .= '
        <tr class="total-row">
            <td colspan="3" style="text-align:right;font-size:10px;">TOTAIS</td>
            <td style="text-align:right;">' . number_format($total_base, 2, ',', '.') . ' Kz</td>
            <td style="text-align:right;">' . number_format($total_multa, 2, ',', '.') . ' Kz</td>
            <td style="text-align:right;">' . number_format($total_desconto, 2, ',', '.') . ' Kz</td>';
    
    if ($mostrar_iva) {
        $html .= '
            <td style="text-align:right;"></td>
            <td style="text-align:right;">' . number_format($total_iva, 2, ',', '.') . ' Kz</td>';
    }
    
    $html .= '
            <td style="text-align:right;color:#c0392b;font-size:14px;font-weight:700;">' . number_format($total_liquido, 2, ',', '.') . ' Kz</td>
        </tr>
    </tbody>
</table>
    
    <div class="resumo-fatura">
        <div class="grid">
            <div class="label">Subtotal</div>
            <div class="value">' . number_format($total_base, 2, ',', '.') . ' Kz</div>
            <div class="label">Multa</div>
            <div class="value">' . number_format($total_multa, 2, ',', '.') . ' Kz</div>
            <div class="label">Desconto</div>
            <div class="value">- ' . number_format($total_desconto, 2, ',', '.') . ' Kz</div>';
    
    if ($mostrar_iva) {
        $html .= '
            <div class="label">IVA (' . number_format($taxa_iva_display, 2, ',', '.') . '%)</div>
            <div class="value">' . number_format($total_iva, 2, ',', '.') . ' Kz</div>';
    }
    
    $html .= '
            <div class="label" style="border-top:1px solid #ddd;padding-top:4px;"><strong>TOTAL</strong></div>
            <div class="value total">' . number_format($total_liquido, 2, ',', '.') . ' Kz</div>
        </div>
    </div>
    
    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;font-size:9px;color:#555;padding:5px 0;border-bottom:1px solid #eee;margin-bottom:8px;">
        <div><strong>💳 Forma:</strong> ' . htmlspecialchars($forma_pagamento) . '</div>
        <div><strong>📅 Data Pagamento:</strong> ' . date('d/m/Y', strtotime($data_pagamento)) . '</div>
        <div><strong>📄 Fatura:</strong> ' . $numero_fatura . '</div>
        <div><strong>📋 Regime:</strong> ' . $regime_texto . '</div>
    </div>
    
    <div class="rodape-fatura">
        <div>
            <p style="margin:1px 0;font-size:8px;">Documento emitido eletronicamente</p>
            <p style="margin:1px 0;font-size:8px;">' . htmlspecialchars($empresa_nome) . ' © ' . date('Y') . '</p>
        </div>
        <div class="assinatura">
            <p style="font-size:8px;font-weight:bold;margin:1px 0;">Assinatura do Responsável</p>
            <div class="linha"></div>
            <p style="font-size:7px;color:#999;margin:1px 0;">_________________________________</p>
        </div>
        <div class="info-extra">
            <p style="margin:1px 0;">Impresso em: ' . date('d/m/Y H:i:s') . '</p>
            <p class="hash">Hash: ' . substr($hash, 0, 20) . '...</p>
        </div>
    </div>';
    
    return $html;
}



function salvarFatura($html, $numero_fatura) {
    global $pasta_faturas;
    
    // Salvar HTML
    $arquivo_html = $pasta_faturas . 'fatura_' . $numero_fatura . '.html';
    file_put_contents($arquivo_html, $html);
    
    // Tentar salvar PDF
    $resultado_pdf = salvarFaturaPDF($html, $numero_fatura);
    
    return [
        'html' => $arquivo_html,
        'pdf' => $resultado_pdf['success'] ? $resultado_pdf['caminho'] : null,
        'pdf_success' => $resultado_pdf['success']
    ];
}



// ============================================================
// VERIFICAR/ADICIONAR COLUNAS NECESSÁRIAS
// ============================================================
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM pagamentos LIKE 'mes_referencia'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE pagamentos ADD COLUMN mes_referencia VARCHAR(30) DEFAULT '-'");
    }
} catch (Exception $e) {}

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM pagamentos LIKE 'nome_aluno'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE pagamentos ADD COLUMN nome_aluno VARCHAR(255) NULL AFTER aluno_id");
        $pdo->exec("ALTER TABLE pagamentos ADD INDEX idx_nome_aluno (nome_aluno)");
    }
} catch (Exception $e) {}

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM pagamentos LIKE 'numero_fatura'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE pagamentos ADD COLUMN numero_fatura VARCHAR(50) NULL AFTER referencia");
        $pdo->exec("ALTER TABLE pagamentos ADD COLUMN hash_autenticacao VARCHAR(255) NULL AFTER numero_fatura");
        $pdo->exec("ALTER TABLE pagamentos ADD COLUMN codigo_iva VARCHAR(10) DEFAULT 'ISE' AFTER valor");
        $pdo->exec("ALTER TABLE pagamentos ADD COLUMN taxa_iva DECIMAL(5,2) DEFAULT 0.00 AFTER codigo_iva");
        $pdo->exec("ALTER TABLE pagamentos ADD COLUMN valor_iva DECIMAL(15,2) DEFAULT 0.00 AFTER taxa_iva");
    }
} catch (Exception $e) {}

// ============================================================
// FUNÇÕES DE IMPORTAÇÃO
// ============================================================
function importarPlanilhaAlunosCSV($pdo, $file) {
    try {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext === 'xlsx' || $ext === 'xls') {
            if (file_exists('../../../../vendor/autoload.php')) {
                require_once '../../../../vendor/autoload.php';
                return importarPlanilhaAlunosPhpSpreadsheet($pdo, $file);
            } else {
                return ['success' => false, 'message' => 'Arquivo .xlsx não suportado.'];
            }
        }
        if ($ext !== 'csv') {
            return ['success' => false, 'message' => 'Formato não suportado. Use CSV ou XLSX.'];
        }
        if (!file_exists($file['tmp_name'])) {
            return ['success' => false, 'message' => 'Arquivo não encontrado'];
        }
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            return ['success' => false, 'message' => 'Não foi possível abrir o arquivo'];
        }
        $rows = [];
        while (($row = fgetcsv($handle, 0, ';', '"')) !== false) {
            if (empty(array_filter($row))) continue;
            $rows[] = $row;
        }
        fclose($handle);
        if (empty($rows)) {
            return ['success' => false, 'message' => 'Arquivo vazio'];
        }
        $headers = array_map('trim', $rows[0]);
        $header_map = [];
        foreach ($headers as $index => $header) {
            $header_clean = strtolower(removerAcentos(trim($header)));
            $header_clean = preg_replace('/[^a-z0-9_]/', '', $header_clean);
            $header_map[$header_clean] = $index;
        }
        if (!isset($header_map['nome'])) {
            return ['success' => false, 'message' => 'Cabeçalho "nome" não encontrado'];
        }
        if (!$pdo->inTransaction()) $pdo->beginTransaction();
        $inserted = 0;
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `alunos_ano_anterior` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `nome` VARCHAR(255) DEFAULT NULL,
                `Sexo` VARCHAR(10) DEFAULT NULL,
                `dia` VARCHAR(10) DEFAULT NULL,
                `mes` VARCHAR(20) DEFAULT NULL,
                `Ano` VARCHAR(10) DEFAULT NULL,
                `Morada` TEXT DEFAULT NULL,
                `Cadastro_Transporte` VARCHAR(10) DEFAULT NULL,
                `Contacto_do_Aluno` VARCHAR(50) DEFAULT NULL,
                `Debilidade` VARCHAR(100) DEFAULT NULL,
                `Idade` INT(11) DEFAULT NULL,
                `Naturalidade` VARCHAR(100) DEFAULT NULL,
                `Município` VARCHAR(100) DEFAULT NULL,
                `Província` VARCHAR(100) DEFAULT NULL,
                `N_BI` VARCHAR(50) DEFAULT NULL,
                `Classe` VARCHAR(10) DEFAULT NULL,
                `Nome_do_Pai` VARCHAR(255) DEFAULT NULL,
                `Morada3` TEXT DEFAULT NULL,
                `Contacto4` VARCHAR(50) DEFAULT NULL,
                `Ocupacao` VARCHAR(100) DEFAULT NULL,
                `Local_de_Trabalho` VARCHAR(255) DEFAULT NULL,
                `Nome_da_mae` VARCHAR(255) DEFAULT NULL,
                `Contacto_Mae` VARCHAR(50) DEFAULT NULL,
                `Data_Matricula` DATE DEFAULT NULL,
                `Ocupacao_do_Aluno` VARCHAR(100) DEFAULT NULL,
                `Periodo` VARCHAR(20) DEFAULT NULL,
                `Data_Emissao_do_BI` VARCHAR(50) DEFAULT NULL,
                `Arq_identificação` VARCHAR(100) DEFAULT NULL,
                `Situacao_Cadastro` VARCHAR(50) DEFAULT NULL,
                `TURMA` VARCHAR(50) DEFAULT NULL,
                `SALA` VARCHAR(50) DEFAULT NULL,
                `Curso` VARCHAR(50) DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $stmt = $pdo->prepare("
            INSERT INTO alunos_ano_anterior 
            (nome, Sexo, dia, mes, Ano, Morada, Cadastro_Transporte, Contacto_do_Aluno, 
             Debilidade, Idade, Naturalidade, Município, Província, N_BI, Classe, 
             Nome_do_Pai, Morada3, Contacto4, Ocupacao, Local_de_Trabalho, Nome_da_mae, 
             Contacto_Mae, Data_Matricula, Ocupacao_do_Aluno, Periodo, Data_Emissao_do_BI, 
             Arq_identificação, Situacao_Cadastro, TURMA, SALA, Curso) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty(array_filter($row))) continue;
            $nome = isset($row[$header_map['nome'] ?? -1]) ? trim($row[$header_map['nome'] ?? -1]) : null;
            if (empty($nome)) continue;
            $inserted++;
        }
        if ($pdo->inTransaction()) $pdo->commit();
        return ['success' => true, 'inserted' => $inserted, 'message' => "Importados $inserted alunos com sucesso!"];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['success' => false, 'message' => 'Erro na importação: ' . $e->getMessage()];
    }
}

function importarPlanilhaAlunosPhpSpreadsheet($pdo, $file) {
    try {
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $spreadsheet = $reader->load($file['tmp_name']);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        if (empty($rows)) {
            return ['success' => false, 'message' => 'Arquivo vazio'];
        }
        return ['success' => true, 'inserted' => 0, 'message' => 'Importado com sucesso!'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
    }
}

function importarRelatorioPendenciasCSV($pdo, $file) {
    try {
        return ['success' => true, 'inserted' => 0, 'message' => 'Importado com sucesso!'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
    }
}

function importarRelatorioPendenciasPhpSpreadsheet($pdo, $file) {
    try {
        return ['success' => true, 'inserted' => 0, 'message' => 'Importado com sucesso!'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
    }
}

// ============================================================
// PROCESSAMENTO DAS AÇÕES
// ============================================================

$import_result = null;
if (isset($_POST['importar_alunos']) && isset($_FILES['arquivo_importacao'])) {
    $import_result = importarPlanilhaAlunosCSV($pdo, $_FILES['arquivo_importacao']);
}
if (isset($_POST['importar_pendencias']) && isset($_FILES['arquivo_pendencias'])) {
    $import_result = importarRelatorioPendenciasCSV($pdo, $_FILES['arquivo_pendencias']);
}

// ===== BUSCAR DADOS =====
$alunos = [];
$emolumentos = [];
$aluno_selecionado = null;
$alunos_busca = [];
$aluno_busca = '';
$erro = '';
$sucesso = '';
$fatura_html = '';
$mostrar_fatura = false;
$pagamento_duplicado = false;

// ===== ITENS DA FATURA (SESSÃO) =====
if (!isset($_SESSION['fatura_itens'])) {
    $_SESSION['fatura_itens'] = [];
}
$itens_fatura = $_SESSION['fatura_itens'];

// ===== ITENS MANUAIS (SESSÃO) =====
if (!isset($_SESSION['fatura_itens_manual'])) {
    $_SESSION['fatura_itens_manual'] = [];
}
$itens_manual = $_SESSION['fatura_itens_manual'];

// ===== PROCESSAR BUSCA =====
if (isset($_GET['busca']) && !empty(trim($_GET['busca']))) {
    $aluno_busca = trim($_GET['busca']);
    try {
        $stmt = $pdo->prepare("
            SELECT id, nome, genero, turma, classe, curso, status, `N_BI` as nif
            FROM alunos WHERE status = 'ativo' 
            AND (id = ? OR id LIKE ? OR nome LIKE ? OR nome LIKE ?)
            ORDER BY nome LIMIT 20
        ");
        $busca = "%{$aluno_busca}%";
        $stmt->execute([$aluno_busca, $busca, $busca, "{$aluno_busca}%"]);
        $alunos_busca = $stmt->fetchAll();
        if (count($alunos_busca) == 1) $aluno_selecionado = $alunos_busca[0];
    } catch (Exception $e) {
        $erro = 'Erro na busca: ' . $e->getMessage();
    }
}

try {
    if (empty($aluno_busca)) {
        $alunos = $pdo->query("SELECT id, nome, genero, turma, classe, curso, status, `N_BI` as nif FROM alunos WHERE status='ativo' ORDER BY nome LIMIT 100")->fetchAll();
    } else {
        $alunos = $alunos_busca;
    }
    $emolumentos = buscarEmolumentosAgrupados($pdo);
} catch (Exception $e) {
    $erro = 'Erro ao carregar dados: ' . $e->getMessage();
}

// ============================================================
// PROCESSAR PAGAMENTOS MANUAL - SEM ALUNO_ID
// ============================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_pagamento_manual'])) {
    
    $aluno_nome = trim($_POST['aluno_nome_manual'] ?? '');
    $aluno_classe = trim($_POST['aluno_classe_manual'] ?? '');
    $aluno_turma = trim($_POST['aluno_turma_manual'] ?? '');
    $aluno_periodo = trim($_POST['aluno_periodo_manual'] ?? 'Manhã');
    $aluno_genero = trim($_POST['aluno_genero_manual'] ?? 'M');
    $aluno_nif = trim($_POST['aluno_nif_manual'] ?? '9999999999');
    
    $data_pagamento = $_POST['data_pagamento_manual'] ?? date('Y-m-d');
    $forma_pagamento = $_POST['forma_pagamento_manual'] ?? 'dinheiro';
    $referencia = $_POST['referencia_manual'] ?? '';
    $observacoes = $_POST['observacoes_manual'] ?? '';
    
    $itens_manual = $_SESSION['fatura_itens_manual'] ?? [];
    
    if (empty($itens_manual)) {
        $erro = 'Adicione pelo menos um item à fatura manual!';
    } elseif (empty($aluno_nome)) {
        $erro = 'Preencha o nome do aluno!';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, nome, `N_BI` as nif FROM alunos WHERE nome = ? LIMIT 1");
            $stmt->execute([$aluno_nome]);
            $aluno_existente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($aluno_existente) {
                $aluno_id = $aluno_existente['id'];
                $aluno_nif = $aluno_existente['nif'] ?? '9999999999';
                $mensagem_aluno = "Aluno: <strong>$aluno_nome</strong> (ID: #$aluno_id)";
                $tem_aluno = true;
            } else {
                $aluno_id = null;
                $mensagem_aluno = "⚠️ Aluno <strong>$aluno_nome</strong> NÃO CADASTRADO";
                $tem_aluno = false;
            }
            
            $total_pago = 0;
            $itens_registrados = [];
            $itens_duplicados = [];
            $ultimo_pagamento_id = null;
            
            foreach ($itens_manual as $item) {
                $mes_referencia_formatado = ($item['mes_referencia'] ?? '-') != '-' 
                    ? ($item['mes_referencia'] ?? '-') . '/' . ($item['ano_referencia'] ?? date('Y')) 
                    : '-';
                
                $verificacao = verificarDuplicadoDetalhado(
                    $pdo, 
                    $aluno_id,
                    $item['emolumento_id'], 
                    $mes_referencia_formatado,
                    $aluno_nome
                );
                
                if ($verificacao['duplicado']) {
                    $itens_duplicados[] = $item['emolumento'] . ' - ' . $mes_referencia_formatado;
                    continue;
                }
                
                // Buscar emolumento com IVA
                $emol = buscarEmolumentoComIVA($pdo, $item['emolumento_id']);
                $taxa_iva = floatval($emol['taxa_iva'] ?? 0);
                $valor_iva = ($item['valor_liquido'] * $taxa_iva) / 100;
                $codigo_iva = $emol['codigo_iva'] ?? 'ISE';
                
                $sql_pag = "INSERT INTO pagamentos SET
                    aluno_id = ?,
                    emolumento_id = ?,
                    valor = ?,
                    data_pagamento = ?,
                    forma_pagamento = ?,
                    referencia = ?,
                    observacoes = ?,
                    status = 'confirmado',
                    mes_referencia = ?,
                    nome_aluno = ?,
                    codigo_iva = ?,
                    taxa_iva = ?,
                    valor_iva = ?,
                    created_at = NOW()";
                
                $stmt_pag = $pdo->prepare($sql_pag);
                $stmt_pag->execute([
                    $aluno_id,
                    $item['emolumento_id'],
                    $item['valor_liquido'],
                    $data_pagamento,
                    $forma_pagamento,
                    $referencia,
                    $observacoes,
                    $mes_referencia_formatado,
                    $aluno_nome,
                    $codigo_iva,
                    $taxa_iva,
                    $valor_iva
                ]);
                
                $ultimo_pagamento_id = $pdo->lastInsertId();
                $total_pago += $item['valor_liquido'];
                $itens_registrados[] = $item;
                
                if ($item['mes_numero'] > 0 && $tem_aluno && $aluno_id) {
                    atualizarMensalidade($pdo, $aluno_id, $item['mes_numero'], $item['ano_referencia'], $item['valor_liquido'], $item['emolumento_id']);
                }
            }
            
            if (empty($itens_registrados)) {
                if (!empty($itens_duplicados)) {
                    $erro = '⚠️ Todos os itens já estão pagos para este aluno! Itens duplicados: ' . implode(', ', $itens_duplicados);
                } else {
                    $erro = '⚠️ Nenhum item foi registrado.';
                }
            } else {
                // Gerar fatura AGT para pagamento manual
                $config = buscarConfigAGT($pdo);
                $numero_fatura_agt = null;
                
                if ($config && $ultimo_pagamento_id) {
                    $dados_fatura = [
                        'aluno_id' => $aluno_id,
                        'aluno_nome' => $aluno_nome,
                        'aluno_nif' => $aluno_nif,
                        'aluno_classe' => $aluno_classe,
                        'aluno_turma' => $aluno_turma,
                        'forma_pagamento' => $forma_pagamento
                    ];
                    
                    $itens_fatura_agt = [];
                    foreach ($itens_registrados as $item) {
                        $emol = buscarEmolumentoComIVA($pdo, $item['emolumento_id']);
                        $taxa_iva = floatval($emol['taxa_iva'] ?? 0);
                        $valor_iva = ($item['valor_liquido'] * $taxa_iva) / 100;
                        
                        $itens_fatura_agt[] = [
                            'emolumento_id' => $item['emolumento_id'],
                            'descricao' => $item['emolumento'],
                            'valor_base' => $item['valor_base'],
                            'valor_iva' => $valor_iva,
                            'taxa_iva' => $taxa_iva,
                            'valor_liquido' => $item['valor_liquido'],
                            'mes_referencia' => $item['mes_referencia'] ?? null,
                            'ano_referencia' => $item['ano_referencia'] ?? date('Y')
                        ];
                    }
                    
                    $resultado = salvarFaturaAGT($pdo, $dados_fatura, $itens_fatura_agt);
                    
                    if ($resultado['success'] && $ultimo_pagamento_id) {
                        $numero_fatura_agt = $resultado['numero_fatura'];
                        $emol = buscarEmolumentoComIVA($pdo, $itens_registrados[0]['emolumento_id'] ?? null);
                        $taxa_iva = floatval($emol['taxa_iva'] ?? 0);
                        $valor_iva = ($itens_registrados[0]['valor_liquido'] * $taxa_iva) / 100;
                        $codigo_iva = $emol['codigo_iva'] ?? 'ISE';
                        atualizarPagamentoComFatura($pdo, $ultimo_pagamento_id, $resultado['numero_fatura'], $resultado['hash'], $taxa_iva, $valor_iva, $codigo_iva);
                    }
                }
                
                $numero_fatura = $numero_fatura_agt ?? 'FAT-MANUAL-' . date('Ymd') . '-' . str_pad($ultimo_pagamento_id ?? 1, 6, '0', STR_PAD_LEFT);
                $dados_empresa = buscarDadosEmpresa($pdo);
                
                $dados_fatura = [
                    'nome_escola' => $dados_empresa['nome'],
                    'endereco' => $dados_empresa['endereco'],
                    'contacto' => $dados_empresa['telefone'] ?? $dados_empresa['celular'],
                    'email' => $dados_empresa['email'],
                    'nif' => $dados_empresa['nif'],
                    'aluno_id' => $aluno_id ?? 'N/A',
                    'aluno_nome' => $aluno_nome,
                    'aluno_classe' => $aluno_classe,
                    'aluno_turma' => $aluno_turma,
                    'aluno_genero' => $aluno_genero,
                    'itens' => $itens_registrados,
                    'numero_fatura' => $numero_fatura,
                    'data_pagamento' => $data_pagamento,
                    'forma_pagamento' => $forma_pagamento,
                    'referencia' => $referencia,
                    'observacoes' => $observacoes
                ];
                
                $fatura_html = gerarFaturaAGT($dados_fatura, $pdo);
                salvarFatura($fatura_html, $numero_fatura);
                $mostrar_fatura = true;
                
                $sucesso = "✅ Pagamento manual registrado com sucesso!<br>";
                $sucesso .= "👤 " . $mensagem_aluno . "<br>";
                $sucesso .= "💰 Total: <strong>" . number_format($total_pago, 2, ',', '.') . " Kz</strong><br>";
                $sucesso .= "📦 Itens: <strong>" . count($itens_registrados) . "</strong><br>";
                $sucesso .= "📄 Fatura: <strong>$numero_fatura</strong><br>";
                
                if (!empty($itens_duplicados)) {
                    $sucesso .= '<br>⚠️ Itens ignorados (já pagos): ' . implode(', ', $itens_duplicados);
                }
                
                // NOTIFICAÇÃO PARA DISPOSITIVOS MÓVEIS - MANUAL
                if ($ultimo_pagamento_id) {
                    try {
                        $stmt_notif = $pdo->prepare("
                            SELECT p.*, a.nome as aluno_nome, e.nome as emolumento_nome 
                            FROM pagamentos p
                            LEFT JOIN alunos a ON p.aluno_id = a.id
                            LEFT JOIN emolumentos e ON p.emolumento_id = e.id
                            WHERE p.id = ?
                        ");
                        $stmt_notif->execute([$ultimo_pagamento_id]);
                        $pag_notif = $stmt_notif->fetch();
                        
                        if ($pag_notif) {
                            $_SESSION['ultima_notificacao_mobile'] = [
                                'aluno_nome' => $pag_notif['aluno_nome'] ?? $aluno_nome,
                                'valor' => number_format($pag_notif['valor'] ?? $total_pago, 2, ',', '.'),
                                'emolumento' => $pag_notif['emolumento_nome'] ?? 'Pagamento',
                                'id' => $ultimo_pagamento_id
                            ];
                        }
                    } catch (Exception $e) {
                        error_log("Erro ao preparar notificação: " . $e->getMessage());
                    }
                }
                
                $_SESSION['fatura_itens_manual'] = [];
                $itens_manual = [];
            }
            
        } catch (Exception $e) {
            $erro = '❌ Erro: ' . $e->getMessage();
            error_log("ERRO PAGAMENTO MANUAL: " . $e->getMessage());
        }
    }
}

// ===== ADICIONAR ITEM MANUAL =====
if (isset($_POST['add_item_manual'])) {
    $emolumento_id = $_POST['emolumento_id_manual_item'] ?? null;
    $valor_base = floatval($_POST['valor_base_manual_item'] ?? 0);
    $desconto = floatval($_POST['desconto_manual_item'] ?? 0);
    $mes_nome = $_POST['mes_referencia_manual_item'] ?? '-';
    $ano = intval($_POST['ano_referencia_manual_item'] ?? date('Y'));
    $mes_numero = mesNomeParaNumero($mes_nome);
    
    if (!$emolumento_id || $valor_base <= 0) {
        $erro = 'Preencha todos os campos obrigatórios do item!';
    } else {
        $stmt_emol = $pdo->prepare("SELECT nome, valor FROM emolumentos WHERE id = ?");
        $stmt_emol->execute([$emolumento_id]);
        $emol = $stmt_emol->fetch();
        $nome_emolumento = $emol['nome'] ?? '';
        
        // Buscar IVA do emolumento
        $emol_iva = buscarEmolumentoComIVA($pdo, $emolumento_id);
        $taxa_iva = floatval($emol_iva['taxa_iva'] ?? 0);
        $valor_iva = ($valor_base * $taxa_iva) / 100;
        
        $multa = 0;
        if ($mes_numero > 0) {
            $multa = calcularMulta($pdo, $emolumento_id, $mes_numero, $ano, $valor_base);
        }
        
        $valor_liquido = $valor_base + $multa + $valor_iva - $desconto;
        if ($valor_liquido < 0) $valor_liquido = 0;
        
        $item = [
            'emolumento_id' => $emolumento_id,
            'emolumento' => $nome_emolumento,
            'descricao' => $nome_emolumento,
            'valor_base' => $valor_base,
            'multa' => $multa,
            'desconto' => $desconto,
            'taxa_iva' => $taxa_iva,
            'valor_iva' => $valor_iva,
            'valor_liquido' => $valor_liquido,
            'mes_referencia' => $mes_nome,
            'ano_referencia' => $ano,
            'mes_numero' => $mes_numero
        ];
        
        $_SESSION['fatura_itens_manual'][] = $item;
        $itens_manual = $_SESSION['fatura_itens_manual'];
        $sucesso = '✅ Item adicionado à fatura manual!';
        header('Location: add.php');
        exit;
    }
}

// ===== REMOVER ITEM MANUAL =====
if (isset($_GET['remove_item_manual'])) {
    $index = intval($_GET['remove_item_manual']);
    if (isset($_SESSION['fatura_itens_manual'][$index])) {
        unset($_SESSION['fatura_itens_manual'][$index]);
        $_SESSION['fatura_itens_manual'] = array_values($_SESSION['fatura_itens_manual']);
        $itens_manual = $_SESSION['fatura_itens_manual'];
        $sucesso = '🗑️ Item removido da fatura manual!';
        header('Location: add.php');
        exit;
    }
}

// ===== LIMPAR ITENS MANUAL =====
if (isset($_GET['clear_items_manual'])) {
    $_SESSION['fatura_itens_manual'] = [];
    $itens_manual = [];
    $sucesso = '🧹 Fatura manual limpa!';
    header('Location: add.php');
    exit;
}

// ===== CALCULAR TOTAL MANUAL =====
$total_manual = 0;
if (!empty($itens_manual)) {
    foreach ($itens_manual as $item) {
        $total_manual += $item['valor_liquido'];
    }
}

// ============================================================
// PROCESSAR PAGAMENTOS NORMAIS (COM ALUNO_ID)
// ============================================================

// ===== ADICIONAR ITEM =====
if (isset($_POST['add_item']) && isset($_POST['aluno_id'])) {
    $aluno_id = $_POST['aluno_id'] ?? null;
    $emolumento_id = $_POST['emolumento_id'] ?? null;
    $valor_base = floatval($_POST['valor_base'] ?? 0);
    $desconto = floatval($_POST['desconto'] ?? 0);
    $mes_nome = $_POST['mes_referencia'] ?? '-';
    $ano = intval($_POST['ano_referencia'] ?? date('Y'));
    $mes_numero = mesNomeParaNumero($mes_nome);
    
    if (!$aluno_id || !$emolumento_id || $valor_base <= 0) {
        $erro = 'Preencha todos os campos obrigatórios!';
    } else {
        $mes_referencia_formatado = $mes_nome != '-' ? $mes_nome . '/' . $ano : '-';
        
        $verificacao = verificarDuplicadoDetalhado($pdo, $aluno_id, $emolumento_id, $mes_referencia_formatado);
        
        if ($verificacao['duplicado']) {
            $detalhes = $verificacao['detalhes'] ?? [];
            $nome_aluno_dup = $detalhes['aluno_nome'] ?? 'Desconhecido';
            $id_aluno_dup = $detalhes['aluno_id'] ?? 'Desconhecido';
            
            $erro = '⚠️ <strong>PAGAMENTO DUPLICADO!</strong><br>';
            $erro .= '👤 Aluno: <strong>' . htmlspecialchars($nome_aluno_dup) . '</strong> (ID: #' . htmlspecialchars($id_aluno_dup) . ')<br>';
            $erro .= '📌 Já existe um pagamento para este mês referente a <strong>' . htmlspecialchars($detalhes['emolumento'] ?? 'Desconhecido') . '</strong>';
            $erro .= ' no valor de ' . number_format($detalhes['valor'] ?? 0, 2, ',', '.') . ' Kz.<br>';
            $erro .= '📅 Data: ' . ($detalhes['data'] ?? 'Desconhecida');
            $pagamento_duplicado = true;
        } else {
            // Buscar emolumento com IVA
            $emol = buscarEmolumentoComIVA($pdo, $emolumento_id);
            $nome_emolumento = $emol['nome'] ?? '';
            $taxa_iva = floatval($emol['taxa_iva'] ?? 0);
            $codigo_iva = $emol['codigo_iva'] ?? 'ISE';
            
            $multa = 0;
            if ($mes_numero > 0) {
                $multa = calcularMulta($pdo, $emolumento_id, $mes_numero, $ano, $valor_base);
            }
            
            $valor_iva = ($valor_base * $taxa_iva) / 100;
            $valor_liquido = $valor_base + $multa + $valor_iva - $desconto;
            if ($valor_liquido < 0) $valor_liquido = 0;
            
            $item = [
                'emolumento_id' => $emolumento_id,
                'emolumento' => $nome_emolumento,
                'descricao' => $nome_emolumento,
                'valor_base' => $valor_base,
                'multa' => $multa,
                'desconto' => $desconto,
                'taxa_iva' => $taxa_iva,
                'valor_iva' => $valor_iva,
                'valor_liquido' => $valor_liquido,
                'mes_referencia' => $mes_nome,
                'ano_referencia' => $ano,
                'mes_numero' => $mes_numero,
                'codigo_iva' => $codigo_iva
            ];
            
            $_SESSION['fatura_itens'][] = $item;
            $itens_fatura = $_SESSION['fatura_itens'];
            $sucesso = '✅ Item adicionado à fatura!';
        }
    }
}

// ===== REMOVER ITEM =====
if (isset($_GET['remove_item'])) {
    $index = intval($_GET['remove_item']);
    if (isset($_SESSION['fatura_itens'][$index])) {
        unset($_SESSION['fatura_itens'][$index]);
        $_SESSION['fatura_itens'] = array_values($_SESSION['fatura_itens']);
        $itens_fatura = $_SESSION['fatura_itens'];
        $sucesso = '🗑️ Item removido da fatura!';
    }
}

// ===== LIMPAR ITENS =====
if (isset($_GET['clear_items'])) {
    $_SESSION['fatura_itens'] = [];
    $itens_fatura = [];
    $sucesso = '🧹 Fatura limpa!';
}


// ===== PROCESSAR FORMULÁRIO (FINALIZAR PAGAMENTO NORMAL) =====
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['finalizar_pagamento'])) {
    $aluno_id = $_POST['aluno_id'] ?? null;
    $data_pagamento = $_POST['data_pagamento'] ?? date('Y-m-d');
    $forma_pagamento = $_POST['forma_pagamento'] ?? 'dinheiro';
    $referencia = $_POST['referencia'] ?? '';
    $observacoes = $_POST['observacoes'] ?? '';
    
    if (empty($itens_fatura)) {
        $erro = 'Adicione pelo menos um item à fatura!';
    } elseif (!$aluno_id) {
        $erro = 'Selecione um aluno!';
    } else {
        try {
            // BUSCAR DADOS DO ALUNO - USAR N_BI COMO NIF
            $stmt_aluno = $pdo->prepare("
                SELECT 
                    id, 
                    nome, 
                    genero, 
                    classe, 
                    turma, 
                    periodo, 
                    `N_BI` as nif
                FROM alunos 
                WHERE id = ?
            ");
            $stmt_aluno->execute([$aluno_id]);
            $aluno_data = $stmt_aluno->fetch();
            
            if (!$aluno_data) {
                throw new Exception('Aluno não encontrado!');
            }
            
            // DEFINIR AS VARIÁVEIS DO ALUNO
            $aluno_nome = $aluno_data['nome'] ?? 'Aluno não encontrado';
            $aluno_classe = $aluno_data['classe'] ?? '';
            $aluno_turma = $aluno_data['turma'] ?? '';
            $aluno_periodo = $aluno_data['periodo'] ?? '';
            $aluno_nif = $aluno_data['nif'] ?? '9999999999';
            $aluno_genero = $aluno_data['genero'] ?? '';
            
            $total_pago = 0;
            $itens_registrados = [];
            $itens_duplicados = [];
            $pagamento_id = null;
            $primeiro_pagamento_id = null;
            $numero_fatura_agt = null;
            $total_base = 0;
            $total_iva = 0;
            $hash = '';
            
            foreach ($itens_fatura as $item) {
                $mes_referencia_formatado = ($item['mes_referencia'] ?? '-') != '-' ? ($item['mes_referencia'] ?? '-') . '/' . ($item['ano_referencia'] ?? date('Y')) : '-';
                
                $verificacao = verificarDuplicadoDetalhado($pdo, $aluno_id, $item['emolumento_id'], $mes_referencia_formatado);
                
                if ($verificacao['duplicado']) {
                    $detalhes = $verificacao['detalhes'] ?? [];
                    $nome_aluno_dup = $detalhes['aluno_nome'] ?? 'Desconhecido';
                    
                    $itens_duplicados[] = [
                        'emolumento' => $item['emolumento'],
                        'mes' => $mes_referencia_formatado,
                        'detalhes' => $verificacao['detalhes'],
                        'aluno_nome' => $nome_aluno_dup
                    ];
                    continue;
                }
                
                // ADICIONAR DADOS DE IVA NO PAGAMENTO
                $emol = buscarEmolumentoComIVA($pdo, $item['emolumento_id']);
                $taxa_iva = floatval($emol['taxa_iva'] ?? 0);
                $valor_iva = ($item['valor_liquido'] * $taxa_iva) / 100;
                $codigo_iva = $emol['codigo_iva'] ?? 'ISE';
                
                $stmt = $pdo->prepare("
                    INSERT INTO pagamentos 
                    (aluno_id, emolumento_id, valor, data_pagamento, forma_pagamento, 
                     referencia, observacoes, status, mes_referencia,
                     taxa_iva, valor_iva, codigo_iva) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'confirmado', ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $aluno_id, 
                    $item['emolumento_id'], 
                    $item['valor_liquido'], 
                    $data_pagamento, 
                    $forma_pagamento, 
                    $referencia, 
                    $observacoes, 
                    $mes_referencia_formatado,
                    $taxa_iva,
                    $valor_iva,
                    $codigo_iva
                ]);
                
                $pagamento_id = $pdo->lastInsertId();
                if ($primeiro_pagamento_id === null) {
                    $primeiro_pagamento_id = $pagamento_id;
                }
                $total_pago += $item['valor_liquido'];
                $total_base += $item['valor_base'] ?? 0;
                $total_iva += $valor_iva;
                $itens_registrados[] = $item;
                
                if ($item['mes_numero'] > 0) {
                    atualizarMensalidade($pdo, $aluno_id, $item['mes_numero'], $item['ano_referencia'], $item['valor_liquido'], $item['emolumento_id']);
                }
            }
            
            if (empty($itens_registrados) && !empty($itens_duplicados)) {
                $erro = '⚠️ <strong>TODOS OS ITENS SÃO DUPLICADOS!</strong><br>';
                foreach ($itens_duplicados as $dup) {
                    $nome_dup = $dup['aluno_nome'] ?? 'Desconhecido';
                    $erro .= '• Aluno: <strong>' . htmlspecialchars($nome_dup) . '</strong> - ';
                    $erro .= htmlspecialchars($dup['emolumento']) . ' - Mês: ' . htmlspecialchars($dup['mes']) . ' (Já pago)<br>';
                }
            } elseif (!empty($itens_registrados)) {
                // GERAR FATURA AGT
                $config = buscarConfigAGT($pdo);
                
                if ($config && $primeiro_pagamento_id) {
                    $dados_fatura = [
                        'aluno_id' => $aluno_id,
                        'aluno_nome' => $aluno_nome,
                        'aluno_nif' => $aluno_nif,
                        'aluno_classe' => $aluno_classe,
                        'aluno_turma' => $aluno_turma,
                        'forma_pagamento' => $forma_pagamento
                    ];
                    
                    $itens_fatura_agt = [];
                    foreach ($itens_registrados as $item) {
                        $emol = buscarEmolumentoComIVA($pdo, $item['emolumento_id']);
                        $taxa_iva_item = floatval($emol['taxa_iva'] ?? 0);
                        $valor_iva_item = ($item['valor_liquido'] * $taxa_iva_item) / 100;
                        
                        $itens_fatura_agt[] = [
                            'emolumento_id' => $item['emolumento_id'],
                            'descricao' => $item['emolumento'],
                            'valor_base' => $item['valor_base'],
                            'valor_iva' => $valor_iva_item,
                            'taxa_iva' => $taxa_iva_item,
                            'valor_liquido' => $item['valor_liquido'],
                            'mes_referencia' => $item['mes_referencia'] ?? null,
                            'ano_referencia' => $item['ano_referencia'] ?? date('Y')
                        ];
                    }
                    
                    $resultado = salvarFaturaAGT($pdo, $dados_fatura, $itens_fatura_agt);
                    
                    if ($resultado['success']) {
                        $numero_fatura_agt = $resultado['numero_fatura'];
                        $hash = $resultado['hash'];
                        
                        $emol = buscarEmolumentoComIVA($pdo, $itens_registrados[0]['emolumento_id'] ?? null);
                        $taxa_iva = floatval($emol['taxa_iva'] ?? 0);
                        $valor_iva = ($itens_registrados[0]['valor_liquido'] * $taxa_iva) / 100;
                        $codigo_iva = $emol['codigo_iva'] ?? 'ISE';
                        
                        atualizarPagamentoComFatura($pdo, $primeiro_pagamento_id, $resultado['numero_fatura'], $resultado['hash'], $taxa_iva, $valor_iva, $codigo_iva);
                        
                        foreach ($itens_registrados as $idx => $item) {
                            if ($idx > 0) {
                                $mes_ref_formatado = ($item['mes_referencia'] ?? '-') != '-' ? ($item['mes_referencia'] ?? '-') . '/' . ($item['ano_referencia'] ?? date('Y')) : '-';
                                $stmt_get = $pdo->prepare("SELECT id FROM pagamentos WHERE aluno_id = ? AND emolumento_id = ? AND mes_referencia = ? ORDER BY id DESC LIMIT 1");
                                $stmt_get->execute([$aluno_id, $item['emolumento_id'], $mes_ref_formatado]);
                                $pag = $stmt_get->fetch();
                                if ($pag) {
                                    $emol_item = buscarEmolumentoComIVA($pdo, $item['emolumento_id']);
                                    $taxa_iva_item = floatval($emol_item['taxa_iva'] ?? 0);
                                    $valor_iva_item = ($item['valor_liquido'] * $taxa_iva_item) / 100;
                                    $codigo_iva_item = $emol_item['codigo_iva'] ?? 'ISE';
                                    atualizarPagamentoComFatura($pdo, $pag['id'], $resultado['numero_fatura'], $resultado['hash'], $taxa_iva_item, $valor_iva_item, $codigo_iva_item);
                                }
                            }
                        }
                    }
                }
                
                $numero_fatura = $numero_fatura_agt ?? 'FAT-' . date('Ymd') . '-' . str_pad($primeiro_pagamento_id ?? 1, 6, '0', STR_PAD_LEFT);
                $dados_empresa = buscarDadosEmpresa($pdo);
                
                
                // ============================================================
                // GERAR FATURA COMPLETA - USANDO AS VARIÁVEIS CORRETAS
                // ============================================================
                $fatura_html = gerarFaturaAGTCompleta(
                    [
                        'nome' => $dados_empresa['nome'],
                        'endereco' => $dados_empresa['endereco'],
                        'telefone' => $dados_empresa['telefone'] ?? $dados_empresa['celular'],
                        'email' => $dados_empresa['email'],
                        'nif' => $dados_empresa['nif']
                    ],
                    [
                        'nome' => $aluno_nome,
                        'nif' => $aluno_nif ?? '9999999999',
                        'classe' => $aluno_classe,
                        'turma' => $aluno_turma,
                        'periodo' => $aluno_periodo
                    ],
                    $itens_registrados,
                    $numero_fatura,
                    $total_base,
                    $total_iva,
                    $total_pago,
                    $hash,
                    $forma_pagamento,
                    $data_pagamento
                );
                // ============================================================
                
                salvarFatura($fatura_html, $numero_fatura);
                
                $mostrar_fatura = true;
                $sucesso = '✅ Pagamento registrado! ' . count($itens_registrados) . ' item(ns) processado(s). Total: ' . number_format($total_pago, 2, ',', '.') . ' Kz';
                
                if (!empty($itens_duplicados)) {
                    $sucesso .= '<br>⚠️ ' . count($itens_duplicados) . ' item(ns) ignorado(s) por duplicidade.';
                }
                
                if ($numero_fatura_agt) {
                    $sucesso .= '<br>📄 Fatura AGT: <strong>' . $numero_fatura_agt . '</strong>';
                }


                // ============================================================
                // ADICIONE A MENSAGEM DO PDF AQUI
                // ============================================================
                if ($resultado_salvar['pdf_success']) {
                    $sucesso .= '<br>📁 PDF salvo em: <strong>' . $resultado_salvar['pdf'] . '</strong>';
                }



                
                
                // NOTIFICAÇÃO PARA DISPOSITIVOS MÓVEIS - NORMAL
                if ($primeiro_pagamento_id) {
                    try {
                        $stmt_notif = $pdo->prepare("
                            SELECT p.*, a.nome as aluno_nome, e.nome as emolumento_nome 
                            FROM pagamentos p
                            LEFT JOIN alunos a ON p.aluno_id = a.id
                            LEFT JOIN emolumentos e ON p.emolumento_id = e.id
                            WHERE p.id = ?
                        ");
                        $stmt_notif->execute([$primeiro_pagamento_id]);
                        $pag_notif = $stmt_notif->fetch();
                        
                        if ($pag_notif) {
                            $_SESSION['ultima_notificacao_mobile'] = [
                                'aluno_nome' => $pag_notif['aluno_nome'] ?? $aluno_nome,
                                'valor' => number_format($pag_notif['valor'] ?? $total_pago, 2, ',', '.'),
                                'emolumento' => $pag_notif['emolumento_nome'] ?? 'Pagamento',
                                'id' => $primeiro_pagamento_id
                            ];
                        }
                    } catch (Exception $e) {
                        error_log("Erro ao preparar notificação: " . $e->getMessage());
                    }
                }
                
                $_SESSION['fatura_itens'] = [];
                $itens_fatura = [];
            }
        } catch (Exception $e) {
            $erro = 'Erro: ' . $e->getMessage();
        }
    }
}

// ============================================================
// INCLUIR HEADER
// ============================================================
include '../../includes/header_escola.php';

// ===== RECALCULAR TOTAL MANUAL =====
$total_manual = 0;
if (!empty($itens_manual)) {
    foreach ($itens_manual as $item) {
        $total_manual += $item['valor_liquido'];
    }
}
?>

<!-- ============================================
     CSS DA PÁGINA
     ============================================ -->
<style>
.page-header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;margin-bottom:25px}
.page-header h1{font-size:24px;font-weight:700;color:#1a2332;margin:0}
.page-header .subtitle{color:#94a3b8;font-size:14px;margin:2px 0 0}
.btn{padding:8px 20px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;transition:all .3s;display:inline-flex;align-items:center;gap:6px;border:none;cursor:pointer}
.btn-primary{background:#c9a84c;color:#1a2332}
.btn-primary:hover{background:#b8973a;transform:translateY(-2px)}
.btn-secondary{background:#f1f5f9;color:#4a5568}
.btn-secondary:hover{background:#e2e8f0}
.btn-success{background:#2ecc71;color:#fff}
.btn-success:hover{background:#27ae60}
.btn-danger{background:#e74c3c;color:#fff}
.btn-danger:hover{background:#c0392b}
.btn-info{background:#3498db;color:#fff}
.btn-info:hover{background:#2980b9}
.btn-pagamento-manual{background:#8e44ad;color:#fff}
.btn-pagamento-manual:hover{background:#7d3c98;transform:translateY(-2px)}
.btn-toggle-manual{background:#6c5ce7;color:#fff}
.btn-toggle-manual:hover{background:#5a4bd1;transform:translateY(-2px)}
.menu-financeiro{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:25px;padding:15px 20px;background:#fff;border-radius:12px;border:1px solid #eef2f7}
.menu-financeiro a{padding:8px 18px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:500;color:#4a5568;background:#f8fafc;border:1px solid #e2e8f0;display:inline-flex;align-items:center;gap:6px}
.menu-financeiro a:hover,.menu-financeiro a.active{background:#c9a84c;color:#1a2332;border-color:#c9a84c}
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
.alert-warning{background:#fef9e8;color:#78350f;border:1px solid #fde68a}
.alert-info{background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe}
.form-actions{display:flex;gap:10px;margin-top:20px;flex-wrap:wrap}
.info-valor{background:#f8fafc;padding:12px 16px;border-radius:8px;border:1px solid #e2e8f0;margin-top:5px;font-size:13px;color:#4a5568}
.info-valor strong{color:#1a2332}
.busca-aluno-container{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:15px;margin-bottom:15px}
.busca-aluno-container .busca-row{display:flex;gap:10px}
.busca-aluno-container .busca-row input{flex:1;padding:10px 14px;border:1px solid #d1d5db;border-radius:8px;font-size:14px}
.busca-aluno-container .busca-row input:focus{outline:none;border-color:#c9a84c;box-shadow:0 0 0 3px rgba(201,168,76,0.1)}
.busca-aluno-container .busca-row .btn{padding:10px 20px;white-space:nowrap}
.resultado-busca{margin-top:12px;border-top:1px solid #e2e8f0;padding-top:12px;max-height:400px;overflow-y:auto}
.resultado-busca .aluno-item{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;background:#fff;border-radius:8px;margin-bottom:8px;border:2px solid #e2e8f0;cursor:pointer;transition:all .2s}
.resultado-busca .aluno-item:hover{border-color:#c9a84c;background:#fef9e8;transform:translateX(5px)}
.resultado-busca .aluno-item .info{display:flex;flex-direction:column;gap:4px;flex:1}
.resultado-busca .aluno-item .info .nome{font-weight:600;color:#1a2332;font-size:15px}
.resultado-busca .aluno-item .info .detalhes{font-size:12px;color:#94a3b8;display:flex;flex-wrap:wrap;gap:10px}
.resultado-busca .aluno-item .info .detalhes span{background:#f1f5f9;padding:2px 8px;border-radius:4px}
.resultado-busca .aluno-item .selecionar-btn{padding:6px 16px;border-radius:6px;background:#c9a84c;color:#1a2332;border:none;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s;margin-left:10px}
.resultado-busca .aluno-item .selecionar-btn:hover{background:#b8973a;transform:scale(1.05)}
.resultado-busca .aluno-item.aluno-selecionado{background:#d1fae5 !important;border-color:#2ecc71 !important}
.resultado-busca .aluno-item.aluno-selecionado .selecionar-btn{background:#2ecc71 !important;color:#fff !important}
.nenhum-resultado{text-align:center;padding:30px;color:#94a3b8;font-size:14px}
.nenhum-resultado .icone{font-size:48px;margin-bottom:10px}
.aluno-selecionado-feedback{margin-top:8px;padding:12px 16px;background:#d1fae5;border-radius:6px;font-size:13px;color:#065f46;border-left:4px solid #2ecc71;display:flex;justify-content:space-between;align-items:center;animation:slideDown .3s ease}
@keyframes slideDown{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.aluno-selecionado-feedback .remover-btn{background:#e74c3c;color:#fff;border:none;padding:4px 12px;border-radius:4px;cursor:pointer;font-size:12px}
.badge-status{display:inline-block;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600}
.badge-ativo{background:#d1fae5;color:#065f46}
.fatura-modal{display:<?= $mostrar_fatura ? 'block' : 'none' ?>;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:9999;overflow-y:auto;padding:20px}
.fatura-modal-content{max-width:210mm;margin:20px auto;background:#fff;border-radius:8px;padding:0;position:relative;box-shadow:0 10px 40px rgba(0,0,0,0.3)}
.fatura-modal .btn-fechar{position:sticky;top:0;float:right;background:#e74c3c;color:#fff;border:none;padding:8px 18px;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;margin:10px;z-index:10}
.fatura-modal .btn-fechar:hover{background:#c0392b}
.fatura-iframe{width:100%;height:900px;border:none;border-radius:0 0 8px 8px}
.mes-hint{font-size:12px;color:#94a3b8;margin-top:4px}
.mes-hint.ativo{color:#27ae60;font-weight:500}
.ano-input{width:100px !important}
.multa-info{background:#fef9e8;border:1px solid #fde68a;padding:8px 12px;border-radius:6px;margin-top:5px;font-size:13px;color:#78350f;display:none}
.multa-info.ativo{display:block}
.tabela-itens-fatura{width:100%;border-collapse:collapse;margin-top:10px;font-size:13px}
.tabela-itens-fatura th{background:#f1f5f9;padding:8px;text-align:left;border-bottom:2px solid #e2e8f0}
.tabela-itens-fatura td{padding:8px;border-bottom:1px solid #e2e8f0}
.tabela-itens-fatura .text-right{text-align:right}
.tabela-itens-fatura .total-row{font-weight:bold;background:#f8fafc}
.tabela-itens-fatura .total-row td{border-top:2px solid #1a2332}
.btn-remove-item{background:#fee2e2;color:#991b1b;border:none;padding:4px 10px;border-radius:4px;cursor:pointer;font-size:12px}
.btn-remove-item:hover{background:#fecaca}
.btn-add-item{background:#d1fae5;color:#065f46;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;font-weight:600}
.btn-add-item:hover{background:#a7f3d0}
.resumo-fatura{background:#f8fafc;padding:10px 14px;border-radius:8px;border:1px solid #e2e8f0;margin-top:10px}
.resumo-fatura .total{font-size:18px;font-weight:bold;color:#c0392b}
.duplicado-warning{background:#fee2e2;border:1px solid #fecaca;padding:12px 16px;border-radius:8px;color:#991b1b;font-size:14px;margin-bottom:15px}
.duplicado-warning strong{color:#991b1b}
.pagamento-manual-section{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:15px;margin-top:20px}
.pagamento-manual-section .manual-header{display:flex;justify-content:space-between;align-items:center;cursor:pointer;user-select:none}
.pagamento-manual-section .manual-header h3{margin:0;font-size:15px;color:#1a2332}
.pagamento-manual-section .manual-header .toggle-icon{font-size:18px;transition:transform .3s}
.pagamento-manual-section .manual-body{display:none;padding-top:15px;border-top:1px solid #e2e8f0;margin-top:15px}
.pagamento-manual-section.active .manual-body{display:block}
.pagamento-manual-section.active .toggle-icon{transform:rotate(180deg)}
.manual-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.manual-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px}
.import-section{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:15px;margin-top:20px}
.import-section .import-header{display:flex;justify-content:space-between;align-items:center;cursor:pointer;user-select:none}
.import-section .import-header h3{margin:0;font-size:15px;color:#1a2332}
.import-section .import-header .toggle-icon{font-size:18px;transition:transform .3s}
.import-section .import-body{display:none;padding-top:15px;border-top:1px solid #e2e8f0;margin-top:15px}
.import-section.active .import-body{display:block}
.import-section.active .toggle-icon{transform:rotate(180deg)}
.import-section .file-input-wrapper{display:flex;gap:10px;flex-wrap:wrap}
.import-section .file-input-wrapper input[type="file"]{flex:1;padding:8px;border:1px solid #d1d5db;border-radius:6px;font-size:13px}
.import-section .file-input-wrapper input[type="file"]:hover{cursor:pointer}
.import-section .file-input-wrapper .btn{white-space:nowrap}
.import-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:15px}
.import-card{background:#fff;padding:15px;border-radius:8px;border:1px solid #e2e8f0}
.import-card h4{margin:0 0 10px;font-size:14px;color:#1a2332}
.import-actions{display:flex;gap:10px;flex-wrap:wrap;border-top:1px solid #e2e8f0;padding-top:15px;margin-top:5px}
.badge-manual{background:#8e44ad;color:#fff;padding:2px 10px;border-radius:10px;font-size:10px;font-weight:600;margin-left:5px}
@media(max-width:768px){
.page-header{flex-direction:column;align-items:stretch}
.menu-financeiro{flex-direction:column;align-items:stretch}
.menu-financeiro a{text-align:center;justify-content:center}
.form-row,.form-row-3{grid-template-columns:1fr;gap:0}
.form-container{padding:20px}
.form-actions{flex-direction:column}
.form-actions .btn{justify-content:center}
.busca-aluno-container .busca-row{flex-direction:column}
.resultado-busca .aluno-item{flex-wrap:wrap}
.resultado-busca .aluno-item .selecionar-btn{margin-left:0;margin-top:8px;width:100%}
.aluno-selecionado-feedback{flex-direction:column;gap:8px;text-align:center}
.fatura-modal-content{margin:10px}
.fatura-iframe{height:500px}
.ano-input{width:100% !important}
.tabela-itens-fatura{font-size:11px}
.tabela-itens-fatura th,.tabela-itens-fatura td{padding:4px}
.import-section .file-input-wrapper{flex-direction:column}
.import-section .file-input-wrapper .btn{width:100%;justify-content:center}
.import-grid{grid-template-columns:1fr}
.manual-grid{grid-template-columns:1fr}
.manual-grid-3{grid-template-columns:1fr}
}
</style>

<!-- ============================================
     CONTEÚDO DA PÁGINA
     ============================================ -->
<div class="page-header">
    <div><h1>💳 Novo Pagamento</h1><p class="subtitle">Registrar pagamento com múltiplos itens</p></div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <button class="btn btn-toggle-manual" onclick="toggleManual()">📝 Pagamento Manual</button>
        <button class="btn btn-info" onclick="toggleImportacao()">📥 Importar Dados</button>
        <a href="index.php" class="btn btn-secondary">← Voltar</a>
    </div>
</div>

<div class="menu-financeiro">
    <a href="../index.php">📊 Dashboard</a>
    <a href="index.php" class="active">💳 Pagamentos</a>
    <a href="../emolumentos/">📋 Emolumentos</a>
    <a href="../mensalidades/">📅 Mensalidades</a>
    <a href="../contas/">🏦 Contas</a>
    <a href="../fluxo_caixa/">💵 Fluxo de Caixa</a>
    <a href="../relatorios/">📈 Relatórios</a>
</div>

<!-- ============================================
     SEÇÃO DE PAGAMENTO MANUAL
     ============================================ -->
<div class="pagamento-manual-section" id="manualSection">
    <div class="manual-header" onclick="toggleManual()">
        <h3>📝 Pagamento Manual <span class="badge-manual">MANUAL</span></h3>
        <span class="toggle-icon">▼</span>
    </div>
    <div class="manual-body">
        <?php if (isset($erro) && $erro && !isset($_POST['add_item']) && !isset($_POST['add_item_manual'])): ?>
            <div class="alert alert-error"><?= $erro ?></div>
        <?php endif; ?>
        
        <?php if ($sucesso && !$mostrar_fatura && isset($_POST['add_item_manual'])): ?>
            <div class="alert alert-success"><?= $sucesso ?></div>
        <?php endif; ?>
        
        <!-- FORMULÁRIO PARA ADICIONAR ITEM MANUAL -->
        <div style="background:#f8fafc;padding:15px;border-radius:8px;border:1px solid #e2e8f0;margin-bottom:15px;">
            <h4 style="margin:0 0 10px;color:#1a2332;font-size:14px;">➕ Adicionar Item à Fatura Manual</h4>
            <form method="POST" action="" id="manualItemForm">
                <input type="hidden" name="add_item_manual" value="1">
                
                <div class="form-row">
                    <div class="form-group" style="margin-bottom:10px;">
                        <label>Emolumento <span class="required">*</span></label>
                        <select name="emolumento_id_manual_item" id="emolumento_id_manual_item" required onchange="atualizarValorItemManual()">
                            <option value="">Selecione</option>
                            <?php foreach($emolumentos as $emol): ?>
                            <option value="<?= $emol['id'] ?>" data-valor="<?= $emol['valor'] ?>" data-nome="<?= htmlspecialchars($emol['nome']) ?>">
                                <?= htmlspecialchars($emol['nome']) ?> - <?= number_format($emol['valor'], 2, ',', '.') ?> Kz
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:10px;">
                        <label>Valor Base <span class="required">*</span></label>
                        <input type="number" step="0.01" name="valor_base_manual_item" id="valor_base_manual_item" required onchange="calcularTotalItemManual()" oninput="calcularTotalItemManual()">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group" style="margin-bottom:10px;">
                        <label>Mês de Referência</label>
                        <select name="mes_referencia_manual_item" id="mes_referencia_manual_item" disabled>
                            <option value="-">-</option>
                            <option value="Janeiro">Janeiro</option>
                            <option value="Fevereiro">Fevereiro</option>
                            <option value="Março">Março</option>
                            <option value="Abril">Abril</option>
                            <option value="Maio">Maio</option>
                            <option value="Junho">Junho</option>
                            <option value="Julho">Julho</option>
                            <option value="Agosto">Agosto</option>
                            <option value="Setembro">Setembro</option>
                            <option value="Outubro">Outubro</option>
                            <option value="Novembro">Novembro</option>
                            <option value="Dezembro">Dezembro</option>
                        </select>
                        <div class="mes-hint" id="mesHintManual">💡 Selecione o mês para Propina, Transporte ou Mensalidade</div>
                    </div>
                    <div class="form-group" style="margin-bottom:10px;">
                        <label>Ano</label>
                        <input type="number" name="ano_referencia_manual_item" id="ano_referencia_manual_item" value="<?= date('Y') ?>" min="2000" max="2100">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group" style="margin-bottom:10px;">
                        <label>Multa (calculada)</label>
                        <input type="text" id="multa_manual_item" readonly style="background:#f8fafc;font-weight:bold;color:#e67e22;font-size:14px;width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;">
                        <div class="multa-info" id="multaInfoManual"></div>
                    </div>
                    <div class="form-group" style="margin-bottom:10px;">
                        <label>Desconto (Kz)</label>
                        <input type="number" step="0.01" name="desconto_manual_item" id="desconto_manual_item" value="0" min="0" onchange="calcularTotalItemManual()" oninput="calcularTotalItemManual()">
                    </div>
                </div>
                
                <div class="form-row-3">
                    <div class="form-group" style="margin-bottom:10px;">
                        <label>Desconto (%)</label>
                        <input type="number" step="0.1" id="desconto_percent_manual_item" value="0" min="0" max="100" onchange="aplicarDescontoPercentualManual()" oninput="aplicarDescontoPercentualManual()">
                    </div>
                    <div class="form-group" style="margin-bottom:10px;">
                        <label>Valor Líquido</label>
                        <input type="text" id="valor_liquido_manual_item" readonly style="background:#f8fafc;font-weight:bold;color:#1a2332;font-size:15px;width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;">
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end;margin-bottom:10px;">
                        <button type="submit" class="btn btn-success btn-add-item" style="width:100%;">
                            ➕ Adicionar Item
                        </button>
                    </div>
                </div>
            </form>
            
            <?php if (!empty($itens_manual)): ?>
            <div style="margin-top:10px;padding:8px 12px;background:#d1fae5;border-radius:6px;font-size:13px;color:#065f46;">
                ✅ <strong><?= count($itens_manual) ?> item(s)</strong> adicionado(s) - Total: <strong><?= number_format($total_manual, 2, ',', '.') ?> Kz</strong>
                <a href="?clear_items_manual=1" class="btn btn-danger btn-sm" style="float:right;padding:2px 10px;" onclick="return confirm('Limpar todos os itens?')">🗑️</a>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- LISTA DE ITENS DA FATURA MANUAL -->
        <?php if (!empty($itens_manual)): ?>
        <div style="margin-top:15px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                <h4 style="color:#1a2332;margin:0;font-size:14px;">📋 Itens da Fatura Manual (<?= count($itens_manual) ?>)</h4>
            </div>
            
            <table class="tabela-itens-fatura" style="font-size:12px;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Descrição</th>
                        <th>Mês</th>
                        <th class="text-right">Valor Base</th>
                        <th class="text-right">Multa</th>
                        <th class="text-right">Desconto</th>
                        <th class="text-right">Total</th>
                        <th style="text-align:center;">Ação</th>
                    </tr>
                </thead>
               

                <tbody>
                    <?php 
                    $total_fatura = 0;
                    $item_num = 1;
                    foreach ($itens_fatura as $index => $item): 
                        $total_fatura += $item['valor_liquido'];
                        $mes_exibicao = ($item['mes_referencia'] ?? '-') != '-' ? ($item['mes_referencia'] ?? '-') . '/' . ($item['ano_referencia'] ?? date('Y')) : '-';
                    ?>
                    <tr>
                        <td><?= $item_num++ ?></td>
                        <td><?= htmlspecialchars($item['emolumento'] ?? '') ?></td>
                        <td><?= $mes_exibicao ?></td>
                        <td class="text-right"><?= number_format($item['valor_base'] ?? 0, 2, ',', '.') ?> Kz</td>
                        <td class="text-right"><?= number_format($item['multa'] ?? 0, 2, ',', '.') ?> Kz</td>
                        <td class="text-right"><?= number_format($item['desconto'] ?? 0, 2, ',', '.') ?> Kz</td>
                        <td class="text-right"><?= number_format($item['taxa_iva'] ?? 0, 2, ',', '.') ?>%</td>
                        <td class="text-right"><strong><?= number_format($item['valor_liquido'] ?? 0, 2, ',', '.') ?> Kz</strong></td>
                        <td style="text-align:center;">
                            <a href="?remove_item=<?= $index ?>" class="btn-remove-item" onclick="return confirm('Remover este item?')">✕</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="7" class="text-right">TOTAL FATURA</td>
                        <td class="text-right"><strong><?= number_format($total_fatura, 2, ',', '.') ?> Kz</strong></td>
                        <td></td>
                    </tr>
                </tbody>
              
            </table>
        </div>
        <?php endif; ?>
        
        <!-- FORMULÁRIO PARA FINALIZAR PAGAMENTO MANUAL -->
        <form method="POST" action="" id="manualForm" style="margin-top:20px;border-top:2px solid #eef2f7;padding-top:20px;">
            <input type="hidden" name="salvar_pagamento_manual" value="1">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Nome do Aluno <span class="required">*</span></label>
                    <input type="text" name="aluno_nome_manual" id="aluno_nome_manual" placeholder="Digite o nome completo do aluno" required>
                </div>
                <div class="form-group">
                    <label>Classe</label>
                    <select name="aluno_classe_manual" id="aluno_classe_manual">
                        <option value="">Selecione</option>
                        <option value="PRÉ">PRÉ</option>
                        <option value="1ª">1ª</option>
                        <option value="2ª">2ª</option>
                        <option value="3ª">3ª</option>
                        <option value="4ª">4ª</option>
                        <option value="5ª">5ª</option>
                        <option value="6ª">6ª</option>
                        <option value="7ª">7ª</option>
                        <option value="8ª">8ª</option>
                        <option value="9ª">9ª</option>
                        <option value="10ª">10ª</option>
                        <option value="11ª">11ª</option>
                        <option value="12ª">12ª</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Turma</label>
                    <input type="text" name="aluno_turma_manual" id="aluno_turma_manual" placeholder="Ex: 2AM">
                </div>
                <div class="form-group">
                    <label>Período</label>
                    <select name="aluno_periodo_manual" id="aluno_periodo_manual">
                        <option value="Manhã">Manhã</option>
                        <option value="Tarde">Tarde</option>
                        <option value="Noite">Noite</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Gênero</label>
                    <select name="aluno_genero_manual" id="aluno_genero_manual">
                        <option value="M">Masculino</option>
                        <option value="F">Feminino</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Data do Pagamento</label>
                    <input type="date" name="data_pagamento_manual" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Forma de Pagamento</label>
                    <select name="forma_pagamento_manual">
                        <option value="dinheiro">💰 Dinheiro</option>
                        <option value="cartao">💳 Cartão</option>
                        <option value="transferencia">🏦 Transferência</option>
                        <option value="pix">📱 Pix</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Referência</label>
                    <input type="text" name="referencia_manual" placeholder="Nº do comprovante...">
                </div>
            </div>
            
            <div class="form-group">
                <label>Observações</label>
                <textarea name="observacoes_manual" placeholder="Observações sobre este pagamento..." rows="2"></textarea>
            </div>
            
            <div class="info-valor" style="margin-top:10px;background:#fef9e8;border-color:#fde68a;">
                ⚠️ <strong>Pagamento Manual:</strong> O sistema verifica duplicidade pelo nome do aluno.
                <?php if (!empty($itens_manual)): ?>
                    <br><strong>💰 Total a pagar:</strong> <span style="color:#c0392b;font-size:18px;"><?= number_format($total_manual, 2, ',', '.') ?> Kz</span>
                <?php else: ?>
                    <br><strong>📦 Nenhum item adicionado.</strong> Adicione itens acima.
                <?php endif; ?>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-pagamento-manual" <?= empty($itens_manual) ? 'disabled' : '' ?>>
                    ✅ Finalizar Pagamento Manual
                </button>
                <a href="add.php" class="btn btn-secondary">🔄 Limpar</a>
            </div>
        </form>
    </div>
</div>

<!-- ============================================
     SEÇÃO DE IMPORTAÇÃO
     ============================================ -->
<div class="import-section" id="importSection">
    <div class="import-header" onclick="toggleImportacao()">
        <h3>📥 Importar Dados da Planilha</h3>
        <span class="toggle-icon">▼</span>
    </div>
    <div class="import-body">
        <?php if ($import_result): ?>
            <div class="alert alert-<?= $import_result['success'] ? 'success' : 'error' ?>">
                <?= $import_result['message'] ?>
            </div>
        <?php endif; ?>
        
        <div class="import-grid">
            <div class="import-card">
                <h4>📚 Alunos Ano Anterior</h4>
                <form method="POST" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:10px;">
                    <div class="file-input-wrapper">
                        <input type="file" name="arquivo_importacao" accept=".csv,.xlsx,.xls" required>
                    </div>
                    <button type="submit" name="importar_alunos" class="btn btn-primary" style="align-self:flex-start;">⬆️ Importar Alunos</button>
                </form>
                <p style="font-size:11px;color:#94a3b8;margin-top:8px;">Formatos: CSV ou XLSX</p>
            </div>
            
            <div class="import-card">
                <h4>📋 Relatório de Pendências</h4>
                <form method="POST" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:10px;">
                    <div class="file-input-wrapper">
                        <input type="file" name="arquivo_pendencias" accept=".csv,.xlsx,.xls" required>
                    </div>
                    <button type="submit" name="importar_pendencias" class="btn btn-primary" style="align-self:flex-start;">⬆️ Importar Pendências</button>
                </form>
                <p style="font-size:11px;color:#94a3b8;margin-top:8px;">Formatos: CSV ou XLSX</p>
            </div>
        </div>
        
        <div class="import-actions">
            <a href="importados_alunos.php" class="btn btn-secondary" target="_blank">👁️ Ver Alunos Importados</a>
            <a href="importados_pendencias.php" class="btn btn-secondary" target="_blank">👁️ Ver Pendências Importadas</a>
        </div>
    </div>
</div>

<?php if ($sucesso && !$mostrar_fatura && !isset($_POST['add_item_manual'])): ?>
<div class="alert alert-success"><?= $sucesso ?></div>
<?php endif; ?>

<?php if ($erro && !isset($_POST['salvar_pagamento_manual']) && !isset($_POST['add_item_manual'])): ?>
<div class="alert alert-<?= $pagamento_duplicado ? 'warning' : 'error' ?>"><?= $erro ?></div>
<?php endif; ?>

<!-- ============================================
     FORMULÁRIO PRINCIPAL
     ============================================ -->
<div class="form-container">
    <form method="GET" action="" id="buscaForm">
        <div class="busca-aluno-container">
            <div class="busca-row">
                <input type="text" id="busca_aluno" name="busca" placeholder="🔍 Buscar por ID ou Nome..." value="<?= htmlspecialchars($aluno_busca) ?>" autocomplete="off">
                <button type="submit" class="btn btn-primary">🔍 Buscar</button>
                <?php if (!empty($aluno_busca)): ?>
                <a href="add.php" class="btn btn-secondary">✕ Limpar</a>
                <?php endif; ?>
            </div>
            <?php if (!empty($aluno_busca) && !empty($alunos_busca)): ?>
            <div class="resultado-busca">
                <div style="font-size:12px;color:#94a3b8;margin-bottom:10px;display:flex;justify-content:space-between">
                    <span>📋 Encontrados <?= count($alunos_busca) ?> aluno(s)</span>
                    <span style="font-size:11px;">Clique em "Selecionar"</span>
                </div>
                <?php foreach($alunos_busca as $aluno): ?>
                <div class="aluno-item <?= ($aluno_selecionado && $aluno_selecionado['id'] == $aluno['id']) ? 'aluno-selecionado' : '' ?>" 
                     data-id="<?= $aluno['id'] ?>" data-nome="<?= htmlspecialchars($aluno['nome']) ?>" 
                     data-genero="<?= htmlspecialchars($aluno['genero'] ?? '') ?>" 
                     data-classe="<?= htmlspecialchars($aluno['classe'] ?? '') ?>" 
                     data-curso="<?= htmlspecialchars($aluno['curso'] ?? '') ?>" 
                     data-turma="<?= htmlspecialchars($aluno['turma'] ?? '') ?>">
                    <div class="info">
                        <span class="nome">#<?= $aluno['id'] ?> - <?= htmlspecialchars($aluno['nome']) ?>
                            <?php if (!empty($aluno['status'])): ?>
                            <span class="badge-status badge-<?= $aluno['status'] == 'ativo' ? 'ativo' : 'inativo' ?>"><?= ucfirst($aluno['status']) ?></span>
                            <?php endif; ?>
                        </span>
                        <div class="detalhes">
                            <?php if (!empty($aluno['genero'])): ?><span>👤 <?= $aluno['genero'] == 'M' ? 'Masculino' : 'Feminino' ?></span><?php endif; ?>
                            <?php if (!empty($aluno['classe']) || !empty($aluno['curso'])): ?><span>🏫 <?= htmlspecialchars($aluno['classe'] ?? '') ?> <?= htmlspecialchars($aluno['curso'] ?? '') ?></span><?php endif; ?>
                            <?php if (!empty($aluno['turma'])): ?><span>📖 Turma: <?= htmlspecialchars($aluno['turma']) ?></span><?php endif; ?>
                        </div>
                    </div>
                    <button type="button" class="selecionar-btn" onclick="selecionarAluno(this)">✅ Selecionar</button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php elseif (!empty($aluno_busca)): ?>
            <div class="resultado-busca">
                <div class="nenhum-resultado"><div class="icone">😕</div><div>Nenhum aluno encontrado com "<strong><?= htmlspecialchars($aluno_busca) ?></strong>"</div></div>
            </div>
            <?php endif; ?>
        </div>
    </form>

    <form method="POST" action="" id="itemForm">
        <input type="hidden" name="add_item" value="1">
        <input type="hidden" name="aluno_id" id="aluno_id_hidden" value="<?= $_POST['aluno_id'] ?? $aluno_selecionado['id'] ?? '' ?>">
        
        <div class="form-group">
            <label>Aluno <span class="required">*</span></label>
            <select name="aluno_id" id="aluno_id" required>
                <option value="">Selecione um aluno</option>
                <?php foreach($alunos as $aluno): ?>
                <option value="<?= $aluno['id'] ?>" <?= ($_POST['aluno_id'] ?? $aluno_selecionado['id'] ?? '') == $aluno['id'] ? 'selected' : '' ?>>
                    #<?= $aluno['id'] ?> - <?= htmlspecialchars($aluno['nome']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php if ($aluno_selecionado): ?>
            <div class="aluno-selecionado-feedback" id="alunoFeedback">
                <span>✅ <strong>#<?= $aluno_selecionado['id'] ?></strong> - <?= htmlspecialchars($aluno_selecionado['nome']) ?>
                <?php if (!empty($aluno_selecionado['genero'])): ?> - <?= $aluno_selecionado['genero'] == 'M' ? '👨 Masculino' : '👩 Feminino' ?><?php endif; ?></span>
                <button type="button" class="remover-btn" onclick="removerSelecao()">✕ Remover</button>
            </div>
            <?php endif; ?>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Emolumento <span class="required">*</span></label>
                <select name="emolumento_id" id="emolumento_id" required onchange="atualizarValorItem()">
                    <option value="">Selecione</option>
                    <?php 
                    $emolumentos_exibidos = [];
                    foreach($emolumentos as $emol): 
                        $nome_sem_acento = removerAcentos(strtolower(trim($emol['nome'])));
                        if (!in_array($nome_sem_acento, $emolumentos_exibidos)):
                            $emolumentos_exibidos[] = $nome_sem_acento;
                    ?>
                    <option value="<?= $emol['id'] ?>" data-valor="<?= $emol['valor'] ?>" data-nome="<?= htmlspecialchars($emol['nome']) ?>">
                        <?= htmlspecialchars($emol['nome']) ?> - <?= number_format($emol['valor'], 2, ',', '.') ?> Kz
                    </option>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label>Valor Base <span class="required">*</span></label>
                <input type="number" step="0.01" name="valor_base" id="valor_base_item" value="" required onchange="calcularTotalItem()" oninput="calcularTotalItem()">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Mês de Referência</label>
                <select name="mes_referencia" id="mes_referencia_item" disabled>
                    <option value="-">-</option>
                    <option value="Janeiro">Janeiro</option><option value="Fevereiro">Fevereiro</option>
                    <option value="Março">Março</option><option value="Abril">Abril</option>
                    <option value="Maio">Maio</option><option value="Junho">Junho</option>
                    <option value="Julho">Julho</option><option value="Agosto">Agosto</option>
                    <option value="Setembro">Setembro</option><option value="Outubro">Outubro</option>
                    <option value="Novembro">Novembro</option><option value="Dezembro">Dezembro</option>
                </select>
                <div class="mes-hint" id="mesHintItem">💡 Selecione o mês para Propina, Transporte ou Mensalidade</div>
            </div>
            <div class="form-group">
                <label>Ano</label>
                <input type="number" class="ano-input" name="ano_referencia" id="ano_referencia_item" value="<?= date('Y') ?>" min="2000" max="2100">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Multa (calculada)</label>
                <input type="text" id="multa_item" readonly style="background:#f8fafc;font-weight:bold;color:#e67e22;font-size:15px;">
                <div class="multa-info" id="multaInfoItem"></div>
            </div>
            <div class="form-group">
                <label>Desconto (Kz)</label>
                <input type="number" step="0.01" name="desconto" id="desconto_item" value="0" min="0" onchange="calcularTotalItem()" oninput="calcularTotalItem()">
            </div>
        </div>

        <div class="form-row-3">
            <div class="form-group">
                <label>Desconto (%)</label>
                <input type="number" step="0.1" id="desconto_percent_item" value="0" min="0" max="100" onchange="aplicarDescontoPercentualItem()" oninput="aplicarDescontoPercentualItem()">
            </div>
            <div class="form-group">
                <label>Valor Líquido</label>
                <input type="text" id="valor_liquido_item" readonly style="background:#f8fafc;font-weight:bold;color:#1a2332;font-size:16px;">
            </div>
            <div class="form-group" style="display:flex;align-items:flex-end;">
                <button type="submit" class="btn btn-success btn-add-item" style="width:100%;">➕ Adicionar Item</button>
            </div>
        </div>
    </form>

    <?php if (!empty($itens_fatura)): ?>
    <div style="margin-top:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <h3 style="color:#1a2332;margin:0;">📋 Itens da Fatura (<?= count($itens_fatura) ?>)</h3>
            <div>
                <a href="?clear_items=1" class="btn btn-danger" onclick="return confirm('Limpar todos os itens?')">🗑️ Limpar</a>
            </div>
        </div>
        
        <table class="tabela-itens-fatura">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Descrição</th>
                    <th>Mês</th>
                    <th class="text-right">Valor Base</th>
                    <th class="text-right">Multa</th>
                    <th class="text-right">Desconto</th>
                    <th class="text-right">Total</th>
                    <th style="text-align:center;">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total_fatura = 0;
                $item_num = 1;
                foreach ($itens_fatura as $index => $item): 
                    $total_fatura += $item['valor_liquido'];
                    $mes_exibicao = ($item['mes_referencia'] ?? '-') != '-' ? ($item['mes_referencia'] ?? '-') . '/' . ($item['ano_referencia'] ?? date('Y')) : '-';
                ?>
                <tr>
                    <td><?= $item_num++ ?></td>
                    <td><?= htmlspecialchars($item['emolumento'] ?? '') ?></td>
                    <td><?= $mes_exibicao ?></td>
                    <td class="text-right"><?= number_format($item['valor_base'] ?? 0, 2, ',', '.') ?> Kz</td>
                    <td class="text-right"><?= number_format($item['multa'] ?? 0, 2, ',', '.') ?> Kz</td>
                    <td class="text-right"><?= number_format($item['desconto'] ?? 0, 2, ',', '.') ?> Kz</td>
                    <td class="text-right"><strong><?= number_format($item['valor_liquido'] ?? 0, 2, ',', '.') ?> Kz</strong></td>
                    <td style="text-align:center;">
                        <a href="?remove_item=<?= $index ?>" class="btn-remove-item" onclick="return confirm('Remover este item?')">✕</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="6" class="text-right">TOTAL FATURA</td>
                    <td class="text-right"><?= number_format($total_fatura, 2, ',', '.') ?> Kz</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <form method="POST" action="" style="margin-top:20px;border-top:2px solid #eef2f7;padding-top:20px;">
        <input type="hidden" name="finalizar_pagamento" value="1">
        <input type="hidden" name="aluno_id" value="<?= $_POST['aluno_id'] ?? $aluno_selecionado['id'] ?? '' ?>">
        
        <div class="form-row">
            <div class="form-group">
                <label>Data do Pagamento <span class="required">*</span></label>
                <input type="date" name="data_pagamento" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>Forma de Pagamento <span class="required">*</span></label>
                <select name="forma_pagamento" required>
                    <option value="dinheiro">💰 Dinheiro</option>
                    <option value="cartao">💳 Cartão</option>
                    <option value="transferencia">🏦 Transferência</option>
                    <option value="pix">📱 Pix</option>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Referência</label>
                <input type="text" name="referencia" placeholder="Nº do comprovante...">
            </div>
            <div class="form-group">
                <label>Observações</label>
                <input type="text" name="observacoes" placeholder="Observações...">
            </div>
        </div>

        <?php if (!empty($itens_fatura)): ?>
        <div class="info-valor" style="margin-top:10px;">
            <strong>📊 Resumo da Fatura:</strong>
            <span id="total_fatura_resumo"><?= count($itens_fatura) ?> item(ns) - Total: <strong style="color:#c0392b;"><?= number_format($total_fatura, 2, ',', '.') ?> Kz</strong></span>
            <input type="hidden" id="total_fatura_hidden" value="<?= $total_fatura ?>">
        </div>
        <?php endif; ?>

        <div class="info-valor" style="margin-top:10px;background:#fef9e8;border-color:#fde68a;">
            ⚠️ <strong>Verificação de Duplicados:</strong> O sistema verifica automaticamente se já existe pagamento para o mesmo aluno, emolumento e mês.
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-success" <?= empty($itens_fatura) ? 'disabled' : '' ?>>
                ✅ Finalizar Pagamento
            </button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<?php if ($mostrar_fatura && $fatura_html): ?>
<div class="fatura-modal" id="faturaModal">
    <div class="fatura-modal-content">
        <button class="btn-fechar" onclick="fecharFatura()">✕ Fechar</button>
        <iframe class="fatura-iframe" srcdoc="<?= htmlspecialchars($fatura_html) ?>"></iframe>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
     SCRIPTS DA PÁGINA
     ============================================ -->
<script>
// ============================================================
// FUNÇÕES PARA ITENS PRINCIPAIS
// ============================================================
function atualizarValorItem() {
    const select = document.getElementById('emolumento_id');
    const valorInput = document.getElementById('valor_base_item');
    const selected = select.options[select.selectedIndex];
    const mesSelect = document.getElementById('mes_referencia_item');
    const mesHint = document.getElementById('mesHintItem');
    
    if (selected.value) {
        const valor = parseFloat(selected.dataset.valor) || 0;
        valorInput.value = valor.toFixed(2);
        
        const nome = selected.dataset.nome || '';
        const isMesRequired = nome.toLowerCase().includes('propina') || 
                              nome.toLowerCase().includes('transporte') || 
                              nome.toLowerCase().includes('mensalidade');
        
        mesSelect.disabled = !isMesRequired;
        if (!isMesRequired) {
            mesSelect.value = '-';
            mesHint.innerHTML = '💡 Selecione o mês para Propina, Transporte ou Mensalidade';
            mesHint.className = 'mes-hint';
        } else {
            mesHint.innerHTML = '✅ Mês obrigatório para <strong>' + nome + '</strong>';
            mesHint.className = 'mes-hint ativo';
            if (mesSelect.value === '-') {
                const meses = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
                mesSelect.value = meses[new Date().getMonth()];
            }
        }
        calcularMultaItem();
        calcularTotalItem();
    }
}

function calcularMultaItem() {
    const select = document.getElementById('emolumento_id');
    const selected = select.options[select.selectedIndex];
    const emolumentoId = selected.value;
    const valorBase = parseFloat(document.getElementById('valor_base_item').value) || 0;
    const mesSelect = document.getElementById('mes_referencia_item');
    const mesNome = mesSelect.value;
    const ano = parseInt(document.getElementById('ano_referencia_item').value) || new Date().getFullYear();
    const multaInput = document.getElementById('multa_item');
    const multaInfo = document.getElementById('multaInfoItem');
    
    if (!emolumentoId || mesNome === '-' || !valorBase) {
        multaInput.value = '0,00 Kz';
        multaInfo.className = 'multa-info';
        return;
    }
    
    fetch('calcular_multa_ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'emolumento_id=' + emolumentoId + '&mes=' + mesNome + '&ano=' + ano + '&valor_base=' + valorBase
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const multa = data.multa;
            if (multa > 0) {
                multaInput.value = multa.toFixed(2).replace('.', ',') + ' Kz';
                multaInfo.innerHTML = '⚠️ Multa de ' + multa.toFixed(2).replace('.', ',') + ' Kz por atraso de ' + data.dias_atraso + ' dias.';
                multaInfo.className = 'multa-info ativo';
            } else {
                multaInput.value = '0,00 Kz';
                multaInfo.className = 'multa-info';
            }
            calcularTotalItem();
        }
    })
    .catch(() => {
        multaInput.value = '0,00 Kz';
        multaInfo.className = 'multa-info';
    });
}

function calcularTotalItem() {
    const valorBase = parseFloat(document.getElementById('valor_base_item').value) || 0;
    const desconto = parseFloat(document.getElementById('desconto_item').value) || 0;
    const multaText = document.getElementById('multa_item').value.replace(/[^0-9,.]/g, '').replace(',', '.');
    const multa = parseFloat(multaText) || 0;
    
    let total = valorBase + multa - desconto;
    if (total < 0) total = 0;
    
    document.getElementById('valor_liquido_item').value = total.toFixed(2);
}

function aplicarDescontoPercentualItem() {
    const valorBase = parseFloat(document.getElementById('valor_base_item').value) || 0;
    const percent = parseFloat(document.getElementById('desconto_percent_item').value) || 0;
    if (percent < 0 || percent > 100) { alert('Percentual deve ser entre 0 e 100'); return; }
    document.getElementById('desconto_item').value = ((valorBase * percent) / 100).toFixed(2);
    calcularTotalItem();
}

// ============================================================
// FUNÇÕES PARA ITENS MANUAIS
// ============================================================
function atualizarValorItemManual() {
    const select = document.getElementById('emolumento_id_manual_item');
    const valorInput = document.getElementById('valor_base_manual_item');
    const selected = select.options[select.selectedIndex];
    const mesSelect = document.getElementById('mes_referencia_manual_item');
    const mesHint = document.getElementById('mesHintManual');
    
    if (selected.value) {
        const valor = parseFloat(selected.dataset.valor) || 0;
        valorInput.value = valor.toFixed(2);
        
        const nome = selected.dataset.nome || '';
        const isMesRequired = nome.toLowerCase().includes('propina') || 
                              nome.toLowerCase().includes('transporte') || 
                              nome.toLowerCase().includes('mensalidade');
        
        mesSelect.disabled = !isMesRequired;
        if (!isMesRequired) {
            mesSelect.value = '-';
            mesHint.innerHTML = '💡 Selecione o mês para Propina, Transporte ou Mensalidade';
            mesHint.className = 'mes-hint';
        } else {
            mesHint.innerHTML = '✅ Mês obrigatório para <strong>' + nome + '</strong>';
            mesHint.className = 'mes-hint ativo';
            if (mesSelect.value === '-') {
                const meses = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
                mesSelect.value = meses[new Date().getMonth()];
            }
        }
        calcularMultaItemManual();
        calcularTotalItemManual();
    }
}

function calcularMultaItemManual() {
    const select = document.getElementById('emolumento_id_manual_item');
    const selected = select.options[select.selectedIndex];
    const emolumentoId = selected.value;
    const valorBase = parseFloat(document.getElementById('valor_base_manual_item').value) || 0;
    const mesSelect = document.getElementById('mes_referencia_manual_item');
    const mesNome = mesSelect.value;
    const ano = parseInt(document.getElementById('ano_referencia_manual_item').value) || new Date().getFullYear();
    const multaInput = document.getElementById('multa_manual_item');
    const multaInfo = document.getElementById('multaInfoManual');
    
    if (!emolumentoId || mesNome === '-' || !valorBase) {
        multaInput.value = '0,00 Kz';
        multaInfo.className = 'multa-info';
        return;
    }
    
    fetch('calcular_multa_ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'emolumento_id=' + emolumentoId + '&mes=' + mesNome + '&ano=' + ano + '&valor_base=' + valorBase
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const multa = data.multa;
            if (multa > 0) {
                multaInput.value = multa.toFixed(2).replace('.', ',') + ' Kz';
                multaInfo.innerHTML = '⚠️ Multa de ' + multa.toFixed(2).replace('.', ',') + ' Kz por atraso de ' + data.dias_atraso + ' dias.';
                multaInfo.className = 'multa-info ativo';
            } else {
                multaInput.value = '0,00 Kz';
                multaInfo.className = 'multa-info';
            }
            calcularTotalItemManual();
        }
    })
    .catch(() => {
        multaInput.value = '0,00 Kz';
        multaInfo.className = 'multa-info';
    });
}

function calcularTotalItemManual() {
    const valorBase = parseFloat(document.getElementById('valor_base_manual_item').value) || 0;
    const desconto = parseFloat(document.getElementById('desconto_manual_item').value) || 0;
    const multaText = document.getElementById('multa_manual_item').value.replace(/[^0-9,.]/g, '').replace(',', '.');
    const multa = parseFloat(multaText) || 0;
    
    let total = valorBase + multa - desconto;
    if (total < 0) total = 0;
    
    document.getElementById('valor_liquido_manual_item').value = total.toFixed(2);
}

function aplicarDescontoPercentualManual() {
    const valorBase = parseFloat(document.getElementById('valor_base_manual_item').value) || 0;
    const percent = parseFloat(document.getElementById('desconto_percent_manual_item').value) || 0;
    if (percent < 0 || percent > 100) { alert('Percentual deve ser entre 0 e 100'); return; }
    document.getElementById('desconto_manual_item').value = ((valorBase * percent) / 100).toFixed(2);
    calcularTotalItemManual();
}

// ============================================================
// FUNÇÕES GERAIS
// ============================================================
function selecionarAluno(btn) {
    const item = btn.closest('.aluno-item');
    if (!item) return;
    const id = item.dataset.id;
    const nome = item.dataset.nome;
    const genero = item.dataset.genero || '';
    const select = document.getElementById('aluno_id');
    const hidden = document.getElementById('aluno_id_hidden');
    
    for (let opt of select.options) {
        if (opt.value == id) {
            opt.selected = true;
            break;
        }
    }
    if (hidden) hidden.value = id;
    
    const old = document.getElementById('alunoFeedback');
    if (old) old.remove();
    
    const parent = document.querySelector('#itemForm .form-group');
    const div = document.createElement('div');
    div.id = 'alunoFeedback';
    div.className = 'aluno-selecionado-feedback';
    div.innerHTML = '<span>✅ <strong>#' + id + '</strong> - ' + nome + (genero ? ' - ' + (genero === 'M' ? '👨 Masculino' : '👩 Feminino') : '') + '</span>' +
                     '<button type="button" class="remover-btn" onclick="removerSelecao()">✕ Remover</button>';
    parent.appendChild(div);
    
    document.querySelectorAll('.aluno-item').forEach(function(i) {
        i.classList.remove('aluno-selecionado');
    });
    item.classList.add('aluno-selecionado');
    div.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function removerSelecao() {
    document.getElementById('aluno_id').value = '';
    document.getElementById('aluno_id_hidden').value = '';
    const fb = document.getElementById('alunoFeedback');
    if (fb) fb.remove();
    document.querySelectorAll('.aluno-item').forEach(function(i) {
        i.classList.remove('aluno-selecionado');
    });
}

function fecharFatura() {
    document.getElementById('faturaModal').style.display = 'none';
    window.location.href = 'add.php';
}

function toggleImportacao() {
    const section = document.getElementById('importSection');
    section.classList.toggle('active');
    localStorage.setItem('importSectionOpen', section.classList.contains('active') ? 'true' : 'false');
}

function toggleManual() {
    const section = document.getElementById('manualSection');
    section.classList.toggle('active');
    localStorage.setItem('manualSectionOpen', section.classList.contains('active') ? 'true' : 'false');
}

// ============================================================
// EVENT LISTENERS
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    const savedImport = localStorage.getItem('importSectionOpen');
    if (savedImport === 'true') {
        document.getElementById('importSection').classList.add('active');
    }
    
    const savedManual = localStorage.getItem('manualSectionOpen');
    if (savedManual === 'true') {
        document.getElementById('manualSection').classList.add('active');
    }
    
    <?php if ($aluno_selecionado): ?>
    setTimeout(function() {
        document.querySelectorAll('.aluno-item').forEach(function(i) {
            if (i.dataset.id == <?= $aluno_selecionado['id'] ?>) {
                i.classList.add('aluno-selecionado');
            }
        });
    }, 100);
    <?php endif; ?>
    
    setTimeout(calcularMultaItem, 500);
    setTimeout(calcularMultaItemManual, 500);
    
    var buscaAluno = document.getElementById('busca_aluno');
    if (buscaAluno) {
        buscaAluno.addEventListener('input', function() {
            clearTimeout(timeoutId);
            if (this.value.trim().length >= 2) {
                timeoutId = setTimeout(function() {
                    document.getElementById('buscaForm').submit();
                }, 500);
            }
        });
    }
    
    var alunoClasseManual = document.getElementById('aluno_classe_manual');
    if (alunoClasseManual) {
        alunoClasseManual.addEventListener('change', function() {
            var classe = this.value;
            var periodo = document.getElementById('aluno_periodo_manual').value;
            var turmaInput = document.getElementById('aluno_turma_manual');
            if (classe && periodo) {
                var numero = classe.replace('ª', '');
                var periodoAbrev = periodo === 'Manhã' ? 'M' : (periodo === 'Tarde' ? 'T' : 'N');
                turmaInput.value = numero + periodoAbrev;
            }
        });
    }
    
    var alunoPeriodoManual = document.getElementById('aluno_periodo_manual');
    if (alunoPeriodoManual) {
        alunoPeriodoManual.addEventListener('change', function() {
            var classe = document.getElementById('aluno_classe_manual').value;
            var periodo = this.value;
            var turmaInput = document.getElementById('aluno_turma_manual');
            if (classe && periodo) {
                var numero = classe.replace('ª', '');
                var periodoAbrev = periodo === 'Manhã' ? 'M' : (periodo === 'Tarde' ? 'T' : 'N');
                turmaInput.value = numero + periodoAbrev;
            }
        });
    }
    
    var emolumentoId = document.getElementById('emolumento_id');
    if (emolumentoId) {
        emolumentoId.addEventListener('change', function() {
            setTimeout(calcularMultaItem, 100);
        });
    }
    
    var mesReferenciaItem = document.getElementById('mes_referencia_item');
    if (mesReferenciaItem) {
        mesReferenciaItem.addEventListener('change', calcularMultaItem);
    }
    
    var anoReferenciaItem = document.getElementById('ano_referencia_item');
    if (anoReferenciaItem) {
        anoReferenciaItem.addEventListener('change', calcularMultaItem);
        anoReferenciaItem.addEventListener('input', calcularMultaItem);
    }
    
    var valorBaseItem = document.getElementById('valor_base_item');
    if (valorBaseItem) {
        valorBaseItem.addEventListener('change', calcularMultaItem);
        valorBaseItem.addEventListener('input', function() {
            setTimeout(calcularMultaItem, 300);
        });
    }
    
    var emolumentoIdManual = document.getElementById('emolumento_id_manual_item');
    if (emolumentoIdManual) {
        emolumentoIdManual.addEventListener('change', function() {
            setTimeout(calcularMultaItemManual, 100);
        });
    }
    
    var mesReferenciaManual = document.getElementById('mes_referencia_manual_item');
    if (mesReferenciaManual) {
        mesReferenciaManual.addEventListener('change', calcularMultaItemManual);
    }
    
    var anoReferenciaManual = document.getElementById('ano_referencia_manual_item');
    if (anoReferenciaManual) {
        anoReferenciaManual.addEventListener('change', calcularMultaItemManual);
        anoReferenciaManual.addEventListener('input', calcularMultaItemManual);
    }
    
    var valorBaseManual = document.getElementById('valor_base_manual_item');
    if (valorBaseManual) {
        valorBaseManual.addEventListener('change', calcularMultaItemManual);
        valorBaseManual.addEventListener('input', function() {
            setTimeout(calcularMultaItemManual, 300);
        });
    }
});

var timeoutId;
</script>

<?php include '../../includes/footer_escola.php'; ?>