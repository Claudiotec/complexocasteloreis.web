<?php
// modules/escola/index.php
// Ou esta versão (mais robusta):
require_once(__DIR__ . '/../includes/verificar_permissao_escola.php');

$permissoes = verificarMultiplasPermissoesEscola('escola', ['visualizar', 'criar', 'editar', 'excluir']);

if ($permissoes['visualizar']) {
    // Mostra conteúdo
}

if ($permissoes['criar']) {
    // Mostra botão criar
}
?>





<?php
// ============================================
// modules/escola/financeiro/pagamentos/calcular_multa_ajax.php
// ============================================

require_once '../../../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $emolumento_id = $_POST['emolumento_id'] ?? 0;
    $mes_nome = $_POST['mes'] ?? '';
    $ano = intval($_POST['ano'] ?? date('Y'));
    $valor_base = floatval($_POST['valor_base'] ?? 0);
    
    // Converter nome do mês para número
    $mapa_meses = array(
        'Janeiro' => 1, 'Fevereiro' => 2, 'Março' => 3, 'Abril' => 4,
        'Maio' => 5, 'Junho' => 6, 'Julho' => 7, 'Agosto' => 8,
        'Setembro' => 9, 'Outubro' => 10, 'Novembro' => 11, 'Dezembro' => 12
    );
    $mes = $mapa_meses[$mes_nome] ?? 0;
    
    if (!$emolumento_id || !$mes || $valor_base <= 0) {
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        exit;
    }
    
    try {
        // Buscar configuração de multa do emolumento
        $stmt = $pdo->prepare("
            SELECT multa_tipo, multa_valor, prazo_dias 
            FROM emolumentos 
            WHERE id = ?
        ");
        $stmt->execute([$emolumento_id]);
        $config = $stmt->fetch();
        
        if (!$config) {
            // Buscar na tabela mensalidades
            $stmt = $pdo->prepare("
                SELECT multa_tipo, multa_valor, prazo_dias 
                FROM mensalidades 
                WHERE emolumento_id = ? 
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([$emolumento_id]);
            $config = $stmt->fetch();
        }
        
        // Se não encontrar, usar padrão
        if (!$config) {
            $config = [
                'multa_tipo' => 'percentual',
                'multa_valor' => 10,
                'prazo_dias' => 10
            ];
        }
        
        // ===== CÁLCULO DO ANO LETIVO BIENAL =====
        // Ano letivo: Setembro (9) a Julho (7) do ano seguinte
        // Exemplo: Setembro 2025 a Julho 2026
        
        function obterAnoLetivo($mes, $ano) {
            if ($mes >= 9) { // Setembro a Dezembro
                return $ano;
            } else { // Janeiro a Julho
                return $ano - 1;
            }
        }
        
        function obterMesCiclo($mes) {
            if ($mes >= 9) {
                return $mes - 8; // Set=1, Out=2, Nov=3, Dez=4
            } else {
                return $mes + 4; // Jan=5, Fev=6, Mar=7, Abr=8, Mai=9, Jun=10, Jul=11
            }
        }
        
        $ano_letivo = obterAnoLetivo($mes, $ano);
        $mes_ciclo = obterMesCiclo($mes);
        
        // Data atual
        $mes_atual = intval(date('m'));
        $ano_atual = intval(date('Y'));
        $dia_atual = intval(date('d'));
        
        $ano_letivo_atual = obterAnoLetivo($mes_atual, $ano_atual);
        $mes_ciclo_atual = obterMesCiclo($mes_atual);
        
        // Data de vencimento (dia 10 do mês)
        $data_vencimento = date("Y-m-d", strtotime("$ano-$mes-10"));
        $data_atual = date('Y-m-d');
        
        // ===== VERIFICAR ATRASO =====
        $esta_atrasado = false;
        $dias_atraso = 0;
        
        // Verificar se o mês já passou no ciclo letivo
        if ($ano_letivo < $ano_letivo_atual) {
            // Ano letivo anterior - está atrasado
            $esta_atrasado = true;
            $diff = strtotime($data_atual) - strtotime($data_vencimento);
            $dias_atraso = floor($diff / (60 * 60 * 24));
        } elseif ($ano_letivo == $ano_letivo_atual) {
            if ($mes_ciclo < $mes_ciclo_atual) {
                // Mês já passou no ciclo
                $esta_atrasado = true;
                $diff = strtotime($data_atual) - strtotime($data_vencimento);
                $dias_atraso = floor($diff / (60 * 60 * 24));
            } elseif ($mes_ciclo == $mes_ciclo_atual) {
                // Mesmo mês - verificar dia do vencimento
                if ($dia_atual > 10) {
                    $esta_atrasado = true;
                    $dias_atraso = $dia_atual - 10;
                }
            }
        }
        
        // Calcular multa
        $multa = 0;
        if ($esta_atrasado && $dias_atraso > $config['prazo_dias']) {
            if ($config['multa_tipo'] == 'percentual') {
                $multa = ($valor_base * $config['multa_valor']) / 100;
            } else {
                $multa = $config['multa_valor'];
            }
        }
        
        echo json_encode([
            'success' => true,
            'multa' => $multa,
            'dias_atraso' => $dias_atraso,
            'prazo' => $config['prazo_dias'],
            'vencimento' => $data_vencimento,
            'ano_letivo' => $ano_letivo . '/' . ($ano_letivo + 1),
            'mes_ciclo' => $mes_ciclo,
            'ano_letivo_atual' => $ano_letivo_atual . '/' . ($ano_letivo_atual + 1),
            'mes_ciclo_atual' => $mes_ciclo_atual,
            'esta_atrasado' => $esta_atrasado
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>