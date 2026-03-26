import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  fetchPressRelease,
  updatePressRelease,
  publishPressRelease,
  deletePressRelease,
  exportPressReleasePdf,
  exportPressReleaseWord,
  fetchEndpoints,
} from '../../api/contentApi';
import type { PressRelease, ContentStatus, PublishingEndpoint } from '../../types/content';
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

function downloadBlob(data: unknown, filename: string) {
  const blob = data instanceof Blob ? data : new Blob([data as BlobPart]);
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

type TabKey = 'contenu' | 'publier';

// ── Component ───────────────────────────────────────────────
export default function PressDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { markDirty, markClean } = useDirtyGuard();

  const [release, setRelease] = useState<PressRelease | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<TabKey>('contenu');
  const [actionLoading, setActionLoading] = useState<string | null>(null);
  const [confirmAction, setConfirmAction] = useState<{ title: string; message: string; variant?: 'danger' | 'warning' | 'default'; action: () => void } | null>(null);

  // Editing
  const [editTitle, setEditTitle] = useState('');
  const [editExcerpt, setEditExcerpt] = useState('');
  const [editContent, setEditContent] = useState('');
  const [editMode, setEditMode] = useState(false);

  // Publish
  const [endpoints, setEndpoints] = useState<PublishingEndpoint[]>([]);
  const [selectedEndpoint, setSelectedEndpoint] = useState<number>(0);
  const [scheduleDate, setScheduleDate] = useState('');

  const loadRelease = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const res = await fetchPressRelease(Number(id));
      const data = res.data as unknown as PressRelease;
      setRelease(data);
      setEditTitle(data.title ?? '');
      setEditExcerpt(data.excerpt ?? '');
      setEditContent(data.content_html ?? '');
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

  useEffect(() => { loadRelease(); loadEndpoints(); }, [loadRelease, loadEndpoints]);

  const handleSaveContent = async () => {
    if (!release) return;
    setActionLoading('save');
    try {
      await updatePressRelease(release.id, {
        title: editTitle,
        excerpt: editExcerpt,
        content_html: editContent,
      });
      toast('success', 'Contenu sauvegarde.');
      setEditMode(false);
      markClean();
      loadRelease();
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  const handlePublish = async () => {
    if (!release || !selectedEndpoint) return;
    setActionLoading('publish');
    try {
      await publishPressRelease(release.id, { endpoint_id: selectedEndpoint });
      toast('success', 'Communique publie.');
      markClean();
      loadRelease();
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  const handleExportPdf = async () => {
    if (!release) return;
    setActionLoading('pdf');
    try {
      const res = await exportPressReleasePdf(release.id);
      downloadBlob(res.data, `${release.slug || 'communique'}.pdf`);
      toast('success', 'PDF exporte.');
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  const handleExportWord = async () => {
    if (!release) return;
    setActionLoading('word');
    try {
      const res = await exportPressReleaseWord(release.id);
      downloadBlob(res.data, `${release.slug || 'communique'}.docx`);
      toast('success', 'Word exporte.');
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  const handleDelete = () => {
    if (!release) return;
    setConfirmAction({
      title: 'Supprimer le communique',
      message: `Voulez-vous vraiment supprimer "${release.title}" ?`,
      variant: 'danger',
      action: async () => {
        try {
          await deletePressRelease(release.id);
          toast('success', 'Communique supprime.');
          navigate('/content/press');
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

  if (error || !release) {
    return (
      <div className="p-4 md:p-6">
        <div className="bg-danger/10 border border-danger/30 rounded-xl p-6 text-center">
          <p className="text-danger">{error ?? 'Communique introuvable'}</p>
          <button onClick={() => navigate('/content/press')} className="mt-4 text-sm text-muted hover:text-white transition-colors">
            Retour a la presse
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Breadcrumb */}
      <div className="flex items-center justify-between">
        <div>
          <button onClick={() => navigate('/content/press')} className="text-xs text-muted hover:text-white transition-colors inline-flex items-center gap-1 mb-2">
            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" /></svg>
            Retour a la presse
          </button>
          <h2 className="font-title text-2xl font-bold text-white">{release.title || 'Sans titre'}</h2>
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
        {/* Main content */}
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

              {/* Excerpt */}
              <div className="bg-surface border border-border rounded-xl p-5">
                <label className="block text-xs text-muted uppercase tracking-wide mb-1">Extrait</label>
                <textarea
                  value={editExcerpt}
                  onChange={e => { setEditExcerpt(e.target.value); markDirty(); }}
                  rows={3}
                  className={inputClass + ' w-full resize-y'}
                  placeholder="Extrait / resume..."
                />
              </div>

              {/* Content */}
              <div className="bg-surface border border-border rounded-xl p-5">
                <div className="flex items-center justify-between mb-2">
                  <label className="text-xs text-muted uppercase tracking-wide">Contenu HTML</label>
                  <button
                    type="button"
                    onClick={() => setEditMode(!editMode)}
                    className="text-xs text-violet hover:text-violet-light transition-colors"
                  >
                    {editMode ? 'Apercu' : 'Editer'}
                  </button>
                </div>
                {editMode ? (
                  <textarea
                    value={editContent}
                    onChange={e => { setEditContent(e.target.value); markDirty(); }}
                    rows={15}
                    className={inputClass + ' w-full font-mono text-xs resize-y'}
                  />
                ) : (
                  <div
                    className="prose prose-invert prose-sm max-w-none p-4 bg-bg rounded-lg border border-border min-h-[200px]"
                    dangerouslySetInnerHTML={{ __html: editContent || '<p class="text-muted">Aucun contenu</p>' }}
                  />
                )}
                <button
                  type="button"
                  onClick={handleSaveContent}
                  disabled={actionLoading === 'save'}
                  className="mt-4 px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors disabled:opacity-50"
                >
                  {actionLoading === 'save' ? 'Sauvegarde...' : 'Sauvegarder'}
                </button>
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

              <div>
                <label className="block text-xs text-muted uppercase tracking-wide mb-1">Planifier (optionnel)</label>
                <input type="datetime-local" value={scheduleDate} onChange={e => setScheduleDate(e.target.value)} className={inputClass + ' w-full'} />
              </div>

              <div className="flex gap-3">
                <button
                  onClick={handlePublish}
                  disabled={actionLoading === 'publish' || !selectedEndpoint}
                  className="px-6 py-2 bg-success hover:bg-success/90 text-white text-sm rounded-lg transition-colors disabled:opacity-50"
                >
                  {actionLoading === 'publish' ? 'Publication...' : 'Publier'}
                </button>
              </div>

              {/* Export buttons */}
              <div className="border-t border-border pt-4 mt-4">
                <h4 className="text-xs text-muted uppercase tracking-wide mb-3">Exporter</h4>
                <div className="flex gap-3">
                  <button
                    onClick={handleExportPdf}
                    disabled={actionLoading === 'pdf'}
                    className="px-4 py-2 bg-surface2 text-white text-sm rounded-lg hover:bg-surface2/80 transition-colors disabled:opacity-50"
                  >
                    {actionLoading === 'pdf' ? 'Export...' : 'Exporter PDF'}
                  </button>
                  <button
                    onClick={handleExportWord}
                    disabled={actionLoading === 'word'}
                    className="px-4 py-2 bg-surface2 text-white text-sm rounded-lg hover:bg-surface2/80 transition-colors disabled:opacity-50"
                  >
                    {actionLoading === 'word' ? 'Export...' : 'Exporter Word'}
                  </button>
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Sidebar */}
        <div className="lg:col-span-3 space-y-4">
          {/* Info card */}
          <div className="bg-surface border border-border rounded-xl p-5 space-y-3">
            <h4 className="font-title font-semibold text-white">Informations</h4>
            <div className="space-y-2 text-sm">
              <div className="flex justify-between">
                <span className="text-muted">Statut</span>
                <span className={`px-2 py-0.5 rounded text-xs ${STATUS_COLORS[release.status]}`}>
                  {STATUS_LABELS[release.status]}
                </span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted">Langue</span>
                <span className="text-white uppercase">{release.language}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted">Cree le</span>
                <span className="text-white text-xs">{formatDate(release.created_at)}</span>
              </div>
              {release.published_at && (
                <div className="flex justify-between">
                  <span className="text-muted">Publie le</span>
                  <span className="text-white text-xs">{formatDate(release.published_at)}</span>
                </div>
              )}
            </div>
          </div>

          {/* SEO score */}
          <div className="bg-surface border border-border rounded-xl p-5">
            <h4 className="font-title font-semibold text-white mb-3">Score SEO</h4>
            <div className="flex items-center gap-3">
              <div className={`text-3xl font-bold ${seoColor(release.seo_score)}`}>{release.seo_score}</div>
              <div className="flex-1">
                <div className="w-full h-2 bg-surface2 rounded-full overflow-hidden">
                  <div className={`h-full rounded-full ${seoBgColor(release.seo_score)}`} style={{ width: `${release.seo_score}%` }} />
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
