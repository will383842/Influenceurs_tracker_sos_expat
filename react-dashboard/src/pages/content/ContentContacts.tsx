import { useEffect, useState, useCallback, useRef } from 'react';
import api from '../../api/client';

interface Contact {
  id: number;
  name: string;
  role: string | null;
  email: string | null;
  phone: string | null;
  company: string | null;
  company_url: string | null;
  sector: string | null;
  country: string | null;
  city: string | null;
  source?: { id: number; name: string; slug: string };
}

export default function ContentContacts() {
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  const [filterSector, setFilterSector] = useState('');
  const [exporting, setExporting] = useState(false);

  const abortRef = useRef<AbortController | null>(null);

  useEffect(() => {
    const timer = setTimeout(() => { setSearch(searchInput); setPage(1); }, 400);
    return () => clearTimeout(timer);
  }, [searchInput]);

  const fetchContacts = useCallback(async () => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;
    setLoading(true);
    try {
      const params: Record<string, string> = { page: String(page), per_page: '50' };
      if (search) params.search = search;
      if (filterSector) params.sector = filterSector;
      const res = await api.get('/content-contacts', { params, signal: controller.signal });
      if (!controller.signal.aborted) {
        setContacts(res.data.data);
        setLastPage(res.data.last_page);
        setTotal(res.data.total);
      }
    } catch (err: unknown) {
      if (err instanceof Error && err.name === 'CanceledError') return;
    } finally {
      if (!controller.signal.aborted) setLoading(false);
    }
  }, [page, search, filterSector]);

  useEffect(() => { fetchContacts(); }, [fetchContacts]);

  const handleExport = async () => {
    setExporting(true);
    try {
      const params: Record<string, string> = {};
      if (filterSector) params.sector = filterSector;
      if (search) params.search = search;
      const res = await api.get('/content-contacts/export', { params, responseType: 'blob' });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const a = document.createElement('a');
      a.href = url;
      a.download = `contacts-${new Date().toISOString().slice(0, 10)}.csv`;
      a.click();
      window.URL.revokeObjectURL(url);
    } catch { /* */ }
    finally { setExporting(false); }
  };

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="font-title text-2xl font-bold text-white">Contacts</h1>
          <p className="text-muted text-sm mt-1">{total} contacts identifies</p>
        </div>
        <button onClick={handleExport} disabled={exporting}
          className="px-4 py-2 bg-surface border border-border text-white rounded-lg text-sm hover:bg-surface2 disabled:opacity-50">
          {exporting ? 'Export...' : 'Export CSV'}
        </button>
      </div>

      <div className="flex flex-wrap gap-3 items-center">
        <input type="text" value={searchInput} onChange={(e) => setSearchInput(e.target.value)}
          placeholder="Rechercher nom, email, entreprise..."
          className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm w-64" />
        <select value={filterSector} onChange={(e) => { setFilterSector(e.target.value); setPage(1); }}
          className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm">
          <option value="">Tous secteurs</option>
          {['media', 'assurance', 'education', 'emploi', 'sante', 'fiscalite', 'social'].map((s) => (
            <option key={s} value={s}>{s}</option>
          ))}
        </select>
      </div>

      <div className="bg-surface border border-border rounded-xl overflow-x-auto">
        {loading ? (
          <div className="flex items-center justify-center h-32" role="status">
            <div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" />
          </div>
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-left text-muted">
                <th className="px-4 py-3 font-medium">Nom</th>
                <th className="px-4 py-3 font-medium">Role</th>
                <th className="px-4 py-3 font-medium">Email</th>
                <th className="px-4 py-3 font-medium">Telephone</th>
                <th className="px-4 py-3 font-medium">Entreprise</th>
                <th className="px-4 py-3 font-medium">Secteur</th>
                <th className="px-4 py-3 font-medium">Source</th>
              </tr>
            </thead>
            <tbody>
              {contacts.length === 0 ? (
                <tr><td colSpan={7} className="px-4 py-8 text-center text-muted">Aucun contact</td></tr>
              ) : contacts.map((c) => (
                <tr key={c.id} className="border-b border-border/50 hover:bg-surface2 transition-colors">
                  <td className="px-4 py-2 text-white font-medium">{c.name}</td>
                  <td className="px-4 py-2 text-gray-400 text-xs">{c.role || '-'}</td>
                  <td className="px-4 py-2">
                    {c.email ? <a href={`mailto:${c.email}`} className="text-green-400 text-xs hover:underline">{c.email}</a> : <span className="text-muted/30 text-xs">-</span>}
                  </td>
                  <td className="px-4 py-2">
                    {c.phone ? <a href={`tel:${c.phone}`} className="text-cyan text-xs hover:underline">{c.phone}</a> : <span className="text-muted/30 text-xs">-</span>}
                  </td>
                  <td className="px-4 py-2 text-xs">
                    {c.company_url ? (
                      <a href={c.company_url} target="_blank" rel="noopener noreferrer" className="text-violet-light hover:underline">{c.company}</a>
                    ) : <span className="text-gray-400">{c.company || '-'}</span>}
                  </td>
                  <td className="px-4 py-2">
                    {c.sector && <span className="px-2 py-0.5 bg-surface2 rounded text-xs text-gray-300">{c.sector}</span>}
                  </td>
                  <td className="px-4 py-2 text-muted text-xs">{c.source?.name || '-'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {lastPage > 1 && (
        <div className="flex items-center justify-center gap-2">
          <button onClick={() => setPage(Math.max(1, page - 1))} disabled={page === 1}
            className="px-3 py-1 bg-surface border border-border rounded text-sm text-white disabled:opacity-30">Prec.</button>
          <span className="text-sm text-muted">Page {page} / {lastPage}</span>
          <button onClick={() => setPage(Math.min(lastPage, page + 1))} disabled={page === lastPage}
            className="px-3 py-1 bg-surface border border-border rounded text-sm text-white disabled:opacity-30">Suiv.</button>
        </div>
      )}
    </div>
  );
}
