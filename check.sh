#!/bin/bash

# Script pour vérifier les logs et l'état du système sur le VPS

echo "=========================================="
echo "Vérification des logs Laravel"
echo "=========================================="
cd /home/alex/party-planner-backend

echo ""
echo "--- Dernières erreurs dans laravel.log ---"
tail -n 200 storage/logs/laravel.log | grep -A 10 -B 5 "ERROR\|Exception\|SQLSTATE" | tail -n 50

echo ""
echo "--- Dernières lignes du log ---"
tail -n 30 storage/logs/laravel.log

echo ""
echo "=========================================="
echo "État de la queue"
echo "=========================================="
php artisan queue:failed

echo ""
echo "=========================================="
echo "État des migrations"
echo "=========================================="
php artisan migrate:status | tail -n 20

echo ""
echo "=========================================="
echo "Test de connexion DB"
echo "=========================================="
php artisan tinker --execute="try { DB::connection()->getPdo(); echo '✓ Connexion DB OK\n'; } catch (Exception \$e) { echo '✗ Erreur DB: ' . \$e->getMessage() . '\n'; }"

echo ""
echo "=========================================="
echo "Vérification de la structure de la table jobs"
echo "=========================================="
docker exec -i party-planner-postgres psql -U party_app -d party-planner -c "\d jobs"

echo ""
echo "=========================================="
echo "Vérification des contraintes UUID"
echo "=========================================="
docker exec -i party-planner-postgres psql -U party_app -d party-planner -c "SELECT column_name, data_type, column_default FROM information_schema.columns WHERE table_name = 'jobs' AND column_name = 'id';"

