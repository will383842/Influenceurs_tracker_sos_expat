import React, { useEffect, useState, useCallback } from 'react';
import {
  fetchTranslationOverview,
  fetchTranslationBatches,
  startTranslationBatch,
  pauseTranslationBatch,
  resumeTranslationBatch,
  cancelTranslationBatch,
} from '../../api/contentApi';
import type {
  TranslationOverview,
  TranslationBatch,
  TranslationBatchStatus,
  PaginatedResponse,
} from '../../types/content';
import { toast } from '../../components/Toast';
import { errMsg } from './helpers';

const LANGUAGE_INFO: Record<string, { name: string; flag: string }> = {
  en: { name: 'English', flag: '\uD83C\uDDEC\uD83C\uDDE7' },
  de: { name: 'Deutsch', flag: '\uD83C\uDDE9\uD83C\uDDEA' },
  es: { name: 'Espanol', flag: '\uD83C\uDDEA\uD83C\uDDF8' },
  pt: { name: 'Portugues', flag: '\uD83C\uDDF5\uD83C\uDDF9' },
  ru: { name: 'Russkij', flag: '\uD83C\uDDF7\uD83C\uDDFA' },
  zh: { name: 'Zhongwen', flag: '\uD83C\uDDE8\uD83C\uDDF3' },
  ar: { name: 'Arabiyya', flag: '\uD83C\uDDF8\uD83C\uDDE6' },
  hi: { name: 'Hindi', flag: '\uD83C\uDDEE\uD83C\uDDF3' },
};

const BATCH_STATUS_COLORS: Record<TranslationBatchStatus, string> = {
  pending: 'bg-muted/20 text-muted',
  running: 'bg-amber/20 text-amber animate-pulse',
  paused: 'bg-blue-500/20 text-blue-400',
  completed: 'bg-success/20 text-success',
  cancelled: 'bg-muted/20 text-muted line-through',
  failed: 'bg-danger/20 text-danger',
};

function progressColor(pct: number): string {
  if (pct < 30) return 'bg-danger';
  if (pct < 70) return 'bg-amber';
  return 'bg-success';
}

function formatCost(cents: number): string {
  return '$' + (cents / 100).toFixed(2);
}

export default function TranslationsDashboard() {
  const [overview, setOverview] = useState<TranslationOverview[]>([]);
  const [batches, setBatches] = useState<TranslationBatch[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionLoading, setActionLoading] = useState<string | null>(null);
  const [showHistory, setShowHistory] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [ovRes, batchRes] = await Promise.all([
        fetchTranslationOverview(),
        fetchTranslationBatches({}),
      ]);
      setOverview((ovRes.data as unknown as TranslationOverview[]) ?? []);
      const bData = batchRes.data as unknown as PaginatedResponse<TranslationBatch>;
      setBatches(bData.data ?? []);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Erreur de chargement');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const handleStart = async (language: string) => {
    setActionLoading(language);
    try {
      await startTranslationBatch({ target_language: language, content_type: 'all' });
      toast('success', `Traduction ${language.toUpperCase()} lancee.`);
      load();
    } catch (err) { toast('error', errMsg(err)); } finally {
      setActionLoading(null);
    }
  };

  const handlePause = async (id: number) => {
    setActionLoading(`batch-${id}`);
    try {
      await pauseTranslationBatch(id);
      toast('success', 'Batch en pause.');
      load();
    } catch (err) { toast('error', errMsg(err)); } finally {
      setActionLoading(null);
    }
  };

  const handleResume = async (id: number) => {
    setActionLoading(`batch-${id}`);
    try {
      await resumeTranslationBatch(id);
      toast('success', 'Batch repris.');
      load();
    } catch (err) { toast('error', errMsg(err)); } finally {
      setActionLoading(null);
    }
  };

  const handleCancelBatch = async (id: number) => {
    setActionLoading(`batch-${id}`);
    try {
      await cancelTranslationBatch(id);
      toast('success', 'Batch annule.');
      load();
    } catch (err) { toast('error', errMsg(err)); } finally {
      setActionLoading(null);
    }
  };

  // Find running batch for a language
  const runningBatchForLang = (lang: string) => batches.find(b => b.target_language === lang && (b.status === 'running' || b.status === 'paused'));

  const activeBatches = batches.filter(b => b.status === 'running' || b.status === 'paused' || b.status === 'pending');
  const completedBatches = batches.filter(b => b.status === 'completed' || b.status === 'cancelled' || b.status === 'failed');

  if (loading) {
    return (
      <div className="p-4 md:p-6 space-y-6">
        <h2 className="font-title text-2xl font-bold text-white">Traductions</h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {[1, 2, 3, 4, 5, 6].map(i => <div key={i} className="animate-pulse bg-surface border border-border rounded-xl h-32" />)}
        </div>
      </div>
    );
  }

  return (
    <div className="p-4 md:p-6 space-y-6">
      <h2 className="font-title text-2xl font-bold text-white">Traductions</h2>
      {error && (
        <div className="flex items-center justify-between bg-danger/10 border border-danger/30 rounded-lg p-3">
          <p className="text-danger text-sm">{error}</p>
          <button onClick={load} className="text-xs text-danger hover:text-red-300 transition-colors">Reessayer</button>
        </div>
      )}

      {/* Language overview cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        {Object.entries(LANGUAGE_INFO).map(([lang, info]) => {
          const ov = overview.find(o => o.language === lang);
          const translated = ov?.translated ?? 0;
          const total = ov?.total_fr ?? 0;
          const pct = ov?.percent ?? 0;
          const runBatch = runningBatchForLang(lang);

          return (
            <div
              key={lang}
              className={`bg-surface border border-border rounded-xl p-5 space-y-3 ${runBatch?.status === 'running' ? 'ring-1 ring-amber/50' : ''}`}
            >
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <span className="text-xl">{info.flag}</span>
                  <span className="text-white font-medium">{info.name}</span>
                </div>
                <span className="text-xs text-muted uppercase">{lang}</span>
              </div>

              <p className="text-sm text-muted">
                {translated}/{total} traduits ({pct}%)
              </p>

              {/* Progress bar */}
              <div className="w-full h-2 bg-surface2 rounded-full overflow-hidden">
                <div
                  className={`h-full rounded-full transition-all ${progressColor(pct)} ${runBatch?.status === 'running' ? 'animate-pulse' : ''}`}
                  style={{ width: `${pct}%` }}
                />
              </div>

              {/* Action button */}
              <div>
                {pct >= 100 ? (
                  <span className="text-xs text-success font-medium">Termine</span>
                ) : runBatch?.status === 'running' ? (
                  <button
                    onClick={() => handlePause(runBatch.id)}
                    disabled={actionLoading === `batch-${runBatch.id}`}
                    className="px-3 py-1 text-xs bg-amber/20 text-amber hover:bg-amber/30 rounded-lg transition-colors disabled:opacity-50"
                  >
                    {actionLoading === `batch-${runBatch.id}` ? '...' : 'Pause'}
                  </button>
                ) : runBatch?.status === 'paused' ? (
                  <button
                    onClick={() => handleResume(runBatch.id)}
                    disabled={actionLoading === `batch-${runBatch.id}`}
                    className="px-3 py-1 text-xs bg-blue-500/20 text-blue-400 hover:bg-blue-500/30 rounded-lg transition-colors disabled:opacity-50"
                  >
                    {actionLoading === `batch-${runBatch.id}` ? '...' : 'Reprendre'}
                  </button>
                ) : (
                  <button
                    onClick={() => handleStart(lang)}
                    disabled={actionLoading === lang}
                    className="px-3 py-1 text-xs bg-violet/20 text-violet hover:bg-violet/30 rounded-lg transition-colors disabled:opacity-50"
                  >
                    {actionLoading === lang ? '...' : 'Lancer'}
                  </button>
                )}
              </div>
            </div>
          );
        })}
      </div>

      {/* Active batches table */}
      {activeBatches.length > 0 && (
        <div className="bg-surface border border-border rounded-xl p-5">
          <h3 className="font-title text-lg font-semibold text-white mb-4">Batches actifs</h3>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs text-muted uppercase tracking-wide border-b border-border">
                  <th className="pb-3 pr-4">Langue</th>
                  <th className="pb-3 pr-4">Type</th>
                  <th className="pb-3 pr-4">Statut</th>
                  <th className="pb-3 pr-4">Progression</th>
                  <th className="pb-3 pr-4">Items</th>
                  <th className="pb-3 pr-4">Cout</th>
                  <th className="pb-3">Actions</th>
                </tr>
              </thead>
              <tbody>
                {activeBatches.map(batch => {
                  const bpct = batch.total_items > 0 ? Math.round((batch.completed_items / batch.total_items) * 100) : 0;
                  return (
                    <tr key={batch.id} className="border-b border-border/50">
                      <td className="py-3 pr-4">
                        <span className="text-white">
                          {LANGUAGE_INFO[batch.target_language]?.flag ?? ''} {batch.target_language.toUpperCase()}
                        </span>
                      </td>
                      <td className="py-3 pr-4 text-muted">{batch.content_type}</td>
                      <td className="py-3 pr-4">
                        <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${BATCH_STATUS_COLORS[batch.status]}`}>
                          {batch.status}
                        </span>
                      </td>
                      <td className="py-3 pr-4">
                        <div className="flex items-center gap-2">
                          <div className="w-20 h-1.5 bg-surface2 rounded-full overflow-hidden">
                            <div className={`h-full rounded-full ${progressColor(bpct)}`} style={{ width: `${bpct}%` }} />
                          </div>
                          <span className="text-xs text-muted">{bpct}%</span>
                        </div>
                      </td>
                      <td className="py-3 pr-4 text-white">{batch.completed_items}/{batch.total_items}</td>
                      <td className="py-3 pr-4 text-white">{formatCost(batch.total_cost_cents)}</td>
                      <td className="py-3">
                        <div className="flex items-center gap-2">
                          {batch.status === 'running' && (
                            <button
                              onClick={() => handlePause(batch.id)}
                              disabled={actionLoading === `batch-${batch.id}`}
                              className="text-xs text-amber hover:text-yellow-300 transition-colors disabled:opacity-50"
                            >
                              Pause
                            </button>
                          )}
                          {batch.status === 'paused' && (
                            <button
                              onClick={() => handleResume(batch.id)}
                              disabled={actionLoading === `batch-${batch.id}`}
                              className="text-xs text-blue-400 hover:text-blue-300 transition-colors disabled:opacity-50"
                            >
                              Reprendre
                            </button>
                          )}
                          <button
                            onClick={() => handleCancelBatch(batch.id)}
                            disabled={actionLoading === `batch-${batch.id}`}
                            className="text-xs text-danger hover:text-red-300 transition-colors disabled:opacity-50"
                          >
                            Annuler
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* History */}
      {completedBatches.length > 0 && (
        <div className="bg-surface border border-border rounded-xl p-5">
          <button
            onClick={() => setShowHistory(!showHistory)}
            className="flex items-center gap-2 font-title text-lg font-semibold text-white"
          >
            <span className={`text-xs transition-transform ${showHistory ? 'rotate-90' : ''}`}>{'\u25B6'}</span>
            Historique ({completedBatches.length})
          </button>
          {showHistory && (
            <div className="overflow-x-auto mt-4">
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-left text-xs text-muted uppercase tracking-wide border-b border-border">
                    <th className="pb-3 pr-4">Langue</th>
                    <th className="pb-3 pr-4">Type</th>
                    <th className="pb-3 pr-4">Statut</th>
                    <th className="pb-3 pr-4">Items</th>
                    <th className="pb-3 pr-4">Echoues</th>
                    <th className="pb-3 pr-4">Cout</th>
                    <th className="pb-3">Date</th>
                  </tr>
                </thead>
                <tbody>
                  {completedBatches.map(batch => (
                    <tr key={batch.id} className="border-b border-border/50">
                      <td className="py-3 pr-4 text-white">
                        {LANGUAGE_INFO[batch.target_language]?.flag ?? ''} {batch.target_language.toUpperCase()}
                      </td>
                      <td className="py-3 pr-4 text-muted">{batch.content_type}</td>
                      <td className="py-3 pr-4">
                        <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${BATCH_STATUS_COLORS[batch.status]}`}>
                          {batch.status}
                        </span>
                      </td>
                      <td className="py-3 pr-4 text-white">{batch.completed_items}/{batch.total_items}</td>
                      <td className="py-3 pr-4 text-danger">{batch.failed_items}</td>
                      <td className="py-3 pr-4 text-white">{formatCost(batch.total_cost_cents)}</td>
                      <td className="py-3 text-muted text-xs">
                        {batch.completed_at ? new Date(batch.completed_at).toLocaleString('fr') : '-'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
