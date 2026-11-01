# 0010 — Backup diário, cifrado com chave pública, fora do servidor

**Marco:** M3

## Contexto

O banco é um arquivo num servidor só (ver [0009](0009-sqlite-em-producao.md)), e o servidor pode sumir: disco, invasão, ou o provedor recuperando a máquina (ver [0012](0012-hospedagem-gratis-oracle-e-duckdns.md)). O backup sai do servidor, então quem o guarda não pode conseguir lê-lo. E o modo de falha mais comum de backup é falhar calado por semanas.

## Decisão

- Todo dia às 3h, o comando `backup:criar`:
    1. copia o banco com **`VACUUM INTO`**, uma cópia consistente sem parar o site;
    2. **confere a cópia**: existe, não está vazia, abre, passa no `integrity_check` e tem a tabela `migrations`;
    3. **cifra com o age, usando só a chave pública**: o servidor cifra, mas não consegue decifrar;
    4. envia com o **rclone** (Google Drive com escopo `drive.file`: o token só enxerga o que ele mesmo criou);
    5. só depois de tudo dar certo, apaga as cópias antigas do servidor e mantém as últimas 7.
- Cada tentativa é registrada. O **painel avisa** quando o último backup bom passou de 36 horas, e mostra a falha mais recente.
- A chave privada fica só com o dono, fora do servidor.

## Alternativas descartadas

- **Copiar o arquivo do banco.** Pode pegar uma escrita pela metade, e no modo WAL parte dos dados está em outro arquivo.
- **Senha simétrica (`rclone crypt`).** A senha ficaria no servidor: quem o invadisse abriria todos os backups.
- **GPG.** Faz o mesmo com mais peças.
- **Snapshot do provedor.** Mora no mesmo lugar que o servidor.
- **Monitor externo ou e-mail de alerta.** Mais um serviço; o painel é a tela que o dono já abre.

## Consequências

- Perder a chave privada é perder todos os backups: ela fica no gerenciador de senhas, com uma cópia fora do computador.
- Backup que falha nunca apaga os bons.
- A restauração tem dois cuidados que o roteiro (`docs/deploy.md`) descreve: apagar o `-wal` e o `-shm` antigos e gravar o arquivo como o usuário do app.

## Como é verificado

- `BackupTest::test_copia_cifra_e_envia_para_fora_do_servidor`
- `BackupTest::test_a_copia_sem_cifra_nao_sobra_no_disco`
- `BackupTest::test_copia_que_nao_passa_na_conferencia_nao_e_cifrada_nem_enviada`
- `BackupTest::test_a_conferencia_aceita_a_copia_boa_e_recusa_a_que_nao_serve`
- `BackupTest::test_se_o_age_falha_nada_e_enviado_e_nada_sobra`
- `BackupTest::test_backup_que_falha_nao_apaga_as_copias_antigas`
- `BackupTest::test_guarda_no_servidor_so_as_ultimas_copias`
- `BackupTest::test_o_backup_roda_todo_dia_as_tres_da_manha`
- `DashboardTest::test_avisa_quando_o_ultimo_backup_bom_passou_de_36_horas`
- `DashboardTest::test_avisa_quando_nenhum_backup_deu_certo`
- `imagem.yml`: _Backup de verdade, e a volta dele_ — o backup cifrado na imagem abre com a chave privada
- `imagem.yml`: _Restaura com os comandos do docs/deploy.md_ — o roteiro de restauração não fica desatualizado sem ninguém perceber
