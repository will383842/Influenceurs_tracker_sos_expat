import React, { useEffect, useState, useCallback } from 'react';
import {
  fetchQualityMonitoring,
  checkArticlePlagiarism,
  improveArticleQuality,
  rejectArticle,
  approveArticle,
} from '../../api/contentApi';
import type { QualityMonitoringData, GeneratedArticle } from '../../types/content';
import { toast } from '../../components/Toast';
import { errMsg } from './helpers';

function scoreColor(score: number): string {
  if (score >= 80) return 'bg-success';
  if (score >= 60) return 'bg-amber';
  return 'bg-danger';
}

function scoreTextColor(score: number): string {
  if (score >= 80) return 'text-success';
  if (score >= 60) return 'text-amber';
  return 'text-danger';
}

function plagiarismBadge(status: string | undefined): { bg: string; text: string; label: string } {
  switch (status) {
    case 'plagiarized': return { bg: 'bg-danger/20', text: 'text-danger', label: 'Plagie' };
    case 'similar': return { bg: 'bg-amber/20', text: 'text-amber', label: 'Similaire' };
    case 'original': return { bg: 'bg-success/20', text: 'text-success', label: 'Original' };
    default: return { bg: 'bg-muted/20', text: 'text-muted', label: 'Non verifie' };
  }
}

export default function QualityMonitoring() {
  const [data, setData] = useState<QualityMonitoringData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionLoading, setActionLoading] = useState<Record<number, string>>({});
  const [filter, setFilter] = useState<string>('all');
  const [tab, setTab] = useState<'flagged' | 'rejected'>('flagged');

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await fetchQualityMonitoring({ status: filter === 'all' ? undefined : filter });
      setData(res.data as unknown as QualityMonitoringData);
    } catch (err) {
      setError(errMsg(err));
    } finally {
      setLoading(false);
    }
  }, [filter]);

  useEffect(() => { load(); }, [load]);

  const handleAction = async (id: number, action: string) => {
    setActionLoading(prev => ({ ...prev, [id]: action }));
    try {
      switch (action) {
        case 'plagiarism':
          await checkArticlePlagiarism(id);
          toast('success', 'Verification plagiat lancee.');
          break;
        case 'improve':
          await improveArticleQuality(id);
          toast('success', 'Amelioration lancee.');
          break;
        case 'reject':
          if (!confirm('Rejeter cet article ? Il sera retire du flux de publication.')) return;
          await rejectArticle(id);
          toast('success', 'Article rejete.');
          break;
        case 'approve':
          await approveArticle(id);
          toast('success', 'Article approuve.');
          break;
      }
      load();
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(prev => { const n = { ...prev }; delete n[id]; return n; });
    }
  };

  if (loading && !data) {
    return (
      <div className="p-4 md:p-6 space-y-6">
        <div className="animate-pulse bg-surface2 rounded-lg h-8 w-72" />
        <div className="grid grid-cols-2 sm:grid-cols-6 gap-4">
          {[1, 2, 3, 4, 5, 6].map(i => <div key={i} className="animate-pulse bg-surface2 rounded-xl h-24" />)}
        </div>
      </div>
    );
  }

  if (error && !data) {
    return (
      <div className="p-4 md:p-6">
        <div className="bg-surface border border-border rounded-xl p-8 text-center">
          <p className="text-danger mb-4">{error}</p>
          <button onClick={load} className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg">Reessayer</button>
        </div>
      </div>
    );
  }

  const stats = data?.stats;
  const articles = data?.flagged_articles ?? [];
  const rejectedArticles = articles.filter(a => a.status === 'archived');
  const flaggedArticles = articles.filter(a => a.status !== 'archived');

  return (
    <div className="p-4 md:p-6 space-y-6">
      <h1 className="text-xl font-semibold text-t1">Qualite & Plagiat — Monitoring</h1>

      {/* Stats cards */}
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        <div className="bg-surface border border-border rounded-xl p-4 text-center">
          <p className="text-xs text-t3">Verifies</p>
          <p className="text-2xl font-bold text-t1">{stats?.total_checked ?? 0}</p>
        </div>
        <div className="bg-surface border border-border rounded-xl p-4 text-center">
          <p className="text-xs text-t3">Originaux</p>
          <p className="text-2xl font-bold text-success">{stats?.original ?? 0}</p>
        </div>
        <div className="bg-surface border border-border rounded-xl p-4 text-center">
          <p className="text-xs text-t3">Similaires</p>
          <p className="text-2xl font-bold text-amber">{stats?.similar ?? 0}</p>
        </div>
        <div className="bg-surface border border-border rounded-xl p-4 text-center">
          <p className="text-xs text-t3">Plagies</p>
          <p className="text-2xl font-bold text-danger">{stats?.plagiarized ?? 0}</p>
        </div>
        <div className="bg-surface border border-border rounded-xl p-4 text-center">
          <p className="text-xs text-t3">Qualite moy.</p>
          <p className={`text-2xl font-bold ${scoreTextColor(stats?.avg_quality_score ?? 0)}`}>{stats?.avg_quality_score ?? 0}</p>
        </div>
        <div className="bg-surface border border-border rounded-xl p-4 text-center">
          <p className="text-xs text-t3">SEO moy.</p>
          <p className={`text-2xl font-bold ${scoreTextColor(stats?.avg_seo_score ?? 0)}`}>{stats?.avg_seo_score ?? 0}</p>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-2 border-b border-border pb-2">
        <button onClick={() => setTab('flagged')}
                className={`px-4 py-2 text-sm rounded-t-lg ${tab === 'flagged' ? 'bg-surface border border-b-0 border-border text-t1 font-medium' : 'text-t3 hover:text-t1'}`}>
          Articles flagges ({flaggedArticles.length})
        </button>
        <button onClick={() => setTab('rejected')}
                className={`px-4 py-2 text-sm rounded-t-lg ${tab === 'rejected' ? 'bg-surface border border-b-0 border-border text-t1 font-medium' : 'text-t3 hover:text-t1'}`}>
          Rejetes ({rejectedArticles.length})
        </button>
      </div>

      {/* Filters */}
      <div className="flex gap-2 flex-wrap">
        {['all', 'similar', 'plagiarized', 'low-quality'].map(f => (
          <button key={f} onClick={() => setFilter(f)}
                  className={`px-3 py-1.5 text-xs rounded-full border ${filter === f ? 'bg-violet text-white border-violet' : 'border-border text-t2 hover:bg-surface2'}`}>
            {f === 'all' ? 'Tous' : f === 'similar' ? 'Similaires' : f === 'plagiarized' ? 'Plagies' : 'Qualite basse'}
          </button>
        ))}
      </div>

      {/* Articles table */}
      {(tab === 'flagged' ? flaggedArticles : rejectedArticles).length === 0 ? (
        <div className="bg-surface border border-border rounded-xl p-8 text-center text-t3">
          {tab === 'flagged' ? 'Aucun article flagge.' : 'Aucun article rejete.'}
        </div>
      ) : (
        <div className="bg-surface border border-border rounded-xl overflow-x-auto">
          <table className="w-full text-xs">
            <thead>
              <tr className="text-t3 uppercase tracking-wider border-b border-border">
                <th className="text-left py-3 px-3">Article</th>
                <th className="text-left py-3 px-2">Langue</th>
                <th className="text-left py-3 px-2">Qualite</th>
                <th className="text-left py-3 px-2">SEO</th>
                <th className="text-left py-3 px-2">Plagiat</th>
                <th className="text-right py-3 px-3">Actions</th>
              </tr>
            </thead>
            <tbody>
              {(tab === 'flagged' ? flaggedArticles : rejectedArticles).map(article => {
                const badge = plagiarismBadge((article as any).plagiarism_status);
                const isLoading = !!actionLoading[article.id];
                return (
                  <tr key={article.id} className="border-b border-border/50 hover:bg-surface2/50">
                    <td className="py-3 px-3 max-w-[250px]">
                      <a href={`/content/articles/${article.id}`} className="text-t1 hover:text-violet font-medium truncate block">
                        {article.title}
                      </a>
                      <span className="text-[10px] text-t3">{article.content_type} · {article.word_count} mots</span>
                    </td>
                    <td className="py-3 px-2">
                      <span className="px-2 py-0.5 bg-surface2 rounded text-[10px] uppercase">{article.language}</span>
                    </td>
                    <td className="py-3 px-2">
                      <div className="flex items-center gap-2">
                        <div className="w-16 h-2 bg-surface2 rounded-full overflow-hidden">
                          <div className={`h-full rounded-full ${scoreColor(article.quality_score)}`}
                               style={{ width: `${article.quality_score}%` }} />
                        </div>
                        <span className={`text-[11px] font-mono ${scoreTextColor(article.quality_score)}`}>{article.quality_score}</span>
                      </div>
                    </td>
                    <td className="py-3 px-2">
                      <div className="flex items-center gap-2">
                        <div className="w-16 h-2 bg-surface2 rounded-full overflow-hidden">
                          <div className={`h-full rounded-full ${scoreColor(article.seo_score)}`}
                               style={{ width: `${article.seo_score}%` }} />
                        </div>
                        <span className={`text-[11px] font-mono ${scoreTextColor(article.seo_score)}`}>{article.seo_score}</span>
                      </div>
                    </td>
                    <td className="py-3 px-2">
                      <span className={`px-2 py-0.5 rounded-full text-[10px] font-medium ${badge.bg} ${badge.text}`}>
                        {badge.label}
                      </span>
                    </td>
                    <td className="py-3 px-3 text-right">
                      <div className="flex gap-1 justify-end flex-wrap">
                        {tab === 'flagged' ? (
                          <>
                            <button onClick={() => handleAction(article.id, 'plagiarism')} disabled={isLoading}
                                    className="px-2 py-1 bg-info/20 text-info rounded text-[10px] hover:bg-info/30 disabled:opacity-50">
                              Plagiat
                            </button>
                            <button onClick={() => handleAction(article.id, 'improve')} disabled={isLoading}
                                    className="px-2 py-1 bg-violet/20 text-violet rounded text-[10px] hover:bg-violet/30 disabled:opacity-50">
                              Ameliorer
                            </button>
                            <button onClick={() => handleAction(article.id, 'approve')} disabled={isLoading}
                                    className="px-2 py-1 bg-success/20 text-success rounded text-[10px] hover:bg-success/30 disabled:opacity-50">
                              Approuver
                            </button>
                            <button onClick={() => handleAction(article.id, 'reject')} disabled={isLoading}
                                    className="px-2 py-1 bg-danger/20 text-danger rounded text-[10px] hover:bg-danger/30 disabled:opacity-50">
                              Rejeter
                            </button>
                          </>
                        ) : (
                          <button onClick={() => handleAction(article.id, 'approve')} disabled={isLoading}
                                  className="px-2 py-1 bg-success/20 text-success rounded text-[10px] hover:bg-success/30 disabled:opacity-50">
                            Restaurer
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
