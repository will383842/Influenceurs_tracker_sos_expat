import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { Link } from 'react-router-dom';
import api from '../../api/client';

interface ContentSource {
  id: number;
  name: string;
  slug: string;
  base_url: string;
  status: string;
  total_countries: number;
  total_articles: number;
  total_links: number;
  last_scraped_at: string | null;
  countries_count?: number;
  articles_count?: number;
  external_links_count?: number;
}

interface ContentStats {
  total_sources: number;
  total_countries: number;
  total_articles: number;
  total_links: number;
  total_words: number;
  affiliate_links: number;
  top_domains: { domain: string; count: number; total_occurrences: number }[];
  by_category: { category: string; count: number }[];
  link_types: { link_type: string; count: number }[];
}

export default function ContentHub() {
  const [sources, setSources] = useState<ContentSource[]>([]);
  const [stats, setStats] = useState<ContentStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [showAdd, setShowAdd] = useState(false);
  const [newName, setNewName] = useState('');
  const [newUrl, setNewUrl] = useState('');
  const [adding, setAdding] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [addError, setAddError] = useState<string | null>(null);

  const fetchData = async () => {
    try {
      const [srcRes, statsRes] = await Promise.all([
        api.get('/content/sources'),
        api.get('/content/stats'),
      ]);
      setSources(srcRes.data);
      setStats(statsRes.data);
    } catch (err) {
      setError('Impossible de charger les donnees');
      console.error('Failed to fetch content data', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchData(); }, []);

  // Auto-refresh while any source is scraping
  useEffect(() => {
    const hasActive = sources.some(s => s.status === 'scraping');
    if (!hasActive) return;
    const interval = setInterval(fetchData, 15000);
    return () => clearInterval(interval);
  }, [sources]);

  const handleAddSource = async (e: FormEvent) => {
    e.preventDefault();
    setAdding(true);
    try {
      await api.post('/content/sources', { name: newName, base_url: newUrl });
      setNewName('');
      setNewUrl('');
      setShowAdd(false);
      fetchData();
    } catch (err: any) {
      setAddError(err.response?.data?.message || 'Erreur lors de l\'ajout');
    } finally {
      setAdding(false);
    }
  };

  const handleScrape = async (slug: string) => {
    try {
      await api.post(`/content/sources/${slug}/scrape`);
      fetchData();
    } catch (err: any) {
      if (err.response?.status === 409) {
        setError('Scraping deja en cours pour cette source');
      }
    }
  };

  const statusBadge = (status: string) => {
    const colors: Record<string, string> = {
      pending: 'bg-gray-600 text-gray-300',
      scraping: 'bg-amber/20 text-amber',
      completed: 'bg-green-900/30 text-green-400',
    };
    return (
      <span className={`px-2 py-0.5 rounded text-xs font-medium ${colors[status] || 'bg-gray-600 text-gray-300'}`}>
        {status}
      </span>
    );
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="font-title text-2xl font-bold text-white">Content Engine</h1>
          <p className="text-muted text-sm mt-1">Scraping & analyse de contenu pour la generation d'articles</p>
        </div>
        <button
          onClick={() => setShowAdd(!showAdd)}
          className="px-4 py-2 bg-violet hover:bg-violet/80 text-white rounded-lg text-sm font-medium transition-colors"
        >
          + Ajouter une source
        </button>
      </div>

      {/* Error banner */}
      {error && (
        <div className="bg-red-900/20 border border-red-500/30 text-red-400 p-3 rounded-xl text-sm flex items-center justify-between">
          <span>{error}</span>
          <button onClick={() => setError(null)} className="text-red-300 hover:text-white ml-3">x</button>
        </div>
      )}

      {/* Add source form */}
      {showAdd && (
        <form onSubmit={handleAddSource} className="bg-surface border border-border rounded-xl p-4 flex gap-3 items-end">
          <div className="flex-1">
            <label className="text-xs text-muted block mb-1">Nom</label>
            <input
              type="text"
              value={newName}
              onChange={(e) => setNewName(e.target.value)}
              placeholder="Expat.com"
              className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm"
              required
            />
          </div>
          <div className="flex-1">
            <label className="text-xs text-muted block mb-1">URL de base</label>
            <input
              type="url"
              value={newUrl}
              onChange={(e) => setNewUrl(e.target.value)}
              placeholder="https://www.expat.com/fr/guide/"
              className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm"
              required
            />
          </div>
          <button
            type="submit"
            disabled={adding}
            className="px-4 py-2 bg-violet hover:bg-violet/80 text-white rounded-lg text-sm font-medium disabled:opacity-50"
          >
            {adding ? '...' : 'Ajouter'}
          </button>
          {addError && <p className="text-red-400 text-xs mt-1">{addError}</p>}
        </form>
      )}

      {/* KPIs */}
      {stats && (
        <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
          {[
            { label: 'Sources', value: stats.total_sources },
            { label: 'Pays', value: stats.total_countries },
            { label: 'Articles', value: stats.total_articles.toLocaleString() },
            { label: 'Liens externes', value: stats.total_links.toLocaleString() },
            { label: 'Mots scrapes', value: stats.total_words.toLocaleString() },
            { label: 'Liens affilies', value: stats.affiliate_links.toLocaleString() },
          ].map((kpi) => (
            <div key={kpi.label} className="bg-surface border border-border rounded-xl p-4 text-center">
              <div className="text-xl font-bold text-white">{kpi.value}</div>
              <div className="text-xs text-muted mt-1">{kpi.label}</div>
            </div>
          ))}
        </div>
      )}

      {/* Sources table */}
      <div className="bg-surface border border-border rounded-xl overflow-hidden">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-border text-left text-muted">
              <th className="px-4 py-3 font-medium">Source</th>
              <th className="px-4 py-3 font-medium">Status</th>
              <th className="px-4 py-3 font-medium text-right">Pays</th>
              <th className="px-4 py-3 font-medium text-right">Articles</th>
              <th className="px-4 py-3 font-medium text-right">Liens</th>
              <th className="px-4 py-3 font-medium">Dernier scraping</th>
              <th className="px-4 py-3 font-medium"></th>
            </tr>
          </thead>
          <tbody>
            {sources.length === 0 ? (
              <tr>
                <td colSpan={7} className="px-4 py-8 text-center text-muted">
                  Aucune source. Ajoutez-en une pour commencer.
                </td>
              </tr>
            ) : (
              sources.map((src) => (
                <tr key={src.id} className="border-b border-border/50 hover:bg-surface2 transition-colors">
                  <td className="px-4 py-3">
                    <Link to={`/content/${src.slug}`} className="text-violet-light hover:underline font-medium">
                      {src.name}
                    </Link>
                    <div className="text-xs text-muted">{src.base_url}</div>
                  </td>
                  <td className="px-4 py-3">{statusBadge(src.status)}</td>
                  <td className="px-4 py-3 text-right text-white">{src.total_countries}</td>
                  <td className="px-4 py-3 text-right text-white">{src.total_articles}</td>
                  <td className="px-4 py-3 text-right text-white">{src.total_links}</td>
                  <td className="px-4 py-3 text-muted text-xs">
                    {src.last_scraped_at ? new Date(src.last_scraped_at).toLocaleString('fr-FR') : '-'}
                  </td>
                  <td className="px-4 py-3">
                    <button
                      onClick={() => handleScrape(src.slug)}
                      disabled={src.status === 'scraping'}
                      className="px-3 py-1 bg-cyan/20 text-cyan rounded text-xs font-medium hover:bg-cyan/30 disabled:opacity-50 transition-colors"
                    >
                      {src.status === 'scraping' ? 'En cours...' : 'Scraper'}
                    </button>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {/* Top domains */}
      {stats && stats.top_domains.length > 0 && (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div className="bg-surface border border-border rounded-xl p-4">
            <h3 className="text-white font-medium mb-3">Top domaines externes</h3>
            <div className="space-y-2">
              {stats.top_domains.slice(0, 10).map((d) => (
                <div key={d.domain} className="flex items-center justify-between text-sm">
                  <span className="text-gray-300 truncate">{d.domain}</span>
                  <span className="text-muted ml-2">{d.total_occurrences}x</span>
                </div>
              ))}
            </div>
          </div>
          <div className="bg-surface border border-border rounded-xl p-4">
            <h3 className="text-white font-medium mb-3">Articles par categorie</h3>
            <div className="space-y-2">
              {stats.by_category.map((c) => (
                <div key={c.category} className="flex items-center justify-between text-sm">
                  <span className="text-gray-300 capitalize">{c.category}</span>
                  <span className="text-muted">{c.count}</span>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
