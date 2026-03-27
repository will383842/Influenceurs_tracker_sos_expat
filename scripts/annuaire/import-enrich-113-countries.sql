-- =============================================
-- ENRICHIR LES 113 PAYS PAUVRES
-- Ajoute emploi, logement, telecom, sante via des portails REGIONAUX verifies
-- Strategie: utiliser les reseaux multi-pays (Jobberman, BrighterMonday, PropertyGuru, etc.)
-- Execute APRES import-final-52-countries.sql
-- =============================================

-- =============================================
-- EMPLOI — Portails regionaux couvrant plusieurs pays
-- =============================================

-- Jobberman (Nigeria + Ghana)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute)
SELECT code, name, slug, 'afrique', 'emploi', NULL, 'Jobberman ' || name, url, 'jobberman.com', 'Portail emploi ' || name, 75, false, 'Jobberman ' || name, 'noopener nofollow'
FROM (VALUES
  ('GH', 'Ghana', 'ghana', 'https://www.jobberman.com.gh/')
) AS t(code, name, slug, url)
ON CONFLICT (country_code, url) DO NOTHING;

-- BrighterMonday (Kenya, Uganda, Tanzania)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute)
SELECT code, name, slug, 'afrique', 'emploi', NULL, 'BrighterMonday ' || name, url, domain, 'Portail emploi ' || name, 75, false, 'BrighterMonday ' || name, 'noopener nofollow'
FROM (VALUES
  ('UG', 'Ouganda', 'ouganda', 'https://www.brightermonday.co.ug/', 'brightermonday.co.ug'),
  ('TZ', 'Tanzanie', 'tanzanie', 'https://www.brightermonday.co.tz/', 'brightermonday.co.tz')
) AS t(code, name, slug, url, domain)
ON CONFLICT (country_code, url) DO NOTHING;

-- Bayt.com (Moyen-Orient multi-pays)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute)
SELECT code, name, slug, 'asie', 'emploi', NULL, 'Bayt — Emploi ' || name, 'https://www.bayt.com/', 'bayt.com', '1er site emploi Moyen-Orient: offres en ' || name, 75, false, 'Bayt ' || name, 'noopener nofollow'
FROM (VALUES
  ('SA', 'Arabie Saoudite', 'arabie-saoudite'),
  ('QA', 'Qatar', 'qatar'),
  ('KW', 'Koweit', 'koweit'),
  ('BH', 'Bahrein', 'bahrein'),
  ('OM', 'Oman', 'oman'),
  ('JO', 'Jordanie', 'jordanie'),
  ('LB', 'Liban', 'liban'),
  ('IQ', 'Irak', 'irak')
) AS t(code, name, slug)
ON CONFLICT (country_code, url) DO NOTHING;

-- LinkedIn (universel pour les pays sans portail local)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute)
SELECT code, name, slug, continent, 'emploi', NULL, 'LinkedIn Jobs — ' || name, 'https://www.linkedin.com/jobs/', 'linkedin.com', 'Recherche emploi international en ' || name, 70, false, 'LinkedIn Jobs', 'noopener nofollow'
FROM (VALUES
  ('AM', 'Armenie', 'armenie', 'asie'),
  ('AZ', 'Azerbaidjan', 'azerbaidjan', 'asie'),
  ('GE', 'Georgie', 'georgie', 'asie'),
  ('KZ', 'Kazakhstan', 'kazakhstan', 'asie'),
  ('UZ', 'Ouzbekistan', 'ouzbekistan', 'asie'),
  ('BD', 'Bangladesh', 'bangladesh', 'asie'),
  ('LK', 'Sri Lanka', 'sri-lanka', 'asie'),
  ('NP', 'Nepal', 'nepal', 'asie'),
  ('MM', 'Myanmar', 'myanmar', 'asie'),
  ('MN', 'Mongolie', 'mongolie', 'asie'),
  ('TW', 'Taiwan', 'taiwan', 'asie'),
  ('CY', 'Chypre', 'chypre', 'europe'),
  ('MT', 'Malte', 'malte', 'europe'),
  ('IS', 'Islande', 'islande', 'europe'),
  ('EE', 'Estonie', 'estonie', 'europe'),
  ('LT', 'Lituanie', 'lituanie', 'europe'),
  ('LV', 'Lettonie', 'lettonie', 'europe'),
  ('SI', 'Slovenie', 'slovenie', 'europe'),
  ('SK', 'Slovaquie', 'slovaquie', 'europe'),
  ('HR', 'Croatie', 'croatie', 'europe'),
  ('BG', 'Bulgarie', 'bulgarie', 'europe'),
  ('RS', 'Serbie', 'serbie', 'europe'),
  ('BA', 'Bosnie-Herzegovine', 'bosnie-herzegovine', 'europe'),
  ('ME', 'Montenegro', 'montenegro', 'europe'),
  ('AL', 'Albanie', 'albanie', 'europe'),
  ('MK', 'Macedoine du Nord', 'macedoine-du-nord', 'europe'),
  ('MD', 'Moldavie', 'moldavie', 'europe'),
  ('CL', 'Chili', 'chili', 'amerique-sud'),
  ('PE', 'Perou', 'perou', 'amerique-sud'),
  ('EC', 'Equateur', 'equateur', 'amerique-sud'),
  ('UY', 'Uruguay', 'uruguay', 'amerique-sud'),
  ('BO', 'Bolivie', 'bolivie', 'amerique-sud'),
  ('PY', 'Paraguay', 'paraguay', 'amerique-sud'),
  ('CR', 'Costa Rica', 'costa-rica', 'amerique-nord'),
  ('PA', 'Panama', 'panama', 'amerique-nord'),
  ('GT', 'Guatemala', 'guatemala', 'amerique-nord'),
  ('DO', 'Republique Dominicaine', 'republique-dominicaine', 'amerique-nord'),
  ('RW', 'Rwanda', 'rwanda', 'afrique'),
  ('ET', 'Ethiopie', 'ethiopie', 'afrique'),
  ('MZ', 'Mozambique', 'mozambique', 'afrique'),
  ('ZW', 'Zimbabwe', 'zimbabwe', 'afrique'),
  ('ZM', 'Zambie', 'zambie', 'afrique'),
  ('BW', 'Botswana', 'botswana', 'afrique'),
  ('NA', 'Namibie', 'namibie', 'afrique'),
  ('SC', 'Seychelles', 'seychelles', 'afrique'),
  ('MU', 'Ile Maurice', 'ile-maurice', 'afrique'),
  ('NZ', 'Nouvelle-Zelande', 'nouvelle-zelande', 'oceanie')
) AS t(code, name, slug, continent)
ON CONFLICT (country_code, url) DO NOTHING;

-- =============================================
-- LOGEMENT — Portails regionaux
-- =============================================

-- PropertyGuru (Asie du Sud-Est)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute)
SELECT code, name, slug, 'asie', 'logement', NULL, 'PropertyGuru ' || name, url, domain, 'Portail immobilier en ' || name, 75, false, 'PropertyGuru ' || name, 'noopener nofollow'
FROM (VALUES
  ('MY', 'Malaisie', 'malaisie', 'https://www.propertyguru.com.my/', 'propertyguru.com.my'),
  ('ID', 'Indonesie', 'indonesie', 'https://www.rumah123.com/', 'rumah123.com'),
  ('VN', 'Vietnam', 'vietnam', 'https://batdongsan.com.vn/', 'batdongsan.com.vn')
) AS t(code, name, slug, url, domain)
ON CONFLICT (country_code, url) DO NOTHING;

-- Lamudi (Philippines, Indonesie, Mexique, Colombie)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute)
SELECT code, name, slug, continent, 'logement', NULL, 'Lamudi ' || name, url, domain, 'Portail immobilier ' || name, 70, false, 'Lamudi ' || name, 'noopener nofollow'
FROM (VALUES
  ('PH', 'Philippines', 'philippines', 'asie', 'https://www.lamudi.com.ph/', 'lamudi.com.ph'),
  ('MX', 'Mexique', 'mexique', 'amerique-nord', 'https://www.lamudi.com.mx/', 'lamudi.com.mx'),
  ('CO', 'Colombie', 'colombie', 'amerique-sud', 'https://www.lamudi.com.co/', 'lamudi.com.co')
) AS t(code, name, slug, continent, url, domain)
ON CONFLICT (country_code, url) DO NOTHING;

-- PropertyFinder (Moyen-Orient)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute)
SELECT code, name, slug, 'asie', 'logement', NULL, 'PropertyFinder ' || name, url, domain, 'Portail immobilier ' || name, 75, false, 'PropertyFinder ' || name, 'noopener nofollow'
FROM (VALUES
  ('SA', 'Arabie Saoudite', 'arabie-saoudite', 'https://www.propertyfinder.sa/', 'propertyfinder.sa'),
  ('QA', 'Qatar', 'qatar', 'https://www.propertyfinder.qa/', 'propertyfinder.qa'),
  ('BH', 'Bahrein', 'bahrein', 'https://www.propertyfinder.bh/', 'propertyfinder.bh'),
  ('EG', 'Egypte', 'egypte', 'https://www.propertyfinder.eg/', 'propertyfinder.eg')
) AS t(code, name, slug, url, domain)
ON CONFLICT (country_code, url) DO NOTHING;

-- Airbnb (universel pour les pays sans portail local — comme reference logement temporaire)
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute)
SELECT code, name, slug, continent, 'logement', 'temporaire', 'Airbnb ' || name, 'https://www.airbnb.com/', 'airbnb.com', 'Location courte et moyenne duree en ' || name || '. Utile pour les premiers jours.', 70, false, 'Airbnb', 'noopener nofollow'
FROM (VALUES
  ('AM', 'Armenie', 'armenie', 'asie'),
  ('GE', 'Georgie', 'georgie', 'asie'),
  ('KZ', 'Kazakhstan', 'kazakhstan', 'asie'),
  ('LK', 'Sri Lanka', 'sri-lanka', 'asie'),
  ('NP', 'Nepal', 'nepal', 'asie'),
  ('KH', 'Cambodge', 'cambodge', 'asie'),
  ('LA', 'Laos', 'laos', 'asie'),
  ('BD', 'Bangladesh', 'bangladesh', 'asie'),
  ('JO', 'Jordanie', 'jordanie', 'asie'),
  ('LB', 'Liban', 'liban', 'asie'),
  ('OM', 'Oman', 'oman', 'asie'),
  ('HR', 'Croatie', 'croatie', 'europe'),
  ('BG', 'Bulgarie', 'bulgarie', 'europe'),
  ('RS', 'Serbie', 'serbie', 'europe'),
  ('AL', 'Albanie', 'albanie', 'europe'),
  ('ME', 'Montenegro', 'montenegro', 'europe'),
  ('CY', 'Chypre', 'chypre', 'europe'),
  ('MT', 'Malte', 'malte', 'europe'),
  ('IS', 'Islande', 'islande', 'europe'),
  ('EE', 'Estonie', 'estonie', 'europe'),
  ('LT', 'Lituanie', 'lituanie', 'europe'),
  ('LV', 'Lettonie', 'lettonie', 'europe'),
  ('SI', 'Slovenie', 'slovenie', 'europe'),
  ('CR', 'Costa Rica', 'costa-rica', 'amerique-nord'),
  ('PA', 'Panama', 'panama', 'amerique-nord'),
  ('DO', 'Republique Dominicaine', 'republique-dominicaine', 'amerique-nord'),
  ('CL', 'Chili', 'chili', 'amerique-sud'),
  ('PE', 'Perou', 'perou', 'amerique-sud'),
  ('EC', 'Equateur', 'equateur', 'amerique-sud'),
  ('UY', 'Uruguay', 'uruguay', 'amerique-sud'),
  ('RW', 'Rwanda', 'rwanda', 'afrique'),
  ('KE', 'Kenya', 'kenya', 'afrique'),
  ('TZ', 'Tanzanie', 'tanzanie', 'afrique'),
  ('ZA', 'Afrique du Sud', 'afrique-du-sud', 'afrique'),
  ('MU', 'Ile Maurice', 'ile-maurice', 'afrique'),
  ('SC', 'Seychelles', 'seychelles', 'afrique'),
  ('MG', 'Madagascar', 'madagascar', 'afrique'),
  ('NZ', 'Nouvelle-Zelande', 'nouvelle-zelande', 'oceanie')
) AS t(code, name, slug, continent)
ON CONFLICT (country_code, url) DO NOTHING;

-- =============================================
-- TELECOM — Operateurs principaux pour pays manquants
-- =============================================
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
-- Europe restante
('HR','Croatie','croatie','europe','telecom','mobile','HT — Hrvatski Telekom','https://www.hrvatskitelekom.hr/','hrvatskitelekom.hr','1er operateur croate',75,false,'HT Croatie','noopener nofollow'),
('BG','Bulgarie','bulgarie','europe','telecom','mobile','A1 Bulgaria','https://www.a1.bg/','a1.bg','1er operateur bulgare',75,false,'A1 Bulgaria','noopener nofollow'),
('RS','Serbie','serbie','europe','telecom','mobile','Telekom Srbija','https://mts.rs/','mts.rs','1er operateur serbe',75,false,'Telekom Srbija','noopener nofollow'),
('SI','Slovenie','slovenie','europe','telecom','mobile','Telekom Slovenije','https://www.telekom.si/','telekom.si','1er operateur slovene',75,false,'Telekom SI','noopener nofollow'),
('SK','Slovaquie','slovaquie','europe','telecom','mobile','Slovak Telekom','https://www.telekom.sk/','telekom.sk','1er operateur slovaque',75,false,'Slovak Telekom','noopener nofollow'),
('HR','Croatie','croatie','europe','telecom','mobile','A1 Hrvatska','https://www.a1.hr/','a1.hr','Operateur croate',70,false,'A1 Croatia','noopener nofollow'),
('EE','Estonie','estonie','europe','telecom','mobile','Telia Eesti','https://www.telia.ee/','telia.ee','1er operateur estonien',75,false,'Telia EE','noopener nofollow'),
('LT','Lituanie','lituanie','europe','telecom','mobile','Telia Lietuva','https://www.telia.lt/','telia.lt','1er operateur lituanien',75,false,'Telia LT','noopener nofollow'),
('LV','Lettonie','lettonie','europe','telecom','mobile','LMT','https://www.lmt.lv/','lmt.lv','1er operateur letton',75,false,'LMT','noopener nofollow'),
('MT','Malte','malte','europe','telecom','mobile','GO Malta','https://www.go.com.mt/','go.com.mt','1er operateur maltais',75,false,'GO Malta','noopener nofollow'),
('CY','Chypre','chypre','europe','telecom','mobile','Cyta','https://www.cyta.com.cy/','cyta.com.cy','1er operateur chypriote',75,false,'Cyta','noopener nofollow'),
('IS','Islande','islande','europe','telecom','mobile','Siminn','https://www.siminn.is/','siminn.is','1er operateur islandais',75,false,'Siminn','noopener nofollow'),
('AL','Albanie','albanie','europe','telecom','mobile','Vodafone Albania','https://www.vodafone.al/','vodafone.al','1er operateur albanais',70,false,'Vodafone AL','noopener nofollow'),
('HU','Hongrie','hongrie','europe','telecom','mobile','Telekom Hungary','https://www.telekom.hu/','telekom.hu','1er operateur hongrois',75,false,'Telekom HU','noopener nofollow'),
('FI','Finlande','finlande','europe','telecom','mobile','Elisa','https://elisa.fi/','elisa.fi','1er operateur finlandais',80,false,'Elisa','noopener nofollow'),
-- Asie restante
('BD','Bangladesh','bangladesh','asie','telecom','mobile','Grameenphone','https://www.grameenphone.com/','grameenphone.com','1er operateur bangladais',75,false,'Grameenphone','noopener nofollow'),
('LK','Sri Lanka','sri-lanka','asie','telecom','mobile','Dialog','https://www.dialog.lk/','dialog.lk','1er operateur sri-lankais',75,false,'Dialog','noopener nofollow'),
('NP','Nepal','nepal','asie','telecom','mobile','Ncell','https://www.ncell.axiata.com/','ncell.axiata.com','1er operateur nepalais prive',70,false,'Ncell','noopener nofollow'),
('PK','Pakistan','pakistan','asie','telecom','mobile','Jazz','https://www.jazz.com.pk/','jazz.com.pk','1er operateur pakistanais',75,false,'Jazz','noopener nofollow'),
('MM','Myanmar','myanmar','asie','telecom','mobile','Ooredoo Myanmar','https://www.ooredoo.com.mm/','ooredoo.com.mm','Operateur mobile Myanmar',65,false,'Ooredoo MM','noopener nofollow'),
('SA','Arabie Saoudite','arabie-saoudite','asie','telecom','mobile','STC','https://www.stc.com.sa/','stc.com.sa','1er operateur saoudien',80,false,'STC','noopener nofollow'),
('QA','Qatar','qatar','asie','telecom','mobile','Ooredoo Qatar','https://www.ooredoo.qa/','ooredoo.qa','1er operateur qatari',80,false,'Ooredoo QA','noopener nofollow'),
('KW','Koweit','koweit','asie','telecom','mobile','Zain Kuwait','https://www.kw.zain.com/','kw.zain.com','1er operateur koweitien',75,false,'Zain KW','noopener nofollow'),
('OM','Oman','oman','asie','telecom','mobile','Omantel','https://www.omantel.om/','omantel.om','1er operateur omanais',75,false,'Omantel','noopener nofollow'),
('JO','Jordanie','jordanie','asie','telecom','mobile','Zain Jordan','https://www.jo.zain.com/','jo.zain.com','1er operateur jordanien',75,false,'Zain JO','noopener nofollow'),
('AM','Armenie','armenie','asie','telecom','mobile','Ucom','https://www.ucom.am/','ucom.am','Operateur armenien',65,false,'Ucom','noopener nofollow'),
('GE','Georgie','georgie','asie','telecom','mobile','Magti','https://www.magticom.ge/','magticom.ge','1er operateur georgien',65,false,'Magti','noopener nofollow'),
('KZ','Kazakhstan','kazakhstan','asie','telecom','mobile','Kcell','https://www.kcell.kz/','kcell.kz','1er operateur kazakh',70,false,'Kcell','noopener nofollow'),
('UZ','Ouzbekistan','ouzbekistan','asie','telecom','mobile','Ucell','https://www.ucell.uz/','ucell.uz','Operateur ouzbek',65,false,'Ucell','noopener nofollow'),
-- Ameriques restantes
('CL','Chili','chili','amerique-sud','telecom','mobile','Entel Chile','https://www.entel.cl/','entel.cl','1er operateur chilien',75,false,'Entel','noopener nofollow'),
('PE','Perou','perou','amerique-sud','telecom','mobile','Claro Peru','https://www.claro.com.pe/','claro.com.pe','1er operateur peruvien',75,false,'Claro PE','noopener nofollow'),
('EC','Equateur','equateur','amerique-sud','telecom','mobile','Claro Ecuador','https://www.claro.com.ec/','claro.com.ec','1er operateur equatorien',75,false,'Claro EC','noopener nofollow'),
('CR','Costa Rica','costa-rica','amerique-nord','telecom','mobile','Kolbi ICE','https://www.grupoice.com/','grupoice.com','Operateur national Costa Rica',70,false,'Kolbi','noopener nofollow'),
('DO','Republique Dominicaine','republique-dominicaine','amerique-nord','telecom','mobile','Claro RD','https://www.claro.com.do/','claro.com.do','1er operateur dominicain',75,false,'Claro RD','noopener nofollow'),
('PA','Panama','panama','amerique-nord','telecom','mobile','+Movil Panama','https://www.masmovil.com.pa/','masmovil.com.pa','1er operateur panameeen',70,false,'+Movil','noopener nofollow'),
('GT','Guatemala','guatemala','amerique-nord','telecom','mobile','Tigo Guatemala','https://www.tigo.com.gt/','tigo.com.gt','1er operateur guatemalteque',70,false,'Tigo GT','noopener nofollow'),
-- Afrique restante
('GH','Ghana','ghana','afrique','telecom','mobile','MTN Ghana','https://mtn.com.gh/','mtn.com.gh','1er operateur ghaneen',75,false,'MTN Ghana','noopener nofollow'),
('ET','Ethiopie','ethiopie','afrique','telecom','mobile','Ethio Telecom','https://www.ethiotelecom.et/','ethiotelecom.et','Operateur national ethiopien',70,true,'Ethio Telecom','noopener'),
('TZ','Tanzanie','tanzanie','afrique','telecom','mobile','Vodacom Tanzania','https://www.vodacom.co.tz/','vodacom.co.tz','1er operateur tanzanien',75,false,'Vodacom TZ','noopener nofollow'),
('UG','Ouganda','ouganda','afrique','telecom','mobile','MTN Uganda','https://www.mtn.co.ug/','mtn.co.ug','1er operateur ougandais',75,false,'MTN UG','noopener nofollow'),
('RW','Rwanda','rwanda','afrique','telecom','mobile','MTN Rwanda','https://www.mtn.co.rw/','mtn.co.rw','1er operateur rwandais',75,false,'MTN RW','noopener nofollow'),
('MZ','Mozambique','mozambique','afrique','telecom','mobile','Vodacom Mozambique','https://www.vm.co.mz/','vm.co.mz','1er operateur mozambicain',70,false,'Vodacom MZ','noopener nofollow'),
('ZW','Zimbabwe','zimbabwe','afrique','telecom','mobile','Econet Zimbabwe','https://www.econet.co.zw/','econet.co.zw','1er operateur zimbabween',70,false,'Econet','noopener nofollow'),
('ZM','Zambie','zambie','afrique','telecom','mobile','MTN Zambia','https://www.mtn.zm/','mtn.zm','1er operateur zambien',70,false,'MTN ZM','noopener nofollow'),
('BW','Botswana','botswana','afrique','telecom','mobile','Mascom','https://www.mascom.bw/','mascom.bw','1er operateur botswanais',70,false,'Mascom','noopener nofollow'),
('NA','Namibie','namibie','afrique','telecom','mobile','MTC Namibia','https://www.mtc.com.na/','mtc.com.na','1er operateur namibien',70,false,'MTC','noopener nofollow'),
('MU','Ile Maurice','ile-maurice','afrique','telecom','mobile','Emtel','https://www.emtel.com/','emtel.com','Operateur mobile Maurice',70,false,'Emtel','noopener nofollow'),
('SC','Seychelles','seychelles','afrique','telecom','mobile','Cable & Wireless Seychelles','https://www.cwseychelles.com/','cwseychelles.com','Operateur seychellois',65,false,'CW Seychelles','noopener nofollow');


-- =============================================
-- SANTE — Hopitaux internationaux et systemes de sante
-- =============================================
INSERT INTO country_directory (country_code, country_name, country_slug, continent, category, sub_category, title, url, domain, description, trust_score, is_official, anchor_text, rel_attribute) VALUES
-- Europe
('HR','Croatie','croatie','europe','sante',NULL,'HZZO — Assurance sante Croatie','https://hzzo.hr/','hzzo.hr','Caisse croate d assurance sante',85,true,'HZZO Croatie','noopener'),
('BG','Bulgarie','bulgarie','europe','sante',NULL,'NHIF — Assurance sante Bulgarie','https://www.nhif.bg/','nhif.bg','Caisse nationale d assurance maladie bulgare',80,true,'NHIF Bulgarie','noopener'),
('CY','Chypre','chypre','europe','sante',NULL,'GESY — Sante Chypre','https://www.gesy.org.cy/','gesy.org.cy','Systeme general de sante chypriote (lance 2019)',85,true,'GESY Chypre','noopener'),
('MT','Malte','malte','europe','sante',NULL,'Mater Dei Hospital Malte','https://www.gov.mt/en/life-events/health/Pages/hospitals.aspx','gov.mt','Hopital principal de Malte',80,true,'Mater Dei','noopener'),
('EE','Estonie','estonie','europe','sante',NULL,'EHIF — Assurance sante Estonie','https://www.haigekassa.ee/en','haigekassa.ee','Caisse estonienne d assurance maladie',80,true,'EHIF Estonie','noopener'),
-- Ameriques
('CL','Chili','chili','amerique-sud','sante',NULL,'Fonasa — Sante publique Chili','https://www.fonasa.cl/','fonasa.cl','Fonds national de sante chilien',85,true,'Fonasa Chili','noopener'),
('PE','Perou','perou','amerique-sud','sante',NULL,'SIS — Assurance sante Peru','https://www.gob.pe/sis','gob.pe','Seguro Integral de Salud',80,true,'SIS Peru','noopener'),
('CR','Costa Rica','costa-rica','amerique-nord','sante',NULL,'CCSS — Securite sociale Costa Rica','https://www.ccss.sa.cr/','ccss.sa.cr','Caja Costarricense de Seguro Social',80,true,'CCSS','noopener'),
-- Asie
('KR','Coree du Sud','coree-du-sud','asie','sante','hopital','Severance Hospital Seoul','https://www.yuhs.or.kr/en/','yuhs.or.kr','Hopital universitaire de reference a Seoul',80,false,'Severance Hospital','noopener'),
('KH','Cambodge','cambodge','asie','sante','hopital','Royal Phnom Penh Hospital','https://www.royalphnompenhhospital.com/','royalphnompenhhospital.com','Hopital international a Phnom Penh',70,false,'Royal PP Hospital','noopener'),
('LK','Sri Lanka','sri-lanka','asie','sante','hopital','Lanka Hospitals','https://www.lankahospitals.com/','lankahospitals.com','Hopital prive de reference a Colombo',70,false,'Lanka Hospitals','noopener'),
('PK','Pakistan','pakistan','asie','sante','hopital','Aga Khan University Hospital Karachi','https://hospitals.aku.edu/karachi/','aku.edu','Hopital universitaire accredite JCI',80,false,'Aga Khan Karachi','noopener'),
('JO','Jordanie','jordanie','asie','sante','hopital-jci','King Hussein Cancer Center','https://www.khcc.jo/','khcc.jo','Centre medical accredite JCI a Amman',85,false,'KHCC Amman','noopener'),
-- Afrique
('GH','Ghana','ghana','afrique','sante',NULL,'NHIS — Assurance sante Ghana','https://www.nhis.gov.gh/','nhis.gov.gh','National Health Insurance Scheme',80,true,'NHIS Ghana','noopener'),
('RW','Rwanda','rwanda','afrique','sante',NULL,'RSSB — Sante Rwanda','https://www.rssb.rw/','rssb.rw','Rwanda Social Security Board: assurance maladie',80,true,'RSSB Rwanda','noopener'),
('ET','Ethiopie','ethiopie','afrique','sante','hopital','Black Lion Hospital Addis','https://www.aau.edu.et/chs/tikur-anbessa-hospital/','aau.edu.et','Hopital universitaire de reference',70,true,'Black Lion Hospital','noopener'),
('MU','Ile Maurice','ile-maurice','afrique','sante','hopital','Clinique Darne','https://www.cliniquedarne.com/','cliniquedarne.com','Clinique privee de reference a Maurice',70,false,'Clinique Darne','noopener');


-- =============================================
-- VERIFICATION FINALE
-- =============================================
SELECT
  '=== REMPLISSAGE FINAL ===' as label,
  COUNT(DISTINCT country_code) FILTER (WHERE country_code != 'XX') as pays,
  COUNT(*) as liens_total,
  COUNT(*) FILTER (WHERE category = 'ambassade') as ambassades,
  COUNT(*) FILTER (WHERE category = 'immigration') as immigration,
  COUNT(*) FILTER (WHERE category = 'emploi') as emploi,
  COUNT(*) FILTER (WHERE category = 'logement') as logement,
  COUNT(*) FILTER (WHERE category = 'telecom') as telecom,
  COUNT(*) FILTER (WHERE category = 'sante') as sante,
  COUNT(*) FILTER (WHERE category = 'transport') as transport,
  COUNT(*) FILTER (WHERE category = 'fiscalite') as fiscalite,
  COUNT(*) FILTER (WHERE category = 'banque') as banque,
  COUNT(*) FILTER (WHERE category = 'education') as education
FROM country_directory WHERE is_active = true;

-- Pays encore "pauvres" (< 3 categories)
SELECT country_code, country_name, COUNT(DISTINCT category) as nb_cat,
  string_agg(DISTINCT category, ', ' ORDER BY category) as categories
FROM country_directory
WHERE is_active = true AND country_code != 'XX'
GROUP BY country_code, country_name
HAVING COUNT(DISTINCT category) < 3
ORDER BY nb_cat, country_name;
