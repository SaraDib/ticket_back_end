# Script pour créer la base de données MySQL
Write-Host "🗄️  Création de la base de données MySQL..." -ForegroundColor Cyan

try {
    # Vérifier si mysql est accessible
    $mysqlTest = mysql --version 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Host "❌ MySQL n'est pas accessible. Assurez-vous que MySQL est installé et dans le PATH." -ForegroundColor Red
        exit 1
    }

    Write-Host "✅ MySQL trouvé : $($mysqlTest)" -ForegroundColor Green

    # Créer la base de données
    $createDbCommand = "CREATE DATABASE IF NOT EXISTS ticket_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    
    Write-Host "`n📝 Exécution de la commande SQL..." -ForegroundColor Yellow
    $result = mysql -u root -e $createDbCommand 2>&1
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "✅ Base de données 'ticket_management' créée avec succès!" -ForegroundColor Green
    } else {
        Write-Host "⚠️  Une erreur s'est produite. Si la base de données existe déjà, ce n'est pas grave." -ForegroundColor Yellow
        Write-Host "   Erreur : $result" -ForegroundColor Yellow
    }

} catch {
    Write-Host "❌ Erreur lors de la création de la base de données." -ForegroundColor Red
    Write-Host "   Vous pouvez la créer manuellement avec :" -ForegroundColor Yellow
    Write-Host "   CREATE DATABASE ticket_management;" -ForegroundColor Yellow
}

Write-Host "`n✅ Étape terminée!" -ForegroundColor Green
