# Script pour redémarrer Laravel avec cache clear

Write-Host "🔧 Nettoyage du cache Laravel..." -ForegroundColor Cyan

# Clear tous les caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear

Write-Host "✅ Cache nettoyé!" -ForegroundColor Green
Write-Host ""
Write-Host "🚀 Redémarrez maintenant le serveur avec:" -ForegroundColor Yellow
Write-Host "   php artisan serve" -ForegroundColor White
Write-Host ""
Write-Host "Puis testez la connexion depuis le frontend!" -ForegroundColor Cyan
