import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import api from '../../api/client';

interface Source {
  id: number;
  name: string;
  slug: string;
  base_url: string;
  status: string;
  total_countries: number;
  total_articles: number;
  total_links: number;
  scraped_countries: number;
  last_scraped_at: string | null;
}

interface Country {
  id: number;
  name: string;
  slug: string;
  continent: string | null;
  articles_count: number;
  scraped_at: string | null;
}

export default function ContentSourcePage() {
  const { sourceSlug } = useParams<{ sourceSlug: string }>();
  const [source, setSource] = useState<Source | null>(null);
  const [countries, setCountries] = useState<Country[]>([]);
  const [continents, setContinents] = useState<string[]>([]);
  const [filterContinent, setFilterContinent] = useState('');
  const [loading, setLoading] = useState(true);
  const [tab, setTab] = useState<'countries' | 'guides'>('countries');
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!sourceSlug) return;
    setLoading(true);
    Promise.all([
      api.get(`/content/sources/${sourceSlug}`),
      api.get(`/content/sources/${sourceSlug}/countries`),
    ]).then(([srcRes, ctryRes]) => {
      setSource(srcRes.data);
      setCountries(ctryRes.data.countries);
      setContinents(ctryRes.data.continents);
    }).catch(console.error).finally(() => setLoading(false));
  }, [sourceSlug]);

  // Auto-refresh while scraping
  useEffect(() => {
    if (source?.status !== 'scraping' || !sourceSlug) return;
    const interval = setInterval(() => {
      Promise.all([
        api.get(`/content/sources/${sourceSlug}`),
        api.get(`/content/sources/${sourceSlug}/countries`),
      ]).then(([srcRes, ctryRes]) => {
        setSource(srcRes.data);
        setCountries(ctryRes.data.countries);
      }).catch(() => {});
    }, 15000);
    return () => clearInterval(interval);
  }, [source?.status, sourceSlug]);

  const handleScrape = async () => {
    if (!sourceSlug) return;
    try {
      await api.post(`/content/sources/${sourceSlug}/scrape`);
      const res = await api.get(`/content/sources/${sourceSlug}`);
      setSource(res.data);
    } catch (err: any) {
      if (err.response?.status === 409) {
        setError('Scraping deja en cours');
        setTimeout(() => setError(null), 5000);
      }
    }
  };

  const filteredCountries = filterContinent
    ? countries.filter((c) => c.continent === filterContinent)
    : countries;

  // Group by continent
  const grouped = filteredCountries.reduce((acc, c) => {
    const key = c.continent || 'Autre';
    if (!acc[key]) acc[key] = [];
    acc[key].push(c);
    return acc;
  }, {} as Record<string, Country[]>);

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  if (!source) return <div className="p-6 text-muted">Source non trouvee</div>;

  const progress = source.total_countries > 0
    ? Math.round((source.scraped_countries / source.total_countries) * 100)
    : 0;

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <div className="flex items-center gap-2">
            <Link to="/content" className="text-muted hover:text-white text-sm">&larr; Content</Link>
            <span className="text-muted">/</span>
          </div>
          <h1 className="font-title text-2xl font-bold text-white mt-1">{source.name}</h1>
          <p className="text-muted text-sm">{source.base_url}</p>
        </div>
        <button
          onClick={handleScrape}
          disabled={source.status === 'scraping'}
          className="px-4 py-2 bg-cyan/20 text-cyan rounded-lg text-sm font-medium hover:bg-cyan/30 disabled:opacity-50 transition-colors"
        >
          {source.status === 'scraping' ? 'Scraping en cours...' : 'Lancer le scraping'}
        </button>
      </div>

      {error && (
        <div className="bg-red-900/20 border border-red-500/30 text-red-400 p-3 rounded-xl text-sm">
          {error}
        </div>
      )}

      {/* Stats */}
      <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
        {[
          { label: 'Pays', value: source.total_countries },
          { label: 'Scrapes', value: `${source.scraped_countries}/${source.total_countries}` },
          { label: 'Articles', value: source.total_articles.toLocaleString() },
          { label: 'Liens', value: source.total_links.toLocaleString() },
          { label: 'Progression', value: `${progress}%` },
        ].map((s) => (
          <div key={s.label} className="bg-surface border border-border rounded-xl p-3 text-center">
            <div className="text-lg font-bold text-white">{s.value}</div>
            <div className="text-xs text-muted">{s.label}</div>
          </div>
        ))}
      </div>

      {/* Progress bar */}
      {source.status === 'scraping' && (
        <div className="bg-surface border border-border rounded-xl p-4">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm text-white">Progression du scraping</span>
            <span className="text-sm text-muted">{progress}%</span>
          </div>
          <div className="h-2 bg-surface2 rounded-full overflow-hidden">
            <div
              className="h-full bg-cyan rounded-full transition-all duration-500"
              style={{ width: `${progress}%` }}
            />
          </div>
        </div>
      )}

      {/* Tabs */}
      <div className="flex gap-1 border-b border-border">
        {(['countries', 'guides'] as const).map((t) => (
          <button
            key={t}
            onClick={() => setTab(t)}
            className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
              tab === t
                ? 'border-violet text-white'
                : 'border-transparent text-muted hover:text-white'
            }`}
          >
            {t === 'countries' ? 'Par pays' : 'Guides'}
          </button>
        ))}
      </div>

      {/* Filter by continent */}
      {tab === 'countries' && continents.length > 0 && (
        <div className="flex gap-2 flex-wrap">
          <button
            onClick={() => setFilterContinent('')}
            className={`px-3 py-1 rounded-full text-xs font-medium transition-colors ${
              !filterContinent ? 'bg-violet text-white' : 'bg-surface2 text-muted hover:text-white'
            }`}
          >
            Tous ({countries.length})
          </button>
          {continents.map((c) => (
            <button
              key={c}
              onClick={() => setFilterContinent(c)}
              className={`px-3 py-1 rounded-full text-xs font-medium transition-colors ${
                filterContinent === c ? 'bg-violet text-white' : 'bg-surface2 text-muted hover:text-white'
              }`}
            >
              {c} ({countries.filter((co) => co.continent === c).length})
            </button>
          ))}
        </div>
      )}

      {/* Countries list */}
      {tab === 'countries' && (
        <div className="space-y-6">
          {Object.entries(grouped).sort(([a], [b]) => a.localeCompare(b)).map(([continent, ctries]) => (
            <div key={continent}>
              <h3 className="text-white font-medium text-sm mb-2">{continent}</h3>
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-2">
                {ctries.sort((a, b) => a.name.localeCompare(b.name)).map((c) => (
                  <Link
                    key={c.id}
                    to={`/content/${sourceSlug}/${c.slug}`}
                    className="bg-surface border border-border rounded-lg p-3 hover:bg-surface2 hover:border-violet/30 transition-colors flex items-center justify-between"
                  >
                    <div>
                      <div className="text-white text-sm font-medium">{c.name}</div>
                      <div className="text-xs text-muted">{c.articles_count} articles</div>
                    </div>
                    {c.scraped_at ? (
                      <span className="w-2 h-2 rounded-full bg-green-400 flex-shrink-0" title="Scrape" />
                    ) : (
                      <span className="w-2 h-2 rounded-full bg-gray-600 flex-shrink-0" title="En attente" />
                    )}
                  </Link>
                ))}
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Guides tab placeholder */}
      {tab === 'guides' && (
        <div className="text-muted text-sm p-4">
          Les guides principaux (is_guide=true) seront affiches ici apres le scraping.
        </div>
      )}
    </div>
  );
}
