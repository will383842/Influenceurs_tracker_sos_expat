# Import Wikidata — Ambassades Mondiales

## Ce qui a été implémenté

### Architecture
- **`nationality_code`** : nationalité de l'expatrié (dont l'ambassade)
- **`country_code`** : pays hôte (où l'expat vit)
- **`translations`** : JSON multilingue `{"en":{"title":"..."}, "es":{...}, "ar":{...}, "ch":{...}, "hi":{...}, "ru":{...}}`

### Exemple
| country_code | nationality_code | title | translations |
|---|---|---|---|
| TH | DE | Botschaft der Bundesrepublik... | `{"en":{"title":"German Embassy Bangkok"},"fr":{"title":"Ambassade d'Allemagne Bangkok"}}` |
| FR | MA | Ambassade du Maroc en France | `{"ar":{"title":"سفارة المغرب في فرنسا"},"en":{"title":"Embassy of Morocco in France"}}` |

### 9 langues supportées
`fr` · `en` · `es` · `ar` · `de` · `pt` · `ch` (chinois, code Wikidata: `zh`) · `hi` · `ru`

---

## Commandes d'import

### 1. Migrer le schéma (une seule fois)
```bash
cd laravel-api
php artisan migrate
# Ajoute : nationality_code, nationality_name, translations
# Met à jour : ambassades existantes → nationality_code = 'FR'
```

### 2. Importer une nationalité
```bash
php artisan annuaire:import-wikidata --nationality=DE
php artisan annuaire:import-wikidata --nationality=MA
php artisan annuaire:import-wikidata --nationality=FR   # réimporter avec traductions
```

### 3. Importer un groupe de nationalités
```bash
# Les plus demandées sur SOS Expat :
php artisan annuaire:import-wikidata --nationality=FR,DE,GB,ES,IT,BE,CH,MA,DZ,TN

# Toutes les nationalités (195 pays, ~2-3h, 1 req/sec Wikidata)
php artisan annuaire:import-wikidata --nationality=all --skip-existing
```

### 4. Simulation sans insérer
```bash
php artisan annuaire:import-wikidata --nationality=DE --dry-run
```

### 5. Réimporter (mise à jour des données)
```bash
# Reimporte même si déjà présent (sans --skip-existing)
php artisan annuaire:import-wikidata --nationality=FR
```

---

## Ordre recommandé d'import (par volume d'expatriés)

### Priorité 1 — Communautés principales SOS Expat
```bash
php artisan annuaire:import-wikidata --nationality=FR,MA,DZ,TN,SN,CM
php artisan annuaire:import-wikidata --nationality=DE,GB,BE,CH,IT,ES,PT,NL
php artisan annuaire:import-wikidata --nationality=US,CA,AU,JP,CN,IN,BR
```

### Priorité 2 — Europe + Moyen-Orient
```bash
php artisan annuaire:import-wikidata --nationality=PL,RO,UA,TR,LB,EG,SA,AE
```

### Priorité 3 — Reste du monde
```bash
php artisan annuaire:import-wikidata --nationality=all --skip-existing --delay=2
```

---

## Données attendues par nationalité

| Nationalité | Ambassades Wikidata (estim.) |
|---|---|
| France (FR) | ~160 |
| Allemagne (DE) | ~150 |
| USA (US) | ~160 |
| Royaume-Uni (GB) | ~150 |
| Chine (CN) | ~130 |
| Russie (RU) | ~120 |
| Maroc (MA) | ~90 |
| **Total (toutes nat.)** | **~15 000 - 38 000** |

---

## Qualité des données Wikidata

### Points forts
- Coordonnées GPS (latitude/longitude) pour ~60% des ambassades
- Site officiel (url) pour ~70%
- Labels multilingues (selon disponibilité Wikipedia)

### Limites
- Emails/téléphones rarement dans Wikidata (à enrichir manuellement)
- Certains pays moins bien couverts (petites nations)
- Données parfois obsolètes → vérifier les URLs importantes

### Enrichissement complémentaire
Pour enrichir les données manquantes (email, téléphone, horaires) :
```bash
# À venir : commande d'enrichissement via scraping ou Claude API
php artisan annuaire:enrich-embassies --nationality=DE --field=email,phone
```
