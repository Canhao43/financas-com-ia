# 0009 — SQLite em produção

**Marco:** M3

## Contexto

O app tem um usuário e poucas escritas por dia. Em produção, dois processos abrem o mesmo banco: o site e o agendador (que faz o backup da madrugada). Os testes já rodavam em SQLite.

## Decisão

- **SQLite em produção**, num volume do Docker.
- **Modo WAL**: a leitura não espera a escrita.
- **Espera de 5 segundos** quando o banco está ocupado (`busy_timeout`), em vez de falhar na hora com "database is locked".
- `synchronous` no padrão do SQLite (FULL).

## Alternativas descartadas

- **MySQL ou PostgreSQL.** Mais um container, mais uma senha para guardar, e backup por dump. Nada disso compra alguma coisa para um usuário.
- **`synchronous = NORMAL`.** Troca durabilidade por uma velocidade que um usuário não percebe.

## Consequências

- Produção roda no mesmo banco dos testes: um erro de SQLite (como o dia 31 que sumia, ver [0004](0004-fuso-de-sao-paulo-e-mes-semiaberto.md)) aparece no teste, não em produção.
- O backup é um arquivo (ver [0010](0010-backup-cifrado-fora-do-servidor.md)).
- Não existe "desfazer migration" confiável: o desfazer é restaurar o arquivo de antes, e por isso a atualização faz backup primeiro (ver [0011](0011-imagem-testada-pelo-ci-e-deploy-manual.md)).
- Vários usuários escrevendo ao mesmo tempo pediriam outra decisão.

## Como é verificado

- `BancoSqliteTest::test_o_banco_em_arquivo_abre_em_wal_e_espera_quando_esta_ocupado`
- `imagem.yml`: _O banco foi migrado na subida, e as categorias de partida entram_
