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
        Prometheus::addGauge(
            label: 'php_memory_usage_bytes',
            value: fn () => memory_get_usage(true),
            helpText: 'Mémoire PHP utilisée en bytes'
        );

        Prometheus::addGauge(
            label: 'php_memory_peak_bytes',
            value: fn () => memory_get_peak_usage(true),
            helpText: 'Pic de mémoire PHP en bytes'
        );

        // Métrique de temps d'exécution PHP
        Prometheus::addGauge(
            label: 'php_execution_time_seconds',
            value: fn () => defined('LARAVEL_START') ? microtime(true) - LARAVEL_START : 0,
            helpText: 'Temps d\'exécution PHP en secondes'
        );

        // Note: Les métriques HTTP (requêtes, durée, erreurs) sont automatiquement
        // collectées par le middleware du package spatie/laravel-prometheus
        // lorsqu'il est activé dans le kernel HTTP.
    }
}
