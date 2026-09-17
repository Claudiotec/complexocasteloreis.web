<?php
/**
 * Classe Notas - Gerenciamento de Notas Escolares
 * Estilo SOFTGEST
 */

require_once dirname(__DIR__) . '/config/database.php';

class Notas {
    private $db;
    private $tabela = 'notas_alunos';
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
    }
    
    /**
     * Lança ou atualiza nota de um aluno
     */
    public function lancarNota($dados) {
        // Verificar se já existe registro
        $existe = $this->db->fetchOne(
            "SELECT id FROM {$this->tabela} 
             WHERE id_aluno = ? AND disciplina = ? AND classe = ? AND turma = ? AND ano_letivo = ?",
            [
                $dados['id_aluno'],
                $dados['disciplina'],
                $dados['classe'],
                $dados['turma'],
                $dados['ano_letivo']
            ]
        );
        
        if ($existe) {
            return $this->atualizarNota($dados);
        } else {
            return $this->inserirNota($dados);
        }
    }
    
    /**
     * Insere nova nota
     */
    private function inserirNota($dados) {
        // Calcular MTs
        $mt1 = ($dados['mac_t1'] + $dados['npt_t1']) / 2;
        $mt2 = ($dados['mac_t2'] + $dados['npt_t2']) / 2;
        $mt3 = ($dados['mac_t3'] + $dados['npt_t3']) / 2;
        
        // Calcular MFD
        $medias = [];
        if ($mt1 > 0) $medias[] = $mt1;
        if ($mt2 > 0) $medias[] = $mt2;
        if ($mt3 > 0) $medias[] = $mt3;
        
        $mfd = !empty($medias) ? array_sum($medias) / count($medias) : 0;
        $classificacao = $this->determinarClassificacao($mfd);
        
        $dadosInsert = [
            'id_aluno' => $dados['id_aluno'],
            'nome_aluno' => $dados['nome_aluno'],
            'disciplina' => $dados['disciplina'],
            'turma' => $dados['turma'],
            'classe' => $dados['classe'],
            'mac_t1' => $dados['mac_t1'] ?? 0,
            'npt_t1' => $dados['npt_t1'] ?? 0,
            'mt1' => $mt1,
            'mac_t2' => $dados['mac_t2'] ?? 0,
            'npt_t2' => $dados['npt_t2'] ?? 0,
            'mt2' => $mt2,
            'mac_t3' => $dados['mac_t3'] ?? 0,
            'npt_t3' => $dados['npt_t3'] ?? 0,
            'mt3' => $mt3,
            'mfd' => $mfd,
            'classificacao' => $classificacao,
            'sexo' => $dados['sexo'] ?? '',
            'idade' => $dados['idade'] ?? 0,
            'sala' => $dados['sala'] ?? '',
            'turno' => $dados['turno'] ?? '',
            'ano_letivo' => $dados['ano_letivo'],
            'data_lancamento' => date('Y-m-d')
        ];
        
        return $this->db->insert($this->tabela, $dadosInsert);
    }
    
    /**
     * Atualiza nota existente
     */
    private function atualizarNota($dados) {
        // Calcular MTs e MFD
        $mt1 = ($dados['mac_t1'] + $dados['npt_t1']) / 2;
        $mt2 = ($dados['mac_t2'] + $dados['npt_t2']) / 2;
        $mt3 = ($dados['mac_t3'] + $dados['npt_t3']) / 2;
        
        $medias = [];
        if ($mt1 > 0) $medias[] = $mt1;
        if ($mt2 > 0) $medias[] = $mt2;
        if ($mt3 > 0) $medias[] = $mt3;
        
        $mfd = !empty($medias) ? array_sum($medias) / count($medias) : 0;
        $classificacao = $this->determinarClassificacao($mfd);
        
        $dadosUpdate = [
            'mac_t1' => $dados['mac_t1'] ?? 0,
            'npt_t1' => $dados['npt_t1'] ?? 0,
            'mt1' => $mt1,
            'mac_t2' => $dados['mac_t2'] ?? 0,
            'npt_t2' => $dados['npt_t2'] ?? 0,
            'mt2' => $mt2,
            'mac_t3' => $dados['mac_t3'] ?? 0,
            'npt_t3' => $dados['npt_t3'] ?? 0,
            'mt3' => $mt3,
            'mfd' => $mfd,
            'classificacao' => $classificacao
        ];
        
        $where = "id_aluno = ? AND disciplina = ? AND classe = ? AND turma = ? AND ano_letivo = ?";
        $whereParams = [
            $dados['id_aluno'],
            $dados['disciplina'],
            $dados['classe'],
            $dados['turma'],
            $dados['ano_letivo']
        ];
        
        return $this->db->update($this->tabela, $dadosUpdate, $where, $whereParams);
    }
    
    /**
     * Determina classificação da nota
     */
    private function determinarClassificacao($mfd) {
        if ($mfd >= 18) return 'MUITO BOM';
        if ($mfd >= 14) return 'BOM';
        if ($mfd >= 10) return 'SUFICIENTE';
        if ($mfd >= 5) return 'MEDIOCRE';
        if ($mfd > 0) return 'MAU';
        return '';
    }
    
    /**
     * Busca notas de um aluno
     */
    public function getNotasAluno($id_aluno) {
        return $this->db->fetchAll(
            "SELECT * FROM {$this->tabela} WHERE id_aluno = ? ORDER BY disciplina",
            [$id_aluno]
        );
    }
    
    /**
     * Busca notas de uma turma
     */
    public function getNotasTurma($classe, $turma, $disciplina = null) {
        $query = "SELECT * FROM {$this->tabela} WHERE classe = ? AND turma = ?";
        $params = [$classe, $turma];
        
        if ($disciplina) {
            $query .= " AND disciplina = ?";
            $params[] = $disciplina;
        }
        
        $query .= " ORDER BY nome_aluno";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * Recalcula todas as MFDs
     */
    public function recalcularTodasMFDs() {
        $resultado = [
            'total' => 0,
            'atualizadas' => 0,
            'erros' => 0
        ];
        
        $registros = $this->db->fetchAll(
            "SELECT DISTINCT id_aluno, disciplina FROM {$this->tabela}"
        );
        
        $resultado['total'] = count($registros);
        
        foreach ($registros as $reg) {
            if ($this->recalcularMFD($reg['id_aluno'], $reg['disciplina'])) {
                $resultado['atualizadas']++;
            } else {
                $resultado['erros']++;
            }
        }
        
        return $resultado;
    }
    
    /**
     * Recalcula MFD de um aluno/disciplina
     */
    public function recalcularMFD($id_aluno, $disciplina) {
        $nota = $this->db->fetchOne(
            "SELECT mac_t1, npt_t1, mt1, mac_t2, npt_t2, mt2, mac_t3, npt_t3, mt3 
             FROM {$this->tabela} 
             WHERE id_aluno = ? AND disciplina = ?",
            [$id_aluno, $disciplina]
        );
        
        if (!$nota) return false;
        
        // Recalcular MTs
        $mt1 = ($nota['mac_t1'] + $nota['npt_t1']) / 2;
        $mt2 = ($nota['mac_t2'] + $nota['npt_t2']) / 2;
        $mt3 = ($nota['mac_t3'] + $nota['npt_t3']) / 2;
        
        // Atualizar MTs
        $this->db->update(
            $this->tabela,
            ['mt1' => $mt1, 'mt2' => $mt2, 'mt3' => $mt3],
            "id_aluno = ? AND disciplina = ?",
            [$id_aluno, $disciplina]
        );
        
        // Calcular MFD
        $medias = [];
        if ($mt1 > 0) $medias[] = $mt1;
        if ($mt2 > 0) $medias[] = $mt2;
        if ($mt3 > 0) $medias[] = $mt3;
        
        $mfd = !empty($medias) ? array_sum($medias) / count($medias) : 0;
        $classificacao = $this->determinarClassificacao($mfd);
        
        // Atualizar MFD
        return $this->db->update(
            $this->tabela,
            ['mfd' => $mfd, 'classificacao' => $classificacao],
            "id_aluno = ? AND disciplina = ?",
            [$id_aluno, $disciplina]
        );
    }
}