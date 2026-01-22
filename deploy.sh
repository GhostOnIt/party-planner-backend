#!/bin/bash
set -e

APP_DIR="/home/alex/party-planner-backend"
PHP_BIN="/usr/bin/php"

cd $APP_DIR

echo "🚀 Déploiement Laravel – Party Planner"
echo "======================================"

echo "🔐 Sécurisation Git"
git config --global --add safe.directory $APP_DIR || true

echo "📦 Nettoyage vendor si nécessaire"
rm -rf vendor

echo "📦 Installation des dépendances Composer..."
composer install --no-dev --optimize-autoloader

echo "🔐 Correction permissions vendor"
sudo chown -R www-data:www-data vendor

echo "🧹 Nettoyage du cache..."
$PHP_BIN artisan optimize:clear

echo "🗄️ Exécution des migrations..."
$PHP_BIN artisan migrate --force

echo "⚡ Optimisation Laravel..."
$PHP_BIN artisan optimize

echo "🔁 Redémarrage des workers de queue..."
$PHP_BIN artisan queue:restart

echo "Exécution des migrations"
$PHP_BIN artisan migrate

echo "✅ Déploiement terminé avec succès"

