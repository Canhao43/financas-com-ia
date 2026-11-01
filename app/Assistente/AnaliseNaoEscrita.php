<?php

namespace App\Assistente;

use RuntimeException;

/**
 * A análise não saiu (recusa, resposta longa demais, erro da API ou mês sem lançamentos). A
 * mensagem é para a pessoa ler; a análise anterior do mês, se existir, continua intacta.
 */
final class AnaliseNaoEscrita extends RuntimeException {}
