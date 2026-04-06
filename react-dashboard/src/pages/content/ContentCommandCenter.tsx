import { useEffect, useState, useCallback, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../../api/client';
import { toast } from '../../components/Toast';

// ── Source definitions ─────────────────────────────────────────
const SOURCE_DEFS = [
  { slug: 'fiche-pays',       label: 'Fiches Pays',      icon: '🌍', accent: 'text-blue-400',     contentType: 'guide',         blogCategory: '/fiches-pays',        inputQuality: 'full_content' as const, defaultWeight: 20 },
  { slug: 'fiche-villes',     label: 'Fiches Villes',    icon: '🏙️', accent: 'text-cyan-400',     contentType: 'guide_city',    blogCategory: '/fiches-pays',        inputQuality: 'full_content' as const, defaultWeight: 8  },
  { slug: 'qa',               label: 'Q&A',              icon: '❓',  accent: 'text-violet-light', contentType: 'qa',            blogCategory: '/fiches-thematiques', inputQuality: 'mixed'        as const, defaultWeight: 15 },
  { slug: 'fiches-pratiques', label: 'Fiches Pratiques', icon: '📋', accent: 'text-emerald-400',  contentType: 'article',       blogCategory: '/fiches-pratiques',   inputQuality: 'full_content' as const, defaultWeight: 10 },
  { slug: 'temoignages',      label: 'Témoignages',      icon: '💬', accent: 'text-pink-400',     contentType: 'testimonial',   blogCategory: '/fiches-thematiques', inputQuality: 'title_only'  as const,  defaultWeight: 5  },
  { slug: 'comparatifs',      label: 'Comparatifs',      icon: '⚖️',  accent: 'text-orange-400',   contentType: 'comparative',   blogCategory: '/fiches-thematiques', inputQuality: 'structured'  as const,  defaultWeight: 4  },
  { slug: 'besoins-reels',    label: 'Besoins Réels',    icon: '🎯', accent: 'text-lime-400',     contentType: 'qa_needs',      blogCategory: '/fiches-thematiques', inputQuality: 'title_only'  as const,  defaultWeight: 9  },
  { slug: 'chatters',         label: 'Chatters',         icon: '💭', accent: 'text-teal-400',     contentType: 'outreach',      blogCategory: '/programme',          inputQuality: 'title_only'  as const,  defaultWeight: 5  },
  { slug: 'admin-groups',     label: 'Admin Groups',     icon: '👥', accent: 'text-indigo-400',   contentType: 'outreach',      blogCategory: '/programme',          inputQuality: 'title_only'  as const,  defaultWeight: 5  },
  { slug: 'bloggeurs',        label: 'Bloggeurs',        icon: '✍️', accent: 'text-rose-400',     contentType: 'outreach',      blogCategory: '/programme',          inputQuality: 'title_only'  as const,  defaultWeight: 5  },
  { slug: 'avocats',          label: 'Avocats',          icon: '⚖️', accent: 'text-slate-300',    contentType: 'outreach',      blogCategory: '/programme',          inputQuality: 'title_only'  as const,  defaultWeight: 3  },
  { slug: 'expats-aidants',   label: 'Expats Aidants',   icon: '🧳', accent: 'text-sky-400',      contentType: 'outreach',      blogCategory: '/programme',          inputQuality: 'title_only'  as const,  defaultWeight: 3  },
  { slug: 'affiliation',      label: 'Affiliation',      icon: '🔗', accent: 'text-yellow-400',   contentType: 'affiliation',   blogCategory: '/affiliation',        inputQuality: 'title_only'  as const,  defaultWeight: 5  },
  { slug: 'annuaires',        label: 'Annuaires',        icon: '📚', accent: 'text-amber-400',    contentType: 'directory',     blogCategory: 'Section dédiée',      inputQuality: 'structured'  as const,  defaultWeight: 0  },
] as const;

type ScheduleMode = 'percentage' | 'manual';

interface ApiCategory  { slug: string; total_items: number; cleaned_items: number; ready_items: number; countries: number; }
interface PipelineSourceStatus {
  slug: string; pipeline_status: 'active' | 'paused' | 'generating' | 'error' | 'idle';
  generated_today: number; generated_week: number; quota_daily: number;
  last_generated_at: string | null; current_running: number; is_paused: boolean; is_visible: boolean;
}
interface PipelineGlobal {
  is_running: boolean; currently_generating: number; queue_size: number;
  last_activity: string | null; errors_count: number; active_sources: number; generated_today_total: number;
}

// ── Persistence keys ──────────────────────────────────────────
const VIS_KEY    = 'mc_source_visibility';
const WEIGHT_KEY = 'mc_source_weights';
const TOTAL_KEY  = 'mc_daily_total';
const MODE_KEY   = 'mc_schedule_mode';

function loadVisibility(): Record<string, boolean> {
  try { const r = localStorage.getItem(VIS_KEY); if (r) return JSON.parse(r); } catch { /* */ }
  const d: Record<string, boolean> = {};
  SOURCE_DEFS.forEach(s => { d[s.slug] = true; });
  return d;
}
function saveVisibility(v: Record<string, boolean>) { localStorage.setItem(VIS_KEY, JSON.stringify(v)); }

function loadWeights(): Record<string, number> {
  try { const r = localStorage.getItem(WEIGHT_KEY); if (r) return JSON.parse(r); } catch { /* */ }
  const d: Record<string, number> = {};
  SOURCE_DEFS.forEach(s => { d[s.slug] = s.defaultWeight; });
  return d;
}
function saveWeights(w: Record<string, number>) { localStorage.setItem(WEIGHT_KEY, JSON.stringify(w)); }

function loadDailyTotal(): number {
  try { const r = localStorage.getItem(TOTAL_KEY); if (r) return parseInt(r, 10) || 20; } catch { /* */ }
  return 20;
}
function saveDailyTotal(n: number) { localStorage.setItem(TOTAL_KEY, String(n)); }

function loadScheduleMode(): ScheduleMode {
  const r = localStorage.getItem(MODE_KEY);
  return (r === 'manual' ? 'manual' : 'percentage') as ScheduleMode;
}
function saveScheduleMode(m: ScheduleMode) { localStorage.setItem(MODE_KEY, m); }

// ── Helpers ───────────────────────────────────────────────────
function fmt(n: number) { return n.toLocaleString('fr-FR'); }

function relativeTime(iso: string | null): string {
  if (!iso) return '—';
  const m = Math.floor((Date.now() - new Date(iso).getTime()) / 60000);
  if (m < 1) return 'à l\'instant';
  if (m < 60) return `il y a ${m}min`;
  const h = Math.floor(m / 60);
  if (h < 24) return `il y a ${h}h`;
  return `il y a ${Math.floor(h / 24)}j`;
}

/** Distribute totalArticles proportionally across sources by weight */
function distributeByWeight(weights: Record<string, number>, total: number): Record<string, number> {
  const totalWeight = Object.values(weights).reduce((s, w) => s + w, 0);
  if (totalWeight === 0) return Object.fromEntries(SOURCE_DEFS.map(s => [s.slug, 0]));
  const floats: Record<string, number> = {};
  const floors: Record<string, number> = {};
  let remainder = total;
  SOURCE_DEFS.forEach(s => {
    const exact = ((weights[s.slug] ?? 0) / totalWeight) * total;
    floats[s.slug] = exact;
    floors[s.slug] = Math.floor(exact);
    remainder -= floors[s.slug];
  });
  // distribute remainder to largest fractional parts
  const sorted = [...SOURCE_DEFS].sort((a, b) => (floats[b.slug] % 1) - (floats[a.slug] % 1));
  sorted.forEach(s => { if (remainder > 0 && floors[s.slug] !== undefined) { floors[s.slug]++; remainder--; } });
  return floors;
}

const INPUT_QUALITY_LABEL: Record<string, { icon: string; label: string; color: string }> = {
  full_content: { icon: '📄', label: 'Contenu scrapé',  color: 'text-emerald-400' },
  structured:   { icon: '🗂',  label: 'Structuré',       color: 'text-blue-400'    },
  title_only:   { icon: '📝', label: 'Titre seul',       color: 'text-amber-400'   },
  mixed:        { icon: '📄📝', label: 'Mixte',          color: 'text-violet-light' },
};

const PIPELINE_STATUS: Record<string, { label: string; dot: string; badge: string }> = {
  generating: { label: 'En cours',    dot: 'bg-emerald-400 animate-pulse', badge: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30' },
  active:     { label: 'Actif',       dot: 'bg-emerald-400',               badge: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30' },
  paused:     { label: 'En pause',    dot: 'bg-amber-400',                 badge: 'bg-amber-500/15 text-amber-400 border-amber-500/30'       },
  error:      { label: 'Erreur',      dot: 'bg-red-400',                   badge: 'bg-red-500/15 text-red-400 border-red-500/30'             },
  idle:       { label: 'En attente',  dot: 'bg-surface2',                  badge: 'bg-surface2 text-t3 border-border'                        },
};

// ── Component ─────────────────────────────────────────────────
export default function ContentCommandCenter() {
  const navigate = useNavigate();

  // API data
  const [itemStats,      setItemStats]      = useState<Record<string, ApiCategory>>({});
  const [pipelineSrc,    setPipelineSrc]    = useState<Record<string, PipelineSourceStatus>>({});
  const [globalPipeline, setGlobalPipeline] = useState<PipelineGlobal | null>(null);
  const [loading,        setLoading]        = useState(true);
  const [lastRefresh,    setLastRefresh]    = useState(new Date());
  const [countdown,      setCountdown]      = useState(30);

  // Schedule config (persisted)
  const [dailyTotal,    setDailyTotal]    = useState<number>(loadDailyTotal);
  const [scheduleMode,  setScheduleMode]  = useState<ScheduleMode>(loadScheduleMode);
  const [weights,       setWeights]       = useState<Record<string, number>>(loadWeights);
  const [visibility,    setVisibility]    = useState<Record<string, boolean>>(loadVisibility);

  // UI state
  const [editingSlug,    setEditingSlug]    = useState<string | null>(null);
  const [editingVal,     setEditingVal]     = useState('');
  const [editingTotal,   setEditingTotal]   = useState(false);
  const [actionLoading,  setActionLoading]  = useState<Record<string, boolean>>({});
  const [savingVis,      setSavingVis]      = useState<string | null>(null);
  const [savingWeight,   setSavingWeight]   = useState<string | null>(null);
  const [savingTotal,    setSavingTotal]    = useState(false);

  const editInputRef  = useRef<HTMLInputElement>(null);
  const totalInputRef = useRef<HTMLInputElement>(null);

  // Computed: articles per source based on weights + daily total
  const calculatedQuotas = distributeByWeight(weights, dailyTotal);
  const totalWeightSum   = Object.values(weights).reduce((s, w) => s + w, 0);

  // ── Load ──────────────────────────────────────────────────
  const loadData = useCallback(async (silent = false) => {
    if (!silent) setLoading(true);
    try {
      const [catRes, pipelineRes, schedulerRes] = await Promise.allSettled([
        api.get('/generation-sources/categories'),
        api.get('/generation-sources/command-center'),
        api.get('/generation-sources/scheduler-config'),
      ]);

      if (catRes.status === 'fulfilled') {
        const map: Record<string, ApiCategory> = {};
        (catRes.value.data ?? []).forEach((c: ApiCategory) => { map[c.slug] = c; });
        setItemStats(map);
      }

      if (pipelineRes.status === 'fulfilled' && pipelineRes.value.data) {
        const data = pipelineRes.value.data;
        if (data.sources) {
          const srcMap: Record<string, PipelineSourceStatus> = {};
          (data.sources as PipelineSourceStatus[]).forEach(s => { srcMap[s.slug] = s; });
          setPipelineSrc(srcMap);
          // Sync visibility from API
          const apiVis = { ...loadVisibility() };
          Object.values(srcMap).forEach(s => { if (s.is_visible !== undefined) apiVis[s.slug] = s.is_visible; });
          setVisibility(apiVis); saveVisibility(apiVis);
        }
        if (data.pipeline) setGlobalPipeline(data.pipeline);
      }

      if (schedulerRes.status === 'fulfilled' && schedulerRes.value.data?.preview) {
        const preview = schedulerRes.value.data.preview;
        if (preview.total) { setDailyTotal(preview.total); saveDailyTotal(preview.total); }
        if (preview.mode) { setScheduleMode(preview.mode); saveScheduleMode(preview.mode); }
      }
    } catch { /* silent */ } finally {
      if (!silent) setLoading(false);
      setLastRefresh(new Date());
      setCountdown(30);
    }
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  useEffect(() => {
    const tick = setInterval(() => {
      setCountdown(c => { if (c <= 1) { loadData(true); return 30; } return c - 1; });
    }, 1000);
    return () => clearInterval(tick);
  }, [loadData]);

  useEffect(() => { if (editingSlug && editInputRef.current) editInputRef.current.focus(); }, [editingSlug]);
  useEffect(() => { if (editingTotal && totalInputRef.current) totalInputRef.current.focus(); }, [editingTotal]);

  // ── Handlers ─────────────────────────────────────────────
  const handleTriggerAll = useCallback(async () => {
    setActionLoading(p => ({ ...p, _all: true }));
    try {
      await api.post('/generation-sources/trigger-all', { total: dailyTotal });
      toast('success', `Pipeline lancé — ${dailyTotal} articles répartis sur les sources actives`);
      setTimeout(() => loadData(true), 1500);
    } catch {
      toast('error', 'Impossible de lancer le pipeline');
    } finally {
      setActionLoading(p => ({ ...p, _all: false }));
    }
  }, [loadData, dailyTotal]);

  const handleTrigger = useCallback(async (slug: string) => {
    setActionLoading(p => ({ ...p, [slug]: true }));
    try {
      await api.post(`/generation-sources/${slug}/trigger`);
      toast('success', `Génération lancée pour ${SOURCE_DEFS.find(s => s.slug === slug)?.label}`);
      setTimeout(() => loadData(true), 1500);
    } catch {
      toast('error', 'Erreur lors du lancement');
    } finally {
      setActionLoading(p => ({ ...p, [slug]: false }));
    }
  }, [loadData]);

  const handlePause = useCallback(async (slug: string, isPaused: boolean) => {
    setActionLoading(p => ({ ...p, [`pause_${slug}`]: true }));
    try {
      const res = await api.post(`/generation-sources/${slug}/pause`, { paused: !isPaused });
      const label = SOURCE_DEFS.find(s => s.slug === slug)?.label ?? slug;
      toast('success', (res.data?.is_paused ?? !isPaused) ? `${label} mis en pause` : `${label} repris`);
      loadData(true);
    } catch {
      toast('error', 'Impossible de modifier l\'état');
    } finally {
      setActionLoading(p => ({ ...p, [`pause_${slug}`]: false }));
    }
  }, [loadData]);

  const handleVisibilityToggle = useCallback(async (e: React.MouseEvent, slug: string) => {
    e.stopPropagation();
    const newVal = !visibility[slug];
    const updated = { ...visibility, [slug]: newVal };
    setVisibility(updated); saveVisibility(updated);
    setSavingVis(slug);
    try { await api.post(`/generation-sources/${slug}/visibility`, { visible: newVal }); }
    catch { /* localStorage is truth */ } finally { setSavingVis(null); }
  }, [visibility]);

  const commitDailyTotal = useCallback(async (val: number) => {
    const clamped = Math.max(1, Math.min(500, val || 20));
    setDailyTotal(clamped); saveDailyTotal(clamped);
    setEditingTotal(false); setSavingTotal(true);
    try {
      await api.post('/generation-sources/scheduler-config', { total_daily: clamped, schedule_mode: scheduleMode });
    } catch { /* localStorage is truth */ } finally { setSavingTotal(false); }
  }, [scheduleMode]);

  const commitWeight = useCallback(async (slug: string, raw: string) => {
    const val = Math.max(0, Math.min(100, parseInt(raw, 10) || 0));
    const updated = { ...weights, [slug]: val };
    setWeights(updated); saveWeights(updated);
    setEditingSlug(null); setSavingWeight(slug);
    try { await api.patch(`/generation-sources/${slug}/weight`, { weight: val }); }
    catch { /* localStorage is truth */ } finally { setSavingWeight(null); }
  }, [weights]);

  const adjustWeight = useCallback((slug: string, delta: number) => {
    const val = Math.max(0, Math.min(100, (weights[slug] ?? 0) + delta));
    const updated = { ...weights, [slug]: val };
    setWeights(updated); saveWeights(updated);
    setSavingWeight(slug);
    api.patch(`/generation-sources/${slug}/weight`, { weight: val })
      .catch(() => {}).finally(() => setSavingWeight(null));
  }, [weights]);

  // ── Computed globals ──────────────────────────────────────
  const totalItems   = Object.values(itemStats).reduce((s, c) => s + c.total_items, 0);
  const totalReady   = Object.values(itemStats).reduce((s, c) => s + c.ready_items, 0);
  const todayTotal   = globalPipeline?.generated_today_total ?? Object.values(pipelineSrc).reduce((s, c) => s + (c.generated_today ?? 0), 0);
  const isRunning    = globalPipeline?.is_running ?? false;
  const currentlyGen = globalPipeline?.currently_generating ?? 0;
  const activeSources = SOURCE_DEFS.filter(s => (weights[s.slug] ?? 0) > 0 && visibility[s.slug] !== false && s.slug !== 'annuaires').length;

  return (
    <div className="p-6 space-y-5 min-h-screen">

      {/* ── HEADER ── */}
      <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-t1 flex items-center gap-2">⚡ Content Command Center</h1>
          <p className="text-t3 text-sm mt-1">Pilotez la génération de contenu depuis une seule interface</p>
        </div>
        <div className="flex items-center gap-2 flex-shrink-0">
          <button
            onClick={() => loadData(true)}
            className="px-3 py-1.5 bg-surface2 hover:bg-border text-t3 hover:text-t1 text-xs rounded-lg border border-border transition-colors flex items-center gap-1.5"
          >
            <span className={countdown <= 5 ? 'text-amber-400' : 'text-t3'}>{countdown}s</span> ↻
          </button>
          <button
            onClick={handleTriggerAll}
            disabled={actionLoading._all}
            className="px-4 py-1.5 bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-400 text-sm font-semibold rounded-lg border border-emerald-500/30 transition-colors disabled:opacity-50 flex items-center gap-2"
          >
            {actionLoading._all ? <span className="w-3 h-3 border border-current border-t-transparent rounded-full animate-spin inline-block" /> : '▶'}
            Lancer aujourd'hui
          </button>
        </div>
      </div>

      {/* ── DAILY TARGET CONFIG ── */}
      <div className="bg-surface border border-border rounded-xl p-4 flex flex-wrap items-center gap-6">
        {/* Total articles / jour */}
        <div className="flex items-center gap-3">
          <span className="text-t3 text-sm">Objectif du jour :</span>
          {editingTotal ? (
            <input
              ref={totalInputRef}
              type="number" min={1} max={500}
              defaultValue={dailyTotal}
              onBlur={e => commitDailyTotal(parseInt(e.target.value, 10))}
              onKeyDown={e => { if (e.key === 'Enter') commitDailyTotal(parseInt((e.target as HTMLInputElement).value, 10)); if (e.key === 'Escape') setEditingTotal(false); }}
              className="w-20 text-center text-lg font-bold text-t1 bg-bg border border-violet rounded-lg py-1 focus:outline-none"
            />
          ) : (
            <button
              onClick={() => setEditingTotal(true)}
              className={`flex items-center gap-1.5 text-2xl font-bold transition-colors ${savingTotal ? 'text-amber-400 animate-pulse' : 'text-violet-light hover:text-t1'}`}
            >
              {dailyTotal}
              <span className="text-sm font-normal text-t3">articles/jour</span>
            </button>
          )}
          <span className="text-t3 text-xs">(cliquer pour modifier)</span>
        </div>

        {/* Mode */}
        <div className="flex items-center gap-2">
          <span className="text-t3 text-xs">Mode :</span>
          <div className="flex rounded-lg overflow-hidden border border-border">
            {(['percentage', 'manual'] as const).map(m => (
              <button
                key={m}
                onClick={() => { setScheduleMode(m); saveScheduleMode(m); api.post('/generation-sources/scheduler-config', { total_daily: dailyTotal, schedule_mode: m }).catch(() => {}); }}
                className={`px-3 py-1 text-xs font-semibold transition-colors ${scheduleMode === m ? 'bg-violet/20 text-violet-light' : 'bg-surface2 text-t3 hover:text-t2'}`}
              >
                {m === 'percentage' ? '% Répartition' : 'Manuel'}
              </button>
            ))}
          </div>
        </div>

        {/* Total weight indicator */}
        {scheduleMode === 'percentage' && (
          <div className="flex items-center gap-2 ml-auto">
            <span className="text-t3 text-xs">Somme des %</span>
            <span className={`text-sm font-bold ${totalWeightSum === 100 ? 'text-emerald-400' : totalWeightSum < 100 ? 'text-amber-400' : 'text-red-400'}`}>
              {totalWeightSum}%
            </span>
            {totalWeightSum !== 100 && (
              <span className="text-xs text-amber-400">
                {totalWeightSum < 100 ? `(${100 - totalWeightSum}% non assignés)` : `(${totalWeightSum - 100}% en trop)`}
              </span>
            )}
          </div>
        )}
      </div>

      {/* ── GLOBAL STATS ── */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        {[
          { label: 'Items disponibles',    value: fmt(totalItems),   color: 'text-t1',          sub: 'dans toutes les sources' },
          { label: 'Prêts à générer',      value: fmt(totalReady),   color: 'text-emerald-400', sub: totalItems > 0 ? `${Math.round(totalReady/totalItems*100)}% du total` : '—' },
          { label: 'Générés aujourd\'hui', value: todayTotal > 0 ? fmt(todayTotal) : '—', color: 'text-violet-light', sub: `objectif : ${dailyTotal}` },
          { label: 'Sources actives',      value: String(activeSources), color: 'text-blue-400', sub: `sur ${SOURCE_DEFS.length - 1} sources` },
        ].map(stat => (
          <div key={stat.label} className="bg-surface border border-border rounded-xl p-4">
            <p className={`text-xl font-bold ${stat.color}`}>{stat.value}</p>
            <p className="text-t1 text-sm font-medium mt-0.5">{stat.label}</p>
            <p className="text-t3 text-xs mt-0.5">{stat.sub}</p>
          </div>
        ))}
      </div>

      {/* ── PIPELINE STATUS BAR ── */}
      <div className={`rounded-xl border px-5 py-3 flex items-center justify-between gap-4 ${
        isRunning && currentlyGen > 0 ? 'bg-emerald-500/5 border-emerald-500/20'
        : globalPipeline?.errors_count ? 'bg-red-500/5 border-red-500/20'
        : 'bg-surface border-border'
      }`}>
        <div className="flex items-center gap-3">
          <span className={`w-2.5 h-2.5 rounded-full flex-shrink-0 ${isRunning && currentlyGen > 0 ? 'bg-emerald-400 animate-pulse' : globalPipeline?.errors_count ? 'bg-red-400' : 'bg-surface2'}`} />
          <div>
            {currentlyGen > 0 ? (
              <p className="text-sm font-semibold text-emerald-400">
                Pipeline actif · {currentlyGen} article{currentlyGen > 1 ? 's' : ''} en génération
                {globalPipeline?.queue_size ? ` · ${globalPipeline.queue_size} en file` : ''}
              </p>
            ) : globalPipeline?.errors_count ? (
              <p className="text-sm font-semibold text-red-400">⚠ {globalPipeline.errors_count} erreur{globalPipeline.errors_count > 1 ? 's' : ''}</p>
            ) : (
              <p className="text-sm text-t2">Pipeline en attente · Prêt à générer</p>
            )}
            {globalPipeline?.last_activity && (
              <p className="text-xs text-t3 mt-0.5">Dernière activité : {relativeTime(globalPipeline.last_activity)}</p>
            )}
          </div>
        </div>
        <p className="text-xs text-t3 flex-shrink-0">Actualisé {relativeTime(lastRefresh.toISOString())}</p>
      </div>

      {/* ── LÉGENDE ── */}
      <div className="flex flex-wrap items-center gap-4 text-xs text-t3">
        {Object.entries(INPUT_QUALITY_LABEL).map(([k, v]) => (
          <div key={k} className="flex items-center gap-1"><span>{v.icon}</span><span className={v.color}>{v.label}</span></div>
        ))}
        <span className="text-t3/40 ml-auto">
          {scheduleMode === 'percentage' ? 'Modifiez le % pour redistribuer — cliquez sur le total pour le changer' : 'Mode manuel — quotas indépendants par source'}
        </span>
      </div>

      {/* ── TABLE ── */}
      <div className="bg-surface border border-border rounded-xl overflow-hidden">
        {loading ? (
          <div className="p-12 text-center text-t3 text-sm animate-pulse">Chargement du Command Center...</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm min-w-[960px]">
              <thead>
                <tr className="text-t3 text-[11px] uppercase tracking-wider border-b border-border bg-surface2/40">
                  <th className="text-left py-3 px-4">Source</th>
                  <th className="text-left py-3 px-3">Destination</th>
                  <th className="text-left py-3 px-3">Matière</th>
                  <th className="text-right py-3 px-3">Prêts / Total</th>
                  <th className="text-right py-3 px-3">Générés</th>
                  <th className="text-center py-3 px-3">
                    {scheduleMode === 'percentage' ? '% du total' : 'Quota/j'}
                  </th>
                  <th className="text-center py-3 px-3 text-violet-light">Articles/jour</th>
                  <th className="text-center py-3 px-3">Pipeline</th>
                  <th className="text-center py-3 px-3">Blog</th>
                  <th className="text-right py-3 px-4">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border/30">
                {SOURCE_DEFS.map(src => {
                  const items        = itemStats[src.slug];
                  const pipeline     = pipelineSrc[src.slug];
                  const weight       = weights[src.slug] ?? src.defaultWeight;
                  const artPerDay    = calculatedQuotas[src.slug] ?? 0;
                  const isVisible    = visibility[src.slug] !== false;
                  const isEditing    = editingSlug === src.slug;
                  const isSavingW    = savingWeight === src.slug;
                  const isVSaving    = savingVis === src.slug;
                  const isLaunching  = actionLoading[src.slug];
                  const isPausing    = actionLoading[`pause_${src.slug}`];
                  const isPaused     = pipeline?.is_paused ?? (weight === 0);
                  const pipeStatus   = pipeline?.pipeline_status ?? (weight > 0 && !isPaused ? 'idle' : 'paused');
                  const pCfg         = PIPELINE_STATUS[pipeStatus] ?? PIPELINE_STATUS.idle;
                  const iq           = INPUT_QUALITY_LABEL[src.inputQuality] ?? INPUT_QUALITY_LABEL.title_only;
                  const readyPct     = items ? Math.round(items.ready_items / Math.max(items.total_items, 1) * 100) : 0;
                  const isAnnuaires  = src.slug === 'annuaires';

                  return (
                    <tr key={src.slug} className={`transition-colors hover:bg-surface2/20 ${!isVisible ? 'opacity-40' : ''}`}>

                      {/* Source */}
                      <td className="py-3 px-4">
                        <div className="flex items-center gap-2.5">
                          <span className="text-xl leading-none flex-shrink-0">{src.icon}</span>
                          <div>
                            <p className="font-semibold text-t1 text-[13px] leading-tight">{src.label}</p>
                            <span className="text-[10px] text-t3 font-mono bg-surface2 px-1.5 py-0.5 rounded">{src.contentType}</span>
                          </div>
                        </div>
                      </td>

                      {/* Destination */}
                      <td className="py-3 px-3">
                        <span className="text-[11px] text-t3 bg-surface2 px-2 py-0.5 rounded whitespace-nowrap">{src.blogCategory}</span>
                      </td>

                      {/* Matière */}
                      <td className="py-3 px-3">
                        <div className="flex items-center gap-1.5">
                          <span className="text-base leading-none">{iq.icon}</span>
                          <span className={`text-[11px] ${iq.color}`}>{iq.label}</span>
                        </div>
                      </td>

                      {/* Prêts / Total */}
                      <td className="py-3 px-3">
                        {items ? (
                          <div className="text-right">
                            <p className="text-[13px] font-semibold text-t1">
                              <span className="text-emerald-400">{fmt(items.ready_items)}</span>
                              <span className="text-t3 font-normal"> / {fmt(items.total_items)}</span>
                            </p>
                            <div className="h-1 w-20 bg-surface2 rounded-full mt-1.5 ml-auto overflow-hidden">
                              <div className={`h-full rounded-full transition-all ${readyPct >= 70 ? 'bg-emerald-400' : readyPct >= 30 ? 'bg-amber-400' : 'bg-red-400'}`} style={{ width: `${readyPct}%` }} />
                            </div>
                          </div>
                        ) : <p className="text-t3 text-xs text-right">—</p>}
                      </td>

                      {/* Générés */}
                      <td className="py-3 px-3 text-right">
                        {pipeline ? (
                          <div>
                            <p className="text-[13px] font-bold text-violet-light">{fmt(pipeline.generated_today)}</p>
                            <p className="text-[10px] text-t3">{fmt(pipeline.generated_week)}/sem</p>
                          </div>
                        ) : <span className="text-t3 text-xs">—</span>}
                      </td>

                      {/* % ou quota */}
                      <td className="py-3 px-3">
                        {isAnnuaires ? (
                          <p className="text-center text-t3 text-xs">N/A</p>
                        ) : (
                          <div className="flex items-center justify-center gap-1">
                            <button onClick={() => adjustWeight(src.slug, -1)} disabled={weight <= 0 || isSavingW}
                              className="w-5 h-5 flex items-center justify-center bg-surface2 hover:bg-border text-t3 hover:text-t1 rounded text-xs font-bold disabled:opacity-30 transition-colors">−</button>

                            {isEditing ? (
                              <input ref={editInputRef} type="number" min={0} max={100}
                                value={editingVal}
                                onChange={e => setEditingVal(e.target.value)}
                                onBlur={() => commitWeight(src.slug, editingVal)}
                                onKeyDown={e => { if (e.key === 'Enter') commitWeight(src.slug, editingVal); if (e.key === 'Escape') setEditingSlug(null); }}
                                className="w-12 text-center text-xs text-t1 bg-bg border border-violet rounded py-0.5 focus:outline-none"
                              />
                            ) : (
                              <button onClick={() => { setEditingSlug(src.slug); setEditingVal(String(weight)); }}
                                title="Cliquer pour éditer"
                                className={`w-12 text-center text-xs font-bold rounded py-0.5 hover:bg-surface2 transition-colors ${isSavingW ? 'text-amber-400 animate-pulse' : 'text-t1'}`}
                              >
                                {scheduleMode === 'percentage' ? `${weight}%` : weight}
                              </button>
                            )}

                            <button onClick={() => adjustWeight(src.slug, +1)} disabled={weight >= 100 || isSavingW}
                              className="w-5 h-5 flex items-center justify-center bg-surface2 hover:bg-border text-t3 hover:text-t1 rounded text-xs font-bold disabled:opacity-30 transition-colors">+</button>
                          </div>
                        )}
                      </td>

                      {/* Articles calculés / jour */}
                      <td className="py-3 px-3 text-center">
                        {isAnnuaires ? (
                          <span className="text-t3 text-xs">—</span>
                        ) : (
                          <div>
                            <span className={`text-base font-bold ${artPerDay > 0 ? 'text-violet-light' : 'text-t3'}`}>{artPerDay}</span>
                            {pipeline?.last_generated_at && (
                              <p className="text-[10px] text-t3 mt-0.5">{relativeTime(pipeline.last_generated_at)}</p>
                            )}
                          </div>
                        )}
                      </td>

                      {/* Pipeline status */}
                      <td className="py-3 px-3">
                        <div className="flex justify-center">
                          <span className={`flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold border ${pCfg.badge}`}>
                            <span className={`w-1.5 h-1.5 rounded-full flex-shrink-0 ${pCfg.dot}`} />
                            {pCfg.label}
                          </span>
                        </div>
                      </td>

                      {/* Visibilité blog */}
                      <td className="py-3 px-3">
                        <div className="flex justify-center">
                          <button onClick={e => handleVisibilityToggle(e, src.slug)} disabled={isVSaving}
                            title={isVisible ? 'Visible · cliquer pour masquer' : 'Masquée · cliquer pour activer'}
                            className={`flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold border transition-all cursor-pointer ${
                              isVSaving ? 'opacity-50 cursor-wait' :
                              isVisible ? 'bg-emerald-500/15 border-emerald-500/40 text-emerald-400 hover:bg-emerald-500/25'
                                        : 'bg-red-500/15 border-red-500/40 text-red-400 hover:bg-red-500/25'
                            }`}>
                            {isVSaving
                              ? <span className="w-2 h-2 border border-current border-t-transparent rounded-full animate-spin inline-block" />
                              : <span className={`w-2 h-2 rounded-full inline-block ${isVisible ? 'bg-emerald-400' : 'bg-red-400'}`} />
                            }
                            {isVisible ? 'On' : 'Off'}
                          </button>
                        </div>
                      </td>

                      {/* Actions */}
                      <td className="py-3 px-4">
                        <div className="flex items-center justify-end gap-1.5">
                          {!isAnnuaires && (
                            <>
                              <button onClick={() => handleTrigger(src.slug)} disabled={isLaunching || artPerDay === 0}
                                title={artPerDay === 0 ? 'Quota 0 — augmentez le %' : 'Lancer maintenant'}
                                className={`px-2.5 py-1 rounded-lg text-[11px] font-semibold border transition-all ${
                                  artPerDay === 0 ? 'bg-surface2 border-border text-t3 opacity-40 cursor-not-allowed'
                                  : isLaunching ? 'bg-emerald-500/15 border-emerald-500/30 text-emerald-400 opacity-70 cursor-wait'
                                  : 'bg-emerald-500/15 border-emerald-500/30 text-emerald-400 hover:bg-emerald-500/25'
                                }`}>
                                {isLaunching ? <span className="flex items-center gap-1"><span className="w-2 h-2 border border-current border-t-transparent rounded-full animate-spin inline-block" />...</span> : '▶ Lancer'}
                              </button>

                              <button onClick={() => handlePause(src.slug, isPaused)} disabled={isPausing}
                                title={isPaused ? 'Reprendre' : 'Mettre en pause'}
                                className={`px-2 py-1 rounded-lg text-[11px] font-semibold border transition-all disabled:opacity-50 ${
                                  isPaused ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400 hover:bg-emerald-500/20'
                                           : 'bg-amber-500/10 border-amber-500/20 text-amber-400 hover:bg-amber-500/20'
                                }`}>
                                {isPausing ? <span className="w-2 h-2 border border-current border-t-transparent rounded-full animate-spin inline-block" /> : isPaused ? '▶' : '⏸'}
                              </button>
                            </>
                          )}
                          <button onClick={() => navigate(`/content/sources/${src.slug}`)}
                            title="Explorer les items" className="px-2 py-1 rounded-lg text-[11px] border bg-surface2 border-border text-t3 hover:text-t1 hover:bg-border transition-colors">
                            →
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}

        {/* Footer */}
        {!loading && (
          <div className="px-4 py-2.5 border-t border-border flex items-center justify-between text-xs text-t3 bg-surface2/20">
            <span>
              {SOURCE_DEFS.filter(s => (weights[s.slug] ?? 0) > 0 && s.slug !== 'annuaires').length} sources actives ·
              Total calculé : <span className="text-violet-light font-semibold">{Object.values(calculatedQuotas).reduce((a,b) => a+b, 0)} articles/jour</span>
            </span>
            <span>
              Actualisé à {lastRefresh.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' })} · prochain dans {countdown}s
            </span>
          </div>
        )}
      </div>

      {/* ── QUICK LINKS ── */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        {[
          { label: '📊 Vue d\'ensemble', to: '/content/overview',    desc: 'Stats globales articles' },
          { label: '📅 Planification',   to: '/content/scheduler',   desc: 'Schedule quotidien'      },
          { label: '✅ Qualité',         to: '/content/quality',     desc: 'Monitoring qualité'      },
          { label: '📤 Publication',     to: '/content/publication', desc: 'File de publication'     },
        ].map(link => (
          <button key={link.to} onClick={() => navigate(link.to)}
            className="bg-surface border border-border rounded-xl p-4 text-left hover:border-violet/30 hover:bg-surface2/30 transition-all group">
            <p className="text-sm font-semibold text-t2 group-hover:text-t1 transition-colors">{link.label}</p>
            <p className="text-xs text-t3 mt-0.5">{link.desc}</p>
          </button>
        ))}
      </div>
    </div>
  );
}
