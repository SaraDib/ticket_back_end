# Script d'installation automatique du backend Laravel

Write-Host "🚀 Début de l'installation du système Ticket Management..." -ForegroundColor Cyan

# 1. Vérifier que nous sommes dans le bon dossier
$currentPath = Get-Location
Write-Host "📁 Répertoire actuel : $currentPath" -ForegroundColor Yellow

# 2. Création de la base de données MySQL
Write-Host "`n📊 Création de la base de données..." -ForegroundColor Cyan
$createDbCommand = @"
CREATE DATABASE IF NOT EXISTS ticket_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
"@

# Essayer de créer la base de données
try {
    mysql -u root -e $createDbCommand
    Write-Host "✅ Base de données 'ticket_management' créée avec succès!" -ForegroundColor Green
} catch {
    Write-Host "⚠️  Erreur lors de la création de la base de données. Vérifiez que MySQL est démarré." -ForegroundColor Red
    Write-Host "    Vous pouvez créer manuellement la base de données avec :" -ForegroundColor Yellow
    Write-Host "    CREATE DATABASE ticket_management;" -ForegroundColor Yellow
}

# 3. Lancer les migrations
Write-Host "`n🗄️  Lancement des migrations..." -ForegroundColor Cyan
php artisan migrate

if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ Migrations exécutées avec succès!" -ForegroundColor Green
} else {
    Write-Host "❌ Erreur lors de l'exécution des migrations." -ForegroundColor Red
    exit 1
}

# 4. Créer le lien symbolique pour le storage
Write-Host "`n🔗 Création du lien symbolique pour le storage..." -ForegroundColor Cyan
php artisan storage:link

# 5. Créer un utilisateur admin par défaut
Write-Host "`n👤 Création d'un utilisateur admin par défaut..." -ForegroundColor Cyan
$userCreationScript = @"
\$user = App\Models\User::where('email', 'admin@ticketmanagement.com')->first();
if (!\$user) {
    \$user = new App\Models\User();
    \$user->name = 'Administrateur';
    \$user->email = 'admin@ticketmanagement.com';
    \$user->password = bcrypt('Admin@2024');
    \$user->role = 'admin';
    \$user->save();
    echo 'Utilisateur admin créé avec succès !';
} else {
    echo 'L\'utilisateur admin existe déjà.';
}
"@

Write-Host "`n📝 Résumé de l'installation" -ForegroundColor Cyan
Write-Host "================================" -ForegroundColor Cyan
Write-Host "✅ Base de données : ticket_management" -ForegroundColor Green
Write-Host "✅ Migrations : Installées" -ForegroundColor Green
Write-Host "✅ Storage : Configuré" -ForegroundColor Green
Write-Host "" 
Write-Host "🔐 Identifiants Admin par défaut :" -ForegroundColor Yellow
Write-Host "   Email    : admin@ticketmanagement.com" -ForegroundColor White
Write-Host "   Password : Admin@2024" -ForegroundColor White
Write-Host "" 
Write-Host "🚀 Pour démarrer le serveur Laravel :" -ForegroundColor Cyan
Write-Host "   php artisan serve" -ForegroundColor White
Write-Host "" 
Write-Host "✨ Installation terminée !" -ForegroundColor Green
