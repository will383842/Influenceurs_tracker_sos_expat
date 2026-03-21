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

export default function AdminScraper() {
  const [config, setConfig] = useState<ScraperConfig | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [success, setSuccess] = useState('');

  const load = async () => {
    try {
      const { data } = await api.get('/settings/scraper');
      setConfig(data);
    } catch { /* ignore */ }
    finally { setLoading(false); }
  };

  useEffect(() => { load(); }, []);

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
