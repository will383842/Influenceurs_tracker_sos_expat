import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import api from '../../api/client';

interface City {
  id: number;
  name: string;
  slug: string;
  continent: string;
  articles_count: number;
  scraped_at: string | null;
  country: {
    id: number;
    name: string;
    slug: string;
    continent: string;
  } | null;
}

interface Stats {
  total_cities: number;
  scraped_cities: number;
  pending_cities: number;
  total_city_articles: number;
}

interface ContinentGroup {
  [continent: string]: City[];
}

export default function ContentCities() {
  const { sourceSlug } = useParams<{ sourceSlug: string }>();
  const [cities, setCities] = useState<City[]>([]);
  const [stats, setStats] = useState<Stats | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [filterContinent, setFilterContinent] = useState('');
  const [filterCountry, setFilterCountry] = useState('');
  const [search, setSearch] = useState('');
  const [continents, setContinents] = useState<string[]>([]);
  const [countries, setCountries] = useState<{ id: number; name: string; slug: string }[]>([]);

  useEffect(() => {
    if (!sourceSlug) return;
    const params = new URLSearchParams();
    if (filterContinent) params.set('continent', filterContinent);
    if (filterCountry) params.set('country_slug', filterCountry);

    Promise.all([
      api.get(`/content/sources/${sourceSlug}/cities?${params}`),
      api.get(`/content/sources/${sourceSlug}/city-stats`),
    ]).then(([citiesRes, statsRes]) => {
      setCities(citiesRes.data.cities);
      setContinents(citiesRes.data.continents);
      setCountries(citiesRes.data.countries);
      setStats(statsRes.data);
    }).catch(() => setError('Erreur de chargement'))
      .finally(() => setLoading(false));
  }, [sourceSlug, filterContinent, filterCountry]);

  const filtered = search
    ? cities.filter(c =>
        c.name.toLowerCase().includes(search.toLowerCase()) ||
        c.country?.name.toLowerCase().includes(search.toLowerCase())
      )
    : cities;

  // Grouper par continent
  const grouped: ContinentGroup = {};
  filtered.forEach(city => {
    const cont = city.continent || 'Autre';
    if (!grouped[cont]) grouped[cont] = [];
    grouped[cont].push(city);
  });

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  if (error) {
    return <div className="p-6 text-red-400">{error}</div>;
  }

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <Link to={`/content/sites`} className="text-muted text-sm hover:text-white transition-colors">
              Les Sites
            </Link>
            <span className="text-muted">/</span>
            <Link to={`/content/${sourceSlug}`} className="text-muted text-sm hover:text-white transition-colors">
              {sourceSlug}
            </Link>
            <span className="text-muted">/</span>
            <span className="text-white text-sm">Villes</span>
          </div>
          <h1 className="font-title text-2xl font-bold text-white">Villes Scrapees</h1>
          <p className="text-muted text-sm mt-1">Contenu par ville, classe par pays et continent</p>
        </div>
      </div>

      {/* Stats globales */}
      {stats && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div className="bg-surface border border-border rounded-xl p-4 text-center">
            <div className="text-white font-bold text-2xl">{stats.total_cities.toLocaleString()}</div>
            <div className="text-muted text-xs mt-1">Villes decouvertes</div>
          </div>
          <div className="bg-surface border border-border rounded-xl p-4 text-center">
            <div className="text-green-400 font-bold text-2xl">{stats.scraped_cities.toLocaleString()}</div>
            <div className="text-muted text-xs mt-1">Scrapees</div>
          </div>
          <div className="bg-surface border border-border rounded-xl p-4 text-center">
            <div className="text-amber-400 font-bold text-2xl">{stats.pending_cities.toLocaleString()}</div>
            <div className="text-muted text-xs mt-1">En attente</div>
          </div>
          <div className="bg-surface border border-border rounded-xl p-4 text-center">
            <div className="text-blue-300 font-bold text-2xl">{stats.total_city_articles.toLocaleString()}</div>
            <div className="text-muted text-xs mt-1">Articles villes</div>
          </div>
        </div>
      )}

      {/* Filtres */}
      <div className="flex flex-wrap gap-3">
        <input
          type="text"
          placeholder="Rechercher une ville ou un pays..."
          value={search}
          onChange={e => setSearch(e.target.value)}
          className="bg-surface border border-border rounded-lg px-3 py-2 text-white text-sm w-64"
        />
        <select
          value={filterContinent}
          onChange={e => { setFilterContinent(e.target.value); setFilterCountry(''); }}
          className="bg-surface border border-border rounded-lg px-3 py-2 text-white text-sm"
        >
          <option value="">Tous les continents</option>
          {continents.map(c => <option key={c} value={c}>{c}</option>)}
        </select>
        {countries.length > 0 && (
          <select
            value={filterCountry}
            onChange={e => setFilterCountry(e.target.value)}
            className="bg-surface border border-border rounded-lg px-3 py-2 text-white text-sm"
          >
            <option value="">Tous les pays</option>
            {countries.map(c => <option key={c.slug} value={c.slug}>{c.name}</option>)}
          </select>
        )}
        <span className="text-muted text-sm self-center">{filtered.length} villes</span>
      </div>

      {/* Villes groupées par continent */}
      {Object.entries(grouped).sort().map(([continent, continentCities]) => (
        <div key={continent} className="space-y-3">
          <h2 className="text-white font-title font-bold text-lg border-b border-border pb-2">
            {continent}
            <span className="text-muted text-sm font-normal ml-2">
              {continentCities.length} villes · {continentCities.reduce((s, c) => s + c.articles_count, 0)} articles
            </span>
          </h2>
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
            {continentCities.map(city => (
              <Link
                key={city.id}
                to={`/content/${sourceSlug}/cities/${city.slug}`}
                className="bg-surface border border-border rounded-xl p-3 hover:border-violet/50 hover:bg-violet/5 transition-all"
              >
                <div className="flex items-start justify-between mb-1">
                  <span className="text-white font-medium text-sm truncate">{city.name}</span>
                  {city.scraped_at ? (
                    <span className="w-2 h-2 rounded-full bg-green-400 flex-shrink-0 mt-1 ml-1" title="Scrapee" />
                  ) : (
                    <span className="w-2 h-2 rounded-full bg-amber-400 flex-shrink-0 mt-1 ml-1" title="En attente" />
                  )}
                </div>
                <div className="text-xs text-muted truncate mb-2">{city.country?.name}</div>
                <div className="flex items-center justify-between">
                  <span className="text-blue-400 text-xs font-bold">{city.articles_count} art.</span>
                  {city.scraped_at && (
                    <span className="text-muted text-xs">
                      {new Date(city.scraped_at).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' })}
                    </span>
                  )}
                </div>
              </Link>
            ))}
          </div>
        </div>
      ))}

      {filtered.length === 0 && (
        <div className="bg-surface border border-border rounded-xl p-12 text-center">
          <p className="text-white font-medium mb-1">Aucune ville trouvee</p>
          <p className="text-muted text-sm">Lancez le scraping des villes depuis la page des sites</p>
        </div>
      )}
    </div>
  );
}
