#!/bin/bash
# =============================================================
# SCRIPT D'INSTALLATION — AI Mail Triage
# =============================================================
# 
# ÉTAPES :
# 1. Ouvrir le Terminal
# 2. cd /Applications/MAMP/htdocs
# 3. composer create-project laravel/laravel IAmail
# 4. cd IAmail
# 5. Copier TOUS les fichiers de ce zip DANS le dossier IAmail
#    (écraser les fichiers existants quand demandé)
# 6. composer install
# 7. cp .env.example .env  (puis éditer les clés API)
# 8. php artisan key:generate
# 9. Créer la base de données "ia_mail_triage" dans phpMyAdmin
# 10. php artisan migrate
# 11. php artisan db:seed
# 12. php artisan serve
# 13. Ouvrir http://localhost:8000
#     → Login : admin@aimtriage.be / password
#
# =============================================================

echo "============================================"
echo "  AI Mail Triage — Installation"
echo "============================================"
echo ""

# Vérifier qu'on est dans un projet Laravel
if [ ! -f "artisan" ]; then
    echo "❌ ERREUR : Ce script doit être lancé depuis la racine d'un projet Laravel."
    echo ""
    echo "Étapes :"
    echo "  1. cd /Applications/MAMP/htdocs"
    echo "  2. composer create-project laravel/laravel IAmail"
    echo "  3. cd IAmail"
    echo "  4. Copier les fichiers de ce zip dans ce dossier"
    echo "  5. Relancer ce script : bash install.sh"
    exit 1
fi

echo "✓ Projet Laravel détecté"
echo ""

# Installer les dépendances
echo "→ Installation des dépendances..."
composer install --no-interaction --quiet
echo "✓ Dépendances installées"

# Générer la clé
if ! grep -q "APP_KEY=base64" .env 2>/dev/null; then
    if [ ! -f ".env" ]; then
        cp .env.example .env
        echo "✓ Fichier .env créé (pensez à le configurer)"
    fi
    php artisan key:generate --quiet
    echo "✓ Clé d'application générée"
fi

# Migrations
echo ""
echo "→ Lancement des migrations..."
php artisan migrate --force
echo "✓ Base de données créée"

# Seeders
echo "→ Lancement des seeders..."
php artisan db:seed --force
echo "✓ Données initiales insérées"

# Cache
php artisan config:clear --quiet
php artisan route:clear --quiet
php artisan view:clear --quiet

echo ""
echo "============================================"
echo "  ✅ Installation terminée !"
echo "============================================"
echo ""
echo "  → php artisan serve"
echo "  → http://localhost:8000"
echo "  → Login : admin@aimtriage.be / password"
echo ""
echo "  ⚠️  N'oubliez pas de configurer vos clés API"
echo "     dans le fichier .env :"
echo "     GEMINI_API_KEY=..."
echo "     MISTRAL_API_KEY=..."
echo ""
