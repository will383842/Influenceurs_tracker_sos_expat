import React, { useEffect, useState, useCallback, useRef } from 'react';
import {
  fetchDailySchedule,
  updateDailySchedule,
  fetchScheduleHistory,
  runScheduleNow,
  addCustomTitles,
} from '../../api/contentApi';
import type {
  DailyContentSchedule,
  DailyContentLog,
  ScheduleStatus,
} from '../../types/content';
import { toast } from '../../components/Toast';
import { inputClass, errMsg, cents } from './helpers';

// ── Helpers ─────────────────────────────────────────────────

function progressColor(current: number, target: number): string {
  if (target === 0) return 'bg-muted';
  const pct = (current / target) * 100;
  if (pct >= 80) return 'bg-success';
  if (pct >= 50) return 'bg-amber';
  return 'bg-danger';
}

function progressTextColor(current: number, target: number): string {
  if (target === 0) return 'text-muted';
  const pct = (current / target) * 100;
  if (pct >= 80) return 'text-success';
  if (pct >= 50) return 'text-amber';
  return 'text-danger';
}

function completionIcon(current: number, target: number): string {
  if (target === 0) return '';
  const pct = (current / target) * 100;
  if (pct >= 100) return ' \u2705';
  if (pct >= 50) return ' \u26A0\uFE0F';
  return ' \u274C';
}

const CATEGORIES = [
  { value: '', label: 'Toutes' },
  { value: 'visa', label: 'Visa' },
  { value: 'logement', label: 'Logement' },
  { value: 'sante', label: 'Sante' },
  { value: 'emploi', label: 'Emploi' },
  { value: 'education', label: 'Education' },
  { value: 'banque', label: 'Banque' },
  { value: 'transport', label: 'Transport' },
  { value: 'culture', label: 'Culture' },
  { value: 'demarches', label: 'Demarches' },
  { value: 'telecom', label: 'Telecom' },
];

// ── Component ───────────────────────────────────────────────

export default function DailyScheduler() {
  const [status, setStatus] = useState<ScheduleStatus | null>(null);
  const [history, setHistory] = useState<DailyContentLog[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [runningNow, setRunningNow] = useState(false);

  // Form state (mirrors schedule config)
  const [form, setForm] = useState<Partial<DailyContentSchedule>>({});
  const [dirty, setDirty] = useState(false);

  // Custom titles
  const [newTitle, setNewTitle] = useState('');
  const [addingTitles, setAddingTitles] = useState(false);

  // Ref for 30s polling
  const pollingRef = useRef<ReturnType<typeof setInterval> | null>(null);

  // ── Load data ──
  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [scheduleRes, historyRes] = await Promise.all([
        fetchDailySchedule(),
        fetchScheduleHistory(),
      ]);
      const s = scheduleRes.data as unknown as ScheduleStatus;
      setStatus(s);
      setHistory((historyRes.data as unknown as DailyContentLog[]) ?? []);
      // Init form from schedule
      if (s?.schedule) {
        setForm({
          pillar_articles_per_day: s.schedule.pillar_articles_per_day,
          normal_articles_per_day: s.schedule.normal_articles_per_day,
          qa_per_day: s.schedule.qa_per_day,
          comparatives_per_day: s.schedule.comparatives_per_day,
          publish_per_day: s.schedule.publish_per_day,
          publish_start_hour: s.schedule.publish_start_hour,
          publish_end_hour: s.schedule.publish_end_hour,
          publish_irregular: s.schedule.publish_irregular,
          target_country: s.schedule.target_country ?? '',
          target_category: s.schedule.target_category ?? '',
          min_quality_score: s.schedule.min_quality_score,
          is_active: s.schedule.is_active,
        });
        setDirty(false);
      }
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Erreur de chargement');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  // ── Auto-refresh today's progress every 30s ──
  useEffect(() => {
    const refresh = async () => {
      try {
        const res = await fetchDailySchedule();
        const s = res.data as unknown as ScheduleStatus;
        setStatus(s);
      } catch { /* silent */ }
    };
    pollingRef.current = setInterval(refresh, 30000);
    return () => {
      if (pollingRef.current) clearInterval(pollingRef.current);
    };
  }, []);

  // ── Handlers ──
  const updateField = <K extends keyof DailyContentSchedule>(key: K, value: DailyContentSchedule[K]) => {
    setForm(prev => ({ ...prev, [key]: value }));
    setDirty(true);
  };

  const handleSave = async () => {
    setSaving(true);
    try {
      const payload = { ...form };
      // Convert empty strings to null for nullable fields
      if (payload.target_country === '') payload.target_country = null;
      if (payload.target_category === '') payload.target_category = null;
      await updateDailySchedule(payload);
      toast('success', 'Configuration sauvegardee.');
      setDirty(false);
      load();
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setSaving(false);
    }
  };

  const handleRunNow = async () => {
    setRunningNow(true);
    try {
      await runScheduleNow();
      toast('success', 'Pipeline quotidien lance ! Progression en temps reel ci-dessous.');
      // Refresh to show running state
      const res = await fetchDailySchedule();
      setStatus(res.data as unknown as ScheduleStatus);
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setRunningNow(false);
    }
  };

  const handleAddTitle = async () => {
    const title = newTitle.trim();
    if (!title) return;
    setAddingTitles(true);
    try {
      await addCustomTitles([title]);
      toast('success', 'Titre ajoute.');
      setNewTitle('');
      load();
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setAddingTitles(false);
    }
  };

  const handleRemoveTitle = async (index: number) => {
    if (!status?.schedule?.custom_titles) return;
    const updated = status.schedule.custom_titles.filter((_, i) => i !== index);
    try {
      await updateDailySchedule({ custom_titles: updated.length > 0 ? updated : null });
      toast('success', 'Titre supprime.');
      load();
    } catch (err) {
      toast('error', errMsg(err));
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      handleAddTitle();
    }
  };

  // ── Render ──
  if (loading) {
    return (
      <div className="p-4 md:p-6 space-y-6">
        <div className="animate-pulse bg-surface2 rounded-lg h-8 w-72" />
        <div className="grid grid-cols-2 sm:grid-cols-5 gap-4">
          {[1, 2, 3, 4, 5].map(i => <div key={i} className="animate-pulse bg-surface2 rounded-xl h-24" />)}
        </div>
        <div className="animate-pulse bg-surface2 rounded-xl h-64" />
        <div className="animate-pulse bg-surface2 rounded-xl h-48" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="p-4 md:p-6">
        <div className="bg-surface border border-border rounded-xl p-8 text-center">
          <p className="text-danger mb-4">{error}</p>
          <button onClick={load} className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors">
            Reessayer
          </button>
        </div>
      </div>
    );
  }

  const schedule = status?.schedule;
  const today = status?.today;
  const isRunning = status?.is_running ?? false;
  const customTitles = schedule?.custom_titles ?? [];

  // Progress card data
  const progressCards = [
    {
      label: 'Pilier',
      current: today?.pillar_generated ?? 0,
      target: schedule?.pillar_articles_per_day ?? 0,
      desc: '3000+ mots',
    },
    {
      label: 'Normal',
      current: today?.normal_generated ?? 0,
      target: schedule?.normal_articles_per_day ?? 0,
      desc: '1500-2500 mots',
    },
    {
      label: 'Q&A',
      current: today?.qa_generated ?? 0,
      target: schedule?.qa_per_day ?? 0,
      desc: 'Questions/reponses',
    },
    {
      label: 'Comparatif',
      current: today?.comparatives_generated ?? 0,
      target: schedule?.comparatives_per_day ?? 0,
      desc: 'Tableaux comparatifs',
    },
    {
      label: 'Publies',
      current: today?.published ?? 0,
      target: schedule?.publish_per_day ?? 0,
      desc: 'SOS-Expat',
    },
  ];

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h2 className="font-title text-2xl font-bold text-white">Planification quotidienne</h2>
          {isRunning && (
            <span className="inline-block mt-1 px-3 py-1 rounded-full bg-amber/20 text-amber text-xs animate-pulse">
              Pipeline en cours d'execution...
            </span>
          )}
        </div>
        <button
          onClick={handleRunNow}
          disabled={runningNow || isRunning}
          className="px-5 py-2.5 rounded-lg bg-violet hover:bg-violet/80 text-white font-bold text-sm transition disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap"
        >
          {runningNow ? 'Lancement...' : isRunning ? 'En cours...' : 'Lancer maintenant'}
        </button>
      </div>

      {/* ── TODAY'S PROGRESS ── */}
      <div>
        <h3 className="text-xs text-muted uppercase tracking-wider mb-3 font-semibold">Progression aujourd'hui</h3>
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
          {progressCards.map(card => {
            const pct = card.target > 0 ? Math.min(100, Math.round((card.current / card.target) * 100)) : 0;
            return (
              <div key={card.label} className="bg-surface border border-border rounded-xl p-4">
                <div className="flex items-center justify-between mb-1">
                  <span className="text-sm text-white font-medium">{card.label}</span>
                  <span className={`text-xs font-bold ${progressTextColor(card.current, card.target)}`}>
                    {card.current}/{card.target}
                  </span>
                </div>
                <div className="w-full h-2 bg-surface2 rounded-full overflow-hidden mb-1">
                  <div
                    className={`h-full rounded-full transition-all ${progressColor(card.current, card.target)} ${isRunning ? 'animate-pulse' : ''}`}
                    style={{ width: `${pct}%` }}
                  />
                </div>
                <p className="text-[10px] text-muted">{card.desc}</p>
              </div>
            );
          })}
        </div>
        {today?.total_cost_cents != null && today.total_cost_cents > 0 && (
          <p className="text-xs text-muted mt-2">
            Cout aujourd'hui : <span className="text-white font-medium">${cents(today.total_cost_cents)}</span>
          </p>
        )}
      </div>

      {/* ── CONFIGURATION ── */}
      <div className="bg-surface border border-border rounded-xl p-5 space-y-5">
        <h3 className="text-xs text-muted uppercase tracking-wider font-semibold">Configuration</h3>

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {/* Pillar */}
          <div>
            <label className="block text-sm text-white mb-1">Articles pilier / jour</label>
            <input
              type="number" min={0} max={50}
              value={form.pillar_articles_per_day ?? 0}
              onChange={e => updateField('pillar_articles_per_day', +e.target.value)}
              className={inputClass + ' w-full'}
            />
            <p className="text-[10px] text-muted mt-1">3000+ mots, guide complet</p>
          </div>

          {/* Normal */}
          <div>
            <label className="block text-sm text-white mb-1">Articles normaux / jour</label>
            <input
              type="number" min={0} max={100}
              value={form.normal_articles_per_day ?? 0}
              onChange={e => updateField('normal_articles_per_day', +e.target.value)}
              className={inputClass + ' w-full'}
            />
            <p className="text-[10px] text-muted mt-1">1500-2500 mots, standard</p>
          </div>

          {/* Q&A */}
          <div>
            <label className="block text-sm text-white mb-1">Q&A / jour</label>
            <input
              type="number" min={0} max={200}
              value={form.qa_per_day ?? 0}
              onChange={e => updateField('qa_per_day', +e.target.value)}
              className={inputClass + ' w-full'}
            />
            <p className="text-[10px] text-muted mt-1">Pages question/reponse</p>
          </div>

          {/* Comparatives */}
          <div>
            <label className="block text-sm text-white mb-1">Comparatifs / jour</label>
            <input
              type="number" min={0} max={50}
              value={form.comparatives_per_day ?? 0}
              onChange={e => updateField('comparatives_per_day', +e.target.value)}
              className={inputClass + ' w-full'}
            />
            <p className="text-[10px] text-muted mt-1">Tableaux comparatifs</p>
          </div>

          {/* Min quality */}
          <div>
            <label className="block text-sm text-white mb-1">Score qualite minimum</label>
            <div className="flex items-center gap-2">
              <input
                type="number" min={0} max={100}
                value={form.min_quality_score ?? 85}
                onChange={e => updateField('min_quality_score', +e.target.value)}
                className={inputClass + ' w-full'}
              />
              <span className="text-sm text-muted whitespace-nowrap">/100</span>
            </div>
          </div>

          {/* Country */}
          <div>
            <label className="block text-sm text-white mb-1">Pays cible</label>
            <input
              type="text"
              value={form.target_country ?? ''}
              onChange={e => updateField('target_country', e.target.value as string)}
              placeholder="Tous les pays"
              className={inputClass + ' w-full'}
            />
          </div>

          {/* Category */}
          <div>
            <label className="block text-sm text-white mb-1">Categorie</label>
            <select
              value={form.target_category ?? ''}
              onChange={e => updateField('target_category', e.target.value as string)}
              className={inputClass + ' w-full'}
            >
              {CATEGORIES.map(c => (
                <option key={c.value} value={c.value}>{c.label}</option>
              ))}
            </select>
          </div>
        </div>
      </div>

      {/* ── PUBLICATION SETTINGS ── */}
      <div className="bg-surface border border-border rounded-xl p-5 space-y-5">
        <h3 className="text-xs text-muted uppercase tracking-wider font-semibold">Publication SOS-Expat</h3>

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div>
            <label className="block text-sm text-white mb-1">Articles a publier / jour</label>
            <input
              type="number" min={0} max={100}
              value={form.publish_per_day ?? 0}
              onChange={e => updateField('publish_per_day', +e.target.value)}
              className={inputClass + ' w-full'}
            />
          </div>

          <div>
            <label className="block text-sm text-white mb-1">Heure de debut</label>
            <div className="flex items-center gap-2">
              <input
                type="number" min={0} max={23}
                value={form.publish_start_hour ?? 7}
                onChange={e => updateField('publish_start_hour', +e.target.value)}
                className={inputClass + ' w-20'}
              />
              <span className="text-sm text-muted">h</span>
            </div>
          </div>

          <div>
            <label className="block text-sm text-white mb-1">Heure de fin</label>
            <div className="flex items-center gap-2">
              <input
                type="number" min={0} max={23}
                value={form.publish_end_hour ?? 22}
                onChange={e => updateField('publish_end_hour', +e.target.value)}
                className={inputClass + ' w-20'}
              />
              <span className="text-sm text-muted">h</span>
            </div>
          </div>

          <div className="flex items-center gap-3 pt-6">
            <input
              type="checkbox"
              id="publish_irregular"
              checked={form.publish_irregular ?? false}
              onChange={e => updateField('publish_irregular', e.target.checked)}
              className="accent-violet"
            />
            <label htmlFor="publish_irregular" className="text-sm text-muted">
              Publication irreguliere (naturelle, pas toutes les X min)
            </label>
          </div>
        </div>
      </div>

      {/* ── CUSTOM TITLES ── */}
      <div className="bg-surface border border-border rounded-xl p-5 space-y-4">
        <h3 className="text-xs text-muted uppercase tracking-wider font-semibold">Titres personnalises</h3>

        <div className="flex gap-2">
          <input
            type="text"
            value={newTitle}
            onChange={e => setNewTitle(e.target.value)}
            onKeyDown={handleKeyDown}
            placeholder="Ajouter un titre..."
            className={inputClass + ' flex-1'}
          />
          <button
            onClick={handleAddTitle}
            disabled={addingTitles || !newTitle.trim()}
            className="px-4 py-2 bg-violet hover:bg-violet/80 text-white text-sm rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {addingTitles ? '...' : '+'}
          </button>
        </div>

        {customTitles.length > 0 ? (
          <ul className="space-y-2">
            {customTitles.map((title, idx) => (
              <li
                key={idx}
                className="flex items-center justify-between bg-surface2/50 rounded-lg px-3 py-2"
              >
                <span className="text-sm text-white">{title}</span>
                <button
                  onClick={() => handleRemoveTitle(idx)}
                  className="text-muted hover:text-danger transition-colors text-sm ml-3 flex-shrink-0"
                  title="Supprimer"
                >
                  x
                </button>
              </li>
            ))}
          </ul>
        ) : (
          <p className="text-xs text-muted">Aucun titre personnalise. Les articles seront generes automatiquement.</p>
        )}
      </div>

      {/* ── SAVE BUTTON ── */}
      <div className="flex justify-center">
        <button
          onClick={handleSave}
          disabled={saving || !dirty}
          className="px-8 py-3 bg-violet hover:bg-violet/80 text-white font-bold text-sm rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {saving ? 'Sauvegarde...' : 'Sauvegarder la configuration'}
        </button>
      </div>

      {/* ── HISTORY TABLE ── */}
      <div className="bg-surface border border-border rounded-xl p-5">
        <h3 className="font-title text-lg font-semibold text-white mb-4">
          Historique (30 derniers jours)
        </h3>

        {history.length === 0 ? (
          <p className="text-sm text-muted text-center py-6">Aucun historique disponible.</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs text-muted uppercase tracking-wide border-b border-border">
                  <th className="pb-3 pr-4">Date</th>
                  <th className="pb-3 pr-4">Pilier</th>
                  <th className="pb-3 pr-4">Normal</th>
                  <th className="pb-3 pr-4">Q&A</th>
                  <th className="pb-3 pr-4">Comp.</th>
                  <th className="pb-3 pr-4">Custom</th>
                  <th className="pb-3 pr-4">Pub.</th>
                  <th className="pb-3">Cout</th>
                </tr>
              </thead>
              <tbody>
                {history.map(log => (
                  <tr key={log.id} className="border-b border-border/50 hover:bg-surface2/30 transition-colors">
                    <td className="py-3 pr-4 text-white text-xs">
                      {new Date(log.date).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' })}
                    </td>
                    <td className="py-3 pr-4">
                      <span className={progressTextColor(log.pillar_generated, schedule?.pillar_articles_per_day ?? 0)}>
                        {log.pillar_generated}/{schedule?.pillar_articles_per_day ?? 0}
                        {completionIcon(log.pillar_generated, schedule?.pillar_articles_per_day ?? 0)}
                      </span>
                    </td>
                    <td className="py-3 pr-4">
                      <span className={progressTextColor(log.normal_generated, schedule?.normal_articles_per_day ?? 0)}>
                        {log.normal_generated}/{schedule?.normal_articles_per_day ?? 0}
                        {completionIcon(log.normal_generated, schedule?.normal_articles_per_day ?? 0)}
                      </span>
                    </td>
                    <td className="py-3 pr-4">
                      <span className={progressTextColor(log.qa_generated, schedule?.qa_per_day ?? 0)}>
                        {log.qa_generated}/{schedule?.qa_per_day ?? 0}
                        {completionIcon(log.qa_generated, schedule?.qa_per_day ?? 0)}
                      </span>
                    </td>
                    <td className="py-3 pr-4">
                      <span className={progressTextColor(log.comparatives_generated, schedule?.comparatives_per_day ?? 0)}>
                        {log.comparatives_generated}/{schedule?.comparatives_per_day ?? 0}
                        {completionIcon(log.comparatives_generated, schedule?.comparatives_per_day ?? 0)}
                      </span>
                    </td>
                    <td className="py-3 pr-4 text-white">{log.custom_generated}</td>
                    <td className="py-3 pr-4">
                      <span className={progressTextColor(log.published, schedule?.publish_per_day ?? 0)}>
                        {log.published}
                      </span>
                    </td>
                    <td className="py-3 text-white">${cents(log.total_cost_cents)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
