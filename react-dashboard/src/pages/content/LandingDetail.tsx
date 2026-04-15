import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  fetchLanding,
  updateLanding,
  publishLanding,
  deleteLanding,
  manageLandingCtas,
  fetchEndpoints,
} from '../../api/contentApi';
import type { LandingPage, LandingSection, LandingCtaLink, ContentStatus, PublishingEndpoint } from '../../types/content';
import { toast } from '../../components/Toast';
import { ConfirmModal } from '../../components/ConfirmModal';
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

const SECTION_TYPES: { value: LandingSection['type']; label: string }[] = [
  { value: 'hero', label: 'Hero' },
  { value: 'features', label: 'Features' },
  { value: 'testimonials', label: 'Temoignages' },
  { value: 'cta', label: 'Call to Action' },
  { value: 'faq', label: 'FAQ' },
  { value: 'content', label: 'Contenu libre' },
];

const inputClass = 'bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors';

function seoColor(score: number): string {
  if (score >= 80) return 'text-success';
  if (score >= 50) return 'text-amber';
  return 'text-danger';
}

function seoBgColor(score: number): string {
  if (score >= 80) return 'bg-success';
  if (score >= 50) return 'bg-amber';
  return 'bg-danger';
}

function formatDate(d: string): string {
  return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
}

type TabKey = 'contenu' | 'publier';

// ── Component ───────────────────────────────────────────────
export default function LandingDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { markDirty, markClean } = useDirtyGuard();

  const [landing, setLanding] = useState<LandingPage | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<TabKey>('contenu');
  const [actionLoading, setActionLoading] = useState<string | null>(null);
  const [confirmAction, setConfirmAction] = useState<{ title: string; message: string; variant?: 'danger' | 'warning' | 'default'; action: () => void } | null>(null);

  // Editing
  const [editTitle, setEditTitle] = useState('');
  const [editSections, setEditSections] = useState<{ type: LandingSection['type']; content: string }[]>([]);

  // CTAs
  const [ctas, setCtas] = useState<{ url: string; text: string; position: string; style: string; sort_order: number }[]>([]);

  // Publish
  const [endpoints, setEndpoints] = useState<PublishingEndpoint[]>([]);
  const [selectedEndpoint, setSelectedEndpoint] = useState<number>(0);

  const loadLanding = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const res = await fetchLanding(Number(id));
      const data = res.data as unknown as LandingPage;
      setLanding(data);
      setEditTitle(data.title ?? '');
      setEditSections(
        (data.sections ?? []).map(s => ({
          type: s.type,
          content: typeof s.content === 'object' ? (s.content as Record<string, unknown>).text as string ?? JSON.stringify(s.content) : String(s.content),
        }))
      );
      setCtas(
        (data.cta_links ?? []).map(c => ({
          url: c.url,
          text: c.text,
          position: c.position,
          style: c.style,
          sort_order: c.sort_order,
        }))
      );
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Erreur de chargement');
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
      // non-blocking
    }
  }, []);

  useEffect(() => { loadLanding(); loadEndpoints(); }, [loadLanding, loadEndpoints]);

  // Section editing
  const updateSection = (index: number, field: 'type' | 'content', value: string) => {
    markDirty();
    setEditSections(prev => prev.map((s, i) =>
      i === index ? { ...s, [field]: field === 'type' ? value as LandingSection['type'] : value } : s
    ));
  };

  const addSection = () => {
    markDirty();
    setEditSections(prev => [...prev, { type: 'content', content: '' }]);
  };

  const removeSection = (index: number) => {
    markDirty();
    setEditSections(prev => prev.filter((_, i) => i !== index));
  };

  const moveSection = (index: number, direction: 'up' | 'down') => {
    const target = direction === 'up' ? index - 1 : index + 1;
    if (target < 0 || target >= editSections.length) return;
    markDirty();
    const updated = [...editSections];
    [updated[index], updated[target]] = [updated[target], updated[index]];
    setEditSections(updated);
  };

  // CTA editing
  const addCta = () => {
    markDirty();
    setCtas(prev => [...prev, { url: '', text: '', position: 'bottom', style: 'primary', sort_order: prev.length }]);
  };

  const removeCta = (index: number) => {
    markDirty();
    setCtas(prev => prev.filter((_, i) => i !== index));
  };

  const updateCta = (index: number, field: string, value: string | number) => {
    markDirty();
    setCtas(prev => prev.map((c, i) => i === index ? { ...c, [field]: value } : c));
  };

  // Saves
  const handleSaveContent = async () => {
    if (!landing) return;
    setActionLoading('save-content');
    try {
      const sections: LandingSection[] = editSections.map(s => ({
        type: s.type,
        content: { text: s.content },
      }));
      await updateLanding(landing.id, { title: editTitle, sections });
      toast('success', 'Contenu sauvegarde.');
      markClean();
      loadLanding();
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  const handleSaveCtas = async () => {
    if (!landing) return;
    setActionLoading('save-ctas');
    try {
      await manageLandingCtas(landing.id, ctas);
      toast('success', 'CTAs sauvegardes.');
      markClean();
      loadLanding();
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  const handlePublish = async () => {
    if (!landing || !selectedEndpoint) return;
    setActionLoading('publish');
    try {
      await publishLanding(landing.id, { endpoint_id: selectedEndpoint });
      toast('success', 'Landing publiee.');
      markClean();
      loadLanding();
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  const handleUnpublish = async () => {
    if (!landing) return;
    setActionLoading('unpublish');
    try {
      await updateLanding(landing.id, { status: 'review' });
      toast('success', 'Landing depubliee (statut: review).');
      loadLanding();
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  const handleDelete = () => {
    if (!landing) return;
    setConfirmAction({
      title: 'Supprimer la landing',
      message: `Voulez-vous vraiment supprimer "${landing.title}" ?`,
      variant: 'danger',
      action: async () => {
        try {
          await deleteLanding(landing.id);
          toast('success', 'Landing supprimee.');
          navigate('/content/landings');
        } catch (err) {
          toast('error', errMsg(err));
        }
      },
    });
  };

  if (loading) {
    return (
      <div className="p-4 md:p-6 space-y-6">
        <div className="animate-pulse bg-surface2 rounded-lg h-8 w-64" />
        <div className="grid grid-cols-1 lg:grid-cols-10 gap-6">
          <div className="lg:col-span-7 space-y-4">
            {[1, 2, 3].map(i => <div key={i} className="animate-pulse bg-surface2 rounded-xl h-20" />)}
          </div>
          <div className="lg:col-span-3 animate-pulse bg-surface2 rounded-xl h-64" />
        </div>
      </div>
    );
  }

  if (error || !landing) {
    return (
      <div className="p-4 md:p-6">
        <div className="bg-danger/10 border border-danger/30 rounded-xl p-6 text-center">
          <p className="text-danger">{error ?? 'Landing introuvable'}</p>
          <button onClick={() => navigate('/content/landings')} className="mt-4 text-sm text-muted hover:text-white transition-colors">
            Retour aux landings
          </button>
        </div>
      </div>
    );
  }

  const publicUrl = landing.canonical_url ?? (landing.slug ? `https://sos-expat.com/${landing.slug}` : null);

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Breadcrumb */}
      <div className="flex items-center justify-between">
        <div>
          <button onClick={() => navigate('/content/landings')} className="text-xs text-muted hover:text-white transition-colors inline-flex items-center gap-1 mb-2">
            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" /></svg>
            Retour aux landings
          </button>
          <h2 className="font-title text-2xl font-bold text-white">{landing.title || 'Sans titre'}</h2>
        </div>
        <button onClick={handleDelete} className="text-xs text-danger hover:text-red-300 transition-colors">
          Supprimer
        </button>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 border-b border-border">
        {([{ key: 'contenu' as TabKey, label: 'Contenu' }, { key: 'publier' as TabKey, label: 'Publier' }]).map(tab => (
          <button
            key={tab.key}
            onClick={() => setActiveTab(tab.key)}
            className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
              activeTab === tab.key ? 'border-violet text-violet-light' : 'border-transparent text-muted hover:text-white'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-10 gap-6">
        {/* Main content area */}
        <div className="lg:col-span-7 space-y-5">
          {activeTab === 'contenu' && (
            <>
              {/* Title */}
              <div className="bg-surface border border-border rounded-xl p-5">
                <label className="block text-xs text-muted uppercase tracking-wide mb-1">Titre</label>
                <input
                  type="text"
                  value={editTitle}
                  onChange={e => { setEditTitle(e.target.value); markDirty(); }}
                  className={inputClass + ' w-full'}
                />
              </div>

              {/* Sections */}
              <div className="bg-surface border border-border rounded-xl p-5">
                <div className="flex items-center justify-between mb-3">
                  <h3 className="text-sm font-medium text-white">Sections ({editSections.length})</h3>
                  <button type="button" onClick={addSection} className="text-xs text-violet hover:text-violet-light transition-colors">
                    + Ajouter
                  </button>
                </div>
                <div className="space-y-3">
                  {editSections.map((section, index) => (
                    <div key={index} className="bg-bg border border-border rounded-lg p-3">
                      <div className="flex items-center gap-3 mb-2">
                        <span className="text-xs text-muted font-mono">#{index + 1}</span>
                        <select
                          value={section.type}
                          onChange={e => updateSection(index, 'type', e.target.value)}
                          className={inputClass + ' w-40'}
                        >
                          {SECTION_TYPES.map(st => (
                            <option key={st.value} value={st.value}>{st.label}</option>
                          ))}
                        </select>
                        <div className="flex-1" />
                        <button type="button" onClick={() => moveSection(index, 'up')} disabled={index === 0} className="text-muted hover:text-white disabled:opacity-30 transition-colors">
                          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M5 15l7-7 7 7" /></svg>
                        </button>
                        <button type="button" onClick={() => moveSection(index, 'down')} disabled={index === editSections.length - 1} className="text-muted hover:text-white disabled:opacity-30 transition-colors">
                          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                        <button type="button" onClick={() => removeSection(index)} className="text-danger hover:text-red-300 transition-colors">
                          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                      </div>
                      <textarea
                        value={section.content}
                        onChange={e => updateSection(index, 'content', e.target.value)}
                        rows={3}
                        placeholder="Contenu de la section..."
                        className={inputClass + ' w-full resize-y'}
                      />
                    </div>
                  ))}
                </div>
                <button
                  type="button"
                  onClick={handleSaveContent}
                  disabled={actionLoading === 'save-content'}
                  className="mt-4 px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors disabled:opacity-50"
                >
                  {actionLoading === 'save-content' ? 'Sauvegarde...' : 'Sauvegarder le contenu'}
                </button>
              </div>

              {/* CTAs */}
              <div className="bg-surface border border-border rounded-xl p-5">
                <div className="flex items-center justify-between mb-3">
                  <h3 className="text-sm font-medium text-white">Liens CTA ({ctas.length})</h3>
                  <button type="button" onClick={addCta} className="text-xs text-violet hover:text-violet-light transition-colors">
                    + Ajouter un CTA
                  </button>
                </div>
                <div className="space-y-3">
                  {ctas.map((cta, index) => (
                    <div key={index} className="bg-bg border border-border rounded-lg p-3 grid grid-cols-2 gap-2">
                      <input type="text" value={cta.text} onChange={e => updateCta(index, 'text', e.target.value)} placeholder="Texte du bouton" className={inputClass} />
                      <input type="text" value={cta.url} onChange={e => updateCta(index, 'url', e.target.value)} placeholder="URL" className={inputClass} />
                      <select value={cta.position} onChange={e => updateCta(index, 'position', e.target.value)} className={inputClass}>
                        <option value="top">Haut</option>
                        <option value="middle">Milieu</option>
                        <option value="bottom">Bas</option>
                      </select>
                      <div className="flex items-center gap-2">
                        <select value={cta.style} onChange={e => updateCta(index, 'style', e.target.value)} className={inputClass + ' flex-1'}>
                          <option value="primary">Primaire</option>
                          <option value="secondary">Secondaire</option>
                          <option value="outline">Outline</option>
                        </select>
                        <button type="button" onClick={() => removeCta(index)} className="text-danger hover:text-red-300 transition-colors px-1">
                          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
                {ctas.length > 0 && (
                  <button
                    type="button"
                    onClick={handleSaveCtas}
                    disabled={actionLoading === 'save-ctas'}
                    className="mt-3 px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors disabled:opacity-50"
                  >
                    {actionLoading === 'save-ctas' ? 'Sauvegarde...' : 'Sauvegarder les CTAs'}
                  </button>
                )}
              </div>
            </>
          )}

          {activeTab === 'publier' && (
            <div className="bg-surface border border-border rounded-xl p-5 space-y-4">
              <h3 className="font-title font-semibold text-white">Publication</h3>
              <div>
                <label className="block text-xs text-muted uppercase tracking-wide mb-1">Endpoint</label>
                <select value={selectedEndpoint} onChange={e => setSelectedEndpoint(Number(e.target.value))} className={inputClass + ' w-full'}>
                  {endpoints.map(ep => (
                    <option key={ep.id} value={ep.id}>{ep.name} ({ep.type})</option>
                  ))}
                </select>
              </div>
              <button
                onClick={handlePublish}
                disabled={actionLoading === 'publish' || !selectedEndpoint}
                className="px-6 py-2 bg-success hover:bg-success/90 text-white text-sm rounded-lg transition-colors disabled:opacity-50"
              >
                {actionLoading === 'publish' ? 'Publication...' : 'Publier maintenant'}
              </button>
            </div>
          )}
        </div>

        {/* Sidebar */}
        <div className="lg:col-span-3 space-y-4">
          {/* URL publique */}
          {publicUrl && (
            <div className="bg-surface border border-border rounded-xl p-4">
              <h4 className="font-title font-semibold text-white text-sm mb-2">URL publique</h4>
              <a
                href={publicUrl}
                target="_blank"
                rel="noopener noreferrer"
                className="text-xs text-violet-light hover:text-violet break-all flex items-start gap-1.5 group"
              >
                <svg className="w-3.5 h-3.5 shrink-0 mt-0.5 group-hover:text-violet transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                {publicUrl.replace('https://sos-expat.com/', '')}
              </a>
              {landing.status === 'published' && (
                <button
                  onClick={handleUnpublish}
                  disabled={actionLoading === 'unpublish'}
                  className="mt-3 w-full px-3 py-1.5 text-xs bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/30 rounded-lg transition-colors disabled:opacity-50"
                >
                  {actionLoading === 'unpublish' ? 'Depublication...' : 'Depublier'}
                </button>
              )}
              {landing.status !== 'published' && (
                <button
                  onClick={handlePublish}
                  disabled={actionLoading === 'publish'}
                  className="mt-3 w-full px-3 py-1.5 text-xs bg-success/10 hover:bg-success/20 text-success border border-success/30 rounded-lg transition-colors disabled:opacity-50"
                >
                  {actionLoading === 'publish' ? 'Publication...' : 'Publier directement'}
                </button>
              )}
            </div>
          )}

          {/* Status card */}
          <div className="bg-surface border border-border rounded-xl p-5 space-y-3">
            <h4 className="font-title font-semibold text-white">Informations</h4>
            <div className="space-y-2 text-sm">
              <div className="flex justify-between">
                <span className="text-muted">Statut</span>
                <span className={`px-2 py-0.5 rounded text-xs ${STATUS_COLORS[landing.status]}`}>
                  {STATUS_LABELS[landing.status]}
                </span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted">Langue</span>
                <span className="text-white uppercase">{landing.language}</span>
              </div>
              {landing.country && (
                <div className="flex justify-between">
                  <span className="text-muted">Pays</span>
                  <span className="text-white">{landing.country}</span>
                </div>
              )}
              <div className="flex justify-between">
                <span className="text-muted">Sections</span>
                <span className="text-white">{landing.sections?.length ?? 0}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted">CTAs</span>
                <span className="text-white">{landing.cta_links?.length ?? 0}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted">Cree le</span>
                <span className="text-white text-xs">{formatDate(landing.created_at)}</span>
              </div>
            </div>
          </div>

          {/* SEO score */}
          <div className="bg-surface border border-border rounded-xl p-5">
            <h4 className="font-title font-semibold text-white mb-3">Score SEO</h4>
            <div className="flex items-center gap-3">
              <div className={`text-3xl font-bold ${seoColor(landing.seo_score)}`}>{landing.seo_score}</div>
              <div className="flex-1">
                <div className="w-full h-2 bg-surface2 rounded-full overflow-hidden">
                  <div className={`h-full rounded-full ${seoBgColor(landing.seo_score)}`} style={{ width: `${landing.seo_score}%` }} />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <ConfirmModal
        open={!!confirmAction}
        title={confirmAction?.title ?? ''}
        message={confirmAction?.message ?? ''}
        variant={confirmAction?.variant}
        onConfirm={() => { confirmAction?.action(); setConfirmAction(null); }}
        onCancel={() => setConfirmAction(null)}
      />
    </div>
  );
}
