<?php

namespace App\Importacao;

use App\Enums\SituacaoDaLinha;
use App\Models\Conta;
use App\Models\Lancamento;
use Illuminate\Support\Collection;

/**
 * Compara as linhas do extrato com o que já está na conta. Só lê: quem grava é o Importador.
 */
final class Conciliador
{
    /**
     * Diferença máxima, em dias, entre a data do banco e a de um lançamento manual para sugerir
     * que são o mesmo. Compra no cartão costuma aparecer um ou dois dias depois de anotada.
     */
    public const JANELA_EM_DIAS = 3;

    /**
     * Consultas com IN em partes: o SQLite antigo recusa mais de 999 parâmetros.
     */
    private const TAMANHO_DA_PARTE = 500;

    /**
     * @param  list<Linha>  $linhas
     * @return list<Classificacao>
     */
    public function classificar(Conta $conta, array $linhas): array
    {
        $impressoes = $this->impressoesExistentes($conta, array_map(fn (Linha $linha) => $linha->impressao, $linhas));
        $valoresPorFitid = $this->valoresPorFitid($conta, array_values(array_filter(array_map(fn (Linha $linha) => $linha->fitid, $linhas))));

        $resultado = [];
        $semPar = [];

        foreach ($linhas as $indice => $linha) {
            // O FITID só vale com o mesmo valor: banco que reaproveita o número do documento
            // faria um lançamento novo sumir como "já importado".
            $mesmoFitid = $linha->fitid !== null && in_array($linha->valor, $valoresPorFitid[$linha->fitid] ?? [], true);

            if ($mesmoFitid || isset($impressoes[$linha->impressao])) {
                $resultado[$indice] = new Classificacao($indice, $linha, SituacaoDaLinha::JaExiste);
            } else {
                $semPar[$indice] = $linha;
            }
        }

        $manuais = $this->manuaisSemVinculo($conta, $semPar);

        foreach ($semPar as $indice => $linha) {
            $parecido = $this->maisProximo($manuais, $linha);

            if ($parecido === null) {
                $resultado[$indice] = new Classificacao($indice, $linha, SituacaoDaLinha::Nova);

                continue;
            }

            // Cada lançamento manual responde por uma linha só.
            $manuais->forget($parecido->id);
            $resultado[$indice] = new Classificacao($indice, $linha, SituacaoDaLinha::Parecida, $parecido);
        }

        ksort($resultado);

        return array_values($resultado);
    }

    /**
     * @param  list<string>  $impressoes
     * @return array<string, true>
     */
    private function impressoesExistentes(Conta $conta, array $impressoes): array
    {
        $existentes = [];

        foreach (array_chunk(array_unique($impressoes), self::TAMANHO_DA_PARTE) as $parte) {
            foreach ($conta->lancamentos()->whereIn('impressao', $parte)->pluck('impressao') as $impressao) {
                $existentes[(string) $impressao] = true;
            }
        }

        return $existentes;
    }

    /**
     * @param  list<string>  $fitids
     * @return array<string, list<int>>
     */
    private function valoresPorFitid(Conta $conta, array $fitids): array
    {
        $valores = [];

        foreach (array_chunk(array_unique($fitids), self::TAMANHO_DA_PARTE) as $parte) {
            foreach ($conta->lancamentos()->whereIn('id_externo', $parte)->get(['id_externo', 'valor']) as $lancamento) {
                $valores[(string) $lancamento->id_externo][] = $lancamento->valor;
            }
        }

        return $valores;
    }

    /**
     * Lançamentos digitados à mão e ainda não ligados a nenhuma linha de extrato, perto das datas do arquivo.
     *
     * @param  array<int, Linha>  $linhas
     * @return Collection<int, Lancamento>
     */
    private function manuaisSemVinculo(Conta $conta, array $linhas): Collection
    {
        if ($linhas === []) {
            return new Collection;
        }

        $datas = array_map(fn (Linha $linha) => $linha->data, $linhas);

        return $conta->lancamentos()
            ->whereNull('impressao')
            ->where('data', '>=', min($datas)->subDays(self::JANELA_EM_DIAS)->toDateString())
            ->where('data', '<', max($datas)->addDays(self::JANELA_EM_DIAS + 1)->toDateString())
            ->with('categoria:id,nome')
            ->orderBy('id')
            ->get()
            ->keyBy('id')
            ->collect();
    }

    /**
     * O de mesmo valor com a data mais perto, dentro da janela. No empate, o mais antigo.
     *
     * @param  Collection<int, Lancamento>  $manuais
     */
    private function maisProximo(Collection $manuais, Linha $linha): ?Lancamento
    {
        return $manuais
            ->filter(fn (Lancamento $manual) => $manual->valor === $linha->valor
                && abs($manual->data->diffInDays($linha->data)) <= self::JANELA_EM_DIAS)
            ->sortBy([
                fn (Lancamento $a, Lancamento $b) => abs($a->data->diffInDays($linha->data)) <=> abs($b->data->diffInDays($linha->data)),
                fn (Lancamento $a, Lancamento $b) => $a->id <=> $b->id,
            ])
            ->first();
    }
}
