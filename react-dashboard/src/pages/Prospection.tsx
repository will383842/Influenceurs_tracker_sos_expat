import React, { useEffect, useState } from 'react';
import api from '../api/client';
import { CONTACT_TYPES } from '../lib/constants';

interface OutreachEmailRow {
  id: number; step: number; subject: string; body_text: string; body_html: string;
  from_email: string; status: string; ai_generated: boolean; created_at: string;
  influenceur: { id: number; name: string; email: string; contact_type: string; country: string } | null;
}

interface OutreachStats {
  global: { total: number; pending_review: number; approved: number; sent: number; opened: number; clicked: number; replied: number; bounced: number; unsubscribed: number };
  by_step: { step: number; total: number; sent: number }[];
  by_type: { contact_type: string; total: number; sent: number }[];
  warmup: { from_email: string; domain: string; day_count: number; emails_sent_today: number; current_daily_limit: number }[];
}

interface Config {
  id: number; contact_type: string; auto_send: boolean; ai_generation_enabled: boolean;
  max_steps: number; step_delays: number[]; daily_limit: number; is_active: boolean;
}

type Tab = 'dashboard' | 'generate' | 'review' | 'config';

export default function Prospection() {
  const [tab, setTab] = useState<Tab>('dashboard');
  const [stats, setStats] = useState<OutreachStats | null>(null);
  const [reviewQueue, setReviewQueue] = useState<OutreachEmailRow[]>([]);
  const [configs, setConfigs] = useState<Config[]>([]);
  const [loading, setLoading] = useState(true);
  const [generating, setGenerating] = useState(false);
  const [genType, setGenType] = useState('');
  const [genCountry, setGenCountry] = useState('');
  const [genStep, setGenStep] = useState(1);
  const [genLimit, setGenLimit] = useState(20);
  const [genResult, setGenResult] = useState<string | null>(null);
  const [previewEmail, setPreviewEmail] = useState<OutreachEmailRow | null>(null);
  const [actionLoading, setActionLoading] = useState<number | null>(null);

  const fetchAll = async () => {
    setLoading(true);
    try {
      const [statsRes, reviewRes, configRes] = await Promise.all([
        api.get('/outreach/stats'),
        api.get('/outreach/review-queue'),
        api.get('/outreach/config'),
      ]);
      setStats(statsRes.data);
      setReviewQueue(reviewRes.data.data || []);
      setConfigs(configRes.data || []);
    } catch { /* ignore */ }
    setLoading(false);
  };

  useEffect(() => { fetchAll(); }, []);

  const handleGenerate = async () => {
    if (!genType) return;
    setGenerating(true);
    setGenResult(null);
    try {
      const { data } = await api.post('/outreach/generate', {
        contact_type: genType, country: genCountry || undefined, step: genStep, limit: genLimit,
      });
      setGenResult(data.message);
      setTimeout(fetchAll, 3000);
    } catch (err: any) {
      setGenResult(err.response?.data?.message || 'Erreur');
    }
    setGenerating(false);
  };

  const handleApprove = async (id: number) => {
    setActionLoading(id);
    try {
      await api.post(`/outreach/review/${id}/approve`);
      setReviewQueue(prev => prev.filter(e => e.id !== id));
    } catch { /* ignore */ }
    setActionLoading(null);
  };

  const handleReject = async (id: number) => {
    setActionLoading(id);
    try {
      await api.post(`/outreach/review/${id}/reject`);
      setReviewQueue(prev => prev.filter(e => e.id !== id));
    } catch { /* ignore */ }
    setActionLoading(null);
  };

  const handleApproveAll = async () => {
    const ids = reviewQueue.map(e => e.id);
    if (ids.length === 0) return;
    try {
      await api.post('/outreach/review/approve-batch', { ids });
      setReviewQueue([]);
      fetchAll();
    } catch { /* ignore */ }
  };

  const handleToggleConfig = async (contactType: string, field: string, value: boolean) => {
    try {
      await api.put(`/outreach/config/${contactType}`, { [field]: value });
      setConfigs(prev => prev.map(c => c.contact_type === contactType ? { ...c, [field]: value } : c));
    } catch { /* ignore */ }
  };

  if (loading) return <div className="flex items-center justify-center h-64"><div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" /></div>;

  const tabClass = (t: Tab) => `px-4 py-2 text-sm font-medium rounded-lg transition-colors ${tab === t ? 'bg-violet text-white' : 'text-muted hover:text-white hover:bg-surface2'}`;
  const activeTypes = CONTACT_TYPES.filter(t => configs.some(c => c.contact_type === t.value) || t.value);

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div>
        <h1 className="text-2xl font-title font-bold text-white">Prospection Email</h1>
        <p className="text-muted text-sm mt-1">Generation IA, review, envoi et suivi</p>
      </div>

      {/* Tabs */}
      <div className="flex gap-2">
        <button className={tabClass('dashboard')} onClick={() => setTab('dashboard')}>
          Dashboard
        </button>
        <button className={tabClass('generate')} onClick={() => setTab('generate')}>
          Generer
        </button>
        <button className={tabClass('review')} onClick={() => setTab('review')}>
          Review {reviewQueue.length > 0 && <span className="ml-1 px-1.5 py-0.5 bg-amber/30 text-amber rounded-full text-[10px]">{reviewQueue.length}</span>}
        </button>
        <button className={tabClass('config')} onClick={() => setTab('config')}>
          Config
        </button>
      </div>

      {/* Dashboard */}
      {tab === 'dashboard' && stats && (
        <div className="space-y-4">
          {/* Funnel */}
          <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
            {[
              { label: 'En review', value: stats.global.pending_review, color: 'text-amber' },
              { label: 'Approuves', value: stats.global.approved, color: 'text-blue-400' },
              { label: 'Envoyes', value: stats.global.sent, color: 'text-cyan' },
              { label: 'Ouverts', value: stats.global.opened, color: 'text-emerald-400' },
              { label: 'Repondus', value: stats.global.replied, color: 'text-green-400' },
            ].map((kpi, i) => (
              <div key={i} className="bg-surface border border-border rounded-xl p-4 text-center">
                <div className={`text-2xl font-bold font-title ${kpi.color}`}>{kpi.value}</div>
                <div className="text-[10px] text-muted uppercase mt-1">{kpi.label}</div>
              </div>
            ))}
          </div>

          {/* Warmup status */}
          {stats.warmup.length > 0 && (
            <div className="bg-surface border border-border rounded-xl p-5">
              <h3 className="font-title font-semibold text-white mb-3">Warm-up des domaines</h3>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                {stats.warmup.map(w => (
                  <div key={w.from_email} className="bg-surface2 rounded-lg p-3">
                    <div className="text-xs text-white font-medium">{w.domain}</div>
                    <div className="text-[10px] text-muted">{w.from_email}</div>
                    <div className="flex justify-between mt-2 text-xs">
                      <span className="text-muted">Jour {w.day_count}</span>
                      <span className="text-cyan">{w.emails_sent_today}/{w.current_daily_limit} aujourd'hui</span>
                    </div>
                    <div className="w-full bg-bg rounded-full h-1.5 mt-1">
                      <div className="h-1.5 rounded-full bg-cyan" style={{ width: `${Math.min(w.emails_sent_today / w.current_daily_limit * 100, 100)}%` }} />
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {stats.global.total === 0 && (
            <div className="text-center py-12 text-muted">
              <p className="text-4xl mb-3">✉️</p>
              <p>Aucun email genere. Commencez par l'onglet "Generer".</p>
            </div>
          )}
        </div>
      )}

      {/* Generate */}
      {tab === 'generate' && (
        <div className="bg-surface border border-border rounded-xl p-5 space-y-4">
          <h3 className="font-title font-semibold text-white">Generer des emails IA</h3>
          <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div>
              <label className="block text-xs text-muted mb-1">Type de contact *</label>
              <select value={genType} onChange={e => setGenType(e.target.value)}
                className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:border-violet outline-none">
                <option value="">Choisir...</option>
                {activeTypes.map(t => <option key={t.value} value={t.value}>{t.icon} {t.label}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Pays (optionnel)</label>
              <input value={genCountry} onChange={e => setGenCountry(e.target.value)} placeholder="Tous les pays"
                className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:border-violet outline-none" />
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Step</label>
              <select value={genStep} onChange={e => setGenStep(Number(e.target.value))}
                className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:border-violet outline-none">
                <option value={1}>Step 1 — Premier contact</option>
                <option value={2}>Step 2 — Relance J+3</option>
                <option value={3}>Step 3 — Relance J+7</option>
                <option value={4}>Step 4 — Dernier message</option>
              </select>
            </div>
            <div>
              <label className="block text-xs text-muted mb-1">Limite</label>
              <input type="number" value={genLimit} onChange={e => setGenLimit(Number(e.target.value))} min={1} max={50}
                className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:border-violet outline-none" />
            </div>
          </div>
          <div className="flex items-center gap-3">
            <button onClick={handleGenerate} disabled={generating || !genType}
              className="px-6 py-2 bg-violet hover:bg-violet/90 disabled:opacity-50 text-white text-sm rounded-lg transition-colors">
              {generating ? 'Generation en cours...' : 'Generer les emails'}
            </button>
            {genResult && <span className="text-sm text-emerald-400">{genResult}</span>}
          </div>
        </div>
      )}

      {/* Review Queue */}
      {tab === 'review' && (
        <div className="space-y-4">
          {reviewQueue.length > 0 && (
            <div className="flex justify-end">
              <button onClick={handleApproveAll}
                className="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white text-sm rounded-lg transition-colors">
                Tout approuver ({reviewQueue.length})
              </button>
            </div>
          )}

          {reviewQueue.length === 0 && (
            <div className="text-center py-12 text-muted">
              <p className="text-4xl mb-3">✓</p>
              <p>Aucun email en attente de review.</p>
            </div>
          )}

          {reviewQueue.map(email => (
            <div key={email.id} className="bg-surface border border-border rounded-xl p-5">
              <div className="flex items-start justify-between gap-4">
                <div className="flex-1 min-w-0">
                  {/* Contact info */}
                  <div className="flex items-center gap-2 mb-2">
                    <span className="text-xs bg-violet/20 text-violet-light px-2 py-0.5 rounded">{email.influenceur?.contact_type}</span>
                    <span className="text-white font-medium text-sm">{email.influenceur?.name}</span>
                    <span className="text-muted text-xs">{email.influenceur?.country}</span>
                    <span className="text-cyan text-xs">{email.influenceur?.email}</span>
                    <span className="text-muted text-[10px]">Step {email.step}</span>
                  </div>

                  {/* Email preview */}
                  <div className="bg-bg rounded-lg p-4 mt-2">
                    <div className="text-xs text-muted mb-1">De: {email.from_email}</div>
                    <div className="text-sm text-white font-medium mb-2">Objet: {email.subject}</div>
                    <div className="text-sm text-gray-300 whitespace-pre-wrap leading-relaxed">{email.body_text}</div>
                  </div>
                </div>

                {/* Actions */}
                <div className="flex flex-col gap-2 flex-shrink-0">
                  <button onClick={() => handleApprove(email.id)} disabled={actionLoading === email.id}
                    className="px-3 py-1.5 bg-emerald-500/20 text-emerald-400 text-xs rounded hover:bg-emerald-500/30 transition-colors">
                    Approuver
                  </button>
                  <button onClick={() => handleReject(email.id)} disabled={actionLoading === email.id}
                    className="px-3 py-1.5 bg-red-500/20 text-red-400 text-xs rounded hover:bg-red-500/30 transition-colors">
                    Rejeter
                  </button>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Config */}
      {tab === 'config' && (
        <div className="bg-surface border border-border rounded-xl overflow-hidden">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border">
                <th className="text-left text-[10px] text-muted font-medium uppercase px-4 py-3">Type</th>
                <th className="text-center text-[10px] text-muted font-medium uppercase px-4 py-3">IA active</th>
                <th className="text-center text-[10px] text-muted font-medium uppercase px-4 py-3">Envoi auto</th>
                <th className="text-center text-[10px] text-muted font-medium uppercase px-4 py-3">Steps</th>
                <th className="text-center text-[10px] text-muted font-medium uppercase px-4 py-3">Limite/jour</th>
              </tr>
            </thead>
            <tbody>
              {activeTypes.map(t => {
                const config = configs.find(c => c.contact_type === t.value);
                return (
                  <tr key={t.value} className="border-b border-border/50 hover:bg-surface2">
                    <td className="px-4 py-3">
                      <span className="flex items-center gap-2">
                        <span>{t.icon}</span>
                        <span className="text-white text-xs">{t.label}</span>
                      </span>
                    </td>
                    <td className="px-4 py-3 text-center">
                      <input type="checkbox" checked={config?.ai_generation_enabled ?? true}
                        onChange={e => handleToggleConfig(t.value, 'ai_generation_enabled', e.target.checked)}
                        className="rounded border-gray-600 bg-bg text-violet focus:ring-violet" />
                    </td>
                    <td className="px-4 py-3 text-center">
                      <input type="checkbox" checked={config?.auto_send ?? false}
                        onChange={e => handleToggleConfig(t.value, 'auto_send', e.target.checked)}
                        className="rounded border-gray-600 bg-bg text-violet focus:ring-violet" />
                    </td>
                    <td className="px-4 py-3 text-center text-muted">{config?.max_steps ?? 4}</td>
                    <td className="px-4 py-3 text-center text-muted">{config?.daily_limit ?? 50}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
