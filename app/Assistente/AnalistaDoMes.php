<?php

namespace App\Assistente;

use Anthropic\Beta\Messages\BetaMessage;
use Anthropic\Beta\Messages\BetaTextBlock;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use App\Assistente\Ferramentas\ResumoDoMes;
use App\Models\Analise;
use App\Models\Lancamento;
use App\Support\Mes;
use Carbon\CarbonImmutable;

/**
 * Escreve a análise do mês com o Claude a partir do mesmo resumo que o assistente consulta, e
 * confere cada valor do texto contra esses números antes de gravar.
 *
 * Uma chamada, sem ferramentas: os números do mês cabem inteiros no pedido, e não há nada que o
 * Claude precise ir buscar. Vão só totais por categoria; nenhum lançamento, descrição ou conta
 * sai do app.
 */
final class AnalistaDoMes
{
    public function __construct(
        private Client $cliente,
        private ResumoDoMes $resumo,
    ) {}

    public function configurado(): bool
    {
        return filled(config('assistente.chave'));
    }

    /**
     * Os números do mês como vão para o Claude: o resumo do assistente e se o mês já acabou.
     *
     * @return array<string, mixed>
     */
    public function fatos(CarbonImmutable $mes): array
    {
        $mes = $mes->startOfMonth();

        return [
            // Mês pela metade não pode ser comparado com um mês inteiro como se fossem iguais.
            'mes_em_andamento' => CarbonImmutable::today()->lessThanOrEqualTo($mes->endOfMonth()),
            ...$this->resumo->executar(['mes' => $mes->format('Y-m')]),
        ];
    }

    /**
     * @param  array<string, mixed>  $fatos
     */
    public function impressao(array $fatos): string
    {
        return hash('sha256', (string) json_encode($fatos));
    }

    /**
     * Os números do mês mudaram depois que a análise foi escrita (lançamento novo, importação,
     * categoria trocada, ou o mês acabou): o texto pode estar falando de outros valores.
     */
    public function desatualizada(Analise $analise): bool
    {
        return $analise->impressao !== $this->impressao($this->fatos(Mes::doTexto($analise->mes)));
    }

    /**
     * Escreve e grava a análise, substituindo a anterior do mês. Se o texto citar valor que não
     * está nos dados, o Claude recebe a lista exata e reescreve; o que sobrar depois das
     * correções fica gravado como sem fonte, para a tela avisar.
     *
     * @throws AnaliseNaoEscrita
     */
    public function escrever(CarbonImmutable $mes): Analise
    {
        $mes = $mes->startOfMonth();

        if (! Lancamento::query()->doMes($mes)->exists()) {
            throw new AnaliseNaoEscrita('Não há lançamentos em '.Formato::mes($mes).' para analisar.');
        }

        $fatos = $this->fatos($mes);
        $permitidos = array_keys(ConferenciaDeValores::valores((string) json_encode($fatos, JSON_UNESCAPED_UNICODE)));
        $mensagens = [InstrucoesDaAnalise::pedido($mes, $fatos, CarbonImmutable::today())];
        $consumo = new Consumo;
        $correcoes = 0;

        try {
            while (true) {
                $mensagem = $this->chamar($mensagens);
                $consumo->contar($mensagem);
                $texto = $this->ler($mensagem);
                $semFonte = ConferenciaDeValores::semFonte(implode("\n", [$texto['resumo'], ...$texto['destaques']]), $permitidos);

                if ($semFonte === [] || $correcoes >= config()->integer('assistente.correcoes_da_analise')) {
                    break;
                }

                // A resposta volta inteira, com o raciocínio, e o pedido de correção vem depois
                // dela: o histórico só cresce, como a API exige do raciocínio assinado.
                $correcoes++;
                $mensagens[] = ['role' => 'assistant', 'content' => $mensagem->content];
                $mensagens[] = InstrucoesDaAnalise::correcao($semFonte);
            }
        } catch (APIException $erro) {
            throw new AnaliseNaoEscrita(ErroDaApi::mensagem($erro), previous: $erro);
        }

        return Analise::query()->updateOrCreate(['mes' => $mes->format('Y-m')], [
            'resumo' => $texto['resumo'],
            'destaques' => $texto['destaques'],
            'fatos' => $fatos,
            'impressao' => $this->impressao($fatos),
            'valores_sem_fonte' => $semFonte,
            'correcoes' => $correcoes,
            'modelo' => $consumo->modelo,
            'uso' => $consumo->uso,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $mensagens
     */
    private function chamar(array $mensagens): BetaMessage
    {
        return $this->cliente->beta->messages->create(...$this->parametros($mensagens));
    }

    /**
     * @param  list<array<string, mixed>>  $mensagens
     * @return array<string, mixed>
     */
    private function parametros(array $mensagens): array
    {
        return [
            'maxTokens' => config()->integer('assistente.max_tokens'),
            'messages' => $mensagens,
            'model' => config()->string('assistente.modelo'),
            // Sem cache, de propósito: é uma chamada por mês, e gravar o cache custa 25% a mais
            // do que ler sem ele. Só a correção releria o mesmo começo, e ela deve ser rara.
            'fallbacks' => 'default',
            'outputConfig' => [
                'effort' => config()->string('assistente.esforco'),
                'format' => InstrucoesDaAnalise::formato(),
            ],
            'system' => InstrucoesDaAnalise::SISTEMA,
            'thinking' => ['type' => 'adaptive'],
            'betas' => ['server-side-fallback-2026-07-01'],
        ];
    }

    /**
     * @return array{resumo: string, destaques: list<string>}
     *
     * @throws AnaliseNaoEscrita
     */
    private function ler(BetaMessage $mensagem): array
    {
        // A saída estruturada só garante o formato da resposta que terminou normalmente: a
        // recusa e a resposta cortada chegam fora dele.
        if ($mensagem->stopReason === 'refusal') {
            throw new AnaliseNaoEscrita('O Claude não quis escrever a análise deste mês.');
        }

        if ($mensagem->stopReason === 'max_tokens') {
            throw new AnaliseNaoEscrita('A análise passou do tamanho máximo antes de terminar. Tente de novo.');
        }

        $json = null;
        foreach ($mensagem->content as $bloco) {
            if ($bloco instanceof BetaTextBlock) {
                $json = json_decode($bloco->text, true);
                break;
            }
        }

        $resumo = is_array($json) && is_string($json['resumo'] ?? null) ? trim($json['resumo']) : '';
        $destaques = [];
        foreach (is_array($json) && is_array($json['destaques'] ?? null) ? $json['destaques'] : [] as $destaque) {
            if (is_string($destaque) && trim($destaque) !== '') {
                $destaques[] = trim($destaque);
            }
        }

        if ($resumo === '') {
            throw new AnaliseNaoEscrita('O Claude devolveu a análise vazia. Tente de novo.');
        }

        return ['resumo' => $resumo, 'destaques' => $destaques];
    }
}
