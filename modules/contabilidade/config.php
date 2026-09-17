<?php
// ============================================
// CONFIGURAÇÃO DO MÓDULO DE CONTABILIDADE
// ============================================

define('MODULO_CONTABILIDADE', true);
define('CONTABILIDADE_VERSION', '1.0.0');

// Configuração de integração
define('INTEGRAR_PAGAMENTOS', true);
define('INTEGRAR_FATURAS', true);
define('INTEGRAR_RECIBOS', true);
define('INTEGRAR_MOVIMENTACOES', true);

// Configuração de relatórios
define('BALANCO_ANO_PADRAO', date('Y'));
define('DRE_ANO_PADRAO', date('Y'));

// Contas padrão para integração
define('CONTA_CAIXA', '1.1.01.001');
define('CONTA_BANCO', '1.1.01.002');
define('CONTA_CLIENTES', '1.1.02.001');
define('CONTA_FORNECEDORES', '2.1.01.001');
define('CONTA_RECEITA_SERVICOS', '4.1.02');
define('CONTA_RECEITA_MENSALIDADES', '4.1.02.001');
define('CONTA_DESPESA_SALARIOS', '5.1.01.001');
define('CONTA_DESPESA_GERAIS', '5.1.02');
define('CONTA_CMV', '6.1.01');
?>