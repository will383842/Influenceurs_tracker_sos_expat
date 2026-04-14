/**
 * RepublicationLinkedIn — Dashboard complet stratégie LinkedIn SOS-Expat
 *
 * Rythme 5 jours, file d'attente, adaptation contenu, paramètres
 * Phase 1 (maintenant→août) : clients francophones
 * Phase 2 (septembre+) : partenaires avocats/helpers anglophones + brand
 */

import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../api/client';

// ── Types ──────────────────────────────────────────────────────────────────

type PostStatus = 'draft' | 'scheduled' | 'published' | 'failed';
type DayType = 'monday' | 'tuesday' | 'wednesday' | 'thursday' | 'friday';
type AccountType = 'page' | 'personal' | 'both';
type Lang = 'fr' | 'en' | 'both';

interface LinkedInPost {
  id: number;
  source_type: 'article' | 'faq' | 'testimonial' | 'news' | 'case_study' | 'tip';
  source_id?: number;
  source_title?: string;
  day_type: DayType;
  lang: Lang;
  account: AccountType;
  hook: string;
  body: string;
  hashtags: string[];
  status: PostStatus;
  scheduled_at?: string;
  published_at?: string;
  reach?: number;
  likes?: number;
  comments?: number;
  shares?: number;
}

interface LinkedInStats {
  posts_this_week: number;
  posts_scheduled: number;
  posts_published: number;
  total_reach: number;
  avg_engagement_rate: number;
  top_performing_day: string;
}

// ── Constants ──────────────────────────────────────────────────────────────

const DAY_CONFIG: Record<DayType, { label: string; emoji: string; format: string; source: string; color: string }> = {
  monday:    { label: 'Lundi',    emoji: '📄', format: 'Carrousel blog',        source: 'Article blog',           color: 'bg-violet/10 border-violet/30 text-violet' },
  tuesday:   { label: 'Mardi',    emoji: '🎭', format: 'Story fictive',         source: 'Cas pratique fictif',    color: 'bg-blue-500/10 border-blue-500/30 text-blue-600' },
  wednesday: { label: 'Mercredi', emoji: '🚨', format: 'Liste actu visa',       source: 'Actu légale/visa pays',  color: 'bg-amber-500/10 border-amber-500/30 text-amber-600' },
  thursday:  { label: 'Jeudi',    emoji: '❓', format: 'Q&A expat',             source: 'FAQ base de données',    color: 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600' },
  friday:    { label: 'Vendredi', emoji: '💬', format: 'Témoignage / tip',      source: 'Témoignages DB',         color: 'bg-pink-500/10 border-pink-500/30 text-pink-600' },
};

const STATUS_CONFIG: Record<PostStatus, { label: string; color: string }> = {
  draft:     { label: 'Brouillon',  color: 'bg-gray-100 text-gray-600' },
  scheduled: { label: 'Planifié',   color: 'bg-blue-100 text-blue-700' },
  published: { label: 'Publié',     color: 'bg-emerald-100 text-emerald-700' },
  failed:    { label: 'Échec',      color: 'bg-red-100 text-red-700' },
};

const ACCOUNT_LABELS: Record<AccountType, string> = {
  page:     '🏢 Page SOS-Expat',
  personal: '👤 Profil perso',
  both:     '🏢👤 Les deux',
};

const BEST_HOOKS = [
  "Elle voulait tout quitter pour s'installer à l'étranger. Voici ce que personne ne lui a dit.",
  "5 erreurs que font 90% des expats en arrivant au Vietnam (et comment les éviter)",
  "🚨 Visa changement important : ce qui change en [mois] pour les Français à [pays]",
  "La question la plus posée cette semaine : comment ouvrir un compte bancaire à l'étranger sans adresse fixe ?",
  "Il y a 2 ans, Marc a tout perdu. Aujourd'hui, il vit sa meilleure vie à Bangkok.",
];

// ── API calls ──────────────────────────────────────────────────────────────

const fetchLinkedInStats  = () => api.get<LinkedInStats>('/linkedin/stats').then(r => r.data);
const fetchLinkedInQueue  = (status?: string) => api.get<LinkedInPost[]>('/linkedin/queue', { params: { status } }).then(r => r.data);
const generateLinkedInPost = (params: { source_type: string; source_id?: number; day_type: DayType; lang: Lang; account: AccountType }) =>
  api.post<LinkedInPost>('/linkedin/generate', params).then(r => r.data);
const updateLinkedInPost  = (id: number, data: Partial<LinkedInPost>) => api.put(`/linkedin/posts/${id}`, data).then(r => r.data);
const scheduleLinkedInPost = (id: number, scheduled_at: string) => api.post(`/linkedin/posts/${id}/schedule`, { scheduled_at }).then(r => r.data);
const publishLinkedInPost = (id: number) => api.post(`/linkedin/posts/${id}/publish`).then(r => r.data);
const deleteLinkedInPost  = (id: number) => api.delete(`/linkedin/posts/${id}`).then(r => r.data);

// ── Sub-components ──────────────────────────────────────────────────────────

function StatCard({ icon, label, value, sub }: { icon: string; label: string; value: string | number; sub?: string }) {
  return (
    <div className="bg-surface border border-border rounded-xl p-4">
      <div className="flex items-center gap-2 mb-2">
        <span className="text-xl">{icon}</span>
        <span className="text-xs font-semibold uppercase tracking-widest text-muted-foreground">{label}</span>
      </div>
      <div className="text-2xl font-bold text-foreground">{value}</div>
      {sub && <div className="text-xs text-muted-foreground mt-0.5">{sub}</div>}
    </div>
  );
}

function WeeklyRhythm({ onGenerate }: { onGenerate: (day: DayType) => void }) {
  return (
    <div className="bg-surface border border-border rounded-xl p-5">
      <div className="flex items-center justify-between mb-4">
        <h3 className="font-semibold text-foreground">🗓️ Rythme de publication</h3>
        <span className="text-xs text-muted-foreground bg-violet/10 text-violet px-2 py-0.5 rounded-full font-medium">Best practices 2026</span>
      </div>
      <div className="space-y-2">
        {(Object.entries(DAY_CONFIG) as [DayType, typeof DAY_CONFIG[DayType]][]).map(([day, cfg]) => (
          <div key={day} className={`flex items-center justify-between p-3 rounded-lg border ${cfg.color}`}>
            <div className="flex items-center gap-3">
              <span className="text-lg">{cfg.emoji}</span>
              <div>
                <span className="font-semibold text-sm">{cfg.label}</span>
                <span className="text-xs text-muted-foreground ml-2">— {cfg.format}</span>
              </div>
            </div>
            <div className="flex items-center gap-2">
              <span className="text-xs text-muted-foreground hidden sm:block">{cfg.source}</span>
              <button
                onClick={() => onGenerate(day)}
                className="text-xs px-2 py-1 rounded-md bg-white/70 border border-current/20 hover:bg-white transition-colors font-medium"
              >
                Générer
              </button>
            </div>
          </div>
        ))}
      </div>
      <p className="text-[11px] text-muted-foreground mt-3">
        ⚡ Lien externe toujours en 1er commentaire · Heure cible : 7h30 ou 12h15 · 3-5 hashtags max
      </p>
    </div>
  );
}

function PostCard({ post, onSchedule, onPublish, onDelete }: {
  post: LinkedInPost;
  onSchedule: (id: number) => void;
  onPublish: (id: number) => void;
  onDelete: (id: number) => void;
}) {
  const [expanded, setExpanded] = useState(false);
  const cfg = DAY_CONFIG[post.day_type];
  const status = STATUS_CONFIG[post.status];

  return (
    <div className="bg-surface border border-border rounded-xl p-4 space-y-3">
      {/* Header */}
      <div className="flex items-start justify-between gap-2">
        <div className="flex items-center gap-2 flex-wrap">
          <span className={`text-xs px-2 py-0.5 rounded-full font-medium border ${cfg.color}`}>
            {cfg.emoji} {cfg.label}
          </span>
          <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${status.color}`}>
            {status.label}
          </span>
          <span className="text-xs text-muted-foreground">{ACCOUNT_LABELS[post.account]}</span>
          <span className="text-xs font-semibold uppercase text-violet">{post.lang === 'both' ? 'FR + EN' : post.lang.toUpperCase()}</span>
        </div>
        <div className="flex gap-1 shrink-0">
          {post.status === 'draft' && (
            <>
              <button onClick={() => onSchedule(post.id)} className="text-xs px-2 py-1 rounded bg-blue-500/10 text-blue-600 hover:bg-blue-500/20 transition-colors">
                Planifier
              </button>
              <button onClick={() => onPublish(post.id)} className="text-xs px-2 py-1 rounded bg-emerald-500/10 text-emerald-700 hover:bg-emerald-500/20 transition-colors">
                Publier
              </button>
            </>
          )}
          {post.status === 'scheduled' && (
            <button onClick={() => onPublish(post.id)} className="text-xs px-2 py-1 rounded bg-emerald-500/10 text-emerald-700 hover:bg-emerald-500/20 transition-colors">
              Publier maintenant
            </button>
          )}
          <button onClick={() => onDelete(post.id)} className="text-xs px-2 py-1 rounded bg-red-500/10 text-red-600 hover:bg-red-500/20 transition-colors">
            ✕
          </button>
        </div>
      </div>

      {/* Hook */}
      <div className="bg-[#0A66C2]/5 border border-[#0A66C2]/20 rounded-lg p-3">
        <p className="text-sm font-semibold text-foreground leading-snug">{post.hook}</p>
      </div>

      {/* Body preview */}
      <div>
        <p className="text-sm text-muted-foreground leading-relaxed whitespace-pre-line">
          {expanded ? post.body : post.body.slice(0, 200) + (post.body.length > 200 ? '…' : '')}
        </p>
        {post.body.length > 200 && (
          <button onClick={() => setExpanded(!expanded)} className="text-xs text-violet hover:underline mt-1">
            {expanded ? 'Voir moins' : 'Voir plus'}
          </button>
        )}
      </div>

      {/* Hashtags */}
      {post.hashtags.length > 0 && (
        <div className="flex flex-wrap gap-1">
          {post.hashtags.map(h => (
            <span key={h} className="text-xs text-[#0A66C2] hover:underline cursor-default">#{h}</span>
          ))}
        </div>
      )}

      {/* Stats if published */}
      {post.status === 'published' && (post.reach || post.likes) && (
        <div className="flex gap-4 pt-1 border-t border-border text-xs text-muted-foreground">
          {post.reach    && <span>👁️ {post.reach.toLocaleString()} vues</span>}
          {post.likes    && <span>👍 {post.likes} likes</span>}
          {post.comments && <span>💬 {post.comments} commentaires</span>}
          {post.shares   && <span>🔁 {post.shares} partages</span>}
        </div>
      )}

      {post.scheduled_at && post.status === 'scheduled' && (
        <p className="text-xs text-muted-foreground">📅 Planifié le {new Date(post.scheduled_at).toLocaleString('fr-FR')}</p>
      )}
    </div>
  );
}

function GenerateModal({ onClose, onGenerate }: {
  onClose: () => void;
  onGenerate: (params: { source_type: string; day_type: DayType; lang: Lang; account: AccountType }) => void;
}) {
  const [dayType, setDayType] = useState<DayType>('monday');
  const [lang, setLang] = useState<Lang>('fr');
  const [account, setAccount] = useState<AccountType>('both');
  const [sourceType, setSourceType] = useState('article');

  return (
    <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
      <div className="bg-surface border border-border rounded-2xl w-full max-w-lg p-6 space-y-5">
        <div className="flex items-center justify-between">
          <h3 className="text-lg font-bold text-foreground">✍️ Générer un post LinkedIn</h3>
          <button onClick={onClose} className="text-muted-foreground hover:text-foreground text-xl">✕</button>
        </div>

        <div className="space-y-4">
          <div>
            <label className="text-xs font-semibold uppercase tracking-widest text-muted-foreground block mb-1.5">Jour / Format</label>
            <div className="grid grid-cols-5 gap-1">
              {(Object.entries(DAY_CONFIG) as [DayType, typeof DAY_CONFIG[DayType]][]).map(([day, cfg]) => (
                <button
                  key={day}
                  onClick={() => setDayType(day)}
                  className={`flex flex-col items-center gap-1 p-2 rounded-lg border text-xs transition-colors ${dayType === day ? `${cfg.color} font-semibold` : 'border-border text-muted-foreground hover:bg-white/5'}`}
                >
                  <span className="text-base">{cfg.emoji}</span>
                  <span>{cfg.label}</span>
                </button>
              ))}
            </div>
            <p className="text-xs text-muted-foreground mt-1.5">Format : {DAY_CONFIG[dayType].format} · Source : {DAY_CONFIG[dayType].source}</p>
          </div>

          <div>
            <label className="text-xs font-semibold uppercase tracking-widest text-muted-foreground block mb-1.5">Source de contenu</label>
            <select value={sourceType} onChange={e => setSourceType(e.target.value)} className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm text-foreground">
              <option value="article">Article blog publié</option>
              <option value="faq">FAQ base de données</option>
              <option value="testimonial">Témoignage</option>
              <option value="news">Actu légale / visa</option>
              <option value="case_study">Cas pratique fictif (IA)</option>
              <option value="tip">Tip rapide (IA)</option>
            </select>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="text-xs font-semibold uppercase tracking-widest text-muted-foreground block mb-1.5">Langue</label>
              <div className="flex gap-1">
                {(['fr', 'en', 'both'] as Lang[]).map(l => (
                  <button key={l} onClick={() => setLang(l)} className={`flex-1 py-1.5 rounded-lg text-xs font-medium border transition-colors ${lang === l ? 'bg-violet text-white border-violet' : 'border-border text-muted-foreground hover:bg-white/5'}`}>
                    {l === 'both' ? 'FR+EN' : l.toUpperCase()}
                  </button>
                ))}
              </div>
            </div>
            <div>
              <label className="text-xs font-semibold uppercase tracking-widest text-muted-foreground block mb-1.5">Compte</label>
              <div className="flex flex-col gap-1">
                {(['both', 'page', 'personal'] as AccountType[]).map(a => (
                  <button key={a} onClick={() => setAccount(a)} className={`py-1 rounded-lg text-xs font-medium border transition-colors ${account === a ? 'bg-violet text-white border-violet' : 'border-border text-muted-foreground hover:bg-white/5'}`}>
                    {a === 'both' ? '🏢👤 Les deux' : a === 'page' ? '🏢 Page' : '👤 Perso'}
                  </button>
                ))}
              </div>
            </div>
          </div>
        </div>

        <div className="border-t border-border pt-4">
          <div className="bg-amber-500/10 border border-amber-500/30 rounded-lg p-3 mb-4 text-xs text-amber-700">
            💡 <strong>Règles LinkedIn 2026 appliquées automatiquement :</strong> Hook accrocheur, zéro lien dans le post, 3-5 hashtags, 1200-1800 caractères, CTA doux.
          </div>
          <div className="flex gap-2 justify-end">
            <button onClick={onClose} className="px-4 py-2 text-sm text-muted-foreground hover:text-foreground transition-colors">Annuler</button>
            <button
              onClick={() => { onGenerate({ source_type: sourceType, day_type: dayType, lang, account }); onClose(); }}
              className="px-5 py-2 bg-[#0A66C2] text-white text-sm font-semibold rounded-lg hover:bg-[#0A66C2]/90 transition-colors"
            >
              🚀 Générer avec IA
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

// ── Main Component ──────────────────────────────────────────────────────────

type Tab = 'dashboard' | 'queue' | 'strategy' | 'settings';

export default function RepublicationLinkedIn() {
  const qc = useQueryClient();
  const [tab, setTab] = useState<Tab>('dashboard');
  const [showGenModal, setShowGenModal] = useState(false);
  const [defaultDay, setDefaultDay] = useState<DayType>('monday');
  const [filterStatus, setFilterStatus] = useState<string>('all');
  const [phase, setPhase] = useState<1 | 2>(1);

  const { data: stats } = useQuery({ queryKey: ['linkedin-stats'], queryFn: fetchLinkedInStats, retry: false });
  const { data: queue = [], isLoading } = useQuery({
    queryKey: ['linkedin-queue', filterStatus],
    queryFn: () => fetchLinkedInQueue(filterStatus === 'all' ? undefined : filterStatus),
    retry: false,
  });

  const mutatePub  = useMutation({ mutationFn: (id: number) => publishLinkedInPost(id),  onSuccess: () => qc.invalidateQueries({ queryKey: ['linkedin-queue'] }) });
  const mutateDel  = useMutation({ mutationFn: (id: number) => deleteLinkedInPost(id),   onSuccess: () => qc.invalidateQueries({ queryKey: ['linkedin-queue'] }) });
  const mutateGen  = useMutation({ mutationFn: generateLinkedInPost, onSuccess: () => { qc.invalidateQueries({ queryKey: ['linkedin-queue'] }); setTab('queue'); } });
  const mutateSched = useMutation({
    mutationFn: ({ id, date }: { id: number; date: string }) => scheduleLinkedInPost(id, date),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['linkedin-queue'] }),
  });

  const handleGenerate = (day: DayType) => { setDefaultDay(day); setShowGenModal(true); };

  const TABS: { id: Tab; label: string }[] = [
    { id: 'dashboard', label: '📊 Tableau de bord' },
    { id: 'queue',     label: `⏰ File d'attente${queue.filter(p => p.status !== 'published').length > 0 ? ` (${queue.filter(p => p.status !== 'published').length})` : ''}` },
    { id: 'strategy',  label: '🗺️ Stratégie' },
    { id: 'settings',  label: '⚙️ Paramètres' },
  ];

  return (
    <div className="p-6 max-w-5xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-start justify-between gap-4 flex-wrap">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <span className="text-2xl">💼</span>
            <h1 className="text-2xl font-bold text-foreground">LinkedIn — Republication</h1>
            <span className="px-2 py-0.5 rounded-full text-xs font-semibold bg-[#0A66C2]/10 text-[#0A66C2] border border-[#0A66C2]/20">SOS-Expat</span>
          </div>
          <p className="text-sm text-muted-foreground">Republication automatique · Page entreprise + Profil personnel · FR & EN</p>
        </div>
        <button
          onClick={() => setShowGenModal(true)}
          className="flex items-center gap-2 px-4 py-2 bg-[#0A66C2] text-white text-sm font-semibold rounded-lg hover:bg-[#0A66C2]/90 transition-colors shadow-sm"
        >
          ✍️ Générer un post
        </button>
      </div>

      {/* Phase banner */}
      <div className={`rounded-xl border p-4 flex items-center justify-between gap-3 ${phase === 1 ? 'bg-violet/5 border-violet/30' : 'bg-amber-500/5 border-amber-500/30'}`}>
        <div>
          <p className={`text-sm font-semibold ${phase === 1 ? 'text-violet' : 'text-amber-600'}`}>
            {phase === 1 ? '🎯 Phase 1 — Clients francophones (maintenant → août 2026)' : '🌍 Phase 2 — Expansion globale (septembre 2026+)'}
          </p>
          <p className="text-xs text-muted-foreground mt-0.5">
            {phase === 1
              ? 'Objectif : acquérir des clients FR dans le monde entier. Posts majoritairement en français.'
              : 'Objectif : partenaires avocats/helpers anglophones + brand awareness mondial FR+EN.'}
          </p>
        </div>
        <div className="flex gap-1 shrink-0">
          <button onClick={() => setPhase(1)} className={`text-xs px-2 py-1 rounded-lg border font-medium transition-colors ${phase === 1 ? 'bg-violet text-white border-violet' : 'border-border text-muted-foreground hover:bg-white/5'}`}>Phase 1</button>
          <button onClick={() => setPhase(2)} className={`text-xs px-2 py-1 rounded-lg border font-medium transition-colors ${phase === 2 ? 'bg-amber-500 text-white border-amber-500' : 'border-border text-muted-foreground hover:bg-white/5'}`}>Phase 2</button>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 border-b border-border overflow-x-auto">
        {TABS.map(t => (
          <button key={t.id} onClick={() => setTab(t.id)}
            className={`px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 transition-colors ${tab === t.id ? 'border-[#0A66C2] text-[#0A66C2]' : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'}`}>
            {t.label}
          </button>
        ))}
      </div>

      {/* ── TAB: DASHBOARD ── */}
      {tab === 'dashboard' && (
        <div className="space-y-6">
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <StatCard icon="📬" label="Cette semaine" value={stats?.posts_this_week ?? '—'} sub="posts générés" />
            <StatCard icon="⏰" label="Planifiés" value={stats?.posts_scheduled ?? '—'} sub="en attente" />
            <StatCard icon="👁️" label="Reach total" value={stats?.total_reach ? stats.total_reach.toLocaleString() : '—'} sub="vues" />
            <StatCard icon="📈" label="Engagement" value={stats?.avg_engagement_rate ? `${stats.avg_engagement_rate}%` : '—'} sub="taux moyen" />
          </div>

          <WeeklyRhythm onGenerate={handleGenerate} />

          {/* Best hooks reference */}
          <div className="bg-surface border border-border rounded-xl p-5">
            <h3 className="font-semibold text-foreground mb-3">💡 Formules de hooks performants (top LinkedIn 2026)</h3>
            <div className="space-y-2">
              {BEST_HOOKS.map((hook, i) => (
                <div key={i} className="flex gap-2 items-start text-sm p-2 rounded-lg hover:bg-white/5 transition-colors">
                  <span className="text-muted-foreground shrink-0 text-xs mt-0.5">{i + 1}.</span>
                  <span className="text-foreground">{hook}</span>
                </div>
              ))}
            </div>
            <p className="text-xs text-muted-foreground mt-3">Ces formules sont injectées automatiquement lors de la génération IA.</p>
          </div>

          {/* LinkedIn rules */}
          <div className="bg-[#0A66C2]/5 border border-[#0A66C2]/20 rounded-xl p-5">
            <h3 className="font-semibold text-[#0A66C2] mb-3">📋 Règles LinkedIn 2026 appliquées automatiquement</h3>
            <div className="grid sm:grid-cols-2 gap-2 text-sm text-foreground">
              {[
                ['🚫', 'Zéro lien dans le post', '(→ 1er commentaire)'],
                ['⏰', 'Heure optimale', '7h30 ou 12h15 (fuseau cible)'],
                ['📏', 'Longueur idéale', '1200–1800 caractères'],
                ['#️⃣', 'Hashtags', '3–5 max, pertinents'],
                ['🎯', 'Hook', '2–3 premières lignes = tout'],
                ['👤', 'Profil perso', '8–10x plus de reach que la page'],
                ['💬', 'Commentaires', 'Répondre dans les 60 premières minutes'],
                ['📄', 'Carrousel PDF', '3–5x plus de reach que texte pur'],
              ].map(([icon, title, detail]) => (
                <div key={title} className="flex items-start gap-2 p-2 rounded-lg bg-white/5">
                  <span>{icon}</span>
                  <span><strong>{title}</strong> <span className="text-muted-foreground">{detail}</span></span>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* ── TAB: QUEUE ── */}
      {tab === 'queue' && (
        <div className="space-y-4">
          <div className="flex items-center justify-between gap-3 flex-wrap">
            <div className="flex gap-1 flex-wrap">
              {['all', 'draft', 'scheduled', 'published', 'failed'].map(s => (
                <button key={s} onClick={() => setFilterStatus(s)}
                  className={`px-3 py-1 text-xs rounded-full border font-medium transition-colors capitalize ${filterStatus === s ? 'bg-violet text-white border-violet' : 'border-border text-muted-foreground hover:bg-white/5'}`}>
                  {s === 'all' ? 'Tous' : STATUS_CONFIG[s as PostStatus]?.label ?? s}
                  <span className="ml-1 opacity-60">
                    ({s === 'all' ? queue.length : queue.filter(p => p.status === s).length})
                  </span>
                </button>
              ))}
            </div>
            <button onClick={() => setShowGenModal(true)} className="text-xs px-3 py-1.5 bg-[#0A66C2] text-white rounded-lg font-semibold hover:bg-[#0A66C2]/90 transition-colors">
              + Générer
            </button>
          </div>

          {isLoading && <p className="text-muted-foreground text-sm">Chargement…</p>}
          {!isLoading && queue.length === 0 && (
            <div className="text-center py-16 text-muted-foreground">
              <p className="text-4xl mb-3">💼</p>
              <p className="font-semibold">Aucun post dans la file</p>
              <p className="text-sm mt-1">Cliquez sur "Générer" pour créer votre premier post LinkedIn.</p>
              <button onClick={() => setShowGenModal(true)} className="mt-4 px-4 py-2 bg-[#0A66C2] text-white text-sm font-semibold rounded-lg hover:bg-[#0A66C2]/90 transition-colors">
                ✍️ Générer un post
              </button>
            </div>
          )}
          {queue.map(post => (
            <PostCard key={post.id} post={post}
              onSchedule={(id) => mutateSched.mutate({ id, date: new Date(Date.now() + 86400000).toISOString() })}
              onPublish={(id) => mutatePub.mutate(id)}
              onDelete={(id) => { if (confirm('Supprimer ce post ?')) mutateDel.mutate(id); }}
            />
          ))}
        </div>
      )}

      {/* ── TAB: STRATEGY ── */}
      {tab === 'strategy' && (
        <div className="space-y-6">
          <div className="bg-surface border border-border rounded-xl p-5">
            <h3 className="font-semibold text-foreground mb-4">🗺️ Plan stratégique LinkedIn SOS-Expat</h3>
            <div className="space-y-4">
              <div className="border border-violet/30 rounded-xl p-4 bg-violet/5">
                <h4 className="font-semibold text-violet mb-2">Phase 1 — Maintenant → Août 2026</h4>
                <ul className="text-sm space-y-1 text-foreground">
                  <li>🎯 <strong>Objectif principal :</strong> Trouver des clients francophones dans le monde entier</li>
                  <li>🌍 <strong>Cibles :</strong> Expatriés francophones, futurs expats, communautés françaises à l'étranger</li>
                  <li>🗣️ <strong>Langue dominante :</strong> Français (80% FR, 20% EN)</li>
                  <li>📢 <strong>Ton :</strong> Empathique, pratique, rassurant. "On vous comprend, on est là."</li>
                  <li>📄 <strong>CTA principal :</strong> "Réservez un appel avec un avocat expat" / "Découvrez SOS-Expat"</li>
                </ul>
              </div>
              <div className="border border-amber-500/30 rounded-xl p-4 bg-amber-500/5">
                <h4 className="font-semibold text-amber-600 mb-2">Phase 2 — Septembre 2026+</h4>
                <ul className="text-sm space-y-1 text-foreground">
                  <li>🤝 <strong>Recrutement :</strong> Avocats partenaires + Expats aidants anglophones</li>
                  <li>🌐 <strong>Langue :</strong> 50% FR / 50% EN</li>
                  <li>📣 <strong>Brand awareness :</strong> SOS-Expat = référence mondiale pour les expatriés</li>
                  <li>🎯 <strong>Audiences :</strong> Avocats spécialisés expat, helpers, communautés anglophones</li>
                </ul>
              </div>
            </div>
          </div>

          <div className="bg-surface border border-border rounded-xl p-5">
            <h3 className="font-semibold text-foreground mb-3">📅 Calendrier éditorial hebdomadaire</h3>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-border">
                    <th className="text-left py-2 pr-4 text-muted-foreground font-semibold text-xs uppercase">Jour</th>
                    <th className="text-left py-2 pr-4 text-muted-foreground font-semibold text-xs uppercase">Format</th>
                    <th className="text-left py-2 pr-4 text-muted-foreground font-semibold text-xs uppercase">Source</th>
                    <th className="text-left py-2 pr-4 text-muted-foreground font-semibold text-xs uppercase">Compte</th>
                    <th className="text-left py-2 text-muted-foreground font-semibold text-xs uppercase">Langue</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {[
                    ['📄 Lundi',    'Carrousel 5-7 slides',     'Article blog',        '🏢 + 👤', 'FR'],
                    ['🎭 Mardi',    'Story fictive (hook fort)', 'Cas pratique IA',     '👤 Perso', 'FR'],
                    ['🚨 Mercredi', 'Liste actu visa',          'Actu légale',         '🏢 Page',  'FR + EN'],
                    ['❓ Jeudi',    'Q&A expat',                'FAQ base de données', '👤 Perso', 'FR'],
                    ['💬 Vendredi', 'Témoignage / tip rapide',  'Témoignages DB',      '🏢 + 👤', 'FR'],
                  ].map(([jour, format, source, compte, langue]) => (
                    <tr key={jour}>
                      <td className="py-2.5 pr-4 font-medium">{jour}</td>
                      <td className="py-2.5 pr-4 text-muted-foreground">{format}</td>
                      <td className="py-2.5 pr-4 text-muted-foreground">{source}</td>
                      <td className="py-2.5 pr-4 text-muted-foreground">{compte}</td>
                      <td className="py-2.5 font-medium text-violet">{langue}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* ── TAB: SETTINGS ── */}
      {tab === 'settings' && (
        <div className="space-y-5">
          <div className="bg-surface border border-border rounded-xl p-5 space-y-4">
            <h3 className="font-semibold text-foreground">🔗 Comptes LinkedIn connectés</h3>
            <div className="space-y-3">
              {[
                { name: '🏢 Page SOS-Expat', sub: 'Page entreprise LinkedIn', connected: false },
                { name: '👤 Profil personnel', sub: 'Compte personnel (Manon)', connected: false },
              ].map(account => (
                <div key={account.name} className="flex items-center justify-between p-3 rounded-lg border border-border">
                  <div>
                    <p className="font-medium text-sm text-foreground">{account.name}</p>
                    <p className="text-xs text-muted-foreground">{account.sub}</p>
                  </div>
                  <button className={`text-xs px-3 py-1.5 rounded-lg font-semibold border transition-colors ${account.connected ? 'bg-emerald-500/10 text-emerald-700 border-emerald-500/30' : 'bg-[#0A66C2] text-white border-[#0A66C2] hover:bg-[#0A66C2]/90'}`}>
                    {account.connected ? '✓ Connecté' : 'Connecter via OAuth'}
                  </button>
                </div>
              ))}
            </div>
            <p className="text-xs text-muted-foreground">La connexion OAuth LinkedIn nécessite une app LinkedIn Developer avec les scopes <code className="bg-white/10 px-1 rounded">w_member_social</code> et <code className="bg-white/10 px-1 rounded">r_organization_social</code>.</p>
          </div>

          <div className="bg-surface border border-border rounded-xl p-5 space-y-3">
            <h3 className="font-semibold text-foreground">⏰ Fréquence & horaires</h3>
            <div className="grid sm:grid-cols-2 gap-3">
              <div>
                <label className="text-xs font-semibold uppercase tracking-widest text-muted-foreground block mb-1">Posts / semaine</label>
                <select className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm text-foreground">
                  <option value="3">3 posts/semaine</option>
                  <option value="4">4 posts/semaine</option>
                  <option value="5" selected>5 posts/semaine (recommandé)</option>
                </select>
              </div>
              <div>
                <label className="text-xs font-semibold uppercase tracking-widest text-muted-foreground block mb-1">Heure de publication</label>
                <select className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm text-foreground">
                  <option value="07:30" selected>07h30 (meilleure portée)</option>
                  <option value="12:15">12h15 (pause déjeuner)</option>
                  <option value="18:00">18h00 (fin journée)</option>
                </select>
              </div>
            </div>
          </div>

          <div className="bg-surface border border-border rounded-xl p-5 space-y-3">
            <h3 className="font-semibold text-foreground">#️⃣ Hashtags par défaut</h3>
            <div className="flex flex-wrap gap-2">
              {['expatriation', 'vivraetranger', 'expat', 'expatrié', 'sosexpat', 'conseijuridique', 'avocat', 'international', 'français', 'vietnam', 'thaïlande', 'canada'].map(h => (
                <span key={h} className="text-xs px-2 py-1 rounded-full bg-[#0A66C2]/10 text-[#0A66C2] border border-[#0A66C2]/20 cursor-pointer hover:bg-[#0A66C2]/20 transition-colors">
                  #{h}
                </span>
              ))}
              <button className="text-xs px-2 py-1 rounded-full border border-dashed border-border text-muted-foreground hover:bg-white/5 transition-colors">+ Ajouter</button>
            </div>
          </div>
        </div>
      )}

      {/* Modal génération */}
      {showGenModal && (
        <GenerateModal
          onClose={() => setShowGenModal(false)}
          onGenerate={(params) => mutateGen.mutate({ ...params, day_type: defaultDay })}
        />
      )}
    </div>
  );
}
