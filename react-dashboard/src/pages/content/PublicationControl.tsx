import React, { useEffect, useState, useCallback } from 'react';
import {
  fetchPublicationStats,
  updatePublicationRate,
  fetchPublicationQueue,
  executeQueueItem,
  cancelQueueItem,
} from '../../api/contentApi';
import type { PublicationStats, PublicationQueueItem } from '../../types/content';
import { toast } from '../../components/Toast';
import { inputClass, errMsg } from './helpers';

const TYPE_LABELS: Record<string, string> = {
  article: 'Article',
  guide: 'Guide',
  qa: 'Q&A',
  comparative: 'Comparatif',
  news: 'Actualite',
  tutorial: 'Tutoriel',
  landing: 'Landing',
  press_release: 'Communique',
};

const STATUS_LABELS: Record<string, string> = {
  review: 'En revue',
  draft: 'Brouillon',
  scheduled: 'Planifie',
  generating: 'En cours',
};

export default function PublicationControl() {
  const [stats, setStats] = useState<PublicationStats | null>(null);
  const [queue, setQueue] = useState<PublicationQueueItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  // Publication rate form
  const [publishPerDay, setPublishPerDay] = useState(10);
  const [startHour, setStartHour] = useState(7);
  const [endHour, setEndHour] = useState(22);
  const [irregular, setIrregular] = useState(true);
  const [dirty, setDirty] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [statsRes, queueRes] = await Promise.all([
        fetchPublicationStats(),
        fetchPublicationQueue({ status: 'pending', per_page: 20 }),
      ]);
      const s = statsRes.data as unknown as PublicationStats;
      setStats(s);
      setPublishPerDay(s.publish_per_day || 10);
      setQueue((queueRes.data as any)?.data ?? []);
    } catch (err) {
      setError(errMsg(err));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const handleSave = async () => {
    setSaving(true);
    try {
      await updatePublicationRate({ publish_per_day: publishPerDay, start_hour: startHour, end_hour: endHour, irregular });
      toast('success', 'Rythme de publication mis a jour.');
      setDirty(false);
      load();
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setSaving(false);
    }
  };

  const handleExecute = async (id: number) => {
    try {
      await executeQueueItem(id);
      toast('success', 'Article publie.');
      load();
    } catch (err) {
      toast('error', errMsg(err));
    }
  };

  const handleCancel = async (id: number) => {
    try {
      await cancelQueueItem(id);
      toast('success', 'Publication annulee.');
      load();
    } catch (err) {
      toast('error', errMsg(err));
    }
  };

  if (loading) {
    return (
      <div className="p-4 md:p-6 space-y-6">
        <div className="animate-pulse bg-surface2 rounded-lg h-8 w-72" />
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
          {[1, 2, 3, 4].map(i => <div key={i} className="animate-pulse bg-surface2 rounded-xl h-28" />)}
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="p-4 md:p-6">
        <div className="bg-surface border border-border rounded-xl p-8 text-center">
          <p className="text-danger mb-4">{error}</p>
          <button onClick={load} className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg">Reessayer</button>
        </div>
      </div>
    );
  }

  const stock = stats?.unpublished_stock ?? 0;
  const daysOfStock = stats?.days_of_stock ?? 0;
  const pubToday = stats?.published_today ?? 0;
  const genToday = stats?.generation_today ?? 0;

  return (
    <div className="p-4 md:p-6 space-y-6">
      <h1 className="text-xl font-semibold text-t1">Publication — Controle du flux</h1>

      {/* KPI Cards */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-surface border border-border rounded-xl p-5">
          <p className="text-xs text-t3 uppercase tracking-wider">Stock non publie</p>
          <p className="text-3xl font-bold text-t1 mt-1">{stock}</p>
          <p className="text-xs text-t3 mt-1">articles en attente</p>
        </div>
        <div className="bg-surface border border-border rounded-xl p-5">
          <p className="text-xs text-t3 uppercase tracking-wider">Publies aujourd'hui</p>
          <p className="text-3xl font-bold text-t1 mt-1">{pubToday} <span className="text-lg text-t3">/ {publishPerDay}</span></p>
          <div className="mt-2 h-2 bg-surface2 rounded-full overflow-hidden">
            <div className={`h-full rounded-full ${pubToday >= publishPerDay ? 'bg-success' : 'bg-violet'}`}
                 style={{ width: `${Math.min(100, publishPerDay > 0 ? (pubToday / publishPerDay) * 100 : 0)}%` }} />
          </div>
        </div>
        <div className="bg-surface border border-border rounded-xl p-5">
          <p className="text-xs text-t3 uppercase tracking-wider">Generes aujourd'hui</p>
          <p className="text-3xl font-bold text-t1 mt-1">{genToday}</p>
          <p className="text-xs text-t3 mt-1">nouveaux articles</p>
        </div>
        <div className="bg-surface border border-border rounded-xl p-5">
          <p className="text-xs text-t3 uppercase tracking-wider">Projection stock</p>
          <p className="text-3xl font-bold text-t1 mt-1">{daysOfStock} <span className="text-lg text-t3">jours</span></p>
          <p className="text-xs text-t3 mt-1">au rythme actuel ({publishPerDay}/j)</p>
        </div>
      </div>

      {/* Stock breakdown */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* By content type */}
        <div className="bg-surface border border-border rounded-xl p-5">
          <h2 className="text-sm font-semibold text-t1 mb-4">Stock par taxonomie</h2>
          <div className="space-y-3">
            {Object.entries(stats?.by_content_type ?? {}).sort((a, b) => b[1] - a[1]).map(([type, count]) => (
              <div key={type} className="flex items-center gap-3">
                <span className="text-xs text-t2 w-24 truncate">{TYPE_LABELS[type] || type}</span>
                <div className="flex-1 h-5 bg-surface2 rounded-full overflow-hidden">
                  <div className="h-full bg-violet/70 rounded-full" style={{ width: `${stock > 0 ? (count / stock) * 100 : 0}%` }} />
                </div>
                <span className="text-xs font-mono text-t1 w-10 text-right">{count}</span>
              </div>
            ))}
          </div>
        </div>

        {/* By status */}
        <div className="bg-surface border border-border rounded-xl p-5">
          <h2 className="text-sm font-semibold text-t1 mb-4">Stock par status</h2>
          <div className="space-y-3">
            {Object.entries(stats?.by_status ?? {}).sort((a, b) => b[1] - a[1]).map(([status, count]) => (
              <div key={status} className="flex items-center gap-3">
                <span className="text-xs text-t2 w-24 truncate">{STATUS_LABELS[status] || status}</span>
                <div className="flex-1 h-5 bg-surface2 rounded-full overflow-hidden">
                  <div className={`h-full rounded-full ${status === 'review' ? 'bg-amber' : status === 'draft' ? 'bg-muted' : 'bg-info'}`}
                       style={{ width: `${stock > 0 ? (count / stock) * 100 : 0}%` }} />
                </div>
                <span className="text-xs font-mono text-t1 w-10 text-right">{count}</span>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Publication rate config */}
      <div className="bg-surface border border-border rounded-xl p-5">
        <h2 className="text-sm font-semibold text-t1 mb-4">Rythme de publication</h2>
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
          <div>
            <label className="block text-xs text-t3 mb-1">Articles / jour</label>
            <input type="number" min={0} max={200} value={publishPerDay}
                   onChange={e => { setPublishPerDay(+e.target.value); setDirty(true); }}
                   className={inputClass} />
          </div>
          <div>
            <label className="block text-xs text-t3 mb-1">Heure debut</label>
            <input type="number" min={0} max={23} value={startHour}
                   onChange={e => { setStartHour(+e.target.value); setDirty(true); }}
                   className={inputClass} />
          </div>
          <div>
            <label className="block text-xs text-t3 mb-1">Heure fin</label>
            <input type="number" min={0} max={23} value={endHour}
                   onChange={e => { setEndHour(+e.target.value); setDirty(true); }}
                   className={inputClass} />
          </div>
          <div className="flex items-end pb-1">
            <label className="flex items-center gap-2 text-xs text-t2 cursor-pointer">
              <input type="checkbox" checked={irregular}
                     onChange={e => { setIrregular(e.target.checked); setDirty(true); }}
                     className="rounded border-border" />
              Publication irreguliere
            </label>
          </div>
        </div>
        {dirty && (
          <div className="mt-4 flex gap-2">
            <button onClick={handleSave} disabled={saving}
                    className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg disabled:opacity-50">
              {saving ? 'Sauvegarde...' : 'Sauvegarder'}
            </button>
          </div>
        )}
      </div>

      {/* Publication queue */}
      <div className="bg-surface border border-border rounded-xl p-5">
        <h2 className="text-sm font-semibold text-t1 mb-4">File d'attente ({queue.length} en attente)</h2>
        {queue.length === 0 ? (
          <p className="text-sm text-t3 text-center py-8">Aucun article en file d'attente.</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-xs">
              <thead>
                <tr className="text-t3 uppercase tracking-wider border-b border-border">
                  <th className="text-left py-2 px-2">Article</th>
                  <th className="text-left py-2 px-2">Type</th>
                  <th className="text-left py-2 px-2">Planifie</th>
                  <th className="text-left py-2 px-2">Status</th>
                  <th className="text-right py-2 px-2">Actions</th>
                </tr>
              </thead>
              <tbody>
                {queue.map(item => (
                  <tr key={item.id} className="border-b border-border/50 hover:bg-surface2/50">
                    <td className="py-2 px-2 text-t1 max-w-[200px] truncate">{(item as any).publishable?.title ?? `#${item.publishable_id}`}</td>
                    <td className="py-2 px-2 text-t2">{item.publishable_type?.split('\\').pop()}</td>
                    <td className="py-2 px-2 text-t2">{item.scheduled_at ? new Date(item.scheduled_at).toLocaleString('fr-FR') : '—'}</td>
                    <td className="py-2 px-2">
                      <span className={`px-2 py-0.5 rounded-full text-[10px] font-medium ${
                        item.status === 'published' ? 'bg-success/20 text-success' :
                        item.status === 'failed' ? 'bg-danger/20 text-danger' :
                        'bg-amber/20 text-amber'
                      }`}>{item.status}</span>
                    </td>
                    <td className="py-2 px-2 text-right space-x-1">
                      <button onClick={() => handleExecute(item.id)} className="px-2 py-1 bg-success/20 text-success rounded text-[10px] hover:bg-success/30">Publier</button>
                      <button onClick={() => handleCancel(item.id)} className="px-2 py-1 bg-danger/20 text-danger rounded text-[10px] hover:bg-danger/30">Annuler</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Monthly stats */}
      <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
        <div className="bg-surface border border-border rounded-xl p-4 text-center">
          <p className="text-xs text-t3">Cette semaine</p>
          <p className="text-2xl font-bold text-t1">{stats?.published_this_week ?? 0}</p>
        </div>
        <div className="bg-surface border border-border rounded-xl p-4 text-center">
          <p className="text-xs text-t3">Ce mois</p>
          <p className="text-2xl font-bold text-t1">{stats?.published_this_month ?? 0}</p>
        </div>
        <div className="bg-surface border border-border rounded-xl p-4 text-center">
          <p className="text-xs text-t3">Total publie</p>
          <p className="text-2xl font-bold text-t1">{stats?.total_published ?? 0}</p>
        </div>
      </div>
    </div>
  );
}
