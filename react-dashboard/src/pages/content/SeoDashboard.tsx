import React, { useEffect, useState, useCallback } from 'react';
import {
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Cell,
} from 'recharts';
import {
  fetchSeoDashboard,
  fetchHreflangMatrix,
  fetchOrphanedArticles,
  analyzeSeo,
  fixOrphanedArticle,
} from '../../api/contentApi';
import type { SeoDashboard as SeoDashboardType, HreflangMatrixEntry, GeneratedArticle } from '../../types/content';
import { toast } from '../../components/Toast';
import { errMsg } from './helpers';

const LANGUAGES = ['fr', 'en', 'de', 'es', 'pt', 'ru', 'zh', 'ar', 'hi'];

const inputClass = 'bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors';

function scoreColor(score: number): string {
  if (score < 60) return '#ef4444';
  if (score < 80) return '#f59e0b';
  return '#22c55e';
}

function severityIcon(severity: string): string {
  if (severity === 'error') return '\u274C';
  if (severity === 'warning') return '\u26A0\uFE0F';
  return '\u2139\uFE0F';
}

export default function SeoDashboard() {
  const [dashboard, setDashboard] = useState<SeoDashboardType | null>(null);
  const [matrix, setMatrix] = useState<HreflangMatrixEntry[]>([]);
  const [orphaned, setOrphaned] = useState<GeneratedArticle[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionLoading, setActionLoading] = useState<number | null>(null);
  const [analyzeLoading, setAnalyzeLoading] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [dashRes, matRes, orphRes] = await Promise.all([
        fetchSeoDashboard(),
        fetchHreflangMatrix(),
        fetchOrphanedArticles(),
      ]);
      setDashboard(dashRes.data as unknown as SeoDashboardType);
      setMatrix((matRes.data as unknown as HreflangMatrixEntry[]) ?? []);
      setOrphaned((orphRes.data as unknown as GeneratedArticle[]) ?? []);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Erreur de chargement');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const handleFixOrphaned = async (id: number) => {
    setActionLoading(id);
    try {
      await fixOrphanedArticle(id);
      toast('success', 'Article corrige.');
      load();
    } catch (err) { toast('error', errMsg(err)); } finally {
      setActionLoading(null);
    }
  };

  const handleAnalyzeAll = async () => {
    setAnalyzeLoading(true);
    try {
      await analyzeSeo({ model_type: 'all', model_id: 0 });
      toast('success', 'Analyse SEO lancee.');
      load();
    } catch (err) { toast('error', errMsg(err)); } finally {
      setAnalyzeLoading(false);
    }
  };

  // Computed stats
  const avgScore = dashboard?.scores_by_language?.length
    ? Math.round(dashboard.scores_by_language.reduce((s, l) => s + l.avg_score * l.count, 0) / dashboard.scores_by_language.reduce((s, l) => s + l.count, 0))
    : 0;
  const totalIndexed = dashboard?.scores_by_language?.reduce((s, l) => s + l.count, 0) ?? 0;
  const orphanedCount = dashboard?.orphaned_count ?? orphaned.length;
  const hreflangCoverage = matrix.length > 0
    ? Math.round(
        (matrix.reduce((s, m) => s + LANGUAGES.filter(l => m.translations[l]).length, 0) /
          (matrix.length * LANGUAGES.length)) * 100
      )
    : 0;

  const statCards = [
    { label: 'Score SEO moyen', value: avgScore, suffix: '/100', color: scoreColor(avgScore) },
    { label: 'Total indexes', value: totalIndexed, color: '#8b5cf6' },
    { label: 'Articles orphelins', value: orphanedCount, color: orphanedCount > 0 ? '#ef4444' : '#22c55e' },
    { label: 'Couverture hreflang', value: `${hreflangCoverage}%`, color: scoreColor(hreflangCoverage) },
  ];

  const chartData = (dashboard?.scores_by_language ?? []).map(l => ({
    language: l.language.toUpperCase(),
    score: Math.round(l.avg_score),
    count: l.count,
    fill: scoreColor(l.avg_score),
  }));

  if (loading) {
    return (
      <div className="p-4 md:p-6 space-y-6">
        <h2 className="font-title text-2xl font-bold text-white">Dashboard SEO</h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {[1, 2, 3, 4].map(i => <div key={i} className="animate-pulse bg-surface border border-border rounded-xl h-24" />)}
        </div>
        <div className="animate-pulse bg-surface border border-border rounded-xl h-64" />
      </div>
    );
  }

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between gap-3 flex-wrap">
        <h2 className="font-title text-2xl font-bold text-white">Dashboard SEO</h2>
        <div className="flex gap-2">
          <button
            onClick={handleAnalyzeAll}
            disabled={analyzeLoading}
            className="px-4 py-1.5 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors disabled:opacity-50"
          >
            {analyzeLoading ? 'Analyse...' : 'Analyser tout'}
          </button>
          <button className="px-4 py-1.5 bg-surface2 hover:bg-surface2/80 text-white text-sm rounded-lg transition-colors border border-border">
            Generer sitemap
          </button>
        </div>
      </div>

      {error && <p className="text-danger text-sm">{error}</p>}

      {/* Stat Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {statCards.map(card => (
          <div key={card.label} className="bg-surface border border-border rounded-xl p-5">
            <span className="text-xs text-muted uppercase tracking-wide">{card.label}</span>
            <p className="text-2xl font-bold mt-2" style={{ color: card.color }}>
              {card.value}{card.suffix ?? ''}
            </p>
          </div>
        ))}
      </div>

      {/* Score by language chart */}
      {chartData.length > 0 && (
        <div className="bg-surface border border-border rounded-xl p-5">
          <h3 className="font-title text-lg font-semibold text-white mb-4">Score par langue</h3>
          <ResponsiveContainer width="100%" height={300}>
            <BarChart data={chartData} layout="vertical" margin={{ left: 40, right: 20 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="#333" />
              <XAxis type="number" domain={[0, 100]} tick={{ fill: '#999', fontSize: 12 }} />
              <YAxis dataKey="language" type="category" tick={{ fill: '#999', fontSize: 12 }} width={50} />
              <Tooltip
                contentStyle={{ background: '#1e1e2e', border: '1px solid #333', borderRadius: 8, color: '#fff' }}
                formatter={(value: number, _name: string, props: { payload: { count: number } }) => [`${value}/100 (${props.payload.count} articles)`, 'Score']}
              />
              <Bar dataKey="score" radius={[0, 4, 4, 0]}>
                {chartData.map((entry, idx) => (
                  <Cell key={idx} fill={entry.fill} />
                ))}
              </Bar>
            </BarChart>
          </ResponsiveContainer>
        </div>
      )}

      {/* Top Issues */}
      {(dashboard?.top_issues ?? []).length > 0 && (
        <div className="bg-surface border border-border rounded-xl p-5">
          <h3 className="font-title text-lg font-semibold text-white mb-4">Problemes principaux</h3>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs text-muted uppercase tracking-wide border-b border-border">
                  <th className="pb-3 pr-4">Severite</th>
                  <th className="pb-3 pr-4">Type</th>
                  <th className="pb-3 pr-4">Occurrences</th>
                  <th className="pb-3">Action</th>
                </tr>
              </thead>
              <tbody>
                {(dashboard?.top_issues ?? []).map((issue, idx) => (
                  <tr key={idx} className="border-b border-border/50">
                    <td className="py-3 pr-4 text-lg">{severityIcon(issue.severity)}</td>
                    <td className="py-3 pr-4 text-white capitalize">{issue.type.replace(/_/g, ' ')}</td>
                    <td className="py-3 pr-4 text-white font-medium">{issue.count}</td>
                    <td className="py-3">
                      <button className="text-xs text-violet hover:text-violet-light transition-colors">
                        Corriger
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Hreflang Matrix */}
      {matrix.length > 0 && (
        <div className="bg-surface border border-border rounded-xl p-5">
          <h3 className="font-title text-lg font-semibold text-white mb-4">Matrice Hreflang</h3>
          <div className="overflow-x-auto max-h-96">
            <table className="w-full text-sm">
              <thead className="sticky top-0 bg-surface">
                <tr className="text-left text-xs text-muted uppercase tracking-wide border-b border-border">
                  <th className="pb-3 pr-4 min-w-[200px]">Article</th>
                  <th className="pb-3 pr-2">Langue</th>
                  {LANGUAGES.map(lang => (
                    <th key={lang} className="pb-3 px-2 text-center">{lang.toUpperCase()}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {matrix.map(entry => (
                  <tr key={entry.article_id} className="border-b border-border/50">
                    <td className="py-2 pr-4 text-white truncate max-w-[250px]">{entry.title}</td>
                    <td className="py-2 pr-2 text-muted uppercase text-xs">{entry.language}</td>
                    {LANGUAGES.map(lang => (
                      <td key={lang} className="py-2 px-2 text-center">
                        {entry.translations[lang]
                          ? <span className="text-success">{'\u2705'}</span>
                          : <span className="text-muted">{'\u274C'}</span>
                        }
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Orphaned Articles */}
      {orphaned.length > 0 && (
        <div className="bg-surface border border-border rounded-xl p-5">
          <h3 className="font-title text-lg font-semibold text-white mb-4">Articles orphelins ({orphaned.length})</h3>
          <div className="space-y-2">
            {orphaned.map(article => (
              <div key={article.id} className="flex items-center justify-between py-2 border-b border-border/50">
                <div>
                  <span className="text-white text-sm">{article.title}</span>
                  <span className="ml-2 text-xs text-muted">SEO: {article.seo_score}/100</span>
                </div>
                <button
                  onClick={() => handleFixOrphaned(article.id)}
                  disabled={actionLoading === article.id}
                  className="px-3 py-1 text-xs bg-violet/20 text-violet hover:bg-violet/30 rounded-lg transition-colors disabled:opacity-50"
                >
                  {actionLoading === article.id ? '...' : 'Fix maillage'}
                </button>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
