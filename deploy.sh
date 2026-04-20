#!/bin/bash
set -e

# Configuration
APP_DIR="/home/alex/party-planner-backend"
PHP_BIN="/usr/bin/php"

# Vérification et navigation vers le répertoire de l'application
if [ ! -d "$APP_DIR" ]; then
    echo "❌ Erreur: Le répertoire $APP_DIR n'existe pas"
    exit 1
fi

cd $APP_DIR

echo "🚀 Déploiement Laravel – Party Planner"
echo "======================================"

# Sécurisation Git
echo "🔐 Sécurisation Git"
git config --global --add safe.directory $APP_DIR || true

# Installation des dépendances
echo "📦 Nettoyage vendor si nécessaire"
# rm -rf vendor

echo "📦 Installation des dépendances Composer..."
# Désactivation temporaire de set -e pour gérer l'erreur du lock file
set +e
composer install --no-dev --optimize-autoloader 2>&1 | tee /tmp/composer_install.log
COMPOSER_EXIT_CODE=${PIPESTATUS[0]}
set -e

# Si l'installation a échoué à cause du lock file incompatible
if [ $COMPOSER_EXIT_CODE -ne 0 ]; then
    if grep -q "lock file does not contain a compatible set of packages" /tmp/composer_install.log; then
        echo "⚠️  Le lock file n'est pas compatible avec cette plateforme..."
        echo "🔄 Régénération du lock file..."
        # Sauvegarde du lock file actuel
        cp composer.lock composer.lock.backup 2>/dev/null || true
        # Régénération complète du lock file
        composer update --no-dev --no-interaction
        echo "📦 Installation des dépendances avec le nouveau lock file..."
        composer install --no-dev --optimize-autoloader
    else
        echo "❌ Erreur lors de l'installation des dépendances"
        exit 1
    fi
fi

# Correction des permissions
echo "🔐 Correction permissions vendor"
sudo chown -R www-data:www-data vendor

# Nettoyage du cache
echo "🧹 Nettoyage du cache..."
$PHP_BIN artisan optimize:clear

# Exécution des migrations
echo "🗄️ Exécution des migrations..."
$PHP_BIN artisan migrate --force

# Optimisation Laravel
echo "⚡ Optimisation Laravel..."
$PHP_BIN artisan optimize

# Redémarrage des workers de queue
echo "🔁 Redémarrage des workers de queue..."
$PHP_BIN artisan queue:restart

echo "✅ Déploiement terminé avec succès"
