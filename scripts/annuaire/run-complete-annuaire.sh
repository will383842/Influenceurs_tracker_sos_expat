#!/bin/bash
# =============================================
# SCRIPT COMPLET — ANNUAIRE MONDE ENTIER
# Lance tous les imports dans le bon ordre
# Exécuter depuis la racine du projet:
#   bash scripts/annuaire/run-complete-annuaire.sh
# =============================================

set -e
PGCMD="docker exec -i inf-postgres psql -U inf_user -d mission_control"
ARTISAN="docker exec inf-app php artisan"

echo "=== [1/6] Vérification que Docker tourne ==="
docker compose ps | grep "inf-postgres" | grep -q "healthy" || { echo "ERREUR: inf-postgres n'est pas healthy. Lance: docker compose up -d"; exit 1; }
docker compose ps | grep "inf-app" | grep -q "running\|Up" || { echo "ERREUR: inf-app n'est pas running. Lance: docker compose up -d"; exit 1; }

echo ""
echo "=== [2/6] Numéros d'urgence complets (195 pays) ==="
$PGCMD < scripts/annuaire/import-emergency-numbers-complet.sql
echo "✓ Numéros d'urgence importés"

echo ""
echo "=== [3/6] Liens pratiques — 120 pays manquants ==="
$PGCMD < scripts/annuaire/import-practical-links-monde-complet.sql
echo "✓ Liens pratiques monde complet importés"

echo ""
echo "=== [4/6] Import Wikidata — Ambassades TOUTES nationalités ==="
echo "   ATTENTION: cet import dure ~2-3h pour les 195 nationalités"
echo "   Utilise --skip-existing pour ne pas écraser les ambassades FR déjà importées"
echo ""
echo "   Lancement pour toutes les 195 nationalités..."
$ARTISAN annuaire:import-wikidata --nationality=all --skip-existing --delay=1
echo "✓ Import Wikidata terminé"

echo ""
echo "=== [5/6] Import numéros d'urgence via artisan ==="
# Méthode alternative via le job si le script SQL échoue
# $ARTISAN annuaire:import-emergency

echo ""
echo "=== [6/6] Vérification finale ==="
$PGCMD << 'EOF'
SELECT
  continent,
  COUNT(DISTINCT country_code) as pays,
  COUNT(*) FILTER (WHERE category = 'ambassade') as ambassades,
  COUNT(*) FILTER (WHERE category != 'ambassade') as liens_pratiques,
  COUNT(*) FILTER (WHERE emergency_number IS NOT NULL) as avec_urgence
FROM country_directory
WHERE is_active = true AND country_code != 'XX'
GROUP BY continent
ORDER BY continent;

SELECT
  'TOTAL GLOBAL' as label,
  COUNT(*) as total_entrees,
  COUNT(DISTINCT country_code) as pays_couverts,
  COUNT(DISTINCT nationality_code) as nationalites_ambassades,
  COUNT(*) FILTER (WHERE category = 'ambassade') as total_ambassades,
  COUNT(*) FILTER (WHERE category != 'ambassade') as total_liens_pratiques,
  COUNT(*) FILTER (WHERE emergency_number IS NOT NULL) as pays_avec_urgence
FROM country_directory
WHERE is_active = true;
EOF

echo ""
echo "=========================================="
echo "✅ ANNUAIRE COMPLET — Import terminé !"
echo "=========================================="
