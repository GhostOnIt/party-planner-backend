# Party Planner - Queue Worker Script
# Ce script demarre le worker de queue Laravel

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Party Planner - Queue Worker" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Demarrage du worker de queue..." -ForegroundColor Yellow
Write-Host "Le worker va traiter les jobs en attente (emails, rappels, etc.)" -ForegroundColor Yellow
Write-Host ""
Write-Host "Appuyez sur Ctrl+C pour arreter le worker" -ForegroundColor Yellow
Write-Host ""

# Changer vers le repertoire du backend
Set-Location $PSScriptRoot

# Demarrer le worker
php artisan queue:work --queue=high,default,low --tries=3 --timeout=90

