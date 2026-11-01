<?php

namespace App\Importacao;

use App\Enums\OrigemLancamento;
use App\Enums\SituacaoDaLinha;
use App\Enums\StatusImportacao;
use App\Models\Conta;
use App\Models\Importacao;
use App\Models\Lancamento;
use App\Ofx\Extrato;
use App\Regras\Categorizador;
use Illuminate\Support\Facades\DB;

/**
 * Grava: a prévia de um arquivo, a confirmação dela e o desfazer.
 */
final class Importador
{
    public function __construct(
        private readonly Conciliador $conciliador,
        private readonly Categorizador $categorizador,
    ) {}

    /**
     * Guarda as linhas lidas, não o arquivo. Uma prévia por vez: a anterior, não confirmada, sai.
     */
    public function preparar(Conta $conta, string $nomeDoArquivo, Extrato $extrato): Importacao
    {
        return DB::transaction(function () use ($conta, $nomeDoArquivo, $extrato) {
            Importacao::query()->where('status', StatusImportacao::Pendente)->delete();

            return Importacao::query()->create([
                'conta_id' => $conta->id,
                'status' => StatusImportacao::Pendente,
                'arquivo' => mb_substr($nomeDoArquivo, 0, 255),
                'final_da_conta' => $extrato->finalDaConta,
                'inicio' => $extrato->inicio,
                'fim' => $extrato->fim,
                'saldo_banco' => $extrato->saldo,
                'saldo_em' => $extrato->saldoEm,
                'linhas' => array_map(fn (Linha $linha) => $linha->paraArray(), Linha::doExtrato($extrato)),
            ]);
        });
    }

    /**
     * Decisão por linha, vinda da prévia: "ignorar" deixa a linha de fora; numa linha parecida com
     * um lançamento manual, "importar" cria outro em vez de vincular. Sem decisão, a linha nova é
     * importada e a parecida é vinculada — o padrão é o gasto entrar uma vez, nem zero nem duas.
     *
     * Devolve false se a importação já tinha sido confirmada.
     *
     * @param  array<int, string>  $decisoes  posição da linha => importar | ignorar | vincular
     */
    public function confirmar(Importacao $importacao, array $decisoes): bool
    {
        return DB::transaction(function () use ($importacao, $decisoes) {
            // A trava: só uma confirmação muda o status. Clique duplo ou outra aba não acha mais a
            // prévia pendente e não importa de novo.
            $travou = Importacao::query()
                ->whereKey($importacao->id)
                ->where('status', StatusImportacao::Pendente)
                ->update(['status' => StatusImportacao::Concluida]);

            if ($travou === 0) {
                return false;
            }

            $contagem = ['importados' => 0, 'vinculados' => 0, 'ja_existiam' => 0, 'ignorados' => 0];

            // Classifica de novo em vez de confiar na prévia: a conta pode ter mudado desde que ela foi gerada.
            foreach ($this->conciliador->classificar($importacao->conta, $importacao->linhasDoExtrato()) as $item) {
                $decisao = $decisoes[$item->indice] ?? null;

                if ($item->situacao === SituacaoDaLinha::JaExiste) {
                    $contagem['ja_existiam']++;
                } elseif ($decisao === 'ignorar') {
                    $contagem['ignorados']++;
                } elseif ($item->parecidoCom !== null && $decisao !== 'importar') {
                    $this->vincular($importacao, $item->parecidoCom, $item->linha);
                    $contagem['vinculados']++;
                } else {
                    $this->criar($importacao, $item->linha);
                    $contagem['importados']++;
                }
            }

            // As linhas eram só para a prévia: o extrato não fica guardado em dobro.
            $importacao->forceFill([...$contagem, 'status' => StatusImportacao::Concluida, 'linhas' => null])->save();

            return true;
        });
    }

    /**
     * Pendente: descarta a prévia. Concluída: apaga o que ela criou e solta os manuais que ela
     * vinculou — eles voltam a ser só manuais, com a descrição e a categoria que tinham.
     */
    public function desfazer(Importacao $importacao): void
    {
        DB::transaction(function () use ($importacao) {
            $importacao->lancamentos()->where('origem', OrigemLancamento::Importado)->delete();
            $importacao->lancamentos()->update(['importacao_id' => null, 'id_externo' => null, 'impressao' => null]);
            $importacao->delete();
        });
    }

    private function criar(Importacao $importacao, Linha $linha): void
    {
        (new Lancamento([
            'conta_id' => $importacao->conta_id,
            // O vinculado fica com a categoria que a pessoa escolheu; só o criado aqui passa pelas regras.
            'categoria_id' => $this->categorizador->categoriaPara($linha->descricao, $linha->valor)?->id,
            'data' => $linha->data->toDateString(),
            'descricao' => $linha->descricao,
            'valor' => $linha->valor,
        ]))->forceFill($this->vinculo($importacao, $linha) + ['origem' => OrigemLancamento::Importado])->save();
    }

    /**
     * O manual ganha a identidade da linha do banco, então a próxima importação o reconhece
     * como já existente. Descrição, data, valor e categoria continuam os que a pessoa escolheu.
     */
    private function vincular(Importacao $importacao, Lancamento $manual, Linha $linha): void
    {
        $manual->forceFill($this->vinculo($importacao, $linha))->save();
    }

    /**
     * @return array{importacao_id: int, id_externo: string|null, impressao: string}
     */
    private function vinculo(Importacao $importacao, Linha $linha): array
    {
        return ['importacao_id' => $importacao->id, 'id_externo' => $linha->fitid, 'impressao' => $linha->impressao];
    }
}
