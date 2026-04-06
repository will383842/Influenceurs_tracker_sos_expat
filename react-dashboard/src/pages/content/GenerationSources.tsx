import { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../../api/client';

// ── Source definitions ─────────────────────────────────────
interface SourceDef {
  slug: string;
  label: string;
  description: string;
  icon: string;
  color: string;
  accentColor: string;
  contentType: string;       // label affiché (ex: "Guide (pilier)")
  blogCategory: string;      // catégorie destination blog
  inputQuality: 'full_content' | 'title_only' | 'structured';
}

const SOURCES: SourceDef[] = [
  {
    slug: 'fiche-pays',
    label: 'Fiches Pays',
    description: "Guides complets d'expatriation par pays. Visa, logement, santé, emploi, culture.",
    icon: '🌍',
    color: 'border-blue-500/30 hover:border-blue-500/60',
    accentColor: 'text-blue-400',
    contentType: 'guide',
    blogCategory: '/fiches-pays',
    inputQuality: 'full_content',
  },
  {
    slug: 'fiche-villes',
    label: 'Fiches Villes',
    description: "Articles dédiés aux villes — coût de la vie, quartiers, transports, expat life.",
    icon: '🏙️',
    color: 'border-cyan-500/30 hover:border-cyan-500/60',
    accentColor: 'text-cyan-400',
    contentType: 'guide_city',
    blogCategory: '/fiches-pays',
    inputQuality: 'full_content',
  },
  {
    slug: 'qa',
    label: 'Q&A',
    description: "Questions réelles posées par les expatriés. Optimisé AEO, voice search, featured snippets.",
    icon: '❓',
    color: 'border-violet/30 hover:border-violet/60',
    accentColor: 'text-violet-light',
    contentType: 'qa',
    blogCategory: '/fiches-thematiques',
    inputQuality: 'full_content',
  },
  {
    slug: 'fiches-pratiques',
    label: 'Fiches Pratiques',
    description: "Articles thématiques : visa, logement, santé, emploi, banque, transport, éducation.",
    icon: '📋',
    color: 'border-emerald-500/30 hover:border-emerald-500/60',
    accentColor: 'text-emerald-400',
    contentType: 'article',
    blogCategory: '/fiches-pratiques',
    inputQuality: 'full_content',
  },
  {
    slug: 'temoignages',
    label: 'Témoignages',
    description: "Récits authentiques d'expatriés. Contenu social proof, enrichi par retours terrain.",
    icon: '💬',
    color: 'border-pink-500/30 hover:border-pink-500/60',
    accentColor: 'text-pink-400',
    contentType: 'testimonial',
    blogCategory: '/fiches-thematiques',
    inputQuality: 'title_only',
  },
  {
    slug: 'annuaires',
    label: 'Annuaires',
    description: "Répertoires de services : avocats, médecins, agences, écoles par pays et ville.",
    icon: '📚',
    color: 'border-amber-500/30 hover:border-amber-500/60',
    accentColor: 'text-amber-400',
    contentType: 'directory',
    blogCategory: 'Onglet dédié',
    inputQuality: 'structured',
  },
  {
    slug: 'comparatifs',
    label: 'Comparatifs',
    description: "Comparaisons pays vs pays, services, coûts de la vie. Tables, scores, pros/cons.",
    icon: '⚖️',
    color: 'border-orange-500/30 hover:border-orange-500/60',
    accentColor: 'text-orange-400',
    contentType: 'comparative',
    blogCategory: '/fiches-thematiques',
    inputQuality: 'structured',
  },
  {
    slug: 'affiliation',
    label: 'Affiliation',
    description: "Contenus orientés conversion avec liens affiliés SOS-Expat. UTM trackés.",
    icon: '🔗',
    color: 'border-yellow-500/30 hover:border-yellow-500/60',
    accentColor: 'text-yellow-400',
    contentType: 'affiliation',
    blogCategory: '/affiliation',
    inputQuality: 'title_only',
  },
  {
    slug: 'chatters',
    label: 'Chatters',
    description: "Articles de recrutement chatters. Convaincre des candidats à rejoindre le programme.",
    icon: '💭',
    color: 'border-teal-500/30 hover:border-teal-500/60',
    accentColor: 'text-teal-400',
    contentType: 'outreach',
    blogCategory: '/programme',
    inputQuality: 'title_only',
  },
  {
    slug: 'admin-groups',
    label: 'Admin Groups',
    description: "Articles de recrutement admins de groupes. Angle communauté et flexibilité.",
    icon: '👥',
    color: 'border-indigo-500/30 hover:border-indigo-500/60',
    accentColor: 'text-indigo-400',
    contentType: 'outreach',
    blogCategory: '/programme',
    inputQuality: 'title_only',
  },
  {
    slug: 'bloggeurs',
    label: 'Bloggeurs',
    description: "Articles de recrutement bloggeurs partenaires SOS-Expat. Amplification SEO.",
    icon: '✍️',
    color: 'border-rose-500/30 hover:border-rose-500/60',
    accentColor: 'text-rose-400',
    contentType: 'outreach',
    blogCategory: '/programme',
    inputQuality: 'title_only',
  },
  {
    slug: 'avocats',
    label: 'Avocats',
    description: "Articles de recrutement pour attirer des avocats prestataires sur la plateforme.",
    icon: '⚖️',
    color: 'border-slate-400/30 hover:border-slate-400/60',
    accentColor: 'text-slate-300',
    contentType: 'outreach',
    blogCategory: '/programme',
    inputQuality: 'title_only',
  },
  {
    slug: 'expats-aidants',
    label: 'Expats Aidants',
    description: "Articles de recrutement pour attirer des expats aidants prestataires sur la plateforme.",
    icon: '🧳',
    color: 'border-sky-500/30 hover:border-sky-500/60',
    accentColor: 'text-sky-400',
    contentType: 'outreach',
    blogCategory: '/programme',
    inputQuality: 'title_only',
  },
  {
    slug: 'besoins-reels',
    label: 'Besoins Réels',
    description: "Requêtes longue traîne et besoins exprimés. Signaux faibles, intentions de recherche.",
    icon: '🎯',
    color: 'border-lime-500/30 hover:border-lime-500/60',
    accentColor: 'text-lime-400',
    contentType: 'qa_needs',
    blogCategory: '/fiches-thematiques',
    inputQuality: 'title_only',
  },
  {
    slug: 'art-mots-cles',
    label: 'Art Mots Clés',
    description: "Articles ciblant des mots-clés à fort volume. Optimisés pour le featured snippet.",
    icon: '🔑',
    color: 'border-violet-500/30 hover:border-violet-500/60',
    accentColor: 'text-violet-400',
    contentType: 'article',
    blogCategory: '/fiches-pratiques',
    inputQuality: 'title_only',
  },
  {
    slug: 'longues-traines',
    label: 'Longues Traînes',
    description: "Articles sur des requêtes longue traîne. Trafic qualifié à fort potentiel de conversion.",
    icon: '📐',
    color: 'border-lime-500/30 hover:border-lime-500/60',
    accentColor: 'text-lime-400',
    contentType: 'article',
    blogCategory: '/fiches-thematiques',
    inputQuality: 'title_only',
  },
  {
    slug: 'brand-content',
    label: 'Brand Content',
    description: "Contenus de marque SOS-Expat : présentation plateforme, témoignages, storytelling.",
    icon: '🏷️',
    color: 'border-amber-500/30 hover:border-amber-500/60',
    accentColor: 'text-amber-400',
    contentType: 'article',
    blogCategory: '/programme',
    inputQuality: 'title_only',
  },
];

// ── Local storage key ──────────────────────────────────────
const VISIBILITY_KEY = 'mc_source_visibility';

function loadVisibility(): Record<string, boolean> {
  try {
    const raw = localStorage.getItem(VISIBILITY_KEY);
    if (raw) return JSON.parse(raw);
  } catch { /* ignore */ }
  // Default: all visible
  const defaults: Record<string, boolean> = {};
  SOURCES.forEach(s => { defaults[s.slug] = true; });
  return defaults;
}

function saveVisibility(vis: Record<string, boolean>) {
  localStorage.setItem(VISIBILITY_KEY, JSON.stringify(vis));
}

// ── API types ──────────────────────────────────────────────
interface ApiCategory {
  slug: string;
  total_items: number;
  cleaned_items: number;
  ready_items: number;
  countries: number;
}

interface OverallStats {
  overall: { total: number; cleaned: number; ready: number; countries: number };
}

function fmt(n: number): string {
  return n.toLocaleString('fr-FR');
}

// ── Component ──────────────────────────────────────────────
export default function GenerationSources() {
  const navigate = useNavigate();
  const [apiStats, setApiStats]     = useState<Record<string, ApiCategory>>({});
  const [overall, setOverall]       = useState<OverallStats['overall'] | null>(null);
  const [statsLoading, setStatsLoading] = useState(true);

  // Visibility per source slug (persisted in localStorage)
  const [visibility, setVisibility] = useState<Record<string, boolean>>(loadVisibility);
  const [savingSlug, setSavingSlug] = useState<string | null>(null);

  useEffect(() => {
    Promise.all([
      api.get('/generation-sources/categories').catch(() => ({ data: [] })),
      api.get('/generation-sources/stats').catch(() => ({ data: null })),
    ]).then(([catRes, statsRes]) => {
      const cats: ApiCategory[] = catRes.data ?? [];
      const map: Record<string, ApiCategory> = {};
      cats.forEach(c => { map[c.slug] = c; });
      setApiStats(map);
      if (statsRes.data?.overall) setOverall(statsRes.data.overall);
    }).finally(() => setStatsLoading(false));
  }, []);

  // Toggle visibility with optimistic update + persist
  const toggleVisibility = useCallback(async (e: React.MouseEvent, slug: string) => {
    e.stopPropagation(); // Don't navigate to source detail
    const newValue = !visibility[slug];

    // Optimistic update
    const updated = { ...visibility, [slug]: newValue };
    setVisibility(updated);
    saveVisibility(updated);
    setSavingSlug(slug);

    // Try to persist to backend (optional — doesn't block if API not ready)
    try {
      await api.post(`/generation-sources/${slug}/visibility`, { visible: newValue });
    } catch {
      // Backend endpoint not implemented yet — localStorage is the source of truth
    } finally {
      setSavingSlug(null);
    }
  }, [visibility]);

  const visibleCount   = SOURCES.filter(s => visibility[s.slug] !== false).length;
  const invisibleCount = SOURCES.length - visibleCount;

  return (
    <div className="p-6 space-y-8">

      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-t1">Sources de génération</h1>
          <p className="text-t3 text-sm mt-1">
            Chaque source correspond à une taxonomie publiée sur{' '}
            <span className="text-t2">sos-expat.com/blog</span>
          </p>
        </div>

        {/* Summary counters */}
        <div className="flex gap-4 flex-shrink-0">
          {overall && !statsLoading && (
            <>
              <div className="text-right">
                <p className="text-xl font-bold text-t1">{fmt(overall.total)}</p>
                <p className="text-xs text-t3">items total</p>
              </div>
              <div className="text-right">
                <p className="text-xl font-bold text-emerald-400">{fmt(overall.ready)}</p>
                <p className="text-xs text-t3">prêts</p>
              </div>
            </>
          )}
          <div className="text-right">
            <p className="text-xl font-bold text-emerald-400">{visibleCount}</p>
            <p className="text-xs text-t3">visibles blog</p>
          </div>
          {invisibleCount > 0 && (
            <div className="text-right">
              <p className="text-xl font-bold text-red-400">{invisibleCount}</p>
              <p className="text-xs text-t3">masquées</p>
            </div>
          )}
        </div>
      </div>

      {/* Legend */}
      <div className="flex items-center gap-4 text-xs text-t3">
        <div className="flex items-center gap-1.5">
          <span className="w-3 h-3 rounded-full bg-emerald-500 inline-block" />
          Visible sur sos-expat.com/blog
        </div>
        <div className="flex items-center gap-1.5">
          <span className="w-3 h-3 rounded-full bg-red-500 inline-block" />
          Masquée (non publiée sur le blog)
        </div>
        <div className="flex items-center gap-1.5 text-t3/60">
          Cliquez sur le bouton pour basculer · Cliquez sur la carte pour explorer
        </div>
      </div>

      {/* Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        {SOURCES.map(source => {
          const stats      = apiStats[source.slug];
          const isVisible  = visibility[source.slug] !== false;
          const isSaving   = savingSlug === source.slug;

          return (
            <div
              key={source.slug}
              className={`
                group relative rounded-xl border bg-surface transition-all duration-200
                ${isVisible
                  ? source.color
                  : 'border-red-500/20 hover:border-red-500/40 opacity-60 hover:opacity-80'
                }
              `}
            >
              {/* ── Toggle button (top-right, outside click zone) ── */}
              <button
                onClick={e => toggleVisibility(e, source.slug)}
                disabled={isSaving}
                title={isVisible ? 'Visible sur le blog — cliquer pour masquer' : 'Masquée du blog — cliquer pour rendre visible'}
                className={`
                  absolute top-3 right-3 z-10
                  flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold
                  transition-all duration-200 border
                  ${isSaving ? 'opacity-50 cursor-wait' : 'cursor-pointer'}
                  ${isVisible
                    ? 'bg-emerald-500/15 border-emerald-500/40 text-emerald-400 hover:bg-emerald-500/25'
                    : 'bg-red-500/15 border-red-500/40 text-red-400 hover:bg-red-500/25'
                  }
                `}
              >
                {isSaving ? (
                  <span className="w-2 h-2 rounded-full border border-current border-t-transparent animate-spin inline-block" />
                ) : (
                  <span className={`w-2 h-2 rounded-full inline-block ${isVisible ? 'bg-emerald-400' : 'bg-red-400'}`} />
                )}
                {isVisible ? 'Visible' : 'Masquée'}
              </button>

              {/* ── Card body (clickable → navigate) ── */}
              <button
                onClick={() => navigate(`/content/sources/${source.slug}`)}
                className="w-full text-left p-5 pt-4"
              >
                {/* Icon + label */}
                <div className="flex items-start gap-3 mb-3 pr-20">
                  <span className="text-2xl leading-none mt-0.5">{source.icon}</span>
                  <div className="min-w-0">
                    <h3 className={`font-semibold text-[15px] transition-colors group-hover:text-white ${isVisible ? 'text-t1' : 'text-t2'}`}>
                      {source.label}
                    </h3>
                    <div className="flex items-center gap-1.5 flex-wrap mt-0.5">
                      <span className="text-[10px] text-t3 bg-surface2 px-2 py-0.5 rounded-full font-mono">
                        {source.contentType}
                      </span>
                      <span className="text-[10px] text-t3/60">→</span>
                      <span className="text-[10px] text-t3 bg-surface2 px-2 py-0.5 rounded-full">
                        {source.blogCategory}
                      </span>
                      <span className={`text-[10px] px-1.5 py-0.5 rounded-full ${
                        source.inputQuality === 'full_content' ? 'bg-emerald-500/10 text-emerald-400'
                        : source.inputQuality === 'structured' ? 'bg-blue-500/10 text-blue-400'
                        : 'bg-amber-500/10 text-amber-400'
                      }`}>
                        {source.inputQuality === 'full_content' ? '📄' : source.inputQuality === 'structured' ? '🗂' : '📝'}
                      </span>
                    </div>
                  </div>
                </div>

                {/* Description */}
                <p className="text-xs text-t3 leading-relaxed mb-4 line-clamp-2">
                  {source.description}
                </p>

                {/* Stats footer */}
                {statsLoading ? (
                  <div className="flex gap-3">
                    <div className="h-3 w-20 bg-surface2 rounded animate-pulse" />
                    <div className="h-3 w-14 bg-surface2 rounded animate-pulse" />
                  </div>
                ) : stats ? (
                  <div className="flex items-center gap-3 text-xs">
                    <span className={`font-bold ${source.accentColor}`}>
                      {fmt(stats.total_items)} items
                    </span>
                    {stats.ready_items > 0 && (
                      <span className="text-emerald-400">{fmt(stats.ready_items)} prêts</span>
                    )}
                    {stats.countries > 0 && (
                      <span className="text-t3">{stats.countries} pays</span>
                    )}
                    <span className="ml-auto text-t3 flex items-center gap-1 group-hover:text-t1 transition-colors">
                      Explorer
                      <svg className="w-3 h-3 group-hover:translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                      </svg>
                    </span>
                  </div>
                ) : (
                  <div className="flex items-center justify-between text-xs">
                    <div className="flex items-center gap-2 text-t3">
                      <span className="w-1.5 h-1.5 rounded-full bg-amber-400 inline-block" />
                      À configurer
                    </div>
                    <span className="text-t3 flex items-center gap-1 group-hover:text-t1 transition-colors">
                      Explorer
                      <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                      </svg>
                    </span>
                  </div>
                )}
              </button>
            </div>
          );
        })}
      </div>
    </div>
  );
}
