<?php

namespace App\Assistente;

use Anthropic\Beta\Messages\BetaMessage;
use Anthropic\Beta\Messages\BetaTextBlock;
use Anthropic\Beta\Messages\BetaToolUseBlock;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use App\Enums\SituacaoDaResposta;
use App\Models\Conversa;
use App\Models\Mensagem;
use Carbon\CarbonImmutable;

/**
 * Responde uma pergunta com o Claude: ele pede consultas, o app executa e devolve os números
 * prontos, e no fim cada valor da resposta é conferido contra o que as consultas devolveram.
 *
 * O loop é escrito à mão, e não com o tool runner do SDK, porque o app precisa registrar cada
 * consulta e cada valor devolvido para a conferência, e nos testes trocar a API por respostas
 * gravadas.
 */
final class Assistente
{
    public function __construct(
        private Client $cliente,
        private CaixaDeFerramentas $ferramentas,
    ) {}

    public function configurado(): bool
    {
        return filled(config('assistente.chave'));
    }

    public function perguntar(Conversa $conversa, string $pergunta): Mensagem
    {
        $hoje = CarbonImmutable::today();
        [$mensagens, $permitidos] = $this->historico($conversa);
        $conversa->mensagens()->create(['papel' => 'usuario', 'texto' => $pergunta, 'hoje' => $hoje]);
        $conversa->touch();

        $mensagens[] = $this->daPessoa($pergunta, $hoje);
        $permitidos = [...$permitidos, ...array_keys(ConferenciaDeValores::valores($pergunta))];
        $resposta = new RespostaEmAndamento;

        try {
            for ($rodada = 1; $rodada <= config()->integer('assistente.max_rodadas'); $rodada++) {
                $mensagem = $this->chamar($mensagens);
                $resposta->consumo->contar($mensagem);

                if ($mensagem->stopReason === 'refusal') {
                    return $this->gravar($conversa, $resposta, SituacaoDaResposta::Recusada,
                        'O Claude não quis responder a essa pergunta. Tente perguntar de outro jeito.');
                }

                if ($mensagem->stopReason === 'max_tokens') {
                    return $this->gravar($conversa, $resposta, SituacaoDaResposta::Limite,
                        'A resposta passou do tamanho máximo. Tente uma pergunta mais específica.');
                }

                if ($mensagem->stopReason !== 'tool_use') {
                    $texto = $this->texto($mensagem);
                    $resposta->semFonte = ConferenciaDeValores::semFonte($texto, [...$permitidos, ...$resposta->valores]);

                    return $this->gravar($conversa, $resposta, SituacaoDaResposta::Respondida, $texto);
                }

                // A resposta do Claude volta inteira (com o raciocínio): a API confere que o
                // histórico chega igual ao que ela produziu.
                $mensagens[] = ['role' => 'assistant', 'content' => $mensagem->content];
                $mensagens[] = ['role' => 'user', 'content' => $this->consultar($mensagem, $resposta)];
            }

            return $this->gravar($conversa, $resposta, SituacaoDaResposta::Limite,
                'Essa pergunta precisou de consultas demais e parei antes de terminar. Tente dividir em perguntas menores.');
        } catch (APIException $erro) {
            return $this->gravar($conversa, $resposta, SituacaoDaResposta::Erro, ErroDaApi::mensagem($erro));
        }
    }

    /**
     * @param  list<array<string, mixed>>  $mensagens
     */
    private function chamar(array $mensagens): BetaMessage
    {
        return $this->cliente->beta->messages->create(...$this->parametros($mensagens));
    }

    /**
     * Os parâmetros da chamada num array, como o tool runner do próprio SDK monta os dele: o
     * histórico leva blocos da resposta (raciocínio, pedidos de consulta) de volta como vieram,
     * e o formato exato quem confere é o SDK e a API. O teste confere o que sai na requisição.
     *
     * @param  list<array<string, mixed>>  $mensagens
     * @return array<string, mixed>
     */
    private function parametros(array $mensagens): array
    {
        return [
            'maxTokens' => config()->integer('assistente.max_tokens'),
            'messages' => $mensagens,
            'model' => config()->string('assistente.modelo'),
            // Cache em dois pontos: o fim das instruções (ferramentas + sistema, iguais em toda
            // pergunta) e o fim do histórico (a cada rodada de consultas, só o novo é cobrado inteiro).
            'cacheControl' => ['type' => 'ephemeral'],
            // Se o modelo declinar por política, a API tenta outro modelo sozinha.
            'fallbacks' => 'default',
            'outputConfig' => ['effort' => config()->string('assistente.esforco')],
            'system' => [['type' => 'text', 'text' => Instrucoes::SISTEMA, 'cacheControl' => ['type' => 'ephemeral']]],
            'thinking' => ['type' => 'adaptive'],
            'tools' => $this->ferramentas->definicoes(),
            'betas' => ['server-side-fallback-2026-07-01'],
        ];
    }

    /**
     * Executa as consultas pedidas e devolve os resultados, todos numa mensagem só.
     *
     * @return list<array<string, mixed>>
     */
    private function consultar(BetaMessage $mensagem, RespostaEmAndamento $resposta): array
    {
        $resultados = [];

        foreach ($mensagem->content as $bloco) {
            if (! $bloco instanceof BetaToolUseBlock) {
                continue;
            }

            $entrada = $bloco->input;
            $resultado = $this->ferramentas->executar($bloco->name, $entrada);
            $resposta->consultas[] = [
                'ferramenta' => $bloco->name,
                'descricao' => $this->ferramentas->descrever($bloco->name, $entrada),
                'erro' => $resultado->erro,
            ];

            if (! $resultado->erro) {
                $resposta->valores = [...$resposta->valores, ...array_keys(ConferenciaDeValores::valores($resultado->conteudo))];
            }

            $resultados[] = [
                'type' => 'tool_result',
                'toolUseID' => $bloco->id,
                'content' => $resultado->conteudo,
                'isError' => $resultado->erro,
            ];
        }

        return $resultados;
    }

    /**
     * As últimas perguntas respondidas da conversa, só em texto: a pergunta com a data dela e a
     * resposta final. As consultas de cada pergunta não voltam (para uma pergunta seguinte, o
     * Claude consulta de novo), e os valores que elas devolveram continuam valendo na conferência.
     *
     * @return array{list<array<string, mixed>>, list<string>}
     */
    private function historico(Conversa $conversa): array
    {
        $pares = [];
        $pergunta = null;

        foreach ($conversa->mensagens()->get() as $mensagem) {
            if ($mensagem->papel === 'usuario') {
                $pergunta = $mensagem;
            } elseif ($pergunta !== null && $mensagem->situacao === SituacaoDaResposta::Respondida) {
                // Só volta a pergunta que teve resposta; a que deu erro ou foi recusada fica de fora.
                $pares[] = [$pergunta, $mensagem];
            }
        }

        $mensagens = [];
        $valores = [];
        foreach (array_slice($pares, -config()->integer('assistente.perguntas_anteriores')) as [$daPessoa, $doAssistente]) {
            $mensagens[] = $this->daPessoa($daPessoa->texto, $daPessoa->hoje ?? CarbonImmutable::parse($daPessoa->created_at));
            $mensagens[] = ['role' => 'assistant', 'content' => $doAssistente->texto];
            foreach ($doAssistente->valores_das_consultas ?? [] as $valor) {
                if (is_string($valor)) {
                    $valores[] = $valor;
                }
            }
        }

        return [$mensagens, $valores];
    }

    /**
     * @return array<string, mixed>
     */
    private function daPessoa(string $pergunta, CarbonImmutable $hoje): array
    {
        return ['role' => 'user', 'content' => [
            ['type' => 'text', 'text' => Instrucoes::contextoDoDia($hoje)],
            ['type' => 'text', 'text' => $pergunta],
        ]];
    }

    private function texto(BetaMessage $mensagem): string
    {
        $partes = [];
        foreach ($mensagem->content as $bloco) {
            if ($bloco instanceof BetaTextBlock && trim($bloco->text) !== '') {
                $partes[] = trim($bloco->text);
            }
        }

        return $partes === [] ? 'O Claude não escreveu resposta. Tente perguntar de novo.' : implode("\n\n", $partes);
    }

    private function gravar(Conversa $conversa, RespostaEmAndamento $resposta, SituacaoDaResposta $situacao, string $texto): Mensagem
    {
        return $conversa->mensagens()->create([
            'papel' => 'assistente',
            'texto' => $texto,
            'situacao' => $situacao,
            'consultas' => $resposta->consultas,
            'valores_das_consultas' => array_values(array_unique($resposta->valores)),
            'valores_sem_fonte' => $resposta->semFonte,
            'modelo' => $resposta->consumo->modelo,
            'uso' => $resposta->consumo->uso,
        ]);
    }
}
