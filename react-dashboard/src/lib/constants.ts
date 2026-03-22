import type { ContactType, PipelineStatus } from '../types/influenceur';

// ============================================================
// 19 CONTACT TYPES — icon, label, color, tailwind classes
// ============================================================

export interface ContactTypeConfig {
  value: ContactType;
  label: string;
  icon: string;
  color: string;        // hex
  bg: string;           // tailwind bg class
  text: string;         // tailwind text class
}

export const CONTACT_TYPES: ContactTypeConfig[] = [
  { value: 'school',         label: 'Écoles',              icon: '🏫', color: '#10B981', bg: 'bg-emerald-500/20',  text: 'text-emerald-400' },
  { value: 'chatter',        label: 'Chatters',            icon: '💬', color: '#FF6B6B', bg: 'bg-red-400/20',      text: 'text-red-400' },
  { value: 'tiktoker',       label: 'TikTokeurs',          icon: '🎵', color: '#FF0050', bg: 'bg-rose-500/20',     text: 'text-rose-400' },
  { value: 'youtuber',       label: 'YouTubeurs',          icon: '🎬', color: '#FF0000', bg: 'bg-red-600/20',      text: 'text-red-500' },
  { value: 'instagramer',    label: 'Instagrameurs',       icon: '📸', color: '#E1306C', bg: 'bg-pink-500/20',     text: 'text-pink-400' },
  { value: 'influenceur',    label: 'Influenceurs',        icon: '✨', color: '#FFD60A', bg: 'bg-yellow-400/20',   text: 'text-yellow-300' },
  { value: 'blogger',        label: 'Blogueurs',           icon: '📰', color: '#A855F7', bg: 'bg-purple-500/20',   text: 'text-purple-400' },
  { value: 'backlink',       label: 'Backlinks',           icon: '🔗', color: '#F59E0B', bg: 'bg-amber-500/20',    text: 'text-amber-400' },
  { value: 'association',    label: 'Associations',        icon: '🤝', color: '#EC4899', bg: 'bg-pink-400/20',     text: 'text-pink-300' },
  { value: 'travel_agency',  label: 'Agences voyage',      icon: '✈️', color: '#06B6D4', bg: 'bg-cyan-500/20',     text: 'text-cyan-400' },
  { value: 'real_estate',    label: 'Agents immobiliers',  icon: '🏠', color: '#84CC16', bg: 'bg-lime-500/20',     text: 'text-lime-400' },
  { value: 'translator',     label: 'Traducteurs',         icon: '🌐', color: '#0EA5E9', bg: 'bg-sky-500/20',      text: 'text-sky-400' },
  { value: 'insurer',        label: 'Assureurs/B2B',       icon: '🛡️', color: '#3B82F6', bg: 'bg-blue-500/20',     text: 'text-blue-400' },
  { value: 'enterprise',     label: 'Entreprises',         icon: '🏢', color: '#14B8A6', bg: 'bg-teal-500/20',     text: 'text-teal-400' },
  { value: 'press',          label: 'Presse',              icon: '📺', color: '#E11D48', bg: 'bg-rose-600/20',     text: 'text-rose-400' },
  { value: 'partner',        label: 'Partenariats',        icon: '🏛️', color: '#D97706', bg: 'bg-amber-600/20',    text: 'text-amber-500' },
  { value: 'lawyer',         label: 'Avocats',             icon: '⚖️', color: '#8B5CF6', bg: 'bg-violet-500/20',   text: 'text-violet-400' },
  { value: 'job_board',      label: 'Sites emploi',        icon: '💼', color: '#78716C', bg: 'bg-stone-500/20',    text: 'text-stone-400' },
  { value: 'group_admin',    label: 'Group Admins',        icon: '👥', color: '#F472B6', bg: 'bg-pink-400/20',     text: 'text-pink-300' },
];

export const CONTACT_TYPE_MAP = Object.fromEntries(
  CONTACT_TYPES.map(t => [t.value, t])
) as Record<ContactType, ContactTypeConfig>;

export function getContactType(type: ContactType): ContactTypeConfig {
  return CONTACT_TYPE_MAP[type] ?? CONTACT_TYPES[0];
}

// ============================================================
// 14 PIPELINE STATUSES
// ============================================================

export interface StatusConfig {
  value: PipelineStatus;
  label: string;
  color: string;
  bg: string;
  text: string;
}

export const PIPELINE_STATUSES: StatusConfig[] = [
  { value: 'new',          label: 'Nouveau',     color: '#4A5568', bg: 'bg-gray-600/20',    text: 'text-gray-400' },
  { value: 'prospect',     label: 'Prospect',    color: '#6B7280', bg: 'bg-gray-500/20',    text: 'text-gray-400' },
  { value: 'contacted1',   label: '1er contact', color: '#F59E0B', bg: 'bg-amber-500/20',   text: 'text-amber-400' },
  { value: 'contacted2',   label: 'Relance 1',   color: '#F97316', bg: 'bg-orange-500/20',  text: 'text-orange-400' },
  { value: 'contacted3',   label: 'Relance 2',   color: '#EF4444', bg: 'bg-red-500/20',     text: 'text-red-400' },
  { value: 'contacted',    label: 'Contacté',    color: '#F59E0B', bg: 'bg-amber-500/20',   text: 'text-amber-400' },
  { value: 'negotiating',  label: 'Négociation', color: '#D97706', bg: 'bg-amber-600/20',   text: 'text-amber-500' },
  { value: 'replied',      label: 'Répondu',     color: '#06B6D4', bg: 'bg-cyan-500/20',    text: 'text-cyan-400' },
  { value: 'meeting',      label: 'Meeting',     color: '#A855F7', bg: 'bg-purple-500/20',  text: 'text-purple-400' },
  { value: 'active',       label: 'Actif',       color: '#10B981', bg: 'bg-emerald-500/20', text: 'text-emerald-400' },
  { value: 'signed',       label: 'Signé',       color: '#10B981', bg: 'bg-green-500/20',   text: 'text-green-400' },
  { value: 'refused',      label: 'Refusé',      color: '#374151', bg: 'bg-gray-700/20',    text: 'text-gray-500' },
  { value: 'inactive',     label: 'Inactif',     color: '#6B7280', bg: 'bg-gray-500/20',    text: 'text-gray-500' },
  { value: 'lost',         label: 'Perdu',        color: '#374151', bg: 'bg-gray-700/20',    text: 'text-gray-600' },
];

export const STATUS_MAP = Object.fromEntries(
  PIPELINE_STATUSES.map(s => [s.value, s])
) as Record<PipelineStatus, StatusConfig>;

export function getStatus(status: PipelineStatus): StatusConfig {
  return STATUS_MAP[status] ?? PIPELINE_STATUSES[0];
}

// ============================================================
// COUNTRIES (197 countries + aliases)
// ============================================================

export const COUNTRIES = [
  // --- Europe (45 countries) ---
  { name: 'Albanie',                flag: '🇦🇱', code: 'AL' },
  { name: 'Allemagne',              flag: '🇩🇪', code: 'DE' },
  { name: 'Andorre',                flag: '🇦🇩', code: 'AD' },
  { name: 'Autriche',               flag: '🇦🇹', code: 'AT' },
  { name: 'Belgique',               flag: '🇧🇪', code: 'BE' },
  { name: 'Biélorussie',            flag: '🇧🇾', code: 'BY' },
  { name: 'Bosnie-Herzégovine',     flag: '🇧🇦', code: 'BA' },
  { name: 'Bulgarie',               flag: '🇧🇬', code: 'BG' },
  { name: 'Chypre',                 flag: '🇨🇾', code: 'CY' },
  { name: 'Croatie',                flag: '🇭🇷', code: 'HR' },
  { name: 'Danemark',               flag: '🇩🇰', code: 'DK' },
  { name: 'Espagne',                flag: '🇪🇸', code: 'ES' },
  { name: 'Estonie',                flag: '🇪🇪', code: 'EE' },
  { name: 'Finlande',               flag: '🇫🇮', code: 'FI' },
  { name: 'France',                 flag: '🇫🇷', code: 'FR' },
  { name: 'Grèce',                  flag: '🇬🇷', code: 'GR' },
  { name: 'Hongrie',                flag: '🇭🇺', code: 'HU' },
  { name: 'Irlande',                flag: '🇮🇪', code: 'IE' },
  { name: 'Islande',                flag: '🇮🇸', code: 'IS' },
  { name: 'Italie',                 flag: '🇮🇹', code: 'IT' },
  { name: 'Kosovo',                 flag: '🇽🇰', code: 'XK' },
  { name: 'Lettonie',               flag: '🇱🇻', code: 'LV' },
  { name: 'Liechtenstein',          flag: '🇱🇮', code: 'LI' },
  { name: 'Lituanie',               flag: '🇱🇹', code: 'LT' },
  { name: 'Luxembourg',             flag: '🇱🇺', code: 'LU' },
  { name: 'Macédoine du Nord',      flag: '🇲🇰', code: 'MK' },
  { name: 'Malte',                  flag: '🇲🇹', code: 'MT' },
  { name: 'Moldavie',               flag: '🇲🇩', code: 'MD' },
  { name: 'Monaco',                 flag: '🇲🇨', code: 'MC' },
  { name: 'Monténégro',             flag: '🇲🇪', code: 'ME' },
  { name: 'Norvège',                flag: '🇳🇴', code: 'NO' },
  { name: 'Pays-Bas',               flag: '🇳🇱', code: 'NL' },
  { name: 'Pologne',                flag: '🇵🇱', code: 'PL' },
  { name: 'Portugal',               flag: '🇵🇹', code: 'PT' },
  { name: 'République tchèque',     flag: '🇨🇿', code: 'CZ' },
  { name: 'Roumanie',               flag: '🇷🇴', code: 'RO' },
  { name: 'Royaume-Uni',            flag: '🇬🇧', code: 'GB' },
  { name: 'UK',                     flag: '🇬🇧', code: 'GB' },
  { name: 'Russie',                 flag: '🇷🇺', code: 'RU' },
  { name: 'Saint-Marin',            flag: '🇸🇲', code: 'SM' },
  { name: 'Serbie',                 flag: '🇷🇸', code: 'RS' },
  { name: 'Slovaquie',              flag: '🇸🇰', code: 'SK' },
  { name: 'Slovénie',               flag: '🇸🇮', code: 'SI' },
  { name: 'Suède',                  flag: '🇸🇪', code: 'SE' },
  { name: 'Suisse',                 flag: '🇨🇭', code: 'CH' },
  { name: 'Ukraine',                flag: '🇺🇦', code: 'UA' },
  { name: 'Vatican',                flag: '🇻🇦', code: 'VA' },

  // --- Afrique (54 countries) ---
  { name: 'Afrique du Sud',         flag: '🇿🇦', code: 'ZA' },
  { name: 'Algérie',                flag: '🇩🇿', code: 'DZ' },
  { name: 'Angola',                 flag: '🇦🇴', code: 'AO' },
  { name: 'Bénin',                  flag: '🇧🇯', code: 'BJ' },
  { name: 'Botswana',               flag: '🇧🇼', code: 'BW' },
  { name: 'Burkina Faso',           flag: '🇧🇫', code: 'BF' },
  { name: 'Burundi',                flag: '🇧🇮', code: 'BI' },
  { name: 'Cabo Verde',             flag: '🇨🇻', code: 'CV' },
  { name: 'Cameroun',               flag: '🇨🇲', code: 'CM' },
  { name: 'Centrafrique',           flag: '🇨🇫', code: 'CF' },
  { name: 'Comores',                flag: '🇰🇲', code: 'KM' },
  { name: 'Congo',                  flag: '🇨🇬', code: 'CG' },
  { name: 'Congo (RDC)',            flag: '🇨🇩', code: 'CD' },
  { name: "Côte d'Ivoire",          flag: '🇨🇮', code: 'CI' },
  { name: 'Djibouti',               flag: '🇩🇯', code: 'DJ' },
  { name: 'Égypte',                 flag: '🇪🇬', code: 'EG' },
  { name: 'Érythrée',               flag: '🇪🇷', code: 'ER' },
  { name: 'Eswatini',               flag: '🇸🇿', code: 'SZ' },
  { name: 'Éthiopie',               flag: '🇪🇹', code: 'ET' },
  { name: 'Gabon',                  flag: '🇬🇦', code: 'GA' },
  { name: 'Gambie',                 flag: '🇬🇲', code: 'GM' },
  { name: 'Ghana',                  flag: '🇬🇭', code: 'GH' },
  { name: 'Guinée',                 flag: '🇬🇳', code: 'GN' },
  { name: 'Guinée équatoriale',     flag: '🇬🇶', code: 'GQ' },
  { name: 'Guinée-Bissau',          flag: '🇬🇼', code: 'GW' },
  { name: 'Kenya',                  flag: '🇰🇪', code: 'KE' },
  { name: 'Lesotho',                flag: '🇱🇸', code: 'LS' },
  { name: 'Liberia',                flag: '🇱🇷', code: 'LR' },
  { name: 'Libye',                  flag: '🇱🇾', code: 'LY' },
  { name: 'Madagascar',             flag: '🇲🇬', code: 'MG' },
  { name: 'Malawi',                 flag: '🇲🇼', code: 'MW' },
  { name: 'Mali',                   flag: '🇲🇱', code: 'ML' },
  { name: 'Maroc',                  flag: '🇲🇦', code: 'MA' },
  { name: 'Maurice',                flag: '🇲🇺', code: 'MU' },
  { name: 'Mauritanie',             flag: '🇲🇷', code: 'MR' },
  { name: 'Mozambique',             flag: '🇲🇿', code: 'MZ' },
  { name: 'Namibie',                flag: '🇳🇦', code: 'NA' },
  { name: 'Niger',                  flag: '🇳🇪', code: 'NE' },
  { name: 'Nigeria',                flag: '🇳🇬', code: 'NG' },
  { name: 'Ouganda',                flag: '🇺🇬', code: 'UG' },
  { name: 'Rwanda',                 flag: '🇷🇼', code: 'RW' },
  { name: 'São Tomé-et-Príncipe',   flag: '🇸🇹', code: 'ST' },
  { name: 'Sénégal',                flag: '🇸🇳', code: 'SN' },
  { name: 'Seychelles',             flag: '🇸🇨', code: 'SC' },
  { name: 'Sierra Leone',           flag: '🇸🇱', code: 'SL' },
  { name: 'Somalie',                flag: '🇸🇴', code: 'SO' },
  { name: 'Soudan',                 flag: '🇸🇩', code: 'SD' },
  { name: 'Soudan du Sud',          flag: '🇸🇸', code: 'SS' },
  { name: 'Tanzanie',               flag: '🇹🇿', code: 'TZ' },
  { name: 'Tchad',                  flag: '🇹🇩', code: 'TD' },
  { name: 'Togo',                   flag: '🇹🇬', code: 'TG' },
  { name: 'Tunisie',                flag: '🇹🇳', code: 'TN' },
  { name: 'Zambie',                 flag: '🇿🇲', code: 'ZM' },
  { name: 'Zimbabwe',               flag: '🇿🇼', code: 'ZW' },

  // --- Amériques (35 countries) ---
  { name: 'Antigua-et-Barbuda',     flag: '🇦🇬', code: 'AG' },
  { name: 'Argentine',              flag: '🇦🇷', code: 'AR' },
  { name: 'Bahamas',                flag: '🇧🇸', code: 'BS' },
  { name: 'Barbade',                flag: '🇧🇧', code: 'BB' },
  { name: 'Belize',                 flag: '🇧🇿', code: 'BZ' },
  { name: 'Bolivie',                flag: '🇧🇴', code: 'BO' },
  { name: 'Brésil',                 flag: '🇧🇷', code: 'BR' },
  { name: 'Canada',                 flag: '🇨🇦', code: 'CA' },
  { name: 'Chili',                  flag: '🇨🇱', code: 'CL' },
  { name: 'Colombie',               flag: '🇨🇴', code: 'CO' },
  { name: 'Costa Rica',             flag: '🇨🇷', code: 'CR' },
  { name: 'Cuba',                   flag: '🇨🇺', code: 'CU' },
  { name: 'Dominique',              flag: '🇩🇲', code: 'DM' },
  { name: 'Équateur',               flag: '🇪🇨', code: 'EC' },
  { name: 'États-Unis',             flag: '🇺🇸', code: 'US' },
  { name: 'USA',                    flag: '🇺🇸', code: 'US' },
  { name: 'Grenade',                flag: '🇬🇩', code: 'GD' },
  { name: 'Guatemala',              flag: '🇬🇹', code: 'GT' },
  { name: 'Guyana',                 flag: '🇬🇾', code: 'GY' },
  { name: 'Haïti',                  flag: '🇭🇹', code: 'HT' },
  { name: 'Honduras',               flag: '🇭🇳', code: 'HN' },
  { name: 'Jamaïque',               flag: '🇯🇲', code: 'JM' },
  { name: 'Mexique',                flag: '🇲🇽', code: 'MX' },
  { name: 'Nicaragua',              flag: '🇳🇮', code: 'NI' },
  { name: 'Panama',                 flag: '🇵🇦', code: 'PA' },
  { name: 'Paraguay',               flag: '🇵🇾', code: 'PY' },
  { name: 'Pérou',                  flag: '🇵🇪', code: 'PE' },
  { name: 'République dominicaine', flag: '🇩🇴', code: 'DO' },
  { name: 'Saint-Kitts-et-Nevis',   flag: '🇰🇳', code: 'KN' },
  { name: 'Saint-Vincent-et-les-Grenadines', flag: '🇻🇨', code: 'VC' },
  { name: 'Sainte-Lucie',           flag: '🇱🇨', code: 'LC' },
  { name: 'Salvador',               flag: '🇸🇻', code: 'SV' },
  { name: 'Suriname',               flag: '🇸🇷', code: 'SR' },
  { name: 'Trinité-et-Tobago',      flag: '🇹🇹', code: 'TT' },
  { name: 'Uruguay',                flag: '🇺🇾', code: 'UY' },
  { name: 'Venezuela',              flag: '🇻🇪', code: 'VE' },

  // --- Asie (48 countries) ---
  { name: 'Afghanistan',            flag: '🇦🇫', code: 'AF' },
  { name: 'Arabie saoudite',        flag: '🇸🇦', code: 'SA' },
  { name: 'Arménie',                flag: '🇦🇲', code: 'AM' },
  { name: 'Azerbaïdjan',            flag: '🇦🇿', code: 'AZ' },
  { name: 'Bahreïn',                flag: '🇧🇭', code: 'BH' },
  { name: 'Bangladesh',             flag: '🇧🇩', code: 'BD' },
  { name: 'Bhoutan',                flag: '🇧🇹', code: 'BT' },
  { name: 'Brunei',                 flag: '🇧🇳', code: 'BN' },
  { name: 'Cambodge',               flag: '🇰🇭', code: 'KH' },
  { name: 'Chine',                  flag: '🇨🇳', code: 'CN' },
  { name: 'Corée du Nord',          flag: '🇰🇵', code: 'KP' },
  { name: 'Corée du Sud',           flag: '🇰🇷', code: 'KR' },
  { name: 'Dubaï',                  flag: '🇦🇪', code: 'AE' },
  { name: 'Émirats arabes unis',    flag: '🇦🇪', code: 'AE' },
  { name: 'Géorgie',                flag: '🇬🇪', code: 'GE' },
  { name: 'Inde',                   flag: '🇮🇳', code: 'IN' },
  { name: 'Indonésie',              flag: '🇮🇩', code: 'ID' },
  { name: 'Irak',                   flag: '🇮🇶', code: 'IQ' },
  { name: 'Iran',                   flag: '🇮🇷', code: 'IR' },
  { name: 'Israël',                 flag: '🇮🇱', code: 'IL' },
  { name: 'Japon',                  flag: '🇯🇵', code: 'JP' },
  { name: 'Jordanie',               flag: '🇯🇴', code: 'JO' },
  { name: 'Kazakhstan',             flag: '🇰🇿', code: 'KZ' },
  { name: 'Kirghizistan',           flag: '🇰🇬', code: 'KG' },
  { name: 'Koweït',                 flag: '🇰🇼', code: 'KW' },
  { name: 'Laos',                   flag: '🇱🇦', code: 'LA' },
  { name: 'Liban',                  flag: '🇱🇧', code: 'LB' },
  { name: 'Malaisie',               flag: '🇲🇾', code: 'MY' },
  { name: 'Maldives',               flag: '🇲🇻', code: 'MV' },
  { name: 'Mongolie',               flag: '🇲🇳', code: 'MN' },
  { name: 'Myanmar',                flag: '🇲🇲', code: 'MM' },
  { name: 'Népal',                  flag: '🇳🇵', code: 'NP' },
  { name: 'Oman',                   flag: '🇴🇲', code: 'OM' },
  { name: 'Ouzbékistan',            flag: '🇺🇿', code: 'UZ' },
  { name: 'Pakistan',               flag: '🇵🇰', code: 'PK' },
  { name: 'Palestine',              flag: '🇵🇸', code: 'PS' },
  { name: 'Philippines',            flag: '🇵🇭', code: 'PH' },
  { name: 'Qatar',                  flag: '🇶🇦', code: 'QA' },
  { name: 'Singapour',              flag: '🇸🇬', code: 'SG' },
  { name: 'Sri Lanka',              flag: '🇱🇰', code: 'LK' },
  { name: 'Syrie',                  flag: '🇸🇾', code: 'SY' },
  { name: 'Tadjikistan',            flag: '🇹🇯', code: 'TJ' },
  { name: 'Taïwan',                 flag: '🇹🇼', code: 'TW' },
  { name: 'Thaïlande',              flag: '🇹🇭', code: 'TH' },
  { name: 'Timor oriental',         flag: '🇹🇱', code: 'TL' },
  { name: 'Turkménistan',           flag: '🇹🇲', code: 'TM' },
  { name: 'Turquie',                flag: '🇹🇷', code: 'TR' },
  { name: 'Vietnam',                flag: '🇻🇳', code: 'VN' },
  { name: 'Yémen',                  flag: '🇾🇪', code: 'YE' },

  // --- Océanie (14 countries) ---
  { name: 'Australie',              flag: '🇦🇺', code: 'AU' },
  { name: 'Fidji',                  flag: '🇫🇯', code: 'FJ' },
  { name: 'Îles Marshall',          flag: '🇲🇭', code: 'MH' },
  { name: 'Îles Salomon',           flag: '🇸🇧', code: 'SB' },
  { name: 'Kiribati',               flag: '🇰🇮', code: 'KI' },
  { name: 'Micronésie',             flag: '🇫🇲', code: 'FM' },
  { name: 'Nauru',                  flag: '🇳🇷', code: 'NR' },
  { name: 'Nouvelle-Zélande',       flag: '🇳🇿', code: 'NZ' },
  { name: 'Palaos',                 flag: '🇵🇼', code: 'PW' },
  { name: 'Papouasie-Nouvelle-Guinée', flag: '🇵🇬', code: 'PG' },
  { name: 'Samoa',                  flag: '🇼🇸', code: 'WS' },
  { name: 'Tonga',                  flag: '🇹🇴', code: 'TO' },
  { name: 'Tuvalu',                 flag: '🇹🇻', code: 'TV' },
  { name: 'Vanuatu',                flag: '🇻🇺', code: 'VU' },
];

export const COUNTRY_MAP = Object.fromEntries(
  COUNTRIES.map(c => [c.name, c])
);

export function getCountryFlag(name: string): string {
  return COUNTRY_MAP[name]?.flag ?? '🌍';
}

// ============================================================
// LANGUAGES
// ============================================================

export const LANGUAGES = [
  { code: 'fr', label: 'Français',  flag: '🇫🇷' },
  { code: 'en', label: 'English',   flag: '🇬🇧' },
  { code: 'de', label: 'Deutsch',   flag: '🇩🇪' },
  { code: 'es', label: 'Español',   flag: '🇪🇸' },
  { code: 'pt', label: 'Português', flag: '🇵🇹' },
  { code: 'ar', label: 'العربية',     flag: '🇸🇦' },
  { code: 'ru', label: 'Русский',   flag: '🇷🇺' },
  { code: 'zh', label: '中文',        flag: '🇨🇳' },
  { code: 'hi', label: 'हिन्दी',       flag: '🇮🇳' },
];

export const LANGUAGE_MAP = Object.fromEntries(
  LANGUAGES.map(l => [l.code, l])
) as Record<string, (typeof LANGUAGES)[number]>;

export function getLanguageLabel(code: string): string {
  const lang = LANGUAGES.find(l => l.code === code);
  return lang ? `${lang.flag} ${lang.label}` : code;
}

export function getLanguageFlag(code: string): string {
  return LANGUAGES.find(l => l.code === code)?.flag ?? '🌍';
}

// ============================================================
// CONTENT ENGINE METRICS CONFIG
// ============================================================

export const CONTENT_METRICS_CONFIG = [
  { key: 'landing_pages',   label: 'Landing Pages',  icon: '🌍', color: '#A855F7' },
  { key: 'articles',        label: 'Articles',        icon: '📝', color: '#3B82F6' },
  { key: 'indexed_pages',   label: 'Indexées',        icon: '🔍', color: '#10B981' },
  { key: 'top10_positions', label: 'Top 10',          icon: '🏅', color: '#06B6D4' },
  { key: 'position_zero',   label: 'Position 0',      icon: '🏆', color: '#FFD60A' },
  { key: 'ai_cited',        label: 'Citées IA',       icon: '🤖', color: '#A855F7' },
  { key: 'daily_visits',    label: 'Visites/j',       icon: '👁', color: '#14B8A6' },
  { key: 'calls_generated', label: 'Appels',          icon: '📞', color: '#10B981' },
  { key: 'revenue_cents',   label: 'Revenue €',       icon: '💰', color: '#FFD60A' },
] as const;
