#!/bin/bash

# Script de diagnostic pour vérifier la configuration API
# Usage: ./scripts/check-api-connection.sh

echo "=========================================="
echo "Diagnostic de connexion API"
echo "=========================================="
echo ""

# Couleurs pour l'affichage
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 1. Vérifier les variables d'environnement
echo "1. Vérification des variables d'environnement..."
echo "----------------------------------------"

if [ -f .env ]; then
    echo -e "${GREEN}✓${NC} Fichier .env trouvé"
    
    APP_URL=$(grep "^APP_URL=" .env | cut -d '=' -f2)
    FRONTEND_URL=$(grep "^FRONTEND_URL=" .env | cut -d '=' -f2)
    CORS_ORIGINS=$(grep "^CORS_ALLOWED_ORIGINS=" .env | cut -d '=' -f2)
    
    echo "  APP_URL: ${APP_URL:-'Non défini'}"
    echo "  FRONTEND_URL: ${FRONTEND_URL:-'Non défini'}"
    echo "  CORS_ALLOWED_ORIGINS: ${CORS_ORIGINS:-'Non défini'}"
    
    if [ -z "$CORS_ORIGINS" ]; then
        echo -e "${YELLOW}⚠${NC} CORS_ALLOWED_ORIGINS n'est pas défini"
    fi
else
    echo -e "${RED}✗${NC} Fichier .env non trouvé"
fi

echo ""
echo "2. Vérification de l'accessibilité du backend..."
echo "----------------------------------------"

# Tester l'URL de l'API
API_URL="${APP_URL:-http://localhost:8000}"
if [[ "$API_URL" == http* ]]; then
    echo "Test de connexion à: $API_URL/api"
    
    # Test HTTP
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$API_URL/api" 2>/dev/null || echo "000")
    
    if [ "$HTTP_CODE" = "000" ]; then
        echo -e "${RED}✗${NC} Impossible de se connecter au backend"
        echo "  Vérifiez que le serveur est démarré et accessible"
    elif [ "$HTTP_CODE" = "401" ] || [ "$HTTP_CODE" = "404" ] || [ "$HTTP_CODE" = "200" ]; then
        echo -e "${GREEN}✓${NC} Backend accessible (HTTP $HTTP_CODE)"
    else
        echo -e "${YELLOW}⚠${NC} Réponse inattendue (HTTP $HTTP_CODE)"
    fi
    
    # Test CORS
    echo ""
    echo "Test CORS..."
    CORS_HEADERS=$(curl -s -I -X OPTIONS \
        -H "Origin: https://party-planner.cg" \
        -H "Access-Control-Request-Method: GET" \
        -H "Access-Control-Request-Headers: authorization" \
        "$API_URL/api/user" 2>/dev/null)
    
    if echo "$CORS_HEADERS" | grep -q "Access-Control-Allow-Origin"; then
        echo -e "${GREEN}✓${NC} CORS configuré"
        echo "$CORS_HEADERS" | grep -i "access-control"
    else
        echo -e "${RED}✗${NC} CORS non configuré ou incorrect"
    fi
else
    echo -e "${YELLOW}⚠${NC} APP_URL n'est pas une URL valide"
fi

echo ""
echo "3. Vérification des ports ouverts..."
echo "----------------------------------------"

# Vérifier si le port 8000 est en écoute
if command -v netstat &> /dev/null; then
    PORT_8000=$(netstat -tuln 2>/dev/null | grep ":8000 " || echo "")
elif command -v ss &> /dev/null; then
    PORT_8000=$(ss -tuln 2>/dev/null | grep ":8000 " || echo "")
fi

if [ -n "$PORT_8000" ]; then
    echo -e "${GREEN}✓${NC} Port 8000 en écoute"
    echo "  $PORT_8000"
else
    echo -e "${RED}✗${NC} Port 8000 non en écoute"
fi

# Vérifier le port 443 (HTTPS)
if command -v netstat &> /dev/null; then
    PORT_443=$(netstat -tuln 2>/dev/null | grep ":443 " || echo "")
elif command -v ss &> /dev/null; then
    PORT_443=$(ss -tuln 2>/dev/null | grep ":443 " || echo "")
fi

if [ -n "$PORT_443" ]; then
    echo -e "${GREEN}✓${NC} Port 443 (HTTPS) en écoute"
else
    echo -e "${YELLOW}⚠${NC} Port 443 (HTTPS) non en écoute"
fi

echo ""
echo "4. Vérification du firewall..."
echo "----------------------------------------"

if command -v ufw &> /dev/null; then
    UFW_STATUS=$(sudo ufw status 2>/dev/null | grep -i "active" || echo "")
    if [ -n "$UFW_STATUS" ]; then
        echo "État UFW: $UFW_STATUS"
        PORT_8000_ALLOWED=$(sudo ufw status | grep "8000" || echo "")
        if [ -n "$PORT_8000_ALLOWED" ]; then
            echo -e "${GREEN}✓${NC} Port 8000 autorisé dans UFW"
        else
            echo -e "${YELLOW}⚠${NC} Port 8000 peut ne pas être autorisé dans UFW"
        fi
    fi
fi

echo ""
echo "5. Vérification de la configuration Laravel..."
echo "----------------------------------------"

if command -v php &> /dev/null; then
    echo "Vérification du cache de configuration..."
    php artisan config:show cors.allowed_origins 2>/dev/null || echo "Impossible de lire la config CORS"
    
    echo ""
    echo "Vérification des routes API..."
    php artisan route:list --path=api 2>/dev/null | head -5 || echo "Impossible de lister les routes"
fi

echo ""
echo "=========================================="
echo "Diagnostic terminé"
echo "=========================================="
