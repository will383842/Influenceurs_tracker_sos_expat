#!/bin/bash
# Import SQL uniquement (rapide, ~30 sec)
# Le Wikidata est lancé séparément car il dure 2-3h
PGCMD="docker exec -i inf-postgres psql -U inf_user -d mission_control"

echo "[1/4] Ambassades françaises (si pas encore fait)..."
$PGCMD < scripts/annuaire/import-ambassades-data-gouv.sql

echo "[2/4] Continents + urgences de base..."
$PGCMD < scripts/annuaire/fix-continents-and-emergency.sql

echo "[3/4] Liens pratiques 50 pays (partie 1)..."
$PGCMD < scripts/annuaire/import-practical-links.sql

echo "[4/4] Liens pratiques 120 pays + urgences complètes (partie 2)..."
$PGCMD < scripts/annuaire/import-practical-links-monde-complet.sql
$PGCMD < scripts/annuaire/import-emergency-numbers-complet.sql

echo ""
echo "✅ SQL importé. Lance le Wikidata séparément:"
echo "   docker exec inf-app php artisan annuaire:import-wikidata --nationality=all --skip-existing --delay=1"
echo ""
echo "   Ou par priorité (les nationalités les plus demandées en 1er):"
echo "   docker exec inf-app php artisan annuaire:import-wikidata --nationality=FR,DE,GB,ES,IT,US,MA,DZ,TN,CN,JP,IN,BR,CA,AU --skip-existing"
