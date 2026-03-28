import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import api from '../../api/client';

interface City {
  id: number;
  name: string;
  slug: string;
  continent: string;
  guide_url: string | null;
  scraped_at: string | null;
  articles_count: number;
  country: {
    id: number;
    name: string;
    slug: string;
  } | null;
}

interface Article {
  id: number;
  title: string;
  url: string;
  category: string | null;
  section: string;
  word_count: number;
  is_guide: boolean;
  meta_description: string | null;
  scraped_at: string | null;
}

interface Category {
  category: string;
  count: number;
}

interface Profile {
  total_articles: number;
  total_words: number;
  thematic_coverage: number;
  nb_sources: number;
  visa_articles: number;
  emploi_articles: number;
  logement_articles: number;
  sante_articles: number;
  banque_articles: number;
  transport_articles: number;
  culture_articles: number;
}

const THEME_LABELS: Record<string, string> = {
  visa_articles: 'Visa',
  emploi_articles: 'Emploi',
  logement_articles: 'Logement',
  sante_articles: 'Sante',
  banque_articles: 'Banque',
  transport_articles: 'Transport',
  culture_articles: 'Culture',
};

export default function CityProfileDetail() {
  const { citySlug } = useParams<{ citySlug: string }>();
  const [city, setCity] = useState<City | null>(null);
  const [articles, setArticles] = useState<Article[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [profile, setProfile] = useState<Profile | null>(null);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [filterCategory, setFilterCategory] = useState('');

  useEffect(() => {
    if (!citySlug) return;
    api.get(`/content/city-profiles/${citySlug}`)
      .then((res) => {
        setCity(res.data.city);
        setArticles(res.data.articles);
        setCategories(res.data.categories);
        setProfile(res.data.profile);
      })
      .catch(console.error)
      .finally(() => setLoading(false));
  }, [citySlug]);

  const filtered = articles.filter((a) => {
    if (filterCategory && a.category !== filterCategory) return false;
    if (search && !a.title.toLowerCase().includes(search.toLowerCase())) return false;
    return true;
  });

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  if (!city) return <div className="p-6 text-muted">Ville non trouvee</div>;

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      {/* Breadcrumb */}
      <div>
        <div className="flex items-center gap-2 mb-1 text-sm">
          <Link to="/content/cities" className="text-muted hover:text-white transition-colors">Fiches Villes</Link>
          <span className="text-muted">/</span>
          {city.country && (
            <>
              <Link to={`/content/country/${city.country.slug}`} className="text-muted hover:text-white transition-colors">
                {city.country.name}
              </Link>
              <span className="text-muted">/</span>
            </>
          )}
          <span className="text-white">{city.name}</span>
        </div>
        <h1 className="font-title text-2xl font-bold text-white">{city.name}</h1>
        <p className="text-muted text-sm">{city.continent}{city.country ? ` · ${city.country.name}` : ''}</p>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div className="bg-surface border border-border rounded-xl p-3 text-center">
          <div className="text-lg font-bold text-white">{articles.length}</div>
          <div className="text-xs text-muted">Articles</div>
        </div>
        <div className="bg-surface border border-border rounded-xl p-3 text-center">
          <div className="text-lg font-bold text-white">
            {profile?.total_words ? (profile.total_words > 10000 ? `${Math.round(profile.total_words / 1000)}K` : profile.total_words) : '0'}
          </div>
          <div className="text-xs text-muted">Mots totaux</div>
        </div>
        <div className="bg-surface border border-border rounded-xl p-3 text-center">
          <div className="text-lg font-bold text-white">{categories.length}</div>
          <div className="text-xs text-muted">Categories</div>
        </div>
        <div className="bg-surface border border-border rounded-xl p-3 text-center">
          <div className={`text-lg font-bold ${(profile?.thematic_coverage ?? 0) >= 4 ? 'text-green-400' : (profile?.thematic_coverage ?? 0) >= 2 ? 'text-amber-400' : 'text-muted'}`}>
            {profile?.thematic_coverage ?? 0}/7
          </div>
          <div className="text-xs text-muted">Couverture themes</div>
        </div>
      </div>

      {/* Thematic bars */}
      {profile && (
        <div className="bg-surface border border-border rounded-xl p-4">
          <h3 className="text-white font-medium text-sm mb-3">Couverture thematique</h3>
          <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
            {Object.entries(THEME_LABELS).map(([key, label]) => {
              const count = (profile as any)[key] ?? 0;
              return (
                <div key={key} className="text-center">
                  <div className={`text-base font-bold ${count > 0 ? 'text-white' : 'text-muted/30'}`}>{count}</div>
                  <div className="text-[10px] text-muted">{label}</div>
                  <div className={`h-1 rounded-full mt-1 ${count > 0 ? 'bg-violet' : 'bg-surface2'}`} />
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Category badges */}
      {categories.length > 0 && (
        <div className="flex flex-wrap gap-2">
          <button
            onClick={() => setFilterCategory('')}
            className={`px-3 py-1 rounded-full text-xs font-medium transition-colors ${!filterCategory ? 'bg-violet text-white' : 'bg-surface2 text-muted hover:text-white'}`}
          >
            Tous ({articles.length})
          </button>
          {categories.map((c) => (
            <button
              key={c.category}
              onClick={() => setFilterCategory(filterCategory === c.category ? '' : c.category)}
              className={`px-3 py-1 rounded-full text-xs font-medium transition-colors ${filterCategory === c.category ? 'bg-violet text-white' : 'bg-violet/20 text-violet-light hover:bg-violet/30'}`}
            >
              {c.category} ({c.count})
            </button>
          ))}
        </div>
      )}

      {/* Search */}
      <div className="flex items-center gap-3">
        <input
          type="text"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Rechercher un article..."
          className="bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm w-80"
        />
        <span className="text-muted text-sm">{filtered.length} articles</span>
        {city.guide_url && (
          <a
            href={city.guide_url}
            target="_blank"
            rel="noopener noreferrer"
            className="ml-auto px-3 py-1.5 bg-surface2 text-cyan text-xs rounded-lg hover:bg-surface hover:text-white transition-colors"
          >
            Voir sur expat.com &rarr;
          </a>
        )}
      </div>

      {/* Articles table */}
      <div className="bg-surface border border-border rounded-xl overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-border text-left text-muted">
              <th className="px-4 py-3 font-medium">Titre</th>
              <th className="px-4 py-3 font-medium">Categorie</th>
              <th className="px-4 py-3 font-medium">Section</th>
              <th className="px-4 py-3 font-medium text-right">Mots</th>
              <th className="px-4 py-3 font-medium text-center">Guide</th>
            </tr>
          </thead>
          <tbody>
            {filtered.map((a) => (
              <tr key={a.id} className="border-b border-border/50 hover:bg-surface2">
                <td className="px-4 py-2">
                  <a href={a.url} target="_blank" rel="noopener noreferrer" className="text-white font-medium hover:text-violet-light transition-colors">
                    {a.title}
                  </a>
                  {a.meta_description && (
                    <div className="text-xs text-muted truncate max-w-lg mt-0.5">{a.meta_description}</div>
                  )}
                </td>
                <td className="px-4 py-2">
                  {a.category && (
                    <span className="px-2 py-0.5 bg-violet/20 text-violet-light rounded text-xs">{a.category}</span>
                  )}
                </td>
                <td className="px-4 py-2 text-muted text-xs capitalize">{a.section}</td>
                <td className="px-4 py-2 text-right text-white">{a.word_count.toLocaleString()}</td>
                <td className="px-4 py-2 text-center">
                  {a.is_guide && <span className="text-green-400 text-xs font-bold">OUI</span>}
                </td>
              </tr>
            ))}
            {filtered.length === 0 && (
              <tr>
                <td colSpan={5} className="px-4 py-8 text-center text-muted">Aucun article</td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
