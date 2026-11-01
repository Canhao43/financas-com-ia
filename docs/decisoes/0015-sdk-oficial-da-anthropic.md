# 0015 — SDK oficial da Anthropic, com o modelo no `.env`

**Marco:** M5

## Contexto

O assistente usa o Claude, pago pela conta do dono do app. A primeira proposta foi o `laravel/ai`, que fala com vários provedores e promete trocar de provedor numa linha do `.env`. Com o provedor já escolhido, essa troca perde peso, e o que pesa é usar os recursos da API: raciocínio adaptativo, esforço, saída estruturada, troca de modelo na recusa.

## Decisão

- **SDK oficial** `anthropic-ai/sdk` (1.x), que recebe primeiro cada recurso novo da API.
- **Modelo e esforço no `.env`** (`ANTHROPIC_MODEL`, `ANTHROPIC_EFFORT`). O padrão é o Claude Opus 5.5 com esforço `medium`, escrito na configuração para não mudar sozinho se o padrão da API mudar.
- **`fallbacks: "default"`**: se o modelo declinar por política, a API tenta outro modelo sozinha.
- **Sem a chave (`ANTHROPIC_API_KEY`), o assistente e a análise ficam desligados**, e a tela explica o que falta. O resto do app funciona igual.
- **Tetos de custo**: 8 chamadas por pergunta, 10 perguntas ou análises por minuto, 500 caracteres por pergunta.
- **O uso de cada resposta fica gravado** (tokens de entrada, saída e cache, e o modelo que respondeu).

## Alternativas descartadas

- **`laravel/ai`.** A troca de provedor numa linha não vale o atraso nos recursos novos.
- **HTTP direto.** Reescreveria o que o SDK já faz: tipos, novas tentativas, erros.
- **Escolher um modelo mais barato de saída.** O custo é decisão de quem paga, e deve ser tomada com o uso medido, não estimado.

## Consequências

- Trocar de provedor seria reescrever `Assistente` e `AnalistaDoMes`.
- O modelo pode ser trocado a qualquer momento pelo `.env`, olhando o uso gravado.

## Como é verificado

- `AssistenteTest::test_responde_consultando_o_banco_e_confere_os_valores` — confere no JSON enviado o modelo, o esforço, o fallback e a chave no cabeçalho
- `AssistenteTest::test_sem_chave_o_assistente_fica_desligado`
- `AnaliseTest::test_sem_chave_a_analise_fica_desligada`
- `AssistenteTest::test_para_depois_do_teto_de_rodadas`
- `AssistenteTest::test_pergunta_vazia_ou_longa_e_recusada`
- `AssistenteTest::test_erro_da_api_vira_mensagem_e_a_pergunta_fica_gravada`
