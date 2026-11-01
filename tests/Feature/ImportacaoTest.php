<?php

namespace Tests\Feature;

use App\Enums\OrigemLancamento;
use App\Enums\StatusImportacao;
use App\Importacao\Importador;
use App\Models\Categoria;
use App\Models\Conta;
use App\Models\Importacao;
use App\Models\Lancamento;
use App\Models\Regra;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\OfxDeTeste;
use Tests\TestCase;

class ImportacaoTest extends TestCase
{
    use RefreshDatabase;

    private Conta $conta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->travelTo('2026-11-01 10:00:00');
        $this->conta = Conta::factory()->create(['saldo_inicial' => 0]);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string, 3?: string|null}>  $transacoes
     */
    private function enviar(array $transacoes, string $contaNoBanco = '00012345-6', ?string $saldo = null): Importacao
    {
        $this->post(route('importacoes.store'), [
            'conta_id' => $this->conta->id,
            'arquivo' => OfxDeTeste::arquivo($transacoes, $contaNoBanco, $saldo),
        ])->assertSessionHasNoErrors();

        $importacao = Importacao::query()->latest('id')->firstOrFail();
        $this->assertSame(StatusImportacao::Pendente, $importacao->status);

        return $importacao;
    }

    /**
     * @param  array<int, string>  $decisoes
     */
    private function confirmar(Importacao $importacao, array $decisoes = []): Importacao
    {
        $this->post(route('importacoes.confirmar', $importacao), ['decisoes' => $decisoes])
            ->assertRedirect(route('importacoes.show', $importacao));

        return $importacao->refresh();
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string, 3?: string|null}>  $transacoes
     */
    private function importar(array $transacoes, string $contaNoBanco = '00012345-6', ?string $saldo = null): Importacao
    {
        return $this->confirmar($this->enviar($transacoes, $contaNoBanco, $saldo));
    }

    /**
     * @return list<string>
     */
    private function situacoesDaPrevia(Importacao $importacao): array
    {
        $situacoes = [];

        $this->get(route('importacoes.show', $importacao))
            ->assertInertia(function (Assert $page) use (&$situacoes) {
                $page->component('importacoes/Previa');
                $situacoes = array_column($page->toArray()['props']['linhas'], 'situacao');
            });

        return $situacoes;
    }

    public function test_o_arquivo_vira_uma_previa_e_nada_e_gravado_antes_de_confirmar(): void
    {
        $this->post(route('importacoes.store'), [
            'conta_id' => $this->conta->id,
            'arquivo' => OfxDeTeste::arquivo([
                ['2026-10-05', '6500.00', 'SALARIO', 'F1'],
                ['2026-10-10', '-150', 'PADARIA', 'F2'],
            ]),
        ])->assertRedirect(route('importacoes.show', Importacao::query()->sole()));

        $this->assertDatabaseCount('lancamentos', 0);
        $this->assertSame(['nova', 'nova'], $this->situacoesDaPrevia(Importacao::query()->sole()));
    }

    public function test_arquivo_que_nao_e_extrato_volta_com_o_motivo(): void
    {
        $this->post(route('importacoes.store'), [
            'conta_id' => $this->conta->id,
            'arquivo' => UploadedFile::fake()->createWithContent('extrato.ofx', "data;valor\n2026-10-10;-1,50"),
        ])->assertSessionHasErrors(['arquivo' => 'O arquivo não parece um extrato OFX.']);

        $this->post(route('importacoes.store'), [
            'conta_id' => $this->conta->id,
            'arquivo' => UploadedFile::fake()->createWithContent('extrato.csv', 'qualquer coisa'),
        ])->assertSessionHasErrors(['arquivo' => 'Envie o extrato em OFX (arquivo .ofx).']);

        $this->assertDatabaseCount('importacoes', 0);
    }

    public function test_conta_arquivada_nao_recebe_importacao(): void
    {
        $arquivada = Conta::factory()->arquivada()->create();

        $this->post(route('importacoes.store'), [
            'conta_id' => $arquivada->id,
            'arquivo' => OfxDeTeste::arquivo([['2026-10-10', '-1.00', 'X']]),
        ])->assertSessionHasErrors(['conta_id' => 'Escolha uma conta ativa.']);
    }

    public function test_confirmar_grava_os_lancamentos_em_centavos_com_o_sinal_do_banco(): void
    {
        $importacao = $this->importar([
            ['2026-10-05', '6500.00', 'SALARIO', 'F1'],
            ['2026-10-10', '-150', 'PADARIA', 'F2'],
        ]);

        $this->assertSame(StatusImportacao::Concluida, $importacao->status);
        $this->assertSame(2, $importacao->importados);
        $this->assertNull($importacao->linhas);

        $padaria = Lancamento::query()->where('descricao', 'PADARIA')->sole();
        $this->assertSame(-15000, $padaria->valor);
        $this->assertSame('2026-10-10', $padaria->data->toDateString());
        $this->assertSame(OrigemLancamento::Importado, $padaria->origem);
        $this->assertSame($importacao->id, $padaria->importacao_id);
        $this->assertSame('F2', $padaria->id_externo);
        $this->assertNotNull($padaria->impressao);
        $this->assertSame(650000, Lancamento::query()->where('descricao', 'SALARIO')->value('valor'));
    }

    public function test_importar_o_mesmo_arquivo_de_novo_nao_duplica_nada(): void
    {
        $transacoes = [
            ['2026-10-05', '6500.00', 'SALARIO', 'F1'],
            ['2026-10-10', '-150', 'PADARIA', 'F2'],
        ];
        $this->importar($transacoes);

        $segunda = $this->enviar($transacoes);
        $this->assertSame(['ja_existe', 'ja_existe'], $this->situacoesDaPrevia($segunda));

        $segunda = $this->confirmar($segunda);
        $this->assertSame(2, $segunda->ja_existiam);
        $this->assertSame(0, $segunda->importados);
        $this->assertDatabaseCount('lancamentos', 2);
    }

    public function test_periodos_sobrepostos_trazem_so_o_que_falta(): void
    {
        $this->importar([
            ['2026-10-01', '-10.00', 'A', 'F1'],
            ['2026-10-15', '-20.00', 'B', 'F2'],
        ]);

        $segunda = $this->importar([
            ['2026-10-15', '-20.00', 'B', 'F2'],
            ['2026-10-20', '-30.00', 'C', 'F3'],
        ]);

        $this->assertSame(1, $segunda->importados);
        $this->assertSame(1, $segunda->ja_existiam);
        $this->assertSame(['A', 'B', 'C'], Lancamento::query()->orderBy('data')->pluck('descricao')->all());
    }

    public function test_compras_identicas_no_mesmo_dia_entram_todas_e_so_uma_vez(): void
    {
        // Sem FITID, para valer só a impressão digital — o caso dos bancos que não mandam identificador estável.
        $cafe = ['2026-10-10', '-5.00', 'CAFE'];

        $this->importar([$cafe, $cafe]);
        $this->assertDatabaseCount('lancamentos', 2);

        $this->importar([$cafe, $cafe]);
        $this->assertDatabaseCount('lancamentos', 2);

        // Exportado mais tarde no mesmo dia: apareceu um terceiro café.
        $terceira = $this->importar([$cafe, $cafe, $cafe]);
        $this->assertSame(1, $terceira->importados);
        $this->assertDatabaseCount('lancamentos', 3);
    }

    public function test_o_fitid_reconhece_a_transacao_mesmo_com_outra_descricao(): void
    {
        $this->importar([['2026-10-10', '-89.90', 'COMPRA PENDENTE', 'ABC-1']]);

        $segunda = $this->enviar([['2026-10-10', '-89.90', 'MERCADO DO BAIRRO', 'ABC-1']]);

        $this->assertSame(['ja_existe'], $this->situacoesDaPrevia($segunda));
    }

    public function test_fitid_reaproveitado_com_outro_valor_nao_esconde_lancamento_novo(): void
    {
        $this->importar([['2026-09-10', '-10.00', 'TARIFA', '000123']]);

        $segunda = $this->enviar([['2026-10-10', '-99.00', 'BOLETO', '000123']]);

        $this->assertSame(['nova'], $this->situacoesDaPrevia($segunda));
    }

    public function test_editar_o_lancamento_importado_nao_faz_ele_voltar_na_proxima_importacao(): void
    {
        $transacao = ['2026-10-10', '-150', 'PAG*PADARIA', 'F1'];
        $this->importar([$transacao]);
        $lancamento = Lancamento::query()->sole();

        $this->put(route('lancamentos.update', $lancamento), [
            'direcao' => 'saida',
            'valor' => '149,90',
            'data' => '2026-10-09',
            'descricao' => 'Padaria do bairro',
            'conta_id' => $this->conta->id,
            'categoria_id' => null,
        ])->assertSessionHasNoErrors();

        // Mesmo sem o FITID (banco que não manda), a impressão guardada é a do extrato, não a da tela.
        $this->assertSame(['ja_existe'], $this->situacoesDaPrevia($this->enviar([$transacao])));
        $this->assertSame(['ja_existe'], $this->situacoesDaPrevia($this->enviar([[...array_slice($transacao, 0, 3)]])));
    }

    public function test_lancamento_manual_parecido_e_vinculado_em_vez_de_duplicado(): void
    {
        $mercado = Categoria::factory()->create(['nome' => 'Mercado']);
        $manual = Lancamento::factory()->for($this->conta)->saida(5000)->create([
            'data' => '2026-10-09',
            'descricao' => 'Mercado da semana',
            'categoria_id' => $mercado->id,
        ]);
        // Uma regra que daria outra categoria: no vinculado, vale a escolha da pessoa.
        Regra::query()->create(['texto' => 'SUPERMERCADO', 'categoria_id' => Categoria::factory()->create()->id]);

        $importacao = $this->enviar([['2026-10-11', '-50.00', 'SUPERMERCADO X', 'F1']]);

        $this->get(route('importacoes.show', $importacao))
            ->assertInertia(fn (Assert $page) => $page
                ->where('linhas.0.situacao', 'parecida')
                ->where('linhas.0.parecidoCom.id', $manual->id)
                ->where('linhas.0.parecidoCom.categoria', 'Mercado'));

        $importacao = $this->confirmar($importacao);

        $this->assertSame(1, $importacao->vinculados);
        $this->assertDatabaseCount('lancamentos', 1);

        // O manual continua do jeito que a pessoa escreveu, agora com a identidade da linha do banco.
        $manual->refresh();
        $this->assertSame('Mercado da semana', $manual->descricao);
        $this->assertSame('2026-10-09', $manual->data->toDateString());
        $this->assertSame($mercado->id, $manual->categoria_id);
        $this->assertSame(OrigemLancamento::Manual, $manual->origem);
        $this->assertSame($importacao->id, $manual->importacao_id);
        $this->assertSame('F1', $manual->id_externo);

        $this->assertSame(['ja_existe'], $this->situacoesDaPrevia($this->enviar([['2026-10-11', '-50.00', 'SUPERMERCADO X', 'F1']])));
    }

    public function test_as_regras_categorizam_o_que_a_importacao_cria(): void
    {
        $padaria = Categoria::factory()->create(['nome' => 'Padaria']);
        $salario = Categoria::factory()->receita()->create(['nome' => 'Salário']);
        Regra::query()->create(['texto' => 'PADARIA', 'categoria_id' => $padaria->id]);
        Regra::query()->create(['texto' => 'EMPRESA', 'categoria_id' => $salario->id]);

        $importacao = $this->enviar([
            ['2026-10-05', '6500.00', 'PAGTO EMPRESA FICTICIA', 'F1'],
            ['2026-10-10', '-12.50', 'Padaria São João', 'F2'],
            ['2026-10-11', '12.50', 'ESTORNO PADARIA SAO JOAO', 'F3'],
        ]);

        // A prévia já mostra a categoria que cada linha vai receber.
        $this->get(route('importacoes.show', $importacao))
            ->assertInertia(fn (Assert $page) => $page
                ->where('linhas.0.categoria', 'Salário')
                ->where('linhas.1.categoria', 'Padaria')
                ->where('linhas.2.categoria', null));

        $this->confirmar($importacao);

        $this->assertSame(
            ['F1' => $salario->id, 'F2' => $padaria->id, 'F3' => null],
            Lancamento::query()->orderBy('id_externo')->pluck('categoria_id', 'id_externo')->all(),
        );
    }

    public function test_parecido_so_com_o_mesmo_valor_dentro_da_janela_e_o_mais_perto(): void
    {
        Lancamento::factory()->for($this->conta)->saida(1000)->create(['data' => '2026-10-06']); // 4 dias: fora
        Lancamento::factory()->for($this->conta)->saida(2001)->create(['data' => '2026-10-15']); // outro valor
        $tresDias = Lancamento::factory()->for($this->conta)->saida(3000)->create(['data' => '2026-10-17']);
        $umDia = Lancamento::factory()->for($this->conta)->saida(3000)->create(['data' => '2026-10-21']);
        Lancamento::factory()->for(Conta::factory())->saida(4000)->create(['data' => '2026-10-25']); // outra conta

        $importacao = $this->enviar([
            ['2026-10-10', '-10.00', 'A'],
            ['2026-10-15', '-20.00', 'B'],
            ['2026-10-20', '-30.00', 'C'],
            ['2026-10-20', '-30.00', 'D'],
            ['2026-10-25', '-40.00', 'E'],
        ]);

        $this->get(route('importacoes.show', $importacao))
            ->assertInertia(fn (Assert $page) => $page
                ->where('linhas.0.situacao', 'nova')
                ->where('linhas.1.situacao', 'nova')
                // O de 1 dia responde pela primeira; o de 3 dias, pela segunda. Cada manual vale uma linha só.
                ->where('linhas.2.parecidoCom.id', $umDia->id)
                ->where('linhas.3.parecidoCom.id', $tresDias->id)
                ->where('linhas.4.situacao', 'nova'));
    }

    public function test_as_decisoes_da_previa_sao_respeitadas(): void
    {
        $manual = Lancamento::factory()->for($this->conta)->saida(5000)->create(['data' => '2026-10-10']);

        $importacao = $this->confirmar($this->enviar([
            ['2026-10-10', '-50.00', 'SUPERMERCADO', 'F1'],
            ['2026-10-12', '-7.00', 'ESTACIONAMENTO', 'F2'],
            ['2026-10-13', '-8.00', 'BANCA', 'F3'],
        ]), [0 => 'importar', 1 => 'ignorar']);

        $this->assertSame(2, $importacao->importados);
        $this->assertSame(1, $importacao->ignorados);
        $this->assertSame(0, $importacao->vinculados);
        $this->assertNull($manual->refresh()->impressao);
        $this->assertSame(['BANCA', 'SUPERMERCADO'], Lancamento::query()->where('origem', 'importado')->orderBy('descricao')->pluck('descricao')->all());
    }

    public function test_confirmar_duas_vezes_nao_importa_de_novo(): void
    {
        $importacao = $this->enviar([['2026-10-10', '-1.00', 'X', 'F1']]);
        // A outra aba: o mesmo registro, carregado antes da primeira confirmação.
        $outraAba = Importacao::query()->findOrFail($importacao->id);

        $this->confirmar($importacao);

        $this->assertFalse(app(Importador::class)->confirmar($outraAba, []));
        $this->post(route('importacoes.confirmar', $importacao))
            ->assertSessionHas('inertia.flash_data.toast.type', 'info');
        $this->assertDatabaseCount('lancamentos', 1);
    }

    public function test_desfazer_apaga_o_que_criou_e_solta_o_que_vinculou(): void
    {
        $manual = Lancamento::factory()->for($this->conta)->saida(5000)->create(['data' => '2026-10-10']);
        $deOutraImportacao = $this->importar([['2026-09-01', '-1.00', 'SETEMBRO', 'S1']]);
        $importacao = $this->importar([
            ['2026-10-10', '-50.00', 'SUPERMERCADO', 'F1'],
            ['2026-10-12', '-7.00', 'ESTACIONAMENTO', 'F2'],
        ]);

        $this->delete(route('importacoes.destroy', $importacao))
            ->assertRedirect(route('importacoes.index'));

        $this->assertModelMissing($importacao);
        $this->assertNull(Lancamento::query()->where('descricao', 'ESTACIONAMENTO')->first());
        $this->assertSame(1, $deOutraImportacao->lancamentos()->count());

        $manual->refresh();
        $this->assertNull($manual->importacao_id);
        $this->assertNull($manual->impressao);
        $this->assertNull($manual->id_externo);

        // Desfeita, a importação pode ser refeita do zero.
        $this->assertSame(['parecida', 'nova'], $this->situacoesDaPrevia($this->enviar([
            ['2026-10-10', '-50.00', 'SUPERMERCADO', 'F1'],
            ['2026-10-12', '-7.00', 'ESTACIONAMENTO', 'F2'],
        ])));
    }

    public function test_uma_previa_nova_substitui_a_que_nao_foi_confirmada(): void
    {
        $primeira = $this->enviar([['2026-10-10', '-1.00', 'X']]);
        $segunda = $this->enviar([['2026-10-11', '-2.00', 'Y']]);

        $this->assertModelMissing($primeira);
        $this->assertModelExists($segunda);

        $this->delete(route('importacoes.destroy', $segunda))->assertRedirect(route('importacoes.index'));
        $this->assertDatabaseCount('importacoes', 0);
    }

    public function test_avisa_quando_o_arquivo_e_de_outra_conta_do_mesmo_banco(): void
    {
        $this->importar([['2026-10-10', '-1.00', 'X']], contaNoBanco: '1111-1');

        $this->get(route('importacoes.show', $this->enviar([['2026-10-11', '-1.00', 'Y']], contaNoBanco: '9999-9')))
            ->assertInertia(fn (Assert $page) => $page
                ->where('contaDiferente.arquivo', '9999')
                ->where('contaDiferente.anterior', '1111'));

        $this->get(route('importacoes.show', $this->enviar([['2026-10-11', '-1.00', 'Y']], contaNoBanco: '1111-1')))
            ->assertInertia(fn (Assert $page) => $page->where('contaDiferente', null));
    }

    public function test_o_resumo_confere_o_saldo_do_app_com_o_do_banco(): void
    {
        $this->conta->update(['saldo_inicial' => 100000]);
        // Depois da data do saldo: não pode entrar na conta.
        Lancamento::factory()->for($this->conta)->saida(99900)->create(['data' => '2026-11-01']);

        $confere = $this->importar([['2026-10-31', '-150', 'ALUGUEL', 'F1']], saldo: '850.00');

        $this->get(route('importacoes.show', $confere))
            ->assertInertia(fn (Assert $page) => $page
                ->component('importacoes/Resumo')
                ->where('conferencia.em', '2026-10-31')
                ->where('conferencia.banco', 85000)
                ->where('conferencia.app', 85000)
                ->where('conferencia.diferenca', 0)
                ->has('lancamentos', 1)
                ->where('lancamentos.0.vinculado', false));

        $naoConfere = $this->importar([['2026-10-31', '-150', 'ALUGUEL', 'F1']], saldo: '900.00');

        $this->get(route('importacoes.show', $naoConfere))
            ->assertInertia(fn (Assert $page) => $page->where('conferencia.diferenca', -5000));
    }

    public function test_lista_as_importacoes_da_mais_recente_para_a_mais_antiga(): void
    {
        $setembro = $this->importar([['2026-09-10', '-1.00', 'X']]);
        $outubro = $this->importar([['2026-10-10', '-1.00', 'Y']]);

        $this->get(route('importacoes.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('importacoes/Index')
                ->has('importacoes', 2)
                ->where('importacoes.0.id', $outubro->id)
                ->where('importacoes.1.id', $setembro->id)
                ->where('importacoes.0.importados', 1));
    }
}
