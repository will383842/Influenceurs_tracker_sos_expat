import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  fetchComparative,
  updateComparative,
  publishComparative,
  fetchEndpoints,
} from '../../api/contentApi';
import type { Comparative, ContentStatus, PublishingEndpoint } from '../../types/content';
import { toast } from '../../components/Toast';
import { errMsg } from './helpers';

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

const inputClass = 'bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors';

function formatDate(d: string): string {
  return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
}

// ── Component ───────────────────────────────────────────────
export default function ComparativeDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [comp, setComp] = useState<Comparative | null>(null);
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
  const [title, setTitle] = useState('');
  const [contentHtml, setContentHtml] = useState('');
  const [metaTitle, setMetaTitle] = useState('');
  const [metaDescription, setMetaDescription] = useState('');
  const [slug, setSlug] = useState('');

  const loadComp = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const res = await fetchComparative(Number(id));
      const data = res.data as unknown as Comparative;
      setComp(data);
      setTitle(data.title);
      setContentHtml(data.content_html ?? '');
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

  useEffect(() => { loadComp(); }, [loadComp]);
  useEffect(() => { loadEndpoints(); }, [loadEndpoints]);

  const handleSave = async () => {
    if (!comp) return;
    setSaving(true);
    try {
      await updateComparative(comp.id, {
        title,
        content_html: contentHtml || null,
        meta_title: metaTitle || null,
        meta_description: metaDescription || null,
        slug,
      });
      toast('success', 'Comparatif sauvegarde.');
      loadComp();
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setSaving(false);
    }
  };

  const handlePublish = async () => {
    if (!comp || !selectedEndpoint) return;
    setPublishing(true);
    try {
      await publishComparative(comp.id, {
        endpoint_id: selectedEndpoint,
        scheduled_at: scheduleAt || undefined,
      });
      toast('success', 'Comparatif publie.');
      loadComp();
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
        <div className="space-y-4">
          {[1, 2, 3].map(i => <div key={i} className="animate-pulse bg-surface2 rounded-xl h-32" />)}
        </div>
      </div>
    );
  }

  if (error || !comp) {
    return (
      <div className="p-4 md:p-6">
        <div className="bg-surface border border-border rounded-xl p-8 text-center">
          <p className="text-danger mb-4">{error || 'Comparatif introuvable'}</p>
          <button onClick={() => navigate('/content/comparatives')} className="text-sm text-violet hover:text-violet-light transition-colors">Retour aux comparatifs</button>
        </div>
      </div>
    );
  }

  const costDollars = (comp.generation_cost_cents / 100).toFixed(2);

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Header */}
      <div className="flex items-start justify-between gap-4 flex-wrap">
        <div>
          <button onClick={() => navigate('/content/comparatives')} className="text-xs text-muted hover:text-white transition-colors mb-2 inline-flex items-center gap-1">
            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" /></svg>
            Retour aux comparatifs
          </button>
          <h2 className="font-title text-2xl font-bold text-white">{comp.title}</h2>
          <div className="flex items-center gap-3 mt-2 flex-wrap">
            <span className="text-sm text-muted uppercase">{comp.language}</span>
            {comp.country && <span className="text-sm text-muted capitalize">{comp.country}</span>}
            <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${STATUS_COLORS[comp.status]}`}>
              {STATUS_LABELS[comp.status]}
            </span>
            <span className="text-xs text-muted">SEO: {comp.seo_score}/100</span>
            <span className="text-xs text-muted">Cout: ${costDollars}</span>
            <span className="text-xs text-muted">{formatDate(comp.created_at)}</span>
          </div>
        </div>
      </div>

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

      {/* Tab: Content */}
      {activeTab === 'content' && (
        <div className="space-y-4">
          {/* Title */}
          <div className="bg-surface border border-border rounded-xl p-5">
            <label className="text-xs text-muted uppercase tracking-wide block mb-2">Titre</label>
            <input type="text" value={title} onChange={e => setTitle(e.target.value)} className={inputClass + ' w-full text-lg font-semibold'} />
          </div>

          {/* Entities */}
          <div className="bg-surface border border-border rounded-xl p-5">
            <label className="text-xs text-muted uppercase tracking-wide block mb-3">Entites comparees ({comp.entities.length})</label>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
              {comp.entities.map((entity, i) => (
                <div key={i} className="bg-bg border border-border rounded-lg p-3">
                  <p className="text-sm text-white font-medium">{entity.name}</p>
                  {entity.description && <p className="text-xs text-muted mt-1">{entity.description}</p>}
                  {entity.rating !== undefined && (
                    <div className="flex items-center gap-1 mt-2">
                      <span className="text-xs text-amber">{entity.rating}/10</span>
                      <div className="flex-1 h-1.5 bg-surface2 rounded-full overflow-hidden">
                        <div className="h-full bg-amber rounded-full" style={{ width: `${entity.rating * 10}%` }} />
                      </div>
                    </div>
                  )}
                  {entity.pros && entity.pros.length > 0 && (
                    <div className="mt-2">
                      <p className="text-[10px] text-success uppercase">Avantages</p>
                      {entity.pros.map((p, j) => <p key={j} className="text-xs text-muted">+ {p}</p>)}
                    </div>
                  )}
                  {entity.cons && entity.cons.length > 0 && (
                    <div className="mt-1">
                      <p className="text-[10px] text-danger uppercase">Inconvenients</p>
                      {entity.cons.map((c, j) => <p key={j} className="text-xs text-muted">- {c}</p>)}
                    </div>
                  )}
                </div>
              ))}
            </div>
          </div>

          {/* Comparison data */}
          {comp.comparison_data && Object.keys(comp.comparison_data).length > 0 && (
            <div className="bg-surface border border-border rounded-xl p-5">
              <label className="text-xs text-muted uppercase tracking-wide block mb-2">Donnees de comparaison</label>
              <pre className="text-xs text-muted bg-bg rounded-lg p-3 overflow-x-auto max-h-48">{JSON.stringify(comp.comparison_data, null, 2)}</pre>
            </div>
          )}

          {/* Content HTML */}
          <div className="bg-surface border border-border rounded-xl p-5">
            <div className="flex items-center justify-between mb-2">
              <label className="text-xs text-muted uppercase tracking-wide">Contenu HTML</label>
              <button onClick={() => setHtmlPreview(!htmlPreview)} className="text-xs text-violet hover:text-violet-light transition-colors">
                {htmlPreview ? 'Editer' : 'Apercu'}
              </button>
            </div>
            {htmlPreview ? (
              <div
                className="prose prose-invert prose-sm max-w-none bg-bg rounded-lg p-4 border border-border"
                dangerouslySetInnerHTML={{ __html: contentHtml || '<p class="text-muted">Aucun contenu</p>' }}
              />
            ) : (
              <textarea value={contentHtml} onChange={e => setContentHtml(e.target.value)} rows={16} className={inputClass + ' w-full resize-y font-mono text-xs'} />
            )}
          </div>

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

          {/* JSON-LD */}
          {comp.json_ld && (
            <div className="bg-surface border border-border rounded-xl p-5">
              <label className="text-xs text-muted uppercase tracking-wide block mb-2">JSON-LD</label>
              <pre className="text-xs text-muted bg-bg rounded-lg p-3 overflow-x-auto max-h-48">{JSON.stringify(comp.json_ld, null, 2)}</pre>
            </div>
          )}

          {/* Hreflang */}
          {comp.hreflang_map && Object.keys(comp.hreflang_map).length > 0 && (
            <div className="bg-surface border border-border rounded-xl p-5">
              <label className="text-xs text-muted uppercase tracking-wide block mb-2">Hreflang</label>
              <div className="space-y-1">
                {Object.entries(comp.hreflang_map).map(([lang, url]) => (
                  <div key={lang} className="flex items-center gap-2">
                    <span className="text-xs text-white uppercase w-8">{lang}</span>
                    <span className="text-xs text-muted truncate">{url}</span>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Publish */}
          <div className="bg-surface border border-border rounded-xl p-5 space-y-4">
            <h4 className="font-title font-semibold text-white">Publication</h4>
            {endpoints.length > 0 && (
              <select value={selectedEndpoint} onChange={e => setSelectedEndpoint(Number(e.target.value))} className={inputClass + ' w-full'}>
                {endpoints.map(ep => <option key={ep.id} value={ep.id}>{ep.name}</option>)}
              </select>
            )}
            <input type="datetime-local" value={scheduleAt} onChange={e => setScheduleAt(e.target.value)} className={inputClass + ' w-full'} />
            <div className="flex gap-3">
              <button onClick={handleSave} disabled={saving} className="px-4 py-2 bg-surface2 text-white text-sm rounded-lg border border-border hover:bg-surface transition-colors disabled:opacity-50">
                {saving ? 'Sauvegarde...' : 'Sauvegarder'}
              </button>
              <button onClick={handlePublish} disabled={publishing || !selectedEndpoint} className="px-4 py-2 bg-success/80 hover:bg-success text-white text-sm rounded-lg transition-colors disabled:opacity-50">
                {publishing ? 'Publication...' : 'Publier'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
