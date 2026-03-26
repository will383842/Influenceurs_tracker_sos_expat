import React, { useEffect, useState, useCallback } from 'react';
import {
  fetchKeywords,
  fetchKeywordGaps,
  fetchKeywordCannibalization,
} from '../../api/contentApi';
import type {
  KeywordTracking,
  KeywordGap,
  KeywordCannibalization,
  PaginatedResponse,
  KeywordType,
} from '../../types/content';
import { toast } from '../../components/Toast';
import { errMsg } from './helpers';

const inputClass = 'bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors';

const TABS = ['Tous', 'Longue traine', 'Cannibalization', 'Gaps'] as const;
type Tab = typeof TABS[number];

const TYPE_COLORS: Record<KeywordType, string> = {
  primary: 'bg-violet/20 text-violet-light',
  secondary: 'bg-blue-500/20 text-blue-400',
  long_tail: 'bg-amber/20 text-amber',
  lsi: 'bg-success/20 text-success',
  paa: 'bg-pink-500/20 text-pink-400',
  semantic: 'bg-cyan-500/20 text-cyan-400',
};

const SEVERITY_COLORS: Record<string, string> = {
  high: 'bg-danger/20 text-danger',
  medium: 'bg-amber/20 text-amber',
  low: 'bg-muted/20 text-muted',
};

const TREND_ICONS: Record<string, string> = {
  rising: '\u2191',
  stable: '\u2192',
  declining: '\u2193',
};

export default function KeywordTracker() {
  const [tab, setTab] = useState<Tab>('Tous');
  const [keywords, setKeywords] = useState<KeywordTracking[]>([]);
  const [gaps, setGaps] = useState<KeywordGap[]>([]);
  const [cannibs, setCannibs] = useState<KeywordCannibalization[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [filterType, setFilterType] = useState('');
  const [filterLang, setFilterLang] = useState('');
  const [searchQ, setSearchQ] = useState('');
  const [sortCol, setSortCol] = useState<string>('keyword');
  const [sortDir, setSortDir] = useState<'asc' | 'desc'>('asc');

  const loadKeywords = useCallback(async (page = 1) => {
    setLoading(true);
    setError(null);
    try {
      const params: Record<string, string | number> = { page };
      if (filterType) params.type = filterType;
      if (filterLang) params.language = filterLang;
      if (searchQ) params.search = searchQ;
      const res = await fetchKeywords(params);
      const data = res.data as unknown as PaginatedResponse<KeywordTracking>;
      setKeywords(data.data);
      setPagination({ current_page: data.current_page, last_page: data.last_page, total: data.total });
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Erreur de chargement');
    } finally {
      setLoading(false);
    }
  }, [filterType, filterLang, searchQ]);

  const loadGaps = useCallback(async () => {
    try {
      const res = await fetchKeywordGaps({});
      setGaps((res.data as unknown as KeywordGap[]) ?? []);
    } catch (err) { toast('error', errMsg(err)); }
  }, []);

  const loadCannibs = useCallback(async () => {
    try {
      const res = await fetchKeywordCannibalization();
      setCannibs((res.data as unknown as KeywordCannibalization[]) ?? []);
    } catch (err) { toast('error', errMsg(err)); }
  }, []);

  useEffect(() => { loadKeywords(1); }, [loadKeywords]);
  useEffect(() => { loadGaps(); loadCannibs(); }, [loadGaps, loadCannibs]);

  const handleSort = (col: string) => {
    if (sortCol === col) setSortDir(d => d === 'asc' ? 'desc' : 'asc');
    else { setSortCol(col); setSortDir('asc'); }
  };

  const sortedKeywords = [...keywords].sort((a, b) => {
    const dir = sortDir === 'asc' ? 1 : -1;
    const av = (a as unknown as Record<string, unknown>)[sortCol];
    const bv = (b as unknown as Record<string, unknown>)[sortCol];
    if (typeof av === 'string' && typeof bv === 'string') return av.localeCompare(bv) * dir;
    return ((av as number ?? 0) - (bv as number ?? 0)) * dir;
  });

  const longTailKeywords = sortedKeywords.filter(k => k.type === 'long_tail');

  // Stats
  const totalKw = pagination.total;
  const byType = keywords.reduce<Record<string, number>>((acc, k) => { acc[k.type] = (acc[k.type] || 0) + 1; return acc; }, {});
  const byLang = keywords.reduce<Record<string, number>>((acc, k) => { acc[k.language] = (acc[k.language] || 0) + 1; return acc; }, {});

  const SortHeader = ({ col, label }: { col: string; label: string }) => (
    <th
      className="pb-3 pr-4 cursor-pointer select-none"
      onClick={() => handleSort(col)}
    >
      {label} {sortCol === col ? (sortDir === 'asc' ? '\u25B2' : '\u25BC') : ''}
    </th>
  );

  const renderKeywordsTable = (data: KeywordTracking[]) => (
    <div className="overflow-x-auto">
      <table className="w-full text-sm">
        <thead>
          <tr className="text-left text-xs text-muted uppercase tracking-wide border-b border-border">
            <SortHeader col="keyword" label="Mot-cle" />
            <th className="pb-3 pr-4">Type</th>
            <th className="pb-3 pr-4">Langue</th>
            <th className="pb-3 pr-4">Pays</th>
            <SortHeader col="articles_using_count" label="Articles" />
            <SortHeader col="search_volume_estimate" label="Volume est." />
            <SortHeader col="difficulty_estimate" label="Difficulte" />
            <th className="pb-3">Tendance</th>
          </tr>
        </thead>
        <tbody>
          {data.map(kw => (
            <tr key={kw.id} className="border-b border-border/50 hover:bg-surface2/50 transition-colors">
              <td className="py-3 pr-4 text-white font-medium">{kw.keyword}</td>
              <td className="py-3 pr-4">
                <span className={`inline-block px-1.5 py-0.5 rounded text-[10px] font-medium ${TYPE_COLORS[kw.type] ?? 'bg-muted/20 text-muted'}`}>
                  {kw.type}
                </span>
              </td>
              <td className="py-3 pr-4 text-muted uppercase text-xs">{kw.language}</td>
              <td className="py-3 pr-4 text-muted capitalize">{kw.country ?? '-'}</td>
              <td className="py-3 pr-4 text-white">{kw.articles_using_count}</td>
              <td className="py-3 pr-4 text-white">{kw.search_volume_estimate ?? '-'}</td>
              <td className="py-3 pr-4">
                {kw.difficulty_estimate != null ? (
                  <div className="flex items-center gap-2">
                    <div className="w-12 h-1.5 bg-surface2 rounded-full overflow-hidden">
                      <div className="h-full bg-amber rounded-full" style={{ width: `${kw.difficulty_estimate}%` }} />
                    </div>
                    <span className="text-xs text-muted">{kw.difficulty_estimate}</span>
                  </div>
                ) : <span className="text-muted">-</span>}
              </td>
              <td className="py-3">
                {kw.trend ? (
                  <span className={kw.trend === 'rising' ? 'text-success' : kw.trend === 'declining' ? 'text-danger' : 'text-muted'}>
                    {TREND_ICONS[kw.trend] ?? ''} {kw.trend}
                  </span>
                ) : <span className="text-muted">-</span>}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
      {data.length === 0 && <p className="text-center py-8 text-muted text-sm">Aucun mot-cle trouve</p>}
    </div>
  );

  return (
    <div className="p-4 md:p-6 space-y-6">
      <h2 className="font-title text-2xl font-bold text-white">Suivi mots-cles</h2>

      {/* Stats */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div className="bg-surface border border-border rounded-xl p-5">
          <span className="text-xs text-muted uppercase tracking-wide">Total mots-cles</span>
          <p className="text-2xl font-bold text-white mt-2">{totalKw}</p>
        </div>
        {Object.entries(byType).slice(0, 3).map(([type, count]) => (
          <div key={type} className="bg-surface border border-border rounded-xl p-5">
            <span className="text-xs text-muted uppercase tracking-wide">{type}</span>
            <p className="text-2xl font-bold text-white mt-2">{count}</p>
          </div>
        ))}
      </div>

      {/* Tabs */}
      <div className="flex gap-1 border-b border-border">
        {TABS.map(t => (
          <button
            key={t}
            onClick={() => setTab(t)}
            className={`px-4 py-2 text-sm font-medium transition-colors border-b-2 ${
              tab === t ? 'border-violet text-white' : 'border-transparent text-muted hover:text-white'
            }`}
          >
            {t}
          </button>
        ))}
      </div>

      {error && <p className="text-danger text-sm">{error}</p>}

      {/* Tab: Tous */}
      {tab === 'Tous' && (
        <div className="bg-surface border border-border rounded-xl p-5 space-y-4">
          <div className="flex items-center gap-3 flex-wrap">
            <input
              type="text"
              placeholder="Rechercher..."
              value={searchQ}
              onChange={e => setSearchQ(e.target.value)}
              className={inputClass}
            />
            <select value={filterType} onChange={e => setFilterType(e.target.value)} className={inputClass}>
              <option value="">Tous les types</option>
              <option value="primary">Primary</option>
              <option value="secondary">Secondary</option>
              <option value="long_tail">Long tail</option>
              <option value="lsi">LSI</option>
              <option value="paa">PAA</option>
              <option value="semantic">Semantic</option>
            </select>
            <select value={filterLang} onChange={e => setFilterLang(e.target.value)} className={inputClass}>
              <option value="">Toutes les langues</option>
              {Object.keys(byLang).map(l => <option key={l} value={l}>{l.toUpperCase()}</option>)}
            </select>
          </div>

          {loading ? (
            <div className="space-y-3">{[1, 2, 3, 4, 5].map(i => <div key={i} className="animate-pulse bg-surface2 rounded-lg h-12" />)}</div>
          ) : (
            renderKeywordsTable(sortedKeywords)
          )}

          {pagination.last_page > 1 && (
            <div className="flex items-center justify-between pt-4 border-t border-border">
              <span className="text-xs text-muted">{pagination.total} mots-cles</span>
              <div className="flex gap-2">
                <button
                  onClick={() => loadKeywords(pagination.current_page - 1)}
                  disabled={pagination.current_page <= 1}
                  className="px-3 py-1 text-xs bg-surface2 text-muted hover:text-white rounded-lg disabled:opacity-40"
                >
                  Precedent
                </button>
                <span className="px-3 py-1 text-xs text-muted">{pagination.current_page} / {pagination.last_page}</span>
                <button
                  onClick={() => loadKeywords(pagination.current_page + 1)}
                  disabled={pagination.current_page >= pagination.last_page}
                  className="px-3 py-1 text-xs bg-surface2 text-muted hover:text-white rounded-lg disabled:opacity-40"
                >
                  Suivant
                </button>
              </div>
            </div>
          )}
        </div>
      )}

      {/* Tab: Longue traine */}
      {tab === 'Longue traine' && (
        <div className="bg-surface border border-border rounded-xl p-5">
          {loading ? (
            <div className="space-y-3">{[1, 2, 3].map(i => <div key={i} className="animate-pulse bg-surface2 rounded-lg h-12" />)}</div>
          ) : (
            renderKeywordsTable(longTailKeywords)
          )}
        </div>
      )}

      {/* Tab: Cannibalization */}
      {tab === 'Cannibalization' && (
        <div className="space-y-4">
          {cannibs.length === 0 ? (
            <div className="bg-surface border border-border rounded-xl p-8 text-center">
              <p className="text-muted text-sm">Aucune cannibalisation detectee</p>
            </div>
          ) : (
            cannibs.map((c, idx) => (
              <div key={idx} className="bg-surface border border-border rounded-xl p-5">
                <div className="flex items-center justify-between mb-3">
                  <h4 className="text-white font-medium">{c.keyword}</h4>
                  <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${SEVERITY_COLORS[c.severity] ?? 'bg-muted/20 text-muted'}`}>
                    {c.severity}
                  </span>
                </div>
                <div className="space-y-1">
                  {c.articles.map(a => (
                    <div key={a.id} className="flex items-center gap-2 text-sm text-muted">
                      <span className="text-violet">#{a.id}</span>
                      <span className="text-white">{a.title}</span>
                    </div>
                  ))}
                </div>
              </div>
            ))
          )}
        </div>
      )}

      {/* Tab: Gaps */}
      {tab === 'Gaps' && (
        <div className="bg-surface border border-border rounded-xl p-5">
          {gaps.length === 0 ? (
            <p className="text-center py-8 text-muted text-sm">Aucun gap detecte</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-left text-xs text-muted uppercase tracking-wide border-b border-border">
                    <th className="pb-3 pr-4">Mot-cle</th>
                    <th className="pb-3 pr-4">Type</th>
                    <th className="pb-3 pr-4">Priorite</th>
                    <th className="pb-3">Action</th>
                  </tr>
                </thead>
                <tbody>
                  {gaps.map((g, idx) => (
                    <tr key={idx} className="border-b border-border/50">
                      <td className="py-3 pr-4 text-white">{g.keyword}</td>
                      <td className="py-3 pr-4 text-muted text-xs uppercase">{g.type}</td>
                      <td className="py-3 pr-4">
                        <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${SEVERITY_COLORS[g.suggested_priority] ?? 'bg-muted/20 text-muted'}`}>
                          {g.suggested_priority}
                        </span>
                      </td>
                      <td className="py-3">
                        <button className="text-xs text-violet hover:text-violet-light transition-colors">
                          Creer cluster
                        </button>
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
