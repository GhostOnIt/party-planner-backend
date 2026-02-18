# Configuration Prometheus pour Party Planner API

Ce document décrit la configuration de Prometheus pour l'instrumentation de l'API Laravel.

## 📦 Package Installé

- **spatie/laravel-prometheus** : Package Laravel pour exposer des métriques Prometheus

## 🔧 Configuration

### Fichiers Modifiés/Créés

1. **config/prometheus.php** - Configuration principale
2. **app/Providers/PrometheusServiceProvider.php** - Service provider pour les métriques personnalisées
3. **app/Http/Middleware/CollectPrometheusMetrics.php** - Middleware pour collecter les métriques HTTP
4. **bootstrap/providers.php** - Enregistrement du service provider
5. **bootstrap/app.php** - Enregistrement du middleware global

### Métriques Disponibles

#### Métriques HTTP (collectées automatiquement)
- `app_http_requests_total` - Nombre total de requêtes HTTP (labels: method, route, status)
- `app_http_request_duration_seconds` - Durée des requêtes HTTP en secondes (histogram)
- `app_http_errors_total` - Nombre total d'erreurs HTTP (4xx, 5xx)

#### Métriques PHP (collectées automatiquement)
- `app_php_memory_usage_bytes` - Mémoire PHP utilisée en bytes
- `app_php_memory_peak_bytes` - Pic de mémoire PHP en bytes
- `app_php_execution_time_seconds` - Temps d'exécution PHP en secondes

## 🔒 Sécurité

L'endpoint `/metrics` est protégé par le middleware `AllowIps` du package.

### Configuration en Production

1. **Via variable d'environnement** (recommandé) :
```bash
# .env
PROMETHEUS_ALLOWED_IPS=10.0.0.1,192.168.1.100
```

2. **Via configuration Nginx** (alternative) :
```nginx
location /metrics {
    allow 10.0.0.1;  # IP du serveur Prometheus
    deny all;
    
    proxy_pass http://127.0.0.1:8000;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
}
```

## 🧪 Tests Locaux

### Tester l'endpoint /metrics

```bash
# Test basique
curl http://localhost:8000/metrics

# Test avec authentification IP (si configuré)
curl -H "X-Forwarded-For: 127.0.0.1" http://localhost:8000/metrics
```

### Exemple de réponse attendue

```
# HELP app_http_requests_total Nombre total de requêtes HTTP
# TYPE app_http_requests_total counter
app_http_requests_total{method="GET",route="api_user",status="200"} 42

# HELP app_http_request_duration_seconds Durée des requêtes HTTP en secondes
# TYPE app_http_request_duration_seconds histogram
app_http_request_duration_seconds_bucket{method="GET",route="api_user",le="0.005"} 10
app_http_request_duration_seconds_bucket{method="GET",route="api_user",le="0.01"} 25
app_http_request_duration_seconds_bucket{method="GET",route="api_user",le="0.025"} 40
app_http_request_duration_seconds_bucket{method="GET",route="api_user",le="+Inf"} 42
app_http_request_duration_seconds_sum{method="GET",route="api_user"} 0.523
app_http_request_duration_seconds_count{method="GET",route="api_user"} 42

# HELP app_php_memory_usage_bytes Mémoire PHP utilisée en bytes
# TYPE app_php_memory_usage_bytes gauge
app_php_memory_usage_bytes 15728640
```

## 🔗 Configuration Prometheus

### Ajouter le job dans prometheus.yml

```yaml
scrape_configs:
  - job_name: 'party-planner-api'
    scrape_interval: 15s
    metrics_path: '/metrics'
    static_configs:
      - targets: ['api.party-planner.cg:443']
        labels:
          environment: 'production'
          service: 'api'
    scheme: 'https'
    # Si vous utilisez l'authentification IP via Nginx, pas besoin de credentials
    # Sinon, utilisez basic_auth ou bearer_token si vous ajoutez une authentification
```

### Configuration avec authentification (optionnel)

Si vous souhaitez ajouter une authentification basique :

```yaml
scrape_configs:
  - job_name: 'party-planner-api'
    scrape_interval: 15s
    metrics_path: '/metrics'
    static_configs:
      - targets: ['api.party-planner.cg:443']
    scheme: 'https'
    basic_auth:
      username: 'prometheus'
      password: 'your-secure-password'
```

## 📊 Dashboards Grafana

### Requêtes PromQL utiles

```promql
# Taux de requêtes par seconde
rate(app_http_requests_total[5m])

# Taux d'erreurs par seconde
rate(app_http_errors_total[5m])

# Pourcentage d'erreurs
rate(app_http_errors_total[5m]) / rate(app_http_requests_total[5m]) * 100

# Durée moyenne des requêtes
rate(app_http_request_duration_seconds_sum[5m]) / rate(app_http_request_duration_seconds_count[5m])

# P95 de la durée des requêtes
histogram_quantile(0.95, rate(app_http_request_duration_seconds_bucket[5m]))

# Mémoire PHP utilisée
app_php_memory_usage_bytes

# Mémoire PHP en MB
app_php_memory_usage_bytes / 1024 / 1024
```

## 🚀 Déploiement

1. **Mettre à jour les variables d'environnement** :
```bash
PROMETHEUS_ALLOWED_IPS=10.0.0.1,192.168.1.100
```

2. **Vérifier la configuration** :
```bash
php artisan config:cache
php artisan route:cache
```

3. **Tester l'endpoint** :
```bash
curl https://api.party-planner.cg/metrics
```

4. **Redémarrer l'application** si nécessaire

## 📝 Notes

- Les métriques sont stockées en mémoire par défaut (cache: null)
- Pour un setup multi-nœuds, configurez un cache partagé (Redis, Memcached)
- Le middleware collecte les métriques pour toutes les requêtes HTTP
- Les routes sont sanitizées pour éviter les caractères problématiques dans les labels

## 🔍 Dépannage

### L'endpoint /metrics retourne 403
- Vérifiez que l'IP de Prometheus est dans `PROMETHEUS_ALLOWED_IPS`
- Vérifiez la configuration Nginx si vous utilisez un reverse proxy

### Pas de métriques
- Vérifiez que le middleware est bien enregistré dans `bootstrap/app.php`
- Vérifiez que le service provider est enregistré dans `bootstrap/providers.php`
- Vérifiez les logs Laravel pour des erreurs

### Métriques vides
- Faites quelques requêtes à l'API pour générer des métriques
- Vérifiez que le cache est bien configuré (ou null pour in-memory)
