import React, { useEffect, useState, useCallback } from 'react';
import {
  fetchPresets,
  createPreset,
  updatePreset,
  deletePreset,
} from '../../api/contentApi';
import type { GenerationPreset } from '../../types/content';
import { toast } from '../../components/Toast';
import { ConfirmModal } from '../../components/ConfirmModal';
import { errMsg } from './helpers';

const inputClass = 'bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors';

const CONTENT_TYPE_COLORS: Record<string, string> = {
  article: 'bg-violet/20 text-violet-light',
  guide: 'bg-blue-500/20 text-blue-400',
  news: 'bg-amber/20 text-amber',
  tutorial: 'bg-success/20 text-success',
};

interface PresetForm {
  name: string;
  description: string;
  content_type: string;
  is_default: boolean;
  config: {
    model: string;
    tone: string;
    length: string;
    faq_count: number;
    image_source: string;
    research_sources: boolean;
    auto_internal_links: boolean;
    auto_affiliate_links: boolean;
  };
}

const emptyForm: PresetForm = {
  name: '',
  description: '',
  content_type: 'article',
  is_default: false,
  config: {
    model: 'gpt-4o',
    tone: 'professional',
    length: 'medium',
    faq_count: 5,
    image_source: 'unsplash',
    research_sources: true,
    auto_internal_links: true,
    auto_affiliate_links: false,
  },
};

export default function GenerationPresets() {
  const [presets, setPresets] = useState<GenerationPreset[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [showCreate, setShowCreate] = useState(false);
  const [form, setForm] = useState<PresetForm>({ ...emptyForm });
  const [saving, setSaving] = useState(false);
  const [confirmAction, setConfirmAction] = useState<{ title: string; message: string; action: () => void } | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetchPresets();
      setPresets((res.data as unknown as GenerationPreset[]) ?? []);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Erreur de chargement');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const handleCreate = async () => {
    setSaving(true);
    try {
      await createPreset({ name: form.name, description: form.description, content_type: form.content_type, is_default: form.is_default, config: form.config });
      toast('success', 'Preset cree.');
      setShowCreate(false);
      setForm({ ...emptyForm });
      load();
    } catch (err) { toast('error', errMsg(err)); } finally {
      setSaving(false);
    }
  };

  const handleUpdate = async (id: number) => {
    setSaving(true);
    try {
      await updatePreset(id, { name: form.name, description: form.description, content_type: form.content_type, is_default: form.is_default, config: form.config });
      toast('success', 'Preset mis a jour.');
      setEditingId(null);
      setForm({ ...emptyForm });
      load();
    } catch (err) { toast('error', errMsg(err)); } finally {
      setSaving(false);
    }
  };

  const handleDelete = (id: number) => {
    setConfirmAction({
      title: 'Supprimer ce preset',
      message: 'Cette action est irreversible.',
      action: async () => {
        try {
          await deletePreset(id);
          toast('success', 'Preset supprime.');
          load();
        } catch (err) { toast('error', errMsg(err)); }
      },
    });
  };

  const startEdit = (p: GenerationPreset) => {
    setEditingId(p.id);
    setShowCreate(false);
    const cfg = p.config as PresetForm['config'];
    setForm({
      name: p.name,
      description: p.description ?? '',
      content_type: p.content_type,
      is_default: p.is_default,
      config: {
        model: cfg?.model ?? 'gpt-4o',
        tone: cfg?.tone ?? 'professional',
        length: cfg?.length ?? 'medium',
        faq_count: cfg?.faq_count ?? 5,
        image_source: cfg?.image_source ?? 'unsplash',
        research_sources: cfg?.research_sources ?? true,
        auto_internal_links: cfg?.auto_internal_links ?? true,
        auto_affiliate_links: cfg?.auto_affiliate_links ?? false,
      },
    });
  };

  const renderForm = (onSave: () => void, cancelFn: () => void) => (
    <div className="bg-surface border border-border rounded-xl p-5 space-y-4">
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label className="text-xs text-muted mb-1 block">Nom</label>
          <input type="text" value={form.name} onChange={e => setForm(f => ({ ...f, name: e.target.value }))} className={inputClass + ' w-full'} />
        </div>
        <div>
          <label className="text-xs text-muted mb-1 block">Content type</label>
          <select value={form.content_type} onChange={e => setForm(f => ({ ...f, content_type: e.target.value }))} className={inputClass + ' w-full'}>
            <option value="article">Article</option>
            <option value="guide">Guide</option>
            <option value="news">News</option>
            <option value="tutorial">Tutorial</option>
          </select>
        </div>
      </div>

      <div>
        <label className="text-xs text-muted mb-1 block">Description</label>
        <textarea
          rows={2}
          value={form.description}
          onChange={e => setForm(f => ({ ...f, description: e.target.value }))}
          className={inputClass + ' w-full resize-none'}
        />
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div>
          <label className="text-xs text-muted mb-1 block">Modele</label>
          <select value={form.config.model} onChange={e => setForm(f => ({ ...f, config: { ...f.config, model: e.target.value } }))} className={inputClass + ' w-full'}>
            <option value="gpt-4o">GPT-4o</option>
            <option value="gpt-4o-mini">GPT-4o Mini</option>
            <option value="gpt-4-turbo">GPT-4 Turbo</option>
            <option value="claude-3-opus">Claude 3 Opus</option>
            <option value="claude-3-sonnet">Claude 3 Sonnet</option>
          </select>
        </div>
        <div>
          <label className="text-xs text-muted mb-1 block">Ton</label>
          <select value={form.config.tone} onChange={e => setForm(f => ({ ...f, config: { ...f.config, tone: e.target.value } }))} className={inputClass + ' w-full'}>
            <option value="professional">Professionnel</option>
            <option value="casual">Casual</option>
            <option value="expert">Expert</option>
            <option value="friendly">Amical</option>
          </select>
        </div>
        <div>
          <label className="text-xs text-muted mb-1 block">Longueur</label>
          <select value={form.config.length} onChange={e => setForm(f => ({ ...f, config: { ...f.config, length: e.target.value } }))} className={inputClass + ' w-full'}>
            <option value="short">Court</option>
            <option value="medium">Moyen</option>
            <option value="long">Long</option>
          </select>
        </div>
        <div>
          <label className="text-xs text-muted mb-1 block">FAQ count</label>
          <input
            type="number"
            min={0}
            max={20}
            value={form.config.faq_count}
            onChange={e => setForm(f => ({ ...f, config: { ...f.config, faq_count: +e.target.value } }))}
            className={inputClass + ' w-full'}
          />
        </div>
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div>
          <label className="text-xs text-muted mb-1 block">Source images</label>
          <select value={form.config.image_source} onChange={e => setForm(f => ({ ...f, config: { ...f.config, image_source: e.target.value } }))} className={inputClass + ' w-full'}>
            <option value="unsplash">Unsplash</option>
            <option value="dalle">DALL-E</option>
            <option value="none">Aucune</option>
          </select>
        </div>
        <label className="flex items-center gap-2 cursor-pointer pt-5">
          <input type="checkbox" checked={form.config.research_sources} onChange={e => setForm(f => ({ ...f, config: { ...f.config, research_sources: e.target.checked } }))} className="accent-violet" />
          <span className="text-sm text-white">Recherche</span>
        </label>
        <label className="flex items-center gap-2 cursor-pointer pt-5">
          <input type="checkbox" checked={form.config.auto_internal_links} onChange={e => setForm(f => ({ ...f, config: { ...f.config, auto_internal_links: e.target.checked } }))} className="accent-violet" />
          <span className="text-sm text-white">Liens internes</span>
        </label>
        <label className="flex items-center gap-2 cursor-pointer pt-5">
          <input type="checkbox" checked={form.config.auto_affiliate_links} onChange={e => setForm(f => ({ ...f, config: { ...f.config, auto_affiliate_links: e.target.checked } }))} className="accent-violet" />
          <span className="text-sm text-white">Liens affilies</span>
        </label>
      </div>

      <label className="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" checked={form.is_default} onChange={e => setForm(f => ({ ...f, is_default: e.target.checked }))} className="accent-violet" />
        <span className="text-sm text-white">Preset par defaut</span>
      </label>

      <div className="flex gap-3 pt-2">
        <button
          onClick={onSave}
          disabled={saving || !form.name.trim()}
          className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors disabled:opacity-50"
        >
          {saving ? 'Enregistrement...' : 'Enregistrer'}
        </button>
        <button onClick={cancelFn} className="px-4 py-2 text-sm text-muted hover:text-white transition-colors">
          Annuler
        </button>
      </div>
    </div>
  );

  if (loading) {
    return (
      <div className="p-4 md:p-6 space-y-6">
        <h2 className="font-title text-2xl font-bold text-white">Presets de generation</h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {[1, 2, 3].map(i => <div key={i} className="animate-pulse bg-surface border border-border rounded-xl h-40" />)}
        </div>
      </div>
    );
  }

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div className="flex items-center justify-between gap-3 flex-wrap">
        <h2 className="font-title text-2xl font-bold text-white">Presets de generation</h2>
        <button
          onClick={() => { setShowCreate(true); setEditingId(null); setForm({ ...emptyForm }); }}
          className="px-4 py-1.5 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors"
        >
          + Nouveau
        </button>
      </div>

      {error && <p className="text-danger text-sm">{error}</p>}

      {showCreate && renderForm(handleCreate, () => setShowCreate(false))}

      {editingId !== null && renderForm(() => handleUpdate(editingId), () => { setEditingId(null); setForm({ ...emptyForm }); })}

      {/* Preset cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {presets.map(p => {
          const cfg = p.config as PresetForm['config'];
          return (
            <div key={p.id} className="bg-surface border border-border rounded-xl p-5 space-y-3">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <h4 className="text-white font-medium">{p.name}</h4>
                  {p.is_default && (
                    <span className="text-amber text-sm" title="Preset par defaut">{'\u2B50'}</span>
                  )}
                </div>
                <span className={`inline-block px-1.5 py-0.5 rounded text-[10px] font-medium ${CONTENT_TYPE_COLORS[p.content_type] ?? 'bg-muted/20 text-muted'}`}>
                  {p.content_type}
                </span>
              </div>

              {p.description && (
                <p className="text-sm text-muted line-clamp-2">{p.description}</p>
              )}

              <div className="flex flex-wrap gap-1.5">
                {cfg?.model && <span className="px-1.5 py-0.5 bg-surface2 text-muted rounded text-[10px]">{cfg.model}</span>}
                {cfg?.tone && <span className="px-1.5 py-0.5 bg-surface2 text-muted rounded text-[10px]">{cfg.tone}</span>}
                {cfg?.length && <span className="px-1.5 py-0.5 bg-surface2 text-muted rounded text-[10px]">{cfg.length}</span>}
                {cfg?.faq_count != null && <span className="px-1.5 py-0.5 bg-surface2 text-muted rounded text-[10px]">{cfg.faq_count} FAQ</span>}
                {cfg?.image_source && <span className="px-1.5 py-0.5 bg-surface2 text-muted rounded text-[10px]">{cfg.image_source}</span>}
              </div>

              <div className="flex items-center gap-3 pt-1 border-t border-border">
                <button onClick={() => startEdit(p)} className="text-xs text-violet hover:text-violet-light transition-colors">
                  Editer
                </button>
                <button onClick={() => handleDelete(p.id)} className="text-xs text-danger hover:text-red-300 transition-colors">
                  Suppr
                </button>
              </div>
            </div>
          );
        })}

        {presets.length === 0 && !showCreate && (
          <p className="text-muted text-sm col-span-3">Aucun preset. Creez-en un pour commencer.</p>
        )}
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
