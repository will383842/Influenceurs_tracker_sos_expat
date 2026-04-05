import React, { useEffect, useState, useCallback } from 'react';
import {
  fetchFichesStats, fetchFichesArticles, fetchFichesMissing,
  launchFicheGeneration,
  type FichesStats, type FicheArticle, type FichesMissingCountry,
} from '../../api/contentApi';
import { toast } from '../../components/Toast';

const STATUS_BADGE: Record<string, string> = {
  published: 'bg-success/20 text-success',
  draft:     'bg-amber/20 text-amber',
  review:    'bg-blue-500/20 text-blue-400',
};

const FICHE_LABELS: Record<string, { title: string; emoji: string }> = {
  general:       { title: 'Fiches Pays',              emoji: '🌍' },
  expatriation:  { title: 'Fiches Pays Expat',        emoji: '✈️' },
  vacances:      { title: 'Fiches Pays Vacances',     emoji: '🏖️' },
};

interface Props {
  type: 'general' | 'expatriation' | 'vacances';
}

export default function FichesPays({ type }: Props) {
  const [stats, setStats] = useState<FichesStats | null>(null);
  const [articles, setArticles] = useState<FicheArticle[]>([]);
  const [missing, setMissing] = useState<FichesMissingCountry[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [generating, setGenerating] = useState<string | null>(null);

  const label = FICHE_LABELS[type] || FICHE_LABELS.general;

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const [statsRes, articlesRes, missingRes] = await Promise.all([
        fetchFichesStats(type),
        fetchFichesArticles(type, page),
        fetchFichesMissing(type),
      ]);
      setStats(statsRes.data);
      setArticles(articlesRes.data.data);
      setLastPage(articlesRes.data.last_page);
      setTotal(articlesRes.data.total);
      setMissing(missingRes.data.countries);
    } catch {
      toast.error('Erreur chargement fiches');
    } finally {
      setLoading(false);
    }
  }, [type, page]);

  useEffect(() => { loadData(); }, [loadData]);

  const handleGenerate = async (countryCode: string, countryName: string) => {
    if (!confirm(`Generer la fiche ${type} pour ${countryName} (${countryCode}) ?`)) return;
    setGenerating(countryCode);
    try {
      await launchFicheGeneration(type, countryCode, true);
      toast.success(`Generation lancee pour ${countryName}`);
      // Remove from missing list
      setMissing(prev => prev.filter(c => c.code !== countryCode));
    } catch (e: any) {
      const msg = e?.response?.data?.message || 'Erreur generation';
      toast.error(msg);
    } finally {
      setGenerating(null);
    }
  };

  if (loading && !stats) {
    return <div className="flex items-center justify-center h-64 text-muted">Chargement...</div>;
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-bold text-foreground flex items-center gap-2">
          {label.emoji} {label.title}
        </h1>
        <button onClick={loadData} className="btn-ghost text-sm" disabled={loading}>
          Rafraichir
        </button>
      </div>

      {/* Stats */}
      {stats && (
        <div className="grid grid-cols-3 gap-4">
          <div className="card p-4">
            <div className="text-3xl font-bold text-foreground">{stats.covered}</div>
            <div className="text-sm text-muted">Pays couverts</div>
          </div>
          <div className="card p-4">
            <div className="text-3xl font-bold text-foreground">{stats.total}</div>
            <div className="text-sm text-muted">Total pays</div>
          </div>
          <div className="card p-4">
            <div className={`text-3xl font-bold ${stats.progress >= 80 ? 'text-success' : stats.progress >= 40 ? 'text-amber' : 'text-danger'}`}>
              {stats.progress}%
            </div>
            <div className="text-sm text-muted">Couverture</div>
            <div className="mt-2 w-full bg-surface rounded-full h-2">
              <div className="bg-primary h-2 rounded-full transition-all" style={{ width: `${stats.progress}%` }} />
            </div>
          </div>
        </div>
      )}

      {/* Articles table */}
      <div className="card overflow-hidden">
        <div className="px-4 py-3 border-b border-border flex items-center justify-between">
          <h2 className="text-sm font-bold text-foreground">Fiches existantes ({total})</h2>
        </div>
        {articles.length > 0 ? (
          <>
            <table className="w-full text-sm">
              <thead className="bg-surface">
                <tr>
                  <th className="px-4 py-2 text-left text-xs font-medium text-muted uppercase">Pays</th>
                  <th className="px-4 py-2 text-left text-xs font-medium text-muted uppercase">Titre</th>
                  <th className="px-4 py-2 text-left text-xs font-medium text-muted uppercase">Status</th>
                  <th className="px-4 py-2 text-left text-xs font-medium text-muted uppercase">Langues</th>
                  <th className="px-4 py-2 text-left text-xs font-medium text-muted uppercase">Mis a jour</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {articles.map(a => (
                  <tr key={a.id} className="hover:bg-surface/50">
                    <td className="px-4 py-2.5">
                      <div className="flex items-center gap-2">
                        <img
                          src={`/images/flags/${a.country_code.toLowerCase()}.webp`}
                          alt={a.country_code}
                          className="w-5 h-3.5 object-cover rounded-sm"
                          onError={(e) => { (e.target as HTMLImageElement).style.display = 'none'; }}
                        />
                        <span className="font-medium text-foreground">{a.country_name}</span>
                      </div>
                    </td>
                    <td className="px-4 py-2.5 text-muted max-w-sm truncate">{a.title}</td>
                    <td className="px-4 py-2.5">
                      <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_BADGE[a.status] || 'bg-muted/20 text-muted'}`}>
                        {a.status}
                      </span>
                    </td>
                    <td className="px-4 py-2.5 text-muted">{a.lang_count}/9</td>
                    <td className="px-4 py-2.5 text-muted text-xs">
                      {a.updated_at ? new Date(a.updated_at).toLocaleDateString('fr') : '-'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
            {/* Pagination */}
            {lastPage > 1 && (
              <div className="flex items-center justify-center gap-2 py-3 border-t border-border">
                <button
                  onClick={() => setPage(p => Math.max(1, p - 1))}
                  disabled={page <= 1}
                  className="btn-ghost text-xs"
                >
                  Precedent
                </button>
                <span className="text-xs text-muted">{page} / {lastPage}</span>
                <button
                  onClick={() => setPage(p => Math.min(lastPage, p + 1))}
                  disabled={page >= lastPage}
                  className="btn-ghost text-xs"
                >
                  Suivant
                </button>
              </div>
            )}
          </>
        ) : (
          <div className="px-4 py-8 text-center text-muted text-sm">Aucune fiche {type} generee.</div>
        )}
      </div>

      {/* Missing countries */}
      {missing.length > 0 && (
        <div className="card overflow-hidden">
          <div className="px-4 py-3 border-b border-border">
            <h2 className="text-sm font-bold text-foreground">
              Pays sans fiche {type} ({missing.length} restants)
            </h2>
          </div>
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2 p-4">
            {missing.map(c => (
              <button
                key={c.code}
                onClick={() => handleGenerate(c.code, c.name)}
                disabled={generating === c.code}
                className="flex items-center gap-2 p-2.5 rounded-lg border border-border hover:border-primary/50 hover:bg-primary/5 transition-colors text-left disabled:opacity-50"
              >
                <img
                  src={`/images/flags/${c.code.toLowerCase()}.webp`}
                  alt={c.code}
                  className="w-5 h-3.5 object-cover rounded-sm"
                  onError={(e) => { (e.target as HTMLImageElement).style.display = 'none'; }}
                />
                <span className="text-sm text-foreground truncate flex-1">{c.name}</span>
                {generating === c.code ? (
                  <span className="text-xs text-amber animate-pulse">...</span>
                ) : (
                  <span className="text-primary text-lg leading-none">+</span>
                )}
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
