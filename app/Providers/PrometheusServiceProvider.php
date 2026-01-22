<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
<<<<<<< HEAD
=======
use Spatie\Prometheus\Collectors\Horizon\CurrentMasterSupervisorCollector;
use Spatie\Prometheus\Collectors\Horizon\CurrentProcessesPerQueueCollector;
use Spatie\Prometheus\Collectors\Horizon\CurrentWorkloadCollector;
use Spatie\Prometheus\Collectors\Horizon\FailedJobsPerHourCollector;
use Spatie\Prometheus\Collectors\Horizon\HorizonStatusCollector;
use Spatie\Prometheus\Collectors\Horizon\JobsPerMinuteCollector;
use Spatie\Prometheus\Collectors\Horizon\RecentJobsCollector;
use Spatie\Prometheus\Collectors\Queue\QueueDelayedJobsCollector;
use Spatie\Prometheus\Collectors\Queue\QueueOldestPendingJobCollector;
use Spatie\Prometheus\Collectors\Queue\QueuePendingJobsCollector;
use Spatie\Prometheus\Collectors\Queue\QueueReservedJobsCollector;
use Spatie\Prometheus\Collectors\Queue\QueueSizeCollector;
>>>>>>> e17e8ac (add changes)
use Spatie\Prometheus\Facades\Prometheus;

class PrometheusServiceProvider extends ServiceProvider
{
<<<<<<< HEAD
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
=======
    public function register()
    {
        /*
         * Here you can register all the exporters that you
         * want to export to prometheus
         */
        Prometheus::addGauge('My gauge')
            ->value(function() {
                return 123.45;
            });

        /*
         * Uncomment this line if you want to export
         * all Horizon metrics to prometheus
         */
        // $this->registerHorizonCollectors();

        /*
         * Uncomment this line if you want to export queue metrics to Prometheus.
         * You need to pass an array of queues to monitor.
         */
        // $this->registerQueueCollectors(['default']);
    }

    public function registerHorizonCollectors(): self
    {
        Prometheus::registerCollectorClasses([
            CurrentMasterSupervisorCollector::class,
            CurrentProcessesPerQueueCollector::class,
            CurrentWorkloadCollector::class,
            FailedJobsPerHourCollector::class,
            HorizonStatusCollector::class,
            JobsPerMinuteCollector::class,
            RecentJobsCollector::class,
        ]);

        return $this;
    }

    public function registerQueueCollectors(array $queues = [], ?string $connection = null): self
    {
        Prometheus::registerCollectorClasses([
            QueueSizeCollector::class,
            QueuePendingJobsCollector::class,
            QueueDelayedJobsCollector::class,
            QueueReservedJobsCollector::class,
            QueueOldestPendingJobCollector::class,
        ], compact('connection', 'queues'));

        return $this;
>>>>>>> e17e8ac (add changes)
    }
}
