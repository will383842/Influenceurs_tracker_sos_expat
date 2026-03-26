import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  fetchQaEntry,
  updateQaEntry,
  publishQaEntry,
  fetchEndpoints,
} from '../../api/contentApi';
import type { QaEntry, QaSourceType, ContentStatus, PublishingEndpoint } from '../../types/content';
import { toast } from '../../components/Toast';
import { errMsg } from './helpers';
import { useDirtyGuard } from '../../hooks/useDirtyGuard';

// ── Constants ───────────────────────────────────────────────
const STATUS_COLORS: Record<ContentStatus, string> = {
  draft: 'bg-muted/20 text-muted',
  generating: 'bg-amber/20 text-amber animate-pulse',
  review: 'bg-blue-500/20 text-blue-400',
  scheduled: 'bg-violet/20 text-violet-light',
  published: 'bg-success/20 text-success',
  archived: 'bg-muted/20 text-muted line-through',
};

const STATUS_LABELS: Record<ContentStatus, string> = {
  draft: 'Brouillon',
  generating: 'Generation...',
  review: 'A relire',
  scheduled: 'Planifie',
  published: 'Publie',
  archived: 'Archive',
};

const SOURCE_COLORS: Record<QaSourceType, string> = {
  article_faq: 'bg-violet/20 text-violet-light',
  paa: 'bg-blue-500/20 text-blue-400',
  scraped: 'bg-amber/20 text-amber',
  manual: 'bg-success/20 text-success',
  ai_suggested: 'bg-pink-500/20 text-pink-400',
};

const SOURCE_LABELS: Record<QaSourceType, string> = {
  article_faq: 'FAQ Article',
  paa: 'PAA',
  scraped: 'Scraped',
  manual: 'Manuel',
  ai_suggested: 'IA suggere',
};

const inputClass = 'bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors';

function formatDate(d: string): string {
  return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
}

function wordCount(text: string): number {
  return text.trim().split(/\s+/).filter(Boolean).length;
}

// ── Component ───────────────────────────────────────────────
export default function QaDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { markDirty, markClean } = useDirtyGuard();
  const [qa, setQa] = useState<QaEntry | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [publishing, setPublishing] = useState(false);
  const [activeTab, setActiveTab] = useState<'content' | 'seo'>('content');
  const [endpoints, setEndpoints] = useState<PublishingEndpoint[]>([]);
  const [selectedEndpoint, setSelectedEndpoint] = useState<number>(0);
  const [scheduleAt, setScheduleAt] = useState('');
  const [htmlPreview, setHtmlPreview] = useState(true);

  // Editable fields
  const [question, setQuestion] = useState('');
  const [answerShort, setAnswerShort] = useState('');
  const [answerDetailedHtml, setAnswerDetailedHtml] = useState('');
  const [metaTitle, setMetaTitle] = useState('');
  const [metaDescription, setMetaDescription] = useState('');
  const [slug, setSlug] = useState('');

  const loadQa = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const res = await fetchQaEntry(Number(id));
      const data = res.data as unknown as QaEntry;
      setQa(data);
      setQuestion(data.question);
      setAnswerShort(data.answer_short);
      setAnswerDetailedHtml(data.answer_detailed_html ?? '');
      setMetaTitle(data.meta_title ?? '');
      setMetaDescription(data.meta_description ?? '');
      setSlug(data.slug);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Erreur de chargement';
      setError(msg);
    } finally {
      setLoading(false);
    }
  }, [id]);

  const loadEndpoints = useCallback(async () => {
    try {
      const res = await fetchEndpoints();
      const data = (res.data as unknown as PublishingEndpoint[]) ?? [];
      setEndpoints(data);
      if (data.length > 0) setSelectedEndpoint(data[0].id);
    } catch {
      // optional
    }
  }, []);

  useEffect(() => { loadQa(); }, [loadQa]);
  useEffect(() => { loadEndpoints(); }, [loadEndpoints]);

  const handleSave = async () => {
    if (!qa) return;
    setSaving(true);
    try {
      await updateQaEntry(qa.id, {
        question,
        answer_short: answerShort,
        answer_detailed_html: answerDetailedHtml,
        meta_title: metaTitle || null,
        meta_description: metaDescription || null,
        slug,
      });
      toast('success', 'Q&A sauvegardee.');
      markClean();
      loadQa();
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setSaving(false);
    }
  };

  const handlePublish = async () => {
    if (!qa) return;
    setPublishing(true);
    try {
      await publishQaEntry(qa.id);
      toast('success', 'Q&A publiee.');
      loadQa();
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setPublishing(false);
    }
  };

  if (loading) {
    return (
      <div className="p-4 md:p-6 space-y-6">
        <div className="animate-pulse bg-surface2 rounded-lg h-8 w-64 mb-4" />
        <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
          <div className="lg:col-span-3 space-y-4">
            {[1, 2, 3].map(i => <div key={i} className="animate-pulse bg-surface2 rounded-xl h-32" />)}
          </div>
          <div className="animate-pulse bg-surface2 rounded-xl h-96" />
        </div>
      </div>
    );
  }

  if (error || !qa) {
    return (
      <div className="p-4 md:p-6">
        <div className="bg-surface border border-border rounded-xl p-8 text-center">
          <p className="text-danger mb-4">{error || 'Q&A introuvable'}</p>
          <button onClick={() => navigate('/content/qa')} className="text-sm text-violet hover:text-violet-light transition-colors">Retour aux Q&A</button>
        </div>
      </div>
    );
  }

  const shortWordCount = wordCount(answerShort);
  const shortWordCountColor = shortWordCount >= 40 && shortWordCount <= 60 ? 'text-success' : 'text-danger';
  const costDollars = (qa.generation_cost_cents / 100).toFixed(2);

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Header */}
      <div>
        <button onClick={() => navigate('/content/qa')} className="text-xs text-muted hover:text-white transition-colors mb-2 inline-flex items-center gap-1">
          <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" /></svg>
          Retour aux Q&A
        </button>
        <h2 className="font-title text-2xl font-bold text-white">Detail Q&A</h2>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
        {/* Main content area */}
        <div className="lg:col-span-3 space-y-4">
          {/* Tabs */}
          <div className="flex gap-1 border-b border-border">
            <button
              onClick={() => setActiveTab('content')}
              className={`px-4 py-2 text-sm font-medium transition-colors border-b-2 ${activeTab === 'content' ? 'border-violet text-white' : 'border-transparent text-muted hover:text-white'}`}
            >
              Contenu
            </button>
            <button
              onClick={() => setActiveTab('seo')}
              className={`px-4 py-2 text-sm font-medium transition-colors border-b-2 ${activeTab === 'seo' ? 'border-violet text-white' : 'border-transparent text-muted hover:text-white'}`}
            >
              SEO & Publication
            </button>
          </div>

          {/* Tab: Contenu */}
          {activeTab === 'content' && (
            <div className="space-y-4">
              {/* Question */}
              <div className="bg-surface border border-border rounded-xl p-5">
                <label className="text-xs text-muted uppercase tracking-wide block mb-2">Question</label>
                <input
                  type="text"
                  value={question}
                  onChange={e => { setQuestion(e.target.value); markDirty(); }}
                  className={inputClass + ' w-full text-lg font-semibold'}
                />
              </div>

              {/* Reponse courte */}
              <div className="bg-surface border border-border rounded-xl p-5">
                <div className="flex items-center justify-between mb-2">
                  <label className="text-xs text-muted uppercase tracking-wide">Reponse courte</label>
                  <span className={`text-xs font-medium ${shortWordCountColor}`}>{shortWordCount} mots (cible: 40-60)</span>
                </div>
                <textarea
                  value={answerShort}
                  onChange={e => { setAnswerShort(e.target.value); markDirty(); }}
                  rows={4}
                  className={inputClass + ' w-full resize-y'}
                />
              </div>

              {/* Reponse detaillee */}
              <div className="bg-surface border border-border rounded-xl p-5">
                <div className="flex items-center justify-between mb-2">
                  <label className="text-xs text-muted uppercase tracking-wide">Reponse detaillee (HTML)</label>
                  <button
                    onClick={() => setHtmlPreview(!htmlPreview)}
                    className="text-xs text-violet hover:text-violet-light transition-colors"
                  >
                    {htmlPreview ? 'Editer' : 'Apercu'}
                  </button>
                </div>
                {htmlPreview ? (
                  <div
                    className="prose prose-invert prose-sm max-w-none bg-bg rounded-lg p-4 border border-border"
                    dangerouslySetInnerHTML={{ __html: answerDetailedHtml || '<p class="text-muted">Aucun contenu detaille</p>' }}
                  />
                ) : (
                  <textarea
                    value={answerDetailedHtml}
                    onChange={e => { setAnswerDetailedHtml(e.target.value); markDirty(); }}
                    rows={12}
                    className={inputClass + ' w-full resize-y font-mono text-xs'}
                  />
                )}
              </div>

              {/* Parent article link */}
              {qa.parent_article_id && (
                <div className="bg-surface border border-border rounded-xl p-4">
                  <span className="text-xs text-muted">Article parent: </span>
                  <button
                    onClick={() => navigate(`/content/articles/${qa.parent_article_id}`)}
                    className="text-xs text-violet hover:text-violet-light transition-colors"
                  >
                    Article #{qa.parent_article_id}
                  </button>
                </div>
              )}

              {/* Related QA links */}
              {qa.related_qa_ids && qa.related_qa_ids.length > 0 && (
                <div className="bg-surface border border-border rounded-xl p-4">
                  <span className="text-xs text-muted block mb-2">Q&A liees:</span>
                  <div className="flex flex-wrap gap-2">
                    {qa.related_qa_ids.map(rid => (
                      <button
                        key={rid}
                        onClick={() => navigate(`/content/qa/${rid}`)}
                        className="text-xs text-violet hover:text-violet-light transition-colors px-2 py-1 bg-violet/10 rounded"
                      >
                        Q&A #{rid}
                      </button>
                    ))}
                  </div>
                </div>
              )}

              {/* Save button */}
              <div className="flex justify-end">
                <button onClick={handleSave} disabled={saving} className="px-6 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors disabled:opacity-50">
                  {saving ? 'Sauvegarde...' : 'Sauvegarder'}
                </button>
              </div>
            </div>
          )}

          {/* Tab: SEO & Publication */}
          {activeTab === 'seo' && (
            <div className="space-y-4">
              {/* Meta fields */}
              <div className="bg-surface border border-border rounded-xl p-5 space-y-4">
                <div>
                  <div className="flex items-center justify-between mb-1">
                    <label className="text-xs text-muted uppercase tracking-wide">Meta Title</label>
                    <span className={`text-xs ${(metaTitle.length >= 50 && metaTitle.length <= 60) ? 'text-success' : 'text-amber'}`}>{metaTitle.length}/60</span>
                  </div>
                  <input type="text" value={metaTitle} onChange={e => setMetaTitle(e.target.value)} className={inputClass + ' w-full'} />
                </div>
                <div>
                  <div className="flex items-center justify-between mb-1">
                    <label className="text-xs text-muted uppercase tracking-wide">Meta Description</label>
                    <span className={`text-xs ${(metaDescription.length >= 120 && metaDescription.length <= 160) ? 'text-success' : 'text-amber'}`}>{metaDescription.length}/160</span>
                  </div>
                  <textarea value={metaDescription} onChange={e => setMetaDescription(e.target.value)} rows={3} className={inputClass + ' w-full resize-y'} />
                </div>
                <div>
                  <label className="text-xs text-muted uppercase tracking-wide block mb-1">Slug</label>
                  <input type="text" value={slug} onChange={e => setSlug(e.target.value)} className={inputClass + ' w-full'} />
                </div>
              </div>

              {/* JSON-LD preview */}
              {qa.json_ld && (
                <div className="bg-surface border border-border rounded-xl p-5">
                  <label className="text-xs text-muted uppercase tracking-wide block mb-2">JSON-LD</label>
                  <pre className="text-xs text-muted bg-bg rounded-lg p-3 overflow-x-auto max-h-48">{JSON.stringify(qa.json_ld, null, 2)}</pre>
                </div>
              )}

              {/* Publish section */}
              <div className="bg-surface border border-border rounded-xl p-5 space-y-4">
                <h4 className="font-title font-semibold text-white">Publication</h4>
                {endpoints.length > 0 && (
                  <select value={selectedEndpoint} onChange={e => setSelectedEndpoint(Number(e.target.value))} className={inputClass + ' w-full'}>
                    {endpoints.map(ep => <option key={ep.id} value={ep.id}>{ep.name}</option>)}
                  </select>
                )}
                <input type="datetime-local" value={scheduleAt} onChange={e => setScheduleAt(e.target.value)} className={inputClass + ' w-full'} placeholder="Planifier (optionnel)" />
                <div className="flex gap-3">
                  <button onClick={handleSave} disabled={saving} className="px-4 py-2 bg-surface2 text-white text-sm rounded-lg border border-border hover:bg-surface transition-colors disabled:opacity-50">
                    {saving ? 'Sauvegarde...' : 'Sauvegarder'}
                  </button>
                  <button onClick={handlePublish} disabled={publishing} className="px-4 py-2 bg-success/80 hover:bg-success text-white text-sm rounded-lg transition-colors disabled:opacity-50">
                    {publishing ? 'Publication...' : 'Publier'}
                  </button>
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Sidebar */}
        <div className="space-y-4">
          <div className="bg-surface border border-border rounded-xl p-5 space-y-4">
            <div>
              <p className="text-xs text-muted">Score SEO</p>
              <div className="flex items-center gap-2 mt-1">
                <div className="flex-1 h-2 bg-surface2 rounded-full overflow-hidden">
                  <div className="h-full bg-violet rounded-full" style={{ width: `${Math.min(qa.seo_score, 100)}%` }} />
                </div>
                <span className="text-sm font-bold text-white">{qa.seo_score}</span>
              </div>
            </div>
            <div>
              <p className="text-xs text-muted">Mots</p>
              <p className="text-lg font-bold text-white">{qa.word_count}</p>
            </div>
            <div>
              <p className="text-xs text-muted">Source</p>
              <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium mt-1 ${SOURCE_COLORS[qa.source_type]}`}>
                {SOURCE_LABELS[qa.source_type]}
              </span>
            </div>
            <div>
              <p className="text-xs text-muted">Statut</p>
              <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium mt-1 ${STATUS_COLORS[qa.status]}`}>
                {STATUS_LABELS[qa.status]}
              </span>
            </div>
            <div>
              <p className="text-xs text-muted">Cout</p>
              <p className="text-sm font-medium text-white">${costDollars}</p>
            </div>
            {qa.translations && qa.translations.length > 0 && (
              <div>
                <p className="text-xs text-muted mb-1">Traductions</p>
                <div className="flex flex-wrap gap-1">
                  {qa.translations.map(t => (
                    <button
                      key={t.id}
                      onClick={() => navigate(`/content/qa/${t.id}`)}
                      className="text-xs px-2 py-0.5 rounded bg-surface2 text-muted hover:text-white transition-colors uppercase"
                    >
                      {t.language}
                    </button>
                  ))}
                </div>
              </div>
            )}
            <div>
              <p className="text-xs text-muted">Cree le</p>
              <p className="text-sm text-white">{formatDate(qa.created_at)}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
