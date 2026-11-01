<?php

namespace App\Providers;

use Anthropic\Client;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // A chave vem da configuração, e não do ambiente direto: com o cache de configuração de
        // produção, o .env não é lido. Sem chave, o cliente existe, mas o assistente fica desligado.
        $this->app->bind(Client::class, fn () => new Client(
            apiKey: (string) config('assistente.chave'),
            requestOptions: ['timeout' => (float) config()->integer('assistente.timeout'), 'maxRetries' => 2],
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
