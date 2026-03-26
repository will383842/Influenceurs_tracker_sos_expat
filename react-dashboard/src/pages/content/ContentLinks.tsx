import { useEffect, useState, useCallback, useRef } from 'react';
import api from '../../api/client';

interface ExternalLink {
  id: number;
  url: string;
  original_url: string;
  domain: string;
  anchor_text: string | null;
  context: string | null;
  link_type: string;
  is_affiliate: boolean;
  occurrences: number;
  source?: { id: number; name: string; slug: string };
  country?: { id: number; name: string; slug: string } | null;
}

interface Source {
  id: number;
  name: string;
  slug: string;
}

export default function ContentLinks() {
  const [links, setLinks] = useState<ExternalLink[]>([]);
  const [sources, setSources] = useState<Source[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);

  // Filters
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  const [filterSource, setFilterSource] = useState('');
  const [filterType, setFilterType] = useState('');
  const [filterAffiliate, setFilterAffiliate] = useState('');
  const [exporting, setExporting] = useState(false);

  const abortRef = useRef<AbortController | null>(null);

  // Debounce search input
  useEffect(() => {
    const timer = setTimeout(() => {
      setSearch(searchInput);
      setPage(1);
    }, 400);
    return () => clearTimeout(timer);
  }, [searchInput]);

  const fetchLinks = useCallback(async () => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    setLoading(true);
    setError(null);
    try {
      const params: Record<string, string> = { page: String(page), per_page: '50' };
      if (search) params.search = search;
      if (filterSource) params.source = filterSource;
      if (filterType) params.link_type = filterType;
      if (filterAffiliate) params.is_affiliate = filterAffiliate;

      const res = await api.get('/content/external-links', { params, signal: controller.signal });
      if (!controller.signal.aborted) {
        setLinks(res.data.data);
        setLastPage(res.data.last_page);
        setTotal(res.data.total);
      }
    } catch (err: unknown) {
      if (err instanceof Error && err.name === 'CanceledError') return;
      if (!abortRef.current?.signal.aborted) {
        setError('Erreur lors du chargement des liens');
        console.error('Failed to fetch links', err);
      }
    } finally {
      if (!controller.signal.aborted) setLoading(false);
    }
  }, [page, search, filterSource, filterType, filterAffiliate]);

  useEffect(() => { fetchLinks(); }, [fetchLinks]);

  useEffect(() => {
    const controller = new AbortController();
    api.get('/content/sources', { signal: controller.signal })
      .then(res => setSources(res.data))
      .catch(() => {});
    return () => controller.abort();
  }, []);

  const resetFilters = () => {
    setSearchInput('');
    setSearch('');
    setFilterSource('');
    setFilterType('');
    setFilterAffiliate('');
    setPage(1);
  };

  const handleExport = async () => {
    setExporting(true);
    try {
      const params: Record<string, string> = {};
      if (filterSource) params.source = filterSource;
      if (search) params.search = search;
      if (filterType) params.link_type = filterType;
      if (filterAffiliate) params.is_affiliate = filterAffiliate;

      const res = await api.get('/content/external-links/export', {
        params,
        responseType: 'blob',
      });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const a = document.createElement('a');
      a.href = url;
      a.download = `content-links-${new Date().toISOString().slice(0, 10)}.csv`;
      a.click();
      window.URL.revokeObjectURL(url);
    } catch {
      setError('Erreur lors de l\'export');
    } finally {
      setExporting(false);
    }
  };

  const typeBadge = (type: string) => {
    const colors: Record<string, string> = {
      official: 'bg-blue-900/30 text-blue-400',
      service: 'bg-violet/20 text-violet-light',
      resource: 'bg-green-900/30 text-green-400',
      news: 'bg-amber/20 text-amber',
      other: 'bg-gray-700 text-gray-400',
    };
    return (
      <span className={`px-2 py-0.5 rounded text-xs ${colors[type] || colors.other}`}>{type}</span>
    );
  };

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="font-title text-2xl font-bold text-white">Liens Externes</h1>
          <p className="text-muted text-sm mt-1">{total.toLocaleString()} liens dedupliques</p>
        </div>
        <button
          onClick={handleExport}
          disabled={exporting}
          className="px-4 py-2 bg-surface border border-border text-white rounded-lg text-sm hover:bg-surface2 transition-colors disabled:opacity-50"
        >
          {exporting ? 'Export...' : 'Export CSV'}
        </button>
      </div>

      {error && (
        <div className="bg-red-900/20 border border-red-500/30 text-red-400 p-3 rounded-xl text-sm flex items-center justify-between">
          <span>{error}</span>
          <button onClick={fetchLinks} className="text-red-300 hover:text-white ml-3 text-xs underline">Reessayer</button>
        </div>
      )}

      {/* Filters */}
      <div className="flex flex-wrap gap-3 items-center">
        <input
          type="text"
          value={searchInput}
          onChange={(e) => setSearchInput(e.target.value)}
          placeholder="Rechercher URL, domaine, anchor..."
          className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm w-64"
        />
        <select
          value={filterSource}
          onChange={(e) => { setFilterSource(e.target.value); setPage(1); }}
          className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm"
        >
          <option value="">Toutes sources</option>
          {sources.map((s) => (
            <option key={s.slug} value={s.slug}>{s.name}</option>
          ))}
        </select>
        <select
          value={filterType}
          onChange={(e) => { setFilterType(e.target.value); setPage(1); }}
          className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm"
        >
          <option value="">Tous types</option>
          {['official', 'service', 'resource', 'news', 'other'].map((t) => (
            <option key={t} value={t}>{t}</option>
          ))}
        </select>
        <select
          value={filterAffiliate}
          onChange={(e) => { setFilterAffiliate(e.target.value); setPage(1); }}
          className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm"
        >
          <option value="">Affilies?</option>
          <option value="1">Affilies seulement</option>
          <option value="0">Non-affilies</option>
        </select>
        {(searchInput || filterSource || filterType || filterAffiliate) && (
          <button onClick={resetFilters} className="text-xs text-muted hover:text-white">
            Reinitialiser
          </button>
        )}
      </div>

      {/* Table */}
      <div className="bg-surface border border-border rounded-xl overflow-x-auto">
        {loading ? (
          <div className="flex items-center justify-center h-32" role="status" aria-label="Chargement">
            <div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" />
          </div>
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-left text-muted">
                <th className="px-4 py-3 font-medium">URL</th>
                <th className="px-4 py-3 font-medium">Domaine</th>
                <th className="px-4 py-3 font-medium">Anchor</th>
                <th className="px-4 py-3 font-medium">Source</th>
                <th className="px-4 py-3 font-medium">Pays</th>
                <th className="px-4 py-3 font-medium">Type</th>
                <th className="px-4 py-3 font-medium text-center">Aff.</th>
                <th className="px-4 py-3 font-medium text-right">Occ.</th>
              </tr>
            </thead>
            <tbody>
              {links.length === 0 ? (
                <tr>
                  <td colSpan={8} className="px-4 py-8 text-center text-muted">Aucun lien trouve</td>
                </tr>
              ) : (
                links.map((link) => (
                  <tr key={link.id} className="border-b border-border/50 hover:bg-surface2 transition-colors">
                    <td className="px-4 py-2 max-w-xs">
                      <a
                        href={link.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-cyan hover:underline truncate block"
                        title={link.url}
                      >
                        {link.url.length > 60 ? link.url.slice(0, 60) + '...' : link.url}
                      </a>
                    </td>
                    <td className="px-4 py-2 text-gray-300">{link.domain}</td>
                    <td className="px-4 py-2 text-gray-400 max-w-[200px] truncate">{link.anchor_text || '-'}</td>
                    <td className="px-4 py-2 text-muted">{link.source?.name || '-'}</td>
                    <td className="px-4 py-2 text-muted">{link.country?.name || '-'}</td>
                    <td className="px-4 py-2">{typeBadge(link.link_type)}</td>
                    <td className="px-4 py-2 text-center">
                      {link.is_affiliate && <span className="text-amber text-xs font-bold">AFF</span>}
                    </td>
                    <td className="px-4 py-2 text-right text-white">{link.occurrences}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        )}
      </div>

      {/* Pagination */}
      {lastPage > 1 && (
        <div className="flex items-center justify-center gap-2">
          <button
            onClick={() => setPage(Math.max(1, page - 1))}
            disabled={page === 1}
            className="px-3 py-1 bg-surface border border-border rounded text-sm text-white disabled:opacity-30"
            aria-label="Page precedente"
          >
            Prec.
          </button>
          <span className="text-sm text-muted">
            Page {page} / {lastPage}
          </span>
          <button
            onClick={() => setPage(Math.min(lastPage, page + 1))}
            disabled={page === lastPage}
            className="px-3 py-1 bg-surface border border-border rounded text-sm text-white disabled:opacity-30"
            aria-label="Page suivante"
          >
            Suiv.
          </button>
        </div>
      )}
    </div>
  );
}
