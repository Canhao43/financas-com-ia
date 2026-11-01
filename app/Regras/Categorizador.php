<?php

namespace App\Regras;

use App\Enums\TipoCategoria;
use App\Models\Categoria;
use App\Models\Regra;
use App\Support\Texto;

/**
 * Escolhe a categoria de um lançamento pelas regras. Não grava nada: quem chama decide.
 */
final class Categorizador
{
    /** @var list<Regra>|null */
    private ?array $regras = null;

    /**
     * A categoria da regra cujo texto aparece na descrição. Só vale regra da direção do valor:
     * "UBER → Transporte" é despesa e não categoriza o estorno da corrida, que é entrada.
     */
    public function categoriaPara(string $descricao, int $valor): ?Categoria
    {
        $descricao = Texto::normalizar($descricao);
        $tipo = TipoCategoria::daDirecao($valor > 0 ? 'entrada' : 'saida');

        foreach ($this->regras() as $regra) {
            if ($regra->categoria->tipo === $tipo && str_contains($descricao, $regra->texto)) {
                return $regra->categoria;
            }
        }

        return null;
    }

    /**
     * Carregadas uma vez por uso. A mais longa primeiro: "UBER EATS" é mais específica que "UBER"
     * e precisa vencer sem a pessoa ter que ordenar regra à mão. No empate, a mais antiga.
     *
     * @return list<Regra>
     */
    private function regras(): array
    {
        return $this->regras ??= array_values(Regra::query()->with('categoria')->get()
            ->sortBy([
                fn (Regra $a, Regra $b) => strlen($b->texto) <=> strlen($a->texto),
                fn (Regra $a, Regra $b) => $a->id <=> $b->id,
            ])
            ->all());
    }
}
