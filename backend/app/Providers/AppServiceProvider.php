<?php

namespace App\Providers;

use App\Support\Audit\AuditLogger;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // El contenedor de Laravel NO crea el logger: lo delega al Singleton.
        // Así, tanto `AuditLogger::getInstance()` como la inyección por
        // type-hint devuelven el mismo objeto. Ojo con la diferencia:
        // `$this->app->singleton()` garantiza una instancia por *contenedor*,
        // mientras que el Singleton GoF la garantiza por *proceso*.
        $this->app->singleton(AuditLogger::class, fn () => AuditLogger::getInstance());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
