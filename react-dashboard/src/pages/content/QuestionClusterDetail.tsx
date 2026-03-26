import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  fetchQuestionCluster,
  generateQaFromQuestionCluster,
  generateArticleFromQuestionCluster,
  generateBothFromQuestionCluster,
  skipQuestionCluster,
  deleteQuestionCluster,
} from '../../api/contentApi';
import type { QuestionCluster, QuestionClusterStatus, QuestionClusterItemWithQuestion } from '../../types/content';

// ── Constants ───────────────────────────────────────────────
const QC_STATUS_COLORS: Record<QuestionClusterStatus, string> = {
  pending: 'bg-muted/20 text-muted',
  ready: 'bg-blue-500/20 text-blue-400',
  generating_qa: 'bg-amber/20 text-amber animate-pulse',
  generating_article: 'bg-amber/20 text-amber animate-pulse',
  completed: 'bg-success/20 text-success',
  skipped: 'bg-muted/20 text-muted line-through',
};

const QC_STATUS_LABELS: Record<QuestionClusterStatus, string> = {
  pending: 'En attente',
  ready: 'Pret',
  generating_qa: 'Generation Q&A...',
  generating_article: 'Generation article...',
  completed: 'Termine',
  skipped: 'Ignore',
};

function formatNumber(n: number): string {
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'M';
  if (n >= 1_000) return (n / 1_000).toFixed(1) + 'K';
  return String(n);
}

function similarityBgColor(score: number) {
  if (score >= 0.8) return 'bg-success/20 text-success';
  if (score >= 0.6) return 'bg-amber/20 text-amber';
  return 'bg-blue-500/20 text-blue-400';
}

// ── Component ───────────────────────────────────────────────
export default function QuestionClusterDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [cluster, setCluster] = useState<QuestionCluster | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionLoading, setActionLoading] = useState<string | null>(null);

  const loadCluster = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const res = await fetchQuestionCluster(Number(id));
      setCluster(res.data as unknown as QuestionCluster);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Erreur de chargement';
      setError(msg);
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => { loadCluster(); }, [loadCluster]);

  const handleAction = async (action: string) => {
    if (!cluster) return;
    setActionLoading(action);
    try {
      if (action === 'qa') {
        await generateQaFromQuestionCluster(cluster.id);
      } else if (action === 'article') {
        await generateArticleFromQuestionCluster(cluster.id);
      } else if (action === 'both') {
        await generateBothFromQuestionCluster(cluster.id);
      } else if (action === 'skip') {
        await skipQuestionCluster(cluster.id);
      } else if (action === 'delete') {
        if (!window.confirm('Supprimer ce cluster ?')) { setActionLoading(null); return; }
        await deleteQuestionCluster(cluster.id);
        navigate('/content/question-clusters');
        return;
      }
      loadCluster();
    } catch {
      // silently handled
    } finally {
      setActionLoading(null);
    }
  };

  if (loading) {
    return (
      <div className="p-4 md:p-6 space-y-6">
        <div className="animate-pulse bg-surface2 rounded-lg h-8 w-64 mb-4" />
        <div className="grid grid-cols-1 lg:grid-cols-5 gap-6">
          <div className="lg:col-span-3 space-y-4">
            {[1, 2, 3].map(i => <div key={i} className="animate-pulse bg-surface2 rounded-xl h-24" />)}
          </div>
          <div className="lg:col-span-2 animate-pulse bg-surface2 rounded-xl h-96" />
        </div>
      </div>
    );
  }

  if (error || !cluster) {
    return (
      <div className="p-4 md:p-6">
        <div className="bg-surface border border-border rounded-xl p-8 text-center">
          <p className="text-danger mb-4">{error || 'Cluster introuvable'}</p>
          <button onClick={() => navigate('/content/question-clusters')} className="text-sm text-violet hover:text-violet-light transition-colors">
            Retour aux clusters
          </button>
        </div>
      </div>
    );
  }

  const questions: QuestionClusterItemWithQuestion[] = (cluster.questions ?? []).sort((a, b) => (b.question?.views ?? 0) - (a.question?.views ?? 0));
  const canGenerate = cluster.status === 'pending' || cluster.status === 'ready';
  const article = cluster.generated_article;

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Header */}
      <div className="flex items-start justify-between gap-4 flex-wrap">
        <div>
          <button onClick={() => navigate('/content/question-clusters')} className="text-xs text-muted hover:text-white transition-colors mb-2 inline-flex items-center gap-1">
            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
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
            <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${QC_STATUS_COLORS[cluster.status]}`}>
              {QC_STATUS_LABELS[cluster.status]}
            </span>
            <span className="text-xs text-muted">Popularite: {Math.round(cluster.popularity_score)}</span>
          </div>
        </div>
        <div className="flex items-center gap-2 flex-wrap">
          {canGenerate && (
            <>
              <button
                onClick={() => handleAction('qa')}
                disabled={!!actionLoading}
                className="px-4 py-1.5 bg-blue-600 hover:bg-blue-500 text-white text-sm rounded-lg transition-colors disabled:opacity-50"
              >
                {actionLoading === 'qa' ? 'Generation...' : 'Generer Q&A'}
              </button>
              <button
                onClick={() => handleAction('article')}
                disabled={!!actionLoading}
                className="px-4 py-1.5 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors disabled:opacity-50"
              >
                {actionLoading === 'article' ? 'Generation...' : 'Generer Article'}
              </button>
              <button
                onClick={() => handleAction('both')}
                disabled={!!actionLoading}
                className="px-4 py-1.5 bg-success/80 hover:bg-success text-white text-sm rounded-lg transition-colors disabled:opacity-50"
              >
                {actionLoading === 'both' ? 'Generation...' : 'Generer les deux'}
              </button>
              <button
                onClick={() => handleAction('skip')}
                disabled={!!actionLoading}
                className="px-4 py-1.5 bg-surface2 text-muted hover:text-white text-sm rounded-lg border border-border transition-colors disabled:opacity-50"
              >
                Ignorer
              </button>
            </>
          )}
          <button
            onClick={() => handleAction('delete')}
            disabled={!!actionLoading}
            className="px-4 py-1.5 bg-surface2 text-danger hover:bg-danger/20 text-sm rounded-lg border border-border transition-colors disabled:opacity-50"
          >
            Supprimer
          </button>
        </div>
      </div>

      {/* Two columns */}
      <div className="grid grid-cols-1 lg:grid-cols-5 gap-6">
        {/* Left: Questions list (60%) */}
        <div className="lg:col-span-3 space-y-3">
          <h3 className="font-title font-semibold text-white">Questions ({questions.length})</h3>
          {questions.length === 0 ? (
            <div className="bg-surface border border-border rounded-xl p-6 text-center text-muted text-sm">
              Aucune question associee
            </div>
          ) : (
            questions.map(item => {
              const q = item.question;
              return (
                <div
                  key={item.id}
                  className={`bg-surface border rounded-xl p-4 transition-colors ${
                    item.is_primary ? 'border-violet/50' : 'border-border'
                  }`}
                >
                  <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0 flex-1">
                      <div className="flex items-center gap-2 mb-1">
                        {item.is_primary && (
                          <span className="px-1.5 py-0.5 rounded text-[10px] bg-violet/20 text-violet-light font-medium flex items-center gap-1">
                            <svg className="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2l2.09 6.26H18l-4.77 3.47L14.82 18 10 14.27 5.18 18l1.59-6.27L2 8.26h5.91L10 2z" /></svg>
                            Principal
                          </span>
                        )}
                        {q?.is_closed && (
                          <span className="px-1.5 py-0.5 rounded text-[10px] bg-muted/20 text-muted font-medium">Ferme</span>
                        )}
                      </div>
                      <p className={`text-sm ${item.is_primary ? 'text-white font-semibold' : 'text-white font-medium'}`}>
                        {q?.title ?? `Question #${item.question_id}`}
                      </p>
                      {q?.url && (
                        <a
                          href={q.url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="text-xs text-violet hover:text-violet-light truncate block mt-1"
                          onClick={e => e.stopPropagation()}
                        >
                          {q.url}
                        </a>
                      )}
                      <div className="flex items-center gap-3 mt-2">
                        <span className="text-xs text-muted">{formatNumber(q?.views ?? 0)} vues</span>
                        <span className="text-xs text-muted">{q?.replies ?? 0} reponses</span>
                        {q?.qa_entry_id && (
                          <button
                            onClick={() => navigate(`/content/qa/${q.qa_entry_id}`)}
                            className="text-xs text-blue-400 hover:text-blue-300 transition-colors"
                          >
                            Voir Q&A
                          </button>
                        )}
                        {q?.generated_article_id && (
                          <button
                            onClick={() => navigate(`/content/articles/${q.generated_article_id}`)}
                            className="text-xs text-success hover:text-green-300 transition-colors"
                          >
                            Voir article
                          </button>
                        )}
                      </div>
                    </div>
                    <div className="text-right flex-shrink-0">
                      <div className={`text-xs px-2 py-0.5 rounded ${similarityBgColor(item.similarity_score)}`}>
                        {Math.round(item.similarity_score * 100)}%
                      </div>
                      <p className="text-[10px] text-muted mt-1">similarite</p>
                    </div>
                  </div>
                </div>
              );
            })
          )}
        </div>

        {/* Right: Generation panel (40%) */}
        <div className="lg:col-span-2 space-y-4">
          {/* Stats panel */}
          <div className="bg-surface border border-border rounded-xl p-5">
            <h4 className="font-title font-semibold text-white mb-3">Statistiques</h4>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <p className="text-xs text-muted">Vues totales</p>
                <p className="text-lg font-bold text-white">{formatNumber(cluster.total_views)}</p>
              </div>
              <div>
                <p className="text-xs text-muted">Reponses totales</p>
                <p className="text-lg font-bold text-white">{cluster.total_replies}</p>
              </div>
              <div>
                <p className="text-xs text-muted">Questions</p>
                <p className="text-lg font-bold text-white">{cluster.total_questions}</p>
              </div>
              <div>
                <p className="text-xs text-muted">Score popularite</p>
                <p className="text-lg font-bold text-white">{Math.round(cluster.popularity_score)}</p>
              </div>
            </div>
          </div>

          {/* Generation status */}
          {cluster.status !== 'completed' && cluster.status !== 'skipped' && !cluster.generated_article_id && cluster.generated_qa_count === 0 && (
            <div className="bg-surface border border-border rounded-xl p-5 text-center">
              <p className="text-sm text-muted mb-4">Ce cluster est pret pour la generation.</p>
              {canGenerate && (
                <div className="flex flex-col gap-2">
                  <button
                    onClick={() => handleAction('qa')}
                    disabled={!!actionLoading}
                    className="w-full px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm rounded-lg transition-colors disabled:opacity-50"
                  >
                    {actionLoading === 'qa' ? 'Generation...' : 'Generer Q&A'}
                  </button>
                  <button
                    onClick={() => handleAction('article')}
                    disabled={!!actionLoading}
                    className="w-full px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors disabled:opacity-50"
                  >
                    {actionLoading === 'article' ? 'Generation...' : 'Generer Article'}
                  </button>
                  <button
                    onClick={() => handleAction('both')}
                    disabled={!!actionLoading}
                    className="w-full px-4 py-2 bg-success/80 hover:bg-success text-white text-sm rounded-lg transition-colors disabled:opacity-50"
                  >
                    {actionLoading === 'both' ? 'Generation...' : 'Generer les deux'}
                  </button>
                </div>
              )}
            </div>
          )}

          {/* Q&A generated */}
          {cluster.generated_qa_count > 0 && (
            <div className="bg-surface border border-success/30 rounded-xl p-5">
              <h4 className="font-title font-semibold text-success mb-2">{cluster.generated_qa_count} Q&A generee{cluster.generated_qa_count > 1 ? 's' : ''}</h4>
              <p className="text-xs text-muted">Les Q&A sont accessibles depuis les questions individuelles ci-contre.</p>
            </div>
          )}

          {/* Article generated */}
          {cluster.generated_article_id && (
            <div className="bg-surface border border-success/30 rounded-xl p-5">
              <h4 className="font-title font-semibold text-success mb-2">Article genere</h4>
              {article ? (
                <div>
                  <p className="text-sm text-white mb-1">{article.title}</p>
                  {article.excerpt && (
                    <p className="text-xs text-muted mb-2 line-clamp-3">{article.excerpt}</p>
                  )}
                  <div className="flex items-center gap-3 mb-2">
                    <span className="text-xs text-muted">SEO: {article.seo_score}/100</span>
                    <span className="text-xs text-muted">{article.word_count} mots</span>
                  </div>
                  <button
                    onClick={() => navigate(`/content/articles/${cluster.generated_article_id}`)}
                    className="text-xs text-violet hover:text-violet-light transition-colors"
                  >
                    Voir l'article
                  </button>
                </div>
              ) : (
                <button
                  onClick={() => navigate(`/content/articles/${cluster.generated_article_id}`)}
                  className="text-sm text-violet hover:text-violet-light transition-colors"
                >
                  Voir l'article #{cluster.generated_article_id}
                </button>
              )}
            </div>
          )}

          {/* Skipped state */}
          {cluster.status === 'skipped' && (
            <div className="bg-surface border border-border rounded-xl p-5 text-center">
              <p className="text-sm text-muted">Ce cluster a ete ignore.</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
