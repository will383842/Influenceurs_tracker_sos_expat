import React, { useEffect, useState } from 'react';
import { useTemplates } from '../hooks/useTemplates';
import { CONTACT_TYPES, LANGUAGES, getContactType } from '../lib/constants';
import type { ContactType, EmailTemplate } from '../types/influenceur';

export default function Outreach() {
  const { templates, loading, load, create, update, remove } = useTemplates();
  const [filterType, setFilterType] = useState<string>('');
  const [filterLang, setFilterLang] = useState<string>('');
  const [editing, setEditing] = useState<EmailTemplate | null>(null);
  const [creating, setCreating] = useState(false);
  const [form, setForm] = useState({ contact_type: 'influenceur', language: 'fr', name: '', subject: '', body: '', step: 1, delay_days: 0 });
  const [copied, setCopied] = useState<number | null>(null);

  useEffect(() => { load(); }, [load]);

  const filtered = templates.filter(t =>
    (!filterType || t.contact_type === filterType) &&
    (!filterLang || t.language === filterLang)
  );

  // Group by contact_type
  const grouped = filtered.reduce<Record<string, EmailTemplate[]>>((acc, t) => {
    const key = typeof t.contact_type === 'string' ? t.contact_type : t.contact_type;
    if (!acc[key]) acc[key] = [];
    acc[key].push(t);
    return acc;
  }, {});

  const handleSave = async () => {
    if (editing) {
      await update(editing.id, form);
      setEditing(null);
    } else {
      await create(form);
      setCreating(false);
    }
    setForm({ contact_type: 'influenceur', language: 'fr', name: '', subject: '', body: '', step: 1, delay_days: 0 });
  };

  const copyToClipboard = async (template: EmailTemplate) => {
    const text = `Objet: ${template.subject}\n\n${template.body}`;
    await navigator.clipboard.writeText(text);
    setCopied(template.id);
    setTimeout(() => setCopied(null), 2000);
  };

  const startEdit = (t: EmailTemplate) => {
    setEditing(t);
    setCreating(false);
    setForm({
      contact_type: typeof t.contact_type === 'string' ? t.contact_type : t.contact_type,
      language: t.language,
      name: t.name,
      subject: t.subject,
      body: t.body,
      step: t.step,
      delay_days: t.delay_days,
    });
  };

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="font-title text-2xl font-bold text-white">✉️ Templates & Outreach</h2>
          <p className="text-muted text-sm mt-1">{templates.length} templates • {Object.keys(grouped).length} types couverts</p>
        </div>
        <button onClick={() => { setCreating(true); setEditing(null); setForm({ contact_type: 'influenceur', language: 'fr', name: '', subject: '', body: '', step: 1, delay_days: 0 }); }}
          className="bg-violet hover:bg-violet/80 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
          + Nouveau Template
        </button>
      </div>

      {/* Filters */}
      <div className="flex gap-3 flex-wrap">
        <select value={filterType} onChange={e => setFilterType(e.target.value)}
          className="bg-surface border border-border rounded-lg px-3 py-1.5 text-sm text-white focus:border-violet outline-none">
          <option value="">Tous les types</option>
          {CONTACT_TYPES.map(t => <option key={t.value} value={t.value}>{t.icon} {t.label}</option>)}
        </select>
        <select value={filterLang} onChange={e => setFilterLang(e.target.value)}
          className="bg-surface border border-border rounded-lg px-3 py-1.5 text-sm text-white focus:border-violet outline-none">
          <option value="">Toutes langues</option>
          {LANGUAGES.map(l => <option key={l.code} value={l.code}>{l.flag} {l.label}</option>)}
        </select>
      </div>

      {/* Editor modal */}
      {(creating || editing) && (
        <div className="bg-surface border border-violet/30 rounded-xl p-5 space-y-4">
          <h3 className="font-title font-semibold text-white">{editing ? 'Modifier le template' : 'Nouveau template'}</h3>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
            <select value={form.contact_type} onChange={e => setForm({ ...form, contact_type: e.target.value })}
              className="bg-bg border border-border rounded-lg px-3 py-2 text-sm text-white outline-none">
              {CONTACT_TYPES.map(t => <option key={t.value} value={t.value}>{t.icon} {t.label}</option>)}
            </select>
            <select value={form.language} onChange={e => setForm({ ...form, language: e.target.value })}
              className="bg-bg border border-border rounded-lg px-3 py-2 text-sm text-white outline-none">
              {LANGUAGES.map(l => <option key={l.code} value={l.code}>{l.flag} {l.label}</option>)}
            </select>
            <input value={form.step} onChange={e => setForm({ ...form, step: Number(e.target.value) || 1 })} type="number" min={1} max={10}
              className="bg-bg border border-border rounded-lg px-3 py-2 text-sm text-white outline-none" placeholder="Étape" />
            <input value={form.delay_days} onChange={e => setForm({ ...form, delay_days: Number(e.target.value) || 0 })} type="number" min={0}
              className="bg-bg border border-border rounded-lg px-3 py-2 text-sm text-white outline-none" placeholder="Délai (jours)" />
          </div>
          <input value={form.name} onChange={e => setForm({ ...form, name: e.target.value })}
            className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-sm text-white outline-none" placeholder="Nom du template" />
          <input value={form.subject} onChange={e => setForm({ ...form, subject: e.target.value })}
            className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-sm text-white outline-none" placeholder="Objet du mail" />
          <textarea value={form.body} onChange={e => setForm({ ...form, body: e.target.value })} rows={8}
            className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-sm text-white outline-none resize-y font-mono"
            placeholder="Corps du message. Utilisez {{contactName}}, {{contactCountry}}, etc." />
          <p className="text-[11px] text-muted">Variables : {'{{contactName}}'} {'{{contactCompany}}'} {'{{contactCountry}}'} {'{{contactEmail}}'} {'{{contactUrl}}'} {'{{yourName}}'} {'{{yourCompany}}'}</p>
          <div className="flex gap-2">
            <button onClick={handleSave} className="bg-violet hover:bg-violet/80 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
              {editing ? 'Sauvegarder' : 'Créer'}
            </button>
            <button onClick={() => { setEditing(null); setCreating(false); }} className="text-muted hover:text-white text-sm px-4 py-2 transition-colors">
              Annuler
            </button>
          </div>
        </div>
      )}

      {/* Templates list grouped by type */}
      {loading ? (
        <div className="flex items-center justify-center h-32">
          <div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" />
        </div>
      ) : (
        <div className="space-y-6">
          {Object.entries(grouped).sort().map(([type, tpls]) => {
            const config = getContactType(type as ContactType);
            return (
              <div key={type}>
                <h3 className="text-sm font-semibold text-white flex items-center gap-2 mb-3">
                  <span>{config.icon}</span> {config.label}
                  <span className="text-xs text-muted font-normal">({tpls.length} templates)</span>
                </h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  {tpls.sort((a, b) => a.step - b.step).map(t => (
                    <div key={t.id} className="bg-surface border border-border rounded-xl p-4 hover:border-violet/20 transition-colors">
                      <div className="flex items-start justify-between mb-2">
                        <div>
                          <div className="flex items-center gap-2">
                            <span className="text-xs bg-violet/20 text-violet-light px-1.5 py-0.5 rounded">Étape {t.step}</span>
                            <span className="text-xs text-muted">{t.language.toUpperCase()}</span>
                            {t.delay_days > 0 && <span className="text-xs text-muted">J+{t.delay_days}</span>}
                          </div>
                          <p className="text-sm font-medium text-white mt-1">{t.name}</p>
                        </div>
                        <div className="flex gap-1">
                          <button onClick={() => copyToClipboard(t)}
                            className="text-xs text-muted hover:text-cyan px-2 py-1 rounded transition-colors">
                            {copied === t.id ? '✅' : '📋'}
                          </button>
                          <button onClick={() => startEdit(t)}
                            className="text-xs text-muted hover:text-white px-2 py-1 rounded transition-colors">
                            ✏️
                          </button>
                          <button onClick={() => remove(t.id)}
                            className="text-xs text-muted hover:text-red-400 px-2 py-1 rounded transition-colors">
                            🗑
                          </button>
                        </div>
                      </div>
                      <p className="text-xs text-cyan font-medium">{t.subject}</p>
                      <p className="text-[11px] text-muted mt-1 line-clamp-3 whitespace-pre-line">{t.body}</p>
                    </div>
                  ))}
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
