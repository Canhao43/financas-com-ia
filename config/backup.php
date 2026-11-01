<?php

return [

    /*
    | Chave pública do age ("age1..."). O backup é cifrado para ela, e a chave privada fica
    | com o dono, fora do servidor: quem invadir o servidor não abre os backups.
    */
    'chave_publica' => env('BACKUP_CHAVE_PUBLICA'),

    /*
    | Para onde o rclone envia, no formato "remoto:pasta" (ex.: "gdrive:financas-backups").
    | Backup que fica só no disco do servidor some junto com o servidor.
    */
    'destino' => env('BACKUP_DESTINO'),

    'pasta' => env('BACKUP_PASTA', storage_path('app/backups')),

    // Cópias que ficam também no servidor, para restaurar rápido depois de um erro no app.
    'manter_no_servidor' => (int) env('BACKUP_MANTER_NO_SERVIDOR', 7),

    // O backup é diário: o painel avisa quando o último que deu certo passou deste tempo.
    'aviso_depois_de_horas' => 36,

    // Fora de produção não há agendador rodando, e o aviso só atrapalharia.
    'avisar' => (bool) env('BACKUP_AVISAR', env('APP_ENV') === 'production'),

];
