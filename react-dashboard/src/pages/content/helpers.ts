// Status colors and labels
export const STATUS_COLORS: Record<string, string> = {
  draft: 'bg-muted/20 text-muted',
  generating: 'bg-amber/20 text-amber animate-pulse',
  review: 'bg-blue-500/20 text-blue-400',
  scheduled: 'bg-violet/20 text-violet',
  published: 'bg-success/20 text-success',
  archived: 'bg-muted/20 text-muted',
  pending: 'bg-muted/20 text-muted',
  running: 'bg-success/20 text-success animate-pulse',
  paused: 'bg-amber/20 text-amber',
  completed: 'bg-success/20 text-success',
  cancelled: 'bg-danger/20 text-danger',
  failed: 'bg-danger/20 text-danger',
  skipped: 'bg-muted/20 text-muted line-through',
  ready: 'bg-blue-500/20 text-blue-400',
  generating_qa: 'bg-amber/20 text-amber animate-pulse',
  generating_article: 'bg-violet/20 text-violet animate-pulse',
};

export const STATUS_LABELS: Record<string, string> = {
  draft: 'Brouillon',
  generating: 'Generation...',
  review: 'Revue',
  scheduled: 'Planifie',
  published: 'Publie',
  archived: 'Archive',
  pending: 'En attente',
  running: 'En cours',
  paused: 'En pause',
  completed: 'Termine',
  cancelled: 'Annule',
  failed: 'Echoue',
  skipped: 'Ignore',
  ready: 'Pret',
  generating_qa: 'Generation Q&A',
  generating_article: 'Generation article',
};

export const CATEGORY_COLORS: Record<string, string> = {
  visa: 'bg-blue-500/20 text-blue-400',
  logement: 'bg-green-500/20 text-green-400',
  sante: 'bg-red-500/20 text-red-400',
  emploi: 'bg-purple-500/20 text-purple-400',
  transport: 'bg-yellow-500/20 text-yellow-400',
  education: 'bg-pink-500/20 text-pink-400',
  banque: 'bg-emerald-500/20 text-emerald-400',
  culture: 'bg-orange-500/20 text-orange-400',
  demarches: 'bg-cyan-500/20 text-cyan-400',
  telecom: 'bg-indigo-500/20 text-indigo-400',
};

export const LANGUAGES = [
  { value: 'fr', label: 'Francais', flag: '\uD83C\uDDEB\uD83C\uDDF7' },
  { value: 'en', label: 'English', flag: '\uD83C\uDDEC\uD83C\uDDE7' },
  { value: 'de', label: 'Deutsch', flag: '\uD83C\uDDE9\uD83C\uDDEA' },
  { value: 'es', label: 'Espanol', flag: '\uD83C\uDDEA\uD83C\uDDF8' },
  { value: 'pt', label: 'Portugues', flag: '\uD83C\uDDF5\uD83C\uDDF9' },
  { value: 'ru', label: 'Russkij', flag: '\uD83C\uDDF7\uD83C\uDDFA' },
  { value: 'zh', label: 'Zhongwen', flag: '\uD83C\uDDE8\uD83C\uDDF3' },
  { value: 'ar', label: 'Arabiyya', flag: '\uD83C\uDDF8\uD83C\uDDE6' },
  { value: 'hi', label: 'Hindi', flag: '\uD83C\uDDEE\uD83C\uDDF3' },
];

export const SOURCE_TYPES: Record<string, string> = {
  article_faq: 'FAQ Article',
  paa: 'PAA Google',
  scraped: 'Forum scrappe',
  manual: 'Manuel',
  ai_suggested: 'Suggestion IA',
};

export const inputClass = 'bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors';

export function seoColor(score: number): string {
  if (score >= 80) return 'text-success';
  if (score >= 50) return 'text-amber';
  return 'text-danger';
}

export function seoBarColor(score: number): string {
  if (score >= 80) return 'bg-success';
  if (score >= 50) return 'bg-amber';
  return 'bg-danger';
}

export function budgetColor(pct: number): string {
  if (pct >= 80) return 'bg-danger';
  if (pct >= 50) return 'bg-amber';
  return 'bg-success';
}

export function cents(n: number): string {
  return (n / 100).toFixed(2);
}

export function formatDate(iso: string | null): string {
  if (!iso) return '\u2014';
  return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

export function formatNumber(n: number): string {
  return n.toLocaleString('fr-FR');
}

export function truncate(s: string, max: number): string {
  return s.length > max ? s.slice(0, max) + '...' : s;
}

export function errMsg(err: unknown): string {
  if (err instanceof Error) return err.message;
  if (typeof err === 'object' && err !== null) {
    const e = err as Record<string, unknown>;
    if (e.response && typeof e.response === 'object') {
      const resp = e.response as Record<string, unknown>;
      if (resp.data && typeof resp.data === 'object') {
        const data = resp.data as Record<string, unknown>;
        if (typeof data.message === 'string') return data.message;
      }
    }
    if (typeof e.message === 'string') return e.message;
  }
  return 'Erreur inattendue';
}
