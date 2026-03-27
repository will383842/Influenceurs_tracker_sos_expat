import { useEffect, useState, useMemo } from 'react';
import api from '../../api/client';

interface CountrySummary {
  country_code: string; country_name: string; country_slug: string; continent: string;
  total_links: number; official_links: number; with_address: number; with_phone: number;
  categories_count: number; emergency_number: string | null;
}

interface DirectoryStats {
  total_entries: number; countries: number; with_address: number; with_phone: number;
  with_email: number; official: number;
  by_continent: { continent: string; countries: number; links: number }[];
  by_category: { category: string; count: number }[];
}

interface DirectoryEntry {
  id: number; country_code: string; country_name: string; continent: string;
  category: string; sub_category: string | null; title: string; url: string; domain: string;
  description: string | null; address: string | null; city: string | null;
  phone: string | null; phone_emergency: string | null; email: string | null;
  opening_hours: string | null; trust_score: number; is_official: boolean;
  emergency_number: string | null;
}

const CATEGORY_LABELS: Record<string, string> = {
  ambassade: 'Ambassade', immigration: 'Immigration', sante: 'Sante', logement: 'Logement',
  emploi: 'Emploi', telecom: 'Telecom', transport: 'Transport', fiscalite: 'Fiscalite',
  banque: 'Banque', education: 'Education', urgences: 'Urgences', communaute: 'Communaute',
  juridique: 'Juridique',
};

const CONTINENT_LABELS: Record<string, string> = {
  europe: 'Europe', 'amerique-nord': 'Amerique Nord', 'amerique-sud': 'Amerique Sud',
  afrique: 'Afrique', asie: 'Asie', oceanie: 'Oceanie', global: 'Global', autre: 'Autre',
};

const ALL_CATEGORIES = ['ambassade', 'immigration', 'sante', 'logement', 'emploi', 'telecom', 'transport', 'fiscalite', 'banque', 'education', 'urgences', 'communaute', 'juridique'];

function fmt(n: number) { return n.toLocaleString('fr-FR'); }

export default function CountryDirectoryPage() {
  const [countries, setCountries] = useState<CountrySummary[]>([]);
  const [stats, setStats] = useState<DirectoryStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [filterContinent, setFilterContinent] = useState('');
  const [selectedCountry, setSelectedCountry] = useState<string | null>(null);
  const [countryEntries, setCountryEntries] = useState<Record<string, DirectoryEntry[]>>({});
  const [countryLoading, setCountryLoading] = useState(false);

  useEffect(() => {
    Promise.all([
      api.get('/country-directory/countries'),
      api.get('/country-directory/stats'),
    ]).then(([cRes, sRes]) => {
      setCountries(cRes.data);
      setStats(sRes.data);
    }).finally(() => setLoading(false));
  }, []);

  const loadCountry = async (code: string) => {
    setSelectedCountry(code);
    setCountryLoading(true);
    try {
      const res = await api.get(`/country-directory/country/${code}`);
      setCountryEntries(res.data.entries || {});
    } finally {
      setCountryLoading(false);
    }
  };

  const filtered = useMemo(() => {
    let list = countries;
    if (search) {
      const q = search.toLowerCase();
      list = list.filter(c => c.country_name.toLowerCase().includes(q) || c.country_code.toLowerCase().includes(q));
    }
    if (filterContinent) {
      list = list.filter(c => c.continent === filterContinent);
    }
    return list;
  }, [countries, search, filterContinent]);

  const continents = useMemo(() => [...new Set(countries.map(c => c.continent))].sort(), [countries]);

  // Coverage analysis
  const coverageStats = useMemo(() => {
    const total = countries.length;
    const rich = countries.filter(c => c.categories_count >= 5).length;
    const medium = countries.filter(c => c.categories_count >= 3 && c.categories_count < 5).length;
    const poor = countries.filter(c => c.categories_count < 3).length;
    return { total, rich, medium, poor };
  }, [countries]);

  if (loading) return <div className="p-8 text-gray-400 animate-pulse">Chargement annuaire...</div>;

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-white">Annuaire Pays</h1>
        <p className="text-sm text-gray-400 mt-1">country_directory — donnees dynamiques pour sos-expat.com/blog</p>
      </div>

      {/* Stats */}
      {stats && (
        <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
          <StatCard label="Pays" value={fmt(stats.countries)} />
          <StatCard label="Liens total" value={fmt(stats.total_entries)} />
          <StatCard label="Officiels" value={fmt(stats.official)} color="text-emerald-400" />
          <StatCard label="Avec adresse" value={fmt(stats.with_address)} />
          <StatCard label="Avec tel" value={fmt(stats.with_phone)} />
          <StatCard label="Avec email" value={fmt(stats.with_email)} />
          <StatCard label="Pays pauvres (<3 cat)" value={String(coverageStats.poor)} color={coverageStats.poor > 50 ? 'text-red-400' : 'text-amber-400'} />
        </div>
      )}

      {/* Coverage by category */}
      {stats && (
        <div className="bg-gray-800 rounded-lg p-4">
          <h3 className="text-sm font-bold text-white mb-3">Couverture par categorie</h3>
          <div className="space-y-1.5">
            {stats.by_category.map(cat => {
              const pct = Math.round((cat.count / stats.countries) * 100);
              return (
                <div key={cat.category} className="flex items-center gap-2 text-xs">
                  <span className="w-24 text-gray-400 truncate">{CATEGORY_LABELS[cat.category] || cat.category}</span>
                  <div className="flex-1 h-3 bg-gray-700 rounded-full overflow-hidden">
                    <div
                      className={`h-full rounded-full ${pct >= 80 ? 'bg-emerald-500' : pct >= 40 ? 'bg-blue-500' : pct >= 15 ? 'bg-amber-500' : 'bg-red-500'}`}
                      style={{ width: `${pct}%` }}
                    />
                  </div>
                  <span className="w-16 text-right text-gray-400">{cat.count}/{stats.countries}</span>
                  <span className={`w-10 text-right font-bold ${pct >= 80 ? 'text-emerald-400' : pct >= 40 ? 'text-blue-400' : pct >= 15 ? 'text-amber-400' : 'text-red-400'}`}>{pct}%</span>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Search + Filter */}
      <div className="flex gap-3 items-center">
        <input
          type="search" value={search} onChange={e => setSearch(e.target.value)}
          placeholder="Rechercher un pays..."
          className="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white placeholder:text-gray-500 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
        />
        <select
          value={filterContinent} onChange={e => setFilterContinent(e.target.value)}
          className="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white"
        >
          <option value="">Tous continents</option>
          {continents.map(c => (
            <option key={c} value={c}>{CONTINENT_LABELS[c] || c} ({countries.filter(cc => cc.continent === c).length})</option>
          ))}
        </select>
      </div>

      {/* Countries table */}
      <div className="bg-gray-800 rounded-lg overflow-hidden">
        <div className="overflow-x-auto max-h-[600px] overflow-y-auto">
          <table className="w-full text-sm">
            <thead className="sticky top-0 bg-gray-900 z-10">
              <tr className="text-gray-400 border-b border-gray-700 text-xs">
                <th className="text-left py-2 px-2">Pays</th>
                <th className="text-center py-2 px-1">Code</th>
                <th className="text-center py-2 px-1">Continent</th>
                <th className="text-right py-2 px-1">Liens</th>
                <th className="text-right py-2 px-1">Off.</th>
                <th className="text-right py-2 px-1">Adr.</th>
                <th className="text-right py-2 px-1">Tel.</th>
                <th className="text-center py-2 px-1">SOS</th>
                <th className="text-right py-2 px-1">Cat.</th>
                <th className="text-left py-2 px-2">Remplissage</th>
              </tr>
            </thead>
            <tbody>
              {filtered.map(c => {
                const pct = Math.round((c.categories_count / 13) * 100);
                return (
                  <tr key={c.country_code}
                      onClick={() => loadCountry(c.country_code)}
                      className={`border-b border-gray-700/30 hover:bg-gray-700/20 cursor-pointer ${selectedCountry === c.country_code ? 'bg-blue-500/10' : ''}`}>
                    <td className="py-1.5 px-2 font-medium text-white">{c.country_name}</td>
                    <td className="py-1.5 px-1 text-center text-gray-400 text-xs">{c.country_code}</td>
                    <td className="py-1.5 px-1 text-center text-xs">
                      <span className="px-1.5 py-0.5 bg-gray-700 rounded text-gray-300">{CONTINENT_LABELS[c.continent] || c.continent}</span>
                    </td>
                    <td className="py-1.5 px-1 text-right text-white tabular-nums">{c.total_links}</td>
                    <td className="py-1.5 px-1 text-right text-emerald-400 tabular-nums">{c.official_links}</td>
                    <td className="py-1.5 px-1 text-right text-gray-400 tabular-nums">{c.with_address}</td>
                    <td className="py-1.5 px-1 text-right text-gray-400 tabular-nums">{c.with_phone}</td>
                    <td className="py-1.5 px-1 text-center">{c.emergency_number ? <span className="text-red-400 text-xs font-bold">{c.emergency_number}</span> : <span className="text-gray-600">-</span>}</td>
                    <td className="py-1.5 px-1 text-right tabular-nums">
                      <span className={pct >= 50 ? 'text-emerald-400' : pct >= 20 ? 'text-amber-400' : 'text-red-400'}>{c.categories_count}/13</span>
                    </td>
                    <td className="py-1.5 px-2 w-32">
                      <div className="h-2 bg-gray-700 rounded-full overflow-hidden">
                        <div className={`h-full rounded-full ${pct >= 50 ? 'bg-emerald-500' : pct >= 20 ? 'bg-amber-500' : 'bg-red-500'}`} style={{ width: `${pct}%` }} />
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
        <div className="px-3 py-2 text-xs text-gray-500 border-t border-gray-700">
          {filtered.length} pays affiches sur {countries.length}
        </div>
      </div>

      {/* Country detail panel */}
      {selectedCountry && (
        <div className="bg-gray-800 rounded-lg p-4">
          <div className="flex justify-between items-center mb-4">
            <h3 className="text-lg font-bold text-white">
              {countries.find(c => c.country_code === selectedCountry)?.country_name} ({selectedCountry})
            </h3>
            <button onClick={() => setSelectedCountry(null)} className="text-gray-400 hover:text-white">Fermer</button>
          </div>

          {countryLoading ? (
            <div className="text-gray-400 animate-pulse py-4">Chargement...</div>
          ) : (
            <div className="space-y-4">
              {/* Category coverage grid */}
              <div className="flex flex-wrap gap-1.5">
                {ALL_CATEGORIES.map(cat => {
                  const has = !!countryEntries[cat]?.length;
                  return (
                    <span key={cat} className={`px-2 py-1 rounded text-xs font-medium ${has ? 'bg-emerald-500/20 text-emerald-400' : 'bg-gray-700 text-gray-500'}`}>
                      {has ? '✓' : '✗'} {CATEGORY_LABELS[cat] || cat}
                    </span>
                  );
                })}
              </div>

              {/* Entries by category */}
              {Object.entries(countryEntries).map(([cat, entries]) => (
                <div key={cat}>
                  <h4 className="text-sm font-bold text-blue-400 mb-2">{CATEGORY_LABELS[cat] || cat} ({(entries as DirectoryEntry[]).length})</h4>
                  <div className="space-y-1">
                    {(entries as DirectoryEntry[]).map((e: DirectoryEntry) => (
                      <div key={e.id} className="flex items-start gap-2 text-xs bg-gray-900 rounded px-3 py-2">
                        <div className="flex-1 min-w-0">
                          <a href={e.url} target="_blank" rel="noopener" className="text-white hover:text-blue-400 font-medium">
                            {e.title}
                          </a>
                          {e.description && <p className="text-gray-500 mt-0.5 truncate">{e.description}</p>}
                          <div className="flex flex-wrap gap-3 mt-1 text-gray-500">
                            {e.address && <span>📍 {e.address}{e.city ? `, ${e.city}` : ''}</span>}
                            {e.phone && <span>📞 {e.phone}</span>}
                            {e.email && <span>✉ {e.email}</span>}
                            {e.opening_hours && <span>🕐 {e.opening_hours}</span>}
                          </div>
                        </div>
                        <div className="shrink-0 flex flex-col items-end gap-0.5">
                          {e.is_official && <span className="px-1 py-0.5 bg-emerald-500/15 text-emerald-400 rounded text-[10px]">Officiel</span>}
                          <span className="text-gray-600 text-[10px]">{e.domain}</span>
                          <span className={`text-[10px] ${e.trust_score >= 80 ? 'text-emerald-400' : 'text-gray-500'}`}>{e.trust_score}/100</span>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  );
}

function StatCard({ label, value, color = 'text-white' }: { label: string; value: string; color?: string }) {
  return (
    <div className="bg-gray-800 rounded-lg p-3">
      <div className="text-xs text-gray-400">{label}</div>
      <div className={`text-lg font-bold ${color}`}>{value}</div>
    </div>
  );
}
