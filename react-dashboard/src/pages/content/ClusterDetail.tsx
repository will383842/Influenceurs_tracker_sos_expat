import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  fetchCluster,
  generateClusterBrief,
  generateFromCluster,
  generateClusterQa,
  deleteCluster,
} from '../../api/contentApi';
import type { TopicCluster, ClusterStatus, TopicClusterArticle, ResearchBrief } from '../../types/content';
import { toast } from '../../components/Toast';
import { ConfirmModal } from '../../components/ConfirmModal';
import { errMsg } from './helpers';

// ── Constants ───────────────────────────────────────────────
const CLUSTER_STATUS_COLORS: Record<ClusterStatus, string> = {
  pending: 'bg-muted/20 text-muted',
  ready: 'bg-blue-500/20 text-blue-400',
  generating: 'bg-amber/20 text-amber animate-pulse',
  generated: 'bg-success/20 text-success',
  archived: 'bg-muted/20 text-muted line-through',
};

const CLUSTER_STATUS_LABELS: Record<ClusterStatus, string> = {
  pending: 'En attente',
  ready: 'Pret',
  generating: 'Generation...',
  generated: 'Genere',
  archived: 'Archive',
};

function formatNumber(n: number): string {
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'M';
  if (n >= 1_000) return (n / 1_000).toFixed(1) + 'K';
  return String(n);
}

function formatDate(d: string): string {
  return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
}

function relevanceBgColor(score: number) {
  if (score >= 0.8) return 'bg-success/20 text-success';
  if (score >= 0.6) return 'bg-amber/20 text-amber';
  return 'bg-blue-500/20 text-blue-400';
}

// ── Component ───────────────────────────────────────────────
export default function ClusterDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [cluster, setCluster] = useState<TopicCluster | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionLoading, setActionLoading] = useState<string | null>(null);
  const [confirmAction, setConfirmAction] = useState<{ title: string; message: string; action: () => void } | null>(null);

  const loadCluster = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const res = await fetchCluster(Number(id));
      setCluster(res.data as unknown as TopicCluster);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Erreur de chargement';
      setError(msg);
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => { loadCluster(); }, [loadCluster]);

  const execAction = async (action: string) => {
    if (!cluster) return;
    setActionLoading(action);
    try {
      if (action === 'brief') {
        await generateClusterBrief(cluster.id);
        toast('success', 'Brief genere.');
      } else if (action === 'generate') {
        await generateFromCluster(cluster.id);
        toast('success', 'Generation lancee.');
      } else if (action === 'qa') {
        await generateClusterQa(cluster.id);
        toast('success', 'Q&A generees.');
      } else if (action === 'delete') {
        await deleteCluster(cluster.id);
        toast('success', 'Cluster supprime.');
        navigate('/content/clusters');
        return;
      }
      loadCluster();
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  const handleAction = (action: string) => {
    if (action === 'delete') {
      setConfirmAction({ title: 'Supprimer ce cluster', message: 'Cette action est irreversible.', action: () => execAction(action) });
    } else {
      execAction(action);
    }
  };

  if (loading) {
    return (
      <div className="p-4 md:p-6 space-y-6">
        <div className="animate-pulse bg-surface2 rounded-lg h-8 w-64 mb-4" />
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div className="space-y-4">
            {[1, 2, 3].map(i => <div key={i} className="animate-pulse bg-surface2 rounded-xl h-24" />)}
          </div>
          <div className="animate-pulse bg-surface2 rounded-xl h-96" />
        </div>
      </div>
    );
  }

  if (error || !cluster) {
    return (
      <div className="p-4 md:p-6">
        <div className="bg-surface border border-border rounded-xl p-8 text-center">
          <p className="text-danger mb-4">{error || 'Cluster introuvable'}</p>
          <button onClick={() => navigate('/content/clusters')} className="text-sm text-violet hover:text-violet-light transition-colors">Retour aux clusters</button>
        </div>
      </div>
    );
  }

  const canGenerate = cluster.status === 'pending' || cluster.status === 'ready';
  const articles: TopicClusterArticle[] = cluster.source_articles ?? [];
  const brief: ResearchBrief | null = cluster.research_brief ?? null;
  const generatedArticle = cluster.generated_article;

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Header */}
      <div className="flex items-start justify-between gap-4 flex-wrap">
        <div>
          <button onClick={() => navigate('/content/clusters')} className="text-xs text-muted hover:text-white transition-colors mb-2 inline-flex items-center gap-1">
            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" /></svg>
            Retour aux clusters
          </button>
          <h2 className="font-title text-2xl font-bold text-white">{cluster.name}</h2>
          <div className="flex items-center gap-3 mt-2 flex-wrap">
            <span className="text-sm text-muted capitalize">{cluster.country}</span>
            {cluster.category && (
              <>
                <span className="text-muted">|</span>
                <span className="inline-block px-1.5 py-0.5 rounded text-[10px] bg-violet/20 text-violet-light capitalize">{cluster.category}</span>
              </>
            )}
            <span className="text-muted uppercase text-xs">{cluster.language}</span>
            <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${CLUSTER_STATUS_COLORS[cluster.status]}`}>
              {CLUSTER_STATUS_LABELS[cluster.status]}
            </span>
            <span className="text-xs text-muted">{formatDate(cluster.created_at)}</span>
          </div>
          {cluster.description && <p className="text-sm text-muted mt-2">{cluster.description}</p>}
        </div>
        <div className="flex items-center gap-2 flex-wrap">
          {canGenerate && (
            <>
              <button onClick={() => handleAction('brief')} disabled={!!actionLoading} className="px-4 py-1.5 bg-blue-600 hover:bg-blue-500 text-white text-sm rounded-lg transition-colors disabled:opacity-50">
                {actionLoading === 'brief' ? 'Generation...' : 'Research Brief'}
              </button>
              <button onClick={() => handleAction('generate')} disabled={!!actionLoading} className="px-4 py-1.5 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors disabled:opacity-50">
                {actionLoading === 'generate' ? 'Generation...' : 'Generer Article'}
              </button>
              <button onClick={() => handleAction('qa')} disabled={!!actionLoading} className="px-4 py-1.5 bg-success/80 hover:bg-success text-white text-sm rounded-lg transition-colors disabled:opacity-50">
                {actionLoading === 'qa' ? 'Generation...' : 'Generer Q&A'}
              </button>
            </>
          )}
          <button onClick={() => handleAction('delete')} disabled={!!actionLoading} className="px-4 py-1.5 bg-surface2 text-danger hover:bg-danger/20 text-sm rounded-lg border border-border transition-colors disabled:opacity-50">
            Supprimer
          </button>
        </div>
      </div>

      {/* Keywords */}
      {cluster.keywords_detected && cluster.keywords_detected.length > 0 && (
        <div className="flex flex-wrap gap-2">
          {cluster.keywords_detected.map(kw => (
            <span key={kw} className="px-2 py-0.5 rounded bg-violet/10 text-violet-light text-xs">{kw}</span>
          ))}
        </div>
      )}

      {/* Two columns */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Left: Source articles */}
        <div className="space-y-3">
          <h3 className="font-title font-semibold text-white">Articles sources ({articles.length})</h3>
          {articles.length === 0 ? (
            <div className="bg-surface border border-border rounded-xl p-6 text-center text-muted text-sm">
              Aucun article source associe
            </div>
          ) : (
            articles.sort((a, b) => b.relevance_score - a.relevance_score).map(item => {
              const sa = item.source_article;
              return (
                <div
                  key={item.id}
                  className={`bg-surface border rounded-xl p-4 transition-colors ${item.is_primary ? 'border-violet/50' : 'border-border'}`}
                >
                  <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0 flex-1">
                      <div className="flex items-center gap-2 mb-1">
                        {item.is_primary && (
                          <span className="px-1.5 py-0.5 rounded text-[10px] bg-violet/20 text-violet-light font-medium">Principal</span>
                        )}
                        <span className={`px-1.5 py-0.5 rounded text-[10px] font-medium ${
                          item.processing_status === 'used' ? 'bg-success/20 text-success' :
                          item.processing_status === 'extracted' ? 'bg-blue-500/20 text-blue-400' :
                          'bg-muted/20 text-muted'
                        }`}>{item.processing_status}</span>
                      </div>
                      <p className="text-sm text-white font-medium">{sa?.title ?? `Article #${item.source_article_id}`}</p>
                      {sa?.url && (
                        <a href={sa.url} target="_blank" rel="noopener noreferrer" className="text-xs text-violet hover:text-violet-light truncate block mt-1">{sa.url}</a>
                      )}
                      <div className="flex items-center gap-3 mt-2">
                        {sa?.word_count && <span className="text-xs text-muted">{formatNumber(sa.word_count)} mots</span>}
                        {sa?.category && <span className="text-xs text-muted capitalize">{sa.category}</span>}
                      </div>
                    </div>
                    <div className="text-right flex-shrink-0">
                      <div className={`text-xs px-2 py-0.5 rounded ${relevanceBgColor(item.relevance_score)}`}>
                        {Math.round(item.relevance_score * 100)}%
                      </div>
                      <p className="text-[10px] text-muted mt-1">pertinence</p>
                    </div>
                  </div>
                </div>
              );
            })
          )}
        </div>

        {/* Right: Brief + generated article */}
        <div className="space-y-4">
          {/* Research brief */}
          {brief ? (
            <div className="bg-surface border border-border rounded-xl p-5 space-y-4">
              <h4 className="font-title font-semibold text-white">Research Brief</h4>
              <div className="flex items-center gap-3 text-xs text-muted">
                <span>{brief.tokens_used} tokens</span>
                <span>${(brief.cost_cents / 100).toFixed(2)}</span>
                <span>{formatDate(brief.created_at)}</span>
              </div>

              {brief.paa_questions && brief.paa_questions.length > 0 && (
                <div>
                  <p className="text-xs text-muted uppercase tracking-wide mb-1">PAA Questions</p>
                  <ul className="space-y-1">
                    {brief.paa_questions.map((q, i) => (
                      <li key={i} className="text-sm text-white">- {q}</li>
                    ))}
                  </ul>
                </div>
              )}

              {brief.suggested_keywords && (
                <div>
                  <p className="text-xs text-muted uppercase tracking-wide mb-1">Mots-cles suggeres</p>
                  <div className="space-y-2">
                    {brief.suggested_keywords.primary.length > 0 && (
                      <div className="flex flex-wrap gap-1">
                        <span className="text-[10px] text-muted mr-1">Primaires:</span>
                        {brief.suggested_keywords.primary.map(k => (
                          <span key={k} className="px-1.5 py-0.5 rounded text-[10px] bg-violet/20 text-violet-light">{k}</span>
                        ))}
                      </div>
                    )}
                    {brief.suggested_keywords.secondary.length > 0 && (
                      <div className="flex flex-wrap gap-1">
                        <span className="text-[10px] text-muted mr-1">Secondaires:</span>
                        {brief.suggested_keywords.secondary.map(k => (
                          <span key={k} className="px-1.5 py-0.5 rounded text-[10px] bg-blue-500/20 text-blue-400">{k}</span>
                        ))}
                      </div>
                    )}
                    {brief.suggested_keywords.long_tail.length > 0 && (
                      <div className="flex flex-wrap gap-1">
                        <span className="text-[10px] text-muted mr-1">Long-tail:</span>
                        {brief.suggested_keywords.long_tail.map(k => (
                          <span key={k} className="px-1.5 py-0.5 rounded text-[10px] bg-amber/20 text-amber">{k}</span>
                        ))}
                      </div>
                    )}
                  </div>
                </div>
              )}

              {brief.identified_gaps && brief.identified_gaps.length > 0 && (
                <div>
                  <p className="text-xs text-muted uppercase tracking-wide mb-1">Lacunes identifiees</p>
                  <ul className="space-y-1">
                    {brief.identified_gaps.map((g, i) => (
                      <li key={i} className="text-sm text-amber">- {g}</li>
                    ))}
                  </ul>
                </div>
              )}

              {brief.perplexity_response && (
                <div>
                  <p className="text-xs text-muted uppercase tracking-wide mb-1">Recherche Perplexity</p>
                  <div className="text-sm text-muted bg-bg rounded-lg p-3 max-h-48 overflow-y-auto whitespace-pre-wrap">{brief.perplexity_response}</div>
                </div>
              )}
            </div>
          ) : canGenerate ? (
            <div className="bg-surface border border-border rounded-xl p-5 text-center">
              <p className="text-sm text-muted mb-4">Aucun brief genere. Lancez la recherche pour enrichir le cluster.</p>
              <button onClick={() => handleAction('brief')} disabled={!!actionLoading} className="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm rounded-lg transition-colors disabled:opacity-50">
                {actionLoading === 'brief' ? 'Generation...' : 'Generer Research Brief'}
              </button>
            </div>
          ) : null}

          {/* Generated article */}
          {generatedArticle && (
            <div className="bg-surface border border-success/30 rounded-xl p-5">
              <h4 className="font-title font-semibold text-success mb-2">Article genere</h4>
              <p className="text-sm text-white mb-1">{generatedArticle.title}</p>
              {generatedArticle.excerpt && (
                <p className="text-xs text-muted mb-2 line-clamp-3">{generatedArticle.excerpt}</p>
              )}
              <div className="flex items-center gap-3 mb-2">
                <span className="text-xs text-muted">SEO: {generatedArticle.seo_score}/100</span>
                <span className="text-xs text-muted">{generatedArticle.word_count} mots</span>
              </div>
              <button onClick={() => navigate(`/content/articles/${generatedArticle.id}`)} className="text-xs text-violet hover:text-violet-light transition-colors">
                Voir l'article
              </button>
            </div>
          )}

          {/* Action panel if not generated */}
          {!generatedArticle && canGenerate && (
            <div className="bg-surface border border-border rounded-xl p-5 text-center space-y-3">
              <p className="text-sm text-muted">Cluster pret pour la generation.</p>
              <button onClick={() => handleAction('generate')} disabled={!!actionLoading} className="w-full px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors disabled:opacity-50">
                {actionLoading === 'generate' ? 'Generation...' : 'Generer Article'}
              </button>
              <button onClick={() => handleAction('qa')} disabled={!!actionLoading} className="w-full px-4 py-2 bg-success/80 hover:bg-success text-white text-sm rounded-lg transition-colors disabled:opacity-50">
                {actionLoading === 'qa' ? 'Generation...' : 'Generer Q&A'}
              </button>
            </div>
          )}
        </div>
      </div>

      <ConfirmModal
        open={!!confirmAction}
        title={confirmAction?.title ?? ''}
        message={confirmAction?.message ?? ''}
        variant="danger"
        confirmLabel="Supprimer"
        onConfirm={() => { confirmAction?.action(); setConfirmAction(null); }}
        onCancel={() => setConfirmAction(null)}
      />
    </div>
  );
}
