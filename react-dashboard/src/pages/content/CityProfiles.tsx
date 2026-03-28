import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../../api/client';

interface CityProfile {
  id: number;
  name: string;
  slug: string;
  continent: string;
  country_id: number;
  country_name: string;
  country_slug: string;
  total_articles: number;
  total_words: number;
  nb_sources: number;
  thematic_coverage: number;
  priority_score: number;
  visa: number;
  emploi: number;
  logement: number;
  sante: number;
  banque: number;
  transport: number;
  culture: number;
}

interface Totals {
  cities: number;
  articles: number;
  words: number;
  with_content: number;
}

const THEME_COLORS: Record<string, string> = {
  visa: 'bg-blue-900/40 text-blue-300',
  emploi: 'bg-green-900/40 text-green-300',
  logement: 'bg-amber-900/40 text-amber-300',
  sante: 'bg-red-900/40 text-red-300',
  banque: 'bg-purple-900/40 text-purple-300',
  transport: 'bg-cyan-900/40 text-cyan-300',
  culture: 'bg-pink-900/40 text-pink-300',
};

export default function CityProfiles() {
  const [cities, setCities] = useState<CityProfile[]>([]);
  const [totals, setTotals] = useState<Totals | null>(null);
  const [loading, setLoading] = useState(true);
  const [filterContinent, setFilterContinent] = useState('');
  const [filterCountry, setFilterCountry] = useState('');
  const [search, setSearch] = useState('');
  const [onlyWithContent, setOnlyWithContent] = useState(true);

  useEffect(() => {
    api.get('/content/city-profiles')
      .then((res) => {
        setCities(res.data.cities);
        setTotals(res.data.totals);
      })
      .catch(console.error)
      .finally(() => setLoading(false));
  }, []);

  const continents = [...new Set(cities.map(c => c.continent).filter(Boolean))].sort();
  const countries = filterContinent
    ? [...new Map(cities.filter(c => c.continent === filterContinent).map(c => [c.country_slug, c])).values()]
        .sort((a, b) => a.country_name.localeCompare(b.country_name))
    : [];

  const filtered = cities.filter((c) => {
    if (onlyWithContent && c.total_articles === 0) return false;
    if (filterContinent && c.continent !== filterContinent) return false;
    if (filterCountry && c.country_slug !== filterCountry) return false;
    if (search && !c.name.toLowerCase().includes(search.toLowerCase()) && !c.country_name.toLowerCase().includes(search.toLowerCase())) return false;
    return true;
  });

  // Group by continent then country
  const grouped = filtered.reduce((acc, c) => {
    const contKey = c.continent || 'Autre';
    if (!acc[contKey]) acc[contKey] = {};
    if (!acc[contKey][c.country_slug]) acc[contKey][c.country_slug] = { name: c.country_name, cities: [] };
    acc[contKey][c.country_slug].cities.push(c);
    return acc;
  }, {} as Record<string, Record<string, { name: string; cities: CityProfile[] }>>);

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
        <h1 className="font-title text-2xl font-bold text-white">Fiches Villes</h1>
        <p className="text-muted text-sm mt-1">
          {totals && `${totals.cities.toLocaleString()} villes — ${totals.with_content.toLocaleString()} avec contenu — ${totals.articles.toLocaleString()} articles`}
        </p>
      </div>

      {/* KPIs */}
      {totals && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          {[
            { label: 'Villes totales', value: totals.cities.toLocaleString(), color: 'text-white' },
            { label: 'Avec contenu', value: totals.with_content.toLocaleString(), color: 'text-green-400' },
            { label: 'Articles', value: totals.articles.toLocaleString(), color: 'text-blue-300' },
            { label: 'Mots', value: totals.words > 1000000 ? `${(totals.words / 1000000).toFixed(1)}M` : totals.words.toLocaleString(), color: 'text-violet-light' },
          ].map((k) => (
            <div key={k.label} className="bg-surface border border-border rounded-xl p-3 text-center">
              <div className={`text-lg font-bold ${k.color}`}>{k.value}</div>
              <div className="text-xs text-muted">{k.label}</div>
            </div>
          ))}
        </div>
      )}

      {/* Filters */}
      <div className="flex flex-wrap gap-3 items-center">
        <input
          type="text"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Rechercher une ville ou un pays..."
          className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm w-64"
        />
        <select
          value={filterContinent}
          onChange={(e) => { setFilterContinent(e.target.value); setFilterCountry(''); }}
          className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm"
        >
          <option value="">Tous les continents</option>
          {continents.map((c) => <option key={c} value={c}>{c}</option>)}
        </select>
        {countries.length > 0 && (
          <select
            value={filterCountry}
            onChange={(e) => setFilterCountry(e.target.value)}
            className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm"
          >
            <option value="">Tous les pays</option>
            {countries.map((c) => <option key={c.country_slug} value={c.country_slug}>{c.country_name}</option>)}
          </select>
        )}
        <button
          onClick={() => setOnlyWithContent(!onlyWithContent)}
          className={`px-3 py-2 rounded-lg text-xs font-medium transition-colors ${
            onlyWithContent ? 'bg-violet text-white' : 'bg-surface2 text-muted hover:text-white'
          }`}
        >
          Avec contenu seulement
        </button>
        <span className="text-muted text-sm">{filtered.length} villes</span>
      </div>

      {/* Cities grouped by continent > country */}
      {Object.entries(grouped).sort(([a], [b]) => a.localeCompare(b)).map(([continent, countriesGroup]) => (
        <div key={continent} className="space-y-4">
          <h2 className="text-white font-title font-bold text-lg border-b border-border pb-2">
            {continent}
            <span className="text-muted text-sm font-normal ml-2">
              {Object.values(countriesGroup).reduce((s, g) => s + g.cities.length, 0)} villes
            </span>
          </h2>

          {Object.entries(countriesGroup).sort(([, a], [, b]) => a.name.localeCompare(b.name)).map(([countrySlug, countryGroup]) => (
            <div key={countrySlug} className="space-y-2">
              <div className="flex items-center gap-2">
                <Link
                  to={`/content/country/${countrySlug}`}
                  className="text-sm font-medium text-violet-light hover:text-white transition-colors"
                >
                  {countryGroup.name}
                </Link>
                <span className="text-xs text-muted bg-surface2 px-2 py-0.5 rounded">
                  {countryGroup.cities.length} villes
                </span>
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-2">
                {countryGroup.cities.sort((a, b) => b.total_articles - a.total_articles).map((city) => (
                  <Link
                    key={city.id}
                    to={`/content/cities/${city.slug}`}
                    className="bg-surface border border-border rounded-xl p-3 hover:bg-surface2 hover:border-violet/30 transition-all group"
                  >
                    <div className="text-white font-medium text-sm group-hover:text-violet-light transition-colors mb-1 truncate">
                      {city.name}
                    </div>
                    <div className="grid grid-cols-3 gap-1 text-center mb-2">
                      <div>
                        <div className="text-white text-xs font-bold">{city.total_articles}</div>
                        <div className="text-[10px] text-muted">Art.</div>
                      </div>
                      <div>
                        <div className="text-white text-xs font-bold">
                          {city.total_words > 10000 ? `${Math.round(city.total_words / 1000)}K` : city.total_words}
                        </div>
                        <div className="text-[10px] text-muted">Mots</div>
                      </div>
                      <div>
                        <div className={`text-xs font-bold ${city.thematic_coverage >= 4 ? 'text-green-400' : city.thematic_coverage >= 2 ? 'text-amber-400' : 'text-muted'}`}>
                          {city.thematic_coverage}/7
                        </div>
                        <div className="text-[10px] text-muted">Themes</div>
                      </div>
                    </div>
                    {/* Theme badges */}
                    {city.total_articles > 0 && (
                      <div className="flex flex-wrap gap-1">
                        {(['visa', 'emploi', 'logement', 'sante', 'banque', 'transport', 'culture'] as const).filter(t => city[t] > 0).map(t => (
                          <span key={t} className={`px-1.5 py-0.5 rounded text-[9px] font-medium ${THEME_COLORS[t]}`}>
                            {t}
                          </span>
                        ))}
                      </div>
                    )}
                    {city.total_articles === 0 && (
                      <span className="text-[10px] text-muted/50 italic">Pas de contenu</span>
                    )}
                  </Link>
                ))}
              </div>
            </div>
          ))}
        </div>
      ))}

      {filtered.length === 0 && (
        <div className="bg-surface border border-border rounded-xl p-12 text-center">
          <p className="text-white font-medium mb-1">Aucune ville trouvee</p>
          <p className="text-muted text-sm">Lancez l'import des villes ou le scraping depuis la page des sites</p>
        </div>
      )}
    </div>
  );
}
