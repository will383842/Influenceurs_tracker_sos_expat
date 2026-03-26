import React, { useEffect, useState, useCallback } from 'react';
import {
  fetchPromptTemplates,
  createPromptTemplate,
  updatePromptTemplate,
  deletePromptTemplate,
  testPromptTemplate,
} from '../../api/contentApi';
import type { PromptTemplate } from '../../types/content';
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

const PHASE_COLORS: Record<string, string> = {
  validate: 'bg-muted/20 text-muted',
  research: 'bg-cyan-500/20 text-cyan-400',
  title: 'bg-violet/20 text-violet-light',
  excerpt: 'bg-blue-500/20 text-blue-400',
  content: 'bg-success/20 text-success',
  faq: 'bg-amber/20 text-amber',
  meta: 'bg-pink-500/20 text-pink-400',
  jsonld: 'bg-orange-500/20 text-orange-400',
  internal_links: 'bg-teal-500/20 text-teal-400',
  external_links: 'bg-indigo-500/20 text-indigo-400',
  affiliate_links: 'bg-rose-500/20 text-rose-400',
  images: 'bg-lime-500/20 text-lime-400',
  slugs: 'bg-sky-500/20 text-sky-400',
  quality: 'bg-emerald-500/20 text-emerald-400',
  translations: 'bg-fuchsia-500/20 text-fuchsia-400',
};

interface TemplateForm {
  name: string;
  content_type: string;
  phase: string;
  system_message: string;
  user_message_template: string;
  model: string;
  temperature: number;
  max_tokens: number;
  is_active: boolean;
}

const emptyForm: TemplateForm = {
  name: '',
  content_type: 'article',
  phase: 'content',
  system_message: '',
  user_message_template: '',
  model: 'gpt-4o',
  temperature: 0.7,
  max_tokens: 4096,
  is_active: true,
};

function extractVariables(template: string): string[] {
  const matches = template.match(/\{\{(\w+)\}\}/g) ?? [];
  return [...new Set(matches.map(m => m.replace(/\{\{|\}\}/g, '')))];
}

export default function PromptTemplates() {
  const [templates, setTemplates] = useState<PromptTemplate[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [showCreate, setShowCreate] = useState(false);
  const [form, setForm] = useState<TemplateForm>({ ...emptyForm });
  const [saving, setSaving] = useState(false);

  // Test state
  const [testingId, setTestingId] = useState<number | null>(null);
  const [testVars, setTestVars] = useState<Record<string, string>>({});
  const [testResult, setTestResult] = useState<{ output: string } | null>(null);
  const [testLoading, setTestLoading] = useState(false);
  const [confirmAction, setConfirmAction] = useState<{ title: string; message: string; action: () => void } | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetchPromptTemplates();
      setTemplates((res.data as unknown as PromptTemplate[]) ?? []);
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
      await createPromptTemplate(form);
      toast('success', 'Template cree.');
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
      await updatePromptTemplate(id, form);
      toast('success', 'Template mis a jour.');
      setEditingId(null);
      setForm({ ...emptyForm });
      load();
    } catch (err) { toast('error', errMsg(err)); } finally {
      setSaving(false);
    }
  };

  const handleDelete = (id: number) => {
    setConfirmAction({
      title: 'Supprimer ce template',
      message: 'Cette action est irreversible.',
      action: async () => {
        try {
          await deletePromptTemplate(id);
          toast('success', 'Template supprime.');
          load();
        } catch (err) { toast('error', errMsg(err)); }
      },
    });
  };

  const handleToggleActive = async (t: PromptTemplate) => {
    try {
      await updatePromptTemplate(t.id, { is_active: !t.is_active });
      toast('success', t.is_active ? 'Template desactive.' : 'Template active.');
      load();
    } catch (err) { toast('error', errMsg(err)); }
  };

  const startEdit = (t: PromptTemplate) => {
    setEditingId(t.id);
    setShowCreate(false);
    setTestingId(null);
    setForm({
      name: t.name,
      content_type: t.content_type,
      phase: t.phase,
      system_message: t.system_message,
      user_message_template: t.user_message_template,
      model: t.model,
      temperature: t.temperature,
      max_tokens: t.max_tokens,
      is_active: t.is_active,
    });
  };

  const startTest = (t: PromptTemplate) => {
    if (testingId === t.id) { setTestingId(null); return; }
    setTestingId(t.id);
    setTestResult(null);
    const vars = extractVariables(t.user_message_template);
    const initial: Record<string, string> = {};
    vars.forEach(v => { initial[v] = ''; });
    setTestVars(initial);
  };

  const handleRunTest = async (id: number) => {
    setTestLoading(true);
    setTestResult(null);
    try {
      const res = await testPromptTemplate({ prompt_id: id, variables: testVars });
      setTestResult(res.data as unknown as { output: string });
    } catch (err) { toast('error', errMsg(err)); } finally {
      setTestLoading(false);
    }
  };

  const renderForm = (onSave: () => void, cancelFn: () => void) => (
    <div className="bg-surface2/50 rounded-lg p-4 space-y-3 mt-3">
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
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
        <div>
          <label className="text-xs text-muted mb-1 block">Phase</label>
          <select value={form.phase} onChange={e => setForm(f => ({ ...f, phase: e.target.value }))} className={inputClass + ' w-full'}>
            {['validate', 'research', 'title', 'excerpt', 'content', 'faq', 'meta', 'jsonld', 'internal_links', 'external_links', 'affiliate_links', 'images', 'slugs', 'quality', 'translations'].map(p => (
              <option key={p} value={p}>{p}</option>
            ))}
          </select>
        </div>
      </div>

      <div>
        <label className="text-xs text-muted mb-1 block">System message</label>
        <textarea
          rows={4}
          value={form.system_message}
          onChange={e => setForm(f => ({ ...f, system_message: e.target.value }))}
          className={inputClass + ' w-full font-mono text-xs resize-y'}
        />
      </div>

      <div>
        <label className="text-xs text-muted mb-1 block">
          User message template
          <span className="ml-2 text-[10px] text-violet">{'Variables: {{variable_name}}'}</span>
        </label>
        <textarea
          rows={6}
          value={form.user_message_template}
          onChange={e => setForm(f => ({ ...f, user_message_template: e.target.value }))}
          className={inputClass + ' w-full font-mono text-xs resize-y'}
        />
        {extractVariables(form.user_message_template).length > 0 && (
          <div className="flex gap-1 mt-1 flex-wrap">
            {extractVariables(form.user_message_template).map(v => (
              <span key={v} className="px-1.5 py-0.5 bg-violet/20 text-violet text-[10px] rounded">{`{{${v}}}`}</span>
            ))}
          </div>
        )}
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div>
          <label className="text-xs text-muted mb-1 block">Model</label>
          <select value={form.model} onChange={e => setForm(f => ({ ...f, model: e.target.value }))} className={inputClass + ' w-full'}>
            <option value="gpt-4o">GPT-4o</option>
            <option value="gpt-4o-mini">GPT-4o Mini</option>
            <option value="gpt-4-turbo">GPT-4 Turbo</option>
            <option value="claude-3-opus">Claude 3 Opus</option>
            <option value="claude-3-sonnet">Claude 3 Sonnet</option>
          </select>
        </div>
        <div>
          <label className="text-xs text-muted mb-1 block">Temperature: {form.temperature}</label>
          <input
            type="range"
            min={0}
            max={2}
            step={0.1}
            value={form.temperature}
            onChange={e => setForm(f => ({ ...f, temperature: +e.target.value }))}
            className="w-full accent-violet"
          />
        </div>
        <div>
          <label className="text-xs text-muted mb-1 block">Max tokens</label>
          <input
            type="number"
            value={form.max_tokens}
            onChange={e => setForm(f => ({ ...f, max_tokens: +e.target.value }))}
            className={inputClass + ' w-full'}
          />
        </div>
        <div className="flex items-end">
          <label className="flex items-center gap-2 cursor-pointer">
            <input
              type="checkbox"
              checked={form.is_active}
              onChange={e => setForm(f => ({ ...f, is_active: e.target.checked }))}
              className="accent-violet"
            />
            <span className="text-sm text-white">Actif</span>
          </label>
        </div>
      </div>

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

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div className="flex items-center justify-between gap-3 flex-wrap">
        <h2 className="font-title text-2xl font-bold text-white">Prompt Templates</h2>
        <button
          onClick={() => { setShowCreate(true); setEditingId(null); setTestingId(null); setForm({ ...emptyForm }); }}
          className="px-4 py-1.5 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors"
        >
          + Nouveau
        </button>
      </div>

      {error && <p className="text-danger text-sm">{error}</p>}

      {showCreate && (
        <div className="bg-surface border border-border rounded-xl p-5">
          <h3 className="font-title text-lg font-semibold text-white mb-2">Nouveau template</h3>
          {renderForm(handleCreate, () => setShowCreate(false))}
        </div>
      )}

      <div className="bg-surface border border-border rounded-xl p-5">
        {loading ? (
          <div className="space-y-3">{[1, 2, 3].map(i => <div key={i} className="animate-pulse bg-surface2 rounded-lg h-12" />)}</div>
        ) : templates.length === 0 ? (
          <p className="text-center py-8 text-muted text-sm">Aucun template</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs text-muted uppercase tracking-wide border-b border-border">
                  <th className="pb-3 pr-4">Nom</th>
                  <th className="pb-3 pr-4">Content type</th>
                  <th className="pb-3 pr-4">Phase</th>
                  <th className="pb-3 pr-4">Model</th>
                  <th className="pb-3 pr-4">Actif</th>
                  <th className="pb-3 pr-4">Version</th>
                  <th className="pb-3">Actions</th>
                </tr>
              </thead>
              <tbody>
                {templates.map(t => (
                  <React.Fragment key={t.id}>
                    <tr className="border-b border-border/50 hover:bg-surface2/50 transition-colors">
                      <td className="py-3 pr-4 text-white font-medium">{t.name}</td>
                      <td className="py-3 pr-4">
                        <span className={`inline-block px-1.5 py-0.5 rounded text-[10px] font-medium ${CONTENT_TYPE_COLORS[t.content_type] ?? 'bg-muted/20 text-muted'}`}>
                          {t.content_type}
                        </span>
                      </td>
                      <td className="py-3 pr-4">
                        <span className={`inline-block px-1.5 py-0.5 rounded text-[10px] font-medium ${PHASE_COLORS[t.phase] ?? 'bg-muted/20 text-muted'}`}>
                          {t.phase}
                        </span>
                      </td>
                      <td className="py-3 pr-4 text-muted">{t.model}</td>
                      <td className="py-3 pr-4">
                        <button
                          onClick={() => handleToggleActive(t)}
                          className={`px-2 py-0.5 text-xs rounded-lg transition-colors ${t.is_active ? 'bg-success/20 text-success' : 'bg-muted/20 text-muted'}`}
                        >
                          {t.is_active ? 'Actif' : 'Inactif'}
                        </button>
                      </td>
                      <td className="py-3 pr-4 text-muted">v{t.version}</td>
                      <td className="py-3">
                        <div className="flex items-center gap-2">
                          <button onClick={() => startEdit(t)} className="text-xs text-violet hover:text-violet-light transition-colors">
                            Editer
                          </button>
                          <button onClick={() => startTest(t)} className="text-xs text-blue-400 hover:text-blue-300 transition-colors">
                            Test
                          </button>
                          <button onClick={() => handleDelete(t.id)} className="text-xs text-danger hover:text-red-300 transition-colors">
                            Suppr
                          </button>
                        </div>
                      </td>
                    </tr>

                    {/* Edit form */}
                    {editingId === t.id && (
                      <tr>
                        <td colSpan={7}>
                          {renderForm(() => handleUpdate(t.id), () => { setEditingId(null); setForm({ ...emptyForm }); })}
                        </td>
                      </tr>
                    )}

                    {/* Test panel */}
                    {testingId === t.id && (
                      <tr>
                        <td colSpan={7}>
                          <div className="bg-surface2/50 rounded-lg p-4 space-y-3 mt-1 mb-2">
                            <h4 className="text-sm font-medium text-white">Test: {t.name}</h4>
                            {Object.keys(testVars).length > 0 ? (
                              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                {Object.entries(testVars).map(([key, val]) => (
                                  <div key={key}>
                                    <label className="text-xs text-muted mb-1 block">{`{{${key}}}`}</label>
                                    <input
                                      type="text"
                                      value={val}
                                      onChange={e => setTestVars(v => ({ ...v, [key]: e.target.value }))}
                                      className={inputClass + ' w-full'}
                                    />
                                  </div>
                                ))}
                              </div>
                            ) : (
                              <p className="text-xs text-muted">Aucune variable detectee dans le template</p>
                            )}
                            <button
                              onClick={() => handleRunTest(t.id)}
                              disabled={testLoading}
                              className="px-4 py-2 bg-blue-500 hover:bg-blue-500/90 text-white text-sm rounded-lg transition-colors disabled:opacity-50"
                            >
                              {testLoading ? 'Execution...' : 'Executer le test'}
                            </button>
                            {testResult && (
                              <div className="border border-border rounded-lg p-3 bg-bg">
                                <p className="text-xs text-muted mb-2">Reponse IA :</p>
                                <pre className="text-sm text-white whitespace-pre-wrap font-mono">{testResult.output}</pre>
                              </div>
                            )}
                          </div>
                        </td>
                      </tr>
                    )}
                  </React.Fragment>
                ))}
              </tbody>
            </table>
          </div>
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
