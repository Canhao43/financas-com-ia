<?php

namespace App\Assistente;

use RuntimeException;

/**
 * O Claude chamou uma ferramenta com dado que não serve. A mensagem volta para ele como erro da
 * ferramenta, para que corrija a chamada: por isso é escrita para o modelo ler.
 */
class EntradaInvalida extends RuntimeException {}
