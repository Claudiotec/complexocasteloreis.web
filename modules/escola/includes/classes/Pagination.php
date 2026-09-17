<?php
/**
 * Classe de Paginação
 * 
 * @package Softgest
 * @subpackage Includes/Classes
 * @version 1.0.0
 */

class Pagination
{
    /**
     * @var int Total de registros
     */
    private $total_registros;
    
    /**
     * @var int Registros por página
     */
    private $por_pagina;
    
    /**
     * @var int Página atual
     */
    private $pagina_atual;
    
    /**
     * @var int Total de páginas
     */
    private $total_paginas;
    
    /**
     * @var string URL base para links
     */
    private $url_base;
    
    /**
     * @var array Parâmetros GET
     */
    private $parametros;
    
    /**
     * @var int Número de links a exibir antes/depois
     */
    private $intervalo = 3;
    
    /**
     * Construtor
     * 
     * @param int $total_registros Total de registros
     * @param int $por_pagina Registros por página
     * @param int $pagina_atual Página atual
     * @param string $url_base URL base (opcional)
     * @param array $parametros Parâmetros GET (opcional)
     */
    public function __construct($total_registros, $por_pagina = 50, $pagina_atual = 1, $url_base = '', $parametros = [])
    {
        $this->total_registros = (int)$total_registros;
        $this->por_pagina = (int)$por_pagina;
        $this->pagina_atual = max(1, (int)$pagina_atual);
        $this->total_paginas = ceil($this->total_registros / $this->por_pagina);
        $this->url_base = $url_base ?: $_SERVER['SCRIPT_NAME'];
        $this->parametros = $parametros ?: $_GET;
        
        // Remove o parâmetro 'pagina' dos parâmetros
        unset($this->parametros['pagina']);
    }
    
    /**
     * Obtém o offset para a query SQL
     * 
     * @return int
     */
    public function getOffset()
    {
        return ($this->pagina_atual - 1) * $this->por_pagina;
    }
    
    /**
     * Obtém o limit para a query SQL
     * 
     * @return string
     */
    public function getLimit()
    {
        return $this->por_pagina;
    }
    
    /**
     * Gera o HTML da paginação
     * 
     * @param string $classe Classe CSS para o container
     * @param string $tema Tema (bootstrap, simples)
     * @return string
     */
    public function render($classe = 'pagination', $tema = 'bootstrap')
    {
        if ($this->total_paginas <= 1) {
            return '';
        }
        
        if ($tema == 'bootstrap') {
            return $this->renderBootstrap($classe);
        }
        
        return $this->renderSimples($classe);
    }
    
    /**
     * Renderiza com estilo Bootstrap
     * 
     * @param string $classe
     * @return string
     */
    private function renderBootstrap($classe = 'pagination')
    {
        $html = '<nav aria-label="Paginação">';
        $html .= '<ul class="' . $classe . ' justify-content-center">';
        
        // Primeira página
        $html .= $this->getItemLink(1, '&laquo;', 'Primeira', 'Primeira página');
        
        // Página anterior
        $pagina_anterior = $this->pagina_atual - 1;
        if ($pagina_anterior >= 1) {
            $html .= $this->getItemLink($pagina_anterior, '&lsaquo;', 'Anterior', 'Página anterior');
        } else {
            $html .= $this->getItemLink(0, '&lsaquo;', 'Anterior', 'Página anterior', true);
        }
        
        // Páginas do intervalo
        $pagina_inicial = max(1, $this->pagina_atual - $this->intervalo);
        $pagina_final = min($this->total_paginas, $this->pagina_atual + $this->intervalo);
        
        if ($pagina_inicial > 1) {
            $html .= $this->getItemLink(1, '1');
            if ($pagina_inicial > 2) {
                $html .= $this->getItemLink(0, '...', 'Desabilitado', '', true);
            }
        }
        
        for ($i = $pagina_inicial; $i <= $pagina_final; $i++) {
            $html .= $this->getItemLink($i, $i, $i == $this->pagina_atual ? 'active' : '');
        }
        
        if ($pagina_final < $this->total_paginas) {
            if ($pagina_final < $this->total_paginas - 1) {
                $html .= $this->getItemLink(0, '...', 'Desabilitado', '', true);
            }
            $html .= $this->getItemLink($this->total_paginas, $this->total_paginas);
        }
        
        // Próxima página
        $pagina_proxima = $this->pagina_atual + 1;
        if ($pagina_proxima <= $this->total_paginas) {
            $html .= $this->getItemLink($pagina_proxima, '&rsaquo;', 'Próximo', 'Próxima página');
        } else {
            $html .= $this->getItemLink(0, '&rsaquo;', 'Próximo', 'Próxima página', true);
        }
        
        // Última página
        $html .= $this->getItemLink($this->total_paginas, '&raquo;', 'Última', 'Última página');
        
        $html .= '</ul>';
        $html .= '</nav>';
        
        return $html;
    }
    
    /**
     * Renderiza estilo simples
     * 
     * @param string $classe
     * @return string
     */
    private function renderSimples($classe = 'pagination')
    {
        $html = '<div class="' . $classe . '">';
        $html .= '<span>Página ' . $this->pagina_atual . ' de ' . $this->total_paginas . '</span>';
        $html .= ' | ';
        
        if ($this->pagina_atual > 1) {
            $html .= '<a href="' . $this->getUrl(1) . '">Primeira</a>';
            $html .= ' <a href="' . $this->getUrl($this->pagina_atual - 1) . '">&laquo; Anterior</a>';
        }
        
        $html .= ' | ';
        
        if ($this->pagina_atual < $this->total_paginas) {
            $html .= '<a href="' . $this->getUrl($this->pagina_atual + 1) . '">Próxima &raquo;</a>';
            $html .= ' <a href="' . $this->getUrl($this->total_paginas) . '">Última</a>';
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Gera um item da paginação
     * 
     * @param int $pagina
     * @param string $texto
     * @param string $classe_adicional
     * @param string $title
     * @param bool $desabilitado
     * @return string
     */
    private function getItemLink($pagina, $texto, $classe_adicional = '', $title = '', $desabilitado = false)
    {
        if ($desabilitado || $pagina <= 0) {
            return '<li class="page-item disabled"><span class="page-link">' . $texto . '</span></li>';
        }
        
        $classe = 'page-item';
        if ($classe_adicional == 'active') {
            $classe .= ' active';
        }
        
        $url = $this->getUrl($pagina);
        $title_attr = $title ? ' title="' . htmlspecialchars($title) . '"' : '';
        
        return '<li class="' . $classe . '"><a class="page-link" href="' . $url . '"' . $title_attr . '>' . $texto . '</a></li>';
    }
    
    /**
     * Monta a URL com os parâmetros
     * 
     * @param int $pagina
     * @return string
     */
    private function getUrl($pagina)
    {
        $params = $this->parametros;
        $params['pagina'] = $pagina;
        
        return $this->url_base . '?' . http_build_query($params);
    }
    
    /**
     * Obtém o total de registros
     * 
     * @return int
     */
    public function getTotalRegistros()
    {
        return $this->total_registros;
    }
    
    /**
     * Obtém o total de páginas
     * 
     * @return int
     */
    public function getTotalPaginas()
    {
        return $this->total_paginas;
    }
    
    /**
     * Obtém a página atual
     * 
     * @return int
     */
    public function getPaginaAtual()
    {
        return $this->pagina_atual;
    }
    
    /**
     * Verifica se há mais páginas
     * 
     * @return bool
     */
    public function hasNext()
    {
        return $this->pagina_atual < $this->total_paginas;
    }
    
    /**
     * Verifica se há páginas anteriores
     * 
     * @return bool
     */
    public function hasPrev()
    {
        return $this->pagina_atual > 1;
    }
}
?>