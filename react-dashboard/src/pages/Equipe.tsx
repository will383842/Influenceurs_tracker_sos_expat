import React, { useEffect, useState } from 'react';
import api from '../api/client';
import type { ContactType, TeamMember, ResearcherStat, ObjectiveWithProgress } from '../types/influenceur';
import ContactTypeBadge, { CONTACT_TYPE_OPTIONS } from '../components/ContactTypeBadge';
import { CONTINENTS } from '../data/countries';
import { getLanguageLabel } from '../lib/constants';

type MemberForm = {
  name: string;
  email: string;
  password: string;
  role: 'admin' | 'member' | 'researcher';
  contact_types: ContactType[];
};

// ── Objective helpers ─────────────────────────────────────
interface ObjectiveForm {
  contact_type: string;
  continent: string;
  countries: string[];
  language: string;
  niche: string;
  target_count: number;
  deadline: string;
}

const emptyForm: ObjectiveForm = {
  contact_type: '', continent: '', countries: [], language: '', niche: '', target_count: 10, deadline: '',
};

function getProgressBarColor(obj: ObjectiveWithProgress): string {
  if (obj.days_remaining < 0) return 'bg-gray-500';
  if (obj.percentage >= 80 && obj.days_remaining > 0) return 'bg-green-500';
  if (obj.percentage >= 50 || obj.days_remaining <= 3) return 'bg-amber';
  if (obj.percentage < 50 && obj.days_remaining <= 3) return 'bg-red-500';
  return 'bg-violet';
}

function getProgressTextColor(obj: ObjectiveWithProgress): string {
  if (obj.days_remaining < 0) return 'text-gray-400';
  if (obj.percentage >= 80 && obj.days_remaining > 0) return 'text-green-400';
  if (obj.percentage >= 50 || obj.days_remaining <= 3) return 'text-amber';
  if (obj.percentage < 50 && obj.days_remaining <= 3) return 'text-red-400';
  return 'text-violet-light';
}

function formatDeadline(deadline: string): string {
  return new Date(deadline).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
}

function getTomorrowDate(): string {
  const d = new Date();
  d.setDate(d.getDate() + 1);
  return d.toISOString().split('T')[0];
}

function formatCountries(countries: string[] | null, continent: string | null): string {
  if (!countries || countries.length === 0) return 'Tous pays';
  if (continent && CONTINENTS[continent]) {
    const continentCountries = CONTINENTS[continent].countries.map(c => c.name);
    if (countries.length === continentCountries.length) {
      return `${CONTINENTS[continent].label} (tous)`;
    }
  }
  if (countries.length <= 3) return countries.join(', ');
  return `${countries.slice(0, 2).join(', ')} +${countries.length - 2}`;
}

// ── Tabs ─────────────────────────────────────────────────
type Tab = 'members' | 'researchers';

export default function Equipe() {
  const [activeTab, setActiveTab] = useState<Tab>('members');

  // Team members state
  const [members, setMembers] = useState<TeamMember[]>([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState<MemberForm>({ name: '', email: '', password: '', role: 'member', contact_types: [] });
  const [error, setError] = useState('');

  // Researcher state (from AdminConsole)
  const [researchers, setResearchers] = useState<ResearcherStat[]>([]);
  const [researchersLoading, setResearchersLoading] = useState(true);
  const [editingUserId, setEditingUserId] = useState<number | null>(null);
  const [objForm, setObjForm] = useState<ObjectiveForm>(emptyForm);
  const [saving, setSaving] = useState(false);
  const [showCountryPicker, setShowCountryPicker] = useState(false);
  const [success, setSuccess] = useState('');

  const fetchMembers = async () => {
    const { data } = await api.get<TeamMember[]>('/team');
    setMembers(data);
    setLoading(false);
  };

  const fetchResearchers = async () => {
    try {
      const { data } = await api.get<ResearcherStat[]>('/researchers/stats');
      setResearchers(data);
    } catch { /* ignore */ }
    setResearchersLoading(false);
  };

  useEffect(() => {
    fetchMembers();
    fetchResearchers();
  }, []);

  // ── Team member CRUD ────────────────────────────────────
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    try {
      if (editingId) {
        const payload: Record<string, unknown> = { name: form.name, email: form.email, role: form.role, contact_types: form.contact_types.length > 0 ? form.contact_types : null };
        if (form.password) payload.password = form.password;
        await api.put(`/team/${editingId}`, payload);
      } else {
        await api.post('/team', { ...form, contact_types: form.contact_types.length > 0 ? form.contact_types : null });
      }
      await fetchMembers();
      setShowForm(false);
      setEditingId(null);
      setForm({ name: '', email: '', password: '', role: 'member', contact_types: [] });
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } };
      setError(e.response?.data?.message ?? 'Erreur lors de la sauvegarde.');
    }
  };

  const handleEdit = (member: TeamMember) => {
    setEditingId(member.id);
    setForm({ name: member.name, email: member.email, password: '', role: member.role, contact_types: member.contact_types ?? [] });
    setShowForm(true);
    setActiveTab('members');
  };

  const handleDeactivate = async (id: number) => {
    if (!confirm('Desactiver ce membre ?')) return;
    await api.delete(`/team/${id}`);
    await fetchMembers();
  };

  // ── Objective CRUD ──────────────────────────────────────
  const handleAddObjective = (researcherId: number) => {
    setEditingUserId(researcherId);
    setObjForm({ ...emptyForm, deadline: getTomorrowDate() });
    setSuccess('');
    setShowCountryPicker(false);
  };

  const handleContinentChange = (continentKey: string) => {
    if (continentKey === '') {
      setObjForm(p => ({ ...p, continent: '', countries: [] }));
      setShowCountryPicker(false);
      return;
    }
    const continentData = CONTINENTS[continentKey];
    if (continentData) {
      setObjForm(p => ({
        ...p, continent: continentKey,
        countries: continentData.countries.map(c => c.name),
      }));
      setShowCountryPicker(true);
    }
  };

  const handleToggleCountry = (countryName: string) => {
    setObjForm(p => ({
      ...p,
      countries: p.countries.includes(countryName)
        ? p.countries.filter(c => c !== countryName)
        : [...p.countries, countryName],
    }));
  };

  const handleSelectAll = () => {
    if (!objForm.continent || !CONTINENTS[objForm.continent]) return;
    setObjForm(p => ({ ...p, countries: CONTINENTS[objForm.continent].countries.map(c => c.name) }));
  };

  const handleDeselectAll = () => {
    setObjForm(p => ({ ...p, countries: [] }));
  };

  const handleSubmitObjective = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingUserId) return;
    const deadlineDate = new Date(objForm.deadline);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    if (deadlineDate <= today) {
      setError('La deadline doit etre une date future.');
      return;
    }
    setSaving(true);
    setError('');
    setSuccess('');
    try {
      await api.post('/objectives', {
        user_id: editingUserId,
        contact_type: objForm.contact_type || null,
        target_count: objForm.target_count,
        deadline: objForm.deadline,
        continent: objForm.continent || null,
        countries: objForm.countries.length > 0 ? objForm.countries : null,
        language: objForm.language || null,
        niche: objForm.niche || null,
      });
      setSuccess('Objectif cree avec succes.');
      setEditingUserId(null);
      setShowCountryPicker(false);
      await fetchResearchers();
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } };
      setError(e.response?.data?.message ?? 'Erreur lors de la sauvegarde de l\'objectif.');
    } finally {
      setSaving(false);
    }
  };

  if (loading) return (
    <div className="flex items-center justify-center h-64">
      <div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" />
    </div>
  );

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="font-title text-2xl font-bold text-white">Equipe & Objectifs</h2>
          <p className="text-muted text-sm mt-1">{members.length} membre{members.length !== 1 ? 's' : ''} — {researchers.length} chercheur{researchers.length !== 1 ? 's' : ''}</p>
        </div>
        {activeTab === 'members' && (
          <button
            onClick={() => { setShowForm(!showForm); setEditingId(null); setForm({ name: '', email: '', password: '', role: 'member', contact_types: [] }); }}
            className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors"
          >
            + Ajouter un membre
          </button>
        )}
      </div>

      {/* Tabs */}
      <div className="flex gap-1 bg-surface border border-border rounded-lg p-1">
        {[
          { key: 'members' as Tab, label: 'Membres', count: members.length },
          { key: 'researchers' as Tab, label: 'Chercheurs & Objectifs', count: researchers.length },
        ].map(tab => (
          <button
            key={tab.key}
            onClick={() => setActiveTab(tab.key)}
            className={`flex-1 px-4 py-2 rounded-md text-sm font-medium transition-colors ${
              activeTab === tab.key
                ? 'bg-violet/20 text-violet-light'
                : 'text-muted hover:text-white hover:bg-surface2'
            }`}
          >
            {tab.label} <span className="text-xs text-muted ml-1">({tab.count})</span>
          </button>
        ))}
      </div>

      {error && (
        <div className="bg-red-500/10 border border-red-500/30 text-red-400 text-sm px-4 py-3 rounded-lg">{error}</div>
      )}
      {success && (
        <div className="bg-green-500/10 border border-green-500/30 text-green-400 text-sm px-4 py-3 rounded-lg">{success}</div>
      )}

      {/* ═══════════════════════════════════════════════════════
          TAB 1: MEMBERS
      ═══════════════════════════════════════════════════════ */}
      {activeTab === 'members' && (
        <>
          {/* Member form */}
          {showForm && (
            <form onSubmit={handleSubmit} className="bg-surface border border-border rounded-xl p-5 space-y-4">
              <h3 className="font-title font-semibold text-white">{editingId ? 'Modifier le membre' : 'Nouveau membre'}</h3>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {[
                  { label: 'Nom', field: 'name', type: 'text', placeholder: 'Prenom Nom' },
                  { label: 'Email', field: 'email', type: 'email', placeholder: 'email@sos-expat.com' },
                ].map(({ label, field, type, placeholder }) => (
                  <div key={field}>
                    <label className="block text-sm text-gray-400 mb-1.5">{label}</label>
                    <input
                      type={type}
                      value={(form as Record<string, string>)[field]}
                      onChange={e => setForm(p => ({ ...p, [field]: e.target.value }))}
                      required
                      placeholder={placeholder}
                      className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet"
                    />
                  </div>
                ))}
                <div>
                  <label className="block text-sm text-gray-400 mb-1.5">
                    Mot de passe {editingId && <span className="text-muted">(laisser vide = inchange)</span>}
                  </label>
                  <input
                    type="password"
                    value={form.password}
                    onChange={e => setForm(p => ({ ...p, password: e.target.value }))}
                    required={!editingId}
                    placeholder="••••••••"
                    className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet"
                  />
                </div>
                <div>
                  <label className="block text-sm text-gray-400 mb-1.5">Role</label>
                  <select
                    value={form.role}
                    onChange={e => setForm(p => ({ ...p, role: e.target.value as MemberForm['role'] }))}
                    className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet"
                  >
                    <option value="member">Membre</option>
                    <option value="researcher">Chercheur</option>
                    <option value="admin">Admin</option>
                  </select>
                </div>
              </div>
              {form.role === 'researcher' && (
                <div>
                  <label className="block text-sm text-gray-400 mb-2">Types de contacts assignes</label>
                  <div className="flex flex-wrap gap-2">
                    {CONTACT_TYPE_OPTIONS.map(t => {
                      const isChecked = form.contact_types.includes(t.value);
                      return (
                        <label
                          key={t.value}
                          className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs cursor-pointer border transition-colors ${
                            isChecked
                              ? 'bg-violet/20 text-violet-light border-violet/40'
                              : 'bg-surface2 text-muted border-border hover:text-white'
                          }`}
                        >
                          <input
                            type="checkbox"
                            checked={isChecked}
                            onChange={() => {
                              setForm(p => ({
                                ...p,
                                contact_types: isChecked
                                  ? p.contact_types.filter(ct => ct !== t.value)
                                  : [...p.contact_types, t.value],
                              }));
                            }}
                            className="w-3.5 h-3.5 accent-violet"
                          />
                          {t.label}
                        </label>
                      );
                    })}
                  </div>
                  <p className="text-[10px] text-muted mt-1">Vide = acces a tous les types</p>
                </div>
              )}
              <div className="flex gap-3">
                <button type="submit" className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors">
                  {editingId ? 'Sauvegarder' : 'Creer'}
                </button>
                <button type="button" onClick={() => setShowForm(false)} className="px-4 py-2 text-muted hover:text-white text-sm transition-colors">
                  Annuler
                </button>
              </div>
            </form>
          )}

          {/* Members table */}
          <div className="bg-surface border border-border rounded-xl overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="border-b border-border">
                    {['Membre', 'Email', 'Role', 'Types assignes', 'Statut', 'Derniere connexion', 'Actions'].map(h => (
                      <th key={h} className="text-left text-xs text-muted font-medium px-4 py-3 whitespace-nowrap">{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {members.map(member => (
                    <tr key={member.id} className="border-b border-border last:border-0 hover:bg-surface2 transition-colors">
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-3">
                          <div className="w-8 h-8 rounded-full bg-violet/20 flex items-center justify-center text-violet-light font-bold text-sm flex-shrink-0">
                            {member.name[0]}
                          </div>
                          <span className="text-white text-sm font-medium whitespace-nowrap">{member.name}</span>
                        </div>
                      </td>
                      <td className="px-4 py-3 text-muted text-sm whitespace-nowrap">{member.email}</td>
                      <td className="px-4 py-3">
                        <span className={`px-2 py-0.5 rounded-full text-xs font-mono ${member.role === 'admin' ? 'bg-violet/20 text-violet-light' : 'bg-surface2 text-muted'}`}>
                          {member.role}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        {member.role === 'researcher' && member.contact_types && member.contact_types.length > 0 ? (
                          <div className="flex flex-wrap gap-1">
                            {member.contact_types.map(ct => (
                              <ContactTypeBadge key={ct} type={ct} />
                            ))}
                          </div>
                        ) : (
                          <span className="text-muted text-xs">{member.role === 'researcher' ? 'Tous' : '—'}</span>
                        )}
                      </td>
                      <td className="px-4 py-3">
                        <span className={`px-2 py-0.5 rounded-full text-xs ${member.is_active ? 'bg-green-500/10 text-green-400' : 'bg-red-500/10 text-red-400'}`}>
                          {member.is_active ? 'Actif' : 'Desactive'}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-muted text-sm whitespace-nowrap">
                        {member.last_login_at ? new Date(member.last_login_at).toLocaleDateString('fr-FR') : 'Jamais'}
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex gap-2">
                          <button onClick={() => handleEdit(member)} className="text-xs text-muted hover:text-white transition-colors">
                            Modifier
                          </button>
                          {member.is_active && (
                            <button onClick={() => handleDeactivate(member.id)} className="text-xs text-red-400 hover:text-red-300 transition-colors">
                              Desactiver
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </>
      )}

      {/* ═══════════════════════════════════════════════════════
          TAB 2: RESEARCHERS & OBJECTIVES (from AdminConsole)
      ═══════════════════════════════════════════════════════ */}
      {activeTab === 'researchers' && (
        <>
          {researchersLoading ? (
            <div className="flex items-center justify-center h-32">
              <div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" />
            </div>
          ) : researchers.length === 0 ? (
            <div className="bg-surface border border-border rounded-xl p-8 text-center text-muted text-sm">
              Aucun chercheur enregistre. Ajoutez un membre avec le role "Chercheur" dans l'onglet Membres.
            </div>
          ) : (
            <div className="bg-surface border border-border rounded-xl overflow-hidden divide-y divide-border">
              {researchers.map(r => (
                <div key={r.id} className="p-4 md:p-5">
                  {/* Researcher header */}
                  <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                    <div className="flex items-center gap-3">
                      <div className="w-10 h-10 rounded-full bg-cyan/20 flex items-center justify-center text-cyan font-bold text-sm flex-shrink-0">
                        {r.name?.[0] ?? '?'}
                      </div>
                      <div>
                        <p className="text-white font-medium">{r.name}</p>
                        <p className="text-muted text-xs">{r.email}</p>
                        <p className="text-muted text-xs mt-0.5">
                          Derniere connexion:{' '}
                          {r.last_login_at
                            ? new Date(r.last_login_at).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
                            : 'Jamais'}
                        </p>
                      </div>
                    </div>
                    <div className="flex items-center gap-4">
                      <div className="text-center">
                        <p className="text-xl font-bold text-white font-title">{r.total_created}</p>
                        <p className="text-[10px] text-muted uppercase tracking-wider">Total crees</p>
                      </div>
                      <div className="w-px h-8 bg-border" />
                      <div className="text-center">
                        <p className="text-xl font-bold text-green-400 font-title">{r.valid_count}</p>
                        <p className="text-[10px] text-muted uppercase tracking-wider">Valides</p>
                      </div>
                      <div className="w-px h-8 bg-border" />
                      <div className="text-center">
                        <p className="text-xl font-bold text-muted font-title">
                          {r.total_created > 0 ? Math.round((r.valid_count / r.total_created) * 100) : 0}%
                        </p>
                        <p className="text-[10px] text-muted uppercase tracking-wider">Ratio</p>
                      </div>
                    </div>
                  </div>

                  {/* Active objectives */}
                  {r.objectives.length > 0 ? (
                    <div className="overflow-x-auto mb-3">
                      <table className="w-full text-sm">
                        <thead>
                          <tr className="border-b border-border/50">
                            {['Type', 'Continent/Pays', 'Langue', 'Niche', 'Cible', 'Progression', 'Deadline', 'Jours restants'].map(h => (
                              <th key={h} className="text-left text-[10px] text-muted font-medium uppercase tracking-wider px-3 py-2 whitespace-nowrap">{h}</th>
                            ))}
                          </tr>
                        </thead>
                        <tbody>
                          {r.objectives.map(obj => (
                            <tr key={obj.id} className="border-b border-border/30 last:border-0">
                              <td className="px-3 py-2">
                                {obj.contact_type ? <ContactTypeBadge type={obj.contact_type as ContactType} /> : <span className="text-muted text-xs">Tous</span>}
                              </td>
                              <td className="px-3 py-2 text-gray-300 max-w-[200px]">
                                <span title={obj.countries?.join(', ') ?? 'Tous pays'}>{formatCountries(obj.countries, obj.continent)}</span>
                              </td>
                              <td className="px-3 py-2 text-gray-300 whitespace-nowrap">{obj.language ? getLanguageLabel(obj.language) : 'Toutes'}</td>
                              <td className="px-3 py-2 text-gray-300 whitespace-nowrap">{obj.niche ?? 'Tous types'}</td>
                              <td className="px-3 py-2 text-white font-mono font-bold">{obj.target_count}</td>
                              <td className="px-3 py-2 min-w-[160px]">
                                <div className="flex items-center gap-2">
                                  <div className="flex-1 bg-surface2 rounded-full h-2">
                                    <div className={`h-2 rounded-full transition-all ${getProgressBarColor(obj)}`} style={{ width: `${Math.min(obj.percentage, 100)}%` }} />
                                  </div>
                                  <span className={`text-xs font-mono font-bold whitespace-nowrap ${getProgressTextColor(obj)}`}>
                                    {obj.current_count}/{obj.target_count} ({Math.round(obj.percentage)}%)
                                  </span>
                                </div>
                              </td>
                              <td className="px-3 py-2 text-gray-300 whitespace-nowrap">{formatDeadline(obj.deadline)}</td>
                              <td className="px-3 py-2 whitespace-nowrap">
                                {obj.days_remaining < 0 ? (
                                  <span className="text-gray-500 text-xs font-medium">Expire</span>
                                ) : obj.days_remaining === 0 ? (
                                  <span className="text-red-400 text-xs font-bold">Aujourd'hui</span>
                                ) : (
                                  <span className={`text-xs font-bold ${obj.days_remaining <= 3 ? 'text-amber' : 'text-gray-300'}`}>
                                    {obj.days_remaining}j
                                  </span>
                                )}
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  ) : (
                    <p className="text-xs text-muted mb-3 pl-1">Aucun objectif actif</p>
                  )}

                  {/* Add objective button */}
                  <button
                    onClick={() => handleAddObjective(r.id)}
                    className="text-xs text-violet hover:text-violet-light transition-colors font-medium"
                  >
                    + Ajouter objectif
                  </button>

                  {/* Inline objective form */}
                  {editingUserId === r.id && (
                    <div className="mt-4 bg-surface2/50 rounded-lg p-4 border border-border/50">
                      <form onSubmit={handleSubmitObjective}>
                        <p className="text-xs text-gray-400 mb-3">
                          Nouvel objectif pour <span className="text-white font-medium">{r.name}</span>
                        </p>
                        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-4">
                          <div>
                            <label className="block text-[10px] text-gray-400 uppercase tracking-wider mb-1">Type de contact</label>
                            <select
                              value={objForm.contact_type}
                              onChange={e => setObjForm(p => ({ ...p, contact_type: e.target.value }))}
                              className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet"
                            >
                              <option value="">Tous les types</option>
                              {CONTACT_TYPE_OPTIONS.map(t => (
                                <option key={t.value} value={t.value}>{t.label}</option>
                              ))}
                            </select>
                          </div>
                          <div>
                            <label className="block text-[10px] text-gray-400 uppercase tracking-wider mb-1">Continent</label>
                            <select
                              value={objForm.continent}
                              onChange={e => handleContinentChange(e.target.value)}
                              className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet"
                            >
                              <option value="">Tous (aucun filtre)</option>
                              {Object.entries(CONTINENTS).map(([key, data]) => (
                                <option key={key} value={key}>{data.label} ({data.countries.length} pays)</option>
                              ))}
                            </select>
                          </div>
                          <div>
                            <label className="block text-[10px] text-gray-400 uppercase tracking-wider mb-1">Langue</label>
                            <input
                              type="text" placeholder="ex: fr" value={objForm.language}
                              onChange={e => setObjForm(p => ({ ...p, language: e.target.value }))}
                              className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet placeholder:text-gray-600"
                            />
                          </div>
                          <div>
                            <label className="block text-[10px] text-gray-400 uppercase tracking-wider mb-1">Niche / Type</label>
                            <input
                              type="text" placeholder="ex: Voyage" value={objForm.niche}
                              onChange={e => setObjForm(p => ({ ...p, niche: e.target.value }))}
                              className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet placeholder:text-gray-600"
                            />
                          </div>
                          <div>
                            <label className="block text-[10px] text-gray-400 uppercase tracking-wider mb-1">Quantite *</label>
                            <input
                              type="number" min={1} required value={objForm.target_count}
                              onChange={e => setObjForm(p => ({ ...p, target_count: parseInt(e.target.value) || 1 }))}
                              className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet"
                            />
                          </div>
                          <div>
                            <label className="block text-[10px] text-gray-400 uppercase tracking-wider mb-1">Deadline *</label>
                            <input
                              type="date" required min={getTomorrowDate()} value={objForm.deadline}
                              onChange={e => setObjForm(p => ({ ...p, deadline: e.target.value }))}
                              className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet"
                            />
                          </div>
                        </div>

                        {/* Country picker */}
                        {showCountryPicker && objForm.continent && CONTINENTS[objForm.continent] && (
                          <div className="mb-4">
                            <div className="flex items-center justify-between mb-2">
                              <label className="text-[10px] text-gray-400 uppercase tracking-wider">
                                Pays — {CONTINENTS[objForm.continent].label} ({objForm.countries.length}/{CONTINENTS[objForm.continent].countries.length})
                              </label>
                              <div className="flex gap-2">
                                <button type="button" onClick={handleSelectAll} className="text-[10px] text-cyan hover:text-cyan/80 transition-colors font-medium px-2 py-1 rounded border border-cyan/30">
                                  Tout cocher
                                </button>
                                <button type="button" onClick={handleDeselectAll} className="text-[10px] text-amber hover:text-amber/80 transition-colors font-medium px-2 py-1 rounded border border-amber/30">
                                  Tout decocher
                                </button>
                              </div>
                            </div>
                            <div className="bg-surface2 border border-border rounded-lg p-3 max-h-48 overflow-y-auto">
                              <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-1">
                                {CONTINENTS[objForm.continent].countries.map(country => {
                                  const isChecked = objForm.countries.includes(country.name);
                                  return (
                                    <label
                                      key={country.name}
                                      className={`flex items-center gap-1.5 px-2 py-1.5 rounded-md cursor-pointer text-xs transition-colors ${
                                        isChecked ? 'bg-violet/10 text-white' : 'text-gray-400 hover:bg-surface2 hover:text-gray-300'
                                      }`}
                                    >
                                      <input
                                        type="checkbox" checked={isChecked}
                                        onChange={() => handleToggleCountry(country.name)}
                                        className="w-3.5 h-3.5 rounded border-gray-600 bg-surface2 text-violet focus:ring-0 accent-violet-500"
                                      />
                                      <span>{country.flag}</span>
                                      <span className="truncate">{country.name}</span>
                                    </label>
                                  );
                                })}
                              </div>
                            </div>
                          </div>
                        )}

                        <div className="flex items-center gap-3">
                          <button type="submit" disabled={saving} className="px-5 py-2 bg-violet hover:bg-violet/90 disabled:opacity-50 text-white text-sm rounded-lg transition-colors font-medium">
                            {saving ? 'Enregistrement...' : 'Creer l\'objectif'}
                          </button>
                          <button type="button" onClick={() => { setEditingUserId(null); setShowCountryPicker(false); }} className="px-4 py-2 text-muted hover:text-white text-sm transition-colors">
                            Annuler
                          </button>
                        </div>
                      </form>
                    </div>
                  )}
                </div>
              ))}
            </div>
          )}

          {/* Summary cards (migrated from AdminConsole) */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
            <div className="bg-surface border border-border rounded-xl p-5">
              <h3 className="font-title font-semibold text-white mb-3">Resume global</h3>
              <div className="flex items-center gap-4">
                <div className="w-14 h-14 rounded-xl bg-green-500/10 flex items-center justify-center">
                  <span className="text-2xl">✓</span>
                </div>
                <div>
                  <p className="text-3xl font-bold text-green-400 font-title">
                    {researchers.reduce((sum, r) => sum + r.valid_count, 0)}
                  </p>
                  <p className="text-xs text-muted">Contacts valides dans le systeme</p>
                </div>
              </div>
            </div>
            <div className="bg-surface border border-border rounded-xl p-5">
              <h3 className="font-title font-semibold text-white mb-3">Doublons</h3>
              <div className="flex items-start gap-3 bg-surface2 rounded-lg p-3">
                <div className="w-8 h-8 rounded-lg bg-cyan/10 flex items-center justify-center flex-shrink-0">
                  <span className="text-cyan text-sm">i</span>
                </div>
                <div>
                  <p className="text-sm text-gray-300">Les doublons sont automatiquement bloques a la creation.</p>
                  <p className="text-xs text-muted mt-1">Le systeme verifie l'URL du profil avant d'enregistrer. Si un doublon est detecte, la creation est refusee.</p>
                </div>
              </div>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
