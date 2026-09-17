<?php
// ============================================
// modules/escola/agt/agt_functions.php - Funções AGT
// ============================================

/**
 * Busca configuração AGT
 */
function buscarConfigAGT($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM config_agt WHERE id = 1");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Busca dados da empresa para AGT
 */
function buscarDadosEmpresaAGT($pdo) {
    $config = buscarConfigAGT($pdo);
    
    if ($config) {
        return [
            'nome' => $config['nome_comercial'],
            'nif' => $config['nif'],
            'endereco' => $config['endereco'],
            'telefone' => $config['telefone'],
            'email' => $config['email'],
            'site' => $config['site'],
            'regime_iva' => $config['regime_iva'],
            'taxa_iva_padrao' => $config['taxa_iva_padrao'],
            'serie_fatura' => $config['serie_fatura']
        ];
    }
    
    // Fallback: buscar da tabela empresa
    try {
        $stmt = $pdo->prepare("SELECT * FROM empresa WHERE id = 1");
        $stmt->execute();
        $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($empresa) {
            return [
                'nome' => $empresa['nome_fantasia'] ?? $empresa['razao_social'],
                'nif' => $empresa['cnpj'] ?? '5001234567',
                'endereco' => $empresa['endereco'],
                'telefone' => $empresa['telefone'],
                'email' => $empresa['email'],
                'site' => '',
                'regime_iva' => 'normal',
                'taxa_iva_padrao' => 14,
                'serie_fatura' => 'A'
            ];
        }
    } catch (Exception $e) {}
    
    return [
        'nome' => 'Sistema Escolar',
        'nif' => '5001234567',
        'endereco' => 'Luanda, Angola',
        'telefone' => '',
        'email' => '',
        'site' => '',
        'regime_iva' => 'normal',
        'taxa_iva_padrao' => 14,
        'serie_fatura' => 'A'
    ];
}

/**
 * Busca dados do aluno para fatura
 */
function buscarDadosAlunoFatura($pdo, $aluno_id) {
    $dados = [
        'nome' => '',
        'nif' => '9999999999',
        'endereco' => '',
        'classe' => '',
        'turma' => '',
        'telefone' => '',
        'email' => '',
        'encarregado' => '',
        'encarregado_telefone' => '',
        'periodo' => 'Manhã',
        'ano_letivo' => date('Y') . '/' . (date('Y') + 1)
    ];
    
    if (!$aluno_id) {
        return $dados;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM alunos WHERE id = ?");
        $stmt->execute([$aluno_id]);
        $aluno = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($aluno) {
            $dados['nome'] = $aluno['nome'] ?? '';
            $dados['nif'] = $aluno['nif'] ?? $aluno['N_BI'] ?? '9999999999';
            $dados['endereco'] = $aluno['Morada'] ?? $aluno['endereco'] ?? '';
            $dados['classe'] = $aluno['Classe'] ?? '';
            $dados['turma'] = $aluno['TURMA'] ?? '';
            $dados['telefone'] = $aluno['Contacto_do_Aluno'] ?? $aluno['telefone'] ?? '';
            $dados['email'] = $aluno['email'] ?? '';
            $dados['encarregado'] = $aluno['Nome_do_Pai'] ?? $aluno['Nome_da_mae'] ?? '';
            $dados['encarregado_telefone'] = $aluno['Contacto4'] ?? $aluno['Contacto_Mae'] ?? '';
            $dados['periodo'] = $aluno['Periodo'] ?? 'Manhã';
        }
    } catch (Exception $e) {}
    
    return $dados;
}

/**
 * Busca emolumento com dados de IVA
 */
function buscarEmolumentoComIVA($pdo, $emolumento_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, nome, valor, codigo_iva, taxa_iva, regime_iva, categoria
            FROM emolumentos WHERE id = ?
        ");
        $stmt->execute([$emolumento_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Gera número de fatura AGT
 */
function gerarNumeroFaturaAGT($pdo) {
    $ano = date('Y');
    $mes = date('m');
    
    try {
        $pdo->beginTransaction();
        
        // Buscar último número
        $stmt = $pdo->prepare("SELECT ultimo_numero FROM config_agt WHERE id = 1 FOR UPDATE");
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $num = ($config && isset($config['ultimo_numero'])) ? intval($config['ultimo_numero']) + 1 : 1;
        
        // Atualizar número
        $stmt = $pdo->prepare("UPDATE config_agt SET ultimo_numero = ? WHERE id = 1");
        $stmt->execute([$num]);
        
        $pdo->commit();
        
        return 'FT-' . substr($ano, -2) . $mes . str_pad($num, 6, '0', STR_PAD_LEFT);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return 'FT-' . date('ym') . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }
}

/**
 * Calcula totais da fatura
 */
function calcularTotaisFaturaAGT($itens) {
    $totais = [
        'total_base' => 0,
        'total_iva' => 0,
        'total_multa' => 0,
        'total_desconto' => 0,
        'total_liquido' => 0
    ];
    
    foreach ($itens as $item) {
        $totais['total_base'] += $item['valor_base'] ?? 0;
        $totais['total_iva'] += $item['valor_iva'] ?? 0;
        $totais['total_multa'] += $item['multa'] ?? 0;
        $totais['total_desconto'] += $item['desconto'] ?? 0;
        $totais['total_liquido'] += $item['valor_liquido'] ?? 0;
    }
    
    return $totais;
}

/**
 * Gera hash de autenticação da fatura
 */
function gerarHashFaturaAGT($dados) {
    $string = $dados['numero_fatura'] . 
              $dados['data_emissao'] . 
              $dados['total_liquido'] . 
              $dados['aluno_nome'] . 
              $dados['aluno_nif'] . 
              date('Y-m-d');
    return hash('sha256', $string);
}

/**
 * Salva fatura AGT no banco
 */
function salvarFaturaAGT($pdo, $dados, $itens, $qrcode_base64 = null) {
    try {
        $empresa = buscarDadosEmpresaAGT($pdo);
        $aluno = buscarDadosAlunoFatura($pdo, $dados['aluno_id'] ?? null);
        $totais = calcularTotaisFaturaAGT($itens);
        $numero_fatura = $dados['numero_fatura'] ?? gerarNumeroFaturaAGT($pdo);
        
        // Gerar hash
        $hash_data = [
            'numero_fatura' => $numero_fatura,
            'data_emissao' => date('Y-m-d H:i:s'),
            'total_liquido' => $totais['total_liquido'],
            'aluno_nome' => $aluno['nome'],
            'aluno_nif' => $aluno['nif']
        ];
        $hash = gerarHashFaturaAGT($hash_data);
        
        // Inserir fatura
        $sql = "INSERT INTO faturas_agt SET
            numero_fatura = :numero_fatura,
            tipo_fatura = :tipo_fatura,
            serie = :serie,
            data_emissao = NOW(),
            data_pagamento = :data_pagamento,
            aluno_id = :aluno_id,
            aluno_nome = :aluno_nome,
            aluno_nif = :aluno_nif,
            aluno_endereco = :aluno_endereco,
            aluno_classe = :aluno_classe,
            aluno_turma = :aluno_turma,
            aluno_telefone = :aluno_telefone,
            aluno_email = :aluno_email,
            aluno_encarregado = :aluno_encarregado,
            aluno_encarregado_telefone = :aluno_encarregado_telefone,
            empresa_nome = :empresa_nome,
            empresa_nif = :empresa_nif,
            empresa_endereco = :empresa_endereco,
            empresa_telefone = :empresa_telefone,
            empresa_email = :empresa_email,
            total_base = :total_base,
            total_iva = :total_iva,
            total_multa = :total_multa,
            total_desconto = :total_desconto,
            total_liquido = :total_liquido,
            forma_pagamento = :forma_pagamento,
            referencia = :referencia,
            observacoes = :observacoes,
            status = :status,
            usuario_id = :usuario_id,
            usuario_nome = :usuario_nome,
            itens_json = :itens_json,
            qrcode = :qrcode,
            hash_autenticacao = :hash_autenticacao,
            numero_autorizacao = :numero_autorizacao,
            ano_letivo = :ano_letivo,
            periodo_letivo = :periodo_letivo
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':numero_fatura' => $numero_fatura,
            ':tipo_fatura' => $dados['tipo_fatura'] ?? 'FT',
            ':serie' => $empresa['serie_fatura'] ?? 'A',
            ':data_pagamento' => $dados['data_pagamento'] ?? date('Y-m-d'),
            ':aluno_id' => $dados['aluno_id'] ?? null,
            ':aluno_nome' => $aluno['nome'],
            ':aluno_nif' => $aluno['nif'],
            ':aluno_endereco' => $aluno['endereco'],
            ':aluno_classe' => $aluno['classe'],
            ':aluno_turma' => $aluno['turma'],
            ':aluno_telefone' => $aluno['telefone'],
            ':aluno_email' => $aluno['email'],
            ':aluno_encarregado' => $aluno['encarregado'],
            ':aluno_encarregado_telefone' => $aluno['encarregado_telefone'],
            ':empresa_nome' => $empresa['nome'],
            ':empresa_nif' => $empresa['nif'],
            ':empresa_endereco' => $empresa['endereco'],
            ':empresa_telefone' => $empresa['telefone'],
            ':empresa_email' => $empresa['email'],
            ':total_base' => $totais['total_base'],
            ':total_iva' => $totais['total_iva'],
            ':total_multa' => $totais['total_multa'],
            ':total_desconto' => $totais['total_desconto'],
            ':total_liquido' => $totais['total_liquido'],
            ':forma_pagamento' => $dados['forma_pagamento'] ?? 'dinheiro',
            ':referencia' => $dados['referencia'] ?? '',
            ':observacoes' => $dados['observacoes'] ?? '',
            ':status' => 'emitida',
            ':usuario_id' => $_SESSION['usuario_id'] ?? null,
            ':usuario_nome' => $_SESSION['usuario_nome'] ?? 'Sistema',
            ':itens_json' => json_encode($itens, JSON_UNESCAPED_UNICODE),
            ':qrcode' => $qrcode_base64,
            ':hash_autenticacao' => $hash,
            ':numero_autorizacao' => $dados['numero_autorizacao'] ?? null,
            ':ano_letivo' => $aluno['ano_letivo'],
            ':periodo_letivo' => $aluno['periodo']
        ]);
        
        $fatura_id = $pdo->lastInsertId();
        
        // Inserir itens da fatura
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
                ':descricao' => $item['descricao'],
                ':quantidade' => $item['quantidade'] ?? 1,
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
            'hash' => $hash,
            'totais' => $totais
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Atualiza pagamento com número da fatura
 */
function atualizarPagamentoComFatura($pdo, $pagamento_id, $numero_fatura, $hash) {
    try {
        $stmt = $pdo->prepare("
            UPDATE pagamentos SET 
                numero_fatura = :numero_fatura,
                hash_autenticacao = :hash,
                updated_at = NOW()
            WHERE id = :pagamento_id
        ");
        $stmt->execute([
            ':numero_fatura' => $numero_fatura,
            ':hash' => $hash,
            ':pagamento_id' => $pagamento_id
        ]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}