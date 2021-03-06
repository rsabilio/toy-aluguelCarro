<?php
//
// SIMP
// Descricao: Classe Permissoes dos Arquivos
// Autor: Rubens Takiguti Ribeiro
// Orgao: TecnoLivre - Cooperativa de Tecnologia e Solucoes Livres
// E-mail: rubens@tecnolivre.com.br
// Versao: 1.1.0.13
// Data: 10/09/2007
// Modificado: 21/06/2011
// Copyright (C) 2007  Rubens Takiguti Ribeiro
// License: LICENSE.TXT
//
final class permissao extends permissao_base {

    //
    //     Operacao pre-salvar
    //
    public function pre_salvar(&$salvar_campos) {
    // Array[String] $salvar_campos: campos a serem salvos
    //
        $r = true;

        $posicao = $this->get_atributo('posicao');
        $grupo   = $this->get_atributo('cod_grupo');

        switch ($this->id_form) {
        case $this->id_formulario_excluir():

            // UPDATE {$tabela} SET posicao = posicao - 1 WHERE cod_grupo = '{$grupo}' AND posicao > '{$posicao}'
            $dados = new stdClass();
            $dados->posicao = 'sql:'.self::$dao->delimitar_campo('posicao').' - 1';

            $vt_condicoes = array();
            $vt_condicoes[] = condicao_sql::montar('cod_grupo', '=', $grupo);
            $vt_condicoes[] = condicao_sql::montar('posicao', '>', $posicao);
            $condicoes = condicao_sql::sql_and($vt_condicoes);

            if (!self::$dao->update($this, $dados, $condicoes)) {
                $r = false;
                $this->erros[] = 'Erro ao atualizar posi&ccedil;&otilde;es';
            }
            break;

        case $this->id_formulario_inserir():
            $vt_condicoes = array();
            $vt_condicoes[] = condicao_sql::montar('cod_arquivo', '=', $this->get_atributo('cod_arquivo'));
            $vt_condicoes[] = condicao_sql::montar('cod_grupo', '=', $this->get_atributo('cod_grupo'));
            $condicoes = condicao_sql::sql_and($vt_condicoes);

            if ($this->possui_registros($condicoes)) {
                $this->erros[] = 'Este grupo j&aacute; tem permiss&atilde;o para este arquivo';
                return false;
            }

            // UPDATE {$tabela} SET posicao = posicao + 1 WHERE cod_grupo = '{$grupo}' AND posicao >= '{$posicao}'
            $dados = new stdClass();
            $dados->posicao = 'sql:'.self::$dao->delimitar_campo('posicao').' + 1';

            $vt_condicoes = array();
            $vt_condicoes[] = condicao_sql::montar('cod_grupo', '=', $grupo);
            $vt_condicoes[] = condicao_sql::montar('posicao', '>=', $posicao);
            $condicoes = condicao_sql::sql_and($vt_condicoes);
            if (!self::$dao->update($this, $dados, $condicoes)) {
                $r = false;
                $this->erros[] = 'Erro ao atualizar posi&ccedil;&otilde;es';
            }
            break;
        }
        return $r;
    }


    //
    //     Operacoes pos-salvar
    //
    public function pos_salvar() {
        $r = true;
        switch ($this->id_form) {
        case $this->id_formulario_inserir():
        case $this->id_formulario_excluir():
            objeto::limpar_cache('permissao');
            objeto::limpar_cache('grupo');
            objeto::limpar_cache('usuario');
            break;
        }
        return $r;
    }


    //
    //     Obtem a posicao da ultima permissao de um grupo
    //
    public function maior($cod_grupo) {
    // Int $cod_grupo: codigo do grupo
    //
        $cod_grupo = (int)$cod_grupo;
        $condicao = condicao_sql::montar('cod_grupo', '=', $cod_grupo);
        return self::$dao->select_maior($this, 'posicao', $condicao);
    }


    //
    //     Desce um item
    //
    public function descer() {
        $r = true;
        if (!$this->existe()) {
            $this->erros[] = 'N&atilde;o pode descer um item que n&atilde;o existe';
            return false;
        }

        // Obter proxima posicao
        $proxima_permissao = new self();
        $vt_condicoes = array();
        $vt_condicoes[] = condicao_sql::montar('cod_grupo', '=', $this->get_atributo('cod_grupo'));
        $vt_condicoes[] = condicao_sql::montar('posicao', '=', $this->get_atributo('posicao') + 1);
        $condicoes = condicao_sql::sql_and($vt_condicoes);
        $proxima_permissao->consultar_condicoes($condicoes, array('posicao'));

        // Se nao existe proxima posicao
        if (!$proxima_permissao->existe()) {
            $this->erros[] = 'N&atilde;o pode descer este item';
            $r = false;

        // Alterar as posicoes
        } else {
            $posicao = $this->get_atributo('posicao');
            $this->__set('posicao', $posicao + 1);
            $proxima_permissao->__set('posicao', $posicao);

            $r = objeto::inicio_transacao(DRIVER_BASE_SERIALIZABLE) && $r;
            $r = $this->salvar(array('posicao')) && $r;
            $r = $proxima_permissao->salvar(array('posicao')) && $r;
            $r = objeto::fim_transacao(!$r) && $r;
            if (!$r) {
                $this->erros[] = 'Erro ao descer o item no menu';
            }
        }
        return $r;
    }


    //
    //     Sobe um item
    //
    public function subir() {
        $r = true;
        if (!$this->existe()) {
            $this->erros[] = 'N&atilde;o pode subir um item que n&atilde;o existe';
            return false;
        }

        // Obter proxima posicao
        $permissao_anterior = new self();
        $vt_condicoes = array();
        $vt_condicoes[] = condicao_sql::montar('cod_grupo', '=', $this->get_atributo('cod_grupo'));
        $vt_condicoes[] = condicao_sql::montar('posicao', '=', $this->get_atributo('posicao') - 1);
        $condicoes = condicao_sql::sql_and($vt_condicoes);
        $permissao_anterior->consultar_condicoes($condicoes, array('posicao'));

        // Se nao existe posicao anterior
        if (!$permissao_anterior->existe()) {
            $this->erros[] = 'N&atilde;o pode subir este item';
            return false;

        // Alterar as posicoes
        } else {
            $posicao = $this->get_atributo('posicao');
            $this->__set('posicao', $posicao - 1);
            $permissao_anterior->__set('posicao', $posicao);

            $r = objeto::inicio_transacao(DRIVER_BASE_SERIALIZABLE) && $r;
            $r = $this->salvar(array('posicao')) && $r;
            $r = $permissao_anterior->salvar(array('posicao')) && $r;
            $r = objeto::fim_transacao(!$r) && $r;
            if (!$r) {
                $this->erros[] = 'Erro ao subir o item no menu';
            }
        }
        return $r;
    }


    //
    //     Imprime um campo do formulario
    //
    public function campo_formulario(&$form, $campo, $valor) {
    // formulario $form: objeto do tipo formulario
    // String $campo: campo a ser adicionado
    // Mixed $valor: valor a ser preenchido automaticamente
    //
        if ($this->possui_atributo($campo)) {
            $atributo = $this->get_definicao_atributo($campo);
        }

        switch ($campo) {
        case 'cod_arquivo':
            $modulos = listas::get_modulos();
            $obj = $this->get_objeto_rel_uu('arquivo');
            $vetor = array();
            foreach ($modulos as $modulo) {
                $condicoes = condicao_sql::montar('modulo', '=', $modulo);
                $vetor[$modulo] = $obj->vetor_associativo('cod_arquivo', 'arquivo', $condicoes);
            }
            self::preparar_vetor_select($vetor);
            $form->campo_select($atributo->nome, $atributo->nome, $vetor, $valor, $obj->get_entidade());
            return true;

        case 'posicao':
            if ($cod_grupo = $this->get_atributo('cod_grupo')) {
                if (!$valor) {
                    $condicao = condicao_sql::montar('cod_grupo', '=', $cod_grupo);
                    $valor = $this->quantidade_registros($condicao) + 1;
                } else {
                    $condicao = null;
                }
                $form->campo_relacionamento($atributo->nome, $atributo->nome, 'permissao', 'posicao', 'arquivo:descricao', $condicao, $valor, 3, 5, $atributo->get_label($this->id_form));
            } else {
                $form->campo_text($atributo->nome, $atributo->nome, $valor, $atributo->tamanho_maximo, 30, $atributo->get_label($this->id_form));
            }
            return true;
        }
        return parent::campo_formulario($form, $campo, $valor);
    }


    //
    //     Retorna o conteudo do arquivo INI de permissoes para um grupo
    //
    public function get_ini($grupo, &$nome_arquivo = '') {
    // grupo $grupo: grupo desejado
    // String $nome_arquivo: sugestao de nome de arquivo
    //
        $nome_grupo = texto::strtolower(texto::strip_acentos($grupo->nome));
        $vt_nome_grupo = explode(' ', $nome_grupo);
        $vt_nome_grupo_novo = array();
        foreach ($vt_nome_grupo as $parte_nome_grupo) {
            switch ($parte_nome_grupo) {
            case 'o':
            case 'a':
            case 'os':
            case 'as':
            case 'de':
            case 'do':
            case 'da':
            case 'dos':
            case 'das':
            case 'em':
            case 'no':
            case 'na':
            case 'nos':
            case 'nas':
                break;
            default:
                $vt_nome_grupo_novo[] = $parte_nome_grupo;
                break;
            }
        }
        $nome_arquivo = implode('_', $vt_nome_grupo_novo).'.ini';
        $data = strftime('%d/%m/%Y');
        $ini = <<<INI
;
; SIMP
; Descricao: Permissoes do grupo {$nome_grupo}
; Autor: simp
; Versao: 1.0.0.0
; Data: {$data}
; Modificado: {$data}
; License: LICENSE.TXT
;

; Codigo do grupo em questao
cod_grupo = {$grupo->cod_grupo}


INI;

        $ordem = array('posicao' => true);
        $condicao = condicao_sql::montar('cod_grupo', '=', $grupo->cod_grupo);
        $modulos = $this->vetor_associativo('cod_permissao', 'arquivo:modulo', $condicao, $ordem);
        $modulos = array_unique(array_values($modulos));

        foreach ($modulos as $modulo) {
            if ($modulo) {
                $ini .= "[{$modulo}]\n";
            } else {
                $ini .= "[simp]\n";
            }
            $condicao2 = condicao_sql::montar('arquivo:modulo', '=', $modulo);
            $condicao3 = condicao_sql::sql_and(array($condicao, $condicao2));
            $permissoes = $this->vetor_associativo('arquivo:arquivo', 'visivel', $condicao3, $ordem);
            if (!empty($permissoes)) {
                $maior = max(array_map('strlen', array_keys($permissoes)));
                foreach ($permissoes as $arq => $visivel) {
                    $visivel = $visivel ? 1 : 0;
                    $ini .= sprintf("%-{$maior}s = %d\n", $arq, $visivel);
                }
            } else {
                $ini .= "; nenhuma permissao\n";
            }
            $ini .= "\n";
        }
        return $ini;
    }

}//class
