# 0019 — A IA testada sem chamar a API

**Marco:** M5 · M6

## Contexto

O assistente e a análise do mês precisam de teste como o resto do app. Chamar a API de verdade no CI custaria dinheiro a cada build, exigiria a chave no GitHub e daria respostas diferentes a cada vez. Mas um dublê de uma interface própria testaria só o meu código, não o que o SDK manda pela rede.

## Decisão

- Os testes usam **o SDK de verdade, com o cliente HTTP trocado** por um falso (`php-http/mock-client`), que o SDK aceita como transporte.
- Cada teste **enfileira respostas no formato da API** (pedido de consulta, texto, recusa, erro 401, 429, 529, falha de rede) e **confere o JSON que o app mandou**: modelo, esforço, fallback, cache, a data na pergunta, o raciocínio voltando, o esquema da saída estruturada, e que a descrição de um lançamento não sai do app.
- O loop de consultas do assistente é escrito à mão, e não com o tool runner do SDK, para o app registrar cada consulta e cada valor devolvido.

## Alternativas descartadas

- **Chamar a API no CI.** Custo, chave fora do servidor, resultado que muda a cada execução.
- **Interface própria com um dublê.** Não testaria o SDK nem o formato que sai na rede.
- **Tool runner do SDK.** Esconderia justamente o que a conferência precisa ver.

## Consequências

- Os testes provam o que o app manda e como ele trata cada resposta possível. Não provam como o modelo responde a uma pergunta de verdade: isso só aparece em uso, e é por isso que o uso fica gravado.
- Uma mudança no formato da API aparece primeiro em produção, como erro tratado e registrado no log.

## Como é verificado

- `AssistenteTest` — a conversa inteira, com respostas gravadas
- `AnalistaDoMesTest` — a análise, com respostas gravadas
- `AnaliseTest` — a análise pela tela
- `AnalistaDoMesTest::test_sem_conexao_vira_mensagem_para_a_pessoa`
