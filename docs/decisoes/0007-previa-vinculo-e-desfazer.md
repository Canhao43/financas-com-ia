# 0007 — Importação com prévia, vínculo ao lançamento manual e desfazer

**Marco:** M2

## Contexto

Importar extrato mexe no dinheiro de uma vez: um arquivo errado (de outra conta, do mês errado) precisa ser visto antes e desfeito depois. E quem lança um gasto à mão durante o mês e importa o extrato no fim teria cada gasto duas vezes: o manual e o do banco.

## Decisão

- **O arquivo vira uma prévia; nada é gravado antes de confirmar.** A prévia guarda as linhas lidas, não o arquivo. Uma prévia nova substitui a que não foi confirmada.
- Cada linha aparece como **nova**, **já existe** ou **parecida com um lançamento manual**. Na confirmação, o servidor classifica tudo de novo com o estado atual: a prévia sugere, a confirmação decide.
- **Parecida = mesmo valor, até 3 dias de diferença, o manual mais próximo**, e cada manual responde por uma linha só. Por padrão ela é **vinculada**: o manual fica como foi escrito e ganha a identidade da linha do banco (FITID e impressão).
- **A confirmação trava por `UPDATE ... WHERE status = 'pendente'`**: num clique duplo ou em duas abas, só uma requisição importa.
- **Desfazer apaga o que a importação criou e solta o que ela vinculou.** A coluna `origem` separa os dois casos.
- O resumo **confere o saldo do app com o saldo informado pelo banco** no arquivo.
- Da conta do banco, o app guarda só os 4 últimos dígitos, o bastante para avisar que o arquivo parece de outra conta.

## Alternativas descartadas

- **Importar direto.** Sem chance de ver um arquivo errado antes.
- **Ignorar o lançamento parecido.** Perguntaria a mesma coisa a cada importação.
- **Fundir o manual com a linha do banco sem prévia.** Dois gastos diferentes de mesmo valor virariam um.
- **`lockForUpdate` na confirmação.** O SQLite ignora.
- **Guardar o número inteiro da conta.** Não é necessário para nada que o app faz.

## Consequências

- Importar leva dois passos (enviar e confirmar), de propósito.
- O lançamento manual vinculado não muda: descrição, data, valor e categoria continuam os que a pessoa escreveu.

## Como é verificado

- `ImportacaoTest::test_o_arquivo_vira_uma_previa_e_nada_e_gravado_antes_de_confirmar`
- `ImportacaoTest::test_uma_previa_nova_substitui_a_que_nao_foi_confirmada`
- `ImportacaoTest::test_as_decisoes_da_previa_sao_respeitadas`
- `ImportacaoTest::test_lancamento_manual_parecido_e_vinculado_em_vez_de_duplicado`
- `ImportacaoTest::test_parecido_so_com_o_mesmo_valor_dentro_da_janela_e_o_mais_perto`
- `ImportacaoTest::test_confirmar_duas_vezes_nao_importa_de_novo`
- `ImportacaoTest::test_desfazer_apaga_o_que_criou_e_solta_o_que_vinculou`
- `ImportacaoTest::test_o_resumo_confere_o_saldo_do_app_com_o_do_banco`
- `ImportacaoTest::test_avisa_quando_o_arquivo_e_de_outra_conta_do_mesmo_banco`
