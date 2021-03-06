<?php
//
// SIMP
// Descricao: Classe singleton que realiza validacoes comuns
// Autor: Rubens Takiguti Ribeiro
// Orgao: TecnoLivre - Cooperativa de Tecnologia e Solucoes Livres
// E-mail: rubens@tecnolivre.com.br
// Versao: 1.0.1.6
// Data: 09/08/2007
// Modificado: 09/05/2011
// Copyright (C) 2007  Rubens Takiguti Ribeiro
// License: LICENSE.TXT
//

// Constantes
define('VALIDACAO_CHARSET',               strtolower($CFG->charset));
define('VALIDACAO_UTF8',                  $CFG->utf8);
define('VALIDACAO_CHECAR_DOMINIO_EMAIL',  false);  // Disponivel para Windows a partir do PHP 5.3
define('VALIDACAO_TIPO_CHECAGEM_DOMINIO', 'MX');  // Usado no parametro $type da funcao checkdnsrr

final class validacao {
    private static $instancia = null;


    //
    //     Gera um objeto da classe validacao
    //
    public static function &get_instancia() {
        if (self::$instancia === null) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }


    //
    //     Construtor privado: use o metodo get_instancia
    //
    private function __construct() {}


    //
    //     Valida se um atributo esta vazio ou nao
    //
    public function valor_vazio(&$atributo, $valor) {
    // atributo $atributo: Dados do atributo a ser validado
    // Mixed $valor: valor a ser testado
    //
        switch ($atributo->tipo) {
        case 'data':
            switch ($atributo->campo_formulario) {
            case 'data':
            case 'data_hora':
                $data = objeto::parse_data($valor);
                if ($data['dia'] == 0 && $data['mes'] == 0 && $data['ano'] == 0) {
                    return true;
                }
                break;
            }
            break;
        case 'string':
        case 'int':
        case 'float':
        case 'char':
        case 'binario':
            if ($valor === '' || $valor === null || $valor === false) {
                return true;
            }
            break;
        case 'bool':
            if ($valor === '' || $valor === null) {
                return true;
            }
            break;
        }
        return false;
    }


    //
    //     Valida um atributo
    //
    public function validar_atributo(&$atributo, $valor, &$erros = array(), $incluir_instrucoes = false) {
    // atributo $atributo: descricao do atributo
    // Mixed $valor: valor a ser testado
    // Array[String] $erros: vetor de erros
    // Bool $incluir_instrucoes: inclui instrucoes sobre preenchimento caso preencha incorretamente
    //
        $erros = array();

        // Retorno da funcao
        $valido = true;

        // Se pode vazio
        if ($atributo->pode_vazio) {

            // Se o valor e' vazio: esta' valido
            if ($this->valor_vazio($atributo, $valor)) {
                return true;
            }

        // Se nao pode vazio
        } else {

            // Se esta vazio: nao e' valido
            if ($this->valor_vazio($atributo, $valor)) {
                $erros[] = 'Campo "'.$atributo->descricao.'" &eacute; obrigat&oacute;rio, mas n&atilde;o foi preenchido';
                return false;
            }
        }

        // Validar o campo com uma validacao geral
        $tipo_validacao = $atributo->validacao;
        if ($tipo_validacao) {
            if (!$this->validar_campo($tipo_validacao, $valor, $erro_campo)) {
                $definicao = self::get_definicao_tipo($tipo_validacao);

                $valido = false;
                if (!DEVEL_BLOQUEADO) {
                    $erros[] = '[DEBUG-DEVEL] Erro no campo '.$atributo->classe.':'.$atributo->nome.' = "'.texto::codificar($valor).'" ('.gettype($valor).')';
                }
                if ($incluir_instrucoes) {
                    $instrucoes = ' <em>Instru&ccedil;&otilde;es de preenchimento:</em> '.$definicao->instrucoes.
                                  ' <em>Exemplo:</em> '.$definicao->exemplo;
                } else {
                    $instrucoes = '';
                }
                $erros[] = 'Campo "'.$atributo->descricao.'" possui caracteres inv&aacute;lidos ou n&atilde;o est&aacute; no padr&atilde;o.'.
                           ($erro_campo ? ' <em>Detalhes:</em> '.$erro_campo : '').
                           $instrucoes;
            }
        }

        // Validar o tipo
        $novo_valor = null;
        switch ($atributo->tipo) {
        case 'int':
            if (is_int($valor)) {
                $novo_valor = $valor;
            } elseif (is_float($valor)) {
                $novo_valor = round($valor);
            } elseif (is_string($valor)) {
                $conv = self::get_convencoes_localidade();
                $sinal = preg_quote($conv['positive_sign'].$conv['negative_sign']);
                $milhar = preg_quote($conv['thousands_sep']);
                if ($milhar === '') {
                    $exp = '/^['.$sinal.']?\d+$/';
                } else {
                    $exp = '/^['.$sinal.']?\d{1,3}(['.$milhar.']\d{3})*$/';
                }
                if (preg_match($exp, $valor)) {
                    $novo_valor = $atributo->filtrar($valor);
                } else {
                    $valido = false;
                    $erros[] = 'Campo "'.$atributo->descricao.'" n&atilde;o &eacute; um n&uacute;mero inteiro v&aacute;lido (Exemplos de n&uacute;meros inteiros: '.texto::numero(12345).' ou '.texto::numero(-12345).')';
                }
            } else {
                $valido = false;
                $erros[] = 'Campo "'.$atributo->descricao.'" n&atilde;o &eacute; um n&uacute;mero inteiro v&aacute;lido (Exemplos de n&uacute;meros inteiros: '.texto::numero(12345).' ou '.texto::numero(-12345).')';
            }
            break;

        case 'float':
            if (is_int($valor) || is_float($valor)) {
                $novo_valor = (float)$valor;
                if ($atributo->casas_decimais) {
                    $novo_valor = round($novo_valor, $atributo->casas_decimais);
                }
            } elseif (is_string($valor)) {
                $conv = self::get_convencoes_localidade();
                $sinal = preg_quote($conv['positive_sign'].$conv['negative_sign']);
                $milhar = preg_quote($conv['thousands_sep']);
                $decimal = preg_quote($conv['decimal_point']);
                
                if ($milhar === '') {
                    $exp = '/^['.$sinal.']?\d+(?:['.$decimal.'](\d+))?$/';
                } else {
                    $exp = '/^['.$sinal.']?\d{1,3}(?:['.$milhar.']\d{3})*(?:['.$decimal.'](\d+))?$/';
                }
                if (preg_match($exp, $valor, $matches)) {

                    // Validar casas decimais
                    if (is_numeric($atributo->casas_decimais)) {

                        // Se possui casas decimais
                        if (isset($matches[1])) {

                            // Contar casas decimais
                            $len = strlen($matches[1]) - 1;
                            for ($i = $len; $i >= 0; --$i) {
                                if ($matches[1][$i] == '0') {
                                    $len -= 1;
                                } else {
                                    $len += 1;
                                    break;
                                }
                            }
                            if ($len > $atributo->casas_decimais) {
                                $valido = false;
                                $erros[] = 'Campo "'.$atributo->descricao.'" s&oacute; permite '.texto::numero($atributo->casas_decimais).' casas decimais';
                            }
                        }
                    }
                } else {
                    $valido = false;
                    $erros[] = 'Campo "'.$atributo->descricao.'" n&atilde;o &eacute; um n&uacute;mero real v&aacute;lido (Exemplos de n&uacute;meros reais: '.texto::numero(12345.6789).' ou '.texto::numero(-12345.6789).')';
                }
                if ($valido) {
                    $novo_valor = $atributo->filtrar($valor);
                }

            } else {
                $valido = false;
                $erros[] = 'Campo "'.$atributo->descricao.'" n&atilde;o &eacute; um n&uacute;mero real v&aacute;lido (Exemplos de n&uacute;meros reais: '.texto::numero(12345.6789).' ou '.texto::numero(-12345.6789).')';
            }
            break;

        case 'char':
            if (is_string($valor) && texto::strlen($valor) == 1) {
                $novo_valor = $valor;
            } else {
                $valido = false;
                $erros[] = 'Campo "'.$atributo->descricao.'" n&atilde;o &eacute; um caractere v&aacute;lido';
            }
            break;

        case 'string':
            $novo_valor = (string)$valor;
            if (VALIDACAO_UTF8 && !texto::is_utf8($novo_valor)) {
                $valido = false;
                $erros[] = 'Voc&ecirc; est&aacute; utilizando caracteres n&atilde;o permitidos';
            }
            break;

        case 'data':
            $data = objeto::parse_data($valor, false);
            switch ($atributo->campo_formulario) {
            case 'data':
                if (!self::validar_data($atributo, $data, $erros)) {
                    $valido = false;
                }
                break;
            case 'hora':
                if (!self::validar_hora($atributo, $data, $erros)) {
                    $valido = false;
                }
                break;
            case 'data_hora':
                if (!self::validar_data($atributo, $data, $erros)) {
                    $valido = false;
                }
                if (!self::validar_hora($atributo, $data, $erros)) {
                    $valido = false;
                }
                break;
            }
            break;
        }

        // Se nao validou ate aqui
        if (!$valido) {
            return false;
        }

        // Validar o tamanho
        switch ($atributo->tipo) {
        case 'int':
        case 'float':
            if (is_numeric($atributo->minimo) && ($novo_valor < $atributo->minimo)) {
                $valido = false;
                if ($atributo->minimo >= 0 && $novo_valor < 0) {
                    $erros[] = "Campo \"{$atributo->descricao}\" n&atilde;o pode ser negativo (valor m&iacute;nimo: {$atributo->minimo})";
                } else {
                    $erros[] = "Campo \"{$atributo->descricao}\" est&aacute; abaixo do valor m&iacute;nimo ({$atributo->minimo})";
                }
            }
            if (is_numeric($atributo->maximo) && ($novo_valor > $atributo->maximo)) {
                $valido = false;
                if ($atributo->maximo <= 0 && $novo_valor > 0) {
                    $erros[] = "Campo \"{$atributo->descricao}\" n&atilde;o pode ser positivo (valor m&aacute;ximo: {$atributo->maximo})";
                } else {
                    $erros[] = "Campo \"{$atributo->descricao}\" est&aacute; acima do valor m&aacute;ximo ({$atributo->maximo})";
                }
            }
            break;

        case 'string';
            $valor_decode = utf8_decode($novo_valor);
            if (is_numeric($atributo->minimo) && ($atributo->minimo > 0) && (!isset($valor_decode[$atributo->minimo - 1]))) {
                $valido = false;
                $erros[] = "Campo \"{$atributo->descricao}\" n&atilde;o tem o tamanho m&iacute;nimo de caracteres (m&iacute;nimo: {$atributo->minimo})";
            }
            if (is_numeric($atributo->maximo) && (isset($valor_decode[$atributo->maximo]))) {
                $valido = false;
                $erros[] = "Campo \"{$atributo->descricao}\" ultrapassa o tamanho m&aacute;ximo de caracteres (m&aacute;ximo: {$atributo->maximo})";
            }
            break;

        case 'data':
            if ($atributo->minimo && objeto::comparar_datas($valor, $atributo->minimo) < 0) {
                $valido = false;
                $minimo = objeto::parse_data($atributo->minimo);
                $erros[] = "Campo \"{$atributo->descricao}\" &eacute; anterior &agrave; data ".sprintf('%02d/%02d/%04d', $minimo['dia'], $minimo['mes'], $minimo['ano']);
            }
            if ($atributo->maximo && objeto::comparar_datas($valor, $atributo->maximo) > 0) {
                $valido = false;
                $maximo = objeto::parse_data($atributo->maximo);
                $erros[] = "Campo \"{$atributo->descricao}\" &eacute; posterior &agrave; data ".sprintf('%02d/%02d/%04d', $maximo['dia'], $maximo['mes'], $maximo['ano']);
            }
            break;
        }

        return $valido;
    }


    //
    //     Retorna um vetor com os nomes dos tipos de validacao
    //
    static public function get_tipos() {
        return array(
            'ARQUIVO', 'BD', 'CEP', 'CNPJ', 'CPF', 'DN', 'DOMINIO', 'EMAIL', 'FONE', 'HOST',
            'IDENTIFICADOR', 'IP', 'LETRAS', 'LETRAS_NUMEROS', 'LOGIN', 'MODULO', 'NOME',
            'NOME_ARQUIVO', 'NOME_INCOMPLETO', 'NUMERICO', 'NUMERICO_PONTO', 'PLACA_VEICULO',
            'RG', 'SENHA', 'SITE', 'TEXTO', 'TEXTO_LINHA'
        );
    }


    //
    //     Obtem a definicao dos tipos de validacao (a maioria deles esta na descricao do metodo validar_campo)
    //
    static public function get_definicao_tipo($tipo) {
    // String $tipo: nome do tipo de validacao
    //
        $definicao = new stdClass();

        // Lista de caracteres
        $letras_maiusculas = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $letras_minusculas = 'abcdefghijklmnopqrstuvwxyz';
        $letras_acentuadas = self::acentos(true);
        $str_simbolos      = self::simbolos(true);
        $str_quebra_linha  = self::quebra_linha(true);
        $str_espaco        = self::espaco(true);
        $numeros           = '0123456789';
        $acentos           = self::acentos();
        $simbolos          = self::simbolos();
        $quebra_linha      = self::quebra_linha();
        $espaco            = self::espaco();

        $u = VALIDACAO_UTF8 ? 'u' : '';
        switch ($tipo) {
        case 'BD':
            $definicao->padrao = '/^[A-Za-z0-9-_]+$/'.$u;
            $definicao->permite = $letras_maiusculas.$letras_minusculas.$numeros.'-_';
            $definicao->instrucoes = 'Preencha o nome do BD. Use letras, n&uacute;meros, menos ou underscore.';
            $definicao->exemplo = '"teste".';
            break;
        case 'CEP':
            $definicao->padrao = '/^[0-9]{5}-[0-9]{3}$/'.$u;
            $definicao->permite = $numeros.'-';
            $definicao->instrucoes = 'Preencha o CEP com o formato XXXXX-XXX (cinco n&uacute;meros, h&iacute;fen e tr&ecirc;s n&uacute;meros).';
            $definicao->exemplo = '"37200-000".';
            break;
        case 'CNPJ':
            $definicao->padrao = '/^[0-9]{14}$/'.$u;
            $definicao->permite = $numeros;
            $definicao->instrucoes = 'Preencha o CNPJ apenas com n&uacute;meros, inclusive o digito verificador.';
            $definicao->exemplo = '"12345678901234".';
            break;
        case 'CPF':
            $definicao->padrao = '/^[0-9]{11}$/'.$u;
            $definicao->permite = $numeros;
            $definicao->instrucoes = 'Preencha o CPF apenas com n&uacute;meros, inclusive o digito verificador.';
            $definicao->exemplo = '"12345678901".';
            break;
        case 'DN':
            $definicao->padrao = '/^([A-Za-z0-0-_'.$acentos.$espaco.',=])+$/'.$u;
            $definicao->permite = $letras_maiusculas.$letras_minusculas.$letras_acentuadas.$str_espaco.'=-_,';
            $definicao->instrucoes = 'Preencha o DN usando letras, n&uacute;meros, menos, underscore, acentos, espa&ccedil;o, v&iacute;rgula e sinal de igual.';
            $definicao->exemplo = '"o=TecnoLivre, c=BR".';
            break;
        case 'DOMINIO': // completo ou nao
            $definicao->padrao = '/^(\.?[A-Za-z0-9-_]+)+$/'.$u;
            $definicao->permite = $letras_maiusculas.$letras_minusculas.$numeros.'-_.';
            $definicao->instrucoes = 'Preencha o dom&iacute;nio usando palavras com letras, n&uacute;meros, menos e underscore separadas por ponto.';
            $definicao->exemplo = '"exemplo.com.br".';
            break;
        case 'EMAIL':
            $definicao->padrao = '/^[A-Za-z0-9](\.?[A-Za-z0-9-_]+)*@(([A-Za-z0-9-_]+)(\.[A-Za-z0-9-_]+)+)$/'.$u;
            $definicao->permite = $letras_maiusculas.$letras_minusculas.$numeros.'.-_@';
            $definicao->instrucoes = 'Preencha com um e-mail v&aacute;lido, composto por um prefixo, um sinal arroba (@) e um sufixo.';
            $definicao->exemplo = '"exemplo@dominio.com.br".';
            break;
        case 'FONE':
            $definicao->padrao = '/^(\+([0-9]+)[\040])?\(([0-9]+)\)[\040]([0-9]{4}\-[0-9]{4})([\040]([0-9]+))?$/'.$u;
            $definicao->permite = $numeros.'()-+ ';
            $definicao->instrucoes = 'Preencha com um telefone no formato (XX) XXXX-XXXX e, quando permitido, incluir o c&oacute;digo do pa&iacute;s e/ou o ramal.';
            $definicao->exemplo = '"(35) 9876-5432".';
            break;
        case 'HOST': // completo
            $definicao->padrao = '/^([A-Za-z0-9-_]+)|[A-Za-z0-9-_]+(\.[A-Za-z0-9-_]+)+$/'.$u;
            $definicao->permite = $letras_maiusculas.$letras_minusculas.$numeros.'-_.';
            $definicao->instrucoes = 'Preencha com um host completo. Use palavras com letras, n&uacute;meros, menos ou underscore separadas por ponto.';
            $definicao->exemplo = '"exemplo.com.br".';
            break;
        case 'IDENTIFICADOR':
            $definicao->padrao = '/^[A-Za-z0-9_]*$/'.$u;
            $definicao->permite = $letras_maiusculas.$letras_minusculas.$numeros.'_';
            $definicao->instrucoes = 'Preenchar com letras, n&uacute;meros ou underscore.';
            $definicao->exemplo = '"ALUNO_MATRICULADO".';
            break;
        case 'IP':
            $definicao->padrao = '/^[0-9]{1,3}(\.[0-9]{1,3}){3}$/'.$u;
            $definicao->permite = $numeros.'.';
            $definicao->instrucoes = 'Preencha com um IP v&aacute;lido. Use quatro n&uacute;meros entre 0 e 255 separados por ponto.';
            $definicao->exemplo = '"123.255.1.142".';
            break;
        case 'LETRAS':
            $definicao->padrao = '/^[A-Za-z]+$/'.$u;
            $definicao->permite = $letras_maiusculas.$letras_minusculas;
            $definicao->instrucoes = 'Preenchar com letras n&atilde;o acentuadas.';
            $definicao->exemplo = '"abcd".';
            break;
        case 'LETRAS_NUMEROS':
            $definicao->padrao = '/^[A-Za-z0-9]+$/'.$u;
            $definicao->permite = $letras_maiusculas.$letras_minusculas.$numeros;
            $definicao->instrucoes = 'Preenchar com letras n&atilde;o acentuadas e n&uacute;meros.';
            $definicao->exemplo = '"ab23cd67".';
            break;
        case 'LOGIN':
            $definicao->padrao = '/^[A-Za-z0-9-_\.'.$acentos.']+$/'.$u;
            $definicao->permite = $letras_maiusculas.$letras_minusculas.$letras_acentuadas.$numeros.'-_.';
            $definicao->instrucoes = 'Preencha o login com letras, n&uacute;meros, menos, underscore, ponto ou acentos.';
            $definicao->exemplo = '"pacabral".';
            break;
        case 'MODULO':
            $definicao->padrao = '/^[A-Za-z0-9-_\/]+$/'.$u;
            $definicao->permite = $letras_maiusculas.$letras_minusculas.$numeros.'-_/';
            $definicao->instrucoes = 'Preencha o nome do m&oacute;dulo usando letras, n&uacute;meros, menos, underscore ou barra.';
            $definicao->exemplo = '"servidores/docentes".';
            break;
        case 'NOME':
            $definicao->padrao = '/^[A-Za-z-'.$acentos.$espaco.'\'`]+$/'.$u;
            $definicao->permite = $letras_maiusculas.$letras_minusculas.'-'.$letras_acentuadas.$str_espaco."'`";
            $definicao->instrucoes = 'Preencha com um nome completo usando letras, acentos, espa&ccedil;os, h&iacute;fen ou ap&oacute;strofo.';
            $definicao->exemplo = '"Jos&eacute; Sim&atilde;o McDonald\'s".';
            break;
        case 'NOME_ARQUIVO':
            $definicao->padrao = '/^[A-Za-z0-9-_\.\/]+$/'.$u;
            $definicao->permite = $letras_maiusculas.$letras_minusculas.$numeros.'-_./';
            $definicao->instrucoes = 'Preencha com um nome de arquivo usando letras, n&uacute;meros, h&iacute;fen, underscore, ponto ou barra.';
            $definicao->exemplo = '"/tmp/arquivo.txt".';
            break;
        case 'NOME_INCOMPLETO':
            $definicao->padrao = '/^[A-Za-z-'.$acentos.$espaco.'\'\.]+$/'.$u;
            $definicao->permite = $letras_maiusculas.$letras_minusculas.'-'.$letras_acentuadas.$str_espaco."'.";
            $definicao->instrucoes = 'Preencha com um nome completo ou incompleto. Use letras, acentos, espa&ccedil;o, ap&oacute;strofo ou ponto para abreviar.';
            $definicao->exemplo = '"Jos&eacute; S. McDonald\'s".';
            break;
        case 'NUMERICO':
            $definicao->padrao = '/^[0-9]+$/'.$u;
            $definicao->permite = $numeros;
            $definicao->instrucoes = 'Preencha com n&uacute;meros sem ponto.';
            $definicao->exemplo = '"12345".';
            break;
        case 'NUMERICO_PONTO':
            $definicao->padrao = '/^[0-9\.]+$/'.$u;
            $definicao->permite = $numeros.'.';
            $definicao->instrucoes = 'Preencha com n&uacute;meros, podendo usar ponto.';
            $definicao->exemplo = '"12.83.629".';
            break;
        case 'PLACA_VEICULO':
            $definicao->padrao = '/^[A-Za-z]{3}\-[0-9]{4}$/'.$u;
            $definicao->permite = $letras_maiusculas.$numeros.'-';
            $definicao->instrucoes = 'Preencha a placa do ve&iacute;culo com tr&ecirc;s letras mai&uacute;sculas seguidas por um h&iacute;fen e terminada com quatro d&iacute;gitos num&eacute;ricos.';
            $definicao->exemplo = '"ABC-9876".';
            break;
        case 'RG':
            $definicao->padrao = '/^[A-Za-z0-9-\.'.$espaco.']+$/'.$u;
            $definicao->permite = $letras_maiusculas.$letras_minusculas.$numeros.$str_espaco.'-.';
            $definicao->instrucoes = 'Preencha com um RG usando letras, n&uacute;meros, menos, ponto e espa&ccedil;o.';
            $definicao->exemplo = '"92.073.455-1".';
            break;
        case 'SENHA':
            $definicao->padrao = '/^[A-Za-z0-9'.$simbolos.$acentos.$espaco.']+$/'.$u;
            $definicao->permite = $letras_maiusculas.$letras_minusculas.$numeros.$str_simbolos.$letras_acentuadas.$numeros.$str_espaco;
            $definicao->instrucoes = 'Preencha a senha usando letras, n&uacute;meros, s&iacute;mbolos, acentos ou espa&ccedil;o.';
            $definicao->exemplo = '"'.chr(mt_rand(97, 122)).'a&ccedil;%'.chr(mt_rand(65, 90)).'Rn#x'.mt_rand(0, 99).'".';
            break;
        case 'SITE':
            $definicao->padrao = '/^http[s]?:\/\/[A-Za-z0-9-_\.\?&~%=\/]+$/'.$u;
            $definicao->permite = $letras_maiusculas.$letras_minusculas.':/-_.?&~%=';
            $definicao->instrucoes = 'Preencha o endere&ccedil;o do site usando o prefixo "http://" ou "https://".';
            $definicao->exemplo = '"http://www.exemplo.com".';
            break;
        case 'TEXTO':
            $definicao->padrao = '/^[A-Za-z0-9'.$simbolos.$acentos.$quebra_linha.$espaco.']+$/'.$u;
            $definicao->permite = $letras_maiusculas.$letras_minusculas.$letras_acentuadas.$numeros.$str_simbolos.$str_quebra_linha.$str_espaco;
            $definicao->instrucoes = 'Preencha o texto usando letras, n&uacute;meros, acentos, s&iacute;mbolos, espa&ccedil;o e quebras de linha.';
            $definicao->exemplo = '"A casa &eacute; bonita, mas &eacute; antiga.".';
            break;
        case 'TEXTO_LINHA':
            $definicao->padrao = '/^[A-Za-z0-9'.$simbolos.$acentos.$espaco.']+$/'.$u;
            $definicao->permite = $letras_maiusculas.$letras_minusculas.$letras_acentuadas.$numeros.$str_simbolos.$str_espaco;
            $definicao->instrucoes = 'Preencha o texto de uma linha usando letras, n&uacute;meros, acentos, s&iacute;mbolos, espa&ccedil;o sem usar quebras de linha.';
            $definicao->exemplo = '"A casa &eacute; bonita, mas &eacute; antiga.".';
            break;
        default:
            return false;
        }
        return $definicao;
    }


    //
    //     Valida um campo (geralmente baseado em expressao regular)
    //     O tipo de campo pode assumir um dos valores:
    //     * ARQUIVO: o arquivo deve existir no servidor
    //     * CNPJ: o numero de CNPJ deve ser valido
    //     * CPF: o numero de CPF deve ser valido
    //     * EMAIL: o e-mail deve ser valido, e o dominio tambem, caso a constante VALIDACAO_CHECAR_DOMINIO_EMAIL seja true
    //     * IP: o IP deve ser valido (4 numeros de 0 a 255 separados por ponto)
    //     * BD: o nome do BD deve ser valido
    //     * CEP: o CEP deve estar no formato XXXXX-XXX
    //     * DN: o DN usado em bases LDAP deve ser valido
    //     * DOMINIO: o dominio deve ser valido (completo ou nao, ou seja, pode comecar em ponto)
    //     * FONE: o telefone deve estar no formato (XX) XXXX-XXXX ou +XX (XX) XXXX-XXXX
    //     * HOST: o host deve ser valido e completo (nao inicia em ponto)
    //     * LETRAS: o campo deve possuir apenas letras (independe da caixa)
    //     * LETRAS_NUMEROS: o campo deve possuir apenas letras e numeros (independe da caixa)
    //     * LOGIN: o login deve ser valido (letras, numeros, -, _ e acentos)
    //     * MODULO: o nome do modulo deve ser valido
    //     * NOME: o nome completo da pessoa deve ser valido (caracteres latinos)
    //     * NOME_ARQUIVO: o nome de arquivo deve ser valido (letras, numeros, -, _, . e /)
    //     * NOME_INCOMPLETO: o nome incompleto da pessoa deve ser valido (aceita ponto final para abreviacoes)
    //     * NUMERICO: so aceita numeros
    //     * NUMERICO_PONTO: aceita numeros e ponto
    //     * PLACA_VEICULO: aceita placas no formato: tres letras maiusculas, um hifen e quatro numeros
    //     * RG: so aceita letras, numeros e -
    //     * SENHA: so letras, numeros, acentos, simbolos e espaco
    //     * SITE: o site deve ser valido (iniciando em http:// ou https://)
    //     * TEXTO: o texto deve ser valido (permite-se quebra de linha)
    //     * TEXTO_LINHA: o texto deve ser valido (nao permite-se quebra de linha)
    //
    public function validar_campo($tipo, $valor, &$erro_campo = '') {
    // String $tipo: tipo de campo
    // String $valor: valor do campo
    // String $erro_campo: string que guarda o erro ocorrido caso consiga identificar
    //
        $erro_campo = false;

        // Tipos especiais (especificos)
        switch ($tipo) {
        case 'ARQUIVO':
            if (!file_exists($valor)) {
                $erro_campo = 'Arquivo "'.$valor.'" n&atilde;o existe';
                return false;
            }
            return true;
        case 'CNPJ':
            return $this->validar_cnpj($valor, $erro_campo);
        case 'CPF':
            return $this->validar_cpf($valor, $erro_campo);
        case 'EMAIL':
            return $this->validar_email($valor, $erro_campo);
        case 'IP':
            return $this->validar_ip($valor, $erro_campo);
        }

        // Validar de acordo com expressao regular
        $definicao = self::get_definicao_tipo($tipo);
        if (!$definicao) {
            $erro_campo = 'Valida&ccedil;&atilde;o desconhecida';
            return false;
        }

        // Se validou
        $validou = preg_match($definicao->padrao, $valor);
        if ($validou) {
            return true;

        // Se ocorreu um erro de expressao regular
        } elseif ($validou === false) {
            switch (preg_last_error()) {
            case PREG_INTERNAL_ERROR:
                trigger_error('Erro na expressao "'.$definicao->padrao.'" (PREG_INTERNAL_ERROR)', E_USER_NOTICE);
                break;
            case PREG_BACKTRACK_LIMIT_ERROR:
                trigger_error('Erro na expressao "'.$definicao->padrao.'" (PREG_BACKTRACK_LIMIT_ERROR)', E_USER_NOTICE);
                break;
            case PREG_RECURSION_LIMIT_ERROR:
                trigger_error('Erro na expressao "'.$definicao->padrao.'" (PREG_RECURSION_LIMIT_ERROR)', E_USER_NOTICE);
                break;
            case PREG_BAD_UTF8_ERROR:
                trigger_error('Erro na expressao "'.$definicao->padrao.'" (PREG_BAD_UTF8_ERROR)', E_USER_NOTICE);
                break;
            case PREG_BAD_UTF8_OFFSET_ERROR:
                trigger_error('Erro na expressao "'.$definicao->padrao.'" (PREG_BAD_UTF8_OFFSET_ERROR)', E_USER_NOTICE);
                break;
            }
        }

        // Checando os caracteres que nao podiam ser usados
        if (isset($definicao->permite)) {
            $caracteres_controle = listas::get_caracteres_controle();
            $len = texto::strlen($valor);
            $caracteres_invalidos = array();
            $posicoes = array();
            $sugestao = '';
            for ($i = 0; $i < $len; $i++) {
                $char = texto::get_char($valor, $i);

                // Se o caractere nao e' permitido
                if (strpos($definicao->permite, $char) === false) {
                    if (strlen($char) == 1 && isset($caracteres_controle[ord($char)])) {
                        $caracteres_invalidos[] = 'caractere de controle "'.$caracteres_controle[ord($char)].'"';
                    } else {
                        $caracteres_invalidos[] = htmlspecialchars("\"{$char}\"");
                    }
                    $posicoes[] = $i + 1;

                // Se o caractere e' permitido
                } else {
                    $sugestao .= $char;
                }
            }

            if (!empty($caracteres_invalidos)) {
                $erro_campo = "os seguintes caracteres n&atilde;o s&atilde;o permitidos para este tipo de campo: ".
                              implode(', ', array_unique($caracteres_invalidos)).'. Est&atilde;o localizados nas seguintes '.
                              'posi&ccedil;&otilde;es: '.implode(', ', $posicoes).'.';
                $sugestao = trim($sugestao);
                if ($this->validar_campo($tipo, $sugestao)) {
                    $erro_campo .= ' Sugest&atilde;o de preenchimento: "'.texto::codificar($sugestao).'".';
                }
            }
        }
        return false;
    }


    //
    //     Retorna se o CNPJ e' valido
    //
    public function validar_cnpj($cnpj, &$erro_campo = '') {
    // String $cnpj: CNPJ no formato xx.xxx.xxx/xxxx-xx ou apenas numeros
    // String $erro_campo: erro ocorrido na validacao, caso consiga identificar
    //
        // Se informou o CNPJ no formato completo (com pontos e tracos): converter para numeros
        if (strlen($cnpj) != 14) {
            $erro_campo = 'CNPJ n&atilde;o possui 14 d&iacute;gitos';
            return false;
        }

        // Somatorio das multiplicacoes dos 12 primeiros digitos por 6,7,8,9,2,3,4,5,6,7,8,9
        $soma = ($cnpj[0] * 6) + ($cnpj[1] * 7) + ($cnpj[2] * 8) + ($cnpj[3] * 9) +
                ($cnpj[4] * 2) + ($cnpj[5] * 3) + ($cnpj[6] * 4) + ($cnpj[7] * 5) +
                ($cnpj[8] * 6) + ($cnpj[9] * 7) + ($cnpj[10] * 8) + ($cnpj[11] * 9);

        $dv = $soma % 11;
        if ($dv == 10) { $dv = 0; }
        if ($dv != $cnpj[12]) {
            $erro_campo = 'D&iacute;gito verificador n&atilde;o confere';
            return false;
        }

        // Somatorio das multiplicacoes dos 13 primeiros digitos por 5,6,7,8,9,2,3,4,5,6,7,8,9
        $soma = ($cnpj[0] * 5) + ($cnpj[1] * 6) + ($cnpj[2] * 7) + ($cnpj[3] * 8) + ($cnpj[4] * 9) +
                ($cnpj[5] * 2) + ($cnpj[6] * 3) + ($cnpj[7] * 4) + ($cnpj[8] * 5) +
                ($cnpj[9] * 6) + ($cnpj[10] * 7) + ($cnpj[11] * 8) + ($cnpj[12] * 9);

        $dv = $soma % 11;
        if ($dv == 10) { $dv = 0; }
        if ($dv != $cnpj[13]) {
            $erro_campo = 'D&iacute;gito verificador n&atilde;o confere';
            return false;
        }

        return true;
    }


    //
    //    Retorna se o CPF e' valido
    //
    public function validar_cpf($cpf, &$erro_campo) {
    // String $cpf: CPF apenas numeros
    // String $erro_campo: string que guarda o erro ocorrido caso consiga identificar
    //
        if (strlen($cpf) != 11) {
            $erro_campo = 'CPF precisa ter 11 d&iacute;gitos.';
            return false;
        }
        if ($cpf == '00000000000' || $cpf == '99999999999') {
            $erro_campo = 'CPF inv&aacute;lido.';
            return false;
        }

        $cpf_aux = substr($cpf, 0, 9);
        $dv_aux  = substr($cpf, 9, 2);

        // Calcular digito verificador 1 e 2
        $multiplicador1 = 10;
        $multiplicador2 = 11;
        $soma1 = 0;
        $soma2 = 0;
        for ($i = 0; $i < 9; $i++, $multiplicador1--, $multiplicador2--) {
            $soma1 += $cpf_aux[$i] * $multiplicador1;
            $soma2 += $cpf_aux[$i] * $multiplicador2;
        }
        $soma1 %= 11;
        $dv1 = $soma1 < 2 ? 0 : 11 - $soma1;

        $soma2 += ($dv1 * 2);
        $soma2 %= 11;
        $dv2 = $soma2  < 2 ? 0 : 11 - $soma2;

        // Montar digito verificador
        $dv = sprintf('%d%d', $dv1, $dv2);

        // Testar digito verificador
        if ($dv != $dv_aux) {
            $erro_campo = 'D&iacute;gito verificador incorreto.';
            return false;
        }
        return true;
    }


    //
    //     Valida um IP
    //
    public function validar_ip($ip, &$erro_campo = '') {
    // String $ip: IP a ser validado
    // String $erro_campo: string que guarda o erro ocorrido caso consiga identificar
    //
        // IPv6 local
        if ($ip == '::1') {
            return true;
        }

        // IPv4
        $vetor = explode('.', $ip);
        if (count($vetor) != 4) {
            $erro_campo = 'O IP precisa estar no formato X.X.X.X';
            return false;
        }
        foreach ($vetor as $i) {
            if (!is_numeric($i) || $i < 0 || $i > 255) {
                $erro_campo = "O valor \"{$i}\" deveria estar entre 0 e 255.";
                return false;
            }
        }
        return true;
    }


    //
    //     Valida um e-mail tanto por expressao regular quanto pela validade do dominio
    //
    public function validar_email($email, &$erro_campo) {
    // String $email: e-mail a ser validado
    // String $erro_campo: string que guarda o erro ocorrido caso consiga identificar
    //
        if (isset($_SERVER['SERVER_ADMIN'])) {
            if ($email == $_SERVER['SERVER_ADMIN']) {
                return true;
            }
        } elseif ($email == 'root@localhost') {
            return true;
        }

        $definicao = self::get_definicao_tipo('EMAIL');
        if (!preg_match($definicao->padrao, $email, $match)) {
            $email = texto::codificar($email);
            $erro_campo = "O e-mail n&atilde;o est&aacute; no padr&atilde;o ou possui caracteres inv&aacute;lidos ({$email}).";
            return false;
        }
        if (VALIDACAO_CHECAR_DOMINIO_EMAIL && function_exists('checkdnsrr')) {
            $dominio = substr($email, strpos($email, '@') + 1);
            if (!checkdnsrr($dominio, VALIDACAO_TIPO_CHECAGEM_DOMINIO)) {
                $dominios_semelhantes = usuario::get_dominios_semelhantes($dominio);
                $sugestoes = array();
                $i = 1;
                foreach ($dominios_semelhantes as $dominio_semelhante => $porcentagem) {
                    if (strcmp($dominio_semelhante, $dominio) != 0) {
                        $sugestoes[] = '"'.$dominio_semelhante.'"';
                        if ($i == 5 || $porcentagem > 90) {
                            break;
                        }
                        $i++;
                    }
                }
                $erro_campo = 'O dom&iacute;nio "'.texto::codificar($dominio).'" parece n&atilde;o ser v&aacute;lido. Veja a lista de dom&iacute;nios semelhantes: '.implode(', ', $sugestoes).'.';
                return false;
            }
        }
        return true;
    }


    //
    //     Retorna uma string com letras com acentos para ser usada no preg_match
    //
    public static function acentos($forcar_chr = false) {
    // Bool $forcar_chr: forca o retorno da funcao na forma de chr
    //
        $letras = array(
            192, 193, 194, 195, 196, 199, 200, 201, 202, 203, 204, 205, 209, 210,
            211, 212, 213, 218, 220, 224, 225, 226, 227, 228, 231, 233, 234, 237,
            241, 243, 244, 245, 250, 252
        );
        $str = '';
        if (VALIDACAO_UTF8) {
            foreach ($letras as $l) {
                $str .= $forcar_chr ? texto::chr_utf8($l) : '\x{'.dechex($l).'}';
            }
        } else {

            // Ignorar caracteres nao ASCII
            foreach ($letras as $l) {
                if ($l <= 0xFF) {
                    $str .= chr($l);
                }
            }
        }
        return $str;
    }


    //
    //     Retorna uma string com simbolos para ser usados no preg_match
    //
    public static function simbolos($forcar_chr = false) {
    // Bool $forcar_chr: forca o retorno da funcao na forma de chr
    //
        $simbolos = array(
            /* Caracteres ASCII */
            33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 58,
            59, 60, 61, 62, 63, 64, 91, 92, 93, 95, 96, 124, 126,

            /* Caracteres UTF-8 */
            161, 167, 168, 169, 170, 174, 176, 178, 179, 180, 185, 186, 191,
            194, 200, 203, 204, 206, 207, 210, 214, 215, 216, 217, 219, 221,
            229, 232, 235, 236, 238, 239, 246, 247, 248, 249,
            8211, 8216, 8217, 8218, 8219, 8220, 8221, 8222, 8223, 8242, 8243,
            8244, 8245, 8246, 8247, 8364, 8373, 8482
        );

        $str = '';
        if (VALIDACAO_UTF8) {
            foreach ($simbolos as $s) {
                $str .= $forcar_chr ? texto::chr_utf8($s) : '\x{'.dechex($s).'}';
            }
        } else {

            // Ignorar caracteres nao ASCII
            foreach ($simbolos as $s) {
                if ($s <= 0xFF) {
                    $str .= chr($s);
                }
            }
        }
        return $str;
    }


    //
    //     Retorna uma quebra de linha para ser usada no preg_match
    //
    public static function quebra_linha($forcar_chr = false) {
    // Bool $forcar_chr: forca o retorno da funcao na forma de chr
    //
        if ($forcar_chr || !VALIDACAO_UTF8) {
            return chr(10).chr(13);
        }
        return '\x{a}\x{d}';
    }


    //
    //     Retorna um espaco para ser usado no preg_match
    //
    public static function espaco($forcar_chr = false) {
    // Bool $forcar_chr: forca o retorno da funcao na forma de chr
    //
        if ($forcar_chr || !VALIDACAO_UTF8) {
            return ' ';
        }
        return '\040';
    }


    //
    //     Valida uma hora
    //
    private static function validar_hora($atributo, $data, &$erros) {
    // atributo $atributo: dados do atributo
    // Array[String => Int] $data: componentes da hora
    // Array[String] $erros: vetor de erros
    //
        $valido = true;
        if ($data['hora'] < 0 || $data['hora'] > 23) {
            $valido = false;
            $erros[] = 'Campo "'.$atributo->descricao.'" n&atilde;o possui a hora v&aacute;lida: '.$data['hora'];
        }
        if ($data['minuto'] < 0 || $data['minuto'] > 59) {
            $valido = false;
            $erros[] = 'Campo "'.$atributo->descricao.'" n&atilde;o possui o minuto v&aacute;lido: '.$data['hora'];
        }
        if ($data['segundo'] < 0 || $data['segundo'] > 59) {
            $valido = false;
            $erros[] = 'Campo "'.$atributo->descricao.'" n&atilde;o possui o segundo v&aacute;lido: '.$data['hora'];
        }
        return $valido;
    }


    //
    //     Valida uma data
    //
    private static function validar_data($atributo, $data, &$erros) {
    // atributo $atributo: dados do atributo
    // Array[String => Int] $data: componentes da data
    // Array[String] $erros: vetor de erros
    //
        global $CFG;
        $valido = true;
        if (!checkdate($data['mes'], $data['dia'], $data['ano'])) {
            $valido = false;
            $data_str = sprintf('%02d/%02d/%04d', $data['dia'], $data['mes'], $data['ano']);
            $erros[] = 'Campo "'.$atributo->descricao.'" n&atilde;o &eacute; uma data v&aacute;lida ('.$data_str.')';
        }
        return $valido;
    }


    //
    //     Obtem as convencoes da localidade
    //
    static public function get_convencoes_localidade($locale = null) {
    // String $locale: localidade
    //
        global $CFG;
        static $conv = array();
        
        if (!$locale) {
            $locale = $CFG->localidade;
        }
        
        if (!isset($conv[$locale])) {
            $locale_antigo = setlocale(LC_ALL, '0');

            setlocale(LC_ALL, $locale);
            $conv[$locale] = localeconv();

            if (strpos($locale_antigo, ';') !== false) {
                $locales_antigos = explode(';', $locale_antigo);
                foreach ($locales_antigos as $chave_valor) {
                    list($chave, $valor) = explode('=', $chave_valor);
                    if (defined($chave)) {
                        setlocale(constant($chave), $valor);
                    }
                }
            } else {
                setlocale(LC_ALL, $locale_antigo);
            }
        }
        return $conv[$locale];
    }

}//class
