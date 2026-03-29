import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../../api/client';

interface CountryProfile {
  id: number;
  name: string;
  slug: string;
  continent: string;
  total_articles: number;
  total_words: number;
  total_links: number;
  total_businesses: number;
}

interface Totals {
  countries: number;
  articles: number;
  words: number;
  links: number;
  businesses: number;
}

export default function CountryProfiles() {
  const [countries, setCountries] = useState<CountryProfile[]>([]);
  const [totals, setTotals] = useState<Totals | null>(null);
  const [loading, setLoading] = useState(true);
  const [filterContinent, setFilterContinent] = useState('');
  const [searchCountry, setSearchCountry] = useState('');

  useEffect(() => {
    api.get('/content/country-profiles')
      .then((res) => {
        setCountries(res.data.countries);
        setTotals(res.data.totals);
      })
      .catch(console.error)
      .finally(() => setLoading(false));
  }, []);

  const continents = [...new Set(countries.map(c => c.continent))].sort();

  const filtered = countries.filter((c) => {
    if (filterContinent && c.continent !== filterContinent) return false;
    if (searchCountry && !c.name.toLowerCase().includes(searchCountry.toLowerCase())) return false;
    return true;
  });

  const grouped = filtered.reduce((acc, c) => {
    const key = c.continent || 'Autre';
    if (!acc[key]) acc[key] = [];
    acc[key].push(c);
    return acc;
  }, {} as Record<string, CountryProfile[]>);

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64" role="status">
        <div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      <div>
        <h1 className="font-title text-2xl font-bold text-white">Fiches Pays</h1>
        <p className="text-muted text-sm mt-1">
          {totals && `${totals.countries ?? 0} pays — ${(totals.articles ?? 0).toLocaleString()} articles — ${(totals.businesses ?? 0).toLocaleString()} entreprises`}
        </p>
      </div>

      {/* KPIs */}
      {totals && (
        <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
          {[
            { label: 'Pays', value: totals.countries ?? 0 },
            { label: 'Articles', value: (totals.articles ?? 0).toLocaleString() },
            { label: 'Mots', value: (totals.words ?? 0) > 1000000 ? `${((totals.words ?? 0) / 1000000).toFixed(1)}M` : (totals.words ?? 0).toLocaleString() },
            { label: 'Liens', value: (totals.links ?? 0).toLocaleString() },
            { label: 'Entreprises', value: (totals.businesses ?? 0).toLocaleString() },
          ].map((k) => (
            <div key={k.label} className="bg-surface border border-border rounded-xl p-3 text-center">
              <div className="text-lg font-bold text-white">{k.value}</div>
              <div className="text-xs text-muted">{k.label}</div>
            </div>
          ))}
        </div>
      )}

      {/* Search + Continent filter */}
      <div className="flex flex-wrap gap-3 items-center">
        <input
          type="text"
          value={searchCountry}
          onChange={(e) => setSearchCountry(e.target.value)}
          placeholder="Rechercher un pays..."
          className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm w-64"
        />
        <div className="flex gap-1.5 flex-wrap">
          <button
            onClick={() => setFilterContinent('')}
            className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-colors ${
              !filterContinent ? 'bg-violet text-white' : 'bg-surface2 text-muted hover:text-white'
            }`}
          >
            Tous ({countries.length})
          </button>
          {continents.map((c) => (
            <button
              key={c}
              onClick={() => setFilterContinent(filterContinent === c ? '' : c)}
              className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-colors ${
                filterContinent === c ? 'bg-violet text-white' : 'bg-surface2 text-muted hover:text-white'
              }`}
            >
              {c} ({countries.filter(co => co.continent === c).length})
            </button>
          ))}
        </div>
      </div>

      {/* Countries grouped by continent */}
      {Object.entries(grouped).sort(([a], [b]) => a.localeCompare(b)).map(([continent, ctries]) => (
        <div key={continent}>
          <div className="flex items-center gap-3 mb-3">
            <h2 className="text-white font-title font-bold text-lg">{continent}</h2>
            <span className="text-xs text-muted bg-surface2 px-2 py-0.5 rounded">
              {ctries.length} pays
            </span>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            {ctries.sort((a, b) => a.name.localeCompare(b.name)).map((c) => (
              <Link
                key={c.id}
                to={`/content/country/${c.slug}`}
                className="bg-surface border border-border rounded-xl p-4 hover:bg-surface2 hover:border-violet/30 transition-all group"
              >
                <div className="text-white font-medium group-hover:text-violet-light transition-colors mb-2">
                  {c.name}
                </div>
                <div className="grid grid-cols-4 gap-2 text-center">
                  <div>
                    <div className="text-white text-sm font-bold">{c.total_articles}</div>
                    <div className="text-[10px] text-muted">Articles</div>
                  </div>
                  <div>
                    <div className="text-white text-sm font-bold">
                      {c.total_words > 10000 ? `${Math.round(c.total_words / 1000)}K` : c.total_words}
                    </div>
                    <div className="text-[10px] text-muted">Mots</div>
                  </div>
                  <div>
                    <div className="text-white text-sm font-bold">{c.total_links}</div>
                    <div className="text-[10px] text-muted">Liens</div>
                  </div>
                  <div>
                    <div className={`text-sm font-bold ${c.total_businesses > 0 ? 'text-green-400' : 'text-muted/30'}`}>
                      {c.total_businesses}
                    </div>
                    <div className="text-[10px] text-muted">Entreprises</div>
                  </div>
                </div>
              </Link>
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}
