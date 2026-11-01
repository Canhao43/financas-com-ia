<?php

namespace App\Assistente;

/**
 * O que vai sendo juntado enquanto o Claude consulta: as consultas, os valores que elas
 * devolveram e quanto as chamadas custaram.
 */
final class RespostaEmAndamento
{
    /** @var list<array{ferramenta: string, descricao: string, erro: bool}> */
    public array $consultas = [];

    /** @var list<string> formas normalizadas, como a ConferenciaDeValores compara */
    public array $valores = [];

    /** @var list<string> */
    public array $semFonte = [];

    public Consumo $consumo;

    public function __construct()
    {
        $this->consumo = new Consumo;
    }
}
