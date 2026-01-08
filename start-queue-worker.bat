@echo off
echo ========================================
echo   Party Planner - Queue Worker
echo ========================================
echo.
echo Demarrage du worker de queue...
echo Le worker va traiter les jobs en attente (emails, rappels, etc.)
echo.
echo Appuyez sur Ctrl+C pour arreter le worker
echo.
cd /d "%~dp0"
php artisan queue:work --queue=high,default,low --tries=3 --timeout=90
pause

