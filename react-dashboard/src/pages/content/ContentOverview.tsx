import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { fetchGenerationStats, fetchCostOverview, fetchArticles, runAutoPipeline, fetchPipelineStatus } from '../../api/contentApi';
import type { GenerationStats, CostOverview, GeneratedArticle, ContentStatus, PipelineStatus } from '../../types/content';
import { toast } from '../../components/Toast';
import { inputClass, errMsg } from './helpers';

// ── Constants ───────────────────────────────────────────────
const STATUS_COLORS: Record<ContentStatus, string> = {
  draft: 'bg-muted/20 text-muted',
  generating: 'bg-amber/20 text-amber animate-pulse',
  review: 'bg-blue-500/20 text-blue-400',
  scheduled: 'bg-violet/20 text-violet',
  published: 'bg-success/20 text-success',
  archived: 'bg-muted/20 text-muted',
};

const STATUS_LABELS: Record<ContentStatus, string> = {
  draft: 'Brouillon',
  generating: 'Generation...',
  review: 'Revue',
  scheduled: 'Planifie',
  published: 'Publie',
  archived: 'Archive',
};

const LANG_FLAGS: Record<string, string> = {
  fr: 'FR', en: 'EN', de: 'DE', es: 'ES', pt: 'PT', ru: 'RU', zh: 'ZH', ar: 'AR', hi: 'HI',
};

function seoColor(score: number): string {
  if (score >= 80) return 'text-success';
  if (score >= 50) return 'text-amber';
  return 'text-danger';
}

function budgetColor(pct: number): string {
  if (pct >= 80) return 'bg-danger';
  if (pct >= 50) return 'bg-amber';
  return 'bg-success';
}

function cents(n: number): string {
  return (n / 100).toFixed(2);
}

// ── Component ───────────────────────────────────────────────
export default function ContentOverview() {
  const navigate = useNavigate();
  const [stats, setStats] = useState<GenerationStats | null>(null);
  const [costs, setCosts] = useState<CostOverview | null>(null);
  const [recentArticles, setRecentArticles] = useState<GeneratedArticle[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Pipeline state
  const [pipelineStatus, setPipelineStatus] = useState<PipelineStatus | null>(null);
  const [pipelineLoading, setPipelineLoading] = useState(false);
  const [showPipelineOptions, setShowPipelineOptions] = useState(false);
  const [pipelineOptions, setPipelineOptions] = useState({
    max_articles: 50,
    min_quality_score: 85,
    include_qa: true,
    articles_from_questions: true,
    country: '',
    category: '',
  });

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [statsRes, costsRes, articlesRes] = await Promise.all([
        fetchGenerationStats(),
        fetchCostOverview(),
        fetchArticles({ per_page: 5 } as Record<string, unknown> & { per_page: number }),
      ]);
      setStats(statsRes.data as unknown as GenerationStats);
      setCosts(costsRes.data as unknown as CostOverview);
      const articlesData = articlesRes.data as unknown as { data: GeneratedArticle[] };
      setRecentArticles(articlesData.data ?? []);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Erreur de chargement';
      setError(msg);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  // Fetch pipeline status on mount + every 30s
  useEffect(() => {
    const loadPipeline = async () => {
      try {
        const res = await fetchPipelineStatus();
        setPipelineStatus(res.data as unknown as PipelineStatus);
      } catch { /* silent */ }
    };
    loadPipeline();
    const interval = setInterval(loadPipeline, 30000);
    return () => clearInterval(interval);
  }, []);

  const handleLaunchPipeline = async () => {
    setPipelineLoading(true);
    try {
      const opts: Record<string, unknown> = {
        max_articles: pipelineOptions.max_articles,
        min_quality_score: pipelineOptions.min_quality_score,
        include_qa: pipelineOptions.include_qa,
        articles_from_questions: pipelineOptions.articles_from_questions,
      };
      if (pipelineOptions.country) opts.country = pipelineOptions.country;
      if (pipelineOptions.category) opts.category = pipelineOptions.category;
      await runAutoPipeline(opts);
      toast('success', 'Pipeline automatique lance ! Les articles seront generes progressivement.');
    } catch (e) {
      toast('error', errMsg(e));
    } finally {
      setPipelineLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="p-4 md:p-6 space-y-6">
        <div className="animate-pulse bg-surface2 rounded-lg h-8 w-72" />
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {[1, 2, 3, 4].map(i => <div key={i} className="animate-pulse bg-surface2 rounded-xl h-24" />)}
        </div>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          {[1, 2, 3].map(i => <div key={i} className="animate-pulse bg-surface2 rounded-xl h-12" />)}
        </div>
        <div className="animate-pulse bg-surface2 rounded-xl h-64" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="p-4 md:p-6">
        <div className="bg-surface border border-border rounded-xl p-8 text-center">
          <p className="text-danger mb-4">{error}</p>
          <button onClick={load} className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors">
            Reessayer
          </button>
        </div>
      </div>
    );
  }

  const totalArticles = stats?.total_all_time ?? 0;
  const publishedCount = stats?.by_status?.published ?? 0;
  const avgSeo = Math.round(stats?.avg_seo_score ?? 0);
  const monthlyCost = costs?.this_month_cents ?? 0;

  const dailyUsedPct = costs && costs.daily_budget_cents > 0
    ? Math.min(100, Math.round((costs.today_cents / costs.daily_budget_cents) * 100))
    : 0;
  const monthlyUsedPct = costs && costs.monthly_budget_cents > 0
    ? Math.min(100, Math.round((costs.this_month_cents / costs.monthly_budget_cents) * 100))
    : 0;

  const statCards = [
    { label: 'Total articles', value: totalArticles.toLocaleString('fr-FR'), color: 'text-violet bg-violet/20' },
    { label: 'Publies', value: publishedCount.toLocaleString('fr-FR'), color: 'text-success bg-success/20' },
    { label: 'Score SEO moyen', value: `${avgSeo}/100`, color: `${seoColor(avgSeo)} ${avgSeo >= 80 ? 'bg-success/20' : avgSeo >= 50 ? 'bg-amber/20' : 'bg-danger/20'}` },
    { label: 'Cout mensuel IA', value: `$${cents(monthlyCost)}`, color: 'text-blue-400 bg-blue-500/20' },
  ];

  const byStatus = stats?.by_status ?? {};
  const allStatuses: ContentStatus[] = ['draft', 'generating', 'review', 'published', 'archived'];
  const byLanguage = stats?.by_language ?? {};
  const maxLangCount = Math.max(1, ...Object.values(byLanguage));

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Header */}
      <h2 className="font-title text-2xl font-bold text-white">Content Engine — Vue d'ensemble</h2>

      {/* ── AUTO PIPELINE PANEL ── */}
      <div className="bg-gradient-to-r from-violet/10 to-blue-500/10 border border-violet/30 rounded-xl p-6 mb-8">
        <div className="flex items-start justify-between">
          <div>
            <h2 className="text-xl font-bold text-white flex items-center gap-2">
              ⚡ Pipeline Automatique
            </h2>
            <p className="text-muted mt-1">
              Genere automatiquement des articles a partir de tout le contenu scrappe.
              Clustering, recherche, generation, anti-plagiat, SEO — tout est automatique.
            </p>
          </div>
          {pipelineStatus && pipelineStatus.currently_generating > 0 && (
            <span className="px-3 py-1 rounded-full bg-amber/20 text-amber text-sm animate-pulse">
              {pipelineStatus.currently_generating} en cours...
            </span>
          )}
        </div>

        {/* Pipeline Stats */}
        {pipelineStatus && (
          <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-5 gap-4 mt-4">
            <div className="bg-surface rounded-lg p-3 text-center">
              <div className="text-2xl font-bold text-white">{pipelineStatus.unprocessed_articles.toLocaleString()}</div>
              <div className="text-xs text-muted">Articles a traiter</div>
            </div>
            <div className="bg-surface rounded-lg p-3 text-center">
              <div className="text-2xl font-bold text-white">{pipelineStatus.unprocessed_questions.toLocaleString()}</div>
              <div className="text-xs text-muted">Questions forum</div>
            </div>
            <div className="bg-surface rounded-lg p-3 text-center">
              <div className="text-2xl font-bold text-white">{pipelineStatus.pending_clusters + pipelineStatus.pending_question_clusters}</div>
              <div className="text-xs text-muted">Clusters en attente</div>
            </div>
            <div className="bg-surface rounded-lg p-3 text-center">
              <div className="text-2xl font-bold text-emerald-400">{pipelineStatus.total_generated}</div>
              <div className="text-xs text-muted">Articles generes</div>
            </div>
            <div className="bg-surface rounded-lg p-3 text-center">
              <div className="text-2xl font-bold text-violet">{pipelineStatus.generated_today}</div>
              <div className="text-xs text-muted">Generes aujourd'hui</div>
            </div>
          </div>
        )}

        {/* Options toggle */}
        <div className="mt-4 flex items-center gap-4">
          <button
            onClick={handleLaunchPipeline}
            disabled={pipelineLoading || !pipelineStatus?.pipeline_ready}
            className="px-6 py-3 rounded-lg bg-violet hover:bg-violet/80 text-white font-bold transition disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {pipelineLoading ? 'Lancement...' : '⚡ Lancer le pipeline automatique'}
          </button>
          <button
            onClick={() => setShowPipelineOptions(!showPipelineOptions)}
            className="text-sm text-muted hover:text-white transition"
          >
            {showPipelineOptions ? 'Masquer les options' : 'Options avancees'}
          </button>
        </div>

        {/* Advanced options (collapsible) */}
        {showPipelineOptions && (
          <div className="mt-4 grid grid-cols-2 sm:grid-cols-3 gap-4 p-4 bg-surface rounded-lg">
            <div>
              <label className="block text-xs text-muted mb-1">Max articles</label>
              <input type="number" min={1} max={500} value={pipelineOptions.max_articles}
                onChange={e => setPipelineOptions(p => ({...p, max_articles: +e.target.value}))}
                className={inputClass + ' w-full'} />
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Score qualite minimum</label>
              <input type="number" min={50} max={100} value={pipelineOptions.min_quality_score}
                onChange={e => setPipelineOptions(p => ({...p, min_quality_score: +e.target.value}))}
                className={inputClass + ' w-full'} />
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Pays (optionnel)</label>
              <input type="text" value={pipelineOptions.country} placeholder="Ex: allemagne"
                onChange={e => setPipelineOptions(p => ({...p, country: e.target.value}))}
                className={inputClass + ' w-full'} />
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Categorie (optionnel)</label>
              <select value={pipelineOptions.category}
                onChange={e => setPipelineOptions(p => ({...p, category: e.target.value}))}
                className={inputClass + ' w-full'}>
                <option value="">Toutes</option>
                <option value="visa">Visa</option>
                <option value="logement">Logement</option>
                <option value="sante">Sante</option>
                <option value="emploi">Emploi</option>
                <option value="education">Education</option>
                <option value="banque">Banque</option>
                <option value="transport">Transport</option>
                <option value="culture">Culture</option>
                <option value="demarches">Demarches</option>
                <option value="telecom">Telecom</option>
              </select>
            </div>
            <div className="flex items-center gap-2">
              <input type="checkbox" id="include_qa" checked={pipelineOptions.include_qa}
                onChange={e => setPipelineOptions(p => ({...p, include_qa: e.target.checked}))} />
              <label htmlFor="include_qa" className="text-sm text-muted">Generer les Q&A</label>
            </div>
            <div className="flex items-center gap-2">
              <input type="checkbox" id="from_questions" checked={pipelineOptions.articles_from_questions}
                onChange={e => setPipelineOptions(p => ({...p, articles_from_questions: e.target.checked}))} />
              <label htmlFor="from_questions" className="text-sm text-muted">Articles depuis questions</label>
            </div>
          </div>
        )}
      </div>

      {/* Stat cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {statCards.map(card => (
          <div key={card.label} className="bg-surface border border-border rounded-xl p-5">
            <span className="text-xs text-muted uppercase tracking-wide">{card.label}</span>
            <p className={`text-2xl font-bold mt-2 ${card.color.split(' ')[0]}`}>{card.value}</p>
          </div>
        ))}
      </div>

      {/* Quick actions */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <button
          onClick={() => navigate('/content/articles/new')}
          className="bg-surface border border-border rounded-xl p-4 hover:border-violet/50 transition-colors text-left"
        >
          <span className="text-violet text-lg font-bold">+</span>
          <p className="text-white font-medium mt-1">Nouvel article</p>
          <p className="text-xs text-muted mt-1">Generer un article SEO avec IA</p>
        </button>
        <button
          onClick={() => navigate('/content/comparatives/new')}
          className="bg-surface border border-border rounded-xl p-4 hover:border-violet/50 transition-colors text-left"
        >
          <span className="text-blue-400 text-lg font-bold">+</span>
          <p className="text-white font-medium mt-1">Nouveau comparatif</p>
          <p className="text-xs text-muted mt-1">Comparer des services ou pays</p>
        </button>
        <button
          onClick={() => navigate('/content/campaigns/new')}
          className="bg-surface border border-border rounded-xl p-4 hover:border-violet/50 transition-colors text-left"
        >
          <span className="text-success text-lg font-bold">+</span>
          <p className="text-white font-medium mt-1">Nouvelle campagne</p>
          <p className="text-xs text-muted mt-1">Generation en masse automatisee</p>
        </button>
      </div>

      {/* Budget gauges */}
      {costs && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="bg-surface border border-border rounded-xl p-5">
            <div className="flex items-center justify-between mb-2">
              <span className="text-sm text-white font-medium">Budget quotidien</span>
              <span className="text-xs text-muted">${cents(costs.today_cents)} / ${cents(costs.daily_budget_cents)}</span>
            </div>
            <div className="w-full h-2.5 bg-surface2 rounded-full overflow-hidden">
              <div className={`h-full rounded-full transition-all ${budgetColor(dailyUsedPct)}`} style={{ width: `${dailyUsedPct}%` }} />
            </div>
            <p className="text-xs text-muted mt-1">{dailyUsedPct}% utilise</p>
          </div>
          <div className="bg-surface border border-border rounded-xl p-5">
            <div className="flex items-center justify-between mb-2">
              <span className="text-sm text-white font-medium">Budget mensuel</span>
              <span className="text-xs text-muted">${cents(costs.this_month_cents)} / ${cents(costs.monthly_budget_cents)}</span>
            </div>
            <div className="w-full h-2.5 bg-surface2 rounded-full overflow-hidden">
              <div className={`h-full rounded-full transition-all ${budgetColor(monthlyUsedPct)}`} style={{ width: `${monthlyUsedPct}%` }} />
            </div>
            <p className="text-xs text-muted mt-1">{monthlyUsedPct}% utilise</p>
          </div>
        </div>
      )}

      {/* Two-column layout: Recent articles + Breakdown */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Recent articles */}
        <div className="lg:col-span-2 bg-surface border border-border rounded-xl p-5">
          <div className="flex items-center justify-between mb-4">
            <h3 className="font-title font-semibold text-white">Articles recents</h3>
            <button onClick={() => navigate('/content/articles')} className="text-xs text-violet hover:text-violet-light transition-colors">
              Voir tout
            </button>
          </div>
          {recentArticles.length === 0 ? (
            <div className="text-center py-8">
              <p className="text-muted text-sm mb-3">Aucun article genere pour le moment.</p>
              <button onClick={() => navigate('/content/articles/new')} className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors">
                Creer un article
              </button>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-left text-xs text-muted uppercase tracking-wide border-b border-border">
                    <th className="pb-3 pr-4">Titre</th>
                    <th className="pb-3 pr-4">Langue</th>
                    <th className="pb-3 pr-4">Statut</th>
                    <th className="pb-3 pr-4">SEO</th>
                    <th className="pb-3">Date</th>
                  </tr>
                </thead>
                <tbody>
                  {recentArticles.map(article => (
                    <tr
                      key={article.id}
                      className="border-b border-border/50 hover:bg-surface2/50 transition-colors cursor-pointer"
                      onClick={() => navigate(`/content/articles/${article.id}`)}
                    >
                      <td className="py-3 pr-4">
                        <span className="text-white font-medium truncate block max-w-[300px]">{article.title}</span>
                      </td>
                      <td className="py-3 pr-4">
                        <span className="inline-block px-1.5 py-0.5 rounded text-[10px] bg-violet/20 text-violet-light">
                          {LANG_FLAGS[article.language] ?? article.language.toUpperCase()}
                        </span>
                      </td>
                      <td className="py-3 pr-4">
                        <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${STATUS_COLORS[article.status]}`}>
                          {STATUS_LABELS[article.status]}
                        </span>
                      </td>
                      <td className="py-3 pr-4">
                        <span className={`text-sm font-bold ${seoColor(article.seo_score)}`}>{article.seo_score}</span>
                      </td>
                      <td className="py-3 text-muted text-xs">
                        {new Date(article.created_at).toLocaleDateString('fr-FR')}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>

        {/* Right column: Breakdowns */}
        <div className="space-y-4">
          {/* By status */}
          <div className="bg-surface border border-border rounded-xl p-5">
            <h4 className="font-title font-semibold text-white mb-3">Par statut</h4>
            <div className="space-y-2">
              {allStatuses.map(status => {
                const count = byStatus[status] ?? 0;
                return (
                  <div key={status} className="flex items-center justify-between">
                    <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${STATUS_COLORS[status]}`}>
                      {STATUS_LABELS[status]}
                    </span>
                    <span className="text-sm text-white font-medium">{count}</span>
                  </div>
                );
              })}
            </div>
          </div>

          {/* By language */}
          <div className="bg-surface border border-border rounded-xl p-5">
            <h4 className="font-title font-semibold text-white mb-3">Par langue</h4>
            <div className="space-y-2">
              {Object.entries(byLanguage).sort((a, b) => b[1] - a[1]).map(([lang, count]) => (
                <div key={lang}>
                  <div className="flex items-center justify-between mb-1">
                    <span className="text-xs text-muted uppercase">{LANG_FLAGS[lang] ?? lang.toUpperCase()}</span>
                    <span className="text-xs text-white">{count}</span>
                  </div>
                  <div className="w-full h-1.5 bg-surface2 rounded-full overflow-hidden">
                    <div
                      className="h-full bg-violet rounded-full"
                      style={{ width: `${Math.round((count / maxLangCount) * 100)}%` }}
                    />
                  </div>
                </div>
              ))}
              {Object.keys(byLanguage).length === 0 && (
                <p className="text-xs text-muted">Aucune donnee</p>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
