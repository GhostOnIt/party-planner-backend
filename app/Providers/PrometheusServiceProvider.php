<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Spatie\Prometheus\Facades\Prometheus;

class PrometheusServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     * 
     * Enregistre les métriques Prometheus par défaut pour l'API Laravel.
     */
    public function boot(): void
    {
        // Métriques HTTP par défaut (requêtes, durée, erreurs)
        // Ces métriques sont automatiquement collectées par le middleware du package
        
        // Métrique de mémoire PHP
        Prometheus::addGauge('php_memory_usage_bytes')
            ->help('Mémoire PHP utilisée en bytes')
            ->value(fn () => memory_get_usage(true));

        Prometheus::addGauge('php_memory_peak_bytes')
            ->help('Pic de mémoire PHP en bytes')
            ->value(fn () => memory_get_peak_usage(true));

        // Métrique de temps d'exécution PHP
        Prometheus::addGauge('php_execution_time_seconds')
            ->help('Temps d\'exécution PHP en secondes')
            ->value(fn () => microtime(true) - LARAVEL_START);

        // Note: Les métriques HTTP (requêtes, durée, erreurs) sont automatiquement
        // collectées par le middleware du package spatie/laravel-prometheus
        // lorsqu'il est activé dans le kernel HTTP.
    }
}
