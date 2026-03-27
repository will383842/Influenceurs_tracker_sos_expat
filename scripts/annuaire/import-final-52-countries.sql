-- =============================================
-- FINAL 52 COUNTRIES — Complete remaining gaps
-- Fixes continent mapping + adds verified immigration/practical links
-- Execute APRES import-remaining-countries.sql
-- =============================================

-- =============================================
-- FIX CONTINENT MAPPING for all "autre" countries
-- =============================================
-- These are countries with shared embassies (BS=Panama covers Bahamas, etc.)
-- They were not assigned a continent because their ISO code points to a shared embassy

-- Africa
UPDATE country_directory SET continent = 'afrique' WHERE country_code IN ('AO','BI','BJ','CF','CG','DJ','ER','GM','GN','GQ','GW','KM','LR','LS','LY','ML','MR','MW','NE','SD','SL','SO','SS','ST','TD','TG','ZM') AND continent = 'global';

-- Asia
UPDATE country_directory SET continent = 'asie' WHERE country_code IN ('AF','AM','BT','KP','SY','TJ','TL','TM','YE') AND continent = 'global';

-- Americas
UPDATE country_directory SET continent = 'amerique-nord' WHERE country_code IN ('AG','BB','BS','BZ','DM','GY','HN','KN','LC','VC') AND continent = 'global';
UPDATE country_directory SET continent = 'amerique-sud' WHERE country_code IN ('SR') AND continent = 'global';

-- Oceanie
UPDATE country_directory SET continent = 'oceanie' WHERE country_code IN ('CK','PW','SB','TO','VU','WS') AND continent = 'global';

-- Europe
UPDATE country_directory SET continent = 'europe' WHERE country_code IN ('MC','SH','XK') AND continent = 'global';

-- Fix Israel special code
UPDATE country_directory SET continent = 'asie' WHERE country_code = 'ILPS' AND continent = 'global';

-- =============================================
-- AFRICA — Remaining countries
-- =============================================
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
-- Angola
('AO','Angola','angola','afrique','immigration','evisa','Angola SME e-Visa','https://www.smevisa.gov.ao/','smevisa.gov.ao','Portail officiel e-Visa du Service Migration et Etrangers',90,true,'e-Visa Angola','noopener'),
-- Benin
('BJ','Benin','benin','afrique','immigration','evisa','Benin e-Visa','https://evisa.bj','evisa.bj','Plateforme officielle e-Visa Benin',85,true,'e-Visa Benin','noopener'),
-- Burundi
('BI','Burundi','burundi','afrique','immigration',NULL,'Burundi — MFA Visa Info','https://www.mae.gov.bi/','mae.gov.bi','Ministere Affaires etrangeres: visa',70,true,'visa Burundi','noopener'),
-- Congo Brazzaville
('CG','Congo','congo','afrique','immigration',NULL,'Congo — Direction Immigration','https://www.dgpn-congo.org/','dgpn-congo.org','Direction generale police nationale: immigration',65,true,'immigration Congo','noopener'),
-- Djibouti
('DJ','Djibouti','djibouti','afrique','immigration','evisa','Djibouti e-Visa','http://www.evisa.gouv.dj/','evisa.gouv.dj','Portail officiel e-Visa Djibouti (depuis 2018)',85,true,'e-Visa Djibouti','noopener'),
-- Guinee
('GN','Guinee','guinee','afrique','immigration',NULL,'Guinee — Direction Police Etrangers','https://www.mae.gov.gn/','mae.gov.gn','Ministere Affaires etrangeres Guinee Conakry',65,true,'visa Guinee','noopener'),
-- Liberia
('LR','Liberia','liberia','afrique','immigration',NULL,'LIS — Liberia Immigration Service','https://lis.gov.lr/','lis.gov.lr','Service d immigration du Liberia: visa on arrival',80,true,'immigration Liberia','noopener'),
-- Mali
('ML','Mali','mali','afrique','immigration',NULL,'Mali — Direction Police Etrangers','https://www.diplomatie.gouv.ml/','diplomatie.gouv.ml','Ministere Affaires etrangeres et cooperation',65,true,'visa Mali','noopener'),
-- Mauritanie
('MR','Mauritanie','mauritanie','afrique','immigration',NULL,'Mauritanie — MFA','https://www.diplomatie.gov.mr/','diplomatie.gov.mr','Ministere Affaires etrangeres Mauritanie',65,true,'visa Mauritanie','noopener'),
-- Niger
('NE','Niger','niger','afrique','immigration',NULL,'Niger — MFA','https://www.mae.ne/','mae.ne','Ministere Affaires etrangeres Niger',60,true,'visa Niger','noopener'),
-- Sierra Leone
('SL','Sierra Leone','sierra-leone','afrique','immigration','evisa','Sierra Leone e-Visa','https://www.evisa.sl/','evisa.sl','Portail officiel e-Visa Sierra Leone',80,true,'e-Visa Sierra Leone','noopener'),
-- Tchad
('TD','Tchad','tchad','afrique','immigration',NULL,'Tchad — MFA','https://www.diplomatie.gouv.td/','diplomatie.gouv.td','Ministere Affaires etrangeres Tchad',60,true,'visa Tchad','noopener'),
-- Togo
('TG','Togo','togo','afrique','immigration',NULL,'Togo — e-Visa on arrival','https://www.republicoftogo.com/','republicoftogo.com','Portail officiel du Togo: visa electronique a l arrivee',70,true,'visa Togo','noopener'),
-- Zambie
('ZM','Zambie','zambie','afrique','immigration',NULL,'Zambia Immigration eServices','https://eservices.zambiaimmigration.gov.zm/','zambiaimmigration.gov.zm','Portail e-services immigration Zambie: e-Visa, permis',85,true,'immigration Zambie','noopener'),
-- Comores
('KM','Comores','comores','afrique','immigration',NULL,'Comores — Visa a l arrivee','https://www.diplomatie.gouv.km/','diplomatie.gouv.km','Visa delivre a l arrivee pour la plupart des nationalites',60,true,'visa Comores','noopener'),
-- Guinee Equatoriale
('GQ','Guinee Equatoriale','guinee-equatoriale','afrique','immigration',NULL,'Guinee Equatoriale — MFA','https://www.guineaecuatorialpress.com/','guineaecuatorialpress.com','Portail officiel: informations visa',55,false,'visa Guinee Equatoriale','noopener nofollow'),
-- Guinee-Bissau
('GW','Guinee-Bissau','guinee-bissau','afrique','immigration',NULL,'Guinee-Bissau — Visa a l arrivee','https://www.gov.gw/','gov.gw','Gouvernement: visa electronique ou a l arrivee',55,true,'visa Guinee-Bissau','noopener'),

-- =============================================
-- AMERICAS — Remaining Caribbean + Central
-- =============================================
-- Antigua et Barbuda
('AG','Antigua-et-Barbuda','antigua-et-barbuda','amerique-nord','immigration',NULL,'Antigua Immigration Department','https://immigration.gov.ag/','immigration.gov.ag','Department of Immigration: visa, entree, residence',80,true,'immigration Antigua','noopener'),
-- Barbade
('BB','Barbade','barbade','amerique-nord','immigration',NULL,'Barbados Immigration','https://immigration.gov.bb/','immigration.gov.bb','Immigration Department Barbados',80,true,'immigration Barbade','noopener'),
-- Jamaique
('JM','Jamaique','jamaique','amerique-nord','immigration',NULL,'PICA — Jamaica Immigration','https://www.pica.gov.jm/','pica.gov.jm','Passport Immigration Citizenship Agency Jamaica',85,true,'PICA Jamaica','noopener'),
-- Guyana
('GY','Guyana','guyana','amerique-nord','immigration',NULL,'Guyana Immigration','https://www.moha.gov.gy/','moha.gov.gy','Ministry of Home Affairs: immigration et visa',70,true,'immigration Guyana','noopener'),

-- =============================================
-- ASIA — Remaining
-- =============================================
-- Armenie
('AM','Armenie','armenie','asie','immigration','evisa','Armenia e-Visa','https://evisa.mfa.am/','evisa.mfa.am','Portail officiel e-Visa Ministere Affaires etrangeres Armenie',85,true,'e-Visa Armenie','noopener'),
-- Tadjikistan
('TJ','Tadjikistan','tadjikistan','asie','immigration','evisa','Tajikistan e-Visa','https://www.evisa.tj/','evisa.tj','Portail officiel e-Visa Tadjikistan',80,true,'e-Visa Tadjikistan','noopener'),

-- =============================================
-- SPECIAL CASES: Countries with VERY limited online presence
-- For these, we add a note explaining the situation
-- =============================================
-- Afghanistan (conflit actif, pas de portail officiel fonctionnel)
('AF','Afghanistan','afghanistan','asie','immigration',NULL,'Afghanistan — Situation speciale','https://af.ambafrance.org/','af.ambafrance.org','Pays en conflit: contacter l ambassade de France. Pas de portail immigration fonctionnel.',50,true,'ambassade France Afghanistan','noopener'),
-- Soudan
('SD','Soudan','soudan','afrique','immigration',NULL,'Soudan — Situation speciale','https://sd.ambafrance.org/','sd.ambafrance.org','Pays en conflit: contacter l ambassade de France pour toute information.',50,true,'ambassade France Soudan','noopener'),
-- Soudan du Sud
('SS','Soudan du Sud','soudan-du-sud','afrique','immigration',NULL,'Soudan du Sud — Situation speciale','https://ss.ambafrance.org/','ss.ambafrance.org','Pays en conflit: contacter l ambassade de France.',50,true,'ambassade France Soudan du Sud','noopener'),
-- Syrie
('SY','Syrie','syrie','asie','immigration',NULL,'Syrie — Situation speciale','https://sy.ambafrance.org/','sy.ambafrance.org','Pays en conflit: contacter l ambassade de France.',50,true,'ambassade France Syrie','noopener'),
-- Yemen
('YE','Yemen','yemen','asie','immigration',NULL,'Yemen — Situation speciale','https://ye.ambafrance.org/','ye.ambafrance.org','Pays en conflit: contacter l ambassade de France.',50,true,'ambassade France Yemen','noopener'),
-- Erythree
('ER','Erythree','erythree','afrique','immigration',NULL,'Erythree — Visa par ambassade','https://er.ambafrance.org/','er.ambafrance.org','Visa uniquement aupres des ambassades erythreennes.',50,true,'visa Erythree','noopener'),
-- Libye
('LY','Libye','libye','afrique','immigration',NULL,'Libye — Situation speciale','https://ly.ambafrance.org/','ly.ambafrance.org','Situation securitaire instable: contacter l ambassade.',50,true,'ambassade France Libye','noopener'),
-- Coree du Nord
('KP','Coree du Nord','coree-du-nord','asie','immigration',NULL,'Coree du Nord — Acces restreint','https://www.diplomatie.gouv.fr/fr/conseils-aux-voyageurs/conseils-par-pays-destination/coree-du-nord/','diplomatie.gouv.fr','Voyage fortement deconseille. Visa uniquement via agences specialisees.',40,true,'Coree du Nord conseils','noopener'),
-- Turkmenistan
('TM','Turkmenistan','turkmenistan','asie','immigration',NULL,'Turkmenistan — Visa par ambassade','https://www.mfa.gov.tm/en','mfa.gov.tm','Ministere Affaires etrangeres: visa obligatoire par ambassade',65,true,'visa Turkmenistan','noopener'),

-- =============================================
-- MICRO-STATES & SHARED EMBASSIES
-- For countries where the French embassy covers them from another country,
-- we link to the covering embassy + the country's own government if it exists
-- =============================================
-- Sainte-Lucie (couverte par ambassade a Castries)
('LC','Sainte-Lucie','sainte-lucie','amerique-nord','immigration',NULL,'Saint Lucia Immigration','https://www.govt.lc/','govt.lc','Gouvernement de Sainte-Lucie: entree sans visa pour UE (6 semaines)',65,true,'immigration Sainte-Lucie','noopener'),
-- Cap-Vert (deja couvert)
-- Seychelles (deja couvert)
-- Ile Maurice (deja couvert)
-- Timor-Leste (couvert par ambassade Jakarta)
('TL','Timor-Leste','timor-leste','asie','immigration',NULL,'Timor-Leste Immigration','https://migracao.gov.tl/','migracao.gov.tl','Service migration et asile Timor-Leste',65,true,'immigration Timor-Leste','noopener'),
-- Vanuatu
('VU','Vanuatu','vanuatu','oceanie','immigration',NULL,'Vanuatu Immigration','https://immigration.gov.vu/','immigration.gov.vu','Department of Immigration Vanuatu',65,true,'immigration Vanuatu','noopener');


-- =============================================
-- VERIFICATION FINALE
-- =============================================
SELECT
  continent,
  COUNT(DISTINCT country_code) as pays,
  COUNT(*) as liens_total,
  COUNT(*) FILTER (WHERE category = 'ambassade') as ambassades,
  COUNT(*) FILTER (WHERE category != 'ambassade') as pratiques
FROM country_directory
WHERE is_active = true AND country_code != 'XX'
GROUP BY continent
ORDER BY pays DESC;

SELECT
  '=== BILAN FINAL ===' as label,
  COUNT(DISTINCT country_code) FILTER (WHERE country_code != 'XX') as pays_total,
  COUNT(DISTINCT country_code) FILTER (WHERE category = 'ambassade' AND country_code != 'XX') as avec_ambassade,
  COUNT(DISTINCT country_code) FILTER (WHERE category != 'ambassade' AND country_code != 'XX') as avec_pratique,
  COUNT(*) as liens_total
FROM country_directory WHERE is_active = true;
