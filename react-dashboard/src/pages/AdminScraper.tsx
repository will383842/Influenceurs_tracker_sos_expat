import React, { useEffect, useState } from 'react';
import api from '../api/client';

interface ScraperType {
  value: string;
  label: string;
  icon: string;
  scraper_enabled: boolean;
}

interface ScraperConfig {
  global_enabled: boolean;
  types: ScraperType[];
}

interface ScraperRun {
  id: number;
  scraper_name: string;
  status: 'ok' | 'skipped_no_ia' | 'rate_limited' | 'circuit_broken' | 'error' | 'running';
  country: string | null;
  contacts_found: number;
  contacts_new: number;
  started_at: string;
  ended_at: string | null;
  error_message: string | null;
  requires_perplexity: boolean;
}

interface Stat24h { status: string; count: number; contacts_new: number; }

interface ScraperStatus {
  latest_runs: ScraperRun[];
  stats_24h: Stat24h[];
  rotation_state: Array<{ scraper_name: string; last_country: string | null; last_ran_at: string | null; }>;
  circuit_breakers: Record<string, number>;
  generated_at: string;
}

const STATUS_BADGE: Record<string, { label: string; cls: string }> = {
  ok:             { label: 'OK',          cls: 'bg-emerald-500/20 text-emerald-400' },
  running:        { label: 'En cours',    cls: 'bg-blue-500/20 text-blue-400' },
  skipped_no_ia:  { label: 'Skip (IA KO)',cls: 'bg-amber/20 text-amber' },
  rate_limited:   { label: 'Rate-limited',cls: 'bg-orange-500/20 text-orange-400' },
  circuit_broken: { label: 'Circuit coupé', cls: 'bg-rose-500/20 text-rose-400' },
  error:          { label: 'Erreur',      cls: 'bg-red-500/20 text-red-400' },
};

export default function AdminScraper() {
  const [config, setConfig] = useState<ScraperConfig | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [success, setSuccess] = useState('');
  const [status, setStatus] = useState<ScraperStatus | null>(null);
  const [runs, setRuns] = useState<ScraperRun[]>([]);

  const load = async () => {
    try {
      const { data } = await api.get('/settings/scraper');
      setConfig(data);
    } catch { /* ignore */ }
    finally { setLoading(false); }
  };

  const loadStatus = async () => {
    try {
      const [{ data: s }, { data: r }] = await Promise.all([
        api.get<ScraperStatus>('/scrapers/status'),
        api.get<{ runs: ScraperRun[] }>('/scrapers/runs?limit=30'),
      ]);
      setStatus(s);
      setRuns(r.runs);
    } catch { /* ignore — table may not exist yet */ }
  };

  useEffect(() => { load(); }, []);
  useEffect(() => {
    loadStatus();
    const id = setInterval(loadStatus, 30_000);
    return () => clearInterval(id);
  }, []);

  const toggleGlobal = async () => {
    if (!config) return;
    setSaving(true);
    setSuccess('');
    try {
      const { data } = await api.put('/settings/scraper', {
        global_enabled: !config.global_enabled,
      });
      setConfig(data);
      setSuccess(data.global_enabled ? 'Scraper activé' : 'Scraper désactivé');
    } catch { /* ignore */ }
    finally { setSaving(false); }
  };

  const toggleType = async (typeValue: string, currentState: boolean) => {
    if (!config) return;
    setSaving(true);
    setSuccess('');
    try {
      const { data } = await api.put('/settings/scraper', {
        types: { [typeValue]: !currentState },
      });
      setConfig(data);
    } catch { /* ignore */ }
    finally { setSaving(false); }
  };

  if (loading || !config) return (
    <div className="flex items-center justify-center h-32">
      <div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" />
    </div>
  );

  const enabledCount = config.types.filter(t => t.scraper_enabled).length;
  const disabledCount = config.types.length - enabledCount;

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div>
        <h2 className="font-title text-2xl font-bold text-white">🕷️ Configuration Scraper</h2>
        <p className="text-muted text-sm mt-1">
          Le scraper visite les sites web des contacts pour extraire emails et téléphones automatiquement.
        </p>
      </div>

      {success && (
        <div className="bg-emerald-500/10 border border-emerald-500/20 rounded-lg p-3 text-emerald-400 text-sm">{success}</div>
      )}

      {/* Global toggle */}
      <div className={`bg-surface border rounded-xl p-5 ${config.global_enabled ? 'border-emerald-500/30' : 'border-border'}`}>
        <div className="flex items-center justify-between">
          <div>
            <h3 className="font-title font-semibold text-white text-lg">
              {config.global_enabled ? '🟢 Scraper ACTIF' : '🔴 Scraper DÉSACTIVÉ'}
            </h3>
            <p className="text-xs text-muted mt-1">
              {config.global_enabled
                ? `Le scraper analyse automatiquement les sites web des nouveaux contacts (${enabledCount} types activés)`
                : 'Le scraper est en pause — aucun site ne sera visité'
              }
            </p>
          </div>
          <button onClick={toggleGlobal} disabled={saving}
            style={{ width: 56, height: 28, borderRadius: 14, backgroundColor: config.global_enabled ? '#10b981' : '#4b5563', position: 'relative', border: 'none', cursor: 'pointer', transition: 'background-color 0.2s' }}>
            <span style={{ position: 'absolute', top: 2, left: config.global_enabled ? 30 : 2, width: 24, height: 24, borderRadius: 12, backgroundColor: 'white', transition: 'left 0.2s', boxShadow: '0 1px 3px rgba(0,0,0,0.3)' }} />
          </button>
        </div>
      </div>

      {/* Info box */}
      <div className="bg-amber/5 border border-amber/20 rounded-xl p-4 text-sm text-amber">
        <p className="font-semibold">Comment ça marche :</p>
        <ul className="mt-2 space-y-1 text-xs text-amber/80">
          <li>1. La recherche IA importe des contacts avec des URLs de sites web</li>
          <li>2. Le scraper visite chaque site et cherche les pages Contact, About, footer</li>
          <li>3. Il extrait les emails et téléphones trouvés</li>
          <li>4. Il met à jour automatiquement la fiche contact</li>
          <li className="text-amber font-medium mt-2">⚠️ Désactive le scraper pour YouTube, TikTok, Instagram — ces sites bloquent le scraping</li>
        </ul>
      </div>

{/* KPIs 24h + circuit breakers */}
      {status && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          {['ok', 'skipped_no_ia', 'rate_limited', 'error'].map(st => {
            const s = status.stats_24h.find(x => x.status === st);
            const meta = STATUS_BADGE[st] || { label: st, cls: '' };
            return (
              <div key={st} className="bg-surface border border-border rounded-xl p-3">
                <div className={`text-xs ${meta.cls} inline-block px-2 py-0.5 rounded-full`}>{meta.label}</div>
                <div className="text-2xl font-bold text-white mt-1">{s?.count ?? 0}</div>
                {st === 'ok' && s && (
                  <div className="text-[11px] text-muted">+{s.contacts_new} contacts</div>
                )}
              </div>
            );
          })}
        </div>
      )}

      {status && Object.keys(status.circuit_breakers).length > 0 && (
        <div className="bg-rose-500/5 border border-rose-500/20 rounded-xl p-3 text-sm text-rose-300">
          <span className="font-semibold">🛑 Circuit coupé :</span>{' '}
          {Object.keys(status.circuit_breakers).slice(0, 8).join(', ')}
        </div>
      )}

      {/* Historique runs */}
      {runs.length > 0 && (
        <div className="bg-surface border border-border rounded-xl overflow-hidden">
          <div className="p-4 border-b border-border">
            <h3 className="font-title font-semibold text-white">Historique des runs</h3>
            <p className="text-[10px] text-muted mt-0.5">
              Rafraîchi toutes les 30s · {runs.length} derniers runs
            </p>
          </div>
          <div className="overflow-x-auto max-h-96">
            <table className="w-full text-xs">
              <thead className="bg-surface2 sticky top-0">
                <tr className="text-left text-muted">
                  <th className="p-2 font-medium">Date</th>
                  <th className="p-2 font-medium">Scraper</th>
                  <th className="p-2 font-medium">Pays</th>
                  <th className="p-2 font-medium">Statut</th>
                  <th className="p-2 font-medium text-right">Nouveaux</th>
                  <th className="p-2 font-medium">Détails</th>
                </tr>
              </thead>
              <tbody>
                {runs.map(r => {
                  const badge = STATUS_BADGE[r.status] || { label: r.status, cls: 'bg-gray-600/20 text-gray-300' };
                  return (
                    <tr key={r.id} className="border-t border-border/40 hover:bg-white/5">
                      <td className="p-2 text-muted whitespace-nowrap">
                        {new Date(r.started_at).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}
                      </td>
                      <td className="p-2 text-white font-mono">{r.scraper_name}</td>
                      <td className="p-2 text-muted">{r.country ?? '—'}</td>
                      <td className="p-2">
                        <span className={`text-[10px] px-2 py-0.5 rounded-full ${badge.cls}`}>{badge.label}</span>
                      </td>
                      <td className="p-2 text-right text-white">{r.contacts_new}</td>
                      <td className="p-2 text-muted text-[11px] truncate max-w-xs" title={r.error_message || ''}>
                        {r.error_message ?? '—'}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Per-type toggles */}
      <div className="bg-surface border border-border rounded-xl overflow-hidden">
        <div className="p-4 border-b border-border">
          <h3 className="font-title font-semibold text-white">Scraper par type de contact</h3>
          <p className="text-[10px] text-muted mt-0.5">
            {enabledCount} activés • {disabledCount} désactivés
          </p>
        </div>
        <table className="w-full text-sm">
          <tbody>
            {config.types.map(type => (
              <tr key={type.value} className="border-b border-border/50">
                <td className="p-3">
                  <span className="text-lg mr-2">{type.icon}</span>
                  <span className="text-white font-medium">{type.label}</span>
                  <span className="text-xs text-muted ml-2 font-mono">({type.value})</span>
                </td>
                <td className="p-3 text-right">
                  <button
                    onClick={() => toggleType(type.value, type.scraper_enabled)}
                    disabled={saving}
                    style={{ width: 44, height: 24, borderRadius: 12, backgroundColor: type.scraper_enabled ? '#10b981' : '#4b5563', position: 'relative', border: 'none', cursor: 'pointer', transition: 'background-color 0.2s' }}>
                    <span style={{ position: 'absolute', top: 2, left: type.scraper_enabled ? 22 : 2, width: 20, height: 20, borderRadius: 10, backgroundColor: 'white', transition: 'left 0.2s', boxShadow: '0 1px 2px rgba(0,0,0,0.3)' }} />
                  </button>
                </td>
                <td className="p-3 w-24">
                  {type.scraper_enabled ? (
                    <span className="text-[10px] bg-emerald-500/20 text-emerald-400 px-2 py-0.5 rounded-full">Actif</span>
                  ) : (
                    <span className="text-[10px] bg-gray-600/20 text-gray-500 px-2 py-0.5 rounded-full">Off</span>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
