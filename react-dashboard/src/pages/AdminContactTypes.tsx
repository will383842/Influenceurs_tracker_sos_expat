import React, { useEffect, useState } from 'react';
import api from '../api/client';

interface ContactTypeItem {
  id: number;
  value: string;
  label: string;
  icon: string;
  color: string;
  is_active: boolean;
  sort_order: number;
}

const EMPTY: Omit<ContactTypeItem, 'id'> = {
  value: '', label: '', icon: '📌', color: '#6B7280', is_active: true, sort_order: 0,
};

export default function AdminContactTypes() {
  const [types, setTypes] = useState<ContactTypeItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [editing, setEditing] = useState<ContactTypeItem | null>(null);
  const [creating, setCreating] = useState(false);
  const [form, setForm] = useState(EMPTY);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  const load = async () => {
    setLoading(true);
    try {
      const { data } = await api.get('/contact-types', { params: { all: 1 } });
      setTypes(data);
    } catch { setError('Erreur chargement'); }
    finally { setLoading(false); }
  };

  useEffect(() => { load(); }, []);

  const clearMessages = () => { setError(''); setSuccess(''); };

  const handleSave = async () => {
    clearMessages();
    try {
      if (editing) {
        const { data } = await api.put(`/contact-types/${editing.id}`, {
          label: form.label, icon: form.icon, color: form.color,
          is_active: form.is_active, sort_order: form.sort_order,
        });
        setTypes(prev => prev.map(t => t.id === editing.id ? data : t));
        setSuccess(`"${data.label}" modifié`);
      } else {
        const { data } = await api.post('/contact-types', form);
        setTypes(prev => [...prev, data]);
        setSuccess(`"${data.label}" créé`);
      }
      setEditing(null);
      setCreating(false);
      setForm(EMPTY);
    } catch (err: any) {
      setError(err.response?.data?.message || err.response?.data?.errors?.value?.[0] || 'Erreur');
    }
  };

  const handleDelete = async (type: ContactTypeItem) => {
    clearMessages();
    if (!confirm(`Supprimer "${type.label}" ? (impossible si des contacts l'utilisent)`)) return;
    try {
      await api.delete(`/contact-types/${type.id}`);
      setTypes(prev => prev.filter(t => t.id !== type.id));
      setSuccess(`"${type.label}" supprimé`);
    } catch (err: any) {
      setError(err.response?.data?.message || 'Erreur suppression');
    }
  };

  const startEdit = (type: ContactTypeItem) => {
    setEditing(type);
    setCreating(false);
    setForm(type);
    clearMessages();
  };

  const startCreate = () => {
    setCreating(true);
    setEditing(null);
    setForm({ ...EMPTY, sort_order: types.length + 1 });
    clearMessages();
  };

  if (loading) return (
    <div className="flex items-center justify-center h-32">
      <div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" />
    </div>
  );

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h3 className="font-title text-lg font-bold text-white">Types de Contacts</h3>
          <p className="text-xs text-muted mt-0.5">{types.length} types • Ajouter ou modifier sans coder</p>
        </div>
        <button onClick={startCreate}
          className="bg-violet hover:bg-violet/80 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
          + Nouveau Type
        </button>
      </div>

      {/* Messages */}
      {error && <div className="bg-red-500/10 border border-red-500/20 rounded-lg p-3 text-red-400 text-sm">{error}</div>}
      {success && <div className="bg-emerald-500/10 border border-emerald-500/20 rounded-lg p-3 text-emerald-400 text-sm">{success}</div>}

      {/* Create/Edit form */}
      {(creating || editing) && (
        <div className="bg-surface border border-violet/30 rounded-xl p-5 space-y-4">
          <h4 className="font-title font-semibold text-white">{editing ? `Modifier "${editing.label}"` : 'Nouveau type de contact'}</h4>
          <div className="grid grid-cols-2 md:grid-cols-6 gap-3">
            {/* Slug (only on create) */}
            {!editing && (
              <div className="col-span-2">
                <label className="text-[10px] text-muted block mb-1">Slug (unique, minuscules)</label>
                <input value={form.value} onChange={e => setForm({ ...form, value: e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '_') })}
                  className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-sm text-white outline-none focus:border-violet font-mono"
                  placeholder="erasmus_plus" />
              </div>
            )}
            {/* Label */}
            <div className="col-span-2">
              <label className="text-[10px] text-muted block mb-1">Label (affiché)</label>
              <input value={form.label} onChange={e => setForm({ ...form, label: e.target.value })}
                className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-sm text-white outline-none focus:border-violet"
                placeholder="Écoles Erasmus+" />
            </div>
            {/* Icon */}
            <div>
              <label className="text-[10px] text-muted block mb-1">Emoji</label>
              <input value={form.icon} onChange={e => setForm({ ...form, icon: e.target.value })}
                className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-lg text-center outline-none focus:border-violet"
                placeholder="🎓" />
            </div>
            {/* Color */}
            <div>
              <label className="text-[10px] text-muted block mb-1">Couleur</label>
              <div className="flex items-center gap-2">
                <input type="color" value={form.color} onChange={e => setForm({ ...form, color: e.target.value })}
                  className="w-8 h-8 rounded border-0 cursor-pointer bg-transparent" />
                <input value={form.color} onChange={e => setForm({ ...form, color: e.target.value })}
                  className="flex-1 bg-bg border border-border rounded-lg px-2 py-2 text-xs text-white outline-none font-mono"
                  placeholder="#2563EB" />
              </div>
            </div>
            {/* Sort order */}
            <div>
              <label className="text-[10px] text-muted block mb-1">Ordre</label>
              <input type="number" value={form.sort_order} onChange={e => setForm({ ...form, sort_order: Number(e.target.value) || 0 })}
                className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-sm text-white outline-none focus:border-violet" />
            </div>
            {/* Active toggle */}
            {editing && (
              <div className="flex items-end">
                <label className="flex items-center gap-2 cursor-pointer">
                  <input type="checkbox" checked={form.is_active} onChange={e => setForm({ ...form, is_active: e.target.checked })}
                    className="rounded" />
                  <span className="text-sm text-muted">Actif</span>
                </label>
              </div>
            )}
          </div>

          {/* Preview */}
          <div className="flex items-center gap-3">
            <span className="text-xs text-muted">Aperçu :</span>
            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium"
              style={{ backgroundColor: form.color + '30', color: form.color }}>
              {form.icon} {form.label || 'Nouveau type'}
            </span>
          </div>

          <div className="flex gap-2">
            <button onClick={handleSave} disabled={!form.label || (!editing && !form.value)}
              className="bg-violet hover:bg-violet/80 disabled:opacity-50 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
              {editing ? 'Sauvegarder' : 'Créer'}
            </button>
            <button onClick={() => { setEditing(null); setCreating(false); setForm(EMPTY); }}
              className="text-muted hover:text-white text-sm px-4 py-2 transition-colors">
              Annuler
            </button>
          </div>
        </div>
      )}

      {/* Types table */}
      <div className="bg-surface border border-border rounded-xl overflow-hidden">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-border text-left">
              <th className="p-3 text-xs font-semibold text-muted w-12">#</th>
              <th className="p-3 text-xs font-semibold text-muted">Type</th>
              <th className="p-3 text-xs font-semibold text-muted">Slug</th>
              <th className="p-3 text-xs font-semibold text-muted">Couleur</th>
              <th className="p-3 text-xs font-semibold text-muted text-center">Statut</th>
              <th className="p-3 text-xs font-semibold text-muted text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            {types.sort((a, b) => a.sort_order - b.sort_order).map(type => (
              <tr key={type.id} className={`border-b border-border/50 ${!type.is_active ? 'opacity-40' : ''}`}>
                <td className="p-3 text-muted text-xs">{type.sort_order}</td>
                <td className="p-3">
                  <span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium"
                    style={{ backgroundColor: type.color + '25', color: type.color }}>
                    {type.icon} {type.label}
                  </span>
                </td>
                <td className="p-3 text-xs text-muted font-mono">{type.value}</td>
                <td className="p-3">
                  <div className="flex items-center gap-2">
                    <div className="w-4 h-4 rounded" style={{ backgroundColor: type.color }} />
                    <span className="text-xs text-muted font-mono">{type.color}</span>
                  </div>
                </td>
                <td className="p-3 text-center">
                  <span className={`text-[10px] px-1.5 py-0.5 rounded-full font-medium ${type.is_active ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'}`}>
                    {type.is_active ? 'Actif' : 'Inactif'}
                  </span>
                </td>
                <td className="p-3 text-right">
                  <button onClick={() => startEdit(type)} className="text-xs text-muted hover:text-white px-2 py-1 rounded transition-colors">✏️</button>
                  <button onClick={() => handleDelete(type)} className="text-xs text-muted hover:text-red-400 px-2 py-1 rounded transition-colors">🗑</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
