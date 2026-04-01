-- =============================================
-- NUMEROS D'URGENCE COMPLETS — 195 PAYS
-- Complète les numéros manquants après fix-continents-and-emergency.sql
-- Utilise AND emergency_number IS NULL pour ne pas écraser les existants
-- 2026-03-31
-- =============================================

-- =============================================
-- EUROPE (manquants — hors liste bulk 112 du fichier précédent)
-- =============================================
UPDATE country_directory SET emergency_number = '112' WHERE country_code IN ('BY','RU','XK','AM','AZ') AND emergency_number IS NULL;

-- =============================================
-- AMÉRIQUES (manquants)
-- =============================================
UPDATE country_directory SET emergency_number = '110' WHERE country_code = 'GT' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '118' WHERE country_code = 'NI' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '106' WHERE country_code = 'CU' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '119' WHERE country_code = 'JM' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '114' WHERE country_code = 'HT' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '115' WHERE country_code = 'SR' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '211' WHERE country_code = 'BB' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '919' WHERE country_code = 'BS' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '911' WHERE country_code IN ('BZ','DO','AG','GD','KN','GT','BO','EC','PY','UY','VE','TT') AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '999' WHERE country_code IN ('DM','LC','VC','GY') AND emergency_number IS NULL;

-- =============================================
-- AFRIQUE (manquants)
-- =============================================
UPDATE country_directory SET emergency_number = '112' WHERE country_code IN ('AO','BI','CD','RW','ST','TZ') AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '17' WHERE country_code IN ('BF','CF','TD','KM','CG','DJ','GN','GW','ML','MR','NE') AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '117' WHERE country_code IN ('BJ','GM','GW','CG','TG') AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '1730' WHERE country_code = 'GA' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '999' WHERE country_code IN ('BW','SZ','SC','SL','SO','SS','SD','UG','ZM','ZW','MU','NG') AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '191' WHERE country_code = 'GH' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '911' WHERE country_code IN ('ET','LR') AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '123' WHERE country_code = 'LS' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '1515' WHERE country_code = 'LY' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '997' WHERE country_code = 'MW' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '119' WHERE country_code = 'MZ' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '10111' WHERE country_code = 'NA' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '132' WHERE country_code = 'CV' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '199' WHERE country_code = 'NG' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '114' WHERE country_code IN ('GQ','ER') AND emergency_number IS NULL;

-- =============================================
-- ASIE (manquants)
-- =============================================
UPDATE country_directory SET emergency_number = '119' WHERE country_code IN ('AF','MV','LK','TW','KP') AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '999' WHERE country_code IN ('BH','QA','KW') AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '9999' WHERE country_code = 'OM' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '112' WHERE country_code IN ('BT','KZ','KG','SY','TJ','TL','TM','UZ','GE','AM','AZ') AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '104' WHERE country_code = 'IQ' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '115' WHERE country_code = 'IR' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '911' WHERE country_code IN ('JO','SA') AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '140' WHERE country_code = 'LB' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '102' WHERE country_code = 'MN' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '199' WHERE country_code = 'MM' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '100' WHERE country_code = 'NP' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '991' WHERE country_code = 'BN' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '101' WHERE country_code = 'PS' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '191' WHERE country_code = 'YE' AND emergency_number IS NULL;

-- =============================================
-- OCÉANIE (manquants)
-- =============================================
UPDATE country_directory SET emergency_number = '917' WHERE country_code = 'FJ' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '000' WHERE country_code = 'PG' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '994' WHERE country_code = 'WS' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '913' WHERE country_code = 'TO' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '112' WHERE country_code = 'VU' AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '999' WHERE country_code IN ('SB','KI','TV') AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '911' WHERE country_code IN ('MH','FM','PW') AND emergency_number IS NULL;
UPDATE country_directory SET emergency_number = '110' WHERE country_code = 'NR' AND emergency_number IS NULL;

-- =============================================
-- VÉRIFICATION
-- =============================================
SELECT COUNT(*) as pays_avec_urgence,
  COUNT(*) FILTER (WHERE emergency_number IS NOT NULL) as avec_numero
FROM (SELECT DISTINCT country_code, emergency_number FROM country_directory WHERE country_code != 'XX') t;
