import React, { useEffect, useState, useCallback } from 'react';
import {
  fetchInternalLinksGraph,
  fetchOrphanedArticles,
  fixOrphanedArticle,
} from '../../api/contentApi';
import type { InternalLinksGraph, GeneratedArticle } from '../../types/content';
import { toast } from '../../components/Toast';
import { errMsg } from './helpers';

type View = 'list' | 'stats';

export default function SeoInternalLinks() {
  const [graph, setGraph] = useState<InternalLinksGraph | null>(null);
  const [orphaned, setOrphaned] = useState<GeneratedArticle[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [view, setView] = useState<View>('list');
  const [actionLoading, setActionLoading] = useState<number | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [graphRes, orphRes] = await Promise.all([
        fetchInternalLinksGraph(),
        fetchOrphanedArticles(),
      ]);
      setGraph(graphRes.data as unknown as InternalLinksGraph);
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

  // Computed stats
  const totalLinks = graph?.edges?.length ?? 0;
  const totalNodes = graph?.nodes?.length ?? 0;
  const avgLinks = totalNodes > 0 ? (totalLinks / totalNodes).toFixed(1) : '0';
  const orphanedCount = orphaned.length;

  // Top connected (most incoming links)
  const incomingCount: Record<number, number> = {};
  graph?.edges?.forEach(e => { incomingCount[e.target] = (incomingCount[e.target] || 0) + 1; });
  const topConnected = graph?.nodes
    ?.map(n => ({ ...n, incoming: incomingCount[n.id] ?? 0 }))
    .sort((a, b) => b.incoming - a.incoming)
    .slice(0, 10) ?? [];

  // Node title lookup
  const nodeTitle = (id: number) => graph?.nodes?.find(n => n.id === id)?.title ?? `#${id}`;

  if (loading) {
    return (
      <div className="p-4 md:p-6 space-y-6">
        <h2 className="font-title text-2xl font-bold text-white">Maillage interne</h2>
        <div className="grid grid-cols-3 gap-4">
          {[1, 2, 3].map(i => <div key={i} className="animate-pulse bg-surface border border-border rounded-xl h-24" />)}
        </div>
        <div className="animate-pulse bg-surface border border-border rounded-xl h-64" />
      </div>
    );
  }

  return (
    <div className="p-4 md:p-6 space-y-6">
      <h2 className="font-title text-2xl font-bold text-white">Maillage interne</h2>
      {error && <p className="text-danger text-sm">{error}</p>}

      {/* Stat cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div className="bg-surface border border-border rounded-xl p-5">
          <span className="text-xs text-muted uppercase tracking-wide">Total liens</span>
          <p className="text-2xl font-bold text-white mt-2">{totalLinks}</p>
        </div>
        <div className="bg-surface border border-border rounded-xl p-5">
          <span className="text-xs text-muted uppercase tracking-wide">Moyenne liens/article</span>
          <p className="text-2xl font-bold text-white mt-2">{avgLinks}</p>
        </div>
        <div className="bg-surface border border-border rounded-xl p-5">
          <span className="text-xs text-muted uppercase tracking-wide">Articles orphelins</span>
          <p className={`text-2xl font-bold mt-2 ${orphanedCount > 0 ? 'text-danger' : 'text-success'}`}>{orphanedCount}</p>
        </div>
      </div>

      {/* View toggle */}
      <div className="flex gap-1 border-b border-border">
        <button
          onClick={() => setView('list')}
          className={`px-4 py-2 text-sm font-medium transition-colors border-b-2 ${
            view === 'list' ? 'border-violet text-white' : 'border-transparent text-muted hover:text-white'
          }`}
        >
          Liste
        </button>
        <button
          onClick={() => setView('stats')}
          className={`px-4 py-2 text-sm font-medium transition-colors border-b-2 ${
            view === 'stats' ? 'border-violet text-white' : 'border-transparent text-muted hover:text-white'
          }`}
        >
          Stats
        </button>
      </div>

      {/* List view */}
      {view === 'list' && (
        <div className="bg-surface border border-border rounded-xl p-5">
          {(graph?.edges?.length ?? 0) === 0 ? (
            <p className="text-center py-8 text-muted text-sm">Aucun lien interne</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-left text-xs text-muted uppercase tracking-wide border-b border-border">
                    <th className="pb-3 pr-4">Article source</th>
                    <th className="pb-3 pr-4"></th>
                    <th className="pb-3 pr-4">Article cible</th>
                    <th className="pb-3 pr-4">Texte d'ancre</th>
                  </tr>
                </thead>
                <tbody>
                  {graph?.edges?.map((edge, idx) => (
                    <tr key={idx} className="border-b border-border/50 hover:bg-surface2/50 transition-colors">
                      <td className="py-3 pr-4 text-white max-w-[200px] truncate">{nodeTitle(edge.source)}</td>
                      <td className="py-3 pr-4 text-muted">{'\u2192'}</td>
                      <td className="py-3 pr-4 text-white max-w-[200px] truncate">{nodeTitle(edge.target)}</td>
                      <td className="py-3 pr-4 text-muted italic">{edge.anchor_text || '-'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* Stats view */}
      {view === 'stats' && (
        <div className="space-y-6">
          {/* Top connected */}
          {topConnected.length > 0 && (
            <div className="bg-surface border border-border rounded-xl p-5">
              <h3 className="font-title text-lg font-semibold text-white mb-4">Top articles connectes (liens entrants)</h3>
              <div className="space-y-2">
                {topConnected.map(node => (
                  <div key={node.id} className="flex items-center justify-between py-2 border-b border-border/50">
                    <div className="flex items-center gap-3">
                      <span className="text-white font-medium truncate max-w-[300px]">{node.title}</span>
                      <span className="text-xs text-muted uppercase">{node.language}</span>
                    </div>
                    <div className="flex items-center gap-3">
                      <span className="text-xs text-muted">SEO: {node.seo_score}</span>
                      <span className="px-2 py-0.5 bg-violet/20 text-violet rounded text-xs font-medium">
                        {node.incoming} liens
                      </span>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Orphaned articles */}
          <div className="bg-surface border border-border rounded-xl p-5">
            <h3 className="font-title text-lg font-semibold text-white mb-4">
              Articles orphelins ({orphanedCount})
            </h3>
            {orphanedCount === 0 ? (
              <p className="text-muted text-sm">Aucun article orphelin. Tous les articles ont au moins un lien entrant.</p>
            ) : (
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
                      {actionLoading === article.id ? '...' : 'Fix'}
                    </button>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
