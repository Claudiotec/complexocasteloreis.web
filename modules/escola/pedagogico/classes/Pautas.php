<?php
/**
 * Classe Pautas - Gerenciamento de Pautas Escolares
 * Estilo SOFTGEST
 */

require_once dirname(__DIR__) . '/config/database.php';

class Pautas {
    private $db;
    private $tabela_notas = 'notas_alunos';
    private $tabela_alunos = 'alunos';
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
    }
    
    /**
     * Obtém dados para pauta trimestral
     */
    public function getDadosPautaTrimestral($trimestre, $classe, $turma, $ano_letivo, $disciplinas = []) {
        // Buscar alunos da turma
        $alunos = $this->db->fetchAll(
            "SELECT id, nome, sexo, idade FROM {$this->tabela_alunos} 
             WHERE classe = ? AND turma = ? ORDER BY nome",
            [$classe, $turma]
        );
        
        if (empty($alunos)) {
            return ['erro' => 'Nenhum aluno encontrado'];
        }
        
        // Se não especificou disciplinas, buscar todas
        if (empty($disciplinas)) {
            $disciplinas = $this->getDisciplinasTurma($classe, $turma);
        }
        
        $coluna_mt = "mt{$trimestre}";
        $dados_alunos = [];
        
        foreach ($alunos as $aluno) {
            $notas = [];
            
            foreach ($disciplinas as $disciplina) {
                $nota = $this->db->fetchOne(
                    "SELECT {$coluna_mt} FROM {$this->tabela_notas} 
                     WHERE id_aluno = ? AND disciplina = ? AND classe = ? AND turma = ?",
                    [$aluno['id'], $disciplina, $classe, $turma]
                );
                
                $notas[$disciplina] = $nota ? $nota[$coluna_mt] : '';
            }
            
            // Calcular média
            $notas_validas = array_filter($notas, function($n) {
                return is_numeric($n) && $n > 0;
            });
            
            $media = !empty($notas_validas) ? array_sum($notas_validas) / count($notas_validas) : 0;
            
            // Determinar situação
            if ($media >= 10) {
                $situacao = 'TRANSITA';
            } elseif ($media > 0) {
                $situacao = 'NÃO TRANSITA';
            } else {
                $situacao = 'DESISTENTE';
            }
            
            $dados_alunos[$aluno['id']] = [
                'nome' => $aluno['nome'],
                'sexo' => $aluno['sexo'],
                'notas' => $notas,
                'media' => $media,
                'situacao' => $situacao
            ];
        }
        
        return [
            'alunos' => $dados_alunos,
            'disciplinas' => $disciplinas,
            'total_alunos' => count($alunos),
            'trimestre' => $trimestre
        ];
    }
    
    /**
     * Obtém disciplinas de uma turma
     */
    public function getDisciplinasTurma($classe, $turma) {
        $resultados = $this->db->fetchAll(
            "SELECT DISTINCT disciplina FROM {$this->tabela_notas} 
             WHERE classe = ? AND turma = ? ORDER BY disciplina",
            [$classe, $turma]
        );
        
        return array_column($resultados, 'disciplina');
    }
    
    /**
     * Gera HTML da pauta trimestral
     */
    public function gerarHTMLPautaTrimestral($dados, $classe, $turma, $trimestre, $turno, $sala, $ano_letivo) {
        if (isset($dados['erro'])) {
            return "<h1>Erro: {$dados['erro']}</h1>";
        }
        
        $html = "<!DOCTYPE html>
        <html lang='pt'>
        <head>
            <meta charset='UTF-8'>
            <title>PAUTA TRIMESTRAL - {$trimestre}º TRIMESTRE</title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
                .header { text-align: center; margin-bottom: 20px; }
                .header h1 { font-size: 16px; margin: 5px 0; }
                .header h2 { font-size: 14px; margin: 3px 0; }
                table { width: 100%; border-collapse: collapse; font-size: 11px; }
                th, td { border: 1px solid #333; padding: 5px; text-align: center; }
                th { background-color: #2c3e50; color: white; }
                .aprovado { background-color: #d4edda; }
                .reprovado { background-color: #f8d7da; }
                .desistente { background-color: #fff3cd; }
                .footer { margin-top: 20px; text-align: center; font-size: 10px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>" . htmlspecialchars($this->getNomeEscola()) . "</h1>
                <h2>PAUTA TRIMESTRAL - {$trimestre}º TRIMESTRE</h2>
                <p>CLASSE: {$classe}ª | TURMA: {$turma} | SALA: {$sala} | TURNO: {$turno}</p>
                <p>ANO LECTIVO: {$ano_letivo}</p>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Nº</th>
                        <th>NOME DO ALUNO</th>
                        <th>SEXO</th>";
        
        foreach ($dados['disciplinas'] as $disciplina) {
            $html .= "<th>" . htmlspecialchars(substr($disciplina, 0, 12)) . "</th>";
        }
        
        $html .= "
                        <th>MÉDIA</th>
                        <th>SITUAÇÃO</th>
                    </tr>
                </thead>
                <tbody>";
        
        $i = 1;
        foreach ($dados['alunos'] as $aluno_id => $aluno) {
            $classe_situacao = '';
            if ($aluno['situacao'] == 'TRANSITA') {
                $classe_situacao = 'aprovado';
            } elseif ($aluno['situacao'] == 'DESISTENTE') {
                $classe_situacao = 'desistente';
            } else {
                $classe_situacao = 'reprovado';
            }
            
            $html .= "
                    <tr class='{$classe_situacao}'>
                        <td>{$i}</td>
                        <td style='text-align:left;'>" . htmlspecialchars($aluno['nome']) . "</td>
                        <td>" . htmlspecialchars($aluno['sexo']) . "</td>";
            
            foreach ($dados['disciplinas'] as $disciplina) {
                $nota = isset($aluno['notas'][$disciplina]) ? $aluno['notas'][$disciplina] : '';
                $nota_exib = (is_numeric($nota) && $nota > 0) ? number_format($nota, 1) : '—';
                $html .= "<td>{$nota_exib}</td>";
            }
            
            $media_exib = ($aluno['media'] > 0) ? number_format($aluno['media'], 1) : '0,0';
            $html .= "
                        <td>{$media_exib}</td>
                        <td>{$aluno['situacao']}</td>
                    </tr>";
            $i++;
        }
        
        $html .= "
                </tbody>
            </table>
            
            <div class='footer'>
                <p>Total de Alunos: {$dados['total_alunos']}</p>
                <p>Data de Emissão: " . date('d/m/Y H:i') . "</p>
            </div>
        </body>
        </html>";
        
        return $html;
    }
    
    /**
     * Obtém nome da escola
     */
    private function getNomeEscola() {
        // Você pode carregar de um arquivo de configuração
        return "COMPLEXO ESCOLAR PRIVADO CASTELO REIS";
    }
}